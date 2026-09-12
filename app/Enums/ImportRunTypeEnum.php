<?php

namespace App\Enums;

enum ImportRunTypeEnum: string
{
    case INITIAL = 'initial';
    case NIGHTLY = 'nightly';
    case MANUAL = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::INITIAL => 'Initial Import',
            self::NIGHTLY => 'Nightly Update',
            self::MANUAL => 'Manual Import',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()])->all();
    }
}
