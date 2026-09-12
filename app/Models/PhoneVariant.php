<?php

namespace App\Models;

use App\Traits\HasUserStamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class PhoneVariant extends Model
{
    use HasFactory, HasUserStamps, LogsActivity, SoftDeletes;

    protected $fillable = [
        'phone_id',
        'slug',
        'ram_gb',
        'storage_gb',
        'storage_type',
        'expandable_storage',
        'expandable_storage_max_gb',
        'color',
        'region',
        'sku',
        'is_official_bd',
        'status',
        'source_id',
        'collected_at',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'ram_gb' => 'integer',
            'storage_gb' => 'integer',
            'expandable_storage' => 'boolean',
            'expandable_storage_max_gb' => 'integer',
            'is_official_bd' => 'boolean',
            'collected_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function phone(): BelongsTo
    {
        return $this->belongsTo(Phone::class);
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(PhoneSource::class, 'source_id');
    }

    public function prices(): HasMany
    {
        return $this->hasMany(PhonePrice::class);
    }

    /**
     * The current, outlier-resistant market price(s) for this variant -
     * at most one row per price_type (official_bd/unofficial_bd). See
     * App\Services\PhoneImport\PriceAggregator.
     */
    public function marketPrices(): HasMany
    {
        return $this->hasMany(PhoneMarketPrice::class);
    }

    public function priceHistory(): HasMany
    {
        return $this->hasMany(PhonePriceHistory::class);
    }

    public function availabilities(): HasMany
    {
        return $this->hasMany(PhoneAvailability::class);
    }

    public function label(): string
    {
        return collect([
            $this->ram_gb ? "{$this->ram_gb}GB" : null,
            $this->storage_gb ? "{$this->storage_gb}GB" : null,
            $this->color,
            $this->region,
        ])->filter()->implode(' / ');
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
