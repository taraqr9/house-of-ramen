<?php

namespace App\Enums;

/**
 * Tracks the outcome of manual/agent-driven image enrichment for one
 * phone - see PhoneImageSearchAttempt. Distinct from ImageStatusEnum
 * (which describes a specific image row): this describes the *search
 * itself*, so a phone that was genuinely searched and found nothing
 * trustworthy can be skipped on future runs instead of being silently
 * indistinguishable from a phone nobody has looked at yet.
 */
enum ImageSearchAttemptStatusEnum: string
{
    // No attempt recorded yet, or an attempt is in progress. Rows are
    // not normally persisted in this state (absence of a row means the
    // same thing) - it exists so the column always has a defined value.
    case PENDING = 'pending';

    // A confident, individually-verified image was attached
    // (PhoneImageCollector::attachManual() records this automatically -
    // no separate command call needed for the success path).
    case VERIFIED = 'verified';

    // A real, capped search effort (see PhoneImageCollector's per-phone
    // limits) found no trustworthy single-device image - never a
    // shortcut for "didn't bother looking". Recorded via
    // `phones:mark-image-unresolved` with a reason, so a future session
    // can judge whether it's worth a genuinely different approach rather
    // than repeating the same dead end.
    case UNRESOLVED = 'unresolved';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::VERIFIED => 'Verified',
            self::UNRESOLVED => 'Unresolved',
        };
    }
}
