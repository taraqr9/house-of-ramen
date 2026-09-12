<?php

namespace App\Services\PhoneImport\Sources;

use App\Enums\SourceTypeEnum;

/**
 * Base for adapters that pull from a retailer's public product feed
 * or API where reuse is permitted (product listings, pricing,
 * availability). Not for scraping HTML in a way that violates a
 * site's terms of service or robots rules - prefer an official feed
 * or API wherever one exists.
 */
abstract class RetailerSource extends AbstractPhoneSource
{
    public function type(): SourceTypeEnum
    {
        return SourceTypeEnum::GLOBAL_RETAILER;
    }
}
