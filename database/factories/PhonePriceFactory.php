<?php

namespace Database\Factories;

use App\Models\PhonePrice;
use App\Models\PhoneVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

class PhonePriceFactory extends Factory
{
    protected $model = PhonePrice::class;

    public function definition(): array
    {
        return [
            'phone_variant_id' => PhoneVariant::factory(),
            'price_type' => 'official_bd',
            'amount' => 29999,
            'currency' => 'BDT',
            'is_active' => true,
            'collected_at' => now(),
            'last_verified_at' => now(),
        ];
    }
}
