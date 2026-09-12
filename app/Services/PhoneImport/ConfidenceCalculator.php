<?php

namespace App\Services\PhoneImport;

use App\Models\PhoneSource;

/**
 * Turns a source's configured reliability plus how complete an incoming
 * record is into a 0-100 confidence score, and blends per-category
 * scores into a phone's overall confidence. See config/phone_confidence.php
 * for the weights/thresholds this class reads - nothing here is hard-coded.
 */
class ConfidenceCalculator
{
    /**
     * Score a single category (identity/spec/software/...) for one record.
     *
     * @param  float  $completenessRatio  0.0-1.0, share of expected fields actually present.
     */
    public function categoryScore(PhoneSource $source, float $completenessRatio): int
    {
        $reliabilityWeight = config('phone_confidence.source_reliability_weight');
        $completenessWeight = config('phone_confidence.completeness_weight');

        $score = ($source->reliability_score * $reliabilityWeight)
            + (min(max($completenessRatio, 0), 1) * 100 * $completenessWeight);

        return (int) round(min(100, max(0, $score)));
    }

    /**
     * Share (0.0-1.0) of $expectedKeys that are present and non-empty in $fields.
     *
     * @param  array<string, mixed>  $fields
     * @param  list<string>  $expectedKeys
     */
    public function completeness(array $fields, array $expectedKeys): float
    {
        if (empty($expectedKeys)) {
            return 1.0;
        }

        $present = 0;

        foreach ($expectedKeys as $key) {
            if (array_key_exists($key, $fields) && $fields[$key] !== null && $fields[$key] !== '') {
                $present++;
            }
        }

        return $present / count($expectedKeys);
    }

    public function overall(int $identityScore, int $specScore, int $softwareScore): int
    {
        $weights = config('phone_confidence.weights');

        $score = ($identityScore * $weights['identity'])
            + ($specScore * $weights['spec'])
            + ($softwareScore * $weights['software']);

        return (int) round($score);
    }

    public function shouldAutoApprove(int $score): bool
    {
        return $score >= config('phone_confidence.auto_approve_threshold');
    }
}
