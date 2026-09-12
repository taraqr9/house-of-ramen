<?php

namespace App\Enums;

enum NetworkTypeEnum: string
{
    case TWO_G = '2G';
    case THREE_G = '3G';
    case FOUR_G = '4G';
    case FIVE_G = '5G';

    public function label(): string
    {
        return $this->value;
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()])->all();
    }
}
