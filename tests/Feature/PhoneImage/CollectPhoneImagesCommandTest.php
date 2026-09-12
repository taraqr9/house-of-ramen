<?php

use App\Enums\ImageStatusEnum;
use App\Models\Brand;
use App\Models\Phone;
use App\Models\PhoneImage;
use App\Services\PhoneImage\Sources\WikimediaCommonsImageSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');

    config(['phone_image_sources.sources' => [
        'wikimedia_commons' => [
            'class' => WikimediaCommonsImageSource::class,
            'phone_source_key' => 'wikimedia_commons',
            'enabled' => true,
            'config' => ['user_agent' => 'TestBot/1.0', 'confidence_threshold' => 65],
        ],
    ]]);
});

it('skips phones that already have any image row by default', function () {
    $withImage = Phone::factory()->create();
    PhoneImage::factory()->for($withImage)->needsReview()->create();
    $withoutImage = Phone::factory()->create();

    Http::fake(['*' => Http::response(['query' => ['pages' => []]], 200)]);

    $this->artisan('phones:collect-images', ['--limit' => 10])->assertSuccessful();

    Http::assertSentCount(1);
});

it('re-attempts phones whose only image is needs_review/rejected when --fresh is passed, clearing the stale row first', function () {
    $phone = Phone::factory()->for(Brand::factory()->create(['name' => 'Samsung']), 'brand')
        ->create(['name' => 'Galaxy S24 Ultra']);
    $stale = PhoneImage::factory()->for($phone)->needsReview()->create(['is_primary' => true]);

    $image = imagecreatetruecolor(500, 500);
    ob_start();
    imagejpeg($image);
    $bytes = ob_get_clean();
    imagedestroy($image);

    Http::fake([
        'commons.wikimedia.org/*' => Http::response(['query' => ['pages' => [[
            'title' => 'File:Samsung '.$phone->name.'.jpg',
            'imageinfo' => [[
                'url' => 'https://upload.wikimedia.org/wikipedia/commons/x.jpg',
                'descriptionurl' => 'https://commons.wikimedia.org/wiki/File:X.jpg',
                'width' => 1000, 'height' => 800, 'mime' => 'image/jpeg',
                'extmetadata' => [
                    'Categories' => ['value' => 'Samsung '.$phone->name],
                    'LicenseShortName' => ['value' => 'CC0'],
                ],
            ]],
        ]]]], 200),
        'upload.wikimedia.org/*' => Http::response($bytes, 200),
    ]);

    $this->artisan('phones:collect-images', ['--fresh' => true, '--limit' => 10])->assertSuccessful();

    expect(PhoneImage::query()->find($stale->id))->toBeNull();

    $current = PhoneImage::query()->where('phone_id', $phone->id)->firstOrFail();
    expect($current->status)->toBe(ImageStatusEnum::VERIFIED);
});

it('processes a single phone by id or slug when one is given as an argument', function () {
    $target = Phone::factory()->create();
    $other = Phone::factory()->create();

    Http::fake(['*' => Http::response(['query' => ['pages' => []]], 200)]);

    $this->artisan('phones:collect-images', ['phone' => $target->slug])->assertSuccessful();

    Http::assertSentCount(1);
});
