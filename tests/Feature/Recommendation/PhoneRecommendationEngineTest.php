<?php

use App\Enums\PhoneStatusEnum;
use App\Enums\PriceTypeEnum;
use App\Models\Brand;
use App\Models\Phone;
use App\Models\PhoneImage;
use App\Models\PhonePrice;
use App\Models\PhoneVariant;
use App\Services\PhoneImport\PriceAggregator;
use App\Services\Recommendation\PhoneRecommendationCriteria;
use App\Services\Recommendation\PhoneRecommendationEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function engine(): PhoneRecommendationEngine
{
    return app(PhoneRecommendationEngine::class);
}

it('excludes phones priced above the maximum budget', function () {
    $within = makeRecommendablePhone(priceAttrs: ['amount' => 20000]);
    $over = makeRecommendablePhone(priceAttrs: ['amount' => 60000]);

    $results = engine()->recommend(PhoneRecommendationCriteria::fromArray(['max_budget' => 30000]));

    $ids = collect($results)->pluck('phone_id');
    expect($ids)->toContain($within->id)->not->toContain($over->id);
});

it('ranks differently depending on which dimensions the user cares about', function () {
    // Same price and comparable overall quality - the only meaningful
    // difference is gaming-relevant specs (chipset/refresh) vs
    // camera-relevant specs, so the two importance profiles below should
    // genuinely disagree on which is "better" rather than both agreeing
    // with whichever phone happens to be stronger everywhere.
    $shared = [
        'battery_capacity_mah' => 4500, 'charging_speed_w' => 30, 'camera_has_ois' => false,
        'front_camera' => '8 MP, f/2.0', 'ultrawide_camera' => null, 'telephoto_camera' => null,
    ];

    $gamingPhone = makeRecommendablePhone(
        specAttrs: array_merge($shared, ['processor' => 'Qualcomm Snapdragon 8 Gen 3', 'display_refresh_rate' => 144, 'main_camera' => '12 MP, f/2.2']),
        priceAttrs: ['amount' => 40000],
    );
    $cameraPhone = makeRecommendablePhone(
        specAttrs: array_merge($shared, ['processor' => 'MediaTek Dimensity 7050', 'display_refresh_rate' => 60, 'main_camera' => '108 MP, f/1.7, OIS', 'camera_has_ois' => true]),
        priceAttrs: ['amount' => 40000],
    );

    $gamingFocused = engine()->recommend(PhoneRecommendationCriteria::fromArray([
        'max_budget' => 50000, 'importance' => ['gaming' => 5, 'camera' => 1],
    ]));
    $cameraFocused = engine()->recommend(PhoneRecommendationCriteria::fromArray([
        'max_budget' => 50000, 'importance' => ['gaming' => 1, 'camera' => 5],
    ]));

    expect($gamingFocused[0]['phone_id'])->toBe($gamingPhone->id)
        ->and($cameraFocused[0]['phone_id'])->toBe($cameraPhone->id)
        ->and($gamingFocused[0]['phone_id'])->not->toBe($cameraFocused[0]['phone_id']);
});

it('ranks the stronger battery phone first for a battery-focused user', function () {
    $bigBattery = makeRecommendablePhone(specAttrs: ['battery_capacity_mah' => 6500], priceAttrs: ['amount' => 30000]);
    $smallBattery = makeRecommendablePhone(specAttrs: ['battery_capacity_mah' => 3200], priceAttrs: ['amount' => 30000]);

    $results = engine()->recommend(PhoneRecommendationCriteria::fromArray([
        'max_budget' => 40000, 'importance' => ['battery' => 5],
    ]));

    expect($results[0]['phone_id'])->toBe($bigBattery->id);
});

it('still returns a sensible ranking for a balanced user who only sets a budget', function () {
    makeRecommendablePhone(priceAttrs: ['amount' => 25000]);
    makeRecommendablePhone(priceAttrs: ['amount' => 28000]);

    $results = engine()->recommend(PhoneRecommendationCriteria::fromArray(['max_budget' => 35000]));

    expect($results)->not->toBeEmpty();
    foreach ($results as $result) {
        expect($result['match_score'])->toBeGreaterThanOrEqual(0)->toBeLessThanOrEqual(100);
    }
});

