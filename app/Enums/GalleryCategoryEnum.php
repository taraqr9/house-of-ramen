<?php

namespace App\Enums;

enum GalleryCategoryEnum: string
{
    case INTERIOR = 'interior';
    case EXTERIOR = 'exterior';
    case FOOD = 'food';
    case EVENT = 'event';
    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            self::INTERIOR => 'Interior',
            self::EXTERIOR => 'Exterior',
            self::FOOD => 'Food',
            self::EVENT => 'Event',
            self::OTHER => 'Other',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()])->all();
    }
}
