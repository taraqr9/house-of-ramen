<?php

use App\Enums\ImageCollectionOutcomeEnum;
use App\Enums\ImageSearchAttemptStatusEnum;
use App\Models\Brand;
use App\Models\Phone;
use App\Models\PhoneImageSearchAttempt;
use App\Services\PhoneImage\PhoneImageCollector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function fakeJpegBytesForAttemptTest(): string
{
    $image = imagecreatetruecolor(400, 400);
    ob_start();
    imagejpeg($image);
    $bytes = ob_get_clean();
    imagedestroy($image);

    return $bytes;
}

it('records a verified attempt automatically when attachManual succeeds, with no separate command call needed', function () {
    Storage::fake('public');
    $phone = Phone::factory()->for(Brand::factory()->create(['name' => 'Nothing']), 'brand')
        ->create(['name' => 'Phone (2)']);

    Http::fake(['*' => Http::response(fakeJpegBytesForAttemptTest(), 200)]);

    $outcome = app(PhoneImageCollector::class)->attachManual(
        $phone,
        'https://example.com/nothing-phone-2.jpg',
        'https://example.com/product-page',
        'CC BY 3.0',
        'Some Photographer'
    );

    expect($outcome)->toBe(ImageCollectionOutcomeEnum::VERIFIED);

    $attempt = PhoneImageSearchAttempt::query()->where('phone_id', $phone->id)->firstOrFail();
    expect($attempt->status)->toBe(ImageSearchAttemptStatusEnum::VERIFIED)
        ->and($attempt->unresolved_reason)->toBeNull();
});

it('lets phones:mark-image-unresolved record a give-up with reason, sources tried, and candidate count', function () {
    $phone = Phone::factory()->for(Brand::factory()->create(['name' => 'Walton']), 'brand')
        ->create(['name' => 'Primo RX8']);

    Artisan::call('phones:mark-image-unresolved', [
        'phone' => $phone->slug,
        '--reason' => 'No accessible image source found after exhausting Commons and manufacturer site.',
        '--sources' => ['wikimedia_commons', 'manufacturer_site'],
        '--candidates' => 2,
    ]);

    $attempt = PhoneImageSearchAttempt::query()->where('phone_id', $phone->id)->firstOrFail();
    expect($attempt->status)->toBe(ImageSearchAttemptStatusEnum::UNRESOLVED)
        ->and($attempt->unresolved_reason)->toContain('No accessible image source')
        ->and($attempt->sources_tried)->toBe(['wikimedia_commons', 'manufacturer_site'])
        ->and($attempt->candidates_viewed)->toBe(2)
        ->and($attempt->last_attempted_at)->not->toBeNull();
});

it('refuses to mark a phone unresolved without a reason, so a give-up is never undocumented', function () {
    $phone = Phone::factory()->for(Brand::factory()->create(['name' => 'Walton']), 'brand')
        ->create(['name' => 'Primo RX8']);

    $exitCode = Artisan::call('phones:mark-image-unresolved', ['phone' => $phone->slug]);

    expect($exitCode)->not->toBe(0);
    expect(PhoneImageSearchAttempt::count())->toBe(0);
});

it('upserts on a repeat mark-unresolved call instead of creating a duplicate row', function () {
    $phone = Phone::factory()->for(Brand::factory()->create(['name' => 'Walton']), 'brand')
        ->create(['name' => 'Primo RX8']);

    Artisan::call('phones:mark-image-unresolved', ['phone' => $phone->slug, '--reason' => 'first attempt']);
    Artisan::call('phones:mark-image-unresolved', ['phone' => $phone->slug, '--reason' => 'second attempt, still nothing']);

    expect(PhoneImageSearchAttempt::where('phone_id', $phone->id)->count())->toBe(1);
    expect(PhoneImageSearchAttempt::where('phone_id', $phone->id)->first()->unresolved_reason)->toBe('second attempt, still nothing');
});

it('excludes both verified and unresolved phones from Phone::needsImageEnrichment, but still includes untouched ones', function () {
    Storage::fake('public');
    $brand = Brand::factory()->create(['name' => 'Test Brand']);

    $untouched = Phone::factory()->for($brand, 'brand')->create(['name' => 'Untouched Model', 'is_active' => true]);
    $verified = Phone::factory()->for($brand, 'brand')->create(['name' => 'Verified Model', 'is_active' => true]);
    $unresolved = Phone::factory()->for($brand, 'brand')->create(['name' => 'Unresolved Model', 'is_active' => true]);
    $inactive = Phone::factory()->for($brand, 'brand')->create(['name' => 'Inactive Model', 'is_active' => false]);

    Http::fake(['*' => Http::response(fakeJpegBytesForAttemptTest(), 200)]);
    app(PhoneImageCollector::class)->attachManual($verified, 'https://example.com/a.jpg', 'https://example.com/a', null, null);

    Artisan::call('phones:mark-image-unresolved', ['phone' => $unresolved->slug, '--reason' => 'nothing found']);

    $results = Phone::query()->needsImageEnrichment()->pluck('id')->all();

    expect($results)->toContain($untouched->id)
        ->and($results)->not->toContain($verified->id)
        ->and($results)->not->toContain($unresolved->id)
        ->and($results)->not->toContain($inactive->id);
});