it('boosts a preferred brand when candidates are otherwise tied', function () {
    $preferredBrand = Brand::factory()->create();
    $otherBrand = Brand::factory()->create();

    $specs = ['processor' => 'Qualcomm Snapdragon 7 Gen 3', 'battery_capacity_mah' => 5000, 'display_refresh_rate' => 120, 'main_camera' => '50 MP, f/1.8, OIS'];

    $preferred = makeRecommendablePhone(['brand_id' => $preferredBrand->id], $specs, priceAttrs: ['amount' => 30000]);
    $other = makeRecommendablePhone(['brand_id' => $otherBrand->id], $specs, priceAttrs: ['amount' => 30000]);

    $results = engine()->recommend(PhoneRecommendationCriteria::fromArray([
        'max_budget' => 40000, 'preferred_brand_ids' => [$preferredBrand->id],
    ]));

    expect($results[0]['phone_id'])->toBe($preferred->id);
});

it('never returns a phone from an excluded brand', function () {
    $excludedBrand = Brand::factory()->create();
    $excludedPhone = makeRecommendablePhone(['brand_id' => $excludedBrand->id], priceAttrs: ['amount' => 25000]);
    $allowedPhone = makeRecommendablePhone(priceAttrs: ['amount' => 25000]);

    $results = engine()->recommend(PhoneRecommendationCriteria::fromArray([
        'max_budget' => 40000, 'excluded_brand_ids' => [$excludedBrand->id],
    ]));

    $ids = collect($results)->pluck('phone_id');
    expect($ids)->not->toContain($excludedPhone->id)->toContain($allowedPhone->id);
});

it('excludes unofficial-only phones when official_only is requested', function () {
    $official = makeRecommendablePhone(priceAttrs: ['amount' => 25000, 'price_type' => 'official_bd']);
    $unofficialOnly = makeRecommendablePhone(priceAttrs: ['amount' => 25000, 'price_type' => 'unofficial_bd']);

    $officialOnlyResults = engine()->recommend(PhoneRecommendationCriteria::fromArray([
        'max_budget' => 40000, 'price_preference' => 'official',
    ]));
    $bothResults = engine()->recommend(PhoneRecommendationCriteria::fromArray(['max_budget' => 40000]));

    $officialOnlyIds = collect($officialOnlyResults)->pluck('phone_id');
    $bothIds = collect($bothResults)->pluck('phone_id');

    expect($officialOnlyIds)->toContain($official->id)->not->toContain($unofficialOnly->id)
        ->and($bothIds)->toContain($unofficialOnly->id);
});

it('makes a phone eligible on its cheaper unofficial price under "both", but rejects it under "official" when only the official price exceeds budget', function () {
    // The task's own worked example: official ৳21,000 / unofficial ৳17,500,
    // user budget ৳18,000. Real catalogue shape: POCO M6 Plus at official
    // ৳18,999 / unofficial ৳17,000 against an ৳18,000 budget.
    $phone = makeRecommendablePhone(priceAttrs: ['amount' => 18999, 'price_type' => 'official_bd']);
    $variant = $phone->variants()->firstOrFail();
    $unofficialPrice = PhonePrice::factory()->for($variant, 'variant')->create(['amount' => 17000, 'price_type' => 'unofficial_bd']);
    app(PriceAggregator::class)->recalculate($variant, PriceTypeEnum::UNOFFICIAL_BD);

    $both = engine()->recommend(PhoneRecommendationCriteria::fromArray(['max_budget' => 18000, 'price_preference' => 'both']));
    $officialOnly = engine()->recommend(PhoneRecommendationCriteria::fromArray(['max_budget' => 18000, 'price_preference' => 'official']));

    $bothMatch = collect($both)->firstWhere('phone_id', $phone->id);
    $officialOnlyMatch = collect($officialOnly)->firstWhere('phone_id', $phone->id);

    expect($bothMatch)->not->toBeNull()
        ->and($bothMatch['price'])->toBe(17000.0)
        ->and($bothMatch['price_type'])->toBe('unofficial_bd')
        ->and($officialOnlyMatch)->toBeNull();
});

