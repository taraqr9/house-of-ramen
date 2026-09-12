<?php

use App\Enums\ImageCollectionOutcomeEnum;
use App\Enums\ImageStatusEnum;
use App\Enums\ReviewReasonEnum;
use App\Enums\ReviewStatusEnum;
use App\Models\Brand;
use App\Models\Phone;
use App\Models\PhoneDataReview;
use App\Models\PhoneImage;
use App\Models\PhoneSource;
use App\Services\PhoneImage\PhoneImageCollector;
use App\Services\PhoneImage\Sources\OpenverseImageSource;
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

    PhoneSource::factory()->create(['key' => 'wikimedia_commons', 'name' => 'Wikimedia Commons']);
});

function fakePhoneJpegBytes(): string
{
    $image = imagecreatetruecolor(500, 500);
    ob_start();
    imagejpeg($image);
    $bytes = ob_get_clean();
    imagedestroy($image);

    return $bytes;
}

function fakeCommonsPage(array $overrides = []): array
{
    return [
        'title' => $overrides['title'] ?? 'File:Samsung Galaxy S24 Ultra.jpg',
        'imageinfo' => [array_merge([
            'url' => 'https://upload.wikimedia.org/wikipedia/commons/a/samsung-s24-ultra.jpg',
            'descriptionurl' => 'https://commons.wikimedia.org/wiki/File:Samsung_Galaxy_S24_Ultra.jpg',
            'width' => 1000,
            'height' => 800,
            'mime' => 'image/jpeg',
            'extmetadata' => [
                'Categories' => ['value' => 'Samsung Galaxy S24 Ultra'],
                'LicenseShortName' => ['value' => 'CC BY-SA 4.0'],
                'Artist' => ['value' => '<a href="#">Jane Doe</a>'],
            ],
        ], $overrides['imageinfo'] ?? [])],
    ];
}

it('stores a high-confidence match as verified, makes it primary, and never opens a review', function () {
    $phone = Phone::factory()->for(Brand::factory()->create(['name' => 'Samsung']), 'brand')
        ->create(['name' => 'Galaxy S24 Ultra']);

    Http::fake([
        'commons.wikimedia.org/*' => Http::response(['query' => ['pages' => [fakeCommonsPage()]]], 200),
        'upload.wikimedia.org/*' => Http::response(fakePhoneJpegBytes(), 200),
    ]);

    $outcome = app(PhoneImageCollector::class)->collect($phone);

    expect($outcome)->toBe(ImageCollectionOutcomeEnum::VERIFIED);

    $image = PhoneImage::query()->where('phone_id', $phone->id)->firstOrFail();

    expect($image->status)->toBe(ImageStatusEnum::VERIFIED)
        ->and($image->is_primary)->toBeTrue()
        ->and($image->license)->toBe('CC BY-SA 4.0')
        ->and($image->attribution)->toContain('Jane Doe')
        ->and($image->match_confidence)->toBeGreaterThanOrEqual(65)
        ->and($image->source_id)->not->toBeNull();

    expect($phone->fresh()->primaryImage)->not->toBeNull();
    expect(PhoneDataReview::count())->toBe(0);
    Storage::disk('public')->assertExists($image->path);
});

it('routes a weak text/category match to needs_review and opens a data-quality review instead of publishing it', function () {
    $phone = Phone::factory()->for(Brand::factory()->create(['name' => 'Samsung']), 'brand')
        ->create(['name' => 'Galaxy S24 Ultra']);

    Http::fake([
        'commons.wikimedia.org/*' => Http::response(['query' => ['pages' => [fakeCommonsPage([
            'title' => 'File:Consumer Electronics Fair 2023.jpg',
            'imageinfo' => ['extmetadata' => [
                'Categories' => ['value' => 'Trade fairs'],
                'LicenseShortName' => ['value' => 'CC BY-SA 4.0'],
            ]],
        ])]]], 200),
        'upload.wikimedia.org/*' => Http::response(fakePhoneJpegBytes(), 200),
    ]);

    $outcome = app(PhoneImageCollector::class)->collect($phone);

    expect($outcome)->toBe(ImageCollectionOutcomeEnum::NEEDS_REVIEW);

    $image = PhoneImage::query()->where('phone_id', $phone->id)->firstOrFail();
    expect($image->status)->toBe(ImageStatusEnum::NEEDS_REVIEW);

    // Only a verified image is ever eligible to be shown - a needs_review
    // candidate must never be flagged primary either, not just excluded by
    // the read-path filter (regression test: this used to be true
    // unconditionally on every new image regardless of status).
    expect($image->is_primary)->toBeFalse();

    // Not verified -> the public relation must not surface it, so the
    // frontend falls back to the placeholder rather than showing an
    // unconfirmed match.
    expect($phone->fresh()->primaryImage)->toBeNull();

    $review = PhoneDataReview::query()->where('phone_id', $phone->id)->firstOrFail();
    expect($review->reason)->toBe(ReviewReasonEnum::IMAGE_NEEDS_REVIEW)
        ->and($review->status)->toBe(ReviewStatusEnum::PENDING)
        ->and($review->details['image_id'])->toBe($image->id);
});

