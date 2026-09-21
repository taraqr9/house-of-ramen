<?php

namespace Database\Factories;

use App\Models\Restaurant;
use App\Models\RestaurantReview;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RestaurantReview>
 */
class RestaurantReviewFactory extends Factory
{
    protected $model = RestaurantReview::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'restaurant_id' => Restaurant::factory(),
            'author_name' => fake()->name(),
            'rating' => fake()->numberBetween(4, 5),
            'review_text' => fake()->paragraph(2),
            'reviewed_at' => fake()->dateTimeBetween('-6 months', 'now'),
            'display_order' => 0,
            'is_active' => true,
        ];
    }
}
