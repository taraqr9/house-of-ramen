<?php

namespace App\Enums;

enum OrderTypeEnum: string
{
    case DINE_IN = 'dine_in';
    case TAKEAWAY = 'takeaway';

    public function label(): string
    {
        return match ($this) {
            self::DINE_IN => 'Dine In',
            self::TAKEAWAY => 'Takeaway',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()])->all();
    }
}
