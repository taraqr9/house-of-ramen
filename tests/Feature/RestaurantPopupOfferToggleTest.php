<?php

use App\Models\RestaurantPopupOffer;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('flips is_active for an admin with edit permission', function () {
    $restaurant = makeRestaurant();
    $offer = RestaurantPopupOffer::factory()->create(['restaurant_id' => $restaurant->id, 'is_active' => true]);
    $user = adminUser(['restaurant_popup_offer-view', 'restaurant_popup_offer-edit']);

    $response = $this->actingAs($user)->patchJson(route('restaurant-popup-offers.toggle-active', $offer->id));

    $response->assertOk()->assertJson(['is_active' => false]);
    expect($offer->fresh()->is_active)->toBeFalse();

    $response = $this->actingAs($user)->patchJson(route('restaurant-popup-offers.toggle-active', $offer->id));

    $response->assertOk()->assertJson(['is_active' => true]);
    expect($offer->fresh()->is_active)->toBeTrue();
});

it('forbids toggling without edit permission', function () {
    $restaurant = makeRestaurant();
    $offer = RestaurantPopupOffer::factory()->create(['restaurant_id' => $restaurant->id]);
    $user = adminUser(['restaurant_popup_offer-view']);

    $this->actingAs($user)->patchJson(route('restaurant-popup-offers.toggle-active', $offer->id))->assertForbidden();
});
