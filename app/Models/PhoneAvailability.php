<?php

namespace App\Models;

use App\Enums\AvailabilityStatusEnum;
use App\Traits\HasUserStamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class PhoneAvailability extends Model
{
    use HasUserStamps, LogsActivity;

    protected $table = 'phone_availability';

    protected $fillable = [
        'phone_variant_id',
        'store_id',
        'status',
        'source_id',
        'confidence',
        'collected_at',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => AvailabilityStatusEnum::class,
            'confidence' => 'integer',
            'collected_at' => 'datetime',
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
