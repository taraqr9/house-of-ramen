<?php

namespace App\Enums;

enum AvailabilityStatusEnum: string
{
    case IN_STOCK = 'in_stock';
    case OUT_OF_STOCK = 'out_of_stock';
    case PREORDER = 'preorder';
    case DISCONTINUED = 'discontinued';

    public function label(): string
    {
        return match ($this) {
            self::IN_STOCK => 'In Stock',
            self::OUT_OF_STOCK => 'Out of Stock',
            self::PREORDER => 'Pre-order',
            self::DISCONTINUED => 'Discontinued',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::IN_STOCK => 'bg-success',
            self::OUT_OF_STOCK => 'bg-danger',
            self::PREORDER => 'bg-info',
            self::DISCONTINUED => 'bg-secondary',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()])->all();
    }
}
