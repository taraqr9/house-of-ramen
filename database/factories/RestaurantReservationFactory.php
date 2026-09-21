<?php

namespace Database\Factories;

use App\Enums\ReservationStatusEnum;
use App\Models\Restaurant;
use App\Models\RestaurantReservation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RestaurantReservation>
 */
class RestaurantReservationFactory extends Factory
{
    protected $model = RestaurantReservation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'restaurant_id' => Restaurant::factory(),
            'name' => fake()->name(),
            'phone' => fake()->numerify('01#########'),
            'email' => fake()->optional()->safeEmail(),
            'party_size' => fake()->numberBetween(1, 8),
            'reservation_date' => fake()->dateTimeBetween('now', '+2 weeks')->format('Y-m-d'),
            'reservation_time' => fake()->time('H:i:00'),
            'notes' => fake()->optional()->sentence(),
            'status' => ReservationStatusEnum::PENDING,
        ];
    }
}
