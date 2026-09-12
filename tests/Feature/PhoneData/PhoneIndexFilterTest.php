<?php

use App\Enums\ImageStatusEnum;
use App\Models\Phone;
use App\Models\PhoneImage;
use App\Models\PhonePrice;
use App\Models\PhoneVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('filters the admin phones list to phones with no verified primary image', function () {
    $user = phoneDataUser(['phone-view']);

    $withImage = Phone::factory()->create(['name' => 'With Image']);
    PhoneImage::factory()->for($withImage, 'phone')->create(['is_primary' => true, 'status' => ImageStatusEnum::VERIFIED]);

    $withUnverifiedImage = Phone::factory()->create(['name' => 'Unverified Image Only']);
    PhoneImage::factory()->for($withUnverifiedImage, 'phone')->create(['is_primary' => true, 'status' => ImageStatusEnum::NEEDS_REVIEW]);

    $withoutImage = Phone::factory()->create(['name' => 'No Image']);

    $response = $this->actingAs($user)->get(route('phones.index', ['no_image' => '1']));

    $response->assertOk();
    $names = collect($response->viewData('phones')->items())->pluck('name');

    expect($names)->toContain('No Image')
        ->toContain('Unverified Image Only')
        ->not->toContain('With Image');
});

it('filters the admin phones list to phones with no price on any variant', function () {
    $user = phoneDataUser(['phone-view']);

    $withPrice = Phone::factory()->create(['name' => 'Priced Phone']);
    $variant = PhoneVariant::factory()->create(['phone_id' => $withPrice->id]);
    PhonePrice::factory()->create(['phone_variant_id' => $variant->id, 'amount' => 25000]);

    $withoutPrice = Phone::factory()->create(['name' => 'Unpriced Phone']);
    PhoneVariant::factory()->create(['phone_id' => $withoutPrice->id]);

    $withoutVariantAtAll = Phone::factory()->create(['name' => 'No Variant At All']);

    $response = $this->actingAs($user)->get(route('phones.index', ['no_price' => '1']));

    $response->assertOk();
    $names = collect($response->viewData('phones')->items())->pluck('name');

    expect($names)->toContain('Unpriced Phone')
        ->toContain('No Variant At All')
        ->not->toContain('Priced Phone');
});

it('combines the no-image and no-price filters as a strict AND, not either/or', function () {
    $user = phoneDataUser(['phone-view']);

    $missingBoth = Phone::factory()->create(['name' => 'Missing Both']);

    $missingImageOnly = Phone::factory()->create(['name' => 'Missing Image Only']);
    $variant = PhoneVariant::factory()->create(['phone_id' => $missingImageOnly->id]);
    PhonePrice::factory()->create(['phone_variant_id' => $variant->id, 'amount' => 30000]);

    $missingPriceOnly = Phone::factory()->create(['name' => 'Missing Price Only']);
    PhoneImage::factory()->for($missingPriceOnly, 'phone')->create(['is_primary' => true, 'status' => ImageStatusEnum::VERIFIED]);
    PhoneVariant::factory()->create(['phone_id' => $missingPriceOnly->id]);

    $response = $this->actingAs($user)->get(route('phones.index', ['no_image' => '1', 'no_price' => '1']));

    $response->assertOk();
    $names = collect($response->viewData('phones')->items())->pluck('name');

    expect($names)->toContain('Missing Both')
        ->not->toContain('Missing Image Only')
        ->not->toContain('Missing Price Only');
});
