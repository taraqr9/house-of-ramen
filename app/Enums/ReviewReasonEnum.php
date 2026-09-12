<?php

namespace App\Enums;

enum ReviewReasonEnum: string
{
    case POSSIBLE_DUPLICATE = 'possible_duplicate';
    case LOW_CONFIDENCE = 'low_confidence';
    case MISSING_DATA = 'missing_data';
    case MANUAL_FLAG = 'manual_flag';
    case PRICE_OUTLIER = 'price_outlier';
    case IMAGE_NEEDS_REVIEW = 'image_needs_review';

    public function label(): string
    {
        return match ($this) {
            self::POSSIBLE_DUPLICATE => 'Possible Duplicate',
            self::LOW_CONFIDENCE => 'Low Confidence',
            self::MISSING_DATA => 'Missing Data',
            self::MANUAL_FLAG => 'Manually Flagged',
            self::PRICE_OUTLIER => 'Price Outlier',
            self::IMAGE_NEEDS_REVIEW => 'Image Needs Review',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()])->all();
    }
}
