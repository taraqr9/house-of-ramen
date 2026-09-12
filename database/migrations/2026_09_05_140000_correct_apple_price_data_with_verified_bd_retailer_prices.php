<?php

use App\Enums\PriceTypeEnum;
use App\Enums\SourceTypeEnum;
use App\Models\PhonePrice;
use App\Models\PhonePriceHistory;
use App\Models\PhoneSource;
use App\Models\PhoneStore;
use App\Models\PhoneVariant;
use App\Services\PhoneImport\PriceAggregator;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;

/**
 * Data-correction migration, not a schema change. Fixes the catalogue
 * sorting bug reported for /phones?brand=apple&sort=price_desc: the
 * iPhone 15 Pro Max (a stale, single-retailer 179,900 launch-era
 * official price with no unofficial price on record) was outranking the
 * iPhone 17 Pro Max under "Price: high to low", because a 2-year-old
 * discontinued flagship's only recorded price was compared directly
 * against a brand-new flagship's genuinely cheaper unofficial import
 * price - never an apples-to-apples comparison.
 *
 * Every amount below was verified live against the named Bangladesh
 * retailers on 2026-09-05 (Rio International, Dazzle, Sumash Tech,
 * Apple Gadgets, Gadget & Gear) - see the price-audit conversation for
 * the exact product URLs, now also recorded per-row in
 * phone_prices.source_url. No price is invented: every added
 * observation is fed into the existing outlier-resistant
 * PriceAggregator exactly the way an admin's manual correction or the
 * import pipeline would, so phone_market_prices ends up with an honest,
 * multi-retailer-supported figure rather than a single overridden
 * number. phone_variants itself (RAM/storage/region/etc.) is untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = Carbon::now();
        $aggregator = app(PriceAggregator::class);

        // Looked up, never created here: 'manual_research' is a source
        // already seeded as part of the base catalogue dataset (see
        // database/seed-data). A minimal/test database that never seeded
        // that dataset legitimately has neither it nor the phones/
        // variants this migration corrects - the guard below skips
        // cleanly in that case rather than racing a test's own factory-
        // created PhoneSource of the same key (phone_sources.key is
        // unique).
        $manualResearch = PhoneSource::where('key', 'manual_research')->first();

        $rio = PhoneStore::where('slug', 'rio-international')->first();
        $dazzle = PhoneStore::where('slug', 'dazzle')->first();
        $sumashTech = PhoneStore::where('slug', 'sumash-tech')->first();

        if (! $manualResearch || ! $rio || ! $dazzle || ! $sumashTech) {
            return;
        }

        $gadgetAndGearStore = PhoneStore::firstOrCreate(
            ['slug' => 'gadget-and-gear'],
            ['name' => 'Gadget & Gear', 'is_active' => true]
        );
        PhoneSource::firstOrCreate(
            ['key' => 'gadget_and_gear'],
            ['name' => 'Gadget & Gear (Bangladesh)', 'type' => SourceTypeEnum::BD_RETAILER->value, 'reliability_score' => 78, 'is_active' => true]
        );

        $variant15ProMax256 = PhoneVariant::find(10); // iPhone 15 Pro Max, 8/256GB
        $variant15ProMax512 = PhoneVariant::find(11); // iPhone 15 Pro Max, 8/512GB
        $variant17Pro = PhoneVariant::find(19);        // iPhone 17 Pro, 12/256GB
        $variantAir = PhoneVariant::find(21);          // iPhone Air, 12/256GB

        if (! $variant15ProMax256 || ! $variant15ProMax512 || ! $variant17Pro || ! $variantAir) {
            return;
        }

        // ---- iPhone 15 Pro Max: the actual reported bug ----
        // 256GB variant kept only a stale, single-source official price
        // (iStock BD, 179,900) with no unofficial price at all, even
        // though real, currently-listed unofficial stock exists.
        $this->reverify($variant15ProMax256, PriceTypeEnum::OFFICIAL_BD, 10, $now);

        $this->upsert($manualResearch, $variant15ProMax256, $sumashTech, PriceTypeEnum::OFFICIAL_BD, 240000.00,
            'https://www.sumashtech.com/product/iphone-15-pro-max-official', 'Apple official Bangladesh warranty', $now);
        $this->upsert($manualResearch, $variant15ProMax256, $gadgetAndGearStore, PriceTypeEnum::OFFICIAL_BD, 204999.00,
            'https://gadgetandgear.com/product/iphone-15-pro-max', 'Apple official Bangladesh & international warranty', $now);
        $this->upsert($manualResearch, $variant15ProMax256, $rio, PriceTypeEnum::UNOFFICIAL_BD, 134999.00,
            'https://riointernational.com.bd/product/iphone-15-pro-max', 'Brand New (Unofficial)', $now);
        $this->upsert($manualResearch, $variant15ProMax256, $dazzle, PriceTypeEnum::UNOFFICIAL_BD, 131990.00,
            'https://dazzle.com.bd/product/apple-iphone-15-pro-max', null, $now);

        $aggregator->recalculate($variant15ProMax256, PriceTypeEnum::OFFICIAL_BD);
        $aggregator->recalculate($variant15ProMax256, PriceTypeEnum::UNOFFICIAL_BD);

        // 512GB variant's only price (189,000, generic "Local Market"
        // source) was ~18% above every real current quote found for this
        // exact storage tier - correct it and re-attribute to the real,
        // named retailer it actually matches (Rio International).
        $this->correct($variant15ProMax512, 5, 'unofficial_bd', $rio, 160999.00,
            'https://riointernational.com.bd/product/iphone-15-pro-max', 'Brand New (Unofficial)', $manualResearch, $now);
        $aggregator->recalculate($variant15ProMax512, PriceTypeEnum::UNOFFICIAL_BD);

        // ---- iPhone 17 Pro: same "single generic-source price sits
        // above every named retailer's live quote" pattern, smaller gap. ----
        $this->upsert($manualResearch, $variant17Pro, $rio, PriceTypeEnum::UNOFFICIAL_BD, 143999.00,
            'https://riointernational.com.bd/product/iphone-17-pro', 'Brand New (Unofficial)', $now);
        $this->upsert($manualResearch, $variant17Pro, $dazzle, PriceTypeEnum::UNOFFICIAL_BD, 146990.00,
            'https://dazzle.com.bd/product/iphone-17-pro-price-in-bangladesh', null, $now);
        $this->reverify($variant17Pro, PriceTypeEnum::UNOFFICIAL_BD, 5, $now); // keep existing "Local Market" 160,000 as a 3rd observation
        $aggregator->recalculate($variant17Pro, PriceTypeEnum::UNOFFICIAL_BD);

        // ---- iPhone Air: adding a genuine 3rd official observation
        // reveals the existing Sumash Tech quote (209,999) is a real
        // statistical outlier against Gadget & Gear/Star Tech - the
        // aggregator now correctly discounts it on its own. ----
        $this->reverify($variantAir, PriceTypeEnum::OFFICIAL_BD, 3, $now);
        $this->reverify($variantAir, PriceTypeEnum::OFFICIAL_BD, 24, $now);
        $this->upsert($manualResearch, $variantAir, $gadgetAndGearStore, PriceTypeEnum::OFFICIAL_BD, 164999.00,
            'https://gadgetandgear.com/product/iphone-air', 'Apple official Bangladesh & international warranty', $now);
        $aggregator->recalculate($variantAir, PriceTypeEnum::OFFICIAL_BD);
    }

    public function down(): void
    {
        // Deliberately irreversible: this is a price-data correction, not
        // a structural change. Rolling back would silently reinstate
        // verified-stale/wrong prices (e.g. the iPhone 15 Pro Max bug this
        // migration fixes). If a specific figure ever needs to change
        // again, correct it forward with a new migration instead.
    }

    protected function reverify(PhoneVariant $variant, PriceTypeEnum $type, int $storeId, Carbon $now): void
    {
        PhonePrice::where('phone_variant_id', $variant->id)
            ->where('store_id', $storeId)
            ->where('price_type', $type->value)
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
