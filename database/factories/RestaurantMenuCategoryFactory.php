<?php

namespace Database\Factories;

use App\Models\Restaurant;
use App\Models\RestaurantMenuCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<RestaurantMenuCategory>
 */
class RestaurantMenuCategoryFactory extends Factory
{
    protected $model = RestaurantMenuCategory::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'restaurant_id' => Restaurant::factory(),
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => null,
            'display_order' => 0,
            'is_active' => true,
        ];
    }
}
