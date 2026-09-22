<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Restaurant;
use App\Models\RestaurantGalleryImage;
use App\Models\RestaurantMenuCategory;
use App\Services\Seo\SeoMeta;
use App\Support\RestaurantPresenter;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class MenuController extends Controller
{
    public function index(): Response
    {
        $restaurant = Restaurant::where('is_active', true)->firstOrFail();

        // A real food photo behind the "Our Menu" heading reads much
        // better than the plain brand-color gradient PageHeader falls
        // back to - reuse a gallery shot rather than add a bespoke image.
        $headerImagePath = RestaurantGalleryImage::where('restaurant_id', $restaurant->id)
            ->where('is_active', true)
            ->where('category', 'food')
            ->orderBy('display_order')
            ->value('path');

        $categories = RestaurantMenuCategory::where('restaurant_id', $restaurant->id)
            ->where('is_active', true)
            ->with(['menuItems' => fn ($query) => $query->publiclyVisible()->with('images')])
            ->orderBy('display_order')
            ->orderBy('name')
            ->get()
            // A category with everything currently marked unavailable has
            // nothing to show - skip it rather than render an empty section.
            ->filter(fn (RestaurantMenuCategory $category) => $category->menuItems->isNotEmpty())
            ->map(fn (RestaurantMenuCategory $category) => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'description' => $category->description,
                'items' => $category->menuItems->map(fn ($item) => RestaurantPresenter::menuItem($item)),
            ])
            ->values();

        return Inertia::render('Public/Menu/Index', [
            'restaurant' => RestaurantPresenter::restaurant($restaurant),
            'headerImageUrl' => $headerImagePath ? Storage::url($headerImagePath) : null,
            'categories' => $categories,
            'seo' => SeoMeta::make(
                'Menu',
                'Browse the full House of Ramen menu - ramen, rice, noodles, sushi, and more, with real Bangladesh Taka prices.',
                '/menu',
            )->toArray(),
        ]);
    }
}
