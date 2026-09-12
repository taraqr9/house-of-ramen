<?php

use App\Enums\PriceTypeEnum;
use App\Models\PhoneMarketPrice;
use App\Models\PhonePrice;
use App\Models\PhoneVariant;
use App\Services\PhoneImport\PriceAggregator;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('excludes an active but stale observation from the current market price', function () {
    $variant = PhoneVariant::factory()->create();

    PhonePrice::factory()->for($variant, 'variant')->create([
        'price_type' => 'official_bd', 'amount' => 200000, 'is_active' => true,
        'last_verified_at' => now()->subDays(config('phone_pricing.stale_after_days') + 5),
    ]);
    PhonePrice::factory()->for($variant, 'variant')->create([
        'price_type' => 'official_bd', 'amount' => 100000, 'is_active' => true,
        'last_verified_at' => now(),
    ]);

    $result = app(PriceAggregator::class)->recalculate($variant, PriceTypeEnum::OFFICIAL_BD);

    expect((float) $result->price)->toBe(100000.0)
        ->and($result->observation_count)->toBe(1);
});

it('removes the market price entirely once every observation for a type has gone stale', function () {
    $variant = PhoneVariant::factory()->create();
    PhonePrice::factory()->for($variant, 'variant')->create([
        'price_type' => 'official_bd', 'amount' => 200000, 'is_active' => true,
        'last_verified_at' => now(),
    ]);

    app(PriceAggregator::class)->recalculate($variant, PriceTypeEnum::OFFICIAL_BD);
    expect(PhoneMarketPrice::query()->where('phone_variant_id', $variant->id)->exists())->toBeTrue();

    PhonePrice::query()->where('phone_variant_id', $variant->id)
        ->update(['last_verified_at' => now()->subDays(config('phone_pricing.stale_after_days') + 1)]);

    app(PriceAggregator::class)->recalculate($variant, PriceTypeEnum::OFFICIAL_BD);

    expect(PhoneMarketPrice::query()->where('phone_variant_id', $variant->id)->exists())->toBeFalse();
});

it('a fresh re-verification makes a previously-stale row current again on the next recalculation', function () {
    $variant = PhoneVariant::factory()->create();
    $price = PhonePrice::factory()->for($variant, 'variant')->create([
        'price_type' => 'official_bd', 'amount' => 150000, 'is_active' => true,
        'last_verified_at' => now()->subDays(config('phone_pricing.stale_after_days') + 1),
    ]);

    app(PriceAggregator::class)->recalculate($variant, PriceTypeEnum::OFFICIAL_BD);
    expect(PhoneMarketPrice::query()->where('phone_variant_id', $variant->id)->exists())->toBeFalse();

    $price->update(['last_verified_at' => now()]);
    $result = app(PriceAggregator::class)->recalculate($variant, PriceTypeEnum::OFFICIAL_BD);

    expect((float) $result->price)->toBe(150000.0);
});

it('phones:expire-stale-prices deactivates a long-unverified observation and recalculates its market price', function () {
    $variant = PhoneVariant::factory()->create();
    $fresh = PhonePrice::factory()->for($variant, 'variant')->create([
        'price_type' => 'official_bd', 'amount' => 100000, 'is_active' => true, 'last_verified_at' => now(),
    ]);
    $abandoned = PhonePrice::factory()->for($variant, 'variant')->create([
        'price_type' => 'official_bd', 'amount' => 999999, 'is_active' => true,
        'last_verified_at' => now()->subDays(config('phone_pricing.expire_after_days') + 1),
    ]);

    $this->artisan('phones:expire-stale-prices')->assertSuccessful();

    expect($abandoned->fresh()->is_active)->toBeFalse()
        ->and($fresh->fresh()->is_active)->toBeTrue();

    $market = PhoneMarketPrice::query()->where('phone_variant_id', $variant->id)->where('price_type', 'official_bd')->first();
    expect((float) $market->price)->toBe(100000.0)
        ->and($market->observation_count)->toBe(1);
});

it('phones:expire-stale-prices leaves merely-stale-but-not-yet-abandoned observations active', function () {
    $variant = PhoneVariant::factory()->create();
    $price = PhonePrice::factory()->for($variant, 'variant')->create([
        'price_type' => 'official_bd', 'amount' => 100000, 'is_active' => true,
        'last_verified_at' => now()->subDays(config('phone_pricing.stale_after_days') + 2),
    ]);

    $this->artisan('phones:expire-stale-prices')->assertSuccessful();

    expect($price->fresh()->is_active)->toBeTrue();
});

it('phones:expire-stale-prices never deletes historical price rows, only deactivates them', function () {
    $variant = PhoneVariant::factory()->create();
    $price = PhonePrice::factory()->for($variant, 'variant')->create([
        'price_type' => 'official_bd', 'amount' => 100000, 'is_active' => true,
        'last_verified_at' => now()->subDays(config('phone_pricing.expire_after_days') + 1),
    ]);

    $this->artisan('phones:expire-stale-prices')->assertSuccessful();

    expect(PhonePrice::query()->find($price->id))->not->toBeNull();
});
