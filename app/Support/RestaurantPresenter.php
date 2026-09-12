<?php

namespace App\Support;

use App\Models\Restaurant;
use App\Models\RestaurantGalleryImage;
use App\Models\RestaurantMenuItem;
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
}
