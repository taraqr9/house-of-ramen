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
                    ['label' => 'Billing'],
                ],
            ])

            <div class="card">
                <div class="card-body">
                    <h5 class="card-title mb-1">Billing</h5>
                    <p class="text-muted small mb-0">Running orders - bill requested first. Tap an order to bill, take payment and close it.</p>
                </div>
            </div>

            <div class="row g-3">
                @forelse($orders as $order)
                    <div class="col-12 col-sm-6 col-lg-4 col-xl-3">
                        <a href="{{ route('pos-billing.show', $order) }}" class="text-decoration-none text-body">
                            <div class="pos-ticket h-100 {{ $order->bill_requested_at ? 'border-warning' : '' }}">
                                <div class="pos-ticket-head">
                                    <div>
                                        <div class="fs-4 fw-bold">{{ $order->displayTable() }}</div>
                                        <div class="small text-muted">{{ $order->order_number }}</div>
                                    </div>
                                    <span class="badge {{ $order->status->badgeClass() }}">{{ $order->status->label() }}</span>
                                </div>
                                <div class="pos-ticket-row small">
                                    <div class="d-flex justify-content-between"><span>Grand total</span><span class="fw-bold">{{ number_format($order->grand_total, 2) }}</span></div>
                                    <div class="d-flex justify-content-between"><span>Paid</span><span>{{ number_format($order->paid_total, 2) }}</span></div>
                                    <div class="d-flex justify-content-between {{ $order->balanceDue() > 0 ? 'text-danger' : 'text-success' }}"><span>Due</span><span class="fw-bold">{{ number_format($order->balanceDue(), 2) }}</span></div>
                                    @if($order->outstanding_count)
                                        <div class="text-warning mt-1"><i class="bx bx-time"></i> {{ $order->outstanding_count }} item(s) not served yet</div>
                                    @endif
                                </div>
                            </div>
                        </a>
                    </div>
                @empty
                    <div class="col-12">
                        <div class="card"><div class="card-body text-center text-muted">No running orders.</div></div>
                    </div>
                @endforelse
            </div>

        </div>
    </div>
@endsection
