<?php

namespace App\Services\Presentation;

use App\Enums\PhoneRegionEnum;
use App\Models\PhoneMarketPrice;
use App\Models\PhoneVariant;
use Illuminate\Support\Collection;

/**
 * Single source of truth for "which variant(s) represent this phone's
 * Global version vs. its Chinese version" - used by the phone detail
 * page, the comparison page, and the recommendation engine's candidate
 * filter so all three group/select variants by the same rule (see
 * App\Enums\PhoneRegionEnum) instead of three separate reimplementations
 * silently drifting apart over time.
 */
class PhoneVariantRegionSelector
{
    /**
     * Splits a phone's variants into [global, chinese] per
     * PhoneRegionEnum::resolve(). Order within each group is preserved.
     *
     * @param  Collection<int, PhoneVariant>  $variants
     * @return array{0: Collection<int, PhoneVariant>, 1: Collection<int, PhoneVariant>}
     */
    public static function partition(Collection $variants): array
    {
        $global = $variants->filter(
            fn (PhoneVariant $variant) => PhoneRegionEnum::resolve($variant->region) === PhoneRegionEnum::GLOBAL
        )->values();

        $chinese = $variants->filter(
            fn (PhoneVariant $variant) => PhoneRegionEnum::resolve($variant->region) === PhoneRegionEnum::CHINESE
        )->values();

        return [$global, $chinese];
    }

    /**
     * The single variant the public site shows when it needs exactly one
     * (detail-page hero price, comparison table): the cheapest current
     * market price among Global variants when at least one exists,
     * otherwise the cheapest among Chinese variants. A phone with any
     * Global variant never falls through to a Chinese one here, no
     * matter how much cheaper the Chinese sibling is - "prefer Global,
     * Chinese only as a fallback" is a hard rule, not a price-driven one.
     *
     * @param  Collection<int, PhoneVariant>  $variants  must have marketPrices eager-loaded.
     */
    public static function primary(Collection $variants): ?PhoneVariant
    {
        [$global, $chinese] = self::partition($variants);

        return self::cheapest($global) ?? self::cheapest($chinese);
    }

    /**
     * All of a phone's variants grouped by user-facing region, Global
     * first when both exist, containing only the regions the phone
     * actually has at least one variant for (never an empty "Chinese"
     * group tacked on for a Global-only phone).
     *
     * @param  Collection<int, PhoneVariant>  $variants
     * @return Collection<string, Collection<int, PhoneVariant>> keyed by PhoneRegionEnum::value
     */
    public static function grouped(Collection $variants): Collection
    {
        $byRegion = $variants->groupBy(
            fn (PhoneVariant $variant) => PhoneRegionEnum::resolve($variant->region)->value
        );

        return collect([PhoneRegionEnum::GLOBAL->value, PhoneRegionEnum::CHINESE->value])
            ->filter(fn (string $key) => $byRegion->has($key))
            ->mapWithKeys(fn (string $key) => [$key => $byRegion->get($key)]);
    }

    /**
     * The single cheapest current market price (any price type) within
     * the preferred region for this phone (Global, else Chinese) - the
     * "price + type + region" triple the catalogue card shows. Requires
     * variants.marketPrices to be eager-loaded; returns null when the
     * phone has no current market price in either region.
     *
     * @param  Collection<int, PhoneVariant>  $variants
     * @return array{market_price: PhoneMarketPrice, region: PhoneRegionEnum}|null
     */
    public static function displayPrice(Collection $variants): ?array
    {
        [$global, $chinese] = self::partition($variants);

        if ($marketPrice = self::cheapestMarketPrice($global)) {
            return ['market_price' => $marketPrice, 'region' => PhoneRegionEnum::GLOBAL];
        }

        if ($marketPrice = self::cheapestMarketPrice($chinese)) {
            return ['market_price' => $marketPrice, 'region' => PhoneRegionEnum::CHINESE];
        }

        return null;
    }

    /**
     * @param  Collection<int, PhoneVariant>  $variants
     */
    protected static function cheapest(Collection $variants): ?PhoneVariant
    {
        if ($variants->isEmpty()) {
            return null;
        }

        // Same "cheapest current market price, official or unofficial"
        // rule the phone detail page and comparison page have always
        // used - a variant with no market price at all sorts last, never
        // treated as "free"/cheapest by an absent value.
        return $variants->sortBy(fn (PhoneVariant $variant) => $variant->marketPrices->min('price') ?? PHP_INT_MAX)->first();
    }

    /**
     * @param  Collection<int, PhoneVariant>  $variants
     */
    protected static function cheapestMarketPrice(Collection $variants): ?PhoneMarketPrice
    {
        return $variants
            ->flatMap(fn (PhoneVariant $variant) => $variant->marketPrices)
            ->sortBy(fn (PhoneMarketPrice $marketPrice) => (float) $marketPrice->price)
            ->first();
    }
}
