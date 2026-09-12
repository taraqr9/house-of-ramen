<?php

return [

    /*
    |--------------------------------------------------------------------------
    | GA4 Data API (server-side reporting for the admin Analytics dashboard)
    |--------------------------------------------------------------------------
    |
    | Completely separate from VITE_GA_MEASUREMENT_ID (resources/js/utils/
    | analytics.js), which is the public site's client-side tracking ID.
    | This is the server-side Google Analytics Data API v1beta connection
    | used only by App\Services\Analytics\* to build the /admin dashboard's
    | Analytics section (App\Http\Controllers\DashboardController) - never
    | exposed to the browser/Vue in any way.
    */

    // The numeric GA4 Property ID (GA4 Admin -> Property Settings ->
    // Property details -> "Property ID"), NOT the "G-XXXXXXXXXX" Measurement
    // ID the public site uses - two different identifiers for the same GA4
    // property.
    'property_id' => env('GA4_PROPERTY_ID'),

    // Absolute path to a Google service account JSON key file, granted
    // "Viewer" access to the GA4 property (GA4 Admin -> Property Access
    // Management - not Google Cloud IAM). Never commit this file; the
    // conventional local path (storage/app/private/...) is already
    // git-ignored by default (see .gitignore's storage/app/private rule).
    'credentials_path' => env('GA4_CREDENTIALS_PATH'),

    // How long one GA4 report response is cached before the next dashboard
    // load re-queries the API, keyed per report/date-range combination (see
    // App\Services\Analytics\AnalyticsDashboardService) - keeps repeated
    // admin dashboard visits from hitting the Data API's daily quota.
    'cache_ttl' => (int) env('GA4_CACHE_TTL', 300),

];
