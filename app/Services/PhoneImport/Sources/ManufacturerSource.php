<?php

namespace App\Services\PhoneImport\Sources;

use App\Enums\SourceTypeEnum;

/**
 * Base for adapters that pull from a manufacturer's own public
 * specification pages/feeds/APIs (e.g. an official Samsung/Xiaomi
 * newsroom or spec feed). These are the highest-trust sources.
 *
 * Concrete subclasses must still verify the manufacturer's terms of
 * service / robots rules for the specific endpoint they use before
 * making requests.
 */
abstract class ManufacturerSource extends AbstractPhoneSource
{
    public function type(): SourceTypeEnum
    {
        return SourceTypeEnum::MANUFACTURER;
    }
}
