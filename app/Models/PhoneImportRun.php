<?php

namespace App\Models;

use App\Enums\ImportRunStatusEnum;
use App\Enums\ImportRunTypeEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PhoneImportRun extends Model
{
    protected $fillable = [
        'source_id',
        'type',
        'status',
        'cursor',
        'total_discovered',
        'total_created',
        'total_updated',
        'total_skipped',
        'total_failed',
        'total_conflicts',
        'total_flagged',
        'error_message',
        'triggered_by',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => ImportRunTypeEnum::class,
            'status' => ImportRunStatusEnum::class,
            'cursor' => 'array',
            'total_discovered' => 'integer',
            'total_created' => 'integer',
            'total_updated' => 'integer',
            'total_skipped' => 'integer',
            'total_failed' => 'integer',
            'total_conflicts' => 'integer',
            'total_flagged' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(PhoneSource::class, 'source_id');
    }

    public function triggeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by');
    }

    public function records(): HasMany
    {
        return $this->hasMany(PhoneImportRecord::class, 'import_run_id');
    }

    public function isResumable(): bool
    {
        return $this->status === ImportRunStatusEnum::PARTIAL || $this->status === ImportRunStatusEnum::FAILED;
    }

    public function durationSeconds(): ?int
    {
        if (! $this->started_at) {
            return null;
        }

        return ($this->finished_at ?? now())->diffInSeconds($this->started_at);
    }
}
