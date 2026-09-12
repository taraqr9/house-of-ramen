<?php

namespace App\Services\Recommendation;

/**
 * A candidate with every dimension scored and the final, adjusted match
 * score computed - the unit the explainer and controller work with.
 */
class ScoredPhoneCandidate
{
    /**
     * @param  array<string, int>  $dimensionScores  performance, gaming, camera, battery, display, software, build, charging, value
     */
    public function __construct(
        public readonly PhoneCandidate $candidate,
        public readonly array $dimensionScores,
        public readonly float $lifecycleMultiplier,
        public readonly float $confidenceMultiplier,
        public readonly float $matchScore,
    ) {}

    public function dimension(string $key): int
    {
        return $this->dimensionScores[$key] ?? 0;
    }
}
