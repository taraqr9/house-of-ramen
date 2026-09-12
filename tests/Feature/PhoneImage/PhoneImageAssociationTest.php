<?php

use App\Models\PhoneImage;
use App\Models\PhoneVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('exposes only a verified, primary image as the phone\'s primary image', function () {
    $phone = makeRecommendablePhone(priceAttrs: ['amount' => 20000], withImage: false);

    PhoneImage::factory()->for($phone)->needsReview()->create(['is_primary' => true]);
    expect($phone->fresh()->primaryImage)->toBeNull();

    $verified = PhoneImage::factory()->for($phone)->create(['is_primary' => true]);
    expect($phone->fresh()->primaryImage?->id)->toBe($verified->id);
});

it('never surfaces a rejected image as primary even if it was previously flagged primary', function () {
    $phone = makeRecommendablePhone(priceAttrs: ['amount' => 20000], withImage: false);
    PhoneImage::factory()->for($phone)->rejected()->create();

    expect($phone->fresh()->primaryImage)->toBeNull();
});

it('associates an image with a specific variant independently of the phone-level primary image', function () {
    $phone = makeRecommendablePhone(priceAttrs: ['amount' => 20000], withImage: false);
    $variant = $phone->variants()->firstOrFail();
    // storage_gb (not just color) must differ: color alone no longer
    // distinguishes a variant's identity (see the migration that dropped
    // color from phone_variants' unique key - a retailer prices every
    // color of a given RAM/storage/region the same).
    $otherVariant = PhoneVariant::factory()->create(['phone_id' => $phone->id, 'storage_gb' => 256, 'color' => 'Blue']);

    $variantImage = PhoneImage::factory()->for($phone)->create(['phone_variant_id' => $otherVariant->id, 'is_primary' => false]);

    expect($variantImage->variant->id)->toBe($otherVariant->id)
        ->and($phone->images()->count())->toBe(1);
});

it('resolves a public URL only when a stored file path exists', function () {
    $withPath = PhoneImage::factory()->make(['path' => 'phones/1/test.webp']);
    $withoutPath = PhoneImage::factory()->make(['path' => null]);

    expect($withPath->url)->toContain('/storage/phones/1/test.webp')
        ->and($withoutPath->url)->toBeNull();
});

it('builds an absolute, fetchable URL from the configured app host - regression for the APP_URL-missing-port bug', function () {
    // A bare host with no scheme/port (e.g. APP_URL=http://localhost while the
    // dev server actually listens on :8000) silently produces a URL nothing
    // is listening on - the image "exists" in the DB but never renders for a
    // real user. Assert the URL is always rooted at the current app.url,
    // whatever it is, rather than just "contains the path" (which a
    // fully-broken host would still satisfy).
    config(['app.url' => 'http://localhost:8000']);

    $image = PhoneImage::factory()->make(['disk' => 'public', 'path' => 'phones/9/photo.webp']);

    expect($image->url)->toBe('http://localhost:8000/storage/phones/9/photo.webp');
    expect(parse_url($image->url, PHP_URL_PORT))->toBe(8000);
});

it('still shows a phone with no verified image on the browse page, with a null image_url for the frontend fallback', function () {
    $phone = makeRecommendablePhone(['name' => 'No Image Phone'], priceAttrs: ['amount' => 20000], withImage: false);
    PhoneImage::factory()->for($phone)->needsReview()->create(['is_primary' => true]);

    $this->get('/phones')->assertInertia(fn (Assert $page) => $page
        ->has('phones.data', 1)
        ->where('phones.data.0.image_url', null)
    );

    $this->get("/phones/{$phone->slug}")->assertInertia(fn (Assert $page) => $page
        ->where('phone.image_url', null)
    );
});

it('surfaces the verified image URL and its attribution on the browse page and the detail page', function () {
    $phone = makeRecommendablePhone(['name' => 'Imaged Phone'], priceAttrs: ['amount' => 20000], withImage: false);
    $image = PhoneImage::factory()->for($phone)->create(['is_primary' => true, 'attribution' => 'Jane Doe, CC BY-SA 4.0, via Wikimedia Commons']);

    $this->get('/phones')->assertInertia(fn (Assert $page) => $page
        ->where('phones.data.0.image_url', $image->url)
    );

    $this->get("/phones/{$phone->slug}")->assertInertia(fn (Assert $page) => $page
        ->where('phone.image_url', $image->url)
        ->where('phone.image_attribution', 'Jane Doe, CC BY-SA 4.0, via Wikimedia Commons')
    );
});
