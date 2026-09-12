<?php

namespace App\Services\Recommendation;

/**
 * A deliberately gentle multiplier on the overall match score based on
 * age and remaining software support - never a hard cutoff. A genuinely
 * better-value older phone must still be able to outrank a newer, worse
 * one; this only nudges close calls, it doesn't decide them.
 */
class PhoneLifecycleCalculator
{
    public function multiplier(PhoneCandidate $candidate, PhoneScorer $scorer): float
    {
        $years = $scorer->yearsSinceRelease($candidate->phone->release_date);

        $factor = $this->ageFactor($years);

        if ($this->softwareSupportEnded($candidate, $years)) {
            $factor -= config('phone_recommendation.lifecycle_support_ended_penalty');
        }

        return max(0.5, $factor);
    }

    protected function ageFactor(float $years): float
    {
        $factors = config('phone_recommendation.lifecycle_age_factors');
        ksort($factors);

        foreach ($factors as $thresholdYears => $factor) {
            if ($years <= $thresholdYears) {
                return $factor;
            }
        }

        return (float) config('phone_recommendation.lifecycle_age_factor_beyond');
    }

    protected function softwareSupportEnded(PhoneCandidate $candidate, float $years): bool
    {
        $spec = $candidate->phone->spec;

        if (! $spec || $spec->os_update_years === null || $spec->security_update_years === null) {
            return false;
        }

        return $years > max($spec->os_update_years, $spec->security_update_years);
    }
}
