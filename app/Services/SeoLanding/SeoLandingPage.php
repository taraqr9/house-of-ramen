<?php

namespace App\Services\SeoLanding;

use App\Services\Recommendation\PhoneRecommendationCriteria;

/**
 * One approved, controlled SEO landing page: what to rank (via the real
 * recommendation engine - see SeoLandingPageController) and how to talk
 * about it. Deliberately a plain value object registered in code (see
 * SeoLandingPageRegistry), not a database table - the brief is explicit
 * that only a small, curated set of these should ever exist, and a
 * hardcoded list is what makes "only approved slugs are routable" a
 * property of the code rather than something that has to be checked at
 * runtime.
 */
final class SeoLandingPage
{
    /**
     * @param  string  $slug  Route path segment, e.g. "best-phones-under-15000".
     * @param  string  $heading  Visible H1 / breadcrumb label.
     * @param  int|null  $maxBudget  Budget ceiling in BDT, or null for no cap.
     * @param  string|null  $category  A key from config('phone_recommendation.dimensions'), or null for a pure budget page.
     * @param  string  $categoryLabel  Human label for the category, e.g. "gaming" - used in copy, empty for budget-only pages.
     */
    public function __construct(
        public readonly string $slug,
        public readonly string $heading,
        public readonly ?int $maxBudget,
        public readonly ?string $category = null,
        public readonly string $categoryLabel = '',
    ) {}

    public function criteria(): PhoneRecommendationCriteria
    {
        return PhoneRecommendationCriteria::fromArray([
            'max_budget' => $this->maxBudget,
            'primary_usage' => $this->category,
            'importance' => $this->category ? [$this->category => 5] : [],
        ]);
    }

    public function isBudgetOnly(): bool
    {
        return $this->category === null;
    }
}
