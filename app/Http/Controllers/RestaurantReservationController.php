<?php

namespace App\Http\Controllers;

use App\Filters\RestaurantReservationIndexFilter;
use App\Http\Requests\RestaurantReservationIndexRequest;
use App\Http\Requests\RestaurantReservationUpdateRequest;
use App\Models\RestaurantReservation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class RestaurantReservationController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(RestaurantReservation::class, 'restaurant_reservation');
    }

    public function index(RestaurantReservationIndexRequest $request): View
    {
        $page_title = 'Reservations';

        $query = RestaurantReservation::query();

        $reservations = RestaurantReservationIndexFilter::applyFilters($query, $request)
            ->orderByDesc('reservation_date')
            ->orderByDesc('id')
            ->paginate(20)
            ->appends($request->query());

        return view('restaurant.reservations.index', compact('page_title', 'reservations'));
    }

    /**
     * Staff only ever change the status (see RestaurantReservationUpdateRequest) -
     * returns JSON since it's called from the index table's inline status
     * select, the same AJAX pattern as the is_active toggles elsewhere.
     */
    public function update(RestaurantReservationUpdateRequest $request, RestaurantReservation $restaurant_reservation): JsonResponse
    {
        $restaurant_reservation->update($request->validated());

        return response()->json(['status' => $restaurant_reservation->status->value]);
    }

    public function destroy(RestaurantReservation $restaurant_reservation): RedirectResponse
    {
        $restaurant_reservation->delete();

        return redirect()->route('restaurant-reservations.index')->with('success', 'Reservation deleted successfully.');
    }
}
