<?php

namespace App\Services\PhoneImport\Sources\Retailers;

use App\Enums\PriceTypeEnum;

class SumashTechSource extends JsonLdRetailerSource
{
    protected function retailerName(): string
    {
        return 'Sumash Tech';
    }

    protected function retailerKey(): string
    {
        return 'sumash_tech';
    }

    protected function productUrl(string $slug): string
    {
        return "https://www.sumashtech.com/product/{$slug}";
    }

    protected function defaultPriceType(): PriceTypeEnum
    {
        return PriceTypeEnum::UNOFFICIAL_BD;
    }

    protected function supportsOfficialSuffix(): bool
    {
        return true;
    }
}