it('lets a cheaper, higher-quality older phone outrank a newer, weaker one', function () {
    $newerWeaker = makeRecommendablePhone(
        ['release_date' => now(), 'category' => 'midrange'],
        [
            'processor' => 'Qualcomm Snapdragon 6 Gen 1', 'battery_capacity_mah' => 4000, 'display_refresh_rate' => 90,
            'main_camera' => '50 MP, f/1.8', 'camera_has_ois' => false, 'os_update_years' => 3, 'security_update_years' => 4,
        ],
        priceAttrs: ['amount' => 30000],
    );

    $olderStronger = makeRecommendablePhone(
        ['release_date' => now()->subMonths(20), 'category' => 'flagship'],
        [
            'processor' => 'Qualcomm Snapdragon 7+ Gen 3', 'battery_capacity_mah' => 5000, 'display_refresh_rate' => 120,
            'main_camera' => '108 MP, f/1.7, OIS', 'camera_has_ois' => true, 'ultrawide_camera' => '8 MP',
            'os_update_years' => 3, 'security_update_years' => 4,
        ],
        priceAttrs: ['amount' => 25000],
    );

    $results = engine()->recommend(PhoneRecommendationCriteria::fromArray(['max_budget' => 40000]));

    expect($results[0]['phone_id'])->toBe($olderStronger->id);
});

it('scores the cheaper of two similarly-capable phones higher on value for money', function () {
    $specs = ['processor' => 'Qualcomm Snapdragon 7 Gen 3', 'battery_capacity_mah' => 5000, 'display_refresh_rate' => 120, 'main_camera' => '50 MP, f/1.8, OIS'];

    $cheaper = makeRecommendablePhone(specAttrs: $specs, priceAttrs: ['amount' => 22000]);
    $pricier = makeRecommendablePhone(specAttrs: $specs, priceAttrs: ['amount' => 45000]);

    $results = engine()->recommend(PhoneRecommendationCriteria::fromArray(['max_budget' => 50000]));

    $cheaperResult = collect($results)->firstWhere('phone_id', $cheaper->id);
    $pricierResult = collect($results)->firstWhere('phone_id', $pricier->id);

    expect($cheaperResult['score_breakdown']['value'])->toBeGreaterThan($pricierResult['score_breakdown']['value']);
});

it('penalizes a phone whose software support has already ended', function () {
    $fresh = makeRecommendablePhone(
        ['release_date' => now()],
        ['os_update_years' => 4, 'security_update_years' => 5],
        priceAttrs: ['amount' => 25000],
    );
    $stale = makeRecommendablePhone(
        ['release_date' => now()->subYears(5)],
        ['os_update_years' => 2, 'security_update_years' => 3],
        priceAttrs: ['amount' => 25000],
    );

    $results = engine()->recommend(PhoneRecommendationCriteria::fromArray(['max_budget' => 40000]));

    $freshResult = collect($results)->firstWhere('phone_id', $fresh->id);
    $staleResult = collect($results)->firstWhere('phone_id', $stale->id);

    expect($freshResult['score_breakdown']['software'])->toBeGreaterThan($staleResult['score_breakdown']['software']);
});

it('still scores a phone with no spec row instead of crashing', function () {
    $phone = makeRecommendablePhone(specAttrs: null, priceAttrs: ['amount' => 20000]);

    $results = engine()->recommend(PhoneRecommendationCriteria::fromArray(['max_budget' => 30000]));

    $result = collect($results)->firstWhere('phone_id', $phone->id);
    expect($result)->not->toBeNull()
        ->and($result['match_score'])->toBeGreaterThanOrEqual(0)->toBeLessThanOrEqual(100);
});

it('returns no results when nothing is eligible within budget', function () {
    makeRecommendablePhone(priceAttrs: ['amount' => 90000]);

    $results = engine()->recommend(PhoneRecommendationCriteria::fromArray(['max_budget' => 10000]));

    expect($results)->toBe([]);
});

