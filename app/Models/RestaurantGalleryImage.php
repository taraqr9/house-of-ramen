<?php

namespace App\Models;

use App\Enums\GalleryCategoryEnum;
use App\Traits\HasUserStamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RestaurantGalleryImage extends Model
{
    use HasFactory, HasUserStamps;

    protected $fillable = [
        'restaurant_id',
        'category',
        'caption',
        'path',
        'display_order',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'category' => GalleryCategoryEnum::class,
            'is_active' => 'boolean',
        ];
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }
}
