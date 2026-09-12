<?php

/*
|--------------------------------------------------------------------------
| SEO defaults
|--------------------------------------------------------------------------
|
| Sitewide fallbacks used when a page doesn't provide its own value (see
| App\Services\Seo\SeoMeta). Deliberately a config file rather than a
| database-backed admin screen - editable by a developer via .env/deploy,
| without adding an admin UI for what is, for now, a handful of values
| that change rarely.
|
*/

return [

    'site_name' => env('SEO_SITE_NAME', 'Phone Kinbo'),

    'organization_name' => env('SEO_ORGANIZATION_NAME', 'Phone Kinbo'),

    'default_title' => env('SEO_DEFAULT_TITLE', 'Phone Kinbo — Find the right phone for you'),

    'default_description' => env(
        'SEO_DEFAULT_DESCRIPTION',
        'Compare phone prices and specifications in Bangladesh. Tell Phone Kinbo your budget and what matters to you and get honest, unsponsored recommendations.'
    ),

    // Falls back to APP_URL when unset - kept separate so a CDN/canonical
    // domain can differ from the app's own base URL if that's ever needed.
    'base_url' => env('SEO_BASE_URL', env('APP_URL', 'http://localhost')),

    // Relative to public/ - used as the Open Graph/Twitter image for any
    // page that has no more specific image (e.g. a phone's own photo).
    // 1200x630 (Facebook/Twitter/LinkedIn's recommended link-preview
    // ratio) - built from the site's own brand colors/logo mark, see
    // public/brand/pk-lockup.svg for the source shapes.
    'default_og_image' => env('SEO_DEFAULT_OG_IMAGE', 'brand/social-preview-1200x630.png'),

];
