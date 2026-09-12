<?php

namespace App\Enums;

/**
 * The outcome of one retailer's discovery attempt for one phone - see
 * App\Services\PhoneImport\Sources\Retailers\RetailerListingSource. This
 * is what makes catalogue-wide discovery scalable and resumable: once a
 * phone has a definitive outcome for a retailer, it isn't re-probed with
 * every candidate URL again on every run - see PhoneRetailerMatchAttempt
 * and its next_check_at column.
 */
enum RetailerMatchStatusEnum: string
{
    // A candidate URL resolved to a real product page for this exact
    // phone and a confident variant attribution - the discovered URL is
    // now reused directly on future runs instead of re-probing candidates.
    case MATCHED = 'matched';

    // Every candidate URL this retailer's naming conventions could
    // produce came back with no real product (never fabricated - this is
    // the honest "this retailer doesn't carry this phone" outcome).
    case NO_CANDIDATE = 'no_candidate';

    // A real product page was found and its declared name matches the
    // expected phone, but its RAM/storage couldn't be confidently
    // attributed to exactly one of the phone's variants (multiple
    // variants share the same extractable identity, or none do) - the
    // price is never guessed onto a variant here.
    case VARIANT_AMBIGUOUS = 'variant_ambiguous';

    // A candidate URL returned content, but its declared product name
    // doesn't plausibly match the expected phone (see
    // RetailerListingSource::nameLooksLikeExpectedPhone()) - guards
    // against a coincidental slug collision.
    case NAME_MISMATCH = 'name_mismatch';

    // A network/timeout/5xx error prevented checking at all - distinct
    // from NO_CANDIDATE because it deserves a much sooner retry, not a
    // week-long "this retailer doesn't have it" assumption.
    case FETCH_FAILED = 'fetch_failed';

    public function label(): string
    {
        return match ($this) {
            self::MATCHED => 'Matched',
            self::NO_CANDIDATE => 'No Candidate Found',
            self::VARIANT_AMBIGUOUS => 'Variant Ambiguous',
            self::NAME_MISMATCH => 'Name Mismatch',
            self::FETCH_FAILED => 'Fetch Failed',
        };
    }

    /**
     * How long a phone/retailer pair with this outcome is left alone
     * before being probed again. MATCHED is intentionally short: the
     * outcome itself (which URL to use, what variant it maps to) is
     * cached indefinitely via candidate_url/matched_variant_id, but the
     * PRICE at that URL still needs to be refreshed on the normal import
     * cadence - see PriceAggregator's own freshness gate for what
     * actually governs "current" pricing.
     */
    public function recheckAfterDays(): int
    {
        return match ($this) {
            self::MATCHED => 1,
            self::FETCH_FAILED => 1,
            self::NO_CANDIDATE, self::VARIANT_AMBIGUOUS, self::NAME_MISMATCH => 7,
        };
    }
}
