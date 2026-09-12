<?php

namespace App\Models;

use App\Enums\PriceTypeEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PhonePriceHistory extends Model
{
    const UPDATED_AT = null;

    protected $table = 'phone_price_history';

    protected $fillable = [
        'phone_variant_id',
        'store_id',
        'price_type',
        'amount',
        'previous_amount',
        'currency',
        'source_id',
        'changed_at',
    ];

    protected function casts(): array
    {
        return [
            'price_type' => PriceTypeEnum::class,
            'amount' => 'decimal:2',
            'previous_amount' => 'decimal:2',
            'changed_at' => 'datetime',
        ];
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(PhoneVariant::class, 'phone_variant_id');
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(PhoneStore::class, 'store_id');
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(PhoneSource::class, 'source_id');
    }
}
