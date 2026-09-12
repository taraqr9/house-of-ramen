<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Phone;
use App\Services\Recommendation\PhoneRecommendationCriteria;
use App\Services\Recommendation\PhoneRecommendationEngine;
use App\Services\Seo\SeoMeta;
use App\Services\SeoLanding\SeoLandingPageRegistry;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function index(PhoneRecommendationEngine $engine): Response
    {
        $exampleCriteria = PhoneRecommendationCriteria::fromArray([
            'max_budget' => 25000,
            'importance' => ['camera' => 4, 'battery' => 4],
        ]);
        $exampleResult = $engine->recommend($exampleCriteria, 1)[0] ?? null;

        return Inertia::render('Public/Home', [
            'exampleResult' => $exampleResult,
            'priceBrackets' => $this->priceBracketsWithCounts(),
            // Same reference data FindMyPhoneController::index() passes to
            // the standalone questionnaire page - the embedded homepage
            // questionnaire (FindMyPhoneForm component) needs it too. Not
            // recommendation logic, just the option lists the form renders;
            // scoring/ranking still only ever happens in
            // PhoneRecommendationEngine, called from the one results()
            // action both entry points post to.
            'brands' => Brand::query()
                ->where('is_active', true)
                ->whereHas('phones', fn ($q) => $q->publiclyVisible())
                ->orderBy('name')
                ->get(['id', 'name', 'slug']),
            'dimensions' => config('phone_recommendation.dimensions'),
            // whereHas (not ->having() on the withCount alias) - HAVING on a
            // withCount subquery without an explicit GROUP BY works on MySQL
            // but not SQLite (used in tests), and whereHas is what the rest
            // of the app already uses for "brand has active phones".
            'popularBrands' => Brand::query()
                ->where('is_active', true)
                ->whereHas('phones', fn ($q) => $q->publiclyVisible())
                ->withCount(['phones' => fn ($q) => $q->publiclyVisible()])
                ->orderByDesc('phones_count')
                ->limit(8)
                ->get(['id', 'name', 'slug'])
                ->map(fn (Brand $brand) => ['name' => $brand->name, 'slug' => $brand->slug, 'count' => $brand->phones_count]),
            'popularSearches' => array_map(
                fn ($page) => ['slug' => $page->slug, 'heading' => $page->heading],
                SeoLandingPageRegistry::all(),
            ),
            'stats' => [
                'phone_count' => Phone::query()->publiclyVisible()->count(),
                'brand_count' => Brand::query()->where('is_active', true)->whereHas('phones', fn ($q) => $q->publiclyVisible())->count(),
            ],
            'seo' => SeoMeta::make(
                config('seo.default_title'),
                config('seo.default_description'),
                '/',
            )->toArray(),
        ]);
    }

    /**
     * @return list<array{label: string, max_budget: ?int, count: int}>
     */
    protected function priceBracketsWithCounts(): array
    {
        // Same base query as PhoneController::index()'s actual
        // max_budget filter (publiclyVisible() + the aggregated,
        // outlier-resistant marketPrices - never raw phone_prices) so a
        // bracket's displayed count can never drift from what clicking it
        // actually returns. One query for every phone's cheapest current
        // market price, then bucket in PHP - cheaper than one COUNT query
        // per bracket on every page view.
        //
        // Deliberately has no image requirement: gating a phone's
        // eligibility for these counts behind having a verified image
        // previously reproduced the exact bug scopePubliclyVisible()'s own
        // docblock warns about (see App\Models\Phone) - it silently
        // shrank "phones under X" down to "phones under X WITH a
        // verified image", undercounting real catalogue results by
        // several times over.
        // Region-scoped the same way as PhoneController::index() (Global
        // preferred, Chinese only as a fallback) - see Phone::displayMarketPrice().
        $cheapestPrices = Phone::query()
            ->publiclyVisible()
            ->withDisplayMarketPrice()
            ->get()
            ->map(fn (Phone $phone) => $phone->displayMarketPrice());

        return collect(config('phone_kinbo.price_brackets'))->map(function (array $bracket) use ($cheapestPrices) {
            $count = $cheapestPrices
                ->filter(fn ($price) => $price !== null)
                ->filter(fn ($price) => $bracket['max_budget'] === null || (float) $price <= $bracket['max_budget'])
                ->count();

            return [...$bracket, 'count' => $count];
        })->all();
    }
}
