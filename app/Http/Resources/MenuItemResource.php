<?php

namespace App\Http\Resources;

use App\Models\RestaurantMenuItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * A sellable menu item for the POS - only what the terminal needs (no
 * slug/description/SEO). is_available=false items can be shown greyed out;
 * the server rejects them in a round anyway. There is no SKU/code column.
 *
 * @mixin RestaurantMenuItem
 */
class MenuItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'category_id' => $this->restaurant_menu_category_id,
            'category_name' => $this->category?->name,
            'name' => $this->name,
            'price' => (string) $this->price,
            'price_note' => $this->price_note,
            'is_available' => (bool) $this->is_available,
            'is_featured' => (bool) $this->is_featured,
            'is_new' => (bool) $this->is_new,
            'image_url' => $this->image_path ? Storage::disk('public')->url($this->image_path) : null,
            'display_order' => $this->display_order,
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
