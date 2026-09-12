<?php

use App\Models\Brand;
use App\Models\Phone;
use App\Models\PhoneDataConflict;
use App\Models\PhoneDataReview;
use App\Models\PhoneImportRecord;
use App\Models\PhoneImportRun;
use App\Models\PhoneSource;
use App\Models\PhoneSpec;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('lets a permitted user approve a pending low-confidence review', function () {
    $user = phoneDataUser(['phone_data_review-view', 'phone_data_review-edit']);
    $phone = Phone::factory()->create();
    $review = PhoneDataReview::create(['phone_id' => $phone->id, 'reason' => 'low_confidence', 'status' => 'pending']);

    $this->actingAs($user)
        ->post(route('data-review.reviews.resolve', $review->id), ['action' => 'approve'])
        ->assertRedirect(route('data-review.index'));

    expect($review->fresh()->status->value)->toBe('approved')
        ->and($review->fresh()->reviewed_by)->toBe($user->id);
});

it('activates the phone when a human/agent approves a low_confidence review - the confirmation a low-reliability source can never earn on its own', function () {
    $user = phoneDataUser(['phone_data_review-view', 'phone_data_review-edit']);
    $source = PhoneSource::factory()->create(['reliability_score' => 55, 'requires_review' => true]);
    $run = PhoneImportRun::create(['source_id' => $source->id, 'type' => 'manual', 'status' => 'running', 'started_at' => now()]);
    $phone = Phone::factory()->create(['is_active' => false]);
    $record = PhoneImportRecord::create([
        'import_run_id' => $run->id, 'source_id' => $source->id, 'external_ref' => 'x',
        'match_status' => 'needs_review', 'raw_payload' => [], 'normalized_payload' => [], 'processed_at' => now(),
    ]);
    $review = PhoneDataReview::create([
        'phone_id' => $phone->id, 'import_record_id' => $record->id,
        'reason' => 'low_confidence', 'status' => 'pending', 'details' => ['overall_confidence' => 68],
    ]);

    $this->actingAs($user)
        ->post(route('data-review.reviews.resolve', $review->id), ['action' => 'approve'])
        ->assertRedirect(route('data-review.index'));

    expect($phone->fresh()->is_active)->toBeTrue()
        ->and($review->fresh()->status->value)->toBe('approved');
});

it('does not activate the phone for a price_outlier or image_needs_review approval - those concern a sub-resource, not the phone itself', function () {
    $user = phoneDataUser(['phone_data_review-view', 'phone_data_review-edit']);
    $source = PhoneSource::factory()->create();
    $run = PhoneImportRun::create(['source_id' => $source->id, 'type' => 'manual', 'status' => 'running', 'started_at' => now()]);
    $phone = Phone::factory()->create(['is_active' => false]);
    $record = PhoneImportRecord::create([
        'import_run_id' => $run->id, 'source_id' => $source->id, 'external_ref' => 'x',
        'match_status' => 'needs_review', 'raw_payload' => [], 'normalized_payload' => [], 'processed_at' => now(),
    ]);
    $review = PhoneDataReview::create([
        'phone_id' => $phone->id, 'import_record_id' => $record->id,
        'reason' => 'price_outlier', 'status' => 'pending',
    ]);

    $this->actingAs($user)->post(route('data-review.reviews.resolve', $review->id), ['action' => 'approve']);

    expect($phone->fresh()->is_active)->toBeFalse();
});

it('resolves a review from the CLI with an evidence note, identically to the admin UI action', function () {
    $source = PhoneSource::factory()->create(['reliability_score' => 55, 'requires_review' => true]);
    $run = PhoneImportRun::create(['source_id' => $source->id, 'type' => 'manual', 'status' => 'running', 'started_at' => now()]);
    $phone = Phone::factory()->create(['is_active' => false]);
    $record = PhoneImportRecord::create([
        'import_run_id' => $run->id, 'source_id' => $source->id, 'external_ref' => 'x',
        'match_status' => 'needs_review', 'raw_payload' => [], 'normalized_payload' => [], 'processed_at' => now(),
    ]);
    $review = PhoneDataReview::create([
        'phone_id' => $phone->id, 'import_record_id' => $record->id,
        'reason' => 'low_confidence', 'status' => 'pending',
    ]);

    $this->artisan('phones:resolve-review', [
        'review' => $review->id, 'action' => 'approve', '--note' => 'Confirmed real via GSMArena, specs match exactly.',
    ])->assertExitCode(0);

    expect($phone->fresh()->is_active)->toBeTrue()
        ->and($review->fresh()->status->value)->toBe('approved')
        ->and($review->fresh()->resolution_note)->toBe('Confirmed real via GSMArena, specs match exactly.');
});

it('rejects a review from the CLI without touching the phone, leaving it inactive with the reasoning recorded', function () {
    $source = PhoneSource::factory()->create(['reliability_score' => 55, 'requires_review' => true]);
    $run = PhoneImportRun::create(['source_id' => $source->id, 'type' => 'manual', 'status' => 'running', 'started_at' => now()]);
    $phone = Phone::factory()->create(['is_active' => false]);
    $record = PhoneImportRecord::create([
        'import_run_id' => $run->id, 'source_id' => $source->id, 'external_ref' => 'x',
        'match_status' => 'needs_review', 'raw_payload' => [], 'normalized_payload' => [], 'processed_at' => now(),
    ]);
    $review = PhoneDataReview::create([
        'phone_id' => $phone->id, 'import_record_id' => $record->id,
        'reason' => 'low_confidence', 'status' => 'pending',
    ]);

    $this->artisan('phones:resolve-review', [
        'review' => $review->id, 'action' => 'reject', '--note' => 'No such model found on GSMArena or any manufacturer page - likely fabricated.',
    ])->assertExitCode(0);

    expect($phone->fresh()->is_active)->toBeFalse()
        ->and($review->fresh()->status->value)->toBe('rejected')
        ->and($review->fresh()->resolution_note)->not->toBeEmpty();
});