it('returns the single eligible phone when only one qualifies', function () {
    $eligible = makeRecommendablePhone(priceAttrs: ['amount' => 20000]);
    makeRecommendablePhone(priceAttrs: ['amount' => 90000]);

    $results = engine()->recommend(PhoneRecommendationCriteria::fromArray(['max_budget' => 25000]));

    expect($results)->toHaveCount(1)->and($results[0]['phone_id'])->toBe($eligible->id);
});

it('produces identical ranking for identical input', function () {
    makeRecommendablePhone(priceAttrs: ['amount' => 22000]);
    makeRecommendablePhone(priceAttrs: ['amount' => 26000]);
    makeRecommendablePhone(priceAttrs: ['amount' => 30000]);

    $criteria = PhoneRecommendationCriteria::fromArray(['max_budget' => 40000, 'importance' => ['gaming' => 4, 'camera' => 2]]);

    $first = collect(engine()->recommend($criteria))->pluck('phone_id')->all();
    $second = collect(engine()->recommend($criteria))->pluck('phone_id')->all();

    expect($first)->toBe($second);
});

it('picks the cheapest eligible variant when a phone has multiple variants and prices', function () {
    $phone = makeRecommendablePhone(priceAttrs: ['amount' => 30000, 'price_type' => 'official_bd']);
    $cheaperVariant = PhoneVariant::factory()->create(['phone_id' => $phone->id, 'ram_gb' => 4, 'storage_gb' => 64]);
    $cheaperPrice = PhonePrice::factory()->create(['phone_variant_id' => $cheaperVariant->id, 'amount' => 18000, 'price_type' => 'unofficial_bd']);
    app(PriceAggregator::class)->recalculate($cheaperVariant, $cheaperPrice->price_type);

    $results = engine()->recommend(PhoneRecommendationCriteria::fromArray(['max_budget' => 40000]));

    $result = collect($results)->firstWhere('phone_id', $phone->id);
    expect((float) $result['price'])->toBe(18000.0);
});

it('generates non-empty, relevant explanations for the top recommendation', function () {
    makeRecommendablePhone(
        specAttrs: ['processor' => 'Qualcomm Snapdragon 8 Gen 3', 'display_refresh_rate' => 144, 'charging_speed_w' => 100],
        priceAttrs: ['amount' => 30000],
    );

    $results = engine()->recommend(PhoneRecommendationCriteria::fromArray([
        'max_budget' => 40000, 'importance' => ['gaming' => 5],
    ]));

    expect($results[0]['reasons'])->not->toBeEmpty()
        ->and($results[0]['tradeoffs'])->toBeArray();
});

it('handles a very low budget by returning no results rather than erroring', function () {
    makeRecommendablePhone(priceAttrs: ['amount' => 15000]);

    $results = engine()->recommend(PhoneRecommendationCriteria::fromArray(['max_budget' => 1000]));

    expect($results)->toBe([]);
});

it('handles a very high budget by including every eligible phone', function () {
    makeRecommendablePhone(priceAttrs: ['amount' => 15000]);
    makeRecommendablePhone(priceAttrs: ['amount' => 150000]);

    $results = engine()->recommend(PhoneRecommendationCriteria::fromArray(['max_budget' => 5_000_000]));

    expect($results)->toHaveCount(2);
});

it('does not crash on a data-tied pool and stays within score bounds', function () {
    $specs = ['processor' => 'Qualcomm Snapdragon 7 Gen 3', 'battery_capacity_mah' => 5000];
    makeRecommendablePhone(specAttrs: $specs, priceAttrs: ['amount' => 25000]);
    makeRecommendablePhone(specAttrs: $specs, priceAttrs: ['amount' => 25000]);
    makeRecommendablePhone(specAttrs: $specs, priceAttrs: ['amount' => 25000]);

    $results = engine()->recommend(PhoneRecommendationCriteria::fromArray(['max_budget' => 40000]));

    expect($results)->toHaveCount(3);
    foreach ($results as $result) {
        expect($result['match_score'])->toBeGreaterThanOrEqual(0)->toBeLessThanOrEqual(100);
    }
});

// -----------------------------------------------------------------------
// Regression coverage for issues found during real-data verification
// against the seeded Bangladesh catalogue (see the recommendation-engine
// verification pass).
// -----------------------------------------------------------------------

