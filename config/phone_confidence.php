<?php

/*
|--------------------------------------------------------------------------
| Phone Data Confidence Strategy
|--------------------------------------------------------------------------
|
| Confidence scores (0-100) drive the auto-approve vs flag-for-review
| decision throughout the import pipeline. Nothing here is arbitrary:
|
| - A record's confidence for a given category (identity/spec/etc.) is a
|   blend of the source's configured reliability_score and how complete
|   the incoming data is for that category (see ConfidenceCalculator).
| - A phone's overall confidence is a weighted average of its category
|   scores (see `weights` below).
| - Field-level conflicts are auto-resolved only when the new source is
|   meaningfully more reliable than the source currently on record
|   (see `conflict_auto_resolve_margin`); otherwise they are flagged.
|
| All thresholds are configurable here so reliability tuning never
| requires touching the pipeline code itself.
|
*/

return [

    // Weighted contribution of each category to a phone's overall_confidence.
    // Must sum to 1.0.
    'weights' => [
        'identity' => 0.35,
        'spec' => 0.45,
        'software' => 0.20,
    ],

    // A record (or category score) at or above this is auto-approved;
    // below it is flagged into the review queue instead.
    'auto_approve_threshold' => 80,

    // How much of a single record's category confidence comes from the
    // source's reliability vs. how complete the submitted fields are.
    // Must sum to 1.0.
    'source_reliability_weight' => 0.6,
    'completeness_weight' => 0.4,

    // Duplicate detection similarity thresholds (0-100), see DuplicateDetector.
    'duplicate' => [
        'auto_match_threshold' => 92,
        'possible_duplicate_threshold' => 70,
    ],

    // A conflicting field is auto-resolved in favour of the new value only
    // when the new source's reliability exceeds the source currently on
    // record by at least this many points. Otherwise it is flagged for
    // human review rather than silently overwritten.
    'conflict_auto_resolve_margin' => 15,

    // Default reliability score (0-100) applied to a newly registered
    // source, keyed by App\Enums\SourceTypeEnum value. Overridable per
    // source via the phone_sources.reliability_score column.
    'default_reliability_by_type' => [
        'manufacturer' => 95,
        'bd_retailer' => 80,
        'global_retailer' => 75,
        'manual' => 60,
        'ai_assisted' => 45,
    ],

];
