<?php

namespace App\Services\Recommendation;

use App\Enums\PriceTypeEnum;
use App\Models\Phone;

/**
 * Orchestrates the full pipeline: filter eligible candidates (hard
 * requirements) -> score every dimension -> apply pool-relative value,
 * lifecycle, and confidence adjustments -> weight by the user's
 * priorities -> rank deterministically -> explain. Controllers must not
 * duplicate any of this logic - they only call recommend().
 */
class PhoneRecommendationEngine
{
    public function __construct(
        protected CandidateFilter $filter,
        protected PhoneScorer $scorer,
        protected ValueScorer $valueScorer,
        protected PhoneLifecycleCalculator $lifecycle,
        protected RecommendationExplainer $explainer,
    ) {}

    /**
     * The primary entry point: top-N ranked, explained recommendations
     * ready to hand to a future Vue/Inertia view.
     *
     * @return list<array<string, mixed>>
     */
    public function recommend(PhoneRecommendationCriteria $criteria, ?int $resultCount = null): array
    {
        $ranked = $this->rank($criteria);
        $count = $resultCount ?? (int) config('phone_recommendation.result_count');

        return $this->format(array_slice($ranked, 0, max(0, $count)), $ranked, $criteria);
    }

    /**
     * Every eligible candidate, fully scored and deterministically
     * ordered best-first. Exposed separately so future "best camera" /
     * "best gaming" / "best battery" endpoints can reuse the same scored
     * pool without re-running the pipeline.
     *
     * @return list<ScoredPhoneCandidate>
     */
    public function rank(PhoneRecommendationCriteria $criteria): array
    {
        $candidates = $this->filter->candidates($criteria);

        if ($candidates->isEmpty()) {
            return [];
        }

        $pool = $candidates->map(fn (PhoneCandidate $candidate) => [
            'candidate' => $candidate,
            'dimensions' => $this->baseDimensions($candidate),
        ])->values()->all();

        foreach ($pool as $i => $entry) {
            $pool[$i]['quality'] = array_sum($entry['dimensions']) / count($entry['dimensions']);
        }

        $valueScores = $this->valueScorer->scoreAll($pool);

        $scored = [];

        foreach ($pool as $i => $entry) {
            $scored[] = $this->buildScoredCandidate($entry['candidate'], $entry['dimensions'] + ['value' => $valueScores[$i]], $criteria);
        }

        usort($scored, fn (ScoredPhoneCandidate $a, ScoredPhoneCandidate $b) => $this->compare($a, $b));

        return $scored;
    }

    /**
     * Best-scoring candidate for a single dimension (e.g. 'camera',
     * 'gaming') from an already-ranked pool - the building block for
     * "Best camera" / "Best gaming" / "Best battery" style results.
     */
    public function bestByDimension(array $rankedPool, string $dimension): ?ScoredPhoneCandidate
    {
        if (empty($rankedPool)) {
            return null;
        }

        $sorted = $rankedPool;
        usort($sorted, fn (ScoredPhoneCandidate $a, ScoredPhoneCandidate $b) => $b->dimension($dimension) <=> $a->dimension($dimension)
            ?: $this->compare($a, $b));

        return $sorted[0];
    }

    /**
     * @return array<string, int>
     */
    protected function baseDimensions(PhoneCandidate $candidate): array
    {
        return [
            'performance' => $this->scorer->performance($candidate),
            'gaming' => $this->scorer->gaming($candidate),
            'camera' => $this->scorer->camera($candidate),
            'battery' => $this->scorer->battery($candidate),
            'display' => $this->scorer->display($candidate),
            'software' => $this->scorer->software($candidate),
            'build' => $this->scorer->build($candidate),
            'charging' => $this->scorer->charging($candidate),
        ];
    }

    /**
     * @param  array<string, int>  $dimensions
     */
    protected function buildScoredCandidate(PhoneCandidate $candidate, array $dimensions, PhoneRecommendationCriteria $criteria): ScoredPhoneCandidate
    {
        $weightedSum = 0;
        $weightTotal = 0;

        foreach (config('phone_recommendation.dimensions') as $dimension) {
            $weight = $criteria->weightFor($dimension);
            $weightedSum += $dimensions[$dimension] * $weight;
            $weightTotal += $weight;
        }

        $composite = $weightTotal > 0 ? $weightedSum / $weightTotal : array_sum($dimensions) / count($dimensions);

        if (in_array($candidate->phone->brand_id, $criteria->preferredBrandIds, true)) {
            $composite += config('phone_recommendation.preferred_brand_bonus');
        }

        $lifecycleMultiplier = $this->lifecycle->multiplier($candidate, $this->scorer);
        $confidenceMultiplier = $this->confidenceMultiplier($candidate);

        $finalScore = min(100, max(0, $composite * $lifecycleMultiplier * $confidenceMultiplier));

        return new ScoredPhoneCandidate($candidate, $dimensions, $lifecycleMultiplier, $confidenceMultiplier, round($finalScore, 1));
    }

