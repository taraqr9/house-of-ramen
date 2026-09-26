<?php

namespace App\Enums;

enum OrderStatusEnum: string
{
    case OPEN = 'open';
    case BILL_REQUESTED = 'bill_requested';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::OPEN => 'Open',
            self::BILL_REQUESTED => 'Bill Requested',
            self::COMPLETED => 'Completed',
            self::CANCELLED => 'Cancelled',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::OPEN => 'bg-primary',
            self::BILL_REQUESTED => 'bg-warning',
            self::COMPLETED => 'bg-success',
            self::CANCELLED => 'bg-danger',
        };
    }

    /**
     * Active = still running on the floor: holds its table and can take more
     * items, discounts, and payments.
     */
    public function isActive(): bool
    {
        return in_array($this, self::activeCases(), true);
    }

    /**
     * @return list<self>
     */
    public static function activeCases(): array
    {
        return [self::OPEN, self::BILL_REQUESTED];
    }

    /**
     * @return list<string>
     */
    public static function activeValues(): array
    {
        return array_map(fn (self $case) => $case->value, self::activeCases());
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()])->all();
    }
}
