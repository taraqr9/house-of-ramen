<?php

use App\Services\PhoneImport\Sources\Retailers\AppleGadgetsSource;
use App\Services\PhoneImport\Sources\Retailers\DazzleSource;
use App\Services\PhoneImport\Sources\Retailers\RioInternationalSource;
use App\Services\PhoneImport\Sources\Retailers\StarTechSource;
use App\Services\PhoneImport\Sources\Retailers\SumashTechSource;
use App\Services\PhoneImport\Sources\SeedDatasetSource;

/*
|--------------------------------------------------------------------------
| Phone Data Source Registry
|--------------------------------------------------------------------------
|
| Each entry describes one pluggable data source adapter. Adapters are
| independently enable/disable-able here without touching application
| code. On boot, PhoneSourceRegistry syncs this list into the
| `phone_sources` table (creating/updating rows, never deleting), so
| reliability/active-state edited from the admin UI persists and this
| file stays the source of truth only for "which adapter class backs
| which source key".
|
| To add a real source later (manufacturer feed, permitted retailer
| API, etc.), implement PhoneSourceProvider (see
| App\Services\PhoneImport\Sources\AbstractPhoneSource and its
| ManufacturerSource / RetailerSource / BangladeshRetailerSource
| subclasses) and register it here.
|
*/

