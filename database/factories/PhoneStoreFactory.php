<?php

namespace Database\Factories;

use App\Models\PhoneStore;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class PhoneStoreFactory extends Factory
{
    protected $model = PhoneStore::class;

    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'type' => 'other',
            'is_active' => true,
        ];
    }
}
