<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Restaurant;
use App\Models\RestaurantGalleryImage;
use App\Services\Seo\SeoMeta;
use App\Support\RestaurantPresenter;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Reads its copy from the Restaurant row (tagline/description) rather
 * than hardcoded strings, so an admin can update the real story from
 * Restaurant Settings without another code change - see the "About House
 * of Ramen" section of the project brief (no invented history/claims).
 */
class AboutController extends Controller
{
    public function index(): Response
    {
        $restaurant = Restaurant::where('is_active', true)->firstOrFail();

        $interiorImages = RestaurantGalleryImage::where('restaurant_id', $restaurant->id)
            ->where('is_active', true)
            ->where('category', 'interior')
            ->orderBy('display_order')
            ->take(3)
            ->get()
            ->map(fn (RestaurantGalleryImage $image) => RestaurantPresenter::galleryImage($image));

        return Inertia::render('Public/About', [
            'restaurant' => RestaurantPresenter::restaurant($restaurant),
            'interiorImages' => $interiorImages,
            'seo' => SeoMeta::make(
                'About Us',
                $restaurant->tagline ?? config('seo.default_description'),
                '/about',
            )->toArray(),
        ]);
    }
}
