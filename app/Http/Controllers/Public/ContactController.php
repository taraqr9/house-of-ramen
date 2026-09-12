<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Restaurant;
use App\Services\Seo\SeoMeta;
use App\Support\RestaurantPresenter;
use Inertia\Inertia;
use Inertia\Response;

class ContactController extends Controller
{
    public function index(): Response
    {
        $restaurant = Restaurant::where('is_active', true)->firstOrFail();

        return Inertia::render('Public/Contact', [
            'restaurant' => RestaurantPresenter::restaurant($restaurant),
            // A Google Maps search URL built from the real address text -
            // never an invented place ID or lat/lng - see the project
            // brief's rule against fabricating location data.
            'mapsSearchUrl' => $restaurant->address
                ? 'https://www.google.com/maps/search/?api=1&query='.urlencode($restaurant->address.', '.$restaurant->area)
                : null,
            'seo' => SeoMeta::make(
                'Contact & Location',
                'Find House of Ramen - address, phone, and how to reach us.',
                '/contact',
            )->toArray(),
        ]);
    }
}
