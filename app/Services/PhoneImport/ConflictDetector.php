<?php

namespace App\Services\PhoneImport;

use App\Models\PhoneSource;

/**
 * Compares an incoming normalized value against whatever is currently
 * on record for the same field. When they disagree it never silently
 * picks one - it returns whether the disagreement can be auto-resolved
 * (the new source is meaningfully more reliable than the one currently
 * on record) or must be flagged for a human via phone_data_conflicts.
 */
class ConflictDetector
{
    /**
     * @return array{action: 'auto_resolve'|'flag', existing_value: mixed, new_value: mixed}|null
     *                                                                                            Null means there is nothing to reconcile (no existing value, or values agree).
     */
    public function evaluate(
        mixed $existingValue,
        ?PhoneSource $existingSource,
        mixed $newValue,
        PhoneSource $newSource
    ): ?array {
        if ($existingValue === null || $existingValue === '') {
            return null;
        }

        if ($newValue === null || $newValue === '') {
            return null;
        }

        if ($this->valuesMatch($existingValue, $newValue)) {
            return null;
        }

        $margin = config('phone_confidence.conflict_auto_resolve_margin');
        $existingReliability = $existingSource?->reliability_score ?? 0;
        $newReliability = $newSource->reliability_score;

        $autoResolve = ($newReliability - $existingReliability) >= $margin;

        return [
            'action' => $autoResolve ? 'auto_resolve' : 'flag',
            'existing_value' => $existingValue,
            'new_value' => $newValue,
        ];
    }

    protected function valuesMatch(mixed $a, mixed $b): bool
    {
        if (is_numeric($a) && is_numeric($b)) {
            return abs(((float) $a) - ((float) $b)) < 0.01;
        }

        return (string) $a === (string) $b;
    }
}
