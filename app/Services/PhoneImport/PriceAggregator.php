<?php

namespace App\Services\PhoneImport;

use App\Enums\PriceTypeEnum;
use App\Models\PhoneMarketPrice;
use App\Models\PhoneVariant;

/**
 * Turns the raw, per-retailer rows in phone_prices into one
 * outlier-resistant "current market price" per (variant,
 * official|unofficial) pair, stored in phone_market_prices. See
 * config/phone_pricing.php for the reasoning behind every threshold
 * used here.
 *
 * phone_prices is never touched by this class - it stays the honest,
 * unfiltered record of what each retailer currently quotes. This class
 * only decides what the recommendation engine, comparison page, and
 * phone detail page should treat as "the" price.
 */
class PriceAggregator
{
    /**
     * Ingest-time circuit breaker: whether a newly observed amount is
     * plausible enough to become a retailer's current price at all,
     * given the market price already on record (if any). This runs
     * BEFORE recalculate() ever sees the value - a failure here means
     * the observation is routed to the review queue instead of being
     * written to phone_prices. See config('phone_pricing.sanity_*').
     */
    public function isSane(float $amount, ?PhoneMarketPrice $reference): bool
    {
        if ($amount < config('phone_pricing.absolute_min_bdt') || $amount > config('phone_pricing.absolute_max_bdt')) {
            return false;
        }

        if (! $reference || (float) $reference->price <= 0) {
            return true;
        }

        $ratio = $amount / (float) $reference->price;

        return $ratio >= config('phone_pricing.sanity_min_ratio')
            && $ratio <= config('phone_pricing.sanity_max_ratio');
    }

    /**
     * Recalculates and persists the current market price for one
     * (variant, price_type) pair from every currently-active phone_prices
     * row for it. Deletes the aggregate row if no active observations
     * remain (e.g. every retailer stopped carrying it).
     */
    public function recalculate(PhoneVariant $variant, PriceTypeEnum $type): ?PhoneMarketPrice
    {
        // is_active=true only means "not explicitly removed" - it says
        // nothing about how recently a source actually re-confirmed the
        // amount. Gating on last_verified_at too is what keeps a row
        // nobody has been able to re-fetch in weeks from silently
        // anchoring "the" current price forever. See
        // config('phone_pricing.stale_after_days').
        $freshSince = now()->subDays(config('phone_pricing.stale_after_days'));

        $observations = $variant->prices()
            ->where('is_active', true)
            ->where('price_type', $type->value)
            ->where('last_verified_at', '>=', $freshSince)
            ->get(['amount', 'store_id'])
            ->map(fn ($price) => ['amount' => (float) $price->amount, 'store_id' => $price->store_id])
            ->all();

        if (empty($observations)) {
            PhoneMarketPrice::query()
                ->where('phone_variant_id', $variant->id)
                ->where('price_type', $type->value)
                ->delete();

            return null;
        }

        $stats = $this->robustAggregate($observations);

        return PhoneMarketPrice::query()->updateOrCreate(
            ['phone_variant_id' => $variant->id, 'price_type' => $type->value],
            [
                'price' => $stats['price'],
                'price_min' => $stats['price_min'],
                'price_max' => $stats['price_max'],
                'observation_count' => $stats['observation_count'],
                'retailer_count' => $stats['retailer_count'],
                'outlier_count' => $stats['outlier_count'],
                'calculated_at' => now(),
            ]
        );
    }

    /**
     * The pure statistics, kept separate from the DB read/write above so
     * the aggregation logic itself is trivially unit-testable.
     *
     * Median Absolute Deviation (MAD)-based outlier rejection: robust to
     * exactly the failure mode retail price data has (one retailer wildly
     * off while the rest cluster together), and - unlike a fixed Taka
     * threshold - scales automatically with each phone's own price level
     * since every comparison is relative to that phone's own median.
     *
     * @param  list<array{amount: float, store_id: int|null}>  $observations
     * @return array{price: float, price_min: float, price_max: float, observation_count: int, retailer_count: int, outlier_count: int}
     */
    public function robustAggregate(array $observations): array
    {
        $amounts = array_column($observations, 'amount');
        $median = $this->median($amounts);

        // Fewer than 3 points: no statistically meaningful way to call one
        // of them "the outlier" - trust all of them.
        if (count($observations) < 3) {
            $clean = $observations;
            $outlierCount = 0;
        } else {
            $deviations = array_map(fn ($amount) => abs($amount - $median), $amounts);
            $mad = $this->median($deviations);

            $clean = [];
            $outlierCount = 0;

            foreach ($observations as $observation) {
                $isOutlier = $mad > 0
                    ? (0.6745 * abs($observation['amount'] - $median) / $mad) > config('phone_pricing.outlier_modified_z_threshold')
                    : ($median > 0 && (abs($observation['amount'] - $median) / $median) > config('phone_pricing.outlier_fallback_relative_deviation'));

                if ($isOutlier) {
                    $outlierCount++;
                } else {
                    $clean[] = $observation;
                }
            }

            // Every observation flagged is a sign the test misfired (e.g.
            // several retailers genuinely split into two clusters), not
            // that the whole market is "an outlier" - fall back to trusting
            // everything rather than returning nothing.
            if (empty($clean)) {
                $clean = $observations;
                $outlierCount = 0;
            }
        }

        $cleanAmounts = array_column($clean, 'amount');
        $retailerCount = collect($clean)
            ->map(fn ($observation, $index) => $observation['store_id'] ?? "unattributed-{$index}")
            ->unique()
            ->count();

        return [
            'price' => $this->median($cleanAmounts),
            'price_min' => min($cleanAmounts),
            'price_max' => max($cleanAmounts),
            'observation_count' => count($clean),
            'retailer_count' => $retailerCount,
            'outlier_count' => $outlierCount,
        ];
    }

    protected function median(array $values): float
    {
        sort($values);
        $count = count($values);
        $middle = intdiv($count, 2);

        if ($count % 2 === 0) {
            return ($values[$middle - 1] + $values[$middle]) / 2;
        }

        return $values[$middle];
    }
}
