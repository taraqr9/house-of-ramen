<?php

namespace Database\Factories;

use App\Models\Restaurant;
use App\Models\RestaurantGalleryImage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RestaurantGalleryImage>
 */
class RestaurantGalleryImageFactory extends Factory
{
    protected $model = RestaurantGalleryImage::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'restaurant_id' => Restaurant::factory(),
            'category' => 'food',
            'caption' => fake()->words(2, true),
            'path' => 'restaurant/gallery/'.fake()->uuid().'.jpg',
            'display_order' => 0,
            'is_active' => true,
        ];
    }
}
