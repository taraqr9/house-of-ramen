<?php

namespace App\Services\Recommendation;

use App\Enums\PriceTypeEnum;

/**
 * Backend-shaped input for "which phone should I buy" - built from a
 * validated request (see PhoneRecommendationRequest) so a future Vue/
 * Inertia questionnaire only needs to post these fields. Every
 * preference is optional; only maxBudget realistically needs a value
 * for the engine to be useful, and even that is nullable.
 */
class PhoneRecommendationCriteria
{
    protected const VALID_PRICE_PREFERENCES = ['official', 'unofficial', 'both'];

    /**
     * @param  int|null  $maxBudget  Hard requirement (BDT), compared against whichever price(s) pricePreference makes eligible.
     * @param  string|null  $primaryUsage  e.g. gaming, camera, productivity, general - informational, does not filter.
     * @param  array<string, int>  $importance  dimension => 1-5, missing keys default to config('phone_recommendation.default_importance').
     * @param  list<int>  $preferredBrandIds  Soft preference - boosts ranking, never excludes.
     * @param  list<int>  $excludedBrandIds  Hard requirement - phones from these brands are excluded entirely.
     * @param  string  $pricePreference  'official' | 'unofficial' | 'both' (default). Which market(s) the user is willing to buy
     *                                   from - Bangladesh has a real, non-interchangeable official/unofficial price split (see
     *                                   App\Services\PhoneImport\PriceAggregator), so this decides which current market price(s)
     *                                   a phone is even eligible on, not just which number gets displayed.
     * @param  bool|null  $required5g  Hard requirement when true.
     * @param  bool|null  $requiredNfc  Hard requirement when true.
     * @param  int|null  $requiredStorageGb  Hard requirement - minimum storage, when set.
     */
    public function __construct(
        public readonly ?int $maxBudget = null,
        public readonly ?string $primaryUsage = null,
        public readonly array $importance = [],
        public readonly array $preferredBrandIds = [],
        public readonly array $excludedBrandIds = [],
        public readonly string $pricePreference = 'both',
        public readonly ?bool $required5g = null,
        public readonly ?bool $requiredNfc = null,
        public readonly ?int $requiredStorageGb = null,
    ) {}

    public static function fromArray(array $data): self
    {
        $pricePreference = $data['price_preference'] ?? 'both';

        return new self(
            maxBudget: $data['max_budget'] ?? null,
            primaryUsage: $data['primary_usage'] ?? null,
            importance: $data['importance'] ?? [],
            preferredBrandIds: $data['preferred_brand_ids'] ?? [],
            excludedBrandIds: $data['excluded_brand_ids'] ?? [],
            pricePreference: in_array($pricePreference, self::VALID_PRICE_PREFERENCES, true) ? $pricePreference : 'both',
            required5g: $data['required_5g'] ?? null,
            requiredNfc: $data['required_nfc'] ?? null,
            requiredStorageGb: $data['required_storage_gb'] ?? null,
        );
    }

    /**
     * Which market(s) a phone's price is allowed to come from, given
     * pricePreference. Never empty.
     *
     * @return list<PriceTypeEnum>
     */
    public function eligiblePriceTypes(): array
    {
        return match ($this->pricePreference) {
            'official' => [PriceTypeEnum::OFFICIAL_BD],
            'unofficial' => [PriceTypeEnum::UNOFFICIAL_BD],
            default => [PriceTypeEnum::OFFICIAL_BD, PriceTypeEnum::UNOFFICIAL_BD],
        };
    }

    /**
     * The 1-5 importance for a dimension, defaulting to neutral (Medium)
     * when the user never answered it - this is what lets a user who
     * only sets a budget still get a sensibly balanced ranking.
     */
    public function importanceFor(string $dimension): int
    {
        $value = $this->importance[$dimension] ?? config('phone_recommendation.default_importance');

        return (int) min(5, max(1, $value));
    }

    public function hasBudget(): bool
    {
        return $this->maxBudget !== null;
    }

    /**
     * The composite weight for a dimension, derived from its 1-5
     * importance (see config('phone_recommendation.importance_weight_exponent')
     * for why this isn't just the raw 1-5 value).
     */
    public function weightFor(string $dimension): float
    {
        return $this->importanceFor($dimension) ** config('phone_recommendation.importance_weight_exponent', 1);
    }
}
