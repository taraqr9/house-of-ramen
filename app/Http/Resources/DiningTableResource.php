<?php

namespace App\Http\Resources;

use App\Enums\OrderStatusEnum;
use App\Models\DiningTable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A table with its occupancy - derived from the running order (never a
 * stored flag): available / occupied / bill_requested.
 *
 * @mixin DiningTable
 */
class DiningTableResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $order = $this->relationLoaded('activeOrder') ? $this->activeOrder : null;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'area' => $this->area,
            'capacity' => $this->capacity,
            'display_order' => $this->display_order,
            'is_active' => (bool) $this->is_active,
            'status' => match (true) {
                $order === null => 'available',
                $order->status === OrderStatusEnum::BILL_REQUESTED => 'bill_requested',
                default => 'occupied',
            },
            'active_order' => $order ? [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'status' => $order->status->value,
                'guest_count' => $order->guest_count,
                'grand_total' => (string) $order->grand_total,
                'opened_at' => $order->opened_at?->toIso8601String(),
            ] : null,
        ];
    }
}
