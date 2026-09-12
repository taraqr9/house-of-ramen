<?php

namespace App\Services\Recommendation;

/**
 * "Value for money" only means something relative to the alternatives -
 * this scores each candidate against the whole eligible pool and
 * min-max rescales it, so the pool's best value reads near 100 and its
 * worst still reads as reasonable (never a cliff to 0) rather than an
 * absolute, context-free number.
 *
 * Deliberately additive (quality minus a scaled price penalty), not a
 * quality/price ratio. A ratio lets an extremely cheap but weak phone
 * "win" value purely by having a tiny denominator - real value means
 * good quality for the money, not just low price. Subtracting a
 * price penalty capped at the pool's most expensive phone keeps price
 * meaningful without letting it dominate quality.
 *
 * @param  array<int, array{candidate: PhoneCandidate, quality: float}>  $pool  keyed by candidate index
 * @return array<int, int> value score (0-100) per the same index
 */
class ValueScorer
{
    public function scoreAll(array $pool): array
    {
        if (empty($pool)) {
            return [];
        }

        $maxPrice = max(1.0, ...array_map(fn ($entry) => $entry['candidate']->priceAmount(), $pool));
        $penaltyWeight = config('phone_recommendation.value_price_penalty_weight');

        $rawValues = [];

        foreach ($pool as $index => $entry) {
            $pricePenalty = ($entry['candidate']->priceAmount() / $maxPrice) * 100 * $penaltyWeight;
            $rawValues[$index] = $entry['quality'] - $pricePenalty;
        }

        $min = min($rawValues);
        $max = max($rawValues);

        $scoreMin = config('phone_recommendation.value_score_min');
        $scoreMax = config('phone_recommendation.value_score_max');

        if (abs($max - $min) < PHP_FLOAT_EPSILON) {
            $flat = (int) config('phone_recommendation.value_score_flat_when_equal');

            return array_fill_keys(array_keys($pool), $flat);
        }

        $scores = [];

        foreach ($rawValues as $index => $raw) {
            $normalized = ($raw - $min) / ($max - $min);
            $scores[$index] = (int) round($scoreMin + $normalized * ($scoreMax - $scoreMin));
        }

        return $scores;
    }
}
