<?php

namespace Database\Factories;

use App\Enums\PhoneStatusEnum;
use App\Models\Brand;
use App\Models\Phone;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class PhoneFactory extends Factory
{
    protected $model = Phone::class;

    public function definition(): array
    {
        $name = fake()->words(2, true);

        return [
            'brand_id' => Brand::factory(),
            'name' => $name,
            'slug' => Str::slug($name.' '.fake()->unique()->numberBetween(1, 100000)),
            'status' => PhoneStatusEnum::AVAILABLE,
            'is_active' => true,
        ];
    }
}
