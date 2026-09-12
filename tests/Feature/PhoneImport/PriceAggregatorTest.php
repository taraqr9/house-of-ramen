<?php

use App\Enums\PriceTypeEnum;
use App\Models\PhoneMarketPrice;
use App\Models\PhonePrice;
use App\Models\PhoneVariant;
use App\Services\PhoneImport\PriceAggregator;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function aggregator(): PriceAggregator
{
    return app(PriceAggregator::class);
}

// -----------------------------------------------------------------------
// robustAggregate() - pure statistics, the task's own example scenario.
// -----------------------------------------------------------------------

it('trusts a single observation outright - too few points to call anything an outlier', function () {
    $stats = aggregator()->robustAggregate([['amount' => 199900.0, 'store_id' => 1]]);

    expect($stats['price'])->toBe(199900.0)
        ->and($stats['observation_count'])->toBe(1)
        ->and($stats['outlier_count'])->toBe(0);
});

it('trusts both observations when there are only two, however different they are', function () {
    $stats = aggregator()->robustAggregate([
        ['amount' => 150000.0, 'store_id' => 1],
        ['amount' => 90000.0, 'store_id' => 2],
    ]);

    expect($stats['observation_count'])->toBe(2)
        ->and($stats['outlier_count'])->toBe(0)
        ->and($stats['price'])->toBe(120000.0); // median of two = their average
});

it('excludes a clear outlier retailer from the current market price - the real four-retailer scenario', function () {
    // Retailer A: 154,000 / B: 156,000 / C: 158,000 / D: 80,000 - D must
    // not drag the market price down to something no legitimate retailer
    // is actually charging.
    $stats = aggregator()->robustAggregate([
        ['amount' => 154000.0, 'store_id' => 1],
        ['amount' => 156000.0, 'store_id' => 2],
        ['amount' => 158000.0, 'store_id' => 3],
        ['amount' => 80000.0, 'store_id' => 4],
    ]);

    expect($stats['outlier_count'])->toBe(1)
        ->and($stats['observation_count'])->toBe(3)
        ->and($stats['retailer_count'])->toBe(3)
        ->and($stats['price'])->toBe(156000.0)
        ->and($stats['price_min'])->toBe(154000.0)
        ->and($stats['price_max'])->toBe(158000.0);
});

it('does not treat a tightly-clustered set of matching prices as containing an outlier', function () {
    $stats = aggregator()->robustAggregate([
        ['amount' => 50000.0, 'store_id' => 1],
        ['amount' => 50000.0, 'store_id' => 2],
        ['amount' => 50500.0, 'store_id' => 3],
    ]);

    expect($stats['outlier_count'])->toBe(0)
        ->and($stats['observation_count'])->toBe(3);
});

it('falls back to trusting every observation if the outlier test would otherwise flag all of them', function () {
    // Two genuinely separate clusters (e.g. two different real price
    // tiers briefly coexisting) rather than one bad observation - MAD
    // outlier rejection on the raw amounts alone can flag everything
    // relative to a median sitting between the clusters; the aggregator
    // must not return an empty result in that case.
    $stats = aggregator()->robustAggregate([
        ['amount' => 20000.0, 'store_id' => 1],
        ['amount' => 20500.0, 'store_id' => 2],
        ['amount' => 90000.0, 'store_id' => 3],
        ['amount' => 91000.0, 'store_id' => 4],
    ]);

    expect($stats['observation_count'])->toBeGreaterThan(0);
});

it('counts distinct retailers separately from raw observation count', function () {
    $stats = aggregator()->robustAggregate([
        ['amount' => 50000.0, 'store_id' => 1],
        ['amount' => 50000.0, 'store_id' => 1], // same retailer listed twice - still one retailer
        ['amount' => 51000.0, 'store_id' => 2],
    ]);

    expect($stats['observation_count'])->toBe(3)
        ->and($stats['retailer_count'])->toBe(2);
});

// -----------------------------------------------------------------------
// isSane() - the ingest-time circuit breaker (separate from the
// statistical outlier test above).
// -----------------------------------------------------------------------

it('accepts any plausible phone-priced amount when there is no existing market price to compare against', function () {
    expect(aggregator()->isSane(1000.0, null))->toBeTrue()
        ->and(aggregator()->isSane(45000.0, null))->toBeTrue()
        ->and(aggregator()->isSane(500000.0, null))->toBeTrue();
});

