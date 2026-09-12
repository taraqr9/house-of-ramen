<?php

use App\Models\Phone;
use App\Models\PhoneDataReview;
use App\Models\PhoneImage;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('resolves the linked image_needs_review review when an image is verified via the phone edit screen', function () {
    $user = phoneDataUser(['phone-edit']);
    $phone = Phone::factory()->create();
    $image = PhoneImage::factory()->for($phone)->needsReview()->create(['is_primary' => true]);
    $review = PhoneDataReview::create([
        'phone_id' => $phone->id,
        'reason' => 'image_needs_review',
        'status' => 'pending',
        'similarity_score' => 40,
        'details' => ['image_id' => $image->id, 'title' => 'Some file', 'source_url' => null, 'license' => null],
    ]);

    $this->actingAs($user)
        ->post(route('phones.images.verify', [$phone, $image]))
        ->assertRedirect(route('phones.edit', $phone->id));

    expect($image->fresh()->status->value)->toBe('verified')
        ->and($review->fresh()->status->value)->toBe('approved')
        ->and($review->fresh()->reviewed_by)->toBe($user->id)
        ->and($review->fresh()->reviewed_at)->not->toBeNull()
        ->and($review->fresh()->resolution_note)->not->toBeNull();
});

it('resolves the linked image_needs_review review when an image is rejected via the phone edit screen', function () {
    $user = phoneDataUser(['phone-edit']);
    $phone = Phone::factory()->create();
    $image = PhoneImage::factory()->for($phone)->needsReview()->create(['is_primary' => true]);
    $review = PhoneDataReview::create([
        'phone_id' => $phone->id,
        'reason' => 'image_needs_review',
        'status' => 'pending',
        'similarity_score' => 40,
        'details' => ['image_id' => $image->id, 'title' => 'Some file', 'source_url' => null, 'license' => null],
    ]);

    $this->actingAs($user)
        ->post(route('phones.images.reject', [$phone, $image]))
        ->assertRedirect(route('phones.edit', $phone->id));

    expect($image->fresh()->status->value)->toBe('rejected')
        ->and($review->fresh()->status->value)->toBe('rejected');
});

it('does not touch an unrelated pending image_needs_review review for a different image', function () {
    $user = phoneDataUser(['phone-edit']);
    $phone = Phone::factory()->create();
    $image = PhoneImage::factory()->for($phone)->needsReview()->create(['is_primary' => true]);
    $otherImage = PhoneImage::factory()->for($phone)->needsReview()->create(['is_primary' => false]);
    $unrelatedReview = PhoneDataReview::create([
        'phone_id' => $phone->id,
        'reason' => 'image_needs_review',
        'status' => 'pending',
        'similarity_score' => 40,
        'details' => ['image_id' => $otherImage->id, 'title' => 'Other file', 'source_url' => null, 'license' => null],
    ]);

    $this->actingAs($user)->post(route('phones.images.verify', [$phone, $image]));

    expect($unrelatedReview->fresh()->status->value)->toBe('pending');
});

it('verifying an image with no linked review does not error', function () {
    $user = phoneDataUser(['phone-edit']);
    $phone = Phone::factory()->create();
    $image = PhoneImage::factory()->for($phone)->needsReview()->create(['is_primary' => true]);

    $this->actingAs($user)
        ->post(route('phones.images.verify', [$phone, $image]))
        ->assertRedirect(route('phones.edit', $phone->id));

    expect($image->fresh()->status->value)->toBe('verified');
});

it('promotes an image to primary on verify even when it was not already flagged primary, and demotes the previous primary', function () {
    // Regression test: a needs_review candidate is no longer born
    // is_primary=true (see PhoneImageCollector::collect()), so verify()
    // must explicitly promote it rather than relying on a pre-existing
    // flag - otherwise a manually-verified image would silently never
    // become visible on the public site.
    $user = phoneDataUser(['phone-edit']);
    $phone = Phone::factory()->create();
    $stalePrimary = PhoneImage::factory()->for($phone)->create(['status' => 'verified', 'is_primary' => true]);
    $candidate = PhoneImage::factory()->for($phone)->needsReview()->create(['is_primary' => false]);

    $this->actingAs($user)->post(route('phones.images.verify', [$phone, $candidate]));

    expect($candidate->fresh())
        ->status->value->toBe('verified')
        ->is_primary->toBeTrue();
    expect($stalePrimary->fresh()->is_primary)->toBeFalse();
    expect($phone->fresh()->primaryImage->id)->toBe($candidate->id);
});
