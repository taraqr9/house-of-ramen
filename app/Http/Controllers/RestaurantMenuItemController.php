<?php

namespace App\Http\Controllers;

use App\Filters\RestaurantMenuItemIndexFilter;
use App\Http\Requests\RestaurantMenuItemIndexRequest;
use App\Http\Requests\RestaurantMenuItemStoreRequest;
use App\Http\Requests\RestaurantMenuItemUpdateRequest;
use App\Models\RestaurantMenuCategory;
use App\Models\RestaurantMenuItem;
use App\Models\RestaurantMenuItemImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class RestaurantMenuItemController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(RestaurantMenuItem::class, 'restaurant_menu_item');
    }

    public function index(RestaurantMenuItemIndexRequest $request): View
    {
        $page_title = 'Menu Items';

        $query = RestaurantMenuItem::with('category');

        $items = RestaurantMenuItemIndexFilter::applyFilters($query, $request)
            ->orderBy('display_order')
            ->orderBy('name')
            ->paginate(20)
            ->appends($request->query());

        return view('restaurant.menu-items.index', [
            'page_title' => $page_title,
            'items' => $items,
            'categories' => RestaurantMenuCategory::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function create(): View
    {
        return view('restaurant.menu-items.create', [
            'page_title' => 'Add Menu Item',
            'categories' => RestaurantMenuCategory::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(RestaurantMenuItemStoreRequest $request): RedirectResponse
    {
        $data = $request->safe()->except(['image', 'gallery_images']);

        if ($request->hasFile('image')) {
            $data['image_path'] = $request->file('image')->store('restaurant/menu-items', 'public');
        }

        $item = RestaurantMenuItem::create($data);

        $this->storeGalleryImages($item, $request);

        return redirect()->route('restaurant-menu-items.index')->with('success', 'Menu item created successfully.');
    }

    public function edit(RestaurantMenuItem $restaurant_menu_item): View
    {
        return view('restaurant.menu-items.edit', [
            'page_title' => 'Edit Menu Item',
            'item' => $restaurant_menu_item->load('images'),
            'categories' => RestaurantMenuCategory::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(RestaurantMenuItemUpdateRequest $request, RestaurantMenuItem $restaurant_menu_item): RedirectResponse
    {
        $data = $request->safe()->except(['image', 'gallery_images']);

        if ($request->hasFile('image')) {
            $data['image_path'] = $request->file('image')->store('restaurant/menu-items', 'public');
        }

        $restaurant_menu_item->update($data);

        $this->storeGalleryImages($restaurant_menu_item, $request);

        return redirect()->route('restaurant-menu-items.index')->with('success', 'Menu item updated successfully.');
    }

    public function destroy(RestaurantMenuItem $restaurant_menu_item): RedirectResponse
    {
        $restaurant_menu_item->delete();

        return redirect()->route('restaurant-menu-items.index')->with('success', 'Menu item deleted successfully.');
    }

    /**
     * Extra photos for an item's swipe gallery on the public menu page,
     * beyond its one primary `image_path` - trusted, admin-uploaded, so
     * unlike the deleted phone-catalogue pipeline there's no separate
     * verification workflow here (see RestaurantMenuItemImage).
     */
    private function storeGalleryImages(RestaurantMenuItem $item, RestaurantMenuItemStoreRequest|RestaurantMenuItemUpdateRequest $request): void
    {
        if (! $request->hasFile('gallery_images')) {
            return;
        }

        $nextOrder = (int) $item->images()->max('display_order') + 1;

        foreach ($request->file('gallery_images') as $file) {
            RestaurantMenuItemImage::create([
                'restaurant_menu_item_id' => $item->id,
                'path' => $file->store('restaurant/menu-items', 'public'),
                'display_order' => $nextOrder++,
            ]);
        }
    }

    public function destroyImage(RestaurantMenuItem $restaurant_menu_item, RestaurantMenuItemImage $image): RedirectResponse
    {
        $this->authorize('update', $restaurant_menu_item);
        abort_unless($image->restaurant_menu_item_id === $restaurant_menu_item->id, 404);

        $image->delete();

        return redirect()->route('restaurant-menu-items.edit', $restaurant_menu_item->id)->with('success', 'Image removed.');
    }

    /**
     * Quick on/off switches from the index table (Featured/New Item
     * columns) so an admin doesn't have to open the full edit form just
     * to flip which homepage section a dish shows in.
     */
    public function toggleFeatured(RestaurantMenuItem $restaurant_menu_item): JsonResponse
    {
        return $this->toggleFlag($restaurant_menu_item, 'is_featured');
    }

    public function toggleNew(RestaurantMenuItem $restaurant_menu_item): JsonResponse
    {
        return $this->toggleFlag($restaurant_menu_item, 'is_new');
    }

    private function toggleFlag(RestaurantMenuItem $item, string $field): JsonResponse
    {
        $this->authorize('update', $item);

        $item->update([
            $field => ! $item->{$field},
            'updated_by' => auth()->id(),
        ]);

        return response()->json([$field => $item->{$field}]);
    }
}
