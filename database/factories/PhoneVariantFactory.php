<?php

namespace Database\Factories;

use App\Models\Phone;
use App\Models\PhoneVariant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class PhoneVariantFactory extends Factory
{
    protected $model = PhoneVariant::class;

    public function definition(): array
    {
        return [
            'phone_id' => Phone::factory(),
            'slug' => Str::random(12),
            'ram_gb' => 8,
            'storage_gb' => 128,
            'region' => 'Global',
            'is_official_bd' => true,
            'status' => 'available',
            'is_active' => true,
        ];
    }
}
