<?php

namespace Database\Factories;

use App\Models\Restaurant;
use App\Models\RestaurantVideoFeature;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RestaurantVideoFeature>
 */
class RestaurantVideoFeatureFactory extends Factory
{
    protected $model = RestaurantVideoFeature::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'restaurant_id' => Restaurant::factory(),
            'title' => fake()->sentence(4),
            'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'thumbnail_path' => null,
            'display_order' => 0,
            'is_active' => true,
        ];
    }

    public function facebook(): static
    {
        return $this->state(fn () => ['video_url' => 'https://www.facebook.com/HouseOfRamen/videos/1234567890/']);
    }

    public function instagram(): static
    {
        return $this->state(fn () => ['video_url' => 'https://www.instagram.com/reel/Cabc123XYZ9/']);
    }
}