it('gives phones with different battery capacities different battery scores instead of flattening them into one tier', function () {
    $engine = engine();

    $modest = makeRecommendablePhone(specAttrs: ['battery_capacity_mah' => 5000, 'charging_speed_w' => null], priceAttrs: ['amount' => 25000]);
    $bigger = makeRecommendablePhone(specAttrs: ['battery_capacity_mah' => 5800, 'charging_speed_w' => null], priceAttrs: ['amount' => 25000]);
    $biggest = makeRecommendablePhone(specAttrs: ['battery_capacity_mah' => 6500, 'charging_speed_w' => null], priceAttrs: ['amount' => 25000]);

    $results = collect($engine->recommend(PhoneRecommendationCriteria::fromArray(['max_budget' => 30000])))
        ->keyBy('phone_id');

    $modestScore = $results[$modest->id]['score_breakdown']['battery'];
    $biggerScore = $results[$bigger->id]['score_breakdown']['battery'];
    $biggestScore = $results[$biggest->id]['score_breakdown']['battery'];

    expect($biggerScore)->toBeGreaterThan($modestScore)
        ->and($biggestScore)->toBeGreaterThan($biggerScore);
});

it('gives a flagship camera system a meaningfully higher score than a plain high-megapixel midrange camera instead of clamping both to the same ceiling', function () {
    // Regression: the 108MP tier used to be the top of the scale, so any
    // 200MP+ flagship sensor matched the same tier as an ordinary midrange
    // phone and, once OIS/ultrawide/macro/front bonuses stacked on top,
    // both clamped to 100 - discovered when expanding the seeded catalogue
    // produced 26 real phones spanning ~23k-190k that all scored 98-100 on
    // camera with no separation between a genuine periscope-telephoto
    // flagship and a plain 108MP midrange sensor.
    $engine = engine();

    $midrangeCamera = makeRecommendablePhone(specAttrs: [
        'main_camera' => '108 MP, f/1.8, OIS', 'camera_has_ois' => true,
        'ultrawide_camera' => '8 MP', 'macro_camera' => '2 MP', 'telephoto_camera' => null,
        'front_camera' => '16 MP',
    ], priceAttrs: ['amount' => 25000]);

    $flagshipCamera = makeRecommendablePhone(specAttrs: [
        'main_camera' => '200 MP, f/1.7, OIS', 'camera_has_ois' => true,
        'ultrawide_camera' => '12 MP', 'telephoto_camera' => '50 MP, f/3.4, 5x optical',
        'front_camera' => '16 MP',
    ], priceAttrs: ['amount' => 25000]);

    $results = collect($engine->recommend(PhoneRecommendationCriteria::fromArray(['max_budget' => 30000])))
        ->keyBy('phone_id');

    $midrangeScore = $results[$midrangeCamera->id]['score_breakdown']['camera'];
    $flagshipScore = $results[$flagshipCamera->id]['score_breakdown']['camera'];

    expect($flagshipScore)->toBeGreaterThan($midrangeScore)
        ->and($midrangeScore)->toBeLessThan(100);
});

