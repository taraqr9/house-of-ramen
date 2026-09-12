<?php

namespace App\Services\Recommendation;

use App\Models\PhoneSpec;
use Illuminate\Support\Carbon;

/**
 * Turns raw phone_specs data into 0-100 scores per dimension. There is
 * no benchmark database, so these are deliberately-documented heuristics
 * (see config/phone_recommendation.php) grounded in the fields the data
 * foundation actually collects - not a claim of scientific precision.
 *
 * Every method is null-safe: missing data falls back to a neutral,
 * configured default rather than crashing or silently scoring 0/100.
 */
class PhoneScorer
{
    public function performance(PhoneCandidate $candidate): int
    {
        $spec = $candidate->phone->spec;
        $processor = strtolower($spec?->processor ?? '');

        foreach (config('phone_recommendation.chipset_tiers') as $pattern => $score) {
            if ($processor !== '' && str_contains($processor, $pattern)) {
                return $this->clamp($score);
            }
        }

        $baseline = config('phone_recommendation.category_performance_baseline.'.$candidate->phone->category)
            ?? config('phone_recommendation.performance_default_baseline');

        $ramNudge = min((int) ($candidate->variant->ram_gb ?? 0), 16) * 0.8;

        return $this->clamp($baseline + $ramNudge);
    }

    public function gaming(PhoneCandidate $candidate): int
    {
        $weights = config('phone_recommendation.gaming_weights');

        $score = $this->performance($candidate) * $weights['performance']
            + $this->refreshRateScore($candidate->phone->spec) * $weights['refresh_rate']
            + $this->charging($candidate) * $weights['charging'];

        return $this->clamp($score);
    }

    public function camera(PhoneCandidate $candidate): int
    {
        $spec = $candidate->phone->spec;

        $mp = $this->extractMegapixels($spec?->main_camera);
        $score = $this->tieredScore($mp, config('phone_recommendation.camera_mp_tiers'), config('phone_recommendation.camera_mp_default'));

        if ($spec?->camera_has_ois) {
            $score += config('phone_recommendation.camera_ois_bonus');
        }

        if (! empty($spec?->ultrawide_camera)) {
            $score += config('phone_recommendation.camera_ultrawide_bonus');
        }

        if (! empty($spec?->telephoto_camera)) {
            $score += config('phone_recommendation.camera_telephoto_bonus');
        }

        if (! empty($spec?->macro_camera)) {
            $score += config('phone_recommendation.camera_macro_bonus');
        }

        $frontMp = $this->extractMegapixels($spec?->front_camera);

        if ($frontMp !== null && $frontMp >= config('phone_recommendation.camera_front_bonus_threshold_mp')) {
            $score += config('phone_recommendation.camera_front_bonus');
        }

        return $this->clamp($score);
    }

    public function battery(PhoneCandidate $candidate): int
    {
        $spec = $candidate->phone->spec;

        $score = $this->interpolatedScore(
            $spec?->battery_capacity_mah,
            config('phone_recommendation.battery_capacity_curve'),
            config('phone_recommendation.battery_capacity_default')
        );

        $offset = min(
            config('phone_recommendation.battery_charging_offset_max'),
            ($spec?->charging_speed_w ?? 0) / config('phone_recommendation.battery_charging_offset_divisor')
        );

        return $this->clamp($score + $offset);
    }

    public function display(PhoneCandidate $candidate): int
    {
        $spec = $candidate->phone->spec;
        $weights = config('phone_recommendation.display_weights');

        $panelScore = $this->panelScore($spec?->display_panel_type);
        $resolutionScore = $this->resolutionScore($spec?->display_resolution);

        $score = $this->refreshRateScore($spec) * $weights['refresh_rate']
            + $panelScore * $weights['panel']
            + $resolutionScore * $weights['resolution'];

        return $this->clamp($score);
    }

    public function software(PhoneCandidate $candidate): int
    {
        $spec = $candidate->phone->spec;

        if (! $spec || ($spec->os_update_years === null && $spec->security_update_years === null)) {
            return (int) config('phone_recommendation.software_no_data_score');
        }

        $yearsSinceRelease = $this->yearsSinceRelease($candidate->phone->release_date);

        $remainingOs = max(0, ($spec->os_update_years ?? 0) - $yearsSinceRelease);
        $remainingSecurity = max(0, ($spec->security_update_years ?? 0) - $yearsSinceRelease);

        $score = config('phone_recommendation.software_base')
            + $remainingOs * config('phone_recommendation.software_os_year_weight')
            + $remainingSecurity * config('phone_recommendation.software_security_year_weight');

        if ($remainingOs <= 0 && $remainingSecurity <= 0) {
            $score = min($score, config('phone_recommendation.software_expired_floor') + 15);
        }

        return $this->clamp($score);
    }

