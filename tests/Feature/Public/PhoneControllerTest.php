<?php

use App\Enums\PriceTypeEnum;
use App\Models\Brand;
use App\Models\Phone;
use App\Models\PhoneAvailability;
use App\Models\PhoneImage;
use App\Models\PhonePrice;
use App\Models\PhoneVariant;
use App\Services\PhoneImport\PriceAggregator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('lists active phones with their cheapest price on the browse page', function () {
    makeRecommendablePhone(['name' => 'Cheap Phone'], priceAttrs: ['amount' => 15000]);
    makeRecommendablePhone(['name' => 'Pricey Phone'], priceAttrs: ['amount' => 80000]);

    $response = $this->get('/phones');

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Public/Phones/Index')
        ->has('phones.data', 2)
    );
});

it('never lets a phone with no market price rank first under "price: low to high"', function () {
    // Regression test: MySQL treats NULL as the lowest value in ASC order,
    // so a priceless phone used to sort to the very top of price_asc as
    // if it were the cheapest phone in the catalogue - genuinely
    // misleading. It must still appear under the default sort, just never
    // ranked by a price it doesn't have.
    $noPricePhone = Phone::factory()->create(['name' => 'No Price Phone']);
    PhoneVariant::factory()->create(['phone_id' => $noPricePhone->id]);

    makeRecommendablePhone(['name' => 'Cheap Phone'], priceAttrs: ['amount' => 15000]);

    $ascResponse = $this->get('/phones?sort=price_asc');
    $ascResponse->assertInertia(fn (Assert $page) => $page
        ->has('phones.data', 1)
        ->where('phones.data.0.name', 'Cheap Phone')
    );

    $descResponse = $this->get('/phones?sort=price_desc');
    $descResponse->assertInertia(fn (Assert $page) => $page
        ->has('phones.data', 1)
        ->where('phones.data.0.name', 'Cheap Phone')
    );

    // Still visible under the default sort, honestly labeled.
    $defaultResponse = $this->get('/phones');
    $defaultResponse->assertInertia(fn (Assert $page) => $page->has('phones.data', 2));
});

it('filters the browse page by budget ceiling', function () {
    makeRecommendablePhone(['name' => 'Cheap Phone'], priceAttrs: ['amount' => 15000]);
    makeRecommendablePhone(['name' => 'Pricey Phone'], priceAttrs: ['amount' => 80000]);

    $response = $this->get('/phones?max_budget=20000');

    $response->assertInertia(fn (Assert $page) => $page
        ->has('phones.data', 1)
        ->where('phones.data.0.name', 'Cheap Phone')
    );
});

it('never shows an inactive phone on the browse page or its detail page', function () {
    $inactive = makeRecommendablePhone(['is_active' => false]);

    $this->get('/phones')->assertInertia(fn (Assert $page) => $page->has('phones.data', 0));
    $this->get("/phones/{$inactive->slug}")->assertNotFound();
});

it('renders a phone detail page with real spec data and derived highlights, never fabricated content', function () {
    $phone = makeRecommendablePhone(
        ['name' => 'Detail Test Phone'],
        ['battery_capacity_mah' => 6000, 'charging_speed_w' => 65, 'nfc' => true],
        priceAttrs: ['amount' => 25000, 'price_type' => 'official_bd'],
    );

    $response = $this->get("/phones/{$phone->slug}");

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Public/Phones/Show')
        ->where('phone.name', 'Detail Test Phone')
        ->where('phone.market.official.price', 25000)
        ->has('highlights.pros')
        ->where('highlights.pros', fn ($pros) => $pros->contains('Large 6,000mAh battery for long daily use.'))
    );
});

it('shows both official and unofficial market prices on the detail page when both exist for the same variant', function () {
    $phone = makeRecommendablePhone(priceAttrs: ['amount' => 199900, 'price_type' => 'official_bd']);
    $variant = $phone->variants()->firstOrFail();
    $unofficialPrice = PhonePrice::factory()->for($variant, 'variant')->create(['amount' => 154000, 'price_type' => 'unofficial_bd']);
    app(PriceAggregator::class)->recalculate($variant, PriceTypeEnum::UNOFFICIAL_BD);

    $response = $this->get("/phones/{$phone->slug}");

    $response->assertInertia(fn (Assert $page) => $page
        ->where('phone.market.official.price', 199900)
        ->where('phone.market.unofficial.price', 154000)
    );
});