it('does not stack a second image_needs_review row when a repeat --fresh collection re-attempts the same still-unconfident phone', function () {
    $phone = Phone::factory()->for(Brand::factory()->create(['name' => 'Samsung']), 'brand')
        ->create(['name' => 'Galaxy S24 Ultra']);

    Http::fake([
        'commons.wikimedia.org/*' => Http::response(['query' => ['pages' => [fakeCommonsPage([
            'title' => 'File:Consumer Electronics Fair 2023.jpg',
            'imageinfo' => ['extmetadata' => [
                'Categories' => ['value' => 'Trade fairs'],
                'LicenseShortName' => ['value' => 'CC BY-SA 4.0'],
            ]],
        ])]]], 200),
        'upload.wikimedia.org/*' => Http::response(fakePhoneJpegBytes(), 200),
    ]);

    $collector = app(PhoneImageCollector::class);
    $collector->collect($phone);
    $collector->collect($phone);

    expect(PhoneDataReview::query()->where('phone_id', $phone->id)->where('reason', ReviewReasonEnum::IMAGE_NEEDS_REVIEW)->count())->toBe(1);
});

it('caps confidence and forces review when Commons has no resolvable license, even on a perfect title match', function () {
    $phone = Phone::factory()->for(Brand::factory()->create(['name' => 'Samsung']), 'brand')
        ->create(['name' => 'Galaxy S24 Ultra']);

    Http::fake([
        'commons.wikimedia.org/*' => Http::response(['query' => ['pages' => [fakeCommonsPage([
            'imageinfo' => ['extmetadata' => [
                'Categories' => ['value' => 'Samsung Galaxy S24 Ultra'],
                // No LicenseShortName / UsageTerms at all.
            ]],
        ])]]], 200),
        'upload.wikimedia.org/*' => Http::response(fakePhoneJpegBytes(), 200),
    ]);

    $outcome = app(PhoneImageCollector::class)->collect($phone);

    expect($outcome)->toBe(ImageCollectionOutcomeEnum::NEEDS_REVIEW);

    $image = PhoneImage::query()->where('phone_id', $phone->id)->firstOrFail();
    expect($image->status)->toBe(ImageStatusEnum::NEEDS_REVIEW)
        ->and($image->match_confidence)->toBeLessThanOrEqual(40)
        ->and($image->license)->toBeNull();
});

it('rejects tiny thumbnails and obvious non-photos (box art, screenshots) before they ever reach scoring', function () {
    $phone = Phone::factory()->for(Brand::factory()->create(['name' => 'Samsung']), 'brand')
        ->create(['name' => 'Galaxy S24 Ultra']);

    Http::fake([
        'commons.wikimedia.org/*' => Http::response(['query' => ['pages' => [
            fakeCommonsPage(['title' => 'File:Samsung Galaxy S24 Ultra Box.jpg']),
            fakeCommonsPage(['title' => 'File:Samsung Galaxy S24 Ultra icon.jpg', 'imageinfo' => ['width' => 40, 'height' => 40]]),
        ]]], 200),
    ]);

    $outcome = app(PhoneImageCollector::class)->collect($phone);

    expect($outcome)->toBe(ImageCollectionOutcomeEnum::NOT_FOUND);
    expect(PhoneImage::count())->toBe(0);
});

