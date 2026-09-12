<?php

namespace App\Services\Recommendation;

/**
 * Generates human-readable reasons/tradeoffs purely from the scores and
 * criteria already computed - no external AI call, no free text stored
 * anywhere. Every sentence traces back to a concrete number so it stays
 * honest about being a fit assessment, not an objective ranking.
 */
class RecommendationExplainer
{
    protected const LABELS = [
        'performance' => 'Performance',
        'gaming' => 'Gaming performance',
        'camera' => 'Camera',
        'battery' => 'Battery life',
        'display' => 'Display',
        'software' => 'Software support',
        'build' => 'Build quality',
        'charging' => 'Charging speed',
        'value' => 'Value for money',
    ];

    protected const STRONG_THRESHOLD = 80;

    protected const WEAK_THRESHOLD = 60;

    protected const HIGH_IMPORTANCE = 4;

    /**
     * @param  list<ScoredPhoneCandidate>  $allRanked  the full scored/ranked pool, used for comparative statements.
     * @return array{reasons: list<string>, tradeoffs: list<string>}
     */
    public function explain(ScoredPhoneCandidate $target, array $allRanked, PhoneRecommendationCriteria $criteria): array
    {
        return [
            'reasons' => array_slice($this->buildReasons($target, $allRanked, $criteria), 0, 4),
            'tradeoffs' => array_slice($this->buildTradeoffs($target, $allRanked, $criteria), 0, 3),
        ];
    }

    /**
     * @param  list<ScoredPhoneCandidate>  $allRanked
     * @return list<string>
     */
    protected function buildReasons(ScoredPhoneCandidate $target, array $allRanked, PhoneRecommendationCriteria $criteria): array
    {
        $reasons = [];

        foreach (config('phone_recommendation.dimensions') as $dimension) {
            if ($dimension === 'value') {
                continue;
            }

            if ($criteria->importanceFor($dimension) >= self::HIGH_IMPORTANCE && $target->dimension($dimension) >= self::STRONG_THRESHOLD) {
                $reasons[] = self::LABELS[$dimension].' is excellent for your priorities.';
            }
        }

        if ($criteria->hasBudget() && $target->candidate->priceAmount() <= 0.7 * $criteria->maxBudget) {
            $reasons[] = 'Comfortably fits within your budget.';
        }

        $years = (new PhoneScorer)->yearsSinceRelease($target->candidate->phone->release_date);
        $isBestValue = $this->isBestInPool($target, $allRanked, 'value');

        if ($isBestValue) {
            // Strictly stronger claim than the generic "older but good value"
            // one below - showing both would just repeat the same point.
            $reasons[] = 'The best value for money among your matches.';
        } elseif ($years >= 1.5 && $target->dimension('value') >= self::STRONG_THRESHOLD) {
            $reasons[] = 'Older model, but currently offers excellent value for the price.';
        }

        if (in_array($target->candidate->phone->brand_id, $criteria->preferredBrandIds, true)) {
            $reasons[] = 'Matches one of your preferred brands.';
        }

        if (empty($reasons)) {
            $reasons[] = 'A solid all-round match based on your priorities.';
        }

        return $reasons;
    }

    /**
     * @param  list<ScoredPhoneCandidate>  $allRanked
     * @return list<string>
     */
    protected function buildTradeoffs(ScoredPhoneCandidate $target, array $allRanked, PhoneRecommendationCriteria $criteria): array
    {
        $tradeoffs = [];

        // Surfaced first, ahead of every dimension-based tradeoff below -
        // a buyer needs to know this is the Chinese-market version (not
        // the Global one they'd get by default) before anything else,
        // never buried past the 3-item cap in explain().
        if ($target->candidate->isChineseVariant()) {
            $tradeoffs[] = 'This is the Chinese-market version, not the Global one - check firmware, language support, and warranty coverage before buying.';
        }

        foreach (config('phone_recommendation.dimensions') as $dimension) {
            if ($criteria->importanceFor($dimension) >= self::HIGH_IMPORTANCE && $target->dimension($dimension) < self::WEAK_THRESHOLD) {
                $tradeoffs[] = self::LABELS[$dimension].' is weaker than you may want.';

                continue;
            }

            if ($this->isNotablyBelowAlternatives($target, $allRanked, $dimension)) {
                $tradeoffs[] = self::LABELS[$dimension].' is weaker than the alternatives.';
            }
        }

        if ($criteria->hasBudget() && $target->candidate->priceAmount() >= 0.95 * $criteria->maxBudget) {
            $tradeoffs[] = 'Close to your maximum budget.';
        }

        $confidence = $target->candidate->phone->overall_confidence;

        if ($confidence !== null && $confidence < config('phone_confidence.auto_approve_threshold')) {
            $tradeoffs[] = 'Some specifications for this phone are still awaiting verification.';
        }

        return array_values(array_unique($tradeoffs));
    }

    /**
     * @param  list<ScoredPhoneCandidate>  $allRanked
     */
    protected function isBestInPool(ScoredPhoneCandidate $target, array $allRanked, string $dimension): bool
    {
        if (count($allRanked) < 2) {
            return false;
        }

        $max = max(array_map(fn (ScoredPhoneCandidate $c) => $c->dimension($dimension), $allRanked));

        return $target->dimension($dimension) >= $max && $max > 0;
    }

    /**
     * @param  list<ScoredPhoneCandidate>  $allRanked
     */
    protected function isNotablyBelowAlternatives(ScoredPhoneCandidate $target, array $allRanked, string $dimension): bool
    {
        $others = array_filter($allRanked, fn (ScoredPhoneCandidate $c) => $c !== $target);

        if (empty($others)) {
            return false;
        }

        $average = array_sum(array_map(fn (ScoredPhoneCandidate $c) => $c->dimension($dimension), $others)) / count($others);

        return $target->dimension($dimension) <= $average - 15;
    }
}