it('uses variant-specific pricing - each variant of the same phone keeps its own market price', function () {
    $phone = makeRecommendablePhone(priceAttrs: ['amount' => 30000, 'price_type' => 'official_bd']);

    $largerVariant = PhoneVariant::factory()->create(['phone_id' => $phone->id, 'ram_gb' => 12, 'storage_gb' => 512]);
    $largerPrice = PhonePrice::factory()->for($largerVariant, 'variant')->create(['amount' => 42000, 'price_type' => 'official_bd']);
    app(PriceAggregator::class)->recalculate($largerVariant, PriceTypeEnum::OFFICIAL_BD);

    $response = $this->get("/phones/{$phone->slug}");

    // The detail page's headline price follows the cheapest variant (30,000),
    // not the larger/pricier one (42,000) - but both variants' own prices
    // still show up in the per-variant price table.
    $response->assertOk();
    $props = $response->viewData('page')['props'];
    $variants = collect($props['phone']['variant_regions'])->pluck('variants')->flatten(1);
    $amounts = $variants->pluck('prices')->flatten(1)->pluck('amount')->sort()->values()->all();

    expect($props['phone']['market']['official']['price'])->toBe(30000.0)
        ->and($variants)->toHaveCount(2)
        ->and($amounts)->toBe([30000.0, 42000.0]);
});

it('returns a 404 for a phone slug that does not exist', function () {
    $this->get('/phones/does-not-exist')->assertNotFound();
});

it('only suggests related phones from the same brand', function () {
    $brand = Brand::factory()->create();
    $other = Brand::factory()->create();

    $phone = makeRecommendablePhone(['brand_id' => $brand->id, 'name' => 'Main Phone']);
    makeRecommendablePhone(['brand_id' => $brand->id, 'name' => 'Sibling Phone']);
    makeRecommendablePhone(['brand_id' => $other->id, 'name' => 'Unrelated Phone']);

    $response = $this->get("/phones/{$phone->slug}");

    $response->assertInertia(fn (Assert $page) => $page
        ->has('related', 1)
        ->where('related.0.name', 'Sibling Phone')
    );
});

it('marks the plain browse listing indexable with a bare canonical', function () {
    $response = $this->get('/phones');

    $response->assertInertia(fn (Assert $page) => $page
        ->where('seo.robots', 'index,follow')
        ->where('seo.canonical', config('seo.base_url').'/phones')
    );
});

it('keeps a paginated browse listing indexable with a page-specific canonical', function () {
    $response = $this->get('/phones?page=2');

    $response->assertInertia(fn (Assert $page) => $page
        ->where('seo.robots', 'index,follow')
        ->where('seo.canonical', config('seo.base_url').'/phones?page=2')
    );
});

it('noindexes a filtered browse listing and canonicalizes it back to the bare listing', function () {
    $brand = Brand::factory()->create(['slug' => 'samsung']);
    makeRecommendablePhone(['brand_id' => $brand->id]);

    foreach (['/phones?brand=samsung', '/phones?max_budget=20000', '/phones?sort=price_asc'] as $url) {
        $this->get($url)->assertInertia(fn (Assert $page) => $page
            ->where('seo.robots', 'noindex,follow')
            ->where('seo.canonical', config('seo.base_url').'/phones')
        );
    }
});

it('builds real phone detail metadata - title/description reflect the actual name, brand, and price, never fabricated', function () {
    $brand = Brand::factory()->create(['name' => 'Samsung']);
    $phone = makeRecommendablePhone(
        ['brand_id' => $brand->id, 'name' => 'Galaxy S24 Ultra'],
        ['processor' => 'Snapdragon 8 Gen 3'],
        priceAttrs: ['amount' => 150000, 'price_type' => 'official_bd'],
    );

    $response = $this->get("/phones/{$phone->slug}");

    $response->assertInertia(fn (Assert $page) => $page
        ->where('seo.robots', 'index,follow')
        ->where('seo.canonical', config('seo.base_url')."/phones/{$phone->slug}")
        ->where('seo.title', fn ($title) => str_contains($title, 'Galaxy S24 Ultra') && str_contains($title, '150,000'))
        ->where('seo.description', fn ($d) => str_contains($d, 'Samsung') && str_contains($d, 'Snapdragon 8 Gen 3'))
        ->has('breadcrumbs', 4)
        ->where('breadcrumbs.2.label', 'Samsung')
        ->where('breadcrumbs.3', ['label' => 'Galaxy S24 Ultra', 'href' => null])
    );
});

