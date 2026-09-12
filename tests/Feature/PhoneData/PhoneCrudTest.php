<?php

use App\Models\Brand;
use App\Models\Phone;
use App\Models\PhoneStore;
use App\Models\PhoneVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('lets a permitted user create a phone with its spec sheet in one submission', function () {
    $user = phoneDataUser(['phone-view', 'phone-create', 'phone-edit']);
    $brand = Brand::factory()->create(['name' => 'Samsung', 'slug' => 'samsung']);

    $response = $this->actingAs($user)->post(route('phones.store'), [
        'brand_id' => $brand->id,
        'name' => 'Galaxy S24 Ultra',
        'status' => 'available',
        'is_active' => '1',
        'battery_capacity_mah' => 5000,
        'display_size' => 6.8,
        'processor' => 'Snapdragon 8 Gen 3',
    ]);

    $phone = Phone::where('slug', 'samsung-galaxy-s24-ultra')->firstOrFail();
    $response->assertRedirect(route('phones.edit', $phone->id));

    expect($phone->spec->battery_capacity_mah)->toBe(5000)
        ->and((float) $phone->spec->display_size)->toBe(6.8);
});

it('rejects a release_date before announced_date', function () {
    // Regression test: caught in production data twice (Galaxy A17 5G,
    // Moto Edge 50 Pro) before this validation rule existed.
    $user = phoneDataUser(['phone-view', 'phone-create', 'phone-edit']);
    $brand = Brand::factory()->create(['name' => 'Samsung', 'slug' => 'samsung']);

    $response = $this->actingAs($user)->post(route('phones.store'), [
        'brand_id' => $brand->id,
        'name' => 'Galaxy A17 5G',
        'status' => 'available',
        'is_active' => '1',
        'announced_date' => '2025-08-19',
        'release_date' => '2025-08-18',
    ]);

    $response->assertSessionHasErrors('release_date');
    expect(Phone::where('slug', 'samsung-galaxy-a17-5g')->exists())->toBeFalse();
});

it('accepts a release_date on or after announced_date', function () {
    $user = phoneDataUser(['phone-view', 'phone-create', 'phone-edit']);
    $brand = Brand::factory()->create(['name' => 'Samsung', 'slug' => 'samsung']);

    $response = $this->actingAs($user)->post(route('phones.store'), [
        'brand_id' => $brand->id,
        'name' => 'Galaxy A17 5G',
        'status' => 'available',
        'is_active' => '1',
        'announced_date' => '2025-08-06',
        'release_date' => '2025-08-14',
    ]);

    $response->assertSessionDoesntHaveErrors('release_date');
    expect(Phone::where('slug', 'samsung-galaxy-a17-5g')->exists())->toBeTrue();
});

it('does not double the brand name in the slug when the admin types a name that already includes it', function () {
    $user = phoneDataUser(['phone-view', 'phone-create', 'phone-edit']);
    $brand = Brand::factory()->create(['name' => 'Redmi', 'slug' => 'redmi']);

    $this->actingAs($user)->post(route('phones.store'), [
        'brand_id' => $brand->id,
        'name' => 'Redmi Note 99 Pro',
        'status' => 'available',
        'is_active' => '1',
    ]);

    expect(Phone::where('slug', 'redmi-note-99-pro')->exists())->toBeTrue()
        ->and(Phone::where('slug', 'redmi-redmi-note-99-pro')->exists())->toBeFalse();
});

it('marks a manually edited phone as fully confident', function () {
    $user = phoneDataUser(['phone-edit']);
    $phone = Phone::factory()->create(['overall_confidence' => 40]);

    $this->actingAs($user)->put(route('phones.update', $phone->id), [
        'brand_id' => $phone->brand_id,
        'name' => $phone->name,
        'status' => 'available',
        'is_active' => '1',
    ])->assertRedirect(route('phones.edit', $phone->id));

    expect($phone->fresh()->overall_confidence)->toBe(100);
});

it('lets a permitted user add a variant with Bangladesh pricing and records price history on change', function () {
    $user = phoneDataUser(['phone_variant-create', 'phone_variant-edit']);
    $phone = Phone::factory()->create();
    $store = PhoneStore::factory()->create();

    $this->actingAs($user)->post(route('phones.variants.store', $phone->id), [
        'ram_gb' => 8, 'storage_gb' => 128, 'status' => 'available', 'is_active' => '1',
        'store_id' => $store->id, 'official_bd_price' => 29999,
    ])->assertRedirect(route('phones.edit', $phone->id));

    $variant = PhoneVariant::where('phone_id', $phone->id)->firstOrFail();
    expect($variant->prices()->where('price_type', 'official_bd')->first()->amount)->toEqual('29999.00');
    expect($variant->priceHistory()->count())->toBe(1);

    $this->actingAs($user)->put(route('phones.variants.update', [$phone->id, $variant->id]), [
        'ram_gb' => 8, 'storage_gb' => 128, 'status' => 'available', 'is_active' => '1',
        'store_id' => $store->id, 'official_bd_price' => 27999,
    ])->assertRedirect(route('phones.edit', $phone->id));

    expect($variant->priceHistory()->count())->toBe(2)
        ->and($variant->prices()->where('price_type', 'official_bd')->first()->amount)->toEqual('27999.00');
});

