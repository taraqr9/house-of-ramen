<?php

namespace App\Services\PhoneImport;

use App\Models\Brand;
use App\Models\Phone;
use App\Models\PhoneSpec;

/**
 * Finds the closest existing phone (if any) for an incoming brand + model
 * name so the pipeline can decide whether a record is genuinely NEW, a
 * confident MATCH to an existing phone, or merely a POSSIBLE duplicate
 * that a human should confirm (e.g. "Galaxy A56" vs "A56 5G" vs
 * "Galaxy A56 5G"). Never merges anything itself - it only classifies.
 *
 * Name similarity alone cannot reliably tell "a naming variant of the
 * same phone" apart from "a genuinely different phone that happens to
 * share most of its name" - e.g. "Redmi 12" vs "Redmi Note 12" share
 * every token of the shorter name, but are unrelated devices with
 * different chipsets, while "Galaxy A56" vs "A56 5G" really can be the
 * same phone listed two different ways. A hard-coded list of "this word
 * means different phone" (Note, Power, Premier, Edge, Nord, Reno, ...)
 * doesn't scale - every brand mints new line names constantly. So beyond
 * a small, closed, genuinely universal set of tier words, ambiguous name
 * matches are corroborated against the phones' actual hardware
 * (chipset/battery/camera/charging): real duplicate listings of the same
 * physical phone report matching specs, genuinely different phones
 * essentially never do.
 */
class DuplicateDetector
{
    /**
     * Tier words used the same way across virtually every phone brand -
     * when only one side of a comparison carries one of these, the two
     * names are confidently different PRODUCTS (a "Pro" is never quietly
     * the same SKU as its non-Pro sibling), so this short-circuits
     * straight to "different" without needing spec data to confirm.
     * Deliberately small and closed: these are generic cross-brand tier
     * modifiers, not brand-specific model-line names - see
     * specsDiverge() for how those are actually handled.
     */
    protected const HARD_DIFFERENTIATOR_TOKENS = [
        'pro', 'plus', 'ultra', 'max', 'mini', 'lite', 'fe', 'neo', 'turbo', 'gt', 'se', 'xl',
    ];

    /**
     * Network-generation suffixes. Sometimes these mark a genuine
     * different-chipset SKU split (a 4G and 5G version of the same model
     * number can ship on different silicon), sometimes they're just part
     * of the one-and-only name a phone was ever sold under. Too
     * ambiguous to trust the name alone in either direction, so this
     * only pushes the match into the "needs review" band - specsDiverge()
     * (or a human) makes the final call.
     */
    protected const SOFT_DIFFERENTIATOR_TOKENS = ['5g', '4g', 'lte'];

    /**
     * Relative tolerance for numeric spec comparisons - small enough to
     * absorb rounding/rewording noise between sources or listings, large
     * enough that a real engineering difference (a meaningfully bigger
     * battery, faster charging) still trips it.
     */
    protected const SPEC_DIVERGENCE_TOLERANCE = 0.08;

    /**
     * @return array{phone: Phone, score: int}|null
     */
    public function findBestMatch(Brand $brand, string $modelName, ?array $incomingSpecs = null): ?array
    {
        $needle = $this->canonicalize($modelName);

        $best = null;
        $bestScore = -1;

        Phone::query()
            ->where('brand_id', $brand->id)
            ->with('spec')
            ->get(['id', 'name', 'brand_id'])
            ->each(function (Phone $phone) use ($needle, $incomingSpecs, &$best, &$bestScore) {
                $score = $this->similarityScore($needle, $this->canonicalize($phone->name), $incomingSpecs, $phone->spec);

                if ($score > $bestScore) {
                    $bestScore = $score;
                    $best = $phone;
                }
            });

        return $best ? ['phone' => $best, 'score' => $bestScore] : null;
    }

    /**
     * Classify a similarity score into a MatchStatusEnum value: 'matched',
     * 'needs_review', or 'new'.
     */
    public function classify(int $score): string
    {
        $thresholds = config('phone_confidence.duplicate');

        if ($score >= $thresholds['auto_match_threshold']) {
            return 'matched';
        }

        if ($score >= $thresholds['possible_duplicate_threshold']) {
            return 'needs_review';
        }

        return 'new';
    }

    public function canonicalize(string $name): string
    {
        $name = strtolower($name);
        // "+" is meaningful (e.g. "Note 13 Pro" vs "Note 13 Pro+" are
        // different phones) - spell it out as a word *before* stripping
        // punctuation, otherwise it vanishes silently and the two collapse
        // to the same token set. "5g"/"4g"/"lte" are deliberately kept as
        // real tokens (not stripped) - see SOFT_DIFFERENTIATOR_TOKENS.
        $name = str_replace('+', ' plus ', $name);
        $name = preg_replace('/[^a-z0-9]+/', ' ', $name);

        return trim(preg_replace('/\s+/', ' ', $name));
    }

