<?php

use App\Enums\ImageStatusEnum;
use App\Enums\ReviewReasonEnum;
use App\Enums\ReviewStatusEnum;
use App\Models\Brand;
use App\Models\Phone;
use App\Models\PhoneDataReview;
use App\Models\PhoneImage;
use App\Models\PhoneSource;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('leaves a verified image alone when its stored source title still matches the phone', function () {
    $phone = Phone::factory()->for(Brand::factory()->create(['name' => 'Samsung']), 'brand')
        ->create(['name' => 'Galaxy S24 Ultra']);

    $image = PhoneImage::factory()->for($phone)->create([
        'source_url' => 'https://commons.wikimedia.org/wiki/File:Samsung_Galaxy_S24_Ultra.jpg',
        'match_confidence' => 95,
    ]);

    $this->artisan('phones:audit-images')->assertSuccessful();

    expect($image->refresh()->status)->toBe(ImageStatusEnum::VERIFIED);
});

it('flags a verified image whose stored source title no longer matches the phone under current rules', function () {
    $phone = Phone::factory()->for(Brand::factory()->create(['name' => 'Samsung']), 'brand')
        ->create(['name' => 'Galaxy S24']);

    // Title actually names the "Ultra" sibling - a mismatch the current
    // matcher's sibling-conflict detection would reject outright.
    $image = PhoneImage::factory()->for($phone)->create([
        'source_url' => 'https://commons.wikimedia.org/wiki/File:Samsung_Galaxy_S24_Ultra.jpg',
        'match_confidence' => 95,
        'is_primary' => true,
    ]);

    $this->artisan('phones:audit-images')->assertSuccessful();

    expect($image->refresh()->status)->toBe(ImageStatusEnum::VERIFIED);

    $this->artisan('phones:audit-images', ['--fix' => true])->assertSuccessful();

    $image->refresh();
    expect($image->status)->toBe(ImageStatusEnum::NEEDS_REVIEW)
        ->and($image->is_primary)->toBeFalse();

    expect(PhoneDataReview::query()
        ->where('phone_id', $phone->id)
        ->where('reason', ReviewReasonEnum::IMAGE_NEEDS_REVIEW)
        ->where('status', ReviewStatusEnum::PENDING)
        ->exists())->toBeTrue();
});

it('skips images attached via the manual-research pipeline', function () {
    $phone = Phone::factory()->for(Brand::factory()->create(['name' => 'Apple']), 'brand')
        ->create(['name' => 'iPhone 17 Pro']);

    $manualSource = PhoneSource::factory()->create(['key' => 'manual_research']);

    $image = PhoneImage::factory()->for($phone)->create([
        'source_id' => $manualSource->id,
        'source_url' => 'https://commons.wikimedia.org/wiki/File:Completely_Unrelated.jpg',
        'match_confidence' => 100,
    ]);

    $this->artisan('phones:audit-images', ['--fix' => true])->assertSuccessful();

    expect($image->refresh()->status)->toBe(ImageStatusEnum::VERIFIED);
});

it('skips images whose source_url is not a recognisable Wikimedia file page', function () {
    $phone = Phone::factory()->for(Brand::factory()->create(['name' => 'Google']), 'brand')
        ->create(['name' => 'Pixel 10']);

    $image = PhoneImage::factory()->for($phone)->create([
        'source_url' => 'https://api.openverse.org/v1/images/some-uuid/',
        'match_confidence' => 90,
    ]);

    $this->artisan('phones:audit-images', ['--fix' => true])->assertSuccessful();

    expect($image->refresh()->status)->toBe(ImageStatusEnum::VERIFIED);
});
