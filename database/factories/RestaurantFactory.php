<?php

namespace Database\Factories;

use App\Models\Restaurant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Restaurant>
 */
class RestaurantFactory extends Factory
{
    protected $model = Restaurant::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Unique per instance (not the fixed "House of Ramen") so a test
        // that incidentally creates more than one Restaurant via a nested
        // factory relationship (e.g. RestaurantMenuCategoryFactory's own
        // restaurant_id default) never collides on the unique name/slug -
        // tests that need the real name pass it explicitly.
        $name = 'House of Ramen Test '.fake()->unique()->numberBetween(1, 1000000);

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'tagline' => 'Modern ramen & Japanese-Korean comfort food',
            'description' => fake()->paragraph(),
            'logo_path' => null,
            'cover_image_path' => null,
            'phone' => '+880'.fake()->numerify('##########'),
            'email' => null,
            'address' => fake()->streetAddress(),
            'area' => 'Uttara, Dhaka',
            'opening_hours' => null,
            'facebook_url' => null,
            'instagram_url' => null,
            'delivery_platforms' => ['Foodi', 'foodpanda'],
            'is_active' => true,
        ];
    }
}
