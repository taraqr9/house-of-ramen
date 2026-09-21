<?php

namespace App\Http\Controllers;

use App\Filters\RestaurantReviewIndexFilter;
use App\Http\Requests\RestaurantReviewIndexRequest;
use App\Http\Requests\RestaurantReviewStoreRequest;
use App\Http\Requests\RestaurantReviewUpdateRequest;
use App\Models\RestaurantReview;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class RestaurantReviewController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(RestaurantReview::class, 'restaurant_review');
    }

    public function index(RestaurantReviewIndexRequest $request): View
    {
        $page_title = 'Customer Reviews';

        $query = RestaurantReview::query();

        $reviews = RestaurantReviewIndexFilter::applyFilters($query, $request)
            ->orderBy('display_order')
            ->orderByDesc('reviewed_at')
            ->paginate(20)
            ->appends($request->query());

        return view('restaurant.reviews.index', compact('page_title', 'reviews'));
    }

    public function create(): View
    {
        return view('restaurant.reviews.create', ['page_title' => 'Add Review']);
    }

    public function store(RestaurantReviewStoreRequest $request): RedirectResponse
    {
        RestaurantReview::create($request->validated());

        return redirect()->route('restaurant-reviews.index')->with('success', 'Review created successfully.');
    }

    public function edit(RestaurantReview $restaurant_review): View
    {
        return view('restaurant.reviews.edit', [
            'page_title' => 'Edit Review',
            'review' => $restaurant_review,
        ]);
    }

    public function update(RestaurantReviewUpdateRequest $request, RestaurantReview $restaurant_review): RedirectResponse
    {
        $restaurant_review->update($request->validated());

        return redirect()->route('restaurant-reviews.index')->with('success', 'Review updated successfully.');
    }

    public function destroy(RestaurantReview $restaurant_review): RedirectResponse
    {
        $restaurant_review->delete();

        return redirect()->route('restaurant-reviews.index')->with('success', 'Review deleted successfully.');
    }

    /**
     * Quick on/off switch from the index table, same pattern as popup
     * offers/menu items - flips is_active without opening the full edit form.
     */
    public function toggleActive(RestaurantReview $restaurant_review): JsonResponse
    {
        $this->authorize('update', $restaurant_review);

        $restaurant_review->update([
            'is_active' => ! $restaurant_review->is_active,
            'updated_by' => auth()->id(),
        ]);

        return response()->json(['is_active' => $restaurant_review->is_active]);
    }
}
