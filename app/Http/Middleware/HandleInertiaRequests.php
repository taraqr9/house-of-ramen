<?php

namespace App\Http\Middleware;

use App\Services\Seo\SeoMeta;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            // Sitewide values the public pages need for Organization/WebSite
            // JSON-LD and OG fallbacks (see Layouts/PublicLayout.vue) - kept
            // out of every individual controller so they can't drift.
            'siteMeta' => [
                'site_name' => config('seo.site_name'),
                'organization_name' => config('seo.organization_name'),
                'base_url' => rtrim(config('seo.base_url'), '/'),
                'default_og_image' => SeoMeta::absoluteUrl(config('seo.default_og_image')),
            ],
        ];
    }
}
