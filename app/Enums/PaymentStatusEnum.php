<?php

namespace App\Enums;

enum PaymentStatusEnum: string
{
    case COMPLETED = 'completed';
    case VOIDED = 'voided';

    public function label(): string
    {
        return match ($this) {
            self::COMPLETED => 'Completed',
            self::VOIDED => 'Voided',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::COMPLETED => 'bg-success',
            self::VOIDED => 'bg-danger',
        };
    }
}
