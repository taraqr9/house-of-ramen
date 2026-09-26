@extends('layout.master', ['page_title' => $page_title])

@section('content')
    <div class="page-content">
        <div class="container-fluid">

            @include('partials.breadcrumb', [
                'items' => [
                    ['label' => 'POS Operations'],
                    ['label' => 'Completed Orders'],
                ],
            ])

            <div class="card">
                <div class="card-body">
                    <h5 class="card-title mb-3">Completed Orders</h5>

                    <form action="{{ route('pos-orders.completed') }}" method="GET" data-mobile-filters>
                        <div class="row g-2 align-items-end">
                            <div class="col-6 col-md-2">
                                <label class="form-label">From</label>
                                <input type="date" name="date_from" value="{{ request('date_from') }}" class="form-control">
                            </div>
                            <div class="col-6 col-md-2">
                                <label class="form-label">To</label>
                                <input type="date" name="date_to" value="{{ request('date_to') }}" class="form-control">
                            </div>
                            <div class="col-6 col-md-2">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-control select2-nosearch">
                                    <option value="completed" @selected(request('status', 'completed') === 'completed')>Completed</option>
                                    <option value="cancelled" @selected(request('status') === 'cancelled')>Cancelled</option>
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
                            <div class="col-6 col-md-2">
                                <label class="form-label">Order Number</label>
                                <input type="text" name="order_number" value="{{ request('order_number') }}" class="form-control" placeholder="ORD-...">
                            </div>
                            <div class="col-6 col-md-2">
                                <label class="form-label">Payment Method</label>
                                <select name="payment_method" class="form-control select2">
                                    <option value="">All Methods</option>
                                    @foreach(['cash' => 'Cash', 'card' => 'Card', 'mixed' => 'Mixed'] as $value => $label)
                                        <option value="{{ $value }}" @selected(request('payment_method') === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-6 col-md-3">
                                <label class="form-label">Cashier</label>
                                <select name="completed_by" class="form-control select2">
                                    <option value="">All Cashiers</option>
                                    @foreach($cashiers as $cashier)
                                        <option value="{{ $cashier->id }}" @selected((string) request('completed_by') === (string) $cashier->id)>{{ $cashier->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-6 col-md-5">
                                <label class="form-label">Keyword</label>
                                <input type="text" name="keyword" value="{{ request('keyword') }}" class="form-control" placeholder="Order number, table, note or item name">
                            </div>
                        </div>

                        <div class="d-flex justify-content-end gap-2 mt-3">
                            <a href="{{ route('pos-orders.completed') }}" class="btn btn-light waves-effect">Reset</a>
                            <button type="submit" class="btn btn-primary waves-effect waves-light"><i class="mdi mdi-magnify me-1"></i> Search</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-mobile-cards table-hover align-middle">
                            <thead class="table-light">
                            <tr>
                                <th data-mc="title">Order</th>
                                <th>Table</th>
                                <th>Opened</th>
                                <th>{{ request('status') === 'cancelled' ? 'Cancelled' : 'Completed' }}</th>
                                <th>Payments</th>
                                <th>Cashier</th>
                                <th class="text-end">Total</th>
                                <th class="text-center" data-mc="actions">Action</th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse($orders as $order)
                                <tr>
                                    <td>
                                        <div class="fw-semibold">{{ $order->order_number }}</div>
                                        <span class="badge {{ $order->status->badgeClass() }}">{{ $order->status->label() }}</span>
                                    </td>
                                    <td>{{ $order->displayTable() }}</td>
                                    <td class="small">{{ $order->opened_at?->format('d M Y, h:i A') }}</td>
                                    <td class="small">{{ ($order->completed_at ?? $order->cancelled_at)?->format('d M Y, h:i A') }}</td>
                                    <td class="small">
                                        @forelse($order->completedPayments as $payment)
                                            <div>{{ $payment->payment_method->label() }}: {{ number_format($payment->amount, 2) }}</div>
                                        @empty
                                            —
                                        @endforelse
                                    </td>
                                    <td class="small">{{ $order->completedBy?->name ?? '—' }}</td>
                                    <td class="text-end fw-semibold">{{ number_format($order->grand_total, 2) }}</td>
                                    <td class="text-center">
                                        <a href="{{ route('pos-orders.show', $order) }}" class="btn btn-sm btn-info">View</a>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="8" class="text-center text-muted">No orders found.</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="d-flex justify-content-end mt-3">
                        {{ $orders->links('partials.pagination') }}
                    </div>
                </div>
            </div>

        </div>
    </div>
@endsection

@section('JScript')
    <script>
        $(document).ready(function () {
            $('.select2').select2({width: '100%', allowClear: true});
            $('.select2-nosearch').select2({width: '100%', minimumResultsForSearch: -1});
        });
    </script>
@endsection
