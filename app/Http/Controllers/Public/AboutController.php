<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Services\Seo\SeoMeta;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The About Us page - entirely static marketing content (mission, how the
 * site works, founder), so unlike every other Public controller this reads
 * from no models. The founder photo lives as a plain static asset
 * (public/images/about/) rather than going through the phone catalogue's
 * image pipeline (App\Models\PhoneImage) since it isn't catalogue data -
 * its URL is still built server-side with asset() (not hardcoded in the
 * Vue page) so it stays correct if ASSET_URL/a CDN is ever configured.
 */
class AboutController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Public/About', [
            'founderPhotoUrl' => asset('images/about/founder-taraq-rahman.jpg'),
            'seo' => SeoMeta::make(
                'About Us',
                'Phone Kinbo is a Bangladesh-focused platform helping you choose the right smartphone for your budget, needs, and preferences.',
                '/about',
            )->toArray(),
        ]);
    }
}
