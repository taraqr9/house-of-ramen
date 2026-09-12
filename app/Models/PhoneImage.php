<?php

namespace App\Models;

use App\Enums\ImageStatusEnum;
use App\Traits\HasUserStamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class PhoneImage extends Model
{
    use HasFactory, HasUserStamps;

    protected $fillable = [
        'phone_id',
        'phone_variant_id',
        'is_primary',
        'sort_order',
        'disk',
        'path',
        'external_url',
        'width',
        'height',
        'file_size_bytes',
        'mime_type',
        'source_id',
        'source_url',
        'license',
        'attribution',
        'match_confidence',
        'status',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'sort_order' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'file_size_bytes' => 'integer',
            'match_confidence' => 'integer',
            'status' => ImageStatusEnum::class,
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

    public function source(): BelongsTo
    {
        return $this->belongsTo(PhoneSource::class, 'source_id');
    }

    /**
     * Public URL for the stored, optimized file - null when only an
     * external_url candidate exists (not yet downloaded, or rejected).
     */
    public function getUrlAttribute(): ?string
    {
        if (! $this->path) {
            return null;
        }

        return Storage::disk($this->disk)->url($this->path);
    }
}
