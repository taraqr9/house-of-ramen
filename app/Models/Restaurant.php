<?php

namespace App\Models;

use App\Traits\HasUserStamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Restaurant extends Model
{
    use HasFactory, HasUserStamps, LogsActivity, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'tagline',
        'description',
        'logo_path',
        'cover_image_path',
        'phone',
        'email',
        'address',
        'area',
        'opening_hours',
        'facebook_url',
        'instagram_url',
        'delivery_platforms',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'opening_hours' => 'array',
            'delivery_platforms' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function menuCategories(): HasMany
    {
        return $this->hasMany(RestaurantMenuCategory::class);
    }

    public function menuItems(): HasMany
    {
        return $this->hasMany(RestaurantMenuItem::class);
    }

    public function galleryImages(): HasMany
    {
        return $this->hasMany(RestaurantGalleryImage::class);
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