it('never auto-publishes a different sibling model even when short tokens like "pro" fragment-match', function () {
    // Real bug found during manual QA: "Realme 12 Pro+" naively matched a
    // photo titled "Front of realme C35" (wrong model entirely) because
    // "realme" and "pro" both appeared somewhere in unrelated
    // title/category text. Fragment overlap alone must never cross the
    // auto-publish threshold - only a real contiguous "brand model"
    // phrase match should.
    $phone = Phone::factory()->for(Brand::factory()->create(['name' => 'Realme']), 'brand')
        ->create(['name' => 'Realme 12 Pro+']);

    Http::fake([
        'commons.wikimedia.org/*' => Http::response(['query' => ['pages' => [fakeCommonsPage([
            'title' => 'File:Front of realme C35.jpg',
            'imageinfo' => ['extmetadata' => [
                'Categories' => ['value' => 'Realme smartphones|Products introduced in 2023'],
                'LicenseShortName' => ['value' => 'CC BY-SA 4.0'],
            ]],
        ])]]], 200),
        'upload.wikimedia.org/*' => Http::response(fakePhoneJpegBytes(), 200),
    ]);

    $outcome = app(PhoneImageCollector::class)->collect($phone);

    expect($outcome)->toBe(ImageCollectionOutcomeEnum::NEEDS_REVIEW);

    $image = PhoneImage::query()->where('phone_id', $phone->id)->firstOrFail();
    expect($image->match_confidence)->toBeLessThan(65);
});

it('does not let a short token match inside an unrelated word count as a match ("pro" must not match "province")', function () {
    $phone = Phone::factory()->for(Brand::factory()->create(['name' => 'OnePlus']), 'brand')
        ->create(['name' => 'OnePlus 12R']);

    Http::fake([
        'commons.wikimedia.org/*' => Http::response(['query' => ['pages' => [fakeCommonsPage([
            'title' => 'File:Jameh Mosque of a Kashmar province.jpg',
            'imageinfo' => ['extmetadata' => [
                'Categories' => ['value' => 'Mosques in Iran|Buildings and structures in Razavi Khorasan Province'],
                'LicenseShortName' => ['value' => 'CC0'],
            ]],
        ])]]], 200),
        'upload.wikimedia.org/*' => Http::response(fakePhoneJpegBytes(), 200),
    ]);

    $outcome = app(PhoneImageCollector::class)->collect($phone);

    expect($outcome)->toBe(ImageCollectionOutcomeEnum::NEEDS_REVIEW);

    $image = PhoneImage::query()->where('phone_id', $phone->id)->firstOrFail();
    expect($image->match_confidence)->toBe(0);
});

it('never treats a "Taken with [phone]" EXIF category as a photo of that phone', function () {
    // Real bug: Wikimedia auto-adds a "Taken with Samsung Galaxy M05"
    // category (from EXIF data) to ANY photo shot on that device - a dam,
    // a plate of food, a street scene. That category phrase-matched at
    // high confidence despite the photo having nothing to do with the
    // phone itself.
    $phone = Phone::factory()->for(Brand::factory()->create(['name' => 'Samsung']), 'brand')
        ->create(['name' => 'Galaxy M05']);

    Http::fake([
        'commons.wikimedia.org/*' => Http::response(['query' => ['pages' => [fakeCommonsPage([
            'title' => 'File:Luchi Alur Dam.jpg',
            'imageinfo' => ['extmetadata' => [
                'Categories' => ['value' => 'Dum Aloo|Self-published work|Luchi in West Bengal|Taken with Samsung Galaxy M05'],
                'LicenseShortName' => ['value' => 'CC BY-SA 4.0'],
            ]],
        ])]]], 200),
        'upload.wikimedia.org/*' => Http::response(fakePhoneJpegBytes(), 200),
    ]);

    $outcome = app(PhoneImageCollector::class)->collect($phone);

    expect($outcome)->toBe(ImageCollectionOutcomeEnum::NEEDS_REVIEW);

    $image = PhoneImage::query()->where('phone_id', $phone->id)->firstOrFail();
    expect($image->match_confidence)->toBeLessThan(65);
});

