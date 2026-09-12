<?php

namespace App\Services\PhoneImport\Sources\Retailers;

use App\Enums\PriceTypeEnum;

class DazzleSource extends JsonLdRetailerSource
{
    protected function retailerName(): string
    {
        return 'Dazzle';
    }

    protected function retailerKey(): string
    {
        return 'dazzle';
    }

    protected function productUrl(string $slug): string
    {
        return "https://dazzle.com.bd/product/{$slug}";
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
     * Verified 2026-08-26 against Dazzle's real product sitemap: ~11%
     * of its ~3,300 product URLs end in "-price-in-bangladesh" (e.g.
     * "oppo-reno15-price-in-bangladesh", "vivo-v60e-price-in-
     * bangladesh") - an SEO slug convention not used for every listing,
     * but common enough to be worth trying as an extra candidate.
     */
    protected function additionalSuffixes(): array
    {
        return ['-price-in-bangladesh'];
    }

    /**
     * Verified 2026-08-26: dazzle.com.bd's JSON-LD Offer.availability has
     * been observed stuck at "InStock" for a listing whose own visible
     * page clearly renders a "Out of Stock" banner - so the static
     * banner text is the more trustworthy signal here and takes priority
     * whenever it's present.
     */
    protected function resolveAvailability(string $html, ?string $schemaAvailability): ?string
    {
        if (str_contains($html, '>Out of Stock<')) {
            return 'out_of_stock';
        }

        return parent::resolveAvailability($html, $schemaAvailability);
    }
}
