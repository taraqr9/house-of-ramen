<?php

use App\Models\Brand;
use App\Models\Phone;
use App\Models\PhoneMarketPrice;
use App\Models\PhonePrice;
use App\Models\PhonePriceHistory;
use App\Models\PhoneVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('attaches an unofficial price to a phone with exactly one variant, and recalculates the market price', function () {
    $phone = Phone::factory()->for(Brand::factory()->create(['name' => 'Apple']), 'brand')->create(['name' => 'iPhone 14 Pro']);
    $variant = PhoneVariant::factory()->create(['phone_id' => $phone->id]);

    $this->artisan('phones:attach-price', [
        'phone' => $phone->slug,
        'price_type' => 'unofficial_bd',
        'amount' => 86990,
        '--store' => 'dazzle',
        '--source-url' => 'https://dazzle.com.bd/product/iphone-14-pro',
        '--confidence' => 70,
    ])->assertExitCode(0);

    $price = PhonePrice::where('phone_variant_id', $variant->id)->where('price_type', 'unofficial_bd')->firstOrFail();

    expect((float) $price->amount)->toBe(86990.0)
        ->and($price->source_url)->toBe('https://dazzle.com.bd/product/iphone-14-pro')
        ->and($price->confidence)->toBe(70)
        ->and($price->is_active)->toBeTrue();

    $marketPrice = PhoneMarketPrice::where('phone_variant_id', $variant->id)->where('price_type', 'unofficial_bd')->first();
    expect($marketPrice)->not->toBeNull();
    expect((float) $marketPrice->price)->toBe(86990.0);
});

it('records price history when the amount changes', function () {
    $phone = Phone::factory()->for(Brand::factory()->create(), 'brand')->create();
    PhoneVariant::factory()->create(['phone_id' => $phone->id]);

    $this->artisan('phones:attach-price', [
        'phone' => $phone->slug, 'price_type' => 'unofficial_bd', 'amount' => 100000, '--store' => 'dazzle',
    ]);
    $this->artisan('phones:attach-price', [
        'phone' => $phone->slug, 'price_type' => 'unofficial_bd', 'amount' => 95000, '--store' => 'dazzle',
    ]);

    expect(PhonePriceHistory::where('phone_variant_id', $phone->variants->first()->id)->count())->toBe(2);
});

it('requires --variant when the phone has more than one variant', function () {
    $phone = Phone::factory()->for(Brand::factory()->create(), 'brand')->create();
    PhoneVariant::factory()->create(['phone_id' => $phone->id, 'ram_gb' => 8, 'storage_gb' => 128]);
    PhoneVariant::factory()->create(['phone_id' => $phone->id, 'ram_gb' => 12, 'storage_gb' => 256]);

    $this->artisan('phones:attach-price', [
        'phone' => $phone->slug, 'price_type' => 'unofficial_bd', 'amount' => 100000,
    ])->assertExitCode(1);
});

it('rejects an invalid price_type', function () {
    $phone = Phone::factory()->for(Brand::factory()->create(), 'brand')->create();
    PhoneVariant::factory()->create(['phone_id' => $phone->id]);

    $this->artisan('phones:attach-price', [
        'phone' => $phone->slug, 'price_type' => 'bogus', 'amount' => 100000,
    ])->assertExitCode(1);
});

it('keeps official and unofficial prices separate on the same variant', function () {
    $phone = Phone::factory()->for(Brand::factory()->create(), 'brand')->create();
    PhoneVariant::factory()->create(['phone_id' => $phone->id]);

    $this->artisan('phones:attach-price', [
        'phone' => $phone->slug, 'price_type' => 'official_bd', 'amount' => 150000, '--store' => 'star-tech',
    ]);
    $this->artisan('phones:attach-price', [
        'phone' => $phone->slug, 'price_type' => 'unofficial_bd', 'amount' => 110000, '--store' => 'dazzle',
    ]);

    $variant = $phone->fresh()->variants->first();
    expect(PhonePrice::where('phone_variant_id', $variant->id)->where('price_type', 'official_bd')->exists())->toBeTrue();
    expect(PhonePrice::where('phone_variant_id', $variant->id)->where('price_type', 'unofficial_bd')->exists())->toBeTrue();
});
