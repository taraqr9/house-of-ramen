<?php

namespace App\Services\Presentation;

use App\Enums\PriceTypeEnum;
use App\Models\Phone;
use App\Models\PhoneMarketPrice;
use App\Services\Recommendation\PhoneCandidate;
use App\Services\Recommendation\PhoneScorer;

/**
 * A standalone "how good is this phone, on its own" star profile for the
 * public detail page - the 8 objective PhoneScorer dimensions
 * (App\Services\Recommendation\PhoneScorer), converted to a /5 scale for
 * display. Deliberately reuses PhoneScorer rather than reimplementing any
 * of its formulas: every score shown here is exactly what the live
 * recommendation engine would compute for this phone, so the two can
 * never quietly disagree.
 *
 * "Value for money" (the recommendation engine's 9th dimension) is left
 * out on purpose - it's computed by ValueScorer relative to a whole pool
 * of candidates and a buyer's budget (see PhoneRecommendationEngine), so
 * there is no single honest "value score" for a phone viewed alone.
 *
 * PhoneScorer's methods take a PhoneCandidate (phone + variant + a
 * current market price) purely because that's the shape the
 * recommendation engine already has on hand - none of the 8 methods here
 * actually read the price. A throwaway, unsaved PhoneMarketPrice keeps
 * that constructor happy without implying this phone has a real price,
 * and without changing PhoneScorer's public API for this one caller.
 */
class PhonePerformanceProfile
{
    /**
     * @return list<array{key: string, label: string, score: int, stars: float}>|null
     *                                                                                null when the phone has no variant at all - there is
     *                                                                                nothing to honestly score.
     */
    public static function for(Phone $phone): ?array
    {
        $variant = $phone->variants->first();

        if (! $variant) {
            return null;
        }

        $candidate = new PhoneCandidate($phone, $variant, new PhoneMarketPrice([
            'price_type' => PriceTypeEnum::OFFICIAL_BD,
            'price' => 0,
        ]));

        $scorer = app(PhoneScorer::class);

        $dimensions = [
            'performance' => 'Performance',
            'gaming' => 'Gaming',
            'camera' => 'Camera',
            'battery' => 'Battery',
            'display' => 'Display',
            'software' => 'Software',
            'build' => 'Build',
            'charging' => 'Charging',
        ];

        return collect($dimensions)
            ->map(function (string $label, string $key) use ($scorer, $candidate) {
                $score = $scorer->{$key}($candidate);

                return [
                    'key' => $key,
                    'label' => $label,
                    'score' => $score,
                    // Half-star resolution (0, 0.5, 1, ... 5) - a plain
                    // round-to-nearest-star would make every phone
                    // scoring 70-89 look identically "4 stars".
                    'stars' => round(($score / 100) * 5 * 2) / 2,
                ];
            })
            ->values()
            ->all();
    }
}
