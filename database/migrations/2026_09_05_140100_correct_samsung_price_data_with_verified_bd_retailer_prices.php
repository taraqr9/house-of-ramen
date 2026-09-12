<?php

use App\Enums\PriceTypeEnum;
use App\Models\PhonePrice;
use App\Models\PhonePriceHistory;
use App\Models\PhoneSource;
use App\Models\PhoneStore;
use App\Models\PhoneVariant;
use App\Services\PhoneImport\PriceAggregator;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;

/**
 * Data-correction migration, not a schema change. Companion to
 * 2026_09_05_140000_correct_apple_price_data_with_verified_bd_retailer_prices
 * - same root cause, found across the Samsung catalogue during the same
 * audit: several flagships/foldables carried only a single, stale,
 * official-only (or generic-unattributed) price with no real unofficial
 * price on record, so the catalogue's "cheapest current price" sort
 * compared a newer phone's genuine unofficial price against an older
 * phone's stale official-only price - never apples to apples. Four
 * phones (Galaxy Z Fold7, Z Flip7, M17, M35 5G) had NO price at all
 * despite being active catalogue entries.
 *
 * Every amount below was verified live against the named Bangladesh
 * retailers on 2026-09-05 (Rio International, Dazzle, Sumash Tech,
 * Gadget & Gear) - see phone_prices.source_url per row for the exact
 * product page. No price is invented; every observation feeds the
 * existing outlier-resistant PriceAggregator. phone_variants itself is
 * untouched. Galaxy S22 Ultra was checked (Dazzle only had a smaller
 * 8/128GB config in stock, not a match for this catalogue's 12/256GB
 * variant) and deliberately left uncorrected - no verified apples-to-
 * apples current price was found, so its existing single-source price
 * stays as-is rather than being guessed at. Galaxy M35 5G's 8/256GB
 * variant is left unpriced for the same reason.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = Carbon::now();
        $aggregator = app(PriceAggregator::class);

        // Looked up, never created here - see the sibling Apple
        // migration's comment for why (avoids racing a test's own
        // factory-created PhoneSource of the same unique key).
        $manualResearch = PhoneSource::where('key', 'manual_research')->first();

        $rio = PhoneStore::where('slug', 'rio-international')->first();
        $dazzle = PhoneStore::where('slug', 'dazzle')->first();
        $sumashTech = PhoneStore::where('slug', 'sumash-tech')->first();

        if (! $manualResearch || ! $rio || ! $dazzle || ! $sumashTech) {
            return;
        }

        // Idempotent: reuses the store this migration's Apple sibling may
        // have already created (it runs first, timestamp-ordered).
        $gadgetAndGearStore = PhoneStore::firstOrCreate(
            ['slug' => 'gadget-and-gear'],
            ['name' => 'Gadget & Gear', 'is_active' => true]
        );

        $s25UltraVariant256 = PhoneVariant::find(415);
        $s25UltraVariant512 = PhoneVariant::find(416);
        $zFlip5Variant = PhoneVariant::find(421);
        $zFold7Variant256 = PhoneVariant::find(426);
        $zFold7Variant512 = PhoneVariant::find(427);
        $zFlip7Variant256 = PhoneVariant::find(423);
        $zFlip7Variant512 = PhoneVariant::find(424);
        $m17Variant = PhoneVariant::find(393);
        $m35Variant128 = PhoneVariant::find(397);

        if (! $s25UltraVariant256 || ! $zFold7Variant256 || ! $zFlip7Variant256 || ! $m17Variant || ! $m35Variant128) {
            return;
        }

        // ---- Galaxy S25 Ultra: same split-identity bug as iPhone 15 Pro
        // Max - the 256GB tier had only a single-source official price
        // and NO unofficial price despite real, currently-listed
        // unofficial stock; the 512GB tier's only price (198,000,
        // generic "Local Market") was ~27% above every real quote found. ----
        $this->reverify($s25UltraVariant256, 1, 'official_bd', $now);
        $this->upsert($manualResearch, $s25UltraVariant256, $gadgetAndGearStore, PriceTypeEnum::OFFICIAL_BD, 226999.00,
            'https://gadgetandgear.com/product/samsung-galaxy-s25-ultra', 'Authorized reseller warranty', $now);
        $this->upsert($manualResearch, $s25UltraVariant256, $rio, PriceTypeEnum::UNOFFICIAL_BD, 112999.00,
            'https://riointernational.com.bd/product/samsung-galaxy-s25-ultra', 'Brand New (Unofficial)', $now);
        $aggregator->recalculate($s25UltraVariant256, PriceTypeEnum::OFFICIAL_BD);
        $aggregator->recalculate($s25UltraVariant256, PriceTypeEnum::UNOFFICIAL_BD);

        if ($s25UltraVariant512) {
            $this->correct($s25UltraVariant512, 5, 'unofficial_bd', $rio, 155999.00,
                'https://riointernational.com.bd/product/samsung-galaxy-s25-ultra', 'Brand New (Unofficial)', $manualResearch, $now);
            $aggregator->recalculate($s25UltraVariant512, PriceTypeEnum::UNOFFICIAL_BD);
        }

        // ---- Galaxy Z Flip5: 2-year-old model with only its stale
        // official launch price on record; a real, currently-listed
        // unofficial price was missing entirely. ----
        $this->reverify($zFlip5Variant, 1, 'official_bd', $now);
        $this->upsert($manualResearch, $zFlip5Variant, $dazzle, PriceTypeEnum::UNOFFICIAL_BD, 72990.00,
            'https://dazzle.com.bd/product/samsung-galaxy-z-flip-5-5g', null, $now);
        $aggregator->recalculate($zFlip5Variant, PriceTypeEnum::UNOFFICIAL_BD);

        // ---- Galaxy Z Fold7: active catalogue phone with NO price at
        // all on either variant. ----
        $this->upsert($manualResearch, $zFold7Variant256, $dazzle, PriceTypeEnum::OFFICIAL_BD, 154990.00,
            'https://dazzle.com.bd/product/samsung-galaxy-z-fold-7', 'Official, 1 Year Warranty', $now);
        $this->upsert($manualResearch, $zFold7Variant256, $sumashTech, PriceTypeEnum::OFFICIAL_BD, 154999.00,
            'https://www.sumashtech.com/product/galaxy-z-fold-7', 'Samsung Store warranty', $now);
        $this->upsert($manualResearch, $zFold7Variant256, $rio, PriceTypeEnum::UNOFFICIAL_BD, 150999.00,
            'https://riointernational.com.bd/product/samsung-galaxy-z-fold7', 'Brand New (Unofficial)', $now);
        $aggregator->recalculate($zFold7Variant256, PriceTypeEnum::OFFICIAL_BD);
        $aggregator->recalculate($zFold7Variant256, PriceTypeEnum::UNOFFICIAL_BD);

        if ($zFold7Variant512) {
            $this->upsert($manualResearch, $zFold7Variant512, $rio, PriceTypeEnum::UNOFFICIAL_BD, 175999.00,
                'https://riointernational.com.bd/product/samsung-galaxy-z-fold7', 'Brand New (Unofficial)', $now);
            $aggregator->recalculate($zFold7Variant512, PriceTypeEnum::UNOFFICIAL_BD);
        }

        // ---- Galaxy Z Flip7: same - active catalogue phone, no price
        // at all on either variant. ----
        $this->upsert($manualResearch, $zFlip7Variant256, $dazzle, PriceTypeEnum::OFFICIAL_BD, 99990.00,
            'https://dazzle.com.bd/product/samsung-galaxy-z-flip7', 'Official, 1 Year Warranty', $now);
        $this->upsert($manualResearch, $zFlip7Variant256, $rio, PriceTypeEnum::UNOFFICIAL_BD, 97999.00,
            'https://riointernational.com.bd/product/samsung-galaxy-z-flip7', 'Brand New (Unofficial)', $now);
        $this->upsert($manualResearch, $zFlip7Variant256, $sumashTech, PriceTypeEnum::UNOFFICIAL_BD, 97999.00,
            'https://www.sumashtech.com/product/galaxy-z-flip-7', null, $now);
        $aggregator->recalculate($zFlip7Variant256, PriceTypeEnum::OFFICIAL_BD);
        $aggregator->recalculate($zFlip7Variant256, PriceTypeEnum::UNOFFICIAL_BD);

        if ($zFlip7Variant512) {
            $this->upsert($manualResearch, $zFlip7Variant512, $rio, PriceTypeEnum::UNOFFICIAL_BD, 107999.00,
                'https://riointernational.com.bd/product/samsung-galaxy-z-flip7', 'Brand New (Unofficial)', $now);
            $aggregator->recalculate($zFlip7Variant512, PriceTypeEnum::UNOFFICIAL_BD);
        }

        // ---- Galaxy M17 and M35 5G: active catalogue phones with no
        // price at all. Only the storage tier with a verified, exact
        // per-tier match is priced; other tiers are left unresolved. ----
        $this->upsert($manualResearch, $m17Variant, $rio, PriceTypeEnum::UNOFFICIAL_BD, 22499.00,
            'https://riointernational.com.bd/product/samsung-galaxy-m17-5g', 'Brand New (Unofficial)', $now);
        $aggregator->recalculate($m17Variant, PriceTypeEnum::UNOFFICIAL_BD);

        $this->upsert($manualResearch, $m35Variant128, $rio, PriceTypeEnum::UNOFFICIAL_BD, 22499.00,
            'https://riointernational.com.bd/product/samsung-galaxy-m35-5g', 'Brand New (Unofficial)', $now);
        $aggregator->recalculate($m35Variant128, PriceTypeEnum::UNOFFICIAL_BD);
    }

    public function down(): void
    {
        // Deliberately irreversible - see the sibling Apple migration's
        // down() for why.
    }

    protected function reverify(PhoneVariant $variant, int $storeId, string $priceType, Carbon $now): void
    {
        PhonePrice::where('phone_variant_id', $variant->id)
            ->where('store_id', $storeId)
            ->where('price_type', $priceType)
            ->first()
            ?->update(['last_verified_at' => $now, 'collected_at' => $now]);
    }

    protected function upsert(
        PhoneSource $source,
        PhoneVariant $variant,
        PhoneStore $store,
        PriceTypeEnum $type,
        float $amount,
        string $sourceUrl,
        ?string $warrantyType,
        Carbon $now
    ): void {
        $existing = PhonePrice::where('phone_variant_id', $variant->id)
            ->where('store_id', $store->id)
            ->where('price_type', $type->value)
            ->first();

        if ($existing === null || (float) $existing->amount !== $amount) {
            PhonePriceHistory::create([
                'phone_variant_id' => $variant->id,
                'store_id' => $store->id,
                'price_type' => $type->value,
                'amount' => $amount,
                'previous_amount' => $existing?->amount,
                'source_id' => $source->id,
                'changed_at' => $now,
            ]);
        }

        PhonePrice::updateOrCreate(
            ['phone_variant_id' => $variant->id, 'store_id' => $store->id, 'price_type' => $type->value],
            [
                'amount' => $amount,
                'currency' => 'BDT',
                'source_url' => $sourceUrl,
                'warranty_type' => $warrantyType,
                'source_id' => $source->id,
                'confidence' => 90,
                'collected_at' => $now,
                'last_verified_at' => $now,
                'is_active' => true,
            ]
        );
    }

    protected function correct(
        PhoneVariant $variant,
        int $fromStoreId,
        string $priceType,
        PhoneStore $toStore,
        float $newAmount,
        string $sourceUrl,
        ?string $warrantyType,
        PhoneSource $source,
        Carbon $now
    ): void {
        $row = PhonePrice::where('phone_variant_id', $variant->id)
            ->where('store_id', $fromStoreId)
            ->where('price_type', $priceType)
            ->first();

        if (! $row) {
            return;
        }

        PhonePriceHistory::create([
            'phone_variant_id' => $variant->id,
            'store_id' => $toStore->id,
            'price_type' => $priceType,
            'amount' => $newAmount,
            'previous_amount' => $row->amount,
            'source_id' => $source->id,
            'changed_at' => $now,
        ]);

        $row->update([
            'store_id' => $toStore->id,
            'amount' => $newAmount,
            'source_url' => $sourceUrl,
            'warranty_type' => $warrantyType,
            'source_id' => $source->id,
            'confidence' => 90,
            'collected_at' => $now,
            'last_verified_at' => $now,
        ]);
    }
};
