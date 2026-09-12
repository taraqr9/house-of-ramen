<?php

namespace App\Console\Commands;

use App\Enums\PriceTypeEnum;
use App\Models\Phone;
use App\Models\PhonePrice;
use App\Models\PhonePriceHistory;
use App\Models\PhoneSource;
use App\Models\PhoneStore;
use App\Models\PhoneVariant;
use App\Services\PhoneImport\PriceAggregator;
use Illuminate\Console\Command;

/**
 * Attaches one individually-verified retailer price observation to a
 * specific phone variant, via the same PhonePrice/PhonePriceHistory/
 * PriceAggregator path the admin panel's variant edit screen uses (see
 * PhoneVariantController::upsertPrice()) - not a bypass of the pricing
 * architecture, a scriptable entry point into it. Reuses the same
 * 'manual_research' provenance source phones:attach-image already
 * registers (App\Services\PhoneImage\PhoneImageCollector::attachManual())
 * for the same reason: a developer/AI-assisted web search for one
 * specific phone's real current retailer price, cross-checked against
 * the exact model/variant before being attached, rather than an
 * automated feed.
 */
class AttachManualPhonePriceCommand extends Command
{
    protected $signature = 'phones:attach-price
        {phone : Phone id or slug}
        {price_type : official_bd or unofficial_bd}
        {amount : Price in BDT}
        {--variant= : Variant id or slug - required if the phone has more than one variant}
        {--store= : Store slug (created if it does not already exist)}
        {--source-url= : Retailer page the price was verified on}
        {--confidence=80 : 0-100, how confident this specific price/variant match is}
        {--warranty= : Warranty type text, if known (e.g. "1 Year Apple Warranty")}';

    protected $description = 'Attach one manually-verified retailer price observation to a specific phone variant.';

    public function handle(PriceAggregator $prices): int
    {
        $identifier = $this->argument('phone');
        $phone = Phone::query()->where('id', $identifier)->orWhere('slug', $identifier)->first();

        if (! $phone) {
            $this->error("No phone found for '{$identifier}'.");

            return self::FAILURE;
        }

        $priceType = $this->argument('price_type');

        if (! in_array($priceType, ['official_bd', 'unofficial_bd'], true)) {
            $this->error("price_type must be 'official_bd' or 'unofficial_bd', got '{$priceType}'.");

            return self::FAILURE;
        }

        $variant = $this->resolveVariant($phone);

        if (! $variant) {
            return self::FAILURE;
        }

        $amount = (float) $this->argument('amount');

        $storeId = null;

        if ($storeSlug = $this->option('store')) {
            $storeId = PhoneStore::query()->firstOrCreate(
                ['slug' => $storeSlug],
                ['name' => ucwords(str_replace('-', ' ', $storeSlug)), 'type' => 'marketplace', 'is_active' => true]
            )->id;
        }

        $source = PhoneSource::query()->firstOrCreate(
            ['key' => 'manual_research'],
            ['name' => 'Manual Web Research', 'type' => 'manual', 'reliability_score' => 80, 'is_active' => true]
        );

        $existing = PhonePrice::query()
            ->where('phone_variant_id', $variant->id)
            ->where('store_id', $storeId)
            ->where('price_type', $priceType)
            ->first();

        if (! $existing || (float) $existing->amount !== $amount) {
            PhonePriceHistory::create([
                'phone_variant_id' => $variant->id,
                'store_id' => $storeId,
                'price_type' => $priceType,
                'amount' => $amount,
                'previous_amount' => $existing?->amount,
                'source_id' => $source->id,
                'changed_at' => now(),
            ]);
        }

        PhonePrice::query()->updateOrCreate(
            ['phone_variant_id' => $variant->id, 'store_id' => $storeId, 'price_type' => $priceType],
            [
                'amount' => $amount,
                'source_id' => $source->id,
                'source_url' => $this->option('source-url'),
                'warranty_type' => $this->option('warranty'),
                'confidence' => (int) $this->option('confidence'),
                'collected_at' => now(),
                'last_verified_at' => now(),
                'is_active' => true,
            ]
        );

        $prices->recalculate($variant, PriceTypeEnum::from($priceType));

        $this->info("Attached {$priceType} price ৳{$amount} to '{$phone->name}' variant '{$variant->slug}' (phone id {$phone->id}).");

        return self::SUCCESS;
    }

    protected function resolveVariant(Phone $phone): ?PhoneVariant
    {
        $variants = $phone->variants;

        if ($variantOption = $this->option('variant')) {
            $variant = $variants->firstWhere('id', $variantOption) ?? $variants->firstWhere('slug', $variantOption);

            if (! $variant) {
                $this->error("No variant '{$variantOption}' found on '{$phone->name}'.");

                return null;
            }

            return $variant;
        }

        if ($variants->count() === 1) {
            return $variants->first();
        }

        if ($variants->isEmpty()) {
            $this->error("'{$phone->name}' has no variants at all - add one first.");

            return null;
        }

        $this->error("'{$phone->name}' has {$variants->count()} variants - pass --variant= (one of: ".$variants->pluck('slug')->implode(', ').').');

        return null;
    }
}