it('uses the phone\'s own verified photo as the OG image', function () {
    $withPhoto = makeRecommendablePhone(withImage: false);
    PhoneImage::factory()->for($withPhoto, 'phone')->create([
        'disk' => 'public',
        'path' => 'phones/1/test.webp',
    ]);

    $this->get("/phones/{$withPhoto->slug}")->assertInertia(fn (Assert $page) => $page
        ->where('seo.og_image', fn ($url) => str_contains($url, 'phones/1/test.webp'))
    );
});

it('still renders a phone detail page for a phone with no verified image, falling back to a null image_url', function () {
    $withoutPhoto = makeRecommendablePhone(withImage: false);

    $this->get("/phones/{$withoutPhoto->slug}")->assertInertia(fn (Assert $page) => $page
        ->where('phone.image_url', null)
    );
});

it('reflects the real, most recently collected availability status - never assumes in stock', function () {
    $phone = makeRecommendablePhone(priceAttrs: ['amount' => 30000, 'price_type' => 'official_bd']);
    $variant = $phone->variants()->firstOrFail();
    PhoneAvailability::create(['phone_variant_id' => $variant->id, 'status' => 'out_of_stock']);

    $response = $this->get("/phones/{$phone->slug}");

    $response->assertInertia(fn (Assert $page) => $page
        ->where('phone.schema_availability', 'https://schema.org/OutOfStock')
    );
});

// -----------------------------------------------------------------------
// Global/Chinese version simplification (see App\Enums\PhoneRegionEnum,
// App\Services\Presentation\PhoneVariantRegionSelector).
// -----------------------------------------------------------------------

it('shows exactly one catalogue card for a phone even when it has multiple RAM/storage variants', function () {
    $phone = makeRecommendablePhone(priceAttrs: ['amount' => 30000, 'price_type' => 'official_bd']);
    PhoneVariant::factory()->create(['phone_id' => $phone->id, 'ram_gb' => 12, 'storage_gb' => 512]);

    $response = $this->get('/phones');

    $response->assertInertia(fn (Assert $page) => $page->has('phones.data', 1));
});

it('prefers the Global price and labels it as such on the catalogue card when a Chinese variant is also cheaper', function () {
    $phone = makeRecommendablePhone(priceAttrs: ['amount' => 72000, 'price_type' => 'unofficial_bd']);
    $chineseVariant = PhoneVariant::factory()->create(['phone_id' => $phone->id, 'region' => 'China']);
    $chinesePrice = PhonePrice::factory()->for($chineseVariant, 'variant')->create(['amount' => 61000, 'price_type' => 'unofficial_bd']);
    app(PriceAggregator::class)->recalculate($chineseVariant, PriceTypeEnum::UNOFFICIAL_BD);

    $response = $this->get('/phones');

    $response->assertInertia(fn (Assert $page) => $page
        ->where('phones.data.0.price', 72000)
        ->where('phones.data.0.region', 'Global')
    );
});

it('falls back to the Chinese price on the catalogue card when the phone has no Global variant at all', function () {
    $phone = Phone::factory()->create();
    $chineseVariant = PhoneVariant::factory()->create(['phone_id' => $phone->id, 'region' => 'China']);
    PhoneImage::factory()->for($phone)->create();
    $price = PhonePrice::factory()->for($chineseVariant, 'variant')->create(['amount' => 61000, 'price_type' => 'unofficial_bd']);
    app(PriceAggregator::class)->recalculate($chineseVariant, PriceTypeEnum::UNOFFICIAL_BD);

    $response = $this->get('/phones');

    $response->assertInertia(fn (Assert $page) => $page
        ->where('phones.data.0.price', 61000)
        ->where('phones.data.0.region', 'Chinese')
    );
});

it('hides the region selector on the detail page when the phone has only one version', function () {
    $phone = makeRecommendablePhone(priceAttrs: ['amount' => 30000, 'price_type' => 'official_bd']);
    PhoneVariant::factory()->create(['phone_id' => $phone->id, 'ram_gb' => 12, 'storage_gb' => 512]);

    $response = $this->get("/phones/{$phone->slug}");

    $response->assertInertia(fn (Assert $page) => $page
        ->has('phone.variant_regions', 1)
        ->where('phone.variant_regions.0.region', 'Global')
        ->has('phone.variant_regions.0.variants', 2)
    );
});

