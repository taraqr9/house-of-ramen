<?php

namespace Database\Factories;

use App\Models\PhoneMarketPrice;
use App\Models\PhoneVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

class PhoneMarketPriceFactory extends Factory
{
    protected $model = PhoneMarketPrice::class;

    public function definition(): array
    {
        return [
            'phone_variant_id' => PhoneVariant::factory(),
            'price_type' => 'official_bd',
            'price' => 29999,
            'price_min' => 29999,
            'price_max' => 29999,
            'observation_count' => 1,
            'retailer_count' => 1,
            'outlier_count' => 0,
            'calculated_at' => now(),
        ];
    }
}
