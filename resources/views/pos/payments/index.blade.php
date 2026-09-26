@extends('layout.master', ['page_title' => $page_title])

@section('content')
    <div class="page-content">
        <div class="container-fluid">

            @include('partials.breadcrumb', [
                'items' => [
                    ['label' => 'POS Operations'],
                    ['label' => 'Payments'],
                ],
            ])

            <div class="card">
                <div class="card-body">
                    <h5 class="card-title mb-3">Payments</h5>

                    <form action="{{ route('pos-payments.index') }}" method="GET">
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
                                <label class="form-label">Method</label>
                                <select name="payment_method" class="form-control select2">
                                    <option value="">All Methods</option>
                                    @foreach($methods as $value => $label)
                                        <option value="{{ $value }}" @selected(request('payment_method') === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-6 col-md-2">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-control select2">
                                    <option value="">All Status</option>
                                    <option value="completed" @selected(request('status') === 'completed')>Completed</option>
                                    <option value="voided" @selected(request('status') === 'voided')>Voided</option>
                                </select>
                            </div>
                            <div class="col-6 col-md-2">
                                <label class="form-label">Received By</label>
                                <select name="received_by" class="form-control select2">
                                    <option value="">Everyone</option>
                                    @foreach($cashiers as $cashier)
                                        <option value="{{ $cashier->id }}" @selected((string) request('received_by') === (string) $cashier->id)>{{ $cashier->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-6 col-md-2">
                                <label class="form-label">Keyword</label>
                                <input type="text" name="keyword" value="{{ request('keyword') }}" class="form-control" placeholder="Order, table, reference">
                            </div>
                        </div>
                        <div class="d-flex justify-content-end gap-2 mt-3">
                            <a href="{{ route('pos-payments.index') }}" class="btn btn-light waves-effect">Reset</a>
                            <button type="submit" class="btn btn-primary waves-effect waves-light"><i class="mdi mdi-magnify me-1"></i> Search</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="row">
                @foreach($methods as $value => $label)
                    <div class="col-6 col-md-3">
                        <div class="card mini-stats-wid">
                            <div class="card-body">
                                <p class="text-muted fw-medium mb-1">{{ $label }} <span class="small">({{ $totals->get($value)?->count ?? 0 }})</span></p>
                                <h4 class="mb-0">{{ number_format((float) ($totals->get($value)?->total ?? 0), 2) }}</h4>
                            </div>
                        </div>
                    </div>
                @endforeach
                <div class="col-12 col-md-6">
                    <div class="card mini-stats-wid">
                        <div class="card-body">
                            <p class="text-muted fw-medium mb-1">Total collected (excludes voided)</p>
                            <h4 class="mb-0">{{ number_format((float) $totals->sum('total'), 2) }}</h4>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="table-light">
                            <tr>
                                <th>Paid At</th>
                                <th>Order</th>
                                <th>Table</th>
                                <th>Method</th>
                                <th class="text-end">Amount</th>
                                <th>Reference / Remarks</th>
                                <th>Received By</th>
                                <th>Status</th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse($payments as $payment)
                                <tr>
                                    <td class="small">{{ $payment->paid_at?->format('d M Y, h:i A') }}</td>
                                    <td>
                                        @can('order-view')
                                            <a href="{{ route('pos-orders.show', $payment->order_id) }}">{{ $payment->order?->order_number }}</a>
                                        @else
                                            {{ $payment->order?->order_number }}
                                        @endcan
                                    </td>
                                    <td>{{ $payment->order?->displayTable() }}</td>
                                    <td>{{ $payment->payment_method->label() }}</td>
                                    <td class="text-end">{{ number_format($payment->amount, 2) }}</td>
                                    <td class="small">
                                        {{ $payment->reference_no }}
                                        @if($payment->remarks)<div class="text-muted">{{ $payment->remarks }}</div>@endif
                                        @if($payment->void_reason)<div class="text-danger">Void: {{ $payment->void_reason }}</div>@endif
                                    </td>
                                    <td class="small">{{ $payment->receivedBy?->name }}</td>
                                    <td><span class="badge {{ $payment->status->badgeClass() }}">{{ $payment->status->label() }}</span></td>
                                </tr>
                            @empty
                                <tr><td colspan="8" class="text-center text-muted">No payments found.</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="d-flex justify-content-end mt-3">
                        {{ $payments->links('partials.pagination') }}
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
        });
    </script>
@endsection
