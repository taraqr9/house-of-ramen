@extends('layout.master', ['page_title' => $page_title])

@section('CSSheet')
    @include('pos.partials.styles')
@endsection

@section('content')
    <div class="page-content">
        <div class="container-fluid">

            @include('partials.breadcrumb', [
                'items' => [
                    ['label' => 'Completed Orders', 'url' => route('pos-orders.completed')],
                    ['label' => $order->order_number],
                ],
            ])

            <div class="card">
                <div class="card-body">
                    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
                        <div>@include('pos.orders._header')</div>
                        @can('billing-view')
                            @if($order->status->value === 'completed')
                                <a href="{{ route('pos-billing.print', $order) }}" target="_blank" class="btn btn-outline-secondary"><i class="bx bx-printer"></i> Reprint Receipt</a>
                            @endif
                        @endcan
                    </div>
                    <div class="row small text-muted mt-3">
                        <div class="col-md-4">Opened by: {{ $order->openedBy?->name ?? '—' }}</div>
                        @if($order->completed_at)
                            <div class="col-md-4">Completed: {{ $order->completed_at->format('d M Y, h:i A') }} by {{ $order->completedBy?->name ?? '—' }}</div>
                        @endif
                        @if($order->cancelled_at)
                            <div class="col-md-8 text-danger">Cancelled: {{ $order->cancelled_at->format('d M Y, h:i A') }} by {{ $order->cancelledBy?->name ?? '—' }} — {{ $order->cancellation_reason }}</div>
                        @endif
                    </div>
                    <div class="alert alert-secondary py-2 mt-3 mb-0 small">This order is closed and read-only.</div>
                </div>
            </div>

            <div class="row">
                <div class="col-lg-7">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title mb-3">Items</h5>
                            @foreach($order->items->groupBy('round_no') as $roundNo => $roundItems)
                                <h6 class="text-muted mt-3">Round {{ $roundNo }} · sent {{ $roundItems->first()->sent_at?->format('h:i A') }}</h6>
                                <div class="table-responsive">
                                    <table class="table table-sm align-middle">
                                        <thead class="table-light">
                                        <tr>
                                            <th>Item</th>
                                            <th class="text-center">Qty</th>
                                            <th class="text-end">Price</th>
                                            <th class="text-end">Total</th>
                                            <th>Status / Timeline</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        @foreach($roundItems as $item)
                                            <tr class="{{ $item->isCancelled() ? 'text-muted' : '' }}">
                                                <td>
                                                    <div class="{{ $item->isCancelled() ? 'text-decoration-line-through' : 'fw-semibold' }}">{{ $item->item_name }}</div>
                                                    @if($item->category_name)<div class="small text-muted">{{ $item->category_name }}</div>@endif
                                                    @if($item->note)<span class="pos-note">{{ $item->note }}</span>@endif
                                                </td>
                                                <td class="text-center">{{ $item->quantity }}</td>
                                                <td class="text-end">{{ number_format($item->unit_price, 2) }}</td>
                                                <td class="text-end">{{ number_format($item->line_total, 2) }}</td>
                                                <td class="small">
                                                    <span class="badge {{ $item->kitchen_status->badgeClass() }}">{{ $item->kitchen_status->label() }}</span>
                                                    <div>
                                                        @if($item->preparing_at)Prep {{ $item->preparing_at->format('h:i A') }} · @endif
                                                        @if($item->ready_at)Ready {{ $item->ready_at->format('h:i A') }} · @endif
                                                        @if($item->served_at)Served {{ $item->served_at->format('h:i A') }}{{ $item->servedBy ? ' by '.$item->servedBy->name : '' }}@endif
                                                    </div>
                                                    @if($item->isCancelled())
                                                        <div class="text-danger">Cancelled {{ $item->cancelled_at?->format('h:i A') }}{{ $item->cancelledBy ? ' by '.$item->cancelledBy->name : '' }}: {{ $item->cancellation_reason }}</div>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="col-lg-5">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title mb-3">Totals</h5>
                            @include('pos.orders._summary')
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title mb-3">Payments</h5>
                            @include('pos.orders._payments')
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
@endsection
