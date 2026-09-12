<?php

namespace Database\Factories;

use App\Models\Restaurant;
use App\Models\RestaurantMenuCategory;
use App\Models\RestaurantMenuItem;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<RestaurantMenuItem>
 */
class RestaurantMenuItemFactory extends Factory
{
    protected $model = RestaurantMenuItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);

        return [
            'restaurant_id' => Restaurant::factory(),
            'restaurant_menu_category_id' => RestaurantMenuCategory::factory(),
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => fake()->sentence(),
            'price' => fake()->numberBetween(150, 1500),
            'price_note' => null,
            'image_path' => null,
            'is_featured' => false,
            'is_available' => true,
            'display_order' => 0,
        ];
    }

    public function featured(): static
    {
        return $this->state(fn () => ['is_featured' => true]);
    }
}
