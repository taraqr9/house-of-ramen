<?php

namespace App\Http\Resources;

use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One order line with its snapshots (name/price/category as sold) and full
 * kitchen timeline.
 *
 * @mixin OrderItem
 */
class OrderItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'menu_item_id' => $this->restaurant_menu_item_id,
            'category_id' => $this->restaurant_menu_category_id,
            'name' => $this->item_name,
            'category_name' => $this->category_name,
            'quantity' => $this->quantity,
            'unit_price' => (string) $this->unit_price,
            'line_total' => (string) $this->line_total,
            'note' => $this->note,
            'round_no' => $this->round_no,
            'status' => $this->kitchen_status->value,
            'status_label' => $this->kitchen_status->label(),
            'sent_at' => $this->sent_at?->toIso8601String(),
            'preparing_at' => $this->preparing_at?->toIso8601String(),
            'ready_at' => $this->ready_at?->toIso8601String(),
            'ready_acknowledged_at' => $this->ready_acknowledged_at?->toIso8601String(),
            'served_at' => $this->served_at?->toIso8601String(),
            'served_by' => $this->whenLoaded('servedBy', fn () => $this->servedBy ? ['id' => $this->servedBy->id, 'name' => $this->servedBy->name] : null),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'cancelled_by' => $this->whenLoaded('cancelledBy', fn () => $this->cancelledBy ? ['id' => $this->cancelledBy->id, 'name' => $this->cancelledBy->name] : null),
            'cancellation_reason' => $this->cancellation_reason,
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
