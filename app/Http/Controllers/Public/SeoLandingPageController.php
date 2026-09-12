<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Services\Recommendation\PhoneRecommendationEngine;
use App\Services\SeoLanding\SeoLandingPagePresenter;
use App\Services\SeoLanding\SeoLandingPageRegistry;
use Inertia\Inertia;
use Inertia\Response;

/**
 * One controller for every approved SEO landing page (see
 * SeoLandingPageRegistry) - the route's regex constraint (routes/web.php)
 * only ever dispatches here for a registered slug, so there is no
 * per-page controller to write as the catalogue of pages grows. Ranking
 * is delegated entirely to the real PhoneRecommendationEngine (the same
 * one Find My Phone uses) - this controller never scores or orders
 * phones itself.
 */
class SeoLandingPageController extends Controller
{
    public function show(string $seoSlug, PhoneRecommendationEngine $engine, SeoLandingPagePresenter $presenter): Response
    {
        $page = SeoLandingPageRegistry::find($seoSlug);

        abort_unless($page, 404);

        $ranked = $engine->rank($page->criteria());
        $results = $engine->recommend($page->criteria(), resultCount: 8);

        return Inertia::render('Public/SeoLanding/Show', $presenter->present($page, $results, totalEligible: count($ranked)));
    }
}
