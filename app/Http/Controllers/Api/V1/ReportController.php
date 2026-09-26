<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\PosReportRequest;
use App\Services\Pos\PosReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

/**
 * Figures come straight from PosReportService (the same numbers as the web
 * Reports page) - nothing is recalculated here. Defaults to today.
 */
class ReportController extends ApiController
{
    public function __construct(private readonly PosReportService $reports) {}

    public function summary(PosReportRequest $request): JsonResponse
    {
        $report = $this->build($request);

        return $this->ok($this->summaryFrom($report));
    }

    public function sales(PosReportRequest $request): JsonResponse
    {
        $report = $this->build($request);
        $money = fn ($v) => number_format((float) $v, 2, '.', '');

        return $this->ok([
            ...$this->summaryFrom($report),
            'by_item' => $report['by_item']->map(fn ($r) => ['name' => $r->item_name, 'quantity' => (int) $r->quantity, 'total' => $money($r->total)])->values(),
            'by_category' => $report['by_category']->map(fn ($r) => ['name' => $r->category_name, 'quantity' => (int) $r->quantity, 'total' => $money($r->total)])->values(),
            'by_table' => $report['by_table']->map(fn ($r) => [
                'order_type' => $r->order_type,
                'table' => $r->order_type === 'takeaway' ? 'Takeaway' : $r->table_name,
                'orders_count' => (int) $r->orders_count,
                'total' => $money($r->total),
            ])->values(),
            'cancelled_orders' => $report['cancelled_orders']->map(fn ($o) => [
                'id' => $o->id, 'order_number' => $o->order_number, 'table' => $o->displayTable(),
                'reason' => $o->cancellation_reason, 'cancelled_by' => $o->cancelledBy?->name,
                'cancelled_at' => $o->cancelled_at?->toIso8601String(), 'value' => $money($o->grand_total),
            ])->values(),
            'cancelled_items' => $report['cancelled_items']->map(fn ($i) => [
                'id' => $i->id, 'order_number' => $i->order?->order_number, 'name' => $i->item_name, 'quantity' => $i->quantity,
                'reason' => $i->cancellation_reason, 'cancelled_by' => $i->cancelledBy?->name,
                'cancelled_at' => $i->cancelled_at?->toIso8601String(), 'value' => $money($i->line_total),
            ])->values(),
        ]);
    }

    private function build(PosReportRequest $request): array
    {
        $from = $request->filled('date_from') ? Carbon::parse($request->input('date_from')) : today();
        $to = $request->filled('date_to') ? Carbon::parse($request->input('date_to')) : $from->copy();

        return $this->reports->build($from, $to);
    }

    private function summaryFrom(array $report): array
    {
        $money = fn ($v) => number_format((float) $v, 2, '.', '');

        return [
            'date_from' => $report['from']->toDateString(),
            'date_to' => $report['to']->toDateString(),
            'orders_count' => $report['orders_count'],
            'sales' => $money($report['sales']),
            'average_order' => $money($report['average_order']),
            'subtotal' => $money($report['subtotal']),
            'discount' => $money($report['discount']),
            'service_charge' => $money($report['service_charge']),
            'vat' => $money($report['vat']),
            'cash_total' => $money($report['cash_total']),
            'card_total' => $money($report['card_total']),
            'mixed_orders' => $report['mixed_orders'],
            'mixed_total' => $money($report['mixed_total']),
            'cancelled_orders_count' => $report['cancelled_orders']->count(),
            'cancelled_orders_value' => $money($report['cancelled_orders_value']),
            'cancelled_items_value' => $money($report['cancelled_items_value']),
        ];
    }
}
