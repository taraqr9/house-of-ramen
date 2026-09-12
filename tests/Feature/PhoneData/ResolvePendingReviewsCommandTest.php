<?php

use App\Models\Phone;
use App\Models\PhoneDataReview;
use App\Models\PhoneVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('resolves every pending image_needs_review row since images no longer gate the public site', function () {
    $phone = Phone::factory()->create();
    $review = PhoneDataReview::create(['phone_id' => $phone->id, 'reason' => 'image_needs_review', 'status' => 'pending']);

    $this->artisan('phones:resolve-reviews')->assertSuccessful();

    expect($review->fresh()->status->value)->toBe('resolved')
        ->and($review->fresh()->resolution_note)->not->toBeNull();
});

it('resolves a low_confidence review as stale when the phone is already active', function () {
    $phone = Phone::factory()->create(['is_active' => true, 'overall_confidence' => 85]);
    $review = PhoneDataReview::create(['phone_id' => $phone->id, 'reason' => 'low_confidence', 'status' => 'pending']);

    $this->artisan('phones:resolve-reviews')->assertSuccessful();

    expect($review->fresh()->status->value)->toBe('resolved');
});

it('auto-approves and activates an inactive phone with strong identity+spec+variant evidence', function () {
    $phone = Phone::factory()->create(['is_active' => false, 'identity_confidence' => 83, 'spec_confidence' => 75, 'overall_confidence' => 77]);
    PhoneVariant::factory()->create(['phone_id' => $phone->id, 'ram_gb' => 8, 'storage_gb' => 128]);
    $review = PhoneDataReview::create(['phone_id' => $phone->id, 'reason' => 'low_confidence', 'status' => 'pending']);

    $this->artisan('phones:resolve-reviews')->assertSuccessful();

    expect($phone->fresh()->is_active)->toBeTrue()
        ->and($review->fresh()->status->value)->toBe('approved');
});

it('leaves a genuinely thin low_confidence review pending rather than force-activating it', function () {
    $phone = Phone::factory()->create(['is_active' => false, 'identity_confidence' => 83, 'spec_confidence' => 40, 'overall_confidence' => 55]);
    PhoneVariant::factory()->create(['phone_id' => $phone->id, 'ram_gb' => 8, 'storage_gb' => 128]);
    $review = PhoneDataReview::create(['phone_id' => $phone->id, 'reason' => 'low_confidence', 'status' => 'pending']);

    $this->artisan('phones:resolve-reviews')->assertSuccessful();

    expect($phone->fresh()->is_active)->toBeFalse()
        ->and($review->fresh()->status->value)->toBe('pending');
});

it('leaves a low_confidence review pending when the phone has no real ram/storage variant, even with strong identity+spec', function () {
    $phone = Phone::factory()->create(['is_active' => false, 'identity_confidence' => 83, 'spec_confidence' => 80, 'overall_confidence' => 77]);
    PhoneVariant::factory()->create(['phone_id' => $phone->id, 'ram_gb' => null, 'storage_gb' => null]);
    $review = PhoneDataReview::create(['phone_id' => $phone->id, 'reason' => 'low_confidence', 'status' => 'pending']);

    $this->artisan('phones:resolve-reviews')->assertSuccessful();

    expect($phone->fresh()->is_active)->toBeFalse()
        ->and($review->fresh()->status->value)->toBe('pending');
});

it('does not write anything in --dry-run mode', function () {
    $phone = Phone::factory()->create();
    $review = PhoneDataReview::create(['phone_id' => $phone->id, 'reason' => 'image_needs_review', 'status' => 'pending']);

    $this->artisan('phones:resolve-reviews --dry-run')->assertSuccessful();

    expect($review->fresh()->status->value)->toBe('pending');
});

it('is idempotent - a second run resolves nothing further', function () {
    $phone = Phone::factory()->create();
    PhoneDataReview::create(['phone_id' => $phone->id, 'reason' => 'image_needs_review', 'status' => 'pending']);

    $this->artisan('phones:resolve-reviews')->assertSuccessful();
    $countAfterFirst = PhoneDataReview::where('status', 'pending')->count();

    $this->artisan('phones:resolve-reviews')->assertSuccessful();
    $countAfterSecond = PhoneDataReview::where('status', 'pending')->count();

    expect($countAfterSecond)->toBe($countAfterFirst)->toBe(0);
});
