<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Restaurant;
use App\Models\RestaurantGalleryImage;
use App\Services\Seo\SeoMeta;
use App\Support\RestaurantPresenter;
use Inertia\Inertia;
use Inertia\Response;

class GalleryController extends Controller
{
    public function index(): Response
    {
        $restaurant = Restaurant::where('is_active', true)->firstOrFail();

        $images = RestaurantGalleryImage::where('restaurant_id', $restaurant->id)
            ->where('is_active', true)
            ->orderBy('category')
            ->orderBy('display_order')
            ->get()
            ->map(fn (RestaurantGalleryImage $image) => RestaurantPresenter::galleryImage($image));

        return Inertia::render('Public/Gallery', [
            'restaurant' => RestaurantPresenter::restaurant($restaurant),
            'images' => $images,
            'seo' => SeoMeta::make(
                'Gallery',
                'A look inside House of Ramen - the food, the atmosphere, and the space.',
                '/gallery',
            )->toArray(),
        ]);
    }
}
