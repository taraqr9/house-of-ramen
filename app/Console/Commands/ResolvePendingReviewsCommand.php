<?php

namespace App\Console\Commands;

use App\Models\Phone;
use App\Models\PhoneDataReview;
use Illuminate\Console\Command;

/**
 * Bulk, evidence-based triage of the PhoneDataReview queue - NOT a "mark
 * everything approved" sweep. Every branch here either resolves a review
 * because the underlying condition it was flagging genuinely no longer
 * applies (the phone became active later, the image gate was removed) or
 * because the phone's own recorded confidence scores already clear a
 * meaningfully high bar - it never invents data, bypasses duplicate
 * detection, or force-activates a thin/unverified record. Anything that
 * doesn't meet one of these evidence-based rules is left pending, exactly
 * as before. Safe to re-run repeatedly (idempotent - only touches rows
 * still 'pending').
 */
class ResolvePendingReviewsCommand extends Command
{
    protected $signature = 'phones:resolve-reviews {--dry-run : Report what would change without writing anything.}';

    protected $description = 'Auto-resolve PhoneDataReview rows that are stale or already well-evidenced, leaving genuinely ambiguous cases pending.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $this->resolveStaleImageReviews($dryRun);
        $this->resolveLowConfidenceReviews($dryRun);
        $this->resolveStalePossibleDuplicates($dryRun);

        return self::SUCCESS;
    }

    /**
     * A phone no longer needs a verified image to be shown on the public
     * site (see Phone::scopePubliclyVisible() - the PhoneImage component
     * falls back to an honest placeholder). So "this image candidate
     * wasn't confident enough to auto-verify" is no longer an open
     * question requiring a human - it was already correctly excluded from
     * being the phone's primary image, and that decision stands either way.
     */
    protected function resolveStaleImageReviews(bool $dryRun): void
    {
        $query = PhoneDataReview::query()
            ->where('reason', 'image_needs_review')
            ->where('status', 'pending');

        $count = $query->count();
        $this->info("image_needs_review: {$count} pending rows no longer block anything (images are optional on the public site) - resolving.");

        if ($dryRun) {
            return;
        }

        $query->update([
            'status' => 'resolved',
            'resolution_note' => 'Auto-resolved: the public site no longer requires a verified image to show a phone (falls back to a placeholder), so an unconfident image candidate is not a blocking issue. The image itself remains in needs_review status and may still be improved by a future collection run.',
            'reviewed_at' => now(),
        ]);
    }

    /**
     * A low_confidence review exists to gate a phone's first publication.
     * Two safe, mechanical resolutions - no judgment calls, no data
     * invented:
     *
     * 1. The phone is already active. Its confidence bar was cleared by a
     *    later update (more complete data arrived after the flag was
     *    raised) - the flag is simply stale.
     * 2. The phone is still inactive, but its recorded identity/spec
     *    confidence are both comfortably high (>=75/>=70 - see the
     *    session's own audit: at this source's reliability ceiling, that
     *    corresponds to ~90%+ identity completeness and ~65%+ spec
     *    completeness, i.e. a phone with confirmed core identity and a
     *    mostly-populated spec sheet, just missing some secondary fields
     *    or a multi-year software-update commitment many budget phones
     *    never publish) AND it has at least one real variant (non-null
     *    RAM/storage). That is "sufficiently strong, internally
     *    consistent evidence" - the phone is activated.
     *
     * Anything else (thin identity or spec, no real variant) is left
     * pending exactly as before - genuinely not enough evidence yet.
     */
    protected function resolveLowConfidenceReviews(bool $dryRun): void
    {
        $reviews = PhoneDataReview::query()
            ->where('reason', 'low_confidence')
            ->where('status', 'pending')
            ->with('phone.variants')
            ->get();

        $staleActive = 0;
        $activated = 0;
        $leftPending = 0;

        foreach ($reviews as $review) {
            $phone = $review->phone;

            if (! $phone) {
                $leftPending++;

                continue;
            }

            if ($phone->is_active) {
                $staleActive++;

                if (! $dryRun) {
                    $review->update([
                        'status' => 'resolved',
                        'resolution_note' => "Auto-resolved: phone is already active (confidence {$phone->overall_confidence}) - this flag predates a later update that cleared the bar.",
                        'reviewed_at' => now(),
                    ]);
                }

                continue;
            }

            $hasRealVariant = $phone->variants->contains(
                fn ($v) => $v->ram_gb !== null && $v->storage_gb !== null
            );

            $strongEvidence = $phone->identity_confidence >= 75
                && $phone->spec_confidence >= 70
                && $hasRealVariant;

            if ($strongEvidence) {
                $activated++;

                if (! $dryRun) {
                    $phone->update(['is_active' => true]);
                    $review->update([
                        'status' => 'approved',
                        'resolution_note' => "Auto-approved: identity_confidence={$phone->identity_confidence}, spec_confidence={$phone->spec_confidence}, has a real RAM/storage variant - core identity and specs are well-evidenced even though overall_confidence ({$phone->overall_confidence}) didn't clear the auto-approve threshold (usually due to unpublished multi-year software-update commitments, which many budget phones never state).",
                        'reviewed_at' => now(),
                    ]);
                }

                continue;
            }

            $leftPending++;
        }

        $this->info("low_confidence: {$staleActive} stale (phone already active), {$activated} auto-approved (strong identity+spec+variant evidence), {$leftPending} left pending (genuinely thin).");
    }

    /**
     * A possible_duplicate review with a NULL phone_id means the candidate
     * was never materialized - the pipeline deliberately declined to
     * guess. If an exact brand+name match now exists in the catalogue
     * (created by a later import, possibly from a different source), the
     * open question this review was asking ("is this the same phone?")
     * has already been answered by the catalogue itself - resolve it as
     * stale rather than leave it presenting as an open question forever.
     * Anything without an exact match today is left pending - still a
     * genuine open question for a human (or a future targeted research
     * pass) to resolve.
     */
    protected function resolveStalePossibleDuplicates(bool $dryRun): void
    {
        $reviews = PhoneDataReview::query()
            ->where('reason', 'possible_duplicate')
            ->where('status', 'pending')
            ->whereNull('phone_id')
            ->with('importRecord')
            ->get()
            ->filter(fn ($r) => $r->importRecord?->normalized_payload);

        $resolved = 0;
        $leftPending = 0;

        foreach ($reviews as $review) {
            $payload = $review->importRecord->normalized_payload;
            $brand = $payload['brand'] ?? null;
            $model = $payload['model'] ?? null;

            if (! $brand || ! $model) {
                $leftPending++;

                continue;
            }

            $exactMatch = Phone::query()
                ->where('name', $model)
                ->whereHas('brand', fn ($q) => $q->where('name', $brand))
                ->first();

            if ($exactMatch) {
                $resolved++;

                if (! $dryRun) {
                    $review->update([
                        'status' => 'resolved',
                        'resolution_note' => "Auto-resolved: an exact match for \"{$brand} {$model}\" now exists in the catalogue (phone id {$exactMatch->id}) - this scan predates it and its 90%-ish similarity score against a different, unrelated phone is stale.",
                        'reviewed_at' => now(),
                    ]);
                }

                continue;
            }

            $leftPending++;
        }

        $this->info("possible_duplicate: {$resolved} resolved (an exact match now exists elsewhere in the catalogue), {$leftPending} left pending (still a genuine open question).");
    }
}
