<?php

namespace Database\Factories;

use App\Models\Restaurant;
use App\Models\RestaurantPopupOffer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RestaurantPopupOffer>
 */
class RestaurantPopupOfferFactory extends Factory
{
    protected $model = RestaurantPopupOffer::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'restaurant_id' => Restaurant::factory(),
            'title' => fake()->words(3, true),
            'image_path' => 'restaurant/popup-offers/'.fake()->uuid().'.jpg',
            'display_order' => 0,
            'is_active' => true,
        ];
    }
}
