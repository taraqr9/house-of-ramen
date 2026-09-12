<?php

use App\Enums\PriceTypeEnum;
use App\Models\Phone;
use App\Models\PhonePrice;
use App\Models\PhoneSpec;
use App\Models\PhoneVariant;
use App\Services\PhoneImport\PriceAggregator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('renders the compare page with no phones selected when none are requested', function () {
    makeRecommendablePhone();

    $response = $this->get('/compare');

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Public/Compare/Index')
        ->has('selected', 0)
        ->has('pickerOptions', 1)
    );
});

it('loads the requested phones by slug, in the order given, with their specs', function () {
    $first = makeRecommendablePhone(['name' => 'First Phone']);
    $second = makeRecommendablePhone(['name' => 'Second Phone']);

    $response = $this->get("/compare?phones={$second->slug},{$first->slug}");

    $response->assertInertia(fn (Assert $page) => $page
        ->has('selected', 2)
        ->where('selected.0.name', 'Second Phone')
        ->where('selected.1.name', 'First Phone')
        ->has('selected.0.spec')
    );
});

it('shows official and unofficial prices separately in the comparison output, never one misleading combined price', function () {
    $phone = makeRecommendablePhone(['name' => 'Dual Market Phone'], priceAttrs: ['amount' => 199900, 'price_type' => 'official_bd']);
    $variant = $phone->variants()->firstOrFail();
    PhonePrice::factory()->for($variant, 'variant')->create(['amount' => 154000, 'price_type' => 'unofficial_bd']);
    app(PriceAggregator::class)->recalculate($variant, PriceTypeEnum::UNOFFICIAL_BD);

    $response = $this->get("/compare?phones={$phone->slug}");

    $response->assertInertia(fn (Assert $page) => $page
        ->where('selected.0.market.official.price', 199900)
        ->where('selected.0.market.unofficial.price', 154000)
    );
});

it('never selects more than the maximum comparable phones even if more slugs are passed', function () {
    $slugs = collect(range(1, 6))->map(fn () => makeRecommendablePhone()->slug)->implode(',');

    $response = $this->get("/compare?phones={$slugs}");

    $response->assertInertia(fn (Assert $page) => $page->has('selected', 4));
});

it('attaches the same 8-dimension phone statistics profile used on the detail page, per selected phone', function () {
    $phone = makeRecommendablePhone(['name' => 'Compare Stats Phone']);

    $response = $this->get("/compare?phones={$phone->slug}");

    $response->assertInertia(fn (Assert $page) => $page
        ->has('selected.0.performanceProfile', 8)
        ->where('selected.0.performanceProfile.0.key', 'performance')
        ->has('selected.0.performanceProfile.0.stars')
        ->has('selected.0.performanceProfile.0.score')
    );
});

it('produces identical statistics for the same phone whether viewed on its detail page or in a comparison', function () {
    $phone = makeRecommendablePhone(['name' => 'Consistency Phone']);

    $detail = $this->get("/phones/{$phone->slug}");
    $compare = $this->get("/compare?phones={$phone->slug}");

    $detailProfile = $detail->viewData('page')['props']['performanceProfile'];
    $compareProfile = $compare->viewData('page')['props']['selected'][0]['performanceProfile'];

    expect($compareProfile)->toEqual($detailProfile);
});

it('gives every selected phone its own independent statistics profile, not one shared score', function () {
    $strong = makeRecommendablePhone(['name' => 'Strong Phone', 'category' => 'flagship']);
    PhoneSpec::query()->where('phone_id', $strong->id)->update(['processor' => 'Apple A17 Pro']);
    $weak = makeRecommendablePhone(['name' => 'Weak Phone', 'category' => 'entry']);
    PhoneSpec::query()->where('phone_id', $weak->id)->update(['processor' => 'Unisoc']);

    $response = $this->get("/compare?phones={$strong->slug},{$weak->slug}");

    $response->assertInertia(fn (Assert $page) => $page
        ->has('selected.0.performanceProfile')
        ->has('selected.1.performanceProfile')
    );

    $props = $response->viewData('page')['props'];
    $strongPerf = collect($props['selected'][0]['performanceProfile'])->firstWhere('key', 'performance')['score'];
    $weakPerf = collect($props['selected'][1]['performanceProfile'])->firstWhere('key', 'performance')['score'];

    expect($strongPerf)->toBeGreaterThan($weakPerf);
});

it('prefers the Global variant/price for comparison even when a Chinese variant of the same phone is cheaper', function () {
    $phone = makeRecommendablePhone(['name' => 'Dual Version Phone'], priceAttrs: ['amount' => 72000, 'price_type' => 'unofficial_bd']);
    $chineseVariant = PhoneVariant::factory()->create(['phone_id' => $phone->id, 'region' => 'China']);
    PhonePrice::factory()->for($chineseVariant, 'variant')->create(['amount' => 61000, 'price_type' => 'unofficial_bd']);
    app(PriceAggregator::class)->recalculate($chineseVariant, PriceTypeEnum::UNOFFICIAL_BD);

    $response = $this->get("/compare?phones={$phone->slug}");

    $response->assertInertia(fn (Assert $page) => $page
        ->where('selected.0.market.unofficial.price', 72000)
        ->where('selected.0.region', 'Global')
    );
});

it('falls back to the Chinese variant for comparison, honestly labeled, when the phone has no Global variant', function () {
    $phone = Phone::factory()->create();
    $chineseVariant = PhoneVariant::factory()->create(['phone_id' => $phone->id, 'region' => 'China']);
    PhonePrice::factory()->for($chineseVariant, 'variant')->create(['amount' => 61000, 'price_type' => 'unofficial_bd']);
    app(PriceAggregator::class)->recalculate($chineseVariant, PriceTypeEnum::UNOFFICIAL_BD);

    $response = $this->get("/compare?phones={$phone->slug}");

    $response->assertInertia(fn (Assert $page) => $page
        ->where('selected.0.market.unofficial.price', 61000)
        ->where('selected.0.region', 'Chinese')
    );
});

it('always noindexes the compare page - every ?phones= combination is arbitrary user state, not a distinct page', function () {
    $first = makeRecommendablePhone();
    $second = makeRecommendablePhone();

    foreach (['/compare', "/compare?phones={$first->slug},{$second->slug}"] as $url) {
        $this->get($url)->assertInertia(fn (Assert $page) => $page
            ->where('seo.robots', 'noindex,follow')
            ->where('seo.canonical', config('seo.base_url').'/compare')
        );
    }
});
