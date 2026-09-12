<?php

namespace App\Models;

use App\Enums\PriceTypeEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The current, outlier-resistant market price for one (variant,
 * official|unofficial) pair - see App\Services\PhoneImport\PriceAggregator
 * for how it's computed. Never written to directly by the import
 * pipeline's per-observation writes; always recalculated from the full
 * set of current phone_prices rows for that pair.
 */
class PhoneMarketPrice extends Model
{
    use HasFactory;

    protected $fillable = [
        'phone_variant_id',
        'price_type',
        'price',
        'price_min',
        'price_max',
        'observation_count',
        'retailer_count',
        'outlier_count',
        'calculated_at',
    ];

    protected function casts(): array
    {
        return [
            'price_type' => PriceTypeEnum::class,
            'price' => 'decimal:2',
            'price_min' => 'decimal:2',
            'price_max' => 'decimal:2',
            'observation_count' => 'integer',
            'retailer_count' => 'integer',
            'outlier_count' => 'integer',
            'calculated_at' => 'datetime',
        ];
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(PhoneVariant::class, 'phone_variant_id');
    }

    public function hasPriceRange(): bool
    {
        // price_min/price_max are both 'decimal:2' casts, so they're
        // already fixed 2-decimal-place strings here - plain string
        // comparison is exact, no float/bcmath rounding needed.
        return $this->price_min !== null && $this->price_max !== null && $this->price_min !== $this->price_max;
    }

    /**
     * @return array{price: float, price_min: float|null, price_max: float|null, has_range: bool, retailer_count: int, last_checked_at: string|null}
     */
    public function toDisplayArray(): array
    {
        return [
            'price' => (float) $this->price,
            'price_min' => $this->price_min !== null ? (float) $this->price_min : null,
            'price_max' => $this->price_max !== null ? (float) $this->price_max : null,
            'has_range' => $this->hasPriceRange(),
            'retailer_count' => $this->retailer_count,
            'last_checked_at' => $this->calculated_at?->toIso8601String(),
        ];
    }
}
