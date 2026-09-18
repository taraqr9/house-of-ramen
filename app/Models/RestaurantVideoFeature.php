<?php

namespace App\Models;

use App\Traits\HasUserStamps;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class RestaurantVideoFeature extends Model
{
    use HasFactory, HasUserStamps, LogsActivity, SoftDeletes;

    protected $fillable = [
        'restaurant_id',
        'title',
        'video_url',
        'thumbnail_path',
        'display_order',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function scopePubliclyVisible(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Which of the three supported platforms a link belongs to, or null
     * if it doesn't match a pattern we know how to embed. Used both to
     * validate the admin's input (see RestaurantVideoFeatureStoreRequest)
     * and to decide how to build a thumbnail/embed URL for it.
     */
    public static function detectPlatform(string $url): ?string
    {
        if (preg_match('/(?:youtube\.com\/(?:watch\?v=|embed\/|shorts\/)|youtu\.be\/)[A-Za-z0-9_-]{11}/', $url)) {
            return 'youtube';
        }

        if (preg_match('#^https?://([a-z0-9-]+\.)?(facebook\.com|fb\.watch)/#i', $url)) {
            return 'facebook';
        }

        if (preg_match('#instagram\.com/(p|reel|tv)/[A-Za-z0-9_-]+#i', $url)) {
            return 'instagram';
        }

        return null;
    }

    public function getPlatformAttribute(): ?string
    {
        return static::detectPlatform((string) $this->video_url);
    }

    /**
     * Pulls the 11-character video id out of whatever form of YouTube URL
     * the admin pasted in (watch?v=, youtu.be/, /embed/, /shorts/) so the
     * public site can build both the thumbnail and embed URLs from it
     * without asking admins to figure out the id themselves.
     */
    public function getYoutubeVideoIdAttribute(): ?string
    {
        if (preg_match('/(?:youtube\.com\/(?:watch\?v=|embed\/|shorts\/)|youtu\.be\/)([A-Za-z0-9_-]{11})/', (string) $this->video_url, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * The "{p|reel|tv}/{shortcode}" segment Instagram's own /embed path
     * expects - Instagram has no keyless thumbnail endpoint, so unlike
     * YouTube this is only used to build the click-to-play embed, not a
     * thumbnail image.
     */
    public function getInstagramEmbedPathAttribute(): ?string
    {
        if (preg_match('#instagram\.com/(p|reel|tv)/([A-Za-z0-9_-]+)#i', (string) $this->video_url, $matches)) {
            return "{$matches[1]}/{$matches[2]}";
        }

        return null;
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
