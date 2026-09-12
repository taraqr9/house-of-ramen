<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Restaurant;
use App\Models\RestaurantMenuCategory;
use App\Services\Seo\SeoMeta;
use App\Support\RestaurantPresenter;
use Inertia\Inertia;
use Inertia\Response;

class MenuController extends Controller
{
    public function index(): Response
    {
        $restaurant = Restaurant::where('is_active', true)->firstOrFail();

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
            'categories' => $categories,
            'seo' => SeoMeta::make(
                'Menu',
                'Browse the full House of Ramen menu - ramen, rice, noodles, sushi, and more, with real Bangladesh Taka prices.',
                '/menu',
            )->toArray(),
        ]);
    }
}
