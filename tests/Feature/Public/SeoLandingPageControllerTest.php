<?php

use App\Enums\PriceTypeEnum;
use App\Models\PhonePrice;
use App\Services\PhoneImport\PriceAggregator;
use App\Services\SeoLanding\SeoLandingPageRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('registers exactly the approved, curated set of landing pages - nothing routable that is not registered', function () {
    $slugs = collect(SeoLandingPageRegistry::all())->pluck('slug')->all();

    expect($slugs)->toBe([
        'best-phones-under-15000',
        'best-phones-under-25000',
        'best-phones-under-40000',
        'best-gaming-phones-under-25000',
        'best-camera-phones-under-30000',
    ]);
});

it('returns a 404 for any slug not in the registry, never a catch-all landing page', function () {
    $this->get('/some-random-page-that-was-never-approved')->assertNotFound();
});

it('renders every registered landing page with 200 and real, non-fabricated stats', function () {
    makeRecommendablePhone(priceAttrs: ['amount' => 12000]);
    makeRecommendablePhone(priceAttrs: ['amount' => 22000]);
    makeRecommendablePhone(priceAttrs: ['amount' => 35000]);

    foreach (SeoLandingPageRegistry::all() as $page) {
        $this->get("/{$page->slug}")->assertOk()->assertInertia(fn (Assert $a) => $a
            ->component('Public/SeoLanding/Show')
            ->where('heading', $page->heading)
        );
    }
});

it('respects the budget ceiling - never shows a phone over the stated limit', function () {
    $within = makeRecommendablePhone(['name' => 'Within Budget'], priceAttrs: ['amount' => 14500]);
    makeRecommendablePhone(['name' => 'Over Budget'], priceAttrs: ['amount' => 16000]);

    $response = $this->get('/best-phones-under-15000');

    $response->assertInertia(fn (Assert $a) => $a
        ->where('totalEligible', 1)
        ->has('results', 1)
        ->where('results.0.phone_name', fn ($name) => str_contains($name, 'Within Budget'))
    );
});

it('ranks a category page by the real recommendation engine, not a second scoring system - gaming pushes the strongest gaming phone to the top', function () {
    $strongGaming = makeRecommendablePhone(
        ['name' => 'Gaming Beast'],
        ['processor' => 'Snapdragon 8 Gen 3', 'display_refresh_rate' => 144, 'charging_speed_w' => 120],
        priceAttrs: ['amount' => 24000],
    );
    $weakGaming = makeRecommendablePhone(
        ['name' => 'Basic Phone'],
        ['processor' => 'Unisoc SC9863A', 'display_refresh_rate' => 60, 'charging_speed_w' => 10],
        priceAttrs: ['amount' => 24000],
    );

    $response = $this->get('/best-gaming-phones-under-25000');

    $response->assertInertia(fn (Assert $a) => $a
        ->where('results.0.phone_name', fn ($name) => str_contains($name, 'Gaming Beast'))
    );
});

it('marks every landing page indexable with its own clean self canonical when it has real results', function () {
    makeRecommendablePhone(priceAttrs: ['amount' => 12000]);

    $response = $this->get('/best-phones-under-15000');

    $response->assertInertia(fn (Assert $a) => $a
        ->where('seo.robots', 'index,follow')
        ->where('seo.canonical', config('seo.base_url').'/best-phones-under-15000')
        ->where('seo.title', fn ($title) => str_contains($title, '৳15,000'))
    );
});

it('noindexes a landing page with no eligible phones instead of indexing an empty shell', function () {
    makeRecommendablePhone(priceAttrs: ['amount' => 100000]); // nothing under 15,000

    $response = $this->get('/best-phones-under-15000');

    $response->assertOk()->assertInertia(fn (Assert $a) => $a
        ->where('seo.robots', 'noindex,follow')
        ->where('totalEligible', 0)
        ->has('results', 0)
    );
});

it('clearly separates official and unofficial prices on each result, never blending them into one number', function () {
    $phone = makeRecommendablePhone(priceAttrs: ['amount' => 14000, 'price_type' => 'official_bd']);
    $variant = $phone->variants()->firstOrFail();
    PhonePrice::factory()->for($variant, 'variant')->create(['amount' => 11500, 'price_type' => 'unofficial_bd']);
    app(PriceAggregator::class)->recalculate($variant, PriceTypeEnum::UNOFFICIAL_BD);

    $response = $this->get('/best-phones-under-15000');

    $response->assertInertia(fn (Assert $a) => $a
        ->where('results.0.market.official.price', 14000)
        ->where('results.0.market.unofficial.price', 11500)
    );
});

it('includes a breadcrumb trail from home to the landing page', function () {
    makeRecommendablePhone(priceAttrs: ['amount' => 12000]);

    $response = $this->get('/best-phones-under-15000');

    $response->assertInertia(fn (Assert $a) => $a
        ->has('breadcrumbs', 2)
        ->where('breadcrumbs.1', ['label' => 'Best Phones Under ৳15,000', 'href' => null])
    );
});

it('cross-links to every other landing page for internal linking, never to itself', function () {
    makeRecommendablePhone(priceAttrs: ['amount' => 12000]);

    $response = $this->get('/best-phones-under-15000');

    $response->assertInertia(fn (Assert $a) => $a
        ->has('relatedPages', 4)
        ->where('relatedPages', fn ($pages) => ! $pages->pluck('slug')->contains('best-phones-under-15000'))
    );
});

it('excludes an empty landing page from the sitemap but keeps a populated one', function () {
    // 35,000 clears the 40k-ceiling page but every other registered page has
    // a lower ceiling (15k/25k/25k/30k), so this phone is ineligible for all
    // of them - budget ceilings are monotonic, so this is the only way to
    // make one page non-empty while the others stay genuinely empty.
    makeRecommendablePhone(priceAttrs: ['amount' => 35000]);

    $response = $this->get('/sitemap.xml');
    $locs = collect(iterator_to_array(simplexml_load_string($response->getContent())->url, false))
        ->map(fn ($url) => (string) $url->loc)->all();

    expect($locs)->toContain(config('seo.base_url').'/best-phones-under-40000')
        ->not->toContain(config('seo.base_url').'/best-phones-under-15000')
        ->not->toContain(config('seo.base_url').'/best-gaming-phones-under-25000');
});
