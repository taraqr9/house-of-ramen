<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\PhoneRecommendationRequest;
use App\Services\Recommendation\PhoneRecommendationCriteria;
use App\Services\Recommendation\PhoneRecommendationEngine;
use App\Services\Seo\SeoMeta;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The questionnaire itself is embedded on the homepage (see
 * HomeController) - this controller only handles the results() step.
 * It does nothing but validate input and hand the criteria straight to
 * PhoneRecommendationEngine - every scoring/ranking/explanation
 * decision still lives entirely in the Recommendation service layer.
 */
class FindMyPhoneController extends Controller
{
    public function results(PhoneRecommendationRequest $request, PhoneRecommendationEngine $engine): Response
    {
        $criteria = PhoneRecommendationCriteria::fromArray($request->validated());

        // Up to 9 (down from the engine's 3-result default) so the results
        // grid has enough cards to fill 3 full rows on desktop - degrades
        // gracefully to however many candidates are actually eligible when
        // fewer exist (CandidateFilter already returns at most one entry
        // per phone, so this can never surface a duplicate model/variant).
        $results = $engine->recommend($criteria, resultCount: 9);

        return Inertia::render('Public/Results', [
            'results' => $results,
            'criteria' => [
                'max_budget' => $criteria->maxBudget,
                'primary_usage' => $criteria->primaryUsage,
                'price_preference' => $criteria->pricePreference,
                'preferred_brand_ids' => $criteria->preferredBrandIds,
            ],
            // Reachable only via POST (see resultsFallback below), so it can
            // never actually be indexed - noindex is defense in depth, not
            // the thing doing the real work here (see prompt section 15).
            'seo' => SeoMeta::make(
                'Your matches',
                'Personalised phone recommendations from Phone Kinbo.',
                '/',
            )->noindex()->toArray(),
        ]);
    }

    /**
     * A direct GET to the results URL (refresh, shared link, back-button
     * after the session ended) has no questionnaire answers to score -
     * send the visitor back to the homepage questionnaire rather than a
     * bare error page.
     */
    public function resultsFallback(): RedirectResponse
    {
        return redirect('/#find-my-phone');
    }
}
