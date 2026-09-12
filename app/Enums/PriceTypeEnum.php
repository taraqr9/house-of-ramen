<?php

namespace App\Enums;

enum PriceTypeEnum: string
{
    case OFFICIAL_BD = 'official_bd';
    case UNOFFICIAL_BD = 'unofficial_bd';

    public function label(): string
    {
        return match ($this) {
            self::OFFICIAL_BD => 'Official BD Price',
            self::UNOFFICIAL_BD => 'Unofficial BD Price',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::OFFICIAL_BD => 'bg-success',
            self::UNOFFICIAL_BD => 'bg-warning',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()])->all();
    }
}
