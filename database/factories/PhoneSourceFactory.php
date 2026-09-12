<?php

namespace Database\Factories;

use App\Enums\SourceTypeEnum;
use App\Models\PhoneSource;
use Illuminate\Database\Eloquent\Factories\Factory;

class PhoneSourceFactory extends Factory
{
    protected $model = PhoneSource::class;

    public function definition(): array
    {
        return [
            'key' => fake()->unique()->slug(3),
            'name' => fake()->company(),
            'type' => SourceTypeEnum::MANUAL,
            'reliability_score' => 60,
            'requires_review' => false,
            'is_active' => true,
        ];
    }
}