it('does not let an extremely cheap but clearly weaker phone win value-for-money over a moderately-priced, meaningfully better one', function () {
    // Isolate the comparison properly: every dimension set explicitly so
    // neither phone accidentally inherits a "decent" factory default it
    // wasn't supposed to have - this mirrors the real entry-level phone
    // (Infinix Smart 8: 3GB RAM, 60Hz, Unisoc, no OIS, weak everywhere)
    // that originally exposed this bug by winning "value" purely by being
    // absurdly cheap despite being weak on every axis, not just camera.
    $weakAndCheap = makeRecommendablePhone(
        specAttrs: [
            'processor' => 'Unisoc SC9863A1', 'battery_capacity_mah' => 5000, 'charging_speed_w' => 10,
            'display_refresh_rate' => 60, 'display_panel_type' => 'IPS LCD',
            'main_camera' => '8 MP, f/2.0', 'camera_has_ois' => false, 'front_camera' => null,
            'build_materials' => 'Plastic frame and back', 'os_update_years' => 0, 'security_update_years' => 1,
        ],
        priceAttrs: ['amount' => 9000],
    );
    $solidMidrange = makeRecommendablePhone(
        specAttrs: [
            'processor' => 'Qualcomm Snapdragon 7 Gen 3', 'battery_capacity_mah' => 5000, 'charging_speed_w' => 33,
            'display_refresh_rate' => 120, 'display_panel_type' => 'AMOLED',
            'main_camera' => '50 MP, f/1.8, OIS', 'camera_has_ois' => true, 'front_camera' => '16 MP, f/2.4',
            'build_materials' => 'Glass front, plastic frame and back', 'os_update_years' => 2, 'security_update_years' => 3,
        ],
        priceAttrs: ['amount' => 21000],
    );

    $results = collect(engine()->recommend(PhoneRecommendationCriteria::fromArray(['max_budget' => 25000])))
        ->keyBy('phone_id');

    expect($results[$solidMidrange->id]['score_breakdown']['value'])
        ->toBeGreaterThan($results[$weakAndCheap->id]['score_breakdown']['value']);
});

it('lets the best phone on a single very-important dimension win even when it is not the most well-rounded phone overall', function () {
    $bestSoftwareButOtherwiseModest = makeRecommendablePhone(
        ['release_date' => now()->subYears(2)],
        [
            'processor' => 'Qualcomm Snapdragon 6 Gen 1', 'display_refresh_rate' => 90, 'main_camera' => '50 MP, f/1.8',
            'os_update_years' => 4, 'security_update_years' => 5,
        ],
        priceAttrs: ['amount' => 21000],
    );
    $strongerOverallButWeakerSoftware = makeRecommendablePhone(
        ['release_date' => now()->subYears(2)],
        [
            'processor' => 'Qualcomm Snapdragon 7+ Gen 3', 'display_refresh_rate' => 120, 'main_camera' => '108 MP, f/1.7, OIS',
            'camera_has_ois' => true, 'os_update_years' => 2, 'security_update_years' => 3,
        ],
        priceAttrs: ['amount' => 35000],
    );

    $results = engine()->recommend(PhoneRecommendationCriteria::fromArray([
        'max_budget' => 40000, 'importance' => ['software' => 5],
    ]));

    expect($results[0]['phone_id'])->toBe($bestSoftwareButOtherwiseModest->id);
});

it('does not duplicate the brand name when a phone model already includes it', function () {
    $brand = Brand::factory()->create(['name' => 'POCO']);
    $phone = makeRecommendablePhone(['brand_id' => $brand->id, 'name' => 'POCO X6 Pro'], priceAttrs: ['amount' => 25000]);

    $results = engine()->recommend(PhoneRecommendationCriteria::fromArray(['max_budget' => 30000]));

    $result = collect($results)->firstWhere('phone_id', $phone->id);
    expect($result['phone_name'])->toBe('POCO X6 Pro');
});

it('keeps the brand prefix when the phone model does not already include it', function () {
    $brand = Brand::factory()->create(['name' => 'Samsung']);
    $phone = makeRecommendablePhone(['brand_id' => $brand->id, 'name' => 'Galaxy S24 Ultra'], priceAttrs: ['amount' => 25000]);

    $results = engine()->recommend(PhoneRecommendationCriteria::fromArray(['max_budget' => 30000]));

    $result = collect($results)->firstWhere('phone_id', $phone->id);
    expect($result['phone_name'])->toBe('Samsung Galaxy S24 Ultra');
});

// -----------------------------------------------------------------------
// Global/Chinese version simplification (see App\Enums\PhoneRegionEnum,
// App\Services\Presentation\PhoneVariantRegionSelector).
// -----------------------------------------------------------------------

