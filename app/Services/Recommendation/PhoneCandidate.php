<?php

namespace App\Services\Recommendation;

use App\Enums\PhoneRegionEnum;
use App\Models\Phone;
use App\Models\PhoneMarketPrice;
use App\Models\PhoneVariant;

/**
 * A phone paired with the specific variant + current market price that
 * made it eligible for a given user (the cheapest eligible-market
 * configuration that satisfies their hard requirements). Carries the
 * outlier-resistant market price (see App\Services\PhoneImport\PriceAggregator),
 * never a raw single-retailer phone_prices row - a phone with multiple
 * variants/retailers is represented once, by its best-fit option, so the
 * engine never has to guess which of several current prices to show.
 */
class PhoneCandidate
{
    public function __construct(
        public readonly Phone $phone,
        public readonly PhoneVariant $variant,
        public readonly PhoneMarketPrice $marketPrice,
    ) {}

    public function priceAmount(): float
    {
        return (float) $this->marketPrice->price;
    }

    /**
     * The user-facing version this candidate's variant belongs to - see
     * App\Enums\PhoneRegionEnum. CandidateFilter always prefers a Global
     * variant when one satisfies the user's requirements, so this is
     * CHINESE only when the phone had no eligible Global variant at all.
     */
    public function region(): PhoneRegionEnum
    {
        return PhoneRegionEnum::resolve($this->variant->region);
    }

    public function isChineseVariant(): bool
    {
        return $this->region() === PhoneRegionEnum::CHINESE;
    }
}
