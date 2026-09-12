<?php

use App\Enums\PriceTypeEnum;
use App\Models\Brand;
use App\Models\PhonePrice;
use App\Services\PhoneImport\PriceAggregator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('renders a brand page with real aggregate stats, not filler copy', function () {
    $brand = Brand::factory()->create(['name' => 'Samsung', 'slug' => 'samsung', 'is_active' => true]);
    makeRecommendablePhone(['brand_id' => $brand->id, 'name' => 'Galaxy A05'], priceAttrs: ['amount' => 12000, 'price_type' => 'official_bd']);
    $flagship = makeRecommendablePhone(['brand_id' => $brand->id, 'name' => 'Galaxy S24 Ultra'], priceAttrs: ['amount' => 150000, 'price_type' => 'official_bd']);
    $variant = $flagship->variants()->firstOrFail();
    PhonePrice::factory()->for($variant, 'variant')->create(['amount' => 140000, 'price_type' => 'unofficial_bd']);
    app(PriceAggregator::class)->recalculate($variant, PriceTypeEnum::UNOFFICIAL_BD);

    $response = $this->get('/phones/brand/samsung');

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Public/Phones/Brand')
        ->where('brand.name', 'Samsung')
        ->where('stats.phone_count', 2)
        ->where('stats.min_price', 12000)
        // The flagship's own displayed price is its cheapest current market
        // (140,000 unofficial), same rule the phone detail/browse pages use
        // - not the official price in isolation.
        ->where('stats.max_price', 140000)
        ->where('stats.official_count', 2)
        ->where('stats.unofficial_count', 1)
        ->has('phones', 2)
    );
});

it('marks a brand page indexable with its own clean canonical', function () {
    $brand = Brand::factory()->create(['slug' => 'xiaomi', 'is_active' => true]);
    makeRecommendablePhone(['brand_id' => $brand->id]);

    $response = $this->get('/phones/brand/xiaomi');

    $response->assertInertia(fn (Assert $page) => $page
        ->where('seo.robots', 'index,follow')
        ->where('seo.canonical', config('seo.base_url').'/phones/brand/xiaomi')
    );
});

it('returns a 404 for a brand slug that does not exist', function () {
    $this->get('/phones/brand/does-not-exist')->assertNotFound();
});

it('returns a 404 for a real but inactive brand', function () {
    $brand = Brand::factory()->create(['slug' => 'inactive-brand', 'is_active' => false]);
    makeRecommendablePhone(['brand_id' => $brand->id]);

    $this->get('/phones/brand/inactive-brand')->assertNotFound();
});

it('returns a 404 for a brand with no active phones rather than an empty page', function () {
    $brand = Brand::factory()->create(['slug' => 'no-phones', 'is_active' => true]);

    $this->get('/phones/brand/no-phones')->assertNotFound();
});

it('includes a breadcrumb trail from home through phones to the brand', function () {
    $brand = Brand::factory()->create(['name' => 'Realme', 'slug' => 'realme', 'is_active' => true]);
    makeRecommendablePhone(['brand_id' => $brand->id]);

    $response = $this->get('/phones/brand/realme');

    $response->assertInertia(fn (Assert $page) => $page
        ->has('breadcrumbs', 3)
        ->where('breadcrumbs.2', ['label' => 'Realme', 'href' => null])
    );
});
