<?php

use App\Models\RestaurantGalleryImage;
use App\Models\RestaurantMenuItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('renders the public homepage with the restaurant, hero slides, and featured items', function () {
    $restaurant = makeRestaurant();

    RestaurantGalleryImage::factory()->count(2)->create([
        'restaurant_id' => $restaurant->id,
        'category' => 'interior',
        'is_active' => true,
    ]);

    RestaurantMenuItem::factory()->count(2)->create([
        'restaurant_id' => $restaurant->id,
        'is_featured' => true,
    ]);

    $response = $this->get('/');

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Public/Home')
        ->where('restaurant.name', $restaurant->name)
        ->has('heroSlides', 2)
        ->has('featuredItems', 2)
        ->has('seo')
    );
});

it('only shows available, featured items on the homepage', function () {
    $restaurant = makeRestaurant();

    RestaurantMenuItem::factory()->create(['restaurant_id' => $restaurant->id, 'is_featured' => true, 'is_available' => true, 'name' => 'Visible Dish']);
    RestaurantMenuItem::factory()->create(['restaurant_id' => $restaurant->id, 'is_featured' => true, 'is_available' => false, 'name' => 'Unavailable Dish']);
    RestaurantMenuItem::factory()->create(['restaurant_id' => $restaurant->id, 'is_featured' => false, 'is_available' => true, 'name' => 'Not Featured']);

    $response = $this->get('/');

    $response->assertInertia(fn (Assert $page) => $page
        ->has('featuredItems', 1)
        ->where('featuredItems.0.name', 'Visible Dish')
    );
});

it('marks the homepage indexable with a self canonical', function () {
    makeRestaurant();

    $response = $this->get('/');

    $response->assertInertia(fn (Assert $page) => $page
        ->where('seo.robots', 'index,follow')
        ->where('seo.canonical', config('seo.base_url').'/')
    );
});
