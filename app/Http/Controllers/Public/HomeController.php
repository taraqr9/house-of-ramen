<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Restaurant;
use App\Models\RestaurantGalleryImage;
use App\Models\RestaurantMenuItem;
use App\Models\RestaurantPopupOffer;
use App\Models\RestaurantReview;
use App\Models\RestaurantVideoFeature;
use App\Services\Seo\SeoMeta;
use App\Support\RestaurantPresenter;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function index(): Response
    {
        $restaurant = Restaurant::where('is_active', true)->firstOrFail();

        // Hero slides come from real gallery photos only (interior/food) -
        // never a stock image - so the slider simply shows fewer slides
        // if fewer photos have been uploaded yet, rather than padding
        // itself out with something fake.
        $heroSlides = RestaurantGalleryImage::where('restaurant_id', $restaurant->id)
            ->where('is_active', true)
            ->whereIn('category', ['interior', 'food'])
            ->orderBy('display_order')
            ->take(5)
            ->get()
            ->map(fn (RestaurantGalleryImage $image) => RestaurantPresenter::galleryImage($image));

        $featuredItems = RestaurantMenuItem::where('restaurant_id', $restaurant->id)
            ->publiclyVisible()
            ->where('is_featured', true)
            ->orderBy('display_order')
            ->get()
            ->map(fn (RestaurantMenuItem $item) => RestaurantPresenter::menuItem($item));

        $newItems = RestaurantMenuItem::where('restaurant_id', $restaurant->id)
            ->publiclyVisible()
            ->where('is_new', true)
            ->orderBy('display_order')
            ->get()
            ->map(fn (RestaurantMenuItem $item) => RestaurantPresenter::menuItem($item));

        $videoFeatures = RestaurantVideoFeature::where('restaurant_id', $restaurant->id)
            ->publiclyVisible()
            ->orderBy('display_order')
            ->get()
            ->map(fn (RestaurantVideoFeature $video) => RestaurantPresenter::videoFeature($video));

        $popupOffers = RestaurantPopupOffer::where('restaurant_id', $restaurant->id)
            ->publiclyVisible()
            ->orderBy('display_order')
            ->get()
            ->map(fn (RestaurantPopupOffer $offer) => RestaurantPresenter::popupOffer($offer));

        // Real reviews the restaurant owner copied over from their actual
        // Google listing (see RestaurantSeeder) - never scraped or
        // API-fetched, so the rating summary below is computed from
        // whatever's actually been entered rather than pulled live.
        $reviews = RestaurantReview::where('restaurant_id', $restaurant->id)
            ->publiclyVisible()
            ->orderBy('display_order')
            ->take(6)
            ->get();

        return Inertia::render('Public/Home', [
            'restaurant' => RestaurantPresenter::restaurant($restaurant),
            'heroSlides' => $heroSlides,
            'featuredItems' => $featuredItems,
            'newItems' => $newItems,
            'videoFeatures' => $videoFeatures,
            'popupOffers' => $popupOffers,
            'reviews' => $reviews->map(fn (RestaurantReview $review) => RestaurantPresenter::review($review)),
            'reviewsSummary' => [
                'rating' => $reviews->isNotEmpty() ? round($reviews->avg('rating'), 1) : null,
                'total' => $reviews->count() ?: null,
            ],
            // A short, page-specific title here (not config('seo.default_title'),
            // which is the already-suffixed "Name — description" string
            // app.js/ssr.js fall back to when a page provides none at all) -
            // otherwise the "— House of Ramen" the title() callback appends
            // to every page would double up on the homepage.
            'seo' => SeoMeta::make(
                $restaurant->tagline ?? 'Modern Ramen & Japanese-Korean Comfort Food in Dhaka',
                config('seo.default_description'),
                '/',
            )->ogImage($restaurant->cover_image_path ? SeoMeta::absoluteUrl(Storage::url($restaurant->cover_image_path)) : null)
                ->toArray(),
        ]);
    }
}
