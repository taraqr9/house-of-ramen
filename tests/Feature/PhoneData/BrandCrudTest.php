<?php

use App\Models\Brand;
use App\Models\Phone;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('lets a permitted user list, create, update, and delete a brand', function () {
    $user = phoneDataUser(['brand-view', 'brand-create', 'brand-edit', 'brand-delete']);

    $this->actingAs($user)->get(route('brands.index'))->assertOk();

    $this->actingAs($user)
        ->post(route('brands.store'), ['name' => 'Samsung', 'is_active' => '1'])
        ->assertRedirect(route('brands.index'));

    $brand = Brand::where('name', 'Samsung')->firstOrFail();
    expect($brand->slug)->toBe('samsung');

    $this->actingAs($user)
        ->put(route('brands.update', $brand->id), ['name' => 'Samsung Electronics', 'is_active' => '1'])
        ->assertRedirect(route('brands.index'));

    expect($brand->fresh()->name)->toBe('Samsung Electronics');

    $this->actingAs($user)
        ->delete(route('brands.destroy', $brand->id))
        ->assertRedirect(route('brands.index'));

    expect(Brand::find($brand->id))->toBeNull();
});

it('blocks a user without brand-view permission', function () {
    $user = phoneDataUser([]);

    $this->actingAs($user)->get(route('brands.index'))->assertForbidden();
});

it('refuses to delete a brand that still has phones', function () {
    $user = phoneDataUser(['brand-delete']);
    $brand = Brand::factory()->create();
    Phone::factory()->for($brand)->create();

    $this->actingAs($user)->delete(route('brands.destroy', $brand->id))->assertRedirect(route('brands.index'));

    expect(Brand::find($brand->id))->not->toBeNull();
});
