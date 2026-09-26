<?php

namespace App\Services\Pos;

use App\Enums\OrderItemStatusEnum;
use App\Enums\OrderStatusEnum;
use App\Enums\PaymentStatusEnum;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use Illuminate\Support\Carbon;

/**
 * Basic POS sales reports for a date range. "Sales" means completed orders,
 * dated by completed_at. Item/category figures are line totals before
 * order-level discount/VAT/service charge (the order totals carry those).
 */
class PosReportService
{
    /**
     * @return array<string, mixed>
     */
    public function build(Carbon $from, Carbon $to): array
    {
        $from = $from->copy()->startOfDay();
        $to = $to->copy()->endOfDay();

        $completedIds = Order::query()
            ->where('status', OrderStatusEnum::COMPLETED->value)
            ->whereBetween('completed_at', [$from, $to])
            ->select('id');

        $summary = Order::query()
            ->whereIn('id', $completedIds)
            ->selectRaw('COUNT(*) as orders_count')
            ->selectRaw('COALESCE(SUM(subtotal), 0) as subtotal')
            ->selectRaw('COALESCE(SUM(discount), 0) as discount')
            ->selectRaw('COALESCE(SUM(service_charge), 0) as service_charge')
            ->selectRaw('COALESCE(SUM(vat), 0) as vat')
            ->selectRaw('COALESCE(SUM(grand_total), 0) as grand_total')
            ->toBase()
            ->first();

        $ordersCount = (int) $summary->orders_count;

        $byMethod = Payment::query()
            ->whereIn('order_id', $completedIds)
            ->where('status', PaymentStatusEnum::COMPLETED->value)
            ->groupBy('payment_method')
            ->selectRaw('payment_method, SUM(amount) as total')
            ->toBase()
            ->pluck('total', 'payment_method');

        $mixedOrderIds = Payment::query()
            ->whereIn('order_id', $completedIds)
            ->where('status', PaymentStatusEnum::COMPLETED->value)
            ->groupBy('order_id')
            ->havingRaw('COUNT(DISTINCT payment_method) > 1')
            ->pluck('order_id');

        $mixed = Order::query()->whereIn('id', $mixedOrderIds)->selectRaw('COUNT(*) as orders_count, COALESCE(SUM(grand_total), 0) as total')->toBase()->first();

        $soldItems = OrderItem::query()
            ->whereIn('order_id', $completedIds)
            ->where('kitchen_status', '!=', OrderItemStatusEnum::CANCELLED->value);

        $byItem = (clone $soldItems)
            ->groupBy('item_name')
            ->selectRaw('item_name, SUM(quantity) as quantity, SUM(line_total) as total')
            ->orderByDesc('total')
            ->toBase()
            ->get();

        $byCategory = (clone $soldItems)
            ->groupBy('category_name')
            ->selectRaw("COALESCE(category_name, 'Uncategorized') as category_name, SUM(quantity) as quantity, SUM(line_total) as total")
            ->orderByDesc('total')
            ->toBase()
            ->get();

        $byTable = Order::query()
            ->whereIn('id', $completedIds)
            ->groupBy('order_type', 'table_name')
            ->selectRaw('order_type, table_name, COUNT(*) as orders_count, SUM(grand_total) as total')
            ->orderByDesc('total')
            ->toBase()
            ->get();

        $cancelledOrders = Order::query()
            ->where('status', OrderStatusEnum::CANCELLED->value)
            ->whereBetween('cancelled_at', [$from, $to])
            ->with('cancelledBy:id,name')
            ->orderByDesc('cancelled_at')
            ->get();

        // Items cancelled one by one on orders that carried on - whole
        // cancelled orders are listed above instead.
        $cancelledItems = OrderItem::query()
            ->where('kitchen_status', OrderItemStatusEnum::CANCELLED->value)
            ->whereBetween('cancelled_at', [$from, $to])
            ->whereHas('order', fn ($q) => $q->where('status', '!=', OrderStatusEnum::CANCELLED->value))
            ->with(['order:id,order_number,order_type,table_name', 'cancelledBy:id,name'])
            ->orderByDesc('cancelled_at')
            ->get();

        return [
            'from' => $from,
            'to' => $to,
            'orders_count' => $ordersCount,
            'subtotal' => (float) $summary->subtotal,
            'discount' => (float) $summary->discount,
            'service_charge' => (float) $summary->service_charge,
            'vat' => (float) $summary->vat,
            'sales' => (float) $summary->grand_total,
            'average_order' => $ordersCount ? round((float) $summary->grand_total / $ordersCount, 2) : 0.0,
            'cash_total' => (float) ($byMethod['cash'] ?? 0),
            'card_total' => (float) ($byMethod['card'] ?? 0),
            'mixed_orders' => (int) $mixed->orders_count,
            'mixed_total' => (float) $mixed->total,
            'by_item' => $byItem,
            'by_category' => $byCategory,
            'by_table' => $byTable,
            'cancelled_orders' => $cancelledOrders,
            'cancelled_orders_value' => (float) $cancelledOrders->sum('grand_total'),
            'cancelled_items' => $cancelledItems,
            'cancelled_items_value' => (float) $cancelledItems->sum('line_total'),
        ];
    }
}
