<?php

use App\Models\Brand;
use App\Models\Phone;
use App\Models\PhonePrice;
use App\Models\PhoneVariant;
use App\Services\PhoneImport\PriceAggregator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('renders the public homepage with live price bracket counts', function () {
    makeRecommendablePhone(priceAttrs: ['amount' => 12000]);
    makeRecommendablePhone(priceAttrs: ['amount' => 70000]);

    $response = $this->get('/');

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Public/Home')
        ->has('priceBrackets', 9)
        ->has('stats.phone_count')
        ->where('stats.phone_count', 2)
    );
});

it('counts a phone toward its price bracket even with no image at all - regression for the verified-image-gated count bug', function () {
    // The bug: priceBracketsWithCounts() used to inner-join phone_images
    // requiring status=verified, silently shrinking every bracket's count
    // down to "phones under X WITH a verified image" - a phone with a
    // real, cheap price but no image (or only a needs_review/rejected
    // one) simply vanished from every bracket despite appearing on the
    // actual /phones?max_budget=... listing, which has no image
    // requirement at all.
    makeRecommendablePhone(['name' => 'No Image Phone'], priceAttrs: ['amount' => 12000], withImage: false);

    $response = $this->get('/');

    $response->assertInertia(fn (Assert $page) => $page
        ->where('priceBrackets.0.label', 'Under ৳15,000')
        ->where('priceBrackets.0.count', 1)
    );
});

it('produces a bracket count that exactly matches the phones the equivalent listing filter returns', function () {
    makeRecommendablePhone(['name' => 'Fifteen Exactly'], priceAttrs: ['amount' => 15000]);
    makeRecommendablePhone(['name' => 'Just Over'], priceAttrs: ['amount' => 15001]);
    makeRecommendablePhone(['name' => 'Well Under'], priceAttrs: ['amount' => 5000], withImage: false);

    $home = $this->get('/');
    $home->assertInertia(fn (Assert $page) => $page->where('priceBrackets.0.count', 2));

    $listing = $this->get('/phones?max_budget=15000');
    $listing->assertInertia(fn (Assert $page) => $page->has('phones.data', 2));
});

it('does not double count a phone with multiple variants toward a price bracket', function () {
    $phone = makeRecommendablePhone(['name' => 'Multi Variant'], priceAttrs: ['amount' => 12000]);
    $secondVariant = PhoneVariant::factory()->create(['phone_id' => $phone->id, 'storage_gb' => 256]);
    $secondPrice = PhonePrice::factory()->create(['phone_variant_id' => $secondVariant->id, 'amount' => 13000]);
    app(PriceAggregator::class)->recalculate($secondVariant, $secondPrice->price_type);

    $response = $this->get('/');

    $response->assertInertia(fn (Assert $page) => $page->where('priceBrackets.0.count', 1));
});

it('excludes a phone with no usable price from every bracket without erroring', function () {
    $phone = Phone::factory()->create(['name' => 'No Price Phone']);
    PhoneVariant::factory()->create(['phone_id' => $phone->id]);
    // No PhonePrice/PhoneMarketPrice row at all - a phone the import
    // pipeline has seen but never priced.

    $response = $this->get('/');

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('priceBrackets.0.count', 0)
        ->where('priceBrackets.8.count', 0)
    );
});

it('never counts an inactive phone toward a price bracket', function () {
    makeRecommendablePhone(['name' => 'Inactive Cheap Phone', 'is_active' => false], priceAttrs: ['amount' => 5000]);

    $response = $this->get('/');

    $response->assertInertia(fn (Assert $page) => $page->where('priceBrackets.0.count', 0));
});

it('includes a real example recommendation computed by the actual engine', function () {
    makeRecommendablePhone(priceAttrs: ['amount' => 20000]);

    $response = $this->get('/');

    $response->assertInertia(fn (Assert $page) => $page
        ->component('Public/Home')
        ->has('exampleResult.phone_name')
        ->has('exampleResult.match_score')
        ->has('exampleResult.reasons')
    );
});

it('marks the homepage indexable with a self canonical', function () {
    $response = $this->get('/');

    $response->assertInertia(fn (Assert $page) => $page
        ->where('seo.robots', 'index,follow')
        ->where('seo.canonical', config('seo.base_url').'/')
    );
});

it('supplies the embedded Find My Phone questionnaire with brands and the backend-supported dimensions', function () {
    $brand = Brand::factory()->create(['is_active' => true]);
    makeRecommendablePhone(['brand_id' => $brand->id]);

    $response = $this->get('/');

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Public/Home')
        ->has('brands', 1)
        ->has('priceBrackets')
        ->has('dimensions')
    );
});

it('does not list a brand with no phones in the embedded questionnaire', function () {
    Brand::factory()->create(['is_active' => true]);

    $response = $this->get('/');

    $response->assertInertia(fn (Assert $page) => $page->has('brands', 0));
});

it('links to brand pages with real phone counts for internal linking', function () {
    $brand = Brand::factory()->create(['name' => 'Samsung', 'slug' => 'samsung']);
    makeRecommendablePhone(['brand_id' => $brand->id]);
    makeRecommendablePhone(['brand_id' => $brand->id]);

    $response = $this->get('/');

    $response->assertInertia(fn (Assert $page) => $page
        ->has('popularBrands', 1)
        ->where('popularBrands.0.slug', 'samsung')
        ->where('popularBrands.0.count', 2)
    );
});
