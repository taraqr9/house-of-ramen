<?php

namespace App\Enums;

enum StatusEnum: int
{
    case INACTIVE = 0;
    case ACTIVE = 1;

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => 'Active',
            self::INACTIVE => 'Inactive',
        };
    }

    public function yesNoLabel(): string
    {
        return match ($this) {
            self::ACTIVE => 'Yes',
            self::INACTIVE => 'No',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::ACTIVE => 'bg-success',
            self::INACTIVE => 'bg-danger',
        };
    }

    public static function options(): array
    {
        return [
            self::ACTIVE->value => self::ACTIVE->label(),
            self::INACTIVE->value => self::INACTIVE->label(),
        ];
    }

    public static function yesNoOptions(): array
    {
        return [
            self::ACTIVE->value => self::ACTIVE->yesNoLabel(),
            self::INACTIVE->value => self::INACTIVE->yesNoLabel(),
        ];
    }
}
