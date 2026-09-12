<?php

namespace App\Services\PhoneImport;

use App\Enums\MatchStatusEnum;
use App\Enums\ReviewReasonEnum;
use App\Enums\ReviewStatusEnum;
use App\Models\Brand;
use App\Models\Phone;
use App\Models\PhoneDataReview;
use Illuminate\Support\Str;

/**
 * The single source of truth for what "approve"/"reject"/"merge" actually
 * DO to a PhoneDataReview and the phone/import record behind it - used by
 * both DataReviewController (the admin UI) and ResolveReviewCommand (bulk/
 * scripted resolution, e.g. a catalogue-wide verification pass), so the two
 * can never drift into inconsistent behavior.
 */
class ReviewResolutionService
{
    public function __construct(protected PhoneImportRunner $runner) {}

    /**
     * @return Phone|null the phone the review now points at, if any.
     */
    public function approve(PhoneDataReview $review, ?int $userId = null, ?string $note = null): ?Phone
    {
        $phone = null;

        if ($review->import_record_id && ! $review->phone_id) {
            // A possible-duplicate confirmed as actually a new phone -
            // materialize it now using the payload captured at import time.
            $record = $review->importRecord;
            $brand = Brand::firstOrCreate(
                ['slug' => Str::slug($record->normalized_payload['brand'])],
                ['name' => $record->normalized_payload['brand'], 'is_active' => true]
            );

            $phone = $this->runner->materialize($record, $brand, $record->normalized_payload, $record->source, null);
            $review->phone_id = $phone->id;
        } elseif ($review->import_record_id) {
            $review->importRecord?->update(['match_status' => MatchStatusEnum::APPROVED]);
            $phone = $review->phone;

            if ($phone && in_array($review->reason, [
                ReviewReasonEnum::LOW_CONFIDENCE, ReviewReasonEnum::MISSING_DATA, ReviewReasonEnum::MANUAL_FLAG,
            ], true)) {
                // Approving IS the confirmation event: a low-reliability
                // source (e.g. seed_dataset, requires_review=true) can never
                // clear the auto-approve bar on its own reliability score
                // alone, no matter how complete/accurate its data actually
                // is - a human/agent who has checked the evidence and
                // approved it is exactly the missing corroboration.
                // Deliberately scoped to phone-record-level reasons only -
                // price_outlier/image_needs_review concern a sub-resource,
                // not the phone's own visibility.
                $phone->update(['is_active' => true]);
            }
        }

        $review->update([
            'status' => ReviewStatusEnum::APPROVED,
            'reviewed_by' => $userId,
            'reviewed_at' => now(),
            'resolution_note' => $note,
        ]);

        return $phone;
    }

    public function merge(PhoneDataReview $review, ?int $userId = null, ?string $note = null): Phone
    {
        // The developer confirms this possible-duplicate really IS the
        // same phone as the one it was matched against - materialize the
        // captured payload against that EXISTING phone (enriching it)
        // instead of the data silently vanishing when the review is
        // closed.
        $record = $review->importRecord;
        $existingPhone = $review->matchedPhone ?? Phone::findOrFail($review->matched_phone_id);
        $brand = $existingPhone->brand;

        $phone = $this->runner->materialize($record, $brand, $record->normalized_payload, $record->source, $existingPhone);

        $review->update([
            'phone_id' => $phone->id,
            'status' => ReviewStatusEnum::APPROVED,
            'reviewed_by' => $userId,
            'reviewed_at' => now(),
            'resolution_note' => $note,
        ]);

        return $phone;
    }

    public function reject(PhoneDataReview $review, ?int $userId = null, ?string $note = null): void
    {
        // Deliberately leaves the phone/import record untouched: a
        // not-yet-approved phone is already is_active=false (see
        // PhoneImportRunner::materialize()'s confidence gate), so a
        // rejection simply closes the question rather than requiring a
        // separate deactivation step. Nothing is deleted - the record and
        // the evidence trail (resolution_note) stay for future reference.
        $review->update([
            'status' => ReviewStatusEnum::REJECTED,
            'reviewed_by' => $userId,
            'reviewed_at' => now(),
            'resolution_note' => $note,
        ]);
    }

    public function ignore(PhoneDataReview $review, ?int $userId = null, ?string $note = null): void
    {
        $review->update([
            'status' => ReviewStatusEnum::RESOLVED,
            'reviewed_by' => $userId,
            'reviewed_at' => now(),
            'resolution_note' => $note,
        ]);
    }
}