    protected function confidenceMultiplier(PhoneCandidate $candidate): float
    {
        $confidence = $candidate->phone->overall_confidence ?? config('phone_recommendation.confidence_default');
        $min = config('phone_recommendation.confidence_multiplier_min');
        $max = config('phone_recommendation.confidence_multiplier_max');

        return $min + ($confidence / 100) * ($max - $min);
    }

    /**
     * Deterministic ordering: highest match score first; ties broken by
     * data confidence, then lower price, then phone id - so identical
     * input always produces identical output, never arbitrary DB order.
     */
    protected function compare(ScoredPhoneCandidate $a, ScoredPhoneCandidate $b): int
    {
        return $b->matchScore <=> $a->matchScore
            ?: ($b->candidate->phone->overall_confidence ?? 0) <=> ($a->candidate->phone->overall_confidence ?? 0)
            ?: $a->candidate->priceAmount() <=> $b->candidate->priceAmount()
            ?: $a->candidate->phone->id <=> $b->candidate->phone->id;
    }

    /**
     * @param  list<ScoredPhoneCandidate>  $top
     * @param  list<ScoredPhoneCandidate>  $allRanked
     * @return list<array<string, mixed>>
     */
    protected function format(array $top, array $allRanked, PhoneRecommendationCriteria $criteria): array
    {
        return collect($top)->values()->map(function (ScoredPhoneCandidate $scored, int $index) use ($allRanked, $criteria) {
            $phone = $scored->candidate->phone;
            $variant = $scored->candidate->variant;
            $marketPrice = $scored->candidate->marketPrice;
            $explanation = $this->explainer->explain($scored, $allRanked, $criteria);

            // Both markets are looked up independently of which one was
            // actually eligible/used for scoring, so the UI can always show
            // "also available unofficially at X" even under an official-only
            // preference - transparency about the real market, not just the
            // number that decided ranking.
            $official = $variant->marketPrices->firstWhere('price_type', PriceTypeEnum::OFFICIAL_BD);
            $unofficial = $variant->marketPrices->firstWhere('price_type', PriceTypeEnum::UNOFFICIAL_BD);

            return [
                'rank' => $index + 1,
                'phone_id' => $phone->id,
                'phone_slug' => $phone->slug,
                'phone_name' => $this->displayName($phone),
                'image_url' => $phone->primaryImage?->url,
                'brand' => $phone->brand->name,
                'brand_id' => $phone->brand_id,
                'variant_id' => $variant->id,
                'variant_label' => $variant->label(),
                'variant_region' => $scored->candidate->region()->label(),
                'is_chinese_variant' => $scored->candidate->isChineseVariant(),
                'match_score' => $scored->matchScore,
                'price' => (float) $marketPrice->price,
                'price_type' => $marketPrice->price_type->value,
                'is_official_bd' => $marketPrice->price_type === PriceTypeEnum::OFFICIAL_BD,
                'price_retailer_count' => $marketPrice->retailer_count,
                'price_last_checked_at' => $marketPrice->calculated_at?->toIso8601String(),
                'official_price' => $official ? (float) $official->price : null,
                'unofficial_price' => $unofficial ? (float) $unofficial->price : null,
                'unofficial_price_range' => $unofficial && $unofficial->hasPriceRange()
                    ? ['min' => (float) $unofficial->price_min, 'max' => (float) $unofficial->price_max]
                    : null,
                'confidence' => $phone->overall_confidence,
                'score_breakdown' => $scored->dimensionScores,
                'reasons' => $explanation['reasons'],
                'tradeoffs' => $explanation['tradeoffs'],
            ];
        })->all();
    }

    /**
     * "{Brand} {Model}", without duplicating the brand when the phone's
     * own name already starts with it (e.g. a model imported as "POCO X6
     * Pro" under brand "POCO" must read as "POCO X6 Pro", not
     * "POCO POCO X6 Pro").
     */
    protected function displayName(Phone $phone): string
    {
        $brand = trim($phone->brand->name);
        $name = trim($phone->name);

        if (str_starts_with(strtolower($name), strtolower($brand))) {
            return $name;
        }

        return trim($brand.' '.$name);
    }
}
