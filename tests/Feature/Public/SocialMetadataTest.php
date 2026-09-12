<?php

use App\Models\Brand;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('ships a default social-share image at the 1200x630 ratio social platforms expect', function () {
    $path = public_path(config('seo.default_og_image'));

    expect(is_file($path))->toBeTrue("Default OG image missing at {$path}");

    [$width, $height] = getimagesize($path);
    expect($width)->toBe(1200)->and($height)->toBe(630);
});

it('gives the homepage a valid, absolute og:image URL', function () {
    $response = $this->get('/');

    $response->assertInertia(fn (Assert $page) => $page
        ->where('seo.og_image', fn ($url) => str_starts_with($url, 'http') && str_contains($url, 'social-preview-1200x630.png'))
        ->where('seo.og_type', 'website')
        ->where('seo.robots', 'index,follow')
    );
});

it('gives a phone detail page its own dynamic title, description, canonical, and product OG type', function () {
    $brand = Brand::factory()->create(['name' => 'Samsung']);
    $phone = makeRecommendablePhone(
        ['brand_id' => $brand->id, 'name' => 'Galaxy S24 Ultra'],
        priceAttrs: ['amount' => 150000, 'price_type' => 'official_bd'],
    );

    $response = $this->get("/phones/{$phone->slug}");

    $response->assertInertia(fn (Assert $page) => $page
        ->where('seo.title', fn ($title) => str_contains($title, 'Galaxy S24 Ultra'))
        ->where('seo.canonical', config('seo.base_url')."/phones/{$phone->slug}")
        ->where('seo.og_type', 'product')
        ->where('seo.robots', 'index,follow')
        ->has('seo.og_image')
    );
});

it('falls back to the sitewide social image for a phone with no verified photo yet, rather than no image at all', function () {
    $phone = makeRecommendablePhone(withImage: false);

    $response = $this->get("/phones/{$phone->slug}");

    $response->assertInertia(fn (Assert $page) => $page
        ->where('seo.og_image', config('seo.base_url').'/'.config('seo.default_og_image'))
    );
});

it('gives the About page real social metadata using the sitewide default image', function () {
    $response = $this->get('/about');

    $response->assertInertia(fn (Assert $page) => $page
        ->where('seo.title', 'About Us')
        ->where('seo.robots', 'index,follow')
        ->where('seo.og_image', config('seo.base_url').'/'.config('seo.default_og_image'))
    );
});
