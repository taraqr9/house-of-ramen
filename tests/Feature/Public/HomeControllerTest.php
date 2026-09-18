<?php

use App\Models\RestaurantGalleryImage;
use App\Models\RestaurantMenuItem;
use App\Models\RestaurantPopupOffer;
use App\Models\RestaurantVideoFeature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('renders the public homepage with the restaurant, hero slides, featured items, and video features', function () {
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

    RestaurantVideoFeature::factory()->count(2)->create([
        'restaurant_id' => $restaurant->id,
        'is_active' => true,
    ]);

    $response = $this->get('/');

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Public/Home')
        ->where('restaurant.name', $restaurant->name)
        ->has('heroSlides', 2)
        ->has('featuredItems', 2)
        ->has('newItems', 0)
        ->has('videoFeatures', 2)
        ->has('popupOffers', 0)
        ->has('seo')
    );
});

it('only shows active video features on the homepage, with thumbnail and embed urls derived from the youtube link', function () {
    $restaurant = makeRestaurant();

    RestaurantVideoFeature::factory()->create([
        'restaurant_id' => $restaurant->id,
        'title' => 'Visible Review',
        'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        'is_active' => true,
    ]);

    RestaurantVideoFeature::factory()->create([
        'restaurant_id' => $restaurant->id,
        'title' => 'Hidden Review',
        'is_active' => false,
    ]);

    $response = $this->get('/');

    $response->assertInertia(fn (Assert $page) => $page
        ->has('videoFeatures', 1)
        ->where('videoFeatures.0.title', 'Visible Review')
        ->where('videoFeatures.0.platform', 'youtube')
        ->where('videoFeatures.0.thumbnail_url', 'https://img.youtube.com/vi/dQw4w9WgXcQ/hqdefault.jpg')
        ->where('videoFeatures.0.embed_url', 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?autoplay=1&rel=0')
    );
});

it('builds a facebook video plugin embed url and requires an uploaded thumbnail', function () {
    $restaurant = makeRestaurant();

    RestaurantVideoFeature::factory()->create([
        'restaurant_id' => $restaurant->id,
        'title' => 'Facebook Review',
        'video_url' => 'https://www.facebook.com/HouseOfRamen/videos/1234567890/',
        'is_active' => true,
    ]);

    $response = $this->get('/');

    $response->assertInertia(fn (Assert $page) => $page
        ->has('videoFeatures', 1)
        ->where('videoFeatures.0.platform', 'facebook')
        ->where('videoFeatures.0.thumbnail_url', null)
        ->where(
            'videoFeatures.0.embed_url',
            'https://www.facebook.com/plugins/video.php?href='.urlencode('https://www.facebook.com/HouseOfRamen/videos/1234567890/').'&show_text=false&autoplay=true'
        )
    );
});

it('builds an instagram embed url from the reel shortcode', function () {
    $restaurant = makeRestaurant();

    RestaurantVideoFeature::factory()->create([
        'restaurant_id' => $restaurant->id,
        'title' => 'Instagram Review',
        'video_url' => 'https://www.instagram.com/reel/Cabc123XYZ9/',
        'is_active' => true,
    ]);

    $response = $this->get('/');

    $response->assertInertia(fn (Assert $page) => $page
        ->has('videoFeatures', 1)
        ->where('videoFeatures.0.platform', 'instagram')
        ->where('videoFeatures.0.embed_url', 'https://www.instagram.com/reel/Cabc123XYZ9/embed')
    );
});

it('prefers an uploaded thumbnail over the auto-derived youtube one', function () {
    $restaurant = makeRestaurant();

    RestaurantVideoFeature::factory()->create([
        'restaurant_id' => $restaurant->id,
        'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        'thumbnail_path' => 'restaurant/video-features/custom-thumb.jpg',
        'is_active' => true,
    ]);

    $response = $this->get('/');

    $response->assertInertia(fn (Assert $page) => $page
        ->where('videoFeatures.0.thumbnail_url', Storage::url('restaurant/video-features/custom-thumb.jpg'))
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

it('only shows available, new items in the homepage new items section', function () {
    $restaurant = makeRestaurant();

    RestaurantMenuItem::factory()->create(['restaurant_id' => $restaurant->id, 'is_new' => true, 'is_available' => true, 'name' => 'Fresh Dish']);
    RestaurantMenuItem::factory()->create(['restaurant_id' => $restaurant->id, 'is_new' => true, 'is_available' => false, 'name' => 'Unavailable New Dish']);
    RestaurantMenuItem::factory()->create(['restaurant_id' => $restaurant->id, 'is_new' => false, 'is_available' => true, 'name' => 'Not New']);

    $response = $this->get('/');

    $response->assertInertia(fn (Assert $page) => $page
        ->has('newItems', 1)
        ->where('newItems.0.name', 'Fresh Dish')
    );
});

it('only shows active popup offers on the homepage, ordered, image only', function () {
    $restaurant = makeRestaurant();

    RestaurantPopupOffer::factory()->create([
        'restaurant_id' => $restaurant->id,
        'title' => 'Internal note for admins',
        'image_path' => 'restaurant/popup-offers/visible.jpg',
        'display_order' => 2,
        'is_active' => true,
    ]);

    RestaurantPopupOffer::factory()->create([
        'restaurant_id' => $restaurant->id,
        'image_path' => 'restaurant/popup-offers/first.jpg',
        'display_order' => 1,
        'is_active' => true,
    ]);

    RestaurantPopupOffer::factory()->create([
        'restaurant_id' => $restaurant->id,
        'is_active' => false,
    ]);

    $response = $this->get('/');

    $response->assertInertia(fn (Assert $page) => $page
        ->has('popupOffers', 2)
        ->where('popupOffers.0.image_url', Storage::url('restaurant/popup-offers/first.jpg'))
        ->where('popupOffers.1.image_url', Storage::url('restaurant/popup-offers/visible.jpg'))
        ->missing('popupOffers.0.title')
    );
});

it('does not show any popup offers when none are active', function () {
    $restaurant = makeRestaurant();

    RestaurantPopupOffer::factory()->create(['restaurant_id' => $restaurant->id, 'is_active' => false]);

    $response = $this->get('/');

    $response->assertInertia(fn (Assert $page) => $page->has('popupOffers', 0));
});

it('marks the homepage indexable with a self canonical', function () {
    makeRestaurant();

    $response = $this->get('/');

    $response->assertInertia(fn (Assert $page) => $page
        ->where('seo.robots', 'index,follow')
        ->where('seo.canonical', config('seo.base_url').'/')
    );
});
