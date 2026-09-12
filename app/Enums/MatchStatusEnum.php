<?php

namespace App\Enums;

enum MatchStatusEnum: string
{
    case NEW = 'new';
    case MATCHED = 'matched';
    case DUPLICATE = 'duplicate';
    case CONFLICT = 'conflict';
    case NEEDS_REVIEW = 'needs_review';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::NEW => 'New',
            self::MATCHED => 'Matched',
            self::DUPLICATE => 'Duplicate',
            self::CONFLICT => 'Conflict',
            self::NEEDS_REVIEW => 'Needs Review',
            self::APPROVED => 'Approved',
            self::REJECTED => 'Rejected',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::NEW => 'bg-primary',
            self::MATCHED => 'bg-info',
            self::DUPLICATE => 'bg-secondary',
            self::CONFLICT => 'bg-danger',
            self::NEEDS_REVIEW => 'bg-warning',
            self::APPROVED => 'bg-success',
            self::REJECTED => 'bg-dark',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()])->all();
    }
}
