<?php

use App\Models\RestaurantMenuItem;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('flips is_featured for an admin with edit permission', function () {
    $restaurant = makeRestaurant();
    $item = RestaurantMenuItem::factory()->create(['restaurant_id' => $restaurant->id, 'is_featured' => false]);
    $user = adminUser(['restaurant_menu_item-view', 'restaurant_menu_item-edit']);

    $response = $this->actingAs($user)->patchJson(route('restaurant-menu-items.toggle-featured', $item->id));

    $response->assertOk()->assertJson(['is_featured' => true]);
    expect($item->fresh()->is_featured)->toBeTrue();

    $response = $this->actingAs($user)->patchJson(route('restaurant-menu-items.toggle-featured', $item->id));

    $response->assertOk()->assertJson(['is_featured' => false]);
    expect($item->fresh()->is_featured)->toBeFalse();
});

it('flips is_new for an admin with edit permission', function () {
    $restaurant = makeRestaurant();
    $item = RestaurantMenuItem::factory()->create(['restaurant_id' => $restaurant->id, 'is_new' => false]);
    $user = adminUser(['restaurant_menu_item-view', 'restaurant_menu_item-edit']);

    $response = $this->actingAs($user)->patchJson(route('restaurant-menu-items.toggle-new', $item->id));

    $response->assertOk()->assertJson(['is_new' => true]);
    expect($item->fresh()->is_new)->toBeTrue();
});

it('forbids toggling without edit permission', function () {
    $restaurant = makeRestaurant();
    $item = RestaurantMenuItem::factory()->create(['restaurant_id' => $restaurant->id]);
    $user = adminUser(['restaurant_menu_item-view']);

    $this->actingAs($user)->patchJson(route('restaurant-menu-items.toggle-featured', $item->id))->assertForbidden();
    $this->actingAs($user)->patchJson(route('restaurant-menu-items.toggle-new', $item->id))->assertForbidden();
});
