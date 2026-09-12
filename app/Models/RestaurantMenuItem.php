<?php

namespace App\Models;

use App\Traits\HasUserStamps;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class RestaurantMenuItem extends Model
{
    use HasFactory, HasUserStamps, LogsActivity, SoftDeletes;

    protected $fillable = [
        'restaurant_id',
        'restaurant_menu_category_id',
        'name',
        'slug',
        'description',
        'price',
        'price_note',
        'image_path',
        'is_featured',
        'is_available',
        'display_order',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'is_featured' => 'boolean',
            'is_available' => 'boolean',
        ];
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(RestaurantMenuCategory::class, 'restaurant_menu_category_id');
    }

    public function images(): HasMany
    {
        return $this->hasMany(RestaurantMenuItemImage::class)->orderBy('display_order');
    }

    public function scopePubliclyVisible(Builder $query): Builder
    {
        return $query->where('is_available', true);
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