it('is a no-op (not an error) when asked to resolve a review that is already resolved', function () {
    $review = PhoneDataReview::create(['phone_id' => Phone::factory()->create()->id, 'reason' => 'low_confidence', 'status' => 'approved']);

    $this->artisan('phones:resolve-review', ['review' => $review->id, 'action' => 'reject'])
        ->assertExitCode(0);

    expect($review->fresh()->status->value)->toBe('approved'); // unchanged
});

it('applies the new value and marks a conflict resolved when accepted', function () {
    $user = phoneDataUser(['phone_data_conflict-view', 'phone_data_conflict-edit']);
    $phone = Phone::factory()->create();
    PhoneSpec::create(['phone_id' => $phone->id, 'battery_capacity_mah' => 5000]);
    $conflict = PhoneDataConflict::create([
        'phone_id' => $phone->id, 'table_name' => 'phone_specs', 'field' => 'battery_capacity_mah',
        'existing_value' => '5000', 'new_value' => '5100', 'status' => 'open',
    ]);

    $this->actingAs($user)
        ->post(route('data-review.conflicts.resolve', $conflict->id), ['action' => 'accept_new'])
        ->assertRedirect(route('data-review.index'));

    expect($conflict->fresh()->status->value)->toBe('resolved')
        ->and($phone->spec->fresh()->battery_capacity_mah)->toBe(5100);
});

it('keeps the existing value when a conflict is resolved in its favour', function () {
    $user = phoneDataUser(['phone_data_conflict-edit']);
    $phone = Phone::factory()->create();
    PhoneSpec::create(['phone_id' => $phone->id, 'battery_capacity_mah' => 5000]);
    $conflict = PhoneDataConflict::create([
        'phone_id' => $phone->id, 'table_name' => 'phone_specs', 'field' => 'battery_capacity_mah',
        'existing_value' => '5000', 'new_value' => '5100', 'status' => 'open',
    ]);

    $this->actingAs($user)->post(route('data-review.conflicts.resolve', $conflict->id), ['action' => 'keep_existing']);

    expect($phone->spec->fresh()->battery_capacity_mah)->toBe(5000);
});

it('lets a permitted user mark a source unreliable', function () {
    $user = phoneDataUser(['phone_source-edit']);
    $source = PhoneSource::factory()->create(['reliability_score' => 80]);

    $this->actingAs($user)
        ->post(route('data-review.sources.mark-unreliable', $source->id))
        ->assertRedirect(route('data-review.index'));

    expect($source->fresh()->reliability_score)->toBeLessThanOrEqual(20);
});

it('merges a confirmed possible-duplicate into the existing matched phone instead of discarding it', function () {
    $user = phoneDataUser(['phone_data_review-view', 'phone_data_review-edit']);
    $source = PhoneSource::factory()->create(['reliability_score' => 90]);
    $run = PhoneImportRun::create(['source_id' => $source->id, 'type' => 'manual', 'status' => 'running', 'started_at' => now()]);

    $brand = Brand::factory()->create(['name' => 'Realme']);
    $existingPhone = Phone::factory()->for($brand)->create(['name' => 'Realme 12', 'is_active' => true]);

    $record = PhoneImportRecord::create([
        'import_run_id' => $run->id,
        'source_id' => $source->id,
        'external_ref' => 'realme-narzo-50-5g',
        'match_status' => 'needs_review',
        'raw_payload' => [],
        'normalized_payload' => [
            'brand' => 'Realme', 'model' => 'Realme Narzo 50 5G', 'model_number' => null,
            'announced_date' => null, 'release_date' => null, 'status' => null, 'category' => null,
            'specs' => ['processor' => 'MediaTek Dimensity 810'],
            'variants' => [[
                'ram_gb' => 4, 'storage_gb' => 128, 'storage_type' => null, 'color' => null, 'region' => 'Global',
                'is_official_bd' => false, 'official_bd_prices' => [], 'unofficial_bd_prices' => [],
                'availability' => null, 'store' => null,
            ]],
            'network_bands' => [],
        ],
        'processed_at' => now(),
    ]);

    $review = PhoneDataReview::create([
        'import_record_id' => $record->id,
        'matched_phone_id' => $existingPhone->id,
        'reason' => 'possible_duplicate',
        'similarity_score' => 90,
        'status' => 'pending',
        'details' => ['brand' => 'Realme', 'incoming_model' => 'Realme Narzo 50 5G', 'existing_model' => 'Realme 12'],
    ]);

    $this->actingAs($user)
        ->post(route('data-review.reviews.resolve', $review->id), ['action' => 'merge'])
        ->assertRedirect(route('data-review.index'));

    expect(Phone::count())->toBe(1) // no second phone created
        ->and($review->fresh()->status->value)->toBe('approved')
        ->and($review->fresh()->phone_id)->toBe($existingPhone->id)
        ->and($existingPhone->fresh()->spec->processor)->toBe('MediaTek Dimensity 810');
});

it('blocks the review queue for a user without permission', function () {
    $user = phoneDataUser([]);

    $this->actingAs($user)->get(route('data-review.index'))->assertForbidden();
});
