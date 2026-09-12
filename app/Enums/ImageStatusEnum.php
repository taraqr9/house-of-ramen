<?php

namespace App\Enums;

enum ImageStatusEnum: string
{
    case NEEDS_REVIEW = 'needs_review';
    case VERIFIED = 'verified';
    case REJECTED = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::NEEDS_REVIEW => 'Needs Review',
            self::VERIFIED => 'Verified',
            self::REJECTED => 'Rejected',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::NEEDS_REVIEW => 'bg-warning',
            self::VERIFIED => 'bg-success',
            self::REJECTED => 'bg-danger',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()])->all();
    }
}
