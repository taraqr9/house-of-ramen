<?php

namespace App\Enums;

enum DiscountTypeEnum: string
{
    case FIXED = 'fixed';
    case PERCENT = 'percent';

    public function label(): string
    {
        return match ($this) {
            self::FIXED => 'Fixed Amount',
            self::PERCENT => 'Percent',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()])->all();
    }
}