it('prefers a Global variant over a cheaper Chinese variant of the same phone', function () {
    $phone = makeRecommendablePhone(priceAttrs: ['amount' => 30000, 'price_type' => 'unofficial_bd']);
    $chineseVariant = PhoneVariant::factory()->create(['phone_id' => $phone->id, 'region' => 'China']);
    PhonePrice::factory()->for($chineseVariant, 'variant')->create(['amount' => 18000, 'price_type' => 'unofficial_bd']);
    app(PriceAggregator::class)->recalculate($chineseVariant, PriceTypeEnum::UNOFFICIAL_BD);

    $results = engine()->recommend(PhoneRecommendationCriteria::fromArray(['max_budget' => 40000]));

    $result = collect($results)->firstWhere('phone_id', $phone->id);
    expect((float) $result['price'])->toBe(30000.0)
        ->and($result['variant_region'])->toBe('Global')
        ->and($result['is_chinese_variant'])->toBeFalse();
});

it('falls back to a Chinese variant only when no Global variant satisfies the budget', function () {
    $phone = makeRecommendablePhone(priceAttrs: ['amount' => 90000, 'price_type' => 'unofficial_bd']);
    $chineseVariant = PhoneVariant::factory()->create(['phone_id' => $phone->id, 'region' => 'China']);
    PhonePrice::factory()->for($chineseVariant, 'variant')->create(['amount' => 18000, 'price_type' => 'unofficial_bd']);
    app(PriceAggregator::class)->recalculate($chineseVariant, PriceTypeEnum::UNOFFICIAL_BD);

    $results = engine()->recommend(PhoneRecommendationCriteria::fromArray(['max_budget' => 25000]));

    $result = collect($results)->firstWhere('phone_id', $phone->id);
    expect($result)->not->toBeNull()
        ->and((float) $result['price'])->toBe(18000.0)
        ->and($result['variant_region'])->toBe('Chinese')
        ->and($result['is_chinese_variant'])->toBeTrue();
});

it('flags a Chinese-only recommendation in its explanation tradeoffs', function () {
    $phone = Phone::factory()->create(['status' => PhoneStatusEnum::AVAILABLE]);
    PhoneImage::factory()->for($phone)->create();
    $chineseVariant = PhoneVariant::factory()->create(['phone_id' => $phone->id, 'region' => 'China']);
    PhonePrice::factory()->for($chineseVariant, 'variant')->create(['amount' => 18000, 'price_type' => 'unofficial_bd']);
    app(PriceAggregator::class)->recalculate($chineseVariant, PriceTypeEnum::UNOFFICIAL_BD);

    $results = engine()->recommend(PhoneRecommendationCriteria::fromArray(['max_budget' => 25000]));

    $result = collect($results)->firstWhere('phone_id', $phone->id);
    expect($result['tradeoffs'])->toContain(
        'This is the Chinese-market version, not the Global one - check firmware, language support, and warranty coverage before buying.'
    );
});

it('never flags a Global recommendation as Chinese in its explanation', function () {
    makeRecommendablePhone(priceAttrs: ['amount' => 25000]);

    $results = engine()->recommend(PhoneRecommendationCriteria::fromArray(['max_budget' => 30000]));

    expect($results[0]['is_chinese_variant'])->toBeFalse()
        ->and($results[0]['tradeoffs'])->not->toContain(
            'This is the Chinese-market version, not the Global one - check firmware, language support, and warranty coverage before buying.'
        );
});

it('never shows both the "best value" and the redundant "older model, good value" reason together', function () {
    $specs = ['processor' => 'Qualcomm Snapdragon 7 Gen 3', 'battery_capacity_mah' => 5000, 'display_refresh_rate' => 120, 'main_camera' => '50 MP, f/1.8, OIS'];

    makeRecommendablePhone(['release_date' => now()->subYears(2)], $specs, priceAttrs: ['amount' => 15000]);
    makeRecommendablePhone(['release_date' => now()->subYears(2)], $specs, priceAttrs: ['amount' => 40000]);

    $results = engine()->recommend(PhoneRecommendationCriteria::fromArray(['max_budget' => 50000]));

    $bestValueResult = collect($results)->sortByDesc(fn ($r) => $r['score_breakdown']['value'])->first();

    $hasBestValueReason = in_array('The best value for money among your matches.', $bestValueResult['reasons'], true);
    $hasOlderModelReason = in_array('Older model, but currently offers excellent value for the price.', $bestValueResult['reasons'], true);

    expect($hasBestValueReason)->toBeTrue()
        ->and($hasOlderModelReason)->toBeFalse();
});