it('does not match a base model to a photo of its Pro/Plus/Ultra sibling just because the base name is a text prefix of it', function () {
    // Real bug: "iPhone 12" is a literal substring prefix of "iPhone 12
    // Pro" - a naive phrase-containment check matched the base model to
    // a photo of the Pro sibling (different cameras, different phone).
    // Same class of bug the task explicitly warns about for phone
    // deduplication (Pro vs Pro+, Plus vs non-Plus), here showing up in
    // image matching instead.
    $phone = Phone::factory()->for(Brand::factory()->create(['name' => 'Apple']), 'brand')
        ->create(['name' => 'iPhone 12']);

    Http::fake([
        'commons.wikimedia.org/*' => Http::response(['query' => ['pages' => [fakeCommonsPage([
            'title' => 'File:Apple iPhone 12 Pro - Cameras.jpg',
            'imageinfo' => ['extmetadata' => [
                'Categories' => ['value' => 'Apple iPhone 12 Pro'],
                'LicenseShortName' => ['value' => 'CC BY-SA 4.0'],
            ]],
        ])]]], 200),
        'upload.wikimedia.org/*' => Http::response(fakePhoneJpegBytes(), 200),
    ]);

    $outcome = app(PhoneImageCollector::class)->collect($phone);

    expect($outcome)->toBe(ImageCollectionOutcomeEnum::NEEDS_REVIEW);

    $image = PhoneImage::query()->where('phone_id', $phone->id)->firstOrFail();
    expect($image->match_confidence)->toBeLessThan(65);
});

it('still trusts a phrase match when the model itself legitimately contains the qualifier word', function () {
    $phone = Phone::factory()->for(Brand::factory()->create(['name' => 'Apple']), 'brand')
        ->create(['name' => 'iPhone 12 Pro']);

    Http::fake([
        'commons.wikimedia.org/*' => Http::response(['query' => ['pages' => [fakeCommonsPage([
            'title' => 'File:Apple iPhone 12 Pro - Cameras.jpg',
            'imageinfo' => ['extmetadata' => [
                'Categories' => ['value' => 'Apple iPhone 12 Pro'],
                'LicenseShortName' => ['value' => 'CC BY-SA 4.0'],
            ]],
        ])]]], 200),
        'upload.wikimedia.org/*' => Http::response(fakePhoneJpegBytes(), 200),
    ]);

    $outcome = app(PhoneImageCollector::class)->collect($phone);

    expect($outcome)->toBe(ImageCollectionOutcomeEnum::VERIFIED);
});

it('does not double the brand in the search phrase when the model name already includes it, so a real Commons title still phrase-matches', function () {
    // Real bug: several catalogue brands (Redmi, POCO, Realme, Vivo,
    // Oppo, OnePlus, Honor, Nothing, Infinix, Tecno, itel) store the
    // phone name WITH the brand already in it ("Redmi Note 15 5G" under
    // brand "Redmi"). Building the search phrase as "{brand} {model}"
    // produced "Redmi Redmi Note 15 5G", which never appears in any real
    // image title/category - silently capping every one of these
    // brands' phones to weak fragment-overlap scoring (well below the
    // verify threshold) even when a perfectly good, correctly-titled
    // Commons photo existed.
    $phone = Phone::factory()->for(Brand::factory()->create(['name' => 'Redmi']), 'brand')
        ->create(['name' => 'Redmi Note 15 5G']);

    Http::fake([
        'commons.wikimedia.org/*' => Http::response(['query' => ['pages' => [fakeCommonsPage([
            'title' => 'File:REDMI Note 15 5G camera island.jpg',
            'imageinfo' => ['extmetadata' => [
                'Categories' => ['value' => 'Redmi Note 15 5G'],
                'LicenseShortName' => ['value' => 'CC BY-SA 4.0'],
            ]],
        ])]]], 200),
        'upload.wikimedia.org/*' => Http::response(fakePhoneJpegBytes(), 200),
    ]);

    $outcome = app(PhoneImageCollector::class)->collect($phone);

    expect($outcome)->toBe(ImageCollectionOutcomeEnum::VERIFIED);

    $image = PhoneImage::query()->where('phone_id', $phone->id)->firstOrFail();
    expect($image->match_confidence)->toBeGreaterThanOrEqual(65);
});

