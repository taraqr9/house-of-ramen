<?php

namespace App\Models;

use App\Enums\MatchStatusEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PhoneImportRecord extends Model
{
    protected $fillable = [
        'import_run_id',
        'source_id',
        'phone_id',
        'phone_variant_id',
        'external_ref',
        'match_status',
        'confidence_score',
        'raw_payload',
        'normalized_payload',
        'error_message',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'match_status' => MatchStatusEnum::class,
            'confidence_score' => 'integer',
            'raw_payload' => 'array',
            'normalized_payload' => 'array',
            'processed_at' => 'datetime',
        ];
    }

    public function importRun(): BelongsTo
    {
        return $this->belongsTo(PhoneImportRun::class, 'import_run_id');
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(PhoneSource::class, 'source_id');
    }

    public function phone(): BelongsTo
    {
        return $this->belongsTo(Phone::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(PhoneVariant::class, 'phone_variant_id');
    }
}
