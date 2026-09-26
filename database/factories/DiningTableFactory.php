<?php

namespace Database\Factories;

use App\Models\DiningTable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DiningTable>
 */
class DiningTableFactory extends Factory
{
    protected $model = DiningTable::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'T'.fake()->unique()->numberBetween(1, 999),
            'area' => null,
            'capacity' => 4,
            'display_order' => 0,
            'is_active' => true,
            'remarks' => null,
        ];
    }
}
