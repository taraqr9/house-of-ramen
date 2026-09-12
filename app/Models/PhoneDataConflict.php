<?php

namespace App\Models;

use App\Enums\ConflictStatusEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PhoneDataConflict extends Model
{
    protected $fillable = [
        'phone_id',
        'phone_variant_id',
        'table_name',
        'field',
        'existing_value',
        'existing_source_id',
        'new_value',
        'new_source_id',
        'import_record_id',
        'status',
        'resolved_value',
        'resolution_note',
        'resolved_by',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => ConflictStatusEnum::class,
            'resolved_at' => 'datetime',
        ];
    }

    public function phone(): BelongsTo
    {
        return $this->belongsTo(Phone::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(PhoneVariant::class, 'phone_variant_id');
    }

    public function existingSource(): BelongsTo
    {
        return $this->belongsTo(PhoneSource::class, 'existing_source_id');
    }

    public function newSource(): BelongsTo
    {
        return $this->belongsTo(PhoneSource::class, 'new_source_id');
    }

    public function importRecord(): BelongsTo
    {
        return $this->belongsTo(PhoneImportRecord::class, 'import_record_id');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
