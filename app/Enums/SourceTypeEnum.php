<?php

namespace App\Enums;

enum SourceTypeEnum: string
{
    case MANUFACTURER = 'manufacturer';
    case BD_RETAILER = 'bd_retailer';
    case GLOBAL_RETAILER = 'global_retailer';
    case AI_ASSISTED = 'ai_assisted';
    case MANUAL = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::MANUFACTURER => 'Manufacturer',
            self::BD_RETAILER => 'Bangladesh Retailer',
            self::GLOBAL_RETAILER => 'Global Retailer',
            self::AI_ASSISTED => 'AI Assisted',
            self::MANUAL => 'Manual / Editorial',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::MANUFACTURER => 'bg-success',
            self::BD_RETAILER => 'bg-primary',
            self::GLOBAL_RETAILER => 'bg-info',
            self::AI_ASSISTED => 'bg-warning',
            self::MANUAL => 'bg-secondary',
        };
    }

    /**
     * Default reliability score (0-100) applied to a newly registered source of this type.
     * Overridable per-source via phone_sources.reliability_score.
     */
    public function defaultReliability(): int
    {
        return match ($this) {
            self::MANUFACTURER => 95,
            self::BD_RETAILER => 80,
            self::GLOBAL_RETAILER => 75,
            self::MANUAL => 60,
            self::AI_ASSISTED => 45,
        };
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()])->all();
    }
}
