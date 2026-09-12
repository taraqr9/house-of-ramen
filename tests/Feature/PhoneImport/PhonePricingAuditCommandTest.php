<?php

use App\Models\Phone;
use App\Models\PhoneMarketPrice;
use App\Models\PhoneVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

function freshMarketPrice(PhoneVariant $variant, string $priceType): PhoneMarketPrice
{
    return PhoneMarketPrice::factory()->for($variant, 'variant')->create([
        'price_type' => $priceType,
        'calculated_at' => now(),
    ]);
}

it('correctly set-unions phones with only one of official/unofficial fresh - regression for Collection::union() misuse', function () {
    // Collection::union() merges by array KEY, not by value - with these
    // three phones each contributing an id to only one of the two
    // "fresh" id lists, a key-based "union" silently drops/miscounts
    // some of them instead of performing a real set union. This exact
    // shape (2 single-type phones + 1 both-type phone) previously
    // produced a wrong (inflated) "neither" count in production: 169
    // instead of the correct 66 on the real catalogue.
    $officialOnly = Phone::factory()->create(['is_active' => true]);
    freshMarketPrice(PhoneVariant::factory()->for($officialOnly)->create(['is_active' => true]), 'official_bd');

    $unofficialOnly = Phone::factory()->create(['is_active' => true]);
    freshMarketPrice(PhoneVariant::factory()->for($unofficialOnly)->create(['is_active' => true]), 'unofficial_bd');

    $both = Phone::factory()->create(['is_active' => true]);
    $bothVariant = PhoneVariant::factory()->for($both)->create(['is_active' => true]);
    freshMarketPrice($bothVariant, 'official_bd');
    freshMarketPrice($bothVariant, 'unofficial_bd');

    $neither = Phone::factory()->create(['is_active' => true]);

    Artisan::call('phones:pricing-audit');
    $output = Artisan::output();

    expect($output)->toMatch('/Phones with no fresh price at all\s*\|\s*1\s*\|/')
        ->and($output)->toMatch('/Phones with both\s*\|\s*1\s*\|/')
        ->and($output)->toMatch('/Phones with fresh official price\s*\|\s*2\s*\|/')
        ->and($output)->toMatch('/Phones with fresh unofficial price\s*\|\s*2\s*\|/');
});
