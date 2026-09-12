<?php

namespace App\Services\PhoneImport\Sources;

use App\Enums\SourceTypeEnum;

/**
 * Base for adapters targeting Bangladesh retailers/marketplaces
 * (e.g. an official product feed or permitted API from a local
 * electronics retailer). Primarily used to collect Bangladesh price
 * and availability data - the pipeline treats these as strong
 * evidence for the BANGLADESH fields but not for global hardware
 * specs.
 */
abstract class BangladeshRetailerSource extends RetailerSource
{
    public function type(): SourceTypeEnum
    {
        return SourceTypeEnum::BD_RETAILER;
    }
}
