<?php

namespace App\Support;

use App\Models\Restaurant;
use App\Models\RestaurantGalleryImage;
use App\Models\RestaurantMenuItem;
use App\Models\RestaurantPopupOffer;
use App\Models\RestaurantReview;
use App\Models\RestaurantVideoFeature;
use Illuminate\Support\Facades\Storage;

/**
 * Turns Restaurant/RestaurantMenuItem/RestaurantGalleryImage models into
 * the plain arrays the public Inertia pages expect (real storage URLs,
 * never raw paths) - shared by every Public\* controller so the shape
 * can't drift between the homepage's featured items and the full menu
 * page's items, for example.
 */
class RestaurantPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function restaurant(Restaurant $restaurant): array
    {
        return [
            'name' => $restaurant->name,
            'tagline' => $restaurant->tagline,
            'description' => $restaurant->description,
            'logo_url' => $restaurant->logo_path ? Storage::url($restaurant->logo_path) : null,
            'cover_image_url' => $restaurant->cover_image_path ? Storage::url($restaurant->cover_image_path) : null,
            'phone' => $restaurant->phone,
            'email' => $restaurant->email,
            'address' => $restaurant->address,
            'area' => $restaurant->area,
            'opening_hours' => $restaurant->opening_hours,
            'facebook_url' => $restaurant->facebook_url,
            'instagram_url' => $restaurant->instagram_url,
            'delivery_platforms' => $restaurant->delivery_platforms ?? [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function menuItem(RestaurantMenuItem $item): array
    {
        return [
            'id' => $item->id,
            'name' => $item->name,
            'slug' => $item->slug,
            'description' => $item->description,
            'price' => (float) $item->price,
            'price_note' => $item->price_note,
            'image_url' => $item->image_path ? Storage::url($item->image_path) : null,
            'gallery_image_urls' => $item->relationLoaded('images')
                ? $item->images->pluck('path')->map(fn ($path) => Storage::url($path))->all()
                : [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function galleryImage(RestaurantGalleryImage $image): array
    {
        return [
            'id' => $image->id,
            'image_url' => Storage::url($image->path),
            'caption' => $image->caption,
            'category' => $image->category->value,
        ];
    }

    /**
     * Facebook and Instagram have no public, keyless thumbnail endpoint
     * the way YouTube's img.youtube.com is - so for those (or to override
     * YouTube's auto thumbnail) an admin-uploaded thumbnail always wins;
     * only YouTube falls back to deriving one automatically.
     *
     * @return array<string, mixed>
     */
    public static function videoFeature(RestaurantVideoFeature $video): array
    {
        $platform = $video->platform;

        $thumbnailUrl = $video->thumbnail_path
            ? Storage::url($video->thumbnail_path)
            : match ($platform) {
                'youtube' => $video->youtube_video_id ? "https://img.youtube.com/vi/{$video->youtube_video_id}/hqdefault.jpg" : null,
                default => null,
            };

        $embedUrl = match ($platform) {
            'youtube' => $video->youtube_video_id ? "https://www.youtube-nocookie.com/embed/{$video->youtube_video_id}?autoplay=1&rel=0" : null,
            'facebook' => 'https://www.facebook.com/plugins/video.php?href='.urlencode($video->video_url).'&show_text=false&autoplay=true',
            // Bare /embed (no /captioned) - that variant adds a caption
            // and like-count footer below the video, which doesn't belong
            // inside a fixed video-only frame.
            'instagram' => $video->instagram_embed_path ? "https://www.instagram.com/{$video->instagram_embed_path}/embed" : null,
            default => null,
        };

        return [
            'id' => $video->id,
            'title' => $video->title,
            'platform' => $platform,
            'video_url' => $video->video_url,
            'thumbnail_url' => $thumbnailUrl,
            'embed_url' => $embedUrl,
        ];
    }

    /**
     * Deliberately excludes the admin-only `title` field - the homepage
     * popup shows just the image, never that internal reference text.
     *
     * @return array<string, mixed>
     */
    public static function popupOffer(RestaurantPopupOffer $offer): array
    {
        return [
            'id' => $offer->id,
            'image_url' => Storage::url($offer->image_path),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function review(RestaurantReview $review): array
    {
        return [
            'id' => $review->id,
            'author_name' => $review->author_name,
            'rating' => $review->rating,
            'text' => $review->review_text,
            'relative_time' => $review->reviewed_at?->diffForHumans(),
        ];
    }
}
