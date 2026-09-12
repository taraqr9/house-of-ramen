<?php

namespace App\Models;

use App\Enums\ReviewReasonEnum;
use App\Enums\ReviewStatusEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PhoneDataReview extends Model
{
    protected $fillable = [
        'phone_id',
        'phone_variant_id',
        'import_record_id',
        'reason',
        'matched_phone_id',
        'similarity_score',
        'status',
        'details',
        'reviewed_by',
        'reviewed_at',
        'resolution_note',
        // Only ever set explicitly by PhoneCatalogueSeeder, to reproduce a
        // review's original creation time from the committed snapshot (part
        // of its upsert identity alongside reason+status - see that class).
        // Every other creation path in this app (PhoneImportRunner,
        // PhoneImageCollector, AuditPhoneImagesCommand) omits it and gets
        // the normal auto-timestamp, since none of them build this array
        // from raw request input.
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'reason' => ReviewReasonEnum::class,
            'similarity_score' => 'integer',
            'status' => ReviewStatusEnum::class,
            'details' => 'array',
            'reviewed_at' => 'datetime',
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

    public function importRecord(): BelongsTo
    {
        return $this->belongsTo(PhoneImportRecord::class, 'import_record_id');
    }

    public function matchedPhone(): BelongsTo
    {
        return $this->belongsTo(Phone::class, 'matched_phone_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