it('picks the best candidate across multiple providers instead of settling for the first provider that returns anything', function () {
    $phone = Phone::factory()->for(Brand::factory()->create(['name' => 'Samsung']), 'brand')
        ->create(['name' => 'Galaxy S24 Ultra']);

    config(['phone_image_sources.sources' => [
        'wikimedia_commons' => [
            'class' => WikimediaCommonsImageSource::class,
            'phone_source_key' => 'wikimedia_commons',
            'enabled' => true,
            'config' => ['user_agent' => 'TestBot/1.0', 'confidence_threshold' => 65],
        ],
        'openverse' => [
            'class' => OpenverseImageSource::class,
            'phone_source_key' => 'openverse',
            'enabled' => true,
            'config' => ['user_agent' => 'TestBot/1.0', 'confidence_threshold' => 65],
        ],
    ]]);
    PhoneSource::factory()->create(['key' => 'openverse', 'name' => 'Openverse']);

    // Wikimedia returns first but only a weak, unrelated fragment match;
    // Openverse returns a real, correctly-titled photo. The weak result
    // must never block the strong one just because it was found first.
    Http::fake([
        'commons.wikimedia.org/*' => Http::response(['query' => ['pages' => [fakeCommonsPage([
            'title' => 'File:Consumer Electronics Fair 2023.jpg',
            'imageinfo' => ['extmetadata' => [
                'Categories' => ['value' => 'Trade fairs'],
                'LicenseShortName' => ['value' => 'CC BY-SA 4.0'],
            ]],
        ])]]], 200),
        'api.openverse.org/*' => Http::response(['results' => [[
            'title' => 'Samsung Galaxy S24 Ultra',
            'url' => 'https://upload.wikimedia.org/wikipedia/commons/samsung-s24-ultra.jpg',
            'foreign_landing_url' => 'https://commons.wikimedia.org/wiki/File:Samsung_Galaxy_S24_Ultra.jpg',
            'width' => 1000, 'height' => 800, 'filetype' => 'jpg',
            'license' => 'by-sa', 'license_version' => '4.0',
            'attribution' => '"Samsung Galaxy S24 Ultra" by Jane Doe is licensed under CC BY-SA 4.0.',
            'tags' => [['name' => 'samsung galaxy s24 ultra']],
        ]]], 200),
        'upload.wikimedia.org/*' => Http::response(fakePhoneJpegBytes(), 200),
    ]);

    $outcome = app(PhoneImageCollector::class)->collect($phone);

    expect($outcome)->toBe(ImageCollectionOutcomeEnum::VERIFIED);

    $image = PhoneImage::query()->where('phone_id', $phone->id)->firstOrFail();
    expect($image->source_url)->toBe('https://commons.wikimedia.org/wiki/File:Samsung_Galaxy_S24_Ultra.jpg')
        ->and($image->match_confidence)->toBeGreaterThanOrEqual(65);
});

it('does not verify a base model against its Pro+ sibling when the filename concatenates the qualifier without a separator', function () {
    // Real bug found in production: "Redmi Note 14" (base model) verified
    // against a photo titled "Redmi_Note_14_ProPlus.jpg" - the Pro+
    // sibling. The sibling-conflict guard only checked exact word
    // equality, but Commons filenames often concatenate qualifiers
    // without a separator ("ProPlus", "ProMax"), so "proplus" never
    // matched the literal word "pro" or "plus" in the blocklist.
    $phone = Phone::factory()->for(Brand::factory()->create(['name' => 'Redmi']), 'brand')
        ->create(['name' => 'Redmi Note 14']);

    Http::fake([
        'commons.wikimedia.org/*' => Http::response(['query' => ['pages' => [fakeCommonsPage([
            'title' => 'File:Redmi Note 14 ProPlus.jpg',
            'imageinfo' => ['extmetadata' => [
                'Categories' => ['value' => 'Redmi Note 14 Pro+'],
                'LicenseShortName' => ['value' => 'CC BY-SA 4.0'],
            ]],
        ])]]], 200),
        'upload.wikimedia.org/*' => Http::response(fakePhoneJpegBytes(), 200),
    ]);

    $outcome = app(PhoneImageCollector::class)->collect($phone);

    expect($outcome)->toBe(ImageCollectionOutcomeEnum::NEEDS_REVIEW);

    $image = PhoneImage::query()->where('phone_id', $phone->id)->firstOrFail();
    expect($image->match_confidence)->toBeLessThan(65);
});

