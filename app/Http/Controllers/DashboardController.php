<?php

namespace App\Http\Controllers;

use App\Models\Restaurant;
use App\Models\RestaurantGalleryImage;
use App\Models\RestaurantMenuCategory;
use App\Models\RestaurantMenuItem;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        abort_unless(auth()->user()->can('dashboard-view'), 403);

        $page_title = 'Dashboard';

        if (! auth()->user()->can('restaurant_menu_item-view')) {
            return view('dashboard', ['page_title' => $page_title, 'hasRestaurantData' => false]);
        }

        $restaurant = Restaurant::first();

        $totalItems = RestaurantMenuItem::count();
        $unavailableItems = RestaurantMenuItem::where('is_available', false)->count();
        $itemsMissingImages = RestaurantMenuItem::whereNull('image_path')->count();

        $itemsByCategory = RestaurantMenuCategory::withCount('menuItems')
            ->orderByDesc('menu_items_count')
            ->get(['id', 'name']);

        return view('dashboard', [
            'page_title' => $page_title,
            'hasRestaurantData' => true,
            'restaurant' => $restaurant,
            'totalCategories' => RestaurantMenuCategory::count(),
            'totalItems' => $totalItems,
            'availableItems' => $totalItems - $unavailableItems,
            'unavailableItems' => $unavailableItems,
            'featuredItems' => RestaurantMenuItem::where('is_featured', true)->count(),
            'itemsMissingImages' => $itemsMissingImages,
            'galleryImages' => RestaurantGalleryImage::count(),
            'itemsByCategory' => $itemsByCategory,
        ]);
    }
}
