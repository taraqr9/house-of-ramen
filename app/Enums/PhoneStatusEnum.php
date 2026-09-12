<?php

namespace App\Enums;

enum PhoneStatusEnum: string
{
    case UPCOMING = 'upcoming';
    case AVAILABLE = 'available';
    case DISCONTINUED = 'discontinued';

    public function label(): string
    {
        return match ($this) {
            self::UPCOMING => 'Upcoming',
            self::AVAILABLE => 'Available',
            self::DISCONTINUED => 'Discontinued',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::UPCOMING => 'bg-info',
            self::AVAILABLE => 'bg-success',
            self::DISCONTINUED => 'bg-secondary',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()])->all();
    }
}
