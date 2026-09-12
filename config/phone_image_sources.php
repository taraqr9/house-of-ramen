<?php

use App\Services\PhoneImage\Sources\OpenverseImageSource;
use App\Services\PhoneImage\Sources\WikimediaCommonsImageSource;

/*
|--------------------------------------------------------------------------
| Phone Image Source Registry
|--------------------------------------------------------------------------
|
| Mirrors config/phone_sources.php: each entry is a pluggable image
| source adapter. PhoneImageCollector asks every enabled provider for
| its ranked candidates, merges them into one list ordered by
| confidence ACROSS sources, and stores the best one that actually
| downloads - so a weak match from one source never blocks a stronger
| match from another. Adding a source is purely: implement
| PhoneImageSourceProvider (see App\Services\PhoneImage\Support\
| MatchesPhoneImageText for the shared matching logic every adapter
| should reuse) and register it here - no changes needed to the
| collector or the command.
|
*/

return [

    'sources' => [

        'wikimedia_commons' => [
            'class' => WikimediaCommonsImageSource::class,
            // Matches the phone_sources.key row created for provenance -
            // see config/phone_sources.php.
            'phone_source_key' => 'wikimedia_commons',
            'enabled' => true,
            'config' => [
                'user_agent' => 'PhoneKinboCatalogueBot/1.0 (catalogue image collection; contact: developer@bol-online.com)',
                // Below this, a match is stored but marked needs_review
                // (excluded from public display) rather than verified.
                'confidence_threshold' => 65,
            ],
        ],

        // Aggregates openly-licensed media (CC-licensed / public domain)
        // from many providers - Wikimedia Commons, Flickr's CC-licensed
        // subset, museum/cultural collections, etc. - each with explicit
        // license metadata via its public API. Complements Wikimedia
        // Commons rather than duplicating it: a very recent phone with
        // no dedicated Commons upload yet may still have a CC-licensed
        // photo Openverse has indexed from elsewhere. Same confidence
        // threshold and matching discipline as every other source - nothing
        // is trusted just because Openverse found it.
        'openverse' => [
            'class' => OpenverseImageSource::class,
            'phone_source_key' => 'openverse',
            'enabled' => true,
            'config' => [
                'user_agent' => 'PhoneKinboCatalogueBot/1.0 (catalogue image collection; contact: developer@bol-online.com)',
                'confidence_threshold' => 65,
            ],
        ],

    ],

];
