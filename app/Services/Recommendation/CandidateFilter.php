<?php

namespace App\Services\Recommendation;

use App\Enums\PhoneStatusEnum;
use App\Models\Phone;
use App\Models\PhoneVariant;
use App\Services\Presentation\PhoneVariantRegionSelector;
use Illuminate\Support\Collection;

/**
 * Applies hard requirements only (budget, price preference, required
 * 5G/NFC/storage, brand exclusion). Preferences never appear here - they
 * only influence ranking, in PhoneScorer/PhoneRecommendationEngine.
 *
 * Filtering happens in two passes: a cheap database-level pass narrows
 * the query to plausibly-eligible phones (so an ever-growing catalogue
 * stays fast), then a precise PHP-level pass picks each phone's single
 * best-fit variant/market-price or drops it if nothing actually
 * qualifies. Every price comparison here is against the current market
 * price (phone_market_prices, see App\Services\PhoneImport\PriceAggregator) -
 * never a raw single-retailer phone_prices row - so one outlier retailer
 * can never make a phone look cheaper (or pricier) than it really is.
 *
 * Both market prices (official and unofficial) are always eager-loaded
 * regardless of pricePreference, even though only the eligible type(s)
 * decide eligibility/ranking here - the formatter needs both to show
 * "also available unofficially at X" alongside whichever price won.
 */
class CandidateFilter
{
    /**
     * @return Collection<int, PhoneCandidate>
     */
    public function candidates(PhoneRecommendationCriteria $criteria): Collection
    {
        $eligibleTypes = array_map(fn ($type) => $type->value, $criteria->eligiblePriceTypes());

        $query = Phone::query()
            ->publiclyVisible()
            ->where('status', '!=', PhoneStatusEnum::UPCOMING->value)
            ->with([
                'brand',
                'spec',
                'primaryImage',
                'variants' => fn ($q) => $q->where('is_active', true)->with('marketPrices'),
            ]);

        if (! empty($criteria->excludedBrandIds)) {
            $query->whereNotIn('brand_id', $criteria->excludedBrandIds);
        }

        if ($criteria->required5g) {
            $query->whereHas('spec', fn ($q) => $q->where('network_5g', true));
        }

        if ($criteria->requiredNfc) {
            $query->whereHas('spec', fn ($q) => $q->where('nfc', true));
        }

        $query->whereHas('variants.marketPrices', function ($q) use ($criteria, $eligibleTypes) {
            $q->whereIn('price_type', $eligibleTypes);

            if ($criteria->hasBudget()) {
                $q->where('price', '<=', $criteria->maxBudget);
            }
        });

        return $query->get()
            ->map(fn (Phone $phone) => $this->bestFitCandidate($phone, $criteria, $eligibleTypes))
            ->filter()
            ->values();
    }

    /**
     * Global variants are tried first; a Chinese variant is only
     * considered when nothing in the Global group actually satisfies the
     * user's hard requirements (storage/budget/price type) - see
     * App\Enums\PhoneRegionEnum and PhoneVariantRegionSelector. A phone
     * is never represented by a cheaper Chinese variant while a
     * satisfying Global one exists.
     *
     * @param  list<string>  $eligibleTypes
     */
    protected function bestFitCandidate(Phone $phone, PhoneRecommendationCriteria $criteria, array $eligibleTypes): ?PhoneCandidate
    {
        [$globalVariants, $chineseVariants] = PhoneVariantRegionSelector::partition($phone->variants);

        return $this->bestFitAmong($phone, $globalVariants, $criteria, $eligibleTypes)
            ?? $this->bestFitAmong($phone, $chineseVariants, $criteria, $eligibleTypes);
    }

    /**
     * @param  Collection<int, PhoneVariant>  $variants
     * @param  list<string>  $eligibleTypes
     */
    protected function bestFitAmong(Phone $phone, Collection $variants, PhoneRecommendationCriteria $criteria, array $eligibleTypes): ?PhoneCandidate
    {
        $best = null;

        foreach ($variants as $variant) {
            if ($criteria->requiredStorageGb !== null) {
                if ($variant->storage_gb === null || $variant->storage_gb < $criteria->requiredStorageGb) {
                    continue;
                }
            }

            foreach ($variant->marketPrices as $marketPrice) {
                if (! in_array($marketPrice->price_type->value, $eligibleTypes, true)) {
                    continue;
                }

                if ($criteria->hasBudget() && (float) $marketPrice->price > $criteria->maxBudget) {
                    continue;
                }

                if ($best === null || (float) $marketPrice->price < $best->priceAmount()) {
                    $best = new PhoneCandidate($phone, $variant, $marketPrice);
                }
            }
        }

        return $best;
    }
}