it('still trusts a title with trailing punctuation/index text when the phone itself legitimately has the matched qualifier', function () {
    $phone = Phone::factory()->for(Brand::factory()->create(['name' => 'Redmi']), 'brand')
        ->create(['name' => 'Redmi Note 14 Pro']);

    Http::fake([
        'commons.wikimedia.org/*' => Http::response(['query' => ['pages' => [fakeCommonsPage([
            'title' => 'File:Redmi Note 14 Pro (2).jpg',
            'imageinfo' => ['extmetadata' => [
                'Categories' => ['value' => 'Redmi Note 14 Pro'],
                'LicenseShortName' => ['value' => 'CC BY-SA 4.0'],
            ]],
        ])]]], 200),
        'upload.wikimedia.org/*' => Http::response(fakePhoneJpegBytes(), 200),
    ]);

    $outcome = app(PhoneImageCollector::class)->collect($phone);

    expect($outcome)->toBe(ImageCollectionOutcomeEnum::VERIFIED);
});

it('does not verify a base model against its Plus sibling when the title uses a literal "+" with no separating space', function () {
    // Real bug found in production: "Galaxy S25" (base model) verified
    // against a photo titled "Samsung Galaxy S25+.jpg" - the Plus
    // sibling. ltrim() doesn't treat "+" as a separator to strip, so the
    // qualifier-extraction regex simply failed to match anything at a
    // "+" character and the conflict went undetected.
    $phone = Phone::factory()->for(Brand::factory()->create(['name' => 'Samsung']), 'brand')
        ->create(['name' => 'Galaxy S25']);

    Http::fake([
        'commons.wikimedia.org/*' => Http::response(['query' => ['pages' => [fakeCommonsPage([
            'title' => 'File:Samsung Galaxy S25+.jpg',
            'imageinfo' => ['extmetadata' => [
                'Categories' => ['value' => 'Samsung Galaxy S25+'],
                'LicenseShortName' => ['value' => 'CC BY-SA 4.0'],
            ]],
        ])]]], 200),
        'upload.wikimedia.org/*' => Http::response(fakePhoneJpegBytes(), 200),
    ]);

    $outcome = app(PhoneImageCollector::class)->collect($phone);

    expect($outcome)->toBe(ImageCollectionOutcomeEnum::NEEDS_REVIEW);

    $image = PhoneImage::query()->where('phone_id', $phone->id)->firstOrFail();
    expect($image->match_confidence)->toBeLessThan(65);
});

it('still verifies a "+" model against a matching "+"-titled photo of itself', function () {
    $phone = Phone::factory()->for(Brand::factory()->create(['name' => 'Samsung']), 'brand')
        ->create(['name' => 'Galaxy S25+']);

    Http::fake([
        'commons.wikimedia.org/*' => Http::response(['query' => ['pages' => [fakeCommonsPage([
            'title' => 'File:Samsung Galaxy S25+.jpg',
            'imageinfo' => ['extmetadata' => [
                'Categories' => ['value' => 'Samsung Galaxy S25+'],
                'LicenseShortName' => ['value' => 'CC BY-SA 4.0'],
            ]],
        ])]]], 200),
        'upload.wikimedia.org/*' => Http::response(fakePhoneJpegBytes(), 200),
    ]);

    $outcome = app(PhoneImageCollector::class)->collect($phone);

    expect($outcome)->toBe(ImageCollectionOutcomeEnum::VERIFIED);
});

it('returns not_found and creates nothing when the source has no candidates at all', function () {
    $phone = Phone::factory()->for(Brand::factory()->create(['name' => 'Obscure Brand']), 'brand')
        ->create(['name' => 'Totally Unknown Model X99']);

    Http::fake([
        'commons.wikimedia.org/*' => Http::response(['query' => ['pages' => []]], 200),
    ]);

    $outcome = app(PhoneImageCollector::class)->collect($phone);

    expect($outcome)->toBe(ImageCollectionOutcomeEnum::NOT_FOUND);
    expect(PhoneImage::count())->toBe(0);
});
