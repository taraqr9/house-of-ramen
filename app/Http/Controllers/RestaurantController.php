<?php

namespace App\Http\Controllers;

use App\Http\Requests\RestaurantUpdateRequest;
use App\Models\Restaurant;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * A single-restaurant settings screen, not a full resource - House of
 * Ramen is the only Restaurant row for this launch (see the "Two-Restaurant
 * Future Architecture" note in the project brief), so index/create/destroy
 * would be dead code today. Adding a second restaurant later is a data
 * change (a new Restaurant row + its own menu/gallery rows via the same
 * restaurant_id foreign keys), not a rebuild of this screen.
 */
class RestaurantController extends Controller
{
    public function edit(): View
    {
        abort_unless(auth()->user()->can('restaurant-view'), 403);

        return view('restaurant.settings', [
            'page_title' => 'Restaurant Settings',
            'restaurant' => Restaurant::firstOrFail(),
        ]);
    }

    public function update(RestaurantUpdateRequest $request): RedirectResponse
    {
        $restaurant = Restaurant::firstOrFail();

        $data = $request->safe()->except(['logo', 'cover_image', 'delivery_platforms']);

        $data['delivery_platforms'] = collect(explode(',', (string) $request->input('delivery_platforms')))
            ->map(fn ($platform) => trim($platform))
            ->filter()
            ->values()
            ->all();

        if ($request->hasFile('logo')) {
            $data['logo_path'] = $request->file('logo')->store('restaurant/branding', 'public');
        }

        if ($request->hasFile('cover_image')) {
            $data['cover_image_path'] = $request->file('cover_image')->store('restaurant/branding', 'public');
        }

        $restaurant->update($data);

        return redirect()->route('restaurant.edit')->with('success', 'Restaurant settings updated successfully.');
    }
}
