<?php

namespace App\Services\PhoneImport\Sources\Retailers;

use App\Services\PhoneImport\Normalization\SpecNormalizer;

/**
 * Shared parser for the several Bangladesh retailers (Dazzle, Rio
 * International, Sumash Tech) that publish a standard schema.org
 * Product/Offer JSON-LD block on their product pages - the same
 * structured data these sites publish for Google Rich Results/Merchant
 * listings, so parsing it is reading a feed the retailer already
 * intends for automated consumption, not scraping presentation markup.
 */
abstract class JsonLdRetailerSource extends RetailerListingSource
{
    protected function parseListingPage(string $html): ?array
    {
        $product = $this->extractJsonLdProduct($html);

        if ($product === null) {
            return null;
        }

        $offer = $product['offers'] ?? null;
        $amount = is_array($offer) ? SpecNormalizer::amount($offer['price'] ?? null) : null;

        // A JSON-LD Offer.price of exactly 0 is the same "delisted/never
        // priced" convention verified on Star Tech's microdata (see
        // StarTechSource::parseListingPage()) - treated as no listing
        // here too, rather than as a real ৳0 price for
        // PriceAggregator::isSane() to catch downstream. Both paths were
        // already safe (isSane() rejects it either way), but catching it
        // here means the discovery attempt reports NO_CANDIDATE/ambiguous
        // instead of leaving a phantom MATCHED attempt with no actual
        // price behind it.
        if ($amount === null || $amount <= 0) {
            return null;
        }

        return [
            'amount' => $amount,
            'availability' => $this->resolveAvailability($html, is_array($offer) ? ($offer['availability'] ?? null) : null),
            'name' => $product['name'] ?? null,
        ];
    }

    /**
     * Hook for a subclass to override when this retailer's JSON-LD
     * availability field is known to be unreliable (see e.g.
     * DazzleSource, which has been observed publishing "InStock" in
     * JSON-LD while the same page's visible stock banner says otherwise,
     * and RioInternationalSource, whose real stock count loads via
     * client-side JS the static HTML never contains at all).
     */
    protected function resolveAvailability(string $html, ?string $schemaAvailability): ?string
    {
        return $this->mapSchemaAvailability($schemaAvailability);
    }

    /**
     * @return array<string, mixed>|null the first JSON-LD block whose
     *
     * @type is "Product" and which carries an offers block - a page can (and
     *          these do) also publish an unrelated WebPage/BreadcrumbList block.
     */
    protected function extractJsonLdProduct(string $html): ?array
    {
        if (! preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $html, $matches)) {
            return null;
        }

        foreach ($matches[1] as $json) {
            $decoded = json_decode(trim($json), true);

            if (is_array($decoded) && ($decoded['@type'] ?? null) === 'Product' && isset($decoded['offers'])) {
                return $decoded;
            }
        }

        return null;
    }
}
