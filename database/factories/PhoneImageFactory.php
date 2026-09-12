<?php

namespace Database\Factories;

use App\Enums\ImageStatusEnum;
use App\Models\Phone;
use App\Models\PhoneImage;
use Illuminate\Database\Eloquent\Factories\Factory;

class PhoneImageFactory extends Factory
{
    protected $model = PhoneImage::class;

    public function definition(): array
    {
        return [
            'phone_id' => Phone::factory(),
            'is_primary' => true,
            'sort_order' => 0,
            'disk' => 'public',
            'path' => 'phones/test/'.fake()->uuid().'.webp',
            'external_url' => 'https://upload.wikimedia.org/wikipedia/commons/test.jpg',
            'width' => 800,
            'height' => 1000,
            'file_size_bytes' => 40000,
            'mime_type' => 'image/webp',
            'source_url' => 'https://commons.wikimedia.org/wiki/File:Test.jpg',
            'license' => 'CC BY-SA 4.0',
            'attribution' => 'Test Author, CC BY-SA 4.0, via Wikimedia Commons',
            'match_confidence' => 90,
            'status' => ImageStatusEnum::VERIFIED,
        ];
    }

    public function needsReview(): static
    {
        return $this->state(fn () => ['status' => ImageStatusEnum::NEEDS_REVIEW, 'match_confidence' => 40]);
    }

    public function rejected(): static
    {
        return $this->state(fn () => ['status' => ImageStatusEnum::REJECTED, 'is_primary' => false]);
    }
}
