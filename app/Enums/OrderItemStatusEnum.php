<?php

namespace App\Enums;

enum OrderItemStatusEnum: string
{
    case PENDING = 'pending';
    case PREPARING = 'preparing';
    case READY = 'ready';
    case SERVED = 'served';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::PREPARING => 'Preparing',
            self::READY => 'Ready',
            self::SERVED => 'Served',
            self::CANCELLED => 'Cancelled',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::PENDING => 'bg-secondary',
            self::PREPARING => 'bg-warning',
            self::READY => 'bg-info',
            self::SERVED => 'bg-success',
            self::CANCELLED => 'bg-danger',
        };
    }

    /**
     * The only allowed forward moves. Cancellation is handled separately
     * (OrderService::cancelItem) since it needs its own permission/reason.
     */
    public function canTransitionTo(self $next): bool
    {
        return match ($this) {
            self::PENDING => $next === self::PREPARING,
            self::PREPARING => $next === self::READY,
            self::READY => $next === self::SERVED,
            self::SERVED, self::CANCELLED => false,
        };
    }

    public function isCancellable(): bool
    {
        return in_array($this, [self::PENDING, self::PREPARING, self::READY], true);
    }

    /**
     * Still owed to the table - blocks order completion.
     */
    public function isOutstanding(): bool
    {
        return in_array($this, [self::PENDING, self::PREPARING, self::READY], true);
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()])->all();
    }
}