it('rejects a zero, negative, or implausibly tiny amount even with no reference price', function () {
    // The absolute floor is what actually catches a malformed first
    // observation - an EMI monthly installment, a pre-order deposit, a
    // discount amount instead of the final price - since the
    // reference-ratio check below has nothing to compare against yet.
    expect(aggregator()->isSane(0.0, null))->toBeFalse()
        ->and(aggregator()->isSane(-100.0, null))->toBeFalse()
        ->and(aggregator()->isSane(1.0, null))->toBeFalse()
        ->and(aggregator()->isSane(999.0, null))->toBeFalse();
});

it('rejects an implausibly large amount even with no reference price', function () {
    expect(aggregator()->isSane(500001.0, null))->toBeFalse()
        ->and(aggregator()->isSane(5000000.0, null))->toBeFalse();
});

it('rejects an amount wildly out of line with the existing market price', function () {
    $variant = PhoneVariant::factory()->create();
    $reference = PhoneMarketPrice::factory()->for($variant, 'variant')->create(['price' => 150000]);

    // A missing digit (15,000 instead of 150,000) and a unit slip
    // (1,500,000) are exactly what this circuit breaker exists for.
    expect(aggregator()->isSane(15000.0, $reference))->toBeFalse()
        ->and(aggregator()->isSane(1500000.0, $reference))->toBeFalse();
});

it('accepts a genuinely different but plausible retailer price against the same reference', function () {
    $variant = PhoneVariant::factory()->create();
    $reference = PhoneMarketPrice::factory()->for($variant, 'variant')->create(['price' => 150000]);

    expect(aggregator()->isSane(118000.0, $reference))->toBeTrue()
        ->and(aggregator()->isSane(180000.0, $reference))->toBeTrue();
});

// -----------------------------------------------------------------------
// recalculate() - the DB-integrated read/write half.
// -----------------------------------------------------------------------

it('computes and stores separate official and unofficial market prices for the same variant', function () {
    $variant = PhoneVariant::factory()->create();
    PhonePrice::factory()->for($variant, 'variant')->create(['price_type' => 'official_bd', 'amount' => 199900, 'is_active' => true]);
    PhonePrice::factory()->for($variant, 'variant')->create(['price_type' => 'unofficial_bd', 'amount' => 154000, 'is_active' => true]);

    aggregator()->recalculate($variant, PriceTypeEnum::OFFICIAL_BD);
    aggregator()->recalculate($variant, PriceTypeEnum::UNOFFICIAL_BD);

    $official = PhoneMarketPrice::query()->where('phone_variant_id', $variant->id)->where('price_type', 'official_bd')->firstOrFail();
    $unofficial = PhoneMarketPrice::query()->where('phone_variant_id', $variant->id)->where('price_type', 'unofficial_bd')->firstOrFail();

    expect((float) $official->price)->toBe(199900.0)
        ->and((float) $unofficial->price)->toBe(154000.0);
});

it('removes the market price when the last active observation for a type disappears', function () {
    $variant = PhoneVariant::factory()->create();
    $price = PhonePrice::factory()->for($variant, 'variant')->create(['price_type' => 'official_bd', 'amount' => 100000, 'is_active' => true]);

    aggregator()->recalculate($variant, PriceTypeEnum::OFFICIAL_BD);
    expect(PhoneMarketPrice::query()->where('phone_variant_id', $variant->id)->exists())->toBeTrue();

    $price->update(['is_active' => false]);
    aggregator()->recalculate($variant, PriceTypeEnum::OFFICIAL_BD);

    expect(PhoneMarketPrice::query()->where('phone_variant_id', $variant->id)->exists())->toBeFalse();
});

it('ignores inactive price rows when recalculating', function () {
    $variant = PhoneVariant::factory()->create();
    PhonePrice::factory()->for($variant, 'variant')->create(['price_type' => 'official_bd', 'amount' => 100000, 'is_active' => true]);
    PhonePrice::factory()->for($variant, 'variant')->create(['price_type' => 'official_bd', 'amount' => 999999, 'is_active' => false]);

    $result = aggregator()->recalculate($variant, PriceTypeEnum::OFFICIAL_BD);

    expect((float) $result->price)->toBe(100000.0)
        ->and($result->observation_count)->toBe(1);
});