    protected function similarityScore(string $a, string $b, ?array $incomingSpecs, ?PhoneSpec $existingSpec): int
    {
        if ($a === '' || $b === '') {
            return 0;
        }

        if ($a === $b) {
            return 100;
        }

        $tokensA = array_values(array_unique(array_filter(explode(' ', $a))));
        $tokensB = array_values(array_unique(array_filter(explode(' ', $b))));

        // A universal tier word present on only one side means these are
        // different products (e.g. "Galaxy S24" vs "Galaxy S24 Ultra") -
        // never a duplicate, no matter how much the rest of the name
        // overlaps or what the specs say.
        if ($this->hasAsymmetricToken($tokensA, $tokensB, self::HARD_DIFFERENTIATOR_TOKENS)) {
            return 0;
        }

        // Either a network-generation suffix on only one side, or one
        // name's tokens are a full subset of the other's (e.g. "a56"
        // inside "galaxy a56", or "redmi 12" inside "redmi note 12") -
        // a real signal but not a certain one. Let the actual hardware
        // settle it when we have both sides to compare; otherwise stay
        // in the "needs review" band rather than guessing.
        $isSubset = array_diff($tokensA, $tokensB) === [] || array_diff($tokensB, $tokensA) === [];
        $hasNetworkSuffixAsymmetry = $this->hasAsymmetricToken($tokensA, $tokensB, self::SOFT_DIFFERENTIATOR_TOKENS);

        if ($isSubset || $hasNetworkSuffixAsymmetry) {
            return $this->specsDiverge($incomingSpecs, $existingSpec) ? 0 : 90;
        }

        $union = count(array_unique(array_merge($tokensA, $tokensB)));

        if ($union === 0) {
            return 0;
        }

        $intersection = count(array_intersect($tokensA, $tokensB));
        $score = (int) round($intersection / $union * 100);

        $thresholds = config('phone_confidence.duplicate');

        // A borderline name score that happens to fall in the ambiguous
        // review band gets the same hardware corroboration as the subset
        // case above, for the same reason.
        if ($score >= $thresholds['possible_duplicate_threshold']
            && $score < $thresholds['auto_match_threshold']
            && $this->specsDiverge($incomingSpecs, $existingSpec)) {
            return 0;
        }

        return $score;
    }

    /**
     * @param  list<string>  $vocabulary
     */
    protected function hasAsymmetricToken(array $tokensA, array $tokensB, array $vocabulary): bool
    {
        $inA = array_intersect($tokensA, $vocabulary);
        $inB = array_intersect($tokensB, $vocabulary);

        return array_diff($inA, $inB) !== [] || array_diff($inB, $inA) !== [];
    }

    /**
     * Whether the incoming record's hardware looks like a genuinely
     * different phone from the existing candidate, rather than the same
     * phone listed under a slightly different name. Returns false (can't
     * tell / assume not diverging) whenever either side is missing the
     * spec data needed to compare - callers fall back to the name-only
     * verdict in that case.
     */
    protected function specsDiverge(?array $incoming, ?PhoneSpec $existing): bool
    {
        if (! $incoming || ! $existing) {
            return false;
        }

        if ($this->processorDiverges($incoming['processor'] ?? null, $existing->processor)) {
            return true;
        }

        if ($this->numberDiverges($incoming['battery_capacity_mah'] ?? null, $existing->battery_capacity_mah)) {
            return true;
        }

        if ($this->numberDiverges($incoming['charging_speed_w'] ?? null, $existing->charging_speed_w)) {
            return true;
        }

        $incomingMp = $this->extractMegapixels($incoming['main_camera'] ?? null);
        $existingMp = $this->extractMegapixels($existing->main_camera);

        return $incomingMp !== null && $existingMp !== null && $incomingMp !== $existingMp;
    }

    protected function processorDiverges(?string $a, ?string $b): bool
    {
        if (! $a || ! $b) {
            return false;
        }

        return $this->normalizeProcessor($a) !== $this->normalizeProcessor($b);
    }

    protected function normalizeProcessor(string $value): string
    {
        return trim(preg_replace('/\s+/', ' ', strtolower($value)));
    }

    protected function numberDiverges(?int $a, ?int $b): bool
    {
        if ($a === null || $b === null || $b === 0) {
            return false;
        }

        return abs($a - $b) / $b > self::SPEC_DIVERGENCE_TOLERANCE;
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
}
