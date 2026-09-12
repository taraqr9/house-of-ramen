<?php

namespace App\Http\Controllers;

use App\Filters\RestaurantMenuCategoryIndexFilter;
use App\Http\Requests\RestaurantMenuCategoryIndexRequest;
use App\Http\Requests\RestaurantMenuCategoryStoreRequest;
use App\Http\Requests\RestaurantMenuCategoryUpdateRequest;
use App\Models\RestaurantMenuCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class RestaurantMenuCategoryController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(RestaurantMenuCategory::class, 'restaurant_menu_category');
    }

    public function index(RestaurantMenuCategoryIndexRequest $request): View
    {
        $page_title = 'Menu Categories';

        $query = RestaurantMenuCategory::withCount('menuItems');

        $categories = RestaurantMenuCategoryIndexFilter::applyFilters($query, $request)
            ->orderBy('display_order')
            ->orderBy('name')
            ->paginate(20)
            ->appends($request->query());

        return view('restaurant.menu-categories.index', compact('page_title', 'categories'));
    }

    public function create(): View
    {
        return view('restaurant.menu-categories.create', ['page_title' => 'Add Menu Category']);
    }

    public function store(RestaurantMenuCategoryStoreRequest $request): RedirectResponse
    {
        RestaurantMenuCategory::create($request->validated());

        return redirect()->route('restaurant-menu-categories.index')->with('success', 'Menu category created successfully.');
    }

    public function edit(RestaurantMenuCategory $restaurant_menu_category): View
    {
        return view('restaurant.menu-categories.edit', [
            'page_title' => 'Edit Menu Category',
            'category' => $restaurant_menu_category,
        ]);
    }

    public function update(RestaurantMenuCategoryUpdateRequest $request, RestaurantMenuCategory $restaurant_menu_category): RedirectResponse
    {
        $restaurant_menu_category->update($request->validated());

        return redirect()->route('restaurant-menu-categories.index')->with('success', 'Menu category updated successfully.');
    }

    public function destroy(RestaurantMenuCategory $restaurant_menu_category): RedirectResponse
    {
        if ($restaurant_menu_category->menuItems()->exists()) {
            return redirect()->route('restaurant-menu-categories.index')->with('error', 'Cannot delete a category that still has menu items.');
        }

        $restaurant_menu_category->delete();

        return redirect()->route('restaurant-menu-categories.index')->with('success', 'Menu category deleted successfully.');
    }
}
