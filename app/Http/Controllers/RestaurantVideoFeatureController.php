<?php

namespace App\Http\Controllers;

use App\Filters\RestaurantVideoFeatureIndexFilter;
use App\Http\Requests\RestaurantVideoFeatureIndexRequest;
use App\Http\Requests\RestaurantVideoFeatureStoreRequest;
use App\Http\Requests\RestaurantVideoFeatureUpdateRequest;
use App\Models\RestaurantVideoFeature;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class RestaurantVideoFeatureController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(RestaurantVideoFeature::class, 'restaurant_video_feature');
    }

    public function index(RestaurantVideoFeatureIndexRequest $request): View
    {
        $page_title = 'Blogger Video Features';

        $query = RestaurantVideoFeature::query();

        $videoFeatures = RestaurantVideoFeatureIndexFilter::applyFilters($query, $request)
            ->orderBy('display_order')
            ->orderBy('title')
            ->paginate(20)
            ->appends($request->query());

        return view('restaurant.video-features.index', compact('page_title', 'videoFeatures'));
    }

    public function create(): View
    {
        return view('restaurant.video-features.create', ['page_title' => 'Add Video Feature']);
    }

    public function store(RestaurantVideoFeatureStoreRequest $request): RedirectResponse
    {
        $data = $request->safe()->except('thumbnail');

        if ($request->hasFile('thumbnail')) {
            $data['thumbnail_path'] = $request->file('thumbnail')->store('restaurant/video-features', 'public');
        }

        RestaurantVideoFeature::create($data);

        return redirect()->route('restaurant-video-features.index')->with('success', 'Video feature created successfully.');
    }

    public function edit(RestaurantVideoFeature $restaurant_video_feature): View
    {
        return view('restaurant.video-features.edit', [
            'page_title' => 'Edit Video Feature',
            'videoFeature' => $restaurant_video_feature,
        ]);
    }

    public function update(RestaurantVideoFeatureUpdateRequest $request, RestaurantVideoFeature $restaurant_video_feature): RedirectResponse
    {
        $data = $request->safe()->except('thumbnail');

        if ($request->hasFile('thumbnail')) {
            $data['thumbnail_path'] = $request->file('thumbnail')->store('restaurant/video-features', 'public');
        }

        $restaurant_video_feature->update($data);

        return redirect()->route('restaurant-video-features.index')->with('success', 'Video feature updated successfully.');
    }

    public function destroy(RestaurantVideoFeature $restaurant_video_feature): RedirectResponse
    {
        $restaurant_video_feature->delete();

        return redirect()->route('restaurant-video-features.index')->with('success', 'Video feature deleted successfully.');
    }
}
