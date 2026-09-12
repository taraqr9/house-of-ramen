<?php

namespace App\Models;

use App\Enums\PriceTypeEnum;
use App\Traits\HasUserStamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class PhonePrice extends Model
{
    use HasFactory, HasUserStamps, LogsActivity;

    protected $fillable = [
        'phone_variant_id',
        'store_id',
        'price_type',
        'amount',
        'currency',
        'source_url',
        'warranty_type',
        'source_id',
        'confidence',
        'collected_at',
        'last_verified_at',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'price_type' => PriceTypeEnum::class,
            'amount' => 'decimal:2',
            'confidence' => 'integer',
            'collected_at' => 'datetime',
            'last_verified_at' => 'datetime',
            'is_active' => 'boolean',
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

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName(strtolower(class_basename($this)))
            ->logAll()
            ->logOnlyDirty()
            ->setDescriptionForEvent(fn (string $eventName) => class_basename($this)." {$eventName}<br>".
                '<strong>Table:</strong> '.$this->getTable());
    }
}
