<?php

namespace App\Enums;

/**
 * Why the kitchen rejected an item. Stored on order_items as readable text
 * (label, plus the typed detail for "Other") so history and reports keep
 * the reason even if this list changes.
 */
enum KitchenCancelReasonEnum: string
{
    case OUT_OF_STOCK = 'out_of_stock';
    case INGREDIENT_UNAVAILABLE = 'ingredient_unavailable';
    case EQUIPMENT_ISSUE = 'equipment_issue';
    case UNABLE_TO_PREPARE = 'unable_to_prepare';
    case DUPLICATE_ITEM = 'duplicate_item';
    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            self::OUT_OF_STOCK => 'Out of stock',
            self::INGREDIENT_UNAVAILABLE => 'Ingredient unavailable',
            self::EQUIPMENT_ISSUE => 'Kitchen equipment issue',
            self::UNABLE_TO_PREPARE => 'Unable to prepare',
            self::DUPLICATE_ITEM => 'Duplicate item',
            self::OTHER => 'Other',
        };
    }

    /**
     * Reasons where it may also make sense to take the dish off the menu -
     * only used to *offer* that separate action, never to do it.
     */
    public function suggestsUnavailable(): bool
    {
        return in_array($this, [self::OUT_OF_STOCK, self::INGREDIENT_UNAVAILABLE], true);
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()])->all();
    }
}
