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

    'site_name' => env('SEO_SITE_NAME', 'House of Ramen'),

    'organization_name' => env('SEO_ORGANIZATION_NAME', 'House of Ramen'),

    'default_title' => env('SEO_DEFAULT_TITLE', 'House of Ramen — Modern Ramen & Japanese-Korean Comfort Food in Dhaka'),

    'default_description' => env(
        'SEO_DEFAULT_DESCRIPTION',
        'House of Ramen serves modern Japanese-Korean ramen, rice, noodles, sushi and more in Uttara, Dhaka. Browse the menu, see the space, and find us.'
    ),

    // Falls back to APP_URL when unset - kept separate so a CDN/canonical
    // domain can differ from the app's own base URL if that's ever needed.
    'base_url' => env('SEO_BASE_URL', env('APP_URL', 'http://localhost')),

    // Relative to public/ - used as the Open Graph/Twitter image for any
    // page that has no more specific image (e.g. the restaurant's own
    // cover photo). 1200x630 (Facebook/Twitter/LinkedIn's recommended
    // link-preview ratio) - built from the real House of Ramen logo.
    'default_og_image' => env('SEO_DEFAULT_OG_IMAGE', 'brand/social-preview-1200x630.png'),

];
