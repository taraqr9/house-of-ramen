<?php

namespace App\Services\PhoneImport\Sources\Retailers;

use App\Enums\PriceTypeEnum;
use App\Models\Phone;
use App\Services\PhoneImport\Normalization\SpecNormalizer;
use Illuminate\Support\Str;

/**
 * startech.com.bd publishes schema.org Product data as inline
 * microdata (itemprop attributes) rather than a JSON-LD block - same
 * underlying vocabulary as App\Services\PhoneImport\Sources\Retailers\
 * JsonLdRetailerSource's sources, different on-page encoding, so it
 * needs its own extraction rather than sharing that base class.
 */
class StarTechSource extends RetailerListingSource
{
    protected function retailerName(): string
    {
        return 'Star Tech';
    }

    protected function retailerKey(): string
    {
        return 'star_tech';
    }

    protected function productUrl(string $slug): string
    {
        return "https://www.startech.com.bd/{$slug}";
    }

    protected function defaultPriceType(): PriceTypeEnum
    {
        // Star Tech is an authorized retailer (config('phone_pricing.
        // known_retailer_types')) with one listing per phone - no
        // separate grey-import channel to distinguish.
        return PriceTypeEnum::OFFICIAL_BD;
    }

    /**
     * Verified 2026-08-26: Star Tech's Galaxy S24 Ultra 12/256GB lives at
     * "samsung-galaxy-s24-ultra" while its 12/512GB is a separate
     * "samsung-galaxy-s24-ultra-12-512gb" - a non-default storage tier
     * gets its own "-{ram}-{storage}gb" suffixed slug. Tried for every
     * variant (not just non-default ones) so this stays phone-agnostic
     * rather than needing to know which storage Star Tech treats as the
     * "base" one.
     */
    protected function perVariantSlugs(Phone $phone): array
    {
        // Same brand-duplicate-aware primary + opposite-form fallback as
        // RetailerListingSource::buildCandidateUrls() - see its comment.
        $baseSlugs = array_unique([
            SpecNormalizer::phoneSlug($phone->brand->name, $phone->name),
            str_starts_with(Str::lower($phone->name), Str::lower($phone->brand->name))
                ? SpecNormalizer::slug($phone->brand->name, $phone->name)
                : SpecNormalizer::slug($phone->name),
        ]);

        $slugs = [];

        foreach ($phone->variants->where('is_active', true) as $variant) {
            if (! $variant->ram_gb || ! $variant->storage_gb) {
                continue;
            }

            foreach ($baseSlugs as $base) {
                $slugs[] = "{$base}-{$variant->ram_gb}-{$variant->storage_gb}gb";
            }
        }

        return $slugs;
    }

    protected function parseListingPage(string $html): ?array
    {
        if (! preg_match('/itemprop="price"\s+content="([\d.]+)"/', $html, $priceMatch)) {
            return null;
        }

        $amount = SpecNormalizer::amount($priceMatch[1]);

        // Star Tech renders content="0.0000" for a variant it has
        // delisted/never stocked (verified 2026-08-26 on the 512GB page)
        // rather than omitting the meta tag - PriceAggregator::isSane()
        // would reject it anyway via the absolute-minimum floor, but
        // failing fast here means it's never even attempted.
        if ($amount === null || $amount <= 0) {
            return null;
        }

        $availability = null;

        if (preg_match('/itemprop="availability"\s+href="[^"]*schema\.org\/(\w+)"/i', $html, $availMatch)) {
            $availability = $this->mapSchemaAvailability($availMatch[1]);
        }

        $name = null;

        if (preg_match('/itemprop="name"\s+class="product-name">([^<]+)</', $html, $nameMatch)) {
            $name = html_entity_decode($nameMatch[1]);
        }

        return ['amount' => $amount, 'availability' => $availability, 'name' => $name];
    }
}
