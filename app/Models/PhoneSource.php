<?php

namespace App\Models;

use App\Enums\SourceTypeEnum;
use App\Traits\HasUserStamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class PhoneSource extends Model
{
    use HasFactory, HasUserStamps, LogsActivity, SoftDeletes;

    protected $fillable = [
        'key',
        'name',
        'type',
        'base_url',
        'reliability_score',
        'requires_review',
        'config',
        'last_run_at',
        'notes',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'type' => SourceTypeEnum::class,
            'reliability_score' => 'integer',
            'requires_review' => 'boolean',
            'config' => 'array',
            'last_run_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function importRuns(): HasMany
    {
        return $this->hasMany(PhoneImportRun::class, 'source_id');
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
