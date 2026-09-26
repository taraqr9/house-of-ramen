<?php

namespace App\Services\Pos;

use App\Enums\OrderItemStatusEnum;
use App\Models\OrderItem;
use Illuminate\Support\Carbon;

/**
 * Incremental item feed for the polling screens (Kitchen, Ready to Serve).
 *
 * First call (no cursor): every item currently in $statuses on an active
 * order. Later calls: only items touched since the cursor, in ANY status,
 * so the client also learns about items that left its screen (started,
 * served, cancelled). A 2s overlap covers same-second writes; the client
 * merges by id so repeats are harmless.
 */
class ItemFeedService
{
    /**
     * $recentCancelledMinutes: also include items cancelled within that
     * window on still-running orders in the full snapshot, so a screen
     * opened after a kitchen rejection still shows it (Ready to Serve).
     *
     * @param  list<OrderItemStatusEnum>  $statuses
     * @return array{server_time: string, full: bool, items: list<array<string, mixed>>}
     */
    public function feed(array $statuses, ?string $since, ?int $recentCancelledMinutes = null): array
    {
        $serverTime = now();
        $sinceAt = $since ? Carbon::parse($since) : null;

        // A stale cursor (tab asleep for hours) is cheaper to answer with a
        // fresh full snapshot.
        $full = ! $sinceAt || $sinceAt->lt($serverTime->copy()->subHours(6));

        $query = OrderItem::query()
            ->with(['order:id,order_number,order_type,table_name,general_note,status', 'cancelledBy:id,name'])
            ->orderBy('sent_at')
            ->orderBy('id');

        if ($full) {
            $query->whereHas('order', fn ($q) => $q->active())
                ->where(function ($q) use ($statuses, $recentCancelledMinutes, $serverTime) {
                    $q->whereIn('kitchen_status', array_map(fn ($s) => $s->value, $statuses));

                    if ($recentCancelledMinutes) {
                        $q->orWhere(fn ($cq) => $cq
                            ->where('kitchen_status', OrderItemStatusEnum::CANCELLED->value)
                            ->where('cancelled_at', '>=', $serverTime->copy()->subMinutes($recentCancelledMinutes)));
                    }
                });
        } else {
            $query->where('updated_at', '>=', $sinceAt->copy()->subSeconds(2));
        }

        return [
            'server_time' => $serverTime->toIso8601String(),
            'full' => $full,
            'items' => $query->limit(500)->get()->map(fn (OrderItem $item) => [
                'id' => $item->id,
                'order_id' => $item->order_id,
                'order_number' => $item->order?->order_number,
                'table' => $item->order?->displayTable(),
                'order_note' => $item->order?->general_note,
                'order_active' => (bool) $item->order?->isActive(),
                'round_no' => $item->round_no,
                'name' => $item->item_name,
                'quantity' => $item->quantity,
                'note' => $item->note,
                'status' => $item->kitchen_status->value,
                'acknowledged' => $item->ready_acknowledged_at !== null,
                'sent_at' => $item->sent_at?->toIso8601String(),
                'ready_at' => $item->ready_at?->toIso8601String(),
                'updated_at' => $item->updated_at?->toIso8601String(),
                'cancelled_at' => $item->cancelled_at?->toIso8601String(),
                'cancellation_reason' => $item->cancellation_reason,
                'cancelled_by' => $item->cancelledBy?->name,
            ])->all(),
        ];
    }
}
