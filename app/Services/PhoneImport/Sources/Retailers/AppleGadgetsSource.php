<?php

namespace App\Services\PhoneImport\Sources\Retailers;

use App\Enums\PriceTypeEnum;
use App\Services\PhoneImport\Normalization\SpecNormalizer;

/**
 * applegadgetsbd.com publishes neither JSON-LD nor microdata product
 * data (verified 2026-08-26 on its Galaxy S24 Ultra 5G page - that
 * listing's price was "TBA"/pre-order with no priced offer at all, so
 * there was nothing to reverse-engineer a real price selector against
 * yet). This adapter is registered so the retailer shows up in the Data
 * Sources admin screen and is ready to extend, but its parser
 * deliberately returns null - "cannot reliably extract a price" - for
 * every listing rather than guess at a CSS pattern from a single
 * unpriced page. Extend parseListingPage() once a live-priced product
 * page's actual markup has been inspected.
 */
class AppleGadgetsSource extends RetailerListingSource
{
    protected function retailerName(): string
    {
        return 'Apple Gadgets BD';
    }

    protected function retailerKey(): string
    {
        return 'apple_gadgets';
    }

    protected function productUrl(string $slug): string
    {
        return "https://www.applegadgetsbd.com/product/{$slug}";
    }

    protected function defaultPriceType(): PriceTypeEnum
    {
        // Unverified convention - no listing has been confirmed either
        // way yet (see class docblock). Kept conservative pending real
        // data; correct once a live-priced product page is inspected.
        return PriceTypeEnum::UNOFFICIAL_BD;
    }

    protected function parseListingPage(string $html): ?array
    {
        // Cheap Product/Offer JSON-LD check in case a different product
        // page on this same site does publish it, even though the one
        // page this adapter was verified against did not.
        if (preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $html, $matches)) {
            foreach ($matches[1] as $json) {
                $decoded = json_decode(trim($json), true);

                if (is_array($decoded) && ($decoded['@type'] ?? null) === 'Product' && isset($decoded['offers']['price'])) {
                    $amount = SpecNormalizer::amount($decoded['offers']['price']);

                    if ($amount !== null && $amount > 0) {
                        return [
                            'amount' => $amount,
                            'availability' => $this->mapSchemaAvailability($decoded['offers']['availability'] ?? null),
                            'name' => $decoded['name'] ?? null,
                        ];
                    }
                }
            }
        }

        return null;
    }
}
