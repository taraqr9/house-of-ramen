@extends('layout.master', ['page_title' => $page_title])

@php
    $range = $report['from']->isSameDay($report['to'])
        ? $report['from']->format('d M Y')
        : $report['from']->format('d M Y').' – '.$report['to']->format('d M Y');
    $quick = [
        'Today' => [today(), today()],
        'Yesterday' => [today()->subDay(), today()->subDay()],
        'This Week' => [today()->startOfWeek(), today()],
        'This Month' => [today()->startOfMonth(), today()],
    ];
@endphp

@section('content')
    <div class="page-content">
        <div class="container-fluid">

            @include('partials.breadcrumb', [
                'items' => [
                    ['label' => 'POS Operations'],
                    ['label' => 'Reports'],
                ],
            ])

            <div class="card">
                <div class="card-body">
                    <div class="d-flex flex-wrap justify-content-between align-items-end gap-2">
                        <div>
                            <h5 class="card-title mb-1">Sales Report</h5>
                            <p class="text-muted mb-0">{{ $range }} · completed orders only</p>
                        </div>
                        <div class="d-flex flex-wrap gap-2">
                            @foreach($quick as $label => [$qFrom, $qTo])
                                <a href="{{ route('pos-reports.index', ['date_from' => $qFrom->toDateString(), 'date_to' => $qTo->toDateString()]) }}"
                                   class="btn btn-sm {{ $report['from']->isSameDay($qFrom) && $report['to']->isSameDay($qTo) ? 'btn-primary' : 'btn-outline-primary' }}">{{ $label }}</a>
                            @endforeach
                        </div>
                    </div>

                    <form action="{{ route('pos-reports.index') }}" method="GET" class="row g-2 align-items-end mt-2">
                        <div class="col-6 col-md-3">
                            <label class="form-label">From</label>
                            <input type="date" name="date_from" value="{{ $report['from']->toDateString() }}" class="form-control">
                        </div>
                        <div class="col-6 col-md-3">
                            <label class="form-label">To</label>
                            <input type="date" name="date_to" value="{{ $report['to']->toDateString() }}" class="form-control">
                        </div>
                        <div class="col-12 col-md-2">
                            <button type="submit" class="btn btn-primary w-100"><i class="mdi mdi-magnify me-1"></i> Show</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="row">
                @foreach([
                    ['Sales', $report['sales'], 'bx-money'],
                    ['Orders', $report['orders_count'], 'bx-receipt'],
                    ['Average Order', $report['average_order'], 'bx-trending-up'],
                    ['Discounts Given', $report['discount'], 'bx-purchase-tag'],
                ] as [$label, $value, $icon])
                    <div class="col-6 col-xl-3">
                        <div class="card mini-stats-wid">
                            <div class="card-body d-flex justify-content-between align-items-center">
                                <div>
                                    <p class="text-muted fw-medium mb-1">{{ $label }}</p>
                                    <h4 class="mb-0">{{ $label === 'Orders' ? $value : number_format($value, 2) }}</h4>
                                </div>
                                <i class="bx {{ $icon }} font-size-24 text-primary"></i>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="row">
                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title mb-3">Payment Methods</h5>
                            <table class="table table-sm mb-0">
                                <tr><td>Cash total</td><td class="text-end">{{ number_format($report['cash_total'], 2) }}</td></tr>
                                <tr><td>Card total</td><td class="text-end">{{ number_format($report['card_total'], 2) }}</td></tr>
                                <tr><td>Mixed-payment orders <span class="text-muted small">({{ $report['mixed_orders'] }})</span></td><td class="text-end">{{ number_format($report['mixed_total'], 2) }}</td></tr>
                            </table>
                            <p class="text-muted small mt-2 mb-0">Cash/Card totals include the cash and card parts of mixed payments.</p>
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title mb-3">Breakdown</h5>
                            <table class="table table-sm mb-0">
                                <tr><td>Subtotal</td><td class="text-end">{{ number_format($report['subtotal'], 2) }}</td></tr>
                                <tr><td>Discount</td><td class="text-end">-{{ number_format($report['discount'], 2) }}</td></tr>
                                <tr><td>Service Charge</td><td class="text-end">{{ number_format($report['service_charge'], 2) }}</td></tr>
                                <tr><td>VAT</td><td class="text-end">{{ number_format($report['vat'], 2) }}</td></tr>
                                <tr class="fw-bold"><td>Net Sales</td><td class="text-end">{{ number_format($report['sales'], 2) }}</td></tr>
                            </table>
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title mb-3">Sales by Table</h5>
                            <table class="table table-sm mb-0">
                                <thead class="table-light"><tr><th>Table</th><th class="text-center">Orders</th><th class="text-end">Sales</th></tr></thead>
                                <tbody>
                                @forelse($report['by_table'] as $row)
                                    <tr>
                                        <td>{{ $row->order_type === 'takeaway' ? 'Takeaway' : ($row->table_name ?? '—') }}</td>
                                        <td class="text-center">{{ $row->orders_count }}</td>
                                        <td class="text-end">{{ number_format($row->total, 2) }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="3" class="text-center text-muted">No sales.</td></tr>
                                @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title mb-3">Sales by Category</h5>
                            <div class="table-responsive">
                                <table class="table table-sm mb-0">
                                    <thead class="table-light"><tr><th>Category</th><th class="text-center">Qty</th><th class="text-end">Sales (before charges)</th></tr></thead>
                                    <tbody>
                                    @forelse($report['by_category'] as $row)
                                        <tr><td>{{ $row->category_name }}</td><td class="text-center">{{ $row->quantity }}</td><td class="text-end">{{ number_format($row->total, 2) }}</td></tr>
                                    @empty
                                        <tr><td colspan="3" class="text-center text-muted">No sales.</td></tr>
                                    @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title mb-3">Sales by Menu Item</h5>
                            <div class="table-responsive" style="max-height: 480px;">
                                <table class="table table-sm mb-0">
                                    <thead class="table-light"><tr><th>Item</th><th class="text-center">Qty</th><th class="text-end">Sales (before charges)</th></tr></thead>
                                    <tbody>
                                    @forelse($report['by_item'] as $row)
                                        <tr><td>{{ $row->item_name }}</td><td class="text-center">{{ $row->quantity }}</td><td class="text-end">{{ number_format($row->total, 2) }}</td></tr>
                                    @empty
                                        <tr><td colspan="3" class="text-center text-muted">No sales.</td></tr>
                                    @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title mb-3">Cancelled Orders <span class="text-muted small">({{ $report['cancelled_orders']->count() }} · {{ number_format($report['cancelled_orders_value'], 2) }})</span></h5>
                            <div class="table-responsive">
                                <table class="table table-sm mb-0 table-mobile-cards">
                                    <thead class="table-light"><tr><th data-mc="title">Order</th><th>Table</th><th>Reason</th><th>By</th><th class="text-end">Value</th></tr></thead>
                                    <tbody>
                                    @forelse($report['cancelled_orders'] as $order)
                                        <tr>
                                            <td>
                                                @can('order-view')<a href="{{ route('pos-orders.show', $order) }}">{{ $order->order_number }}</a>@else{{ $order->order_number }}@endcan
                                                <div class="small text-muted">{{ $order->cancelled_at?->format('d M, h:i A') }}</div>
                                            </td>
                                            <td>{{ $order->displayTable() }}</td>
                                            <td class="small">{{ $order->cancellation_reason }}</td>
                                            <td class="small">{{ $order->cancelledBy?->name }}</td>
                                            <td class="text-end">{{ number_format($order->grand_total, 2) }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="5" class="text-center text-muted">None.</td></tr>
                                    @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title mb-3">Cancelled Items <span class="text-muted small">({{ $report['cancelled_items']->sum('quantity') }} · {{ number_format($report['cancelled_items_value'], 2) }})</span></h5>
                            <div class="table-responsive">
                                <table class="table table-sm mb-0 table-mobile-cards">
                                    <thead class="table-light"><tr><th data-mc="title">Item</th><th>Order</th><th>Reason</th><th>By</th><th class="text-end">Value</th></tr></thead>
                                    <tbody>
                                    @forelse($report['cancelled_items'] as $item)
                                        <tr>
                                            <td>{{ $item->quantity }} × {{ $item->item_name }}<div class="small text-muted">{{ $item->cancelled_at?->format('d M, h:i A') }}</div></td>
                                            <td class="small">{{ $item->order?->order_number }}<br>{{ $item->order?->displayTable() }}</td>
                                            <td class="small">{{ $item->cancellation_reason }}</td>
                                            <td class="small">{{ $item->cancelledBy?->name }}</td>
                                            <td class="text-end">{{ number_format($item->line_total, 2) }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="5" class="text-center text-muted">None.</td></tr>
                                    @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
@endsection