return [

    'sources' => [

        // Attributed to price/availability/spec edits made directly by the
        // developer in the admin panel. Not a fetchable provider (enabled
        // stays false so the import command never tries to run it) - it
        // only exists so manual edits show up in the Data Sources screen
        // with their own reliability and are traceable like anything else.
        'admin_manual' => [
            'class' => null,
            'name' => 'Admin (Manual Entry)',
            'type' => 'manual',
            'enabled' => false,
            'reliability_score' => 100,
            'requires_review' => false,
            'config' => [],
        ],

        'seed_dataset' => [
            'class' => SeedDatasetSource::class,
            'name' => 'Curated Seed Dataset',
            'type' => 'manual',
            'enabled' => true,
            // Manually curated by the developer/AI-assisted research, not a live
            // manufacturer or retailer feed - kept at moderate reliability and
            // always routed to review until confirmed against a real source.
            'reliability_score' => 55,
            'requires_review' => true,
            'config' => [
                'directory' => database_path('seed-data'),
            ],
        ],

        // Provenance tag for images collected via the Wikimedia Commons
        // adapter (see App\Services\PhoneImage). Not a phone-catalog data
        // provider - enabled stays false so ImportPhonesCommand never
        // tries to run it - it only exists so every phone_images row can
        // point at a phone_sources row with its own reliability/notes,
        // same as 'admin_manual' above.
        'wikimedia_commons' => [
            'class' => null,
            'name' => 'Wikimedia Commons',
            'type' => 'manual',
            'enabled' => false,
            // Community-contributed, openly-licensed media with clear
            // per-file attribution/license metadata - reliable for
            // sourcing but every match is still confidence-scored and
            // routed to review below a threshold (see PhoneImageCollector).
            'reliability_score' => 70,
            'requires_review' => true,
            'config' => [],
        ],

        // Provenance tag for images collected via the Openverse adapter
        // (see App\Services\PhoneImage) - same role as 'wikimedia_commons'
        // above, not a fetchable phone-catalog data provider.
        'openverse' => [
            'class' => null,
            'name' => 'Openverse',
            'type' => 'manual',
            'enabled' => false,
            // Aggregates openly-licensed media from many providers with
            // explicit per-item license metadata - reliable for sourcing,
            // but (like wikimedia_commons) every match is still
            // confidence-scored and routed to review below a threshold.
            'reliability_score' => 65,
            'requires_review' => true,
            'config' => [],
        ],

        // Provenance tag for images individually sourced by hand (see
        // PhoneImageCollector::attachManual()) - a developer/AI-assisted
        // web search for one specific phone's real product photo,
        // cross-checked against the exact model before being attached,
        // rather than a keyword-matched automated candidate. Not a
        // fetchable provider - same role as 'wikimedia_commons' above.
        'manual_research' => [
            'class' => null,
            'name' => 'Manual Web Research',
            'type' => 'manual',
            'enabled' => false,
            // Each one is individually verified against the exact model
            // before being attached (unlike the automated providers'
            // keyword/text matching), so it's stored directly as VERIFIED
            // rather than routed through the confidence-threshold gate.
            'reliability_score' => 80,
            'requires_review' => false,
            'config' => [],
        ],

        // Catalogue-expansion phase (2026): AI-researched phone records,
        // individually compiled from publicly documented specifications and
        // cross-referenced across multiple angles before being written -
        // deliberately kept in its own directory/source (rather than reusing
        // 'seed_dataset' above) so its provenance and reliability stay
        // distinguishable from the original bootstrap dataset. Unlike
        // seed_dataset, this is NOT force-routed to review: a record whose
        // fields are thoroughly populated (see ConfidenceCalculator - identity/
        // spec/software completeness, not just this source's reliability
        // score) clears the auto-approve threshold and publishes directly;
        // a thin/uncertain record does not, and is held back
        // (Phone.is_active = false, see PhoneImportRunner::materialize())
        // rather than published on faith. Reliability is set below every
        // real manufacturer/retailer tier - this is still AI-assisted
        // research, not a primary source - but high enough that a complete,
        // careful record can actually clear the bar.
        'ai_research_2026' => [
            'class' => SeedDatasetSource::class,
            'name' => 'AI Research (2026 Catalogue Expansion)',
            'type' => 'ai_assisted',
            'enabled' => true,
            'reliability_score' => 72,
            'requires_review' => false,
            'config' => [
                'directory' => database_path('seed-data/expansion-2026'),
            ],
        ],

        // Example of how a future, real adapter would be registered:
        // 'samsung_bd' => [
        //     'class' => \App\Services\PhoneImport\Sources\Manufacturer\SamsungSource::class,
        //     'name' => 'Samsung Bangladesh',
        //     'type' => 'manufacturer',
        //     'enabled' => false,
        //     'reliability_score' => 95,
        //     'requires_review' => false,
        //     'config' => [],
        // ],

        /*
        |------------------------------------------------------------
        | Real Bangladesh retailer price sources (2026)
        |------------------------------------------------------------
        | Each discovers its own product URLs generically from the
        | catalogue itself (brand/model -> candidate slug -> fetch ->
        | verify) rather than a manually curated list - see
        | App\Services\PhoneImport\Sources\Retailers\RetailerListingSource
        | and App\Models\PhoneRetailerMatchAttempt for how that stays
        | scalable and resumable across the whole catalogue.
        | requires_review stays false: these are primary-retailer price
        | observations, not AI-guessed data, so they follow the same
        | auto-approve path any other reliable source does -
        | PriceAggregator's own sanity/outlier checks (config/
        | phone_pricing.php) are what actually guard against a bad fetch,
        | same as for every other source.
        */

        'star_tech' => [
            'class' => StarTechSource::class,
            'name' => 'Star Tech (Bangladesh)',
            'type' => 'bd_retailer',
            'enabled' => true,
            'reliability_score' => 85,
            'requires_review' => false,
            'config' => [],
        ],

        'dazzle' => [
            'class' => DazzleSource::class,
            'name' => 'Dazzle (Bangladesh)',
            'type' => 'bd_retailer',
            'enabled' => true,
            'reliability_score' => 80,
            'requires_review' => false,
            'config' => [],
        ],

        'sumash_tech' => [
            'class' => SumashTechSource::class,
            'name' => 'Sumash Tech (Bangladesh)',
            'type' => 'bd_retailer',
            'enabled' => true,
            'reliability_score' => 80,
            'requires_review' => false,
            'config' => [],
        ],

        'rio_international' => [
            'class' => RioInternationalSource::class,
            'name' => 'Rio International (Bangladesh)',
            'type' => 'bd_retailer',
            'enabled' => true,
            'reliability_score' => 75,
            'requires_review' => false,
            'config' => [],
        ],

        'apple_gadgets' => [
            'class' => AppleGadgetsSource::class,
            'name' => 'Apple Gadgets BD',
            'type' => 'bd_retailer',
            'enabled' => true,
            'reliability_score' => 75,
            'requires_review' => false,
            'config' => [],
        ],

    ],

];
