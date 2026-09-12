<?php

namespace App\Console\Commands;

use App\Enums\PriceTypeEnum;
use App\Models\PhonePrice;
use App\Models\PhoneVariant;
use App\Services\PhoneImport\PriceAggregator;
use Illuminate\Console\Command;

class ExpireStalePricesCommand extends Command
{
    protected $signature = 'phones:expire-stale-prices
        {--days= : Override config(phone_pricing.expire_after_days) for this run.}';

    protected $description = 'Deactivate phone_prices rows no source has re-verified in a long time, then recalculate the affected market prices.';

    public function handle(PriceAggregator $aggregator): int
    {
        $days = (int) ($this->option('days') ?? config('phone_pricing.expire_after_days'));
        $cutoff = now()->subDays($days);

        $stale = PhonePrice::query()
            ->where('is_active', true)
            ->where('last_verified_at', '<', $cutoff)
            ->get(['id', 'phone_variant_id', 'price_type', 'store_id']);

        if ($stale->isEmpty()) {
            $this->info("No price observations older than {$days} days to expire.");

            return self::SUCCESS;
        }

        // Historical rows are never deleted (see PhonePriceHistory) -
        // expiring here only stops this specific retailer/variant/price_type
        // observation from counting as "current". A later successful fetch
        // for the same (variant, store, price_type) simply reactivates it
        // via the normal upsert path (PhoneImportRunner::upsertOnePrice).
        PhonePrice::query()->whereIn('id', $stale->pluck('id'))->update(['is_active' => false]);

        $affectedPairs = $stale->map(fn ($price) => [
            'variant_id' => $price->phone_variant_id,
            'price_type' => $price->price_type->value,
        ])->unique(fn ($pair) => $pair['variant_id'].'|'.$pair['price_type']);

        foreach ($affectedPairs as $pair) {
            $variant = PhoneVariant::find($pair['variant_id']);

            if ($variant) {
                $aggregator->recalculate($variant, PriceTypeEnum::from($pair['price_type']));
            }
        }

        $this->table(
            ['Expired observations', 'Affected variant/price_type pairs'],
            [[$stale->count(), $affectedPairs->count()]]
        );

        return self::SUCCESS;
    }
}