it('groups variants under both Global and Chinese, Global first, when a phone genuinely has both versions', function () {
    $phone = makeRecommendablePhone(priceAttrs: ['amount' => 72000, 'price_type' => 'unofficial_bd']);
    $chineseVariant = PhoneVariant::factory()->create(['phone_id' => $phone->id, 'region' => 'China']);
    PhonePrice::factory()->for($chineseVariant, 'variant')->create(['amount' => 61000, 'price_type' => 'unofficial_bd']);
    app(PriceAggregator::class)->recalculate($chineseVariant, PriceTypeEnum::UNOFFICIAL_BD);

    $response = $this->get("/phones/{$phone->slug}");

    $response->assertInertia(fn (Assert $page) => $page
        ->has('phone.variant_regions', 2)
        ->where('phone.variant_regions.0.region', 'Global')
        ->where('phone.variant_regions.1.region', 'Chinese')
        ->where('phone.primary_region', 'Global')
    );
});

it('treats a legacy "Bangladesh" region value as the Global version on the detail page, never a second group', function () {
    $phone = makeRecommendablePhone(['name' => 'Legacy Region Phone'], variantAttrs: ['region' => 'Bangladesh'], priceAttrs: ['amount' => 20000, 'price_type' => 'official_bd']);

    $response = $this->get("/phones/{$phone->slug}");

    $response->assertInertia(fn (Assert $page) => $page
        ->has('phone.variant_regions', 1)
        ->where('phone.variant_regions.0.region', 'Global')
    );
});

it('keeps Global official and Global unofficial prices separate within the same region group', function () {
    $phone = makeRecommendablePhone(priceAttrs: ['amount' => 199900, 'price_type' => 'official_bd']);
    $variant = $phone->variants()->firstOrFail();
    PhonePrice::factory()->for($variant, 'variant')->create(['amount' => 154000, 'price_type' => 'unofficial_bd']);
    app(PriceAggregator::class)->recalculate($variant, PriceTypeEnum::UNOFFICIAL_BD);

    $response = $this->get("/phones/{$phone->slug}");
    $props = $response->viewData('page')['props'];

    $prices = collect($props['phone']['variant_regions'][0]['variants'][0]['prices'])->keyBy('type');

    expect($props['phone']['variant_regions'])->toHaveCount(1)
        ->and($props['phone']['variant_regions'][0]['region'])->toBe('Global')
        ->and($prices->firstWhere('is_official', true)['amount'])->toBe(199900.0)
        ->and($prices->firstWhere('is_official', false)['amount'])->toBe(154000.0);
});

it('loses no price or configuration data when grouping variants by region on the detail page', function () {
    $phone = makeRecommendablePhone(priceAttrs: ['amount' => 72000, 'price_type' => 'unofficial_bd']);
    $officialForSame = $phone->variants()->firstOrFail();
    PhonePrice::factory()->for($officialForSame, 'variant')->create(['amount' => 75000, 'price_type' => 'official_bd']);
    app(PriceAggregator::class)->recalculate($officialForSame, PriceTypeEnum::OFFICIAL_BD);

    $chineseVariant = PhoneVariant::factory()->create(['phone_id' => $phone->id, 'region' => 'China', 'ram_gb' => 16, 'storage_gb' => 512]);
    PhonePrice::factory()->for($chineseVariant, 'variant')->create(['amount' => 61000, 'price_type' => 'unofficial_bd']);
    app(PriceAggregator::class)->recalculate($chineseVariant, PriceTypeEnum::UNOFFICIAL_BD);

    $response = $this->get("/phones/{$phone->slug}");
    $props = $response->viewData('page')['props'];

    $allPrices = collect($props['phone']['variant_regions'])
        ->pluck('variants')->flatten(1)
        ->pluck('prices')->flatten(1)
        ->pluck('amount')->sort()->values()->all();

    expect(collect($props['phone']['variant_regions'])->pluck('variants')->flatten(1))->toHaveCount(2)
        ->and($allPrices)->toBe([61000.0, 72000.0, 75000.0]);
});
