<?php

namespace App\Models;

use App\Enums\ImageSearchAttemptStatusEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PhoneImageSearchAttempt extends Model
{
    protected $fillable = [
        'phone_id',
        'status',
        'unresolved_reason',
        'sources_tried',
        'candidates_viewed',
        'last_attempted_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => ImageSearchAttemptStatusEnum::class,
            'sources_tried' => 'array',
            'candidates_viewed' => 'integer',
            'last_attempted_at' => 'datetime',
        ];
    }

    public function phone(): BelongsTo
    {
        return $this->belongsTo(Phone::class);
    }
}
