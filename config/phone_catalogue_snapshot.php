<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Phone Catalogue Snapshot Directory
    |--------------------------------------------------------------------------
    |
    | Where ExportPhoneCatalogueCommand writes, and PhoneCatalogueSeeder
    | reads, the committed, fully-reviewed catalogue snapshot (one JSON
    | file per brand, plus an images/ directory of the actual verified
    | image files) - what lets `php artisan migrate:fresh --seed`
    | reproduce the catalogue on a fresh install with no network access.
    | Configurable (rather than a hardcoded path in the command/seeder) so
    | tests can point it at a temporary directory instead of touching the
    | real committed seed data.
    |
    */

    'directory' => database_path('seed-data/catalogue'),

];