    public function build(PhoneCandidate $candidate): int
    {
        $spec = $candidate->phone->spec;
        $materials = strtolower($spec?->build_materials ?? '');

        $hasGlass = str_contains($materials, 'glass');
        $hasMetal = str_contains($materials, 'aluminum') || str_contains($materials, 'metal') || str_contains($materials, 'titanium');

        $score = match (true) {
            $materials === '' => config('phone_recommendation.build_default_score'),
            $hasGlass && $hasMetal => config('phone_recommendation.build_glass_metal_score'),
            $hasGlass => config('phone_recommendation.build_glass_only_score'),
            $hasMetal => config('phone_recommendation.build_metal_frame_score'),
            default => config('phone_recommendation.build_plastic_score'),
        };

        $ipRating = strtolower($spec?->ip_rating ?? '');
        $ipBonus = config('phone_recommendation.ip_rating_bonus')[$ipRating] ?? 0;

        return $this->clamp($score + $ipBonus);
    }

    public function charging(PhoneCandidate $candidate): int
    {
        $spec = $candidate->phone->spec;

        $score = $this->tieredScore(
            $spec?->charging_speed_w,
            config('phone_recommendation.charging_speed_tiers'),
            config('phone_recommendation.charging_speed_default')
        );

        if (! empty($spec?->wireless_charging_w)) {
            $score += config('phone_recommendation.wireless_charging_bonus');
        }

        return $this->clamp($score);
    }

    /**
     * Unweighted average of the 8 objective dimensions - the "how good is
     * this phone" baseline that value-for-money is computed against.
     */
    public function qualityIndex(PhoneCandidate $candidate): float
    {
        $scores = [
            $this->performance($candidate),
            $this->gaming($candidate),
            $this->camera($candidate),
            $this->battery($candidate),
            $this->display($candidate),
            $this->software($candidate),
            $this->build($candidate),
            $this->charging($candidate),
        ];

        return array_sum($scores) / count($scores);
    }

    public function yearsSinceRelease(?Carbon $releaseDate): float
    {
        if (! $releaseDate) {
            return 1.0;
        }

        return max(0, $releaseDate->diffInDays(now()) / 365);
    }

    protected function refreshRateScore(?PhoneSpec $spec): int
    {
        $rate = $spec?->display_refresh_rate;

        if ($rate === null) {
            return (int) config('phone_recommendation.refresh_rate_default');
        }

        $tiers = config('phone_recommendation.refresh_rate_scores');
        krsort($tiers);

        foreach ($tiers as $threshold => $score) {
            if ($rate >= $threshold) {
                return $score;
            }
        }

        return (int) config('phone_recommendation.refresh_rate_default');
    }

    protected function panelScore(?string $panelType): int
    {
        $panel = strtolower($panelType ?? '');

        foreach (config('phone_recommendation.panel_type_scores') as $pattern => $score) {
            if ($panel !== '' && str_contains($panel, $pattern)) {
                return $score;
            }
        }

        return (int) config('phone_recommendation.panel_type_default');
    }

    protected function resolutionScore(?string $resolution): int
    {
        foreach (config('phone_recommendation.resolution_bonus') as $needle => $score) {
            if ($resolution && str_contains($resolution, $needle)) {
                return $score;
            }
        }

        return (int) config('phone_recommendation.resolution_default');
    }

    protected function extractMegapixels(?string $cameraText): ?int
    {
        if (! $cameraText) {
            return null;
        }

        if (preg_match('/([\d.]+)\s*MP/i', $cameraText, $matches)) {
            return (int) round((float) $matches[1]);
        }

        return null;
    }

    protected function tieredScore(?float $value, array $tiers, int $default): int
    {
        if ($value === null) {
            return $default;
        }

        krsort($tiers);

        foreach ($tiers as $threshold => $score) {
            if ($value >= $threshold) {
                return $score;
            }
        }

        return $default;
    }

    /**
     * Linear interpolation between breakpoints, e.g. [5000 => 80, 6000 => 93]
     * gives 5500 a score of 86.5 rather than flattening every value in
     * [5000, 6000) to the same tier. Values outside the breakpoint range
     * clamp to the nearest endpoint.
     */
    protected function interpolatedScore(?float $value, array $breakpoints, int $default): int
    {
        if ($value === null || empty($breakpoints)) {
            return $default;
        }

        ksort($breakpoints);
        $points = array_keys($breakpoints);

        if ($value <= $points[0]) {
            return $this->clamp($breakpoints[$points[0]]);
        }

        $last = end($points);

        if ($value >= $last) {
            return $this->clamp($breakpoints[$last]);
        }

        foreach ($breakpoints as $x => $y) {
            if ($value === (float) $x) {
                return $this->clamp($y);
            }
        }

        $lowerX = $points[0];

        foreach ($points as $x) {
            if ($x > $value) {
                $upperX = $x;
                $ratio = ($value - $lowerX) / ($upperX - $lowerX);
                $interpolated = $breakpoints[$lowerX] + $ratio * ($breakpoints[$upperX] - $breakpoints[$lowerX]);

                return $this->clamp($interpolated);
            }

            $lowerX = $x;
        }

        return $default;
    }

    protected function clamp(float $score): int
    {
        return (int) round(min(100, max(0, $score)));
    }
}
