<?php

namespace App\Services\SeoLanding;

use App\Services\Seo\SeoMeta;

/**
 * Turns a SeoLandingPage's config plus the recommendation engine's
 * already-ranked, already-scored output into what the Vue page needs.
 * Deliberately separate from the controller: this is the "how do we talk
 * about it" layer, the controller/engine stay "what qualifies and how is
 * it ranked". Reuses App\Services\Seo\SeoMeta - no second metadata
 * system - and builds its own short, factual, third-person strength
 * chips from the engine's real per-dimension scores rather than reusing
 * RecommendationExplainer's reasons/tradeoffs, which are phrased for the
 * personalized Find My Phone quiz ("matches your priorities") and would
 * read as broken copy addressed to nobody on an anonymous landing page.
 */
class SeoLandingPagePresenter
{
    protected const DIMENSION_LABELS = [
        'performance' => 'Performance',
        'gaming' => 'Gaming',
        'camera' => 'Camera',
        'battery' => 'Battery',
        'display' => 'Display',
        'software' => 'Software',
        'build' => 'Build',
        'charging' => 'Fast charging',
    ];

    protected const STRONG_THRESHOLD = 75;

    protected const CHIPS_PER_PHONE = 2;

    /**
     * @param  list<array<string, mixed>>  $results  PhoneRecommendationEngine::recommend()'s formatted output.
     * @return array<string, mixed>
     */
    public function present(SeoLandingPage $page, array $results, int $totalEligible): array
    {
        return [
            'heading' => $page->heading,
            'isBudgetOnly' => $page->isBudgetOnly(),
            'categoryLabel' => $page->categoryLabel,
            'maxBudget' => $page->maxBudget,
            'totalEligible' => $totalEligible,
            'results' => array_map(fn (array $result) => $this->presentResult($result), $results),
            'breadcrumbs' => [
                ['label' => 'Home', 'href' => '/'],
                ['label' => $page->heading, 'href' => null],
            ],
            'relatedPages' => array_map(
                fn (SeoLandingPage $related) => ['slug' => $related->slug, 'heading' => $related->heading],
                SeoLandingPageRegistry::relatedTo($page),
            ),
            'seo' => $this->seo($page, $totalEligible)->toArray(),
        ];
    }

    /**
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    protected function presentResult(array $result): array
    {
        return [
            'phone_slug' => $result['phone_slug'],
            'phone_name' => $result['phone_name'],
            'brand' => $result['brand'],
            'image_url' => $result['image_url'],
            // Same {official, unofficial} shape PhoneController/
            // CompareController already pass to MarketPriceSummary.vue -
            // reusing that component instead of a third ad-hoc price
            // shape is the literal "use the existing pricing
            // architecture" from prompt section 10.
            'market' => [
                'official' => $result['official_price'] !== null ? ['price' => $result['official_price']] : null,
                'unofficial' => $result['unofficial_price'] !== null ? [
                    'price' => $result['unofficial_price'],
                    'has_range' => $result['unofficial_price_range'] !== null,
                    'price_min' => $result['unofficial_price_range']['min'] ?? null,
                    'price_max' => $result['unofficial_price_range']['max'] ?? null,
                ] : null,
            ],
            'match_score' => $result['match_score'],
            'strengths' => $this->strengthChips($result['score_breakdown']),
        ];
    }

    /**
     * Top real strengths for this phone, e.g. "Strong camera", derived
     * directly from its own computed dimension scores - never opinion,
     * never a fabricated claim.
     *
     * @param  array<string, float|int>  $scores
     * @return list<string>
     */
    protected function strengthChips(array $scores): array
    {
        return collect($scores)
            ->except('value')
            ->filter(fn ($score) => $score >= self::STRONG_THRESHOLD)
            ->sortDesc()
            ->keys()
            ->take(self::CHIPS_PER_PHONE)
            ->map(fn (string $dimension) => (self::DIMENSION_LABELS[$dimension] ?? ucfirst($dimension)))
            ->all();
    }

    protected function seo(SeoLandingPage $page, int $totalEligible): SeoMeta
    {
        $year = now()->format('Y');
        $budgetLabel = '৳'.number_format($page->maxBudget);

        // $page->heading already starts with "Best" for every registered
        // page (e.g. "Best Gaming Phones Under ৳25,000") - reuse it as-is
        // rather than prepending "Best" again.
        $title = "{$page->heading} in Bangladesh ({$year})";

        $description = $page->isBudgetOnly()
            ? "{$totalEligible} phones under {$budgetLabel} tracked in Bangladesh. Compare real official and unofficial prices and specs, ranked by real performance data - not sponsored placements."
            : "{$totalEligible} phones under {$budgetLabel} tracked in Bangladesh, ranked for {$page->categoryLabel} performance using real specs. Compare official and unofficial prices on Phone Kinbo.";

        $seo = SeoMeta::make($title, $description, "/{$page->slug}");

        // An evergreen "best of" page with nothing to show yet is exactly
        // the thin/empty content Google penalizes - reflect that live
        // rather than indexing a shell (see prompt section 4's "the page
        // should automatically reflect the new state", extended to
        // indexability itself, not just displayed content).
        if ($totalEligible === 0) {
            $seo->noindex();
        }

        return $seo;
    }
}
