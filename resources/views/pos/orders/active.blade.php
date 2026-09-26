@extends('layout.master', ['page_title' => $page_title])

@section('CSSheet')
    @include('pos.partials.styles')
@endsection

@section('content')
    <div class="page-content">
        <div class="container-fluid">

            @include('partials.breadcrumb', [
                'items' => [
                    ['label' => 'POS Operations'],
                    ['label' => 'Active Orders'],
                ],
            ])

            <div class="card">
                <div class="card-body">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                        <div>
                            <h5 class="card-title mb-1">Active Orders</h5>
                            <p class="text-muted small mb-0">Running dine-in and takeaway orders. Refreshes every 30 seconds.</p>
                        </div>
                        @can('order-create')
                            <a href="{{ route('pos-terminal.index') }}" class="btn btn-success pos-btn-lg"><i class="mdi mdi-plus"></i> New Order</a>
                        @endcan
                    </div>

                    <form action="{{ route('pos-orders.active') }}" method="GET">
                        <div class="row g-2 align-items-end">
                            <div class="col-6 col-md-2">
                                <label class="form-label">Type</label>
                                <select name="order_type" class="form-control select2">
                                    <option value="">All Types</option>
                                    @foreach($types as $value => $label)
                                        <option value="{{ $value }}" @selected(request('order_type') === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-6 col-md-2">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-control select2">
                                    <option value="">All Status</option>
                                    @foreach($statuses as $value => $label)
                                        <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-6 col-md-2">
                                <label class="form-label">Table</label>
                                <select name="dining_table_id" class="form-control select2">
                                    <option value="">All Tables</option>
                                    @foreach($tables as $table)
                                        <option value="{{ $table->id }}" @selected((string) request('dining_table_id') === (string) $table->id)>{{ $table->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-6 col-md-4">
                                <label class="form-label">Keyword</label>
                                <input type="text" name="keyword" value="{{ request('keyword') }}" class="form-control" placeholder="Order number, table or note">
                            </div>
                            <div class="col-12 col-md-2 d-flex gap-2">
                                <a href="{{ route('pos-orders.active') }}" class="btn btn-light flex-grow-1">Reset</a>
                                <button type="submit" class="btn btn-primary flex-grow-1"><i class="mdi mdi-magnify"></i> Search</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <div class="row g-3">
                @forelse($orders as $order)
                    <div class="col-12 col-sm-6 col-lg-4 col-xl-3">
                        <a href="{{ route('pos-orders.show', $order) }}" class="text-decoration-none text-body">
                            <div class="pos-ticket h-100 {{ $order->ready_count > 0 ? 'border-info' : '' }}">
                                <div class="pos-ticket-head">
                                    <div>
                                        <div class="fs-4 fw-bold">{{ $order->displayTable() }}</div>
                                        <div class="small text-muted">{{ $order->order_number }}</div>
                                    </div>
                                    <span class="badge {{ $order->status->badgeClass() }}">{{ $order->status->label() }}</span>
                                </div>
                                <div class="pos-ticket-row small">
                                    <div class="d-flex justify-content-between"><span>Opened</span><span>{{ $order->opened_at?->format('h:i A') }} ({{ $order->opened_at?->diffForHumans(short: true) }})</span></div>
                                    @if($order->guest_count)
                                        <div class="d-flex justify-content-between"><span>Guests</span><span>{{ $order->guest_count }}</span></div>
                                    @endif
                                    <div class="d-flex justify-content-between"><span>In kitchen</span><span>{{ $order->pending_count }}</span></div>
                                    <div class="d-flex justify-content-between {{ $order->ready_count ? 'text-info fw-bold' : '' }}"><span>Ready to serve</span><span>{{ $order->ready_count }}</span></div>
                                </div>
                                <div class="pos-ticket-row d-flex justify-content-between fw-bold">
                                    <span>Total</span><span>{{ number_format($order->grand_total, 2) }}</span>
                                </div>
                            </div>
                        </a>
                    </div>
                @empty
                    <div class="col-12">
                        <div class="card"><div class="card-body text-center text-muted">No active orders.</div></div>
                    </div>
                @endforelse
            </div>

            <div class="d-flex justify-content-end mt-3">
                {{ $orders->links('partials.pagination') }}
            </div>

        </div>
    </div>
@endsection

@section('JScript')
    <script>
        $(document).ready(function () {
            $('.select2').select2({width: '100%', allowClear: true});
        });

        // Keep the list fresh, but never while someone is typing a filter.
        setInterval(() => {
            if (!$(document.activeElement).is('input, select, .select2-search__field')) window.location.reload();
        }, 30000);
    </script>
@endsection
