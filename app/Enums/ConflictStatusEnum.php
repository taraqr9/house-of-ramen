<?php

namespace App\Enums;

enum ConflictStatusEnum: string
{
    case OPEN = 'open';
    case AUTO_RESOLVED = 'auto_resolved';
    case RESOLVED = 'resolved';
    case IGNORED = 'ignored';

    public function label(): string
    {
        return match ($this) {
            self::OPEN => 'Open',
            self::AUTO_RESOLVED => 'Auto Resolved',
            self::RESOLVED => 'Resolved',
            self::IGNORED => 'Ignored',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::OPEN => 'bg-warning',
            self::AUTO_RESOLVED => 'bg-info',
            self::RESOLVED => 'bg-success',
            self::IGNORED => 'bg-secondary',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()])->all();
    }
}
