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
                    ['label' => 'New Order / Terminal'],
                ],
            ])

            <div class="card">
                <div class="card-body">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                        <div>
                            <h5 class="card-title mb-1">Select a Table</h5>
                            <p class="text-muted small mb-0">
                                <span class="badge bg-success">Free</span> opens a new order ·
                                <span class="badge bg-danger">Occupied</span> / <span class="badge bg-warning">Bill</span> opens the running order
                            </p>
                        </div>
                        <form action="{{ route('pos-orders.store') }}" method="POST" class="pos-open-form pos-full-sm">
                            @csrf
                            <input type="hidden" name="order_type" value="takeaway">
                            <button type="submit" class="btn btn-dark pos-btn-lg px-4">
                                <i class="bx bx-shopping-bag me-1"></i> New Takeaway
                            </button>
                        </form>
                    </div>

                    @forelse($tables as $area => $areaTables)
                        <h6 class="text-uppercase text-muted small fw-bold mt-3 mb-2">{{ $area }}</h6>
                        <div class="row g-2">
                            @foreach($areaTables as $table)
                                <div class="col-4 col-sm-3 col-md-2">
                                    @if($table->activeOrder)
                                        <a href="{{ route('pos-orders.show', $table->activeOrder->id) }}"
                                           class="pos-tile text-decoration-none {{ $table->activeOrder->status->value === 'bill_requested' ? 'pos-tile-bill' : 'pos-tile-busy' }}">
                                            <span class="pos-tile-title">{{ $table->name }}</span>
                                            <span class="pos-tile-sub">{{ $table->activeOrder->status->label() }}</span>
                                            <span class="pos-tile-sub">{{ number_format($table->activeOrder->grand_total, 2) }}</span>
                                        </a>
                                    @else
                                        <button type="button" class="pos-tile pos-tile-free open-table-btn"
                                                data-table-id="{{ $table->id }}" data-table-name="{{ $table->name }}"
                                                data-capacity="{{ $table->capacity }}">
                                            <span class="pos-tile-title">{{ $table->name }}</span>
                                            <span class="pos-tile-sub">Free · {{ $table->capacity }} seats</span>
                                        </button>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @empty
                        <div class="text-center text-muted py-4">
                            No active tables yet.
                            @can('dining_table-create')
                                <a href="{{ route('dining-tables.create') }}">Add a table</a>.
                            @endcan
                        </div>
                    @endforelse
                </div>
            </div>

            @if($takeawayOrders->isNotEmpty())
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title mb-3">Running Takeaway Orders</h5>
                        <div class="row g-2">
                            @foreach($takeawayOrders as $takeaway)
                                <div class="col-6 col-sm-4 col-md-3 col-xl-2">
                                    <a href="{{ route('pos-orders.show', $takeaway->id) }}"
                                       class="pos-tile text-decoration-none {{ $takeaway->status->value === 'bill_requested' ? 'pos-tile-bill' : 'pos-tile-busy' }}">
                                        <span class="pos-tile-title pos-tile-code">{{ $takeaway->order_number }}</span>
                                        <span class="pos-tile-sub">{{ $takeaway->status->label() }} · {{ $takeaway->opened_at?->format('h:i A') }}</span>
                                        <span class="pos-tile-sub">{{ number_format($takeaway->grand_total, 2) }}</span>
                                    </a>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif

        </div>
    </div>

    <div class="modal fade" id="openTableModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form action="{{ route('pos-orders.store') }}" method="POST" class="modal-content pos-open-form">
                @csrf
                <input type="hidden" name="order_type" value="dine_in">
                <input type="hidden" name="dining_table_id" id="openTableId">
                <div class="modal-header">
                    <h5 class="modal-title">Open <span id="openTableName"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label">Guests <span class="text-muted small">(optional)</span></label>
                    <div class="d-flex flex-wrap gap-2 mb-3" id="guestButtons"></div>
                    <input type="number" name="guest_count" id="guestCount" min="1" max="100" class="form-control" placeholder="Guests">
                    <label class="form-label mt-3">Note <span class="text-muted small">(optional)</span></label>
                    <input type="text" name="general_note" maxlength="1000" class="form-control" placeholder="e.g. birthday, allergy">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light pos-btn-lg" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success pos-btn-lg px-4">Open Order</button>
                </div>
            </form>
        </div>
    </div>
@endsection

@section('JScript')
    <script>
        $(document).on('click', '.open-table-btn', function () {
            const btn = $(this);
            $('#openTableId').val(btn.data('table-id'));
            $('#openTableName').text(btn.data('table-name'));
            $('#guestCount').val('');

            const buttons = $('#guestButtons').empty();
            for (let i = 1; i <= Math.min(8, Math.max(2, btn.data('capacity'))); i++) {
                buttons.append($('<button type="button" class="btn btn-outline-primary pos-qty-btn guest-btn"></button>').text(i).attr('data-count', i));
            }

            bootstrap.Modal.getOrCreateInstance(document.getElementById('openTableModal')).show();
        });

        $(document).on('click', '.guest-btn', function () {
            $('.guest-btn').removeClass('active');
            $(this).addClass('active');
            $('#guestCount').val($(this).data('count'));
        });

        // Double-tap guard - the server also returns the same order if the
        // table was opened meanwhile.
        $(document).on('submit', '.pos-open-form', function () {
            $(this).find('button[type="submit"]').prop('disabled', true);
        });
    </script>
@endsection