it('lets a permitted user set an unofficial Bangladesh price independently of the official one', function () {
    $user = phoneDataUser(['phone_variant-create', 'phone_variant-edit']);
    $phone = Phone::factory()->create();
    $store = PhoneStore::factory()->create();

    $this->actingAs($user)->post(route('phones.variants.store', $phone->id), [
        'ram_gb' => 8, 'storage_gb' => 128, 'status' => 'available', 'is_active' => '1',
        'store_id' => $store->id, 'official_bd_price' => 55000, 'unofficial_bd_price' => 47000,
    ])->assertRedirect(route('phones.edit', $phone->id));

    $variant = PhoneVariant::where('phone_id', $phone->id)->firstOrFail();

    // Both price types coexist as distinct rows on the same variant -
    // never one overwriting the other.
    expect($variant->prices()->where('price_type', 'official_bd')->first()->amount)->toEqual('55000.00')
        ->and($variant->prices()->where('price_type', 'unofficial_bd')->first()->amount)->toEqual('47000.00')
        ->and($variant->prices()->count())->toBe(2);
});

it('keeps each RAM/storage variant\'s prices independent - editing one variant never touches another\'s price', function () {
    $user = phoneDataUser(['phone_variant-create', 'phone_variant-edit']);
    $phone = Phone::factory()->create();
    $store = PhoneStore::factory()->create();

    $this->actingAs($user)->post(route('phones.variants.store', $phone->id), [
        'ram_gb' => 8, 'storage_gb' => 128, 'status' => 'available', 'is_active' => '1',
        'store_id' => $store->id, 'official_bd_price' => 30000,
    ]);
    $this->actingAs($user)->post(route('phones.variants.store', $phone->id), [
        'ram_gb' => 12, 'storage_gb' => 256, 'status' => 'available', 'is_active' => '1',
        'store_id' => $store->id, 'official_bd_price' => 35000,
    ]);

    $small = PhoneVariant::where('phone_id', $phone->id)->where('storage_gb', 128)->firstOrFail();
    $large = PhoneVariant::where('phone_id', $phone->id)->where('storage_gb', 256)->firstOrFail();

    $this->actingAs($user)->put(route('phones.variants.update', [$phone->id, $large->id]), [
        'ram_gb' => 12, 'storage_gb' => 256, 'status' => 'available', 'is_active' => '1',
        'store_id' => $store->id, 'official_bd_price' => 36000,
    ]);

    expect($large->prices()->where('price_type', 'official_bd')->first()->amount)->toEqual('36000.00')
        ->and($small->prices()->where('price_type', 'official_bd')->first()->amount)->toEqual('30000.00');
});

it('saves the controlled Global/Chinese region value submitted from the variant form', function () {
    $user = phoneDataUser(['phone_variant-create']);
    $phone = Phone::factory()->create();

    $this->actingAs($user)->post(route('phones.variants.store', $phone->id), [
        'ram_gb' => 12, 'storage_gb' => 256, 'region' => 'Chinese', 'status' => 'available', 'is_active' => '1',
    ])->assertRedirect(route('phones.edit', $phone->id));

    expect(PhoneVariant::where('phone_id', $phone->id)->firstOrFail()->region)->toBe('Chinese');
});

it('preserves a legacy region value on update without rewriting it or creating a duplicate variant', function () {
    // The admin form's region field now renders as a Global/Chinese select,
    // but a variant seeded before that change may carry a legacy free-text
    // value (e.g. "Bangladesh") - re-saving it (as the form's preserved
    // "legacy value, kept as-is" option would resubmit verbatim) must
    // leave it untouched, never silently switched to Global/blank and
    // never colliding with the (phone_id, ram_gb, storage_gb, region)
    // unique index to create a second row.
    $user = phoneDataUser(['phone_variant-edit']);
    $phone = Phone::factory()->create();
    $variant = PhoneVariant::factory()->create(['phone_id' => $phone->id, 'region' => 'Bangladesh']);

    $this->actingAs($user)->put(route('phones.variants.update', [$phone->id, $variant->id]), [
        'ram_gb' => $variant->ram_gb, 'storage_gb' => $variant->storage_gb, 'region' => 'Bangladesh',
        'status' => 'available', 'is_active' => '1',
    ])->assertRedirect(route('phones.edit', $phone->id));

    expect(PhoneVariant::where('phone_id', $phone->id)->count())->toBe(1)
        ->and($variant->fresh()->region)->toBe('Bangladesh');
});

it('never deletes an existing price when a variant is updated without resubmitting a price', function () {
    // Regression guard: upsertPrice() must leave an existing PhonePrice row
    // untouched (not delete it) whenever the submitted amount is blank/null -
    // a blank field must never be interpreted as "clear this price".
    $user = phoneDataUser(['phone_variant-create', 'phone_variant-edit']);
    $phone = Phone::factory()->create();
    $store = PhoneStore::factory()->create();

    $this->actingAs($user)->post(route('phones.variants.store', $phone->id), [
        'ram_gb' => 8, 'storage_gb' => 128, 'status' => 'available', 'is_active' => '1',
        'store_id' => $store->id, 'official_bd_price' => 42000,
    ]);
    $variant = PhoneVariant::where('phone_id', $phone->id)->firstOrFail();

    $this->actingAs($user)->put(route('phones.variants.update', [$phone->id, $variant->id]), [
        'ram_gb' => 8, 'storage_gb' => 128, 'status' => 'available', 'is_active' => '1',
        'color' => 'Midnight Black', // unrelated field changes; no price field submitted
    ])->assertRedirect(route('phones.edit', $phone->id));

    expect($variant->fresh()->prices()->where('price_type', 'official_bd')->first()->amount)->toEqual('42000.00')
        ->and($variant->fresh()->color)->toBe('Midnight Black');
});
