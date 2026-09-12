<?php

namespace App\Http\Controllers;

use App\Http\Requests\RestaurantGalleryImageStoreRequest;
use App\Http\Requests\RestaurantGalleryImageUpdateRequest;
use App\Models\RestaurantGalleryImage;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class RestaurantGalleryImageController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(RestaurantGalleryImage::class, 'restaurant_gallery_image');
    }

    public function index(): View
    {
        $page_title = 'Gallery';

        $images = RestaurantGalleryImage::orderBy('category')
            ->orderBy('display_order')
            ->paginate(24);

        return view('restaurant.gallery.index', compact('page_title', 'images'));
    }

    public function store(RestaurantGalleryImageStoreRequest $request): RedirectResponse
    {
        $data = $request->safe()->except('image');
        $data['path'] = $request->file('image')->store('restaurant/gallery', 'public');

        RestaurantGalleryImage::create($data);

        return redirect()->route('restaurant-gallery-images.index')->with('success', 'Image added to the gallery.');
    }

    public function update(RestaurantGalleryImageUpdateRequest $request, RestaurantGalleryImage $restaurant_gallery_image): RedirectResponse
    {
        $restaurant_gallery_image->update($request->validated());

        return redirect()->route('restaurant-gallery-images.index')->with('success', 'Image updated.');
    }

    public function destroy(RestaurantGalleryImage $restaurant_gallery_image): RedirectResponse
    {
        $restaurant_gallery_image->delete();

        return redirect()->route('restaurant-gallery-images.index')->with('success', 'Image removed from the gallery.');
    }
}
