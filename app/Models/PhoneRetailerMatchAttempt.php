<?php

namespace App\Models;

use App\Enums\PriceTypeEnum;
use App\Enums\RetailerMatchStatusEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PhoneRetailerMatchAttempt extends Model
{
    protected $fillable = [
        'phone_id',
        'source_key',
        'status',
        'candidate_url',
        'matched_variant_id',
        'price_type',
        'http_status',
        'attempts',
        'note',
        'checked_at',
        'next_check_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => RetailerMatchStatusEnum::class,
            'price_type' => PriceTypeEnum::class,
            'attempts' => 'integer',
            'checked_at' => 'datetime',
            'next_check_at' => 'datetime',
        ];
    }

    public function phone(): BelongsTo
    {
        return $this->belongsTo(Phone::class);
    }

    public function matchedVariant(): BelongsTo
    {
        return $this->belongsTo(PhoneVariant::class, 'matched_variant_id');
    }
}
