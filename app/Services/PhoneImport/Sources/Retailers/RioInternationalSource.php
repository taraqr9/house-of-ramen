<?php

namespace App\Services\PhoneImport\Sources\Retailers;

use App\Enums\PriceTypeEnum;

class RioInternationalSource extends JsonLdRetailerSource
{
    protected function retailerName(): string
    {
        return 'Rio International';
    }

    protected function retailerKey(): string
    {
        return 'rio_international';
    }

    protected function productUrl(string $slug): string
    {
        return "https://riointernational.com.bd/product/{$slug}";
    }

    protected function defaultPriceType(): PriceTypeEnum
    {
        return PriceTypeEnum::UNOFFICIAL_BD;
    }

    protected function supportsOfficialSuffix(): bool
    {
        return true;
    }

    /**
     * Verified 2026-08-26: riointernational.com.bd's real "Available
     * Stock: N" badge is populated by client-side JS and never appears in
     * the static HTML this adapter fetches, while the JSON-LD
     * Offer.availability field was observed hardcoded to "InStock"
     * regardless of actual stock. Neither signal is trustworthy here, so
     * availability is deliberately left unknown (null) rather than
     * asserting a status this adapter cannot actually verify - price is
     * still recorded, since the JSON-LD price field does track the real
     * page price.
     */
    protected function resolveAvailability(string $html, ?string $schemaAvailability): ?string
    {
        return null;
    }
}
