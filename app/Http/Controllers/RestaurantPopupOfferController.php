<?php

namespace App\Http\Controllers;

use App\Filters\RestaurantPopupOfferIndexFilter;
use App\Http\Requests\RestaurantPopupOfferIndexRequest;
use App\Http\Requests\RestaurantPopupOfferStoreRequest;
use App\Http\Requests\RestaurantPopupOfferUpdateRequest;
use App\Models\RestaurantPopupOffer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class RestaurantPopupOfferController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(RestaurantPopupOffer::class, 'restaurant_popup_offer');
    }

    public function index(RestaurantPopupOfferIndexRequest $request): View
    {
        $page_title = 'Popup Offers';

        $query = RestaurantPopupOffer::query();

        $popupOffers = RestaurantPopupOfferIndexFilter::applyFilters($query, $request)
            ->orderBy('display_order')
            ->orderByDesc('id')
            ->paginate(20)
            ->appends($request->query());

        return view('restaurant.popup-offers.index', compact('page_title', 'popupOffers'));
    }

    public function create(): View
    {
        return view('restaurant.popup-offers.create', ['page_title' => 'Add Popup Offer']);
    }

    public function store(RestaurantPopupOfferStoreRequest $request): RedirectResponse
    {
        $data = $request->safe()->except('image');
        $data['image_path'] = $request->file('image')->store('restaurant/popup-offers', 'public');

        RestaurantPopupOffer::create($data);

        return redirect()->route('restaurant-popup-offers.index')->with('success', 'Popup offer created successfully.');
    }

    public function edit(RestaurantPopupOffer $restaurant_popup_offer): View
    {
        return view('restaurant.popup-offers.edit', [
            'page_title' => 'Edit Popup Offer',
            'popupOffer' => $restaurant_popup_offer,
        ]);
    }

    public function update(RestaurantPopupOfferUpdateRequest $request, RestaurantPopupOffer $restaurant_popup_offer): RedirectResponse
    {
        $data = $request->safe()->except('image');

        if ($request->hasFile('image')) {
            $data['image_path'] = $request->file('image')->store('restaurant/popup-offers', 'public');
        }

        $restaurant_popup_offer->update($data);

        return redirect()->route('restaurant-popup-offers.index')->with('success', 'Popup offer updated successfully.');
    }

    public function destroy(RestaurantPopupOffer $restaurant_popup_offer): RedirectResponse
    {
        $restaurant_popup_offer->delete();

        return redirect()->route('restaurant-popup-offers.index')->with('success', 'Popup offer deleted successfully.');
    }

    /**
     * Quick on/off switch from the index table, same pattern as the menu
     * item Featured/New Item toggles - flips is_active without opening
     * the full edit form.
     */
    public function toggleActive(RestaurantPopupOffer $restaurant_popup_offer): JsonResponse
    {
        $this->authorize('update', $restaurant_popup_offer);

        $restaurant_popup_offer->update([
            'is_active' => ! $restaurant_popup_offer->is_active,
            'updated_by' => auth()->id(),
        ]);

        return response()->json(['is_active' => $restaurant_popup_offer->is_active]);
    }
}
