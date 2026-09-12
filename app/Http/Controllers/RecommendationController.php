<?php

namespace App\Http\Controllers;

use App\Http\Requests\PhoneRecommendationRequest;
use App\Services\Recommendation\PhoneRecommendationCriteria;
use App\Services\Recommendation\PhoneRecommendationEngine;
use Illuminate\Http\JsonResponse;

/**
 * Public entry point for the recommendation engine. Deliberately thin -
 * all scoring/ranking/explanation logic lives in the Recommendation
 * service layer (see app/Services/Recommendation), never here.
 */
class RecommendationController extends Controller
{
    public function recommend(PhoneRecommendationRequest $request, PhoneRecommendationEngine $engine): JsonResponse
    {
        $criteria = PhoneRecommendationCriteria::fromArray($request->validated());

        $results = $engine->recommend($criteria, $request->validated('result_count'));

        return response()->json([
            'criteria' => [
                'max_budget' => $criteria->maxBudget,
                'primary_usage' => $criteria->primaryUsage,
                'price_preference' => $criteria->pricePreference,
            ],
            'count' => count($results),
            'results' => $results,
        ]);
    }
}
