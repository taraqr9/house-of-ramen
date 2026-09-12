<?php

namespace App\Models;

use App\Enums\StatusEnum;
use App\Traits\HasUserStamps;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @property int $id
 * @property string $title
 * @property string|null $icon
 * @property string|null $route
 * @property string|null $url
 * @property int|null $parent_id
 * @property string|null $permission
 * @property int $serial
 * @property bool $is_active
 * @property string|null $created_by
 * @property string|null $updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Menu|null $parent
 * @property-read Collection<int, Menu> $children
 * @property-read int|null $children_count
 */
class Menu extends Model
{
    use HasUserStamps, LogsActivity, SoftDeletes;

    protected $fillable = [
        'title',
        'icon',
        'route',
        'url',
        'parent_id',
        'permission',
        'serial',
        'active_routes',
        'remarks',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'active_routes' => 'array',
        'is_active' => StatusEnum::class,
    ];

    public function children(): HasMany
    {
        return $this->hasMany(Menu::class, 'parent_id')
            ->orderBy('serial');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Menu::class, 'parent_id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName(strtolower(class_basename($this)))
            ->logAll()
            ->logOnlyDirty()
            ->setDescriptionForEvent(function (string $eventName) {
                return class_basename($this)." {$eventName}<br>".
                    '<strong>Table:</strong> '.$this->getTable();
            });
    }
}
