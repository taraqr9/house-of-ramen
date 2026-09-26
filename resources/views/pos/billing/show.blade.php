@extends('layout.master', ['page_title' => $page_title])

@section('CSSheet')
    @include('pos.partials.styles')
@endsection

@section('content')
    <div class="page-content">
        <div class="container-fluid">

            @include('partials.breadcrumb', [
                'items' => [
                    ['label' => 'Billing', 'url' => route('pos-billing.index')],
                    ['label' => $order->order_number],
                ],
            ])

            <div class="card">
                <div class="card-body py-3">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <div>@include('pos.orders._header')</div>
                        <div class="d-flex flex-wrap gap-2">
                            @can('order-view')
                                <a href="{{ route('pos-orders.show', $order) }}" class="btn btn-light"><i class="bx bx-food-menu"></i> Order / Add Items</a>
                            @endcan
                            <form action="{{ route('pos-billing.request', $order) }}" method="POST" class="d-inline once-form">
                                @csrf
                                <button type="submit" class="btn btn-warning"><i class="bx bx-printer"></i> {{ $order->bill_requested_at ? 'Reprint Bill' : 'Generate & Print Bill' }}</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            @if($outstanding->isNotEmpty())
                <div class="alert alert-warning">
                    <i class="bx bx-time"></i> {{ $outstanding->count() }} item(s) not served yet
                    ({{ $outstanding->map(fn ($i) => $i->quantity.'× '.$i->item_name.' - '.$i->kitchen_status->label())->implode(', ') }}).
                    The bill can be printed, but the order can only be completed once they're served or cancelled.
                </div>
            @endif

            <div class="row">
                <div class="col-lg-7">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title mb-3">Bill</h5>
                            <div class="table-responsive">
                                <table class="table table-sm align-middle">
                                    <thead class="table-light">
                                    <tr>
                                        <th>Item</th>
                                        <th class="text-center">Qty</th>
                                        <th class="text-end">Price</th>
                                        <th class="text-end">Total</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @forelse($billItems as $item)
                                        <tr>
                                            <td>
                                                {{ $item->item_name }}
                                                <span class="text-muted small">· R{{ $item->round_no }}</span>
                                                @if($item->kitchen_status->isOutstanding())
                                                    <span class="badge {{ $item->kitchen_status->badgeClass() }}">{{ $item->kitchen_status->label() }}</span>
                                                @endif
                                                @if($item->note)<div><span class="pos-note">{{ $item->note }}</span></div>@endif
                                            </td>
                                            <td class="text-center">{{ $item->quantity }}</td>
                                            <td class="text-end">{{ number_format($item->unit_price, 2) }}</td>
                                            <td class="text-end">{{ number_format($item->line_total, 2) }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="4" class="text-center text-muted">No items on this order yet.</td></tr>
                                    @endforelse
                                    </tbody>
                                </table>
                            </div>
                            @include('pos.orders._summary')
                        </div>
                    </div>

                    @can('order-discount')
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title mb-3">Discount</h5>
                                <form action="{{ route('pos-billing.discount', $order) }}" method="POST" class="row g-2 align-items-end">
                                    @csrf
                                    @method('PATCH')
                                    <div class="col-5">
                                        <label class="form-label">Type</label>
                                        <select name="discount_type" class="form-select">
                                            @foreach($discountTypes as $value => $label)
                                                <option value="{{ $value }}" @selected(($order->discount_type?->value ?? 'fixed') === $value)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-4">
                                        <label class="form-label">Value</label>
                                        <input type="number" name="discount_value" step="0.01" min="0" value="{{ (float) $order->discount_value ?: '' }}" class="form-control" placeholder="0">
                                    </div>
                                    <div class="col-3">
                                        <button type="submit" class="btn btn-outline-primary w-100">Apply</button>
                                    </div>
                                    <div class="form-text">Set 0 to remove the discount.</div>
                                </form>
                            </div>
                        </div>
                    @endcan
                </div>

                <div class="col-lg-5">
                    <div class="card border border-success">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5 class="card-title mb-0">Payment</h5>
                                <span class="fs-4 fw-bold {{ $order->balanceDue() > 0 ? 'text-danger' : 'text-success' }}">Due {{ number_format($order->balanceDue(), 2) }}</span>
                            </div>

                            @if($order->balanceDue() > 0)
                                @can('payment-create')
                                    <form action="{{ route('pos-payments.store', $order) }}" method="POST" id="paymentForm" class="once-form">
                                        @csrf
                                        <input type="hidden" name="idempotency_key" id="idempotencyKey">
                                        <input type="hidden" name="payment_method" id="paymentMethod" value="cash">

                                        <div class="btn-group w-100 mb-3" role="group">
                                            @foreach($paymentMethods as $value => $label)
                                                <button type="button" class="btn pos-btn-lg method-btn {{ $value === 'cash' ? 'btn-success' : 'btn-outline-success' }}" data-method="{{ $value }}">
                                                    <i class="bx {{ $value === 'cash' ? 'bx-money' : 'bx-credit-card' }}"></i> {{ $label }}
                                                </button>
                                            @endforeach
                                        </div>

                                        <label class="form-label">Amount <span class="text-muted small" id="amountHint">(cash received - change is calculated)</span></label>
                                        <input type="number" name="amount" id="paymentAmount" step="0.01" min="0.01" required
                                               value="{{ number_format($order->balanceDue(), 2, '.', '') }}" class="form-control form-control-lg mb-2">
                                        <div class="d-flex flex-wrap gap-2 mb-2" id="quickCash">
                                            <button type="button" class="btn btn-light quick-amount" data-amount="{{ number_format($order->balanceDue(), 2, '.', '') }}">Exact</button>
                                            @foreach([500, 1000, 2000, 5000] as $note)
                                                @if($note > $order->balanceDue())
                                                    <button type="button" class="btn btn-light quick-amount" data-amount="{{ $note }}">{{ number_format($note) }}</button>
                                                @endif
                                            @endforeach
                                        </div>
                                        <div class="text-success fw-semibold mb-2" id="changePreview"></div>

                                        <div id="referenceWrap" style="display: none;">
                                            <label class="form-label">Card Reference / Last 4</label>
                                            <input type="text" name="reference_no" maxlength="100" class="form-control mb-2">
                                        </div>
                                        <input type="text" name="remarks" maxlength="255" class="form-control mb-3" placeholder="Remarks (optional)">

                                        <button type="submit" class="btn btn-success w-100 pos-btn-lg"><i class="bx bx-check"></i> Record Payment</button>
                                        <div class="form-text">For split/mixed payment, record each part separately (e.g. cash 1,000 then card 1,500).</div>
                                    </form>
                                @endcan
                            @elseif($order->isFullyPaid())
                                <div class="alert alert-success mb-3">Fully paid.</div>
                            @endif

                            @can('order-complete')
                                @if($order->isFullyPaid())
                                    <form action="{{ route('pos-billing.complete', $order) }}" method="POST" class="once-form mt-2">
                                        @csrf
                                        <button type="submit" class="btn btn-primary w-100 pos-btn-lg" @disabled($outstanding->isNotEmpty())>
                                            <i class="bx bx-check-double"></i> Complete Order & Release Table
                                        </button>
                                    </form>
                                @endif
                            @endcan
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title mb-3">Payments</h5>
                            @include('pos.orders._payments', ['showVoid' => auth()->user()->can('payment-delete')])
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <form method="POST" id="voidForm" class="d-none">
        @csrf
        @method('PATCH')
        <input type="hidden" name="reason">
    </form>
@endsection

@section('JScript')
    @include('pos.partials.scripts')
    <script>
        (function () {
            const due = @json($order->balanceDue());

            // Fresh key per page load: a double click or back-button resubmit
            // of this same form can't record the payment twice.
            $('#idempotencyKey').val(posUuid());

            function updateChange() {
                const method = $('#paymentMethod').val();
                const amount = parseFloat($('#paymentAmount').val() || 0);
                $('#changePreview').text(method === 'cash' && amount > due ? 'Change to return: ' + (amount - due).toFixed(2) : '');
            }

            $('.method-btn').on('click', function () {
                const method = $(this).data('method');
                $('#paymentMethod').val(method);
                $('.method-btn').removeClass('btn-success').addClass('btn-outline-success');
                $(this).addClass('btn-success').removeClass('btn-outline-success');
                $('#referenceWrap').toggle(method === 'card');
                $('#quickCash').toggle(method === 'cash');
                $('#amountHint').text(method === 'cash' ? '(cash received - change is calculated)' : '(cannot exceed amount due)');
                if (method === 'card' && parseFloat($('#paymentAmount').val()) > due) $('#paymentAmount').val(due.toFixed(2));
                updateChange();
            });

            $('.quick-amount').on('click', function () {
                $('#paymentAmount').val($(this).data('amount'));
                updateChange();
            });

            $('#paymentAmount').on('input', updateChange);

            $('.once-form').on('submit', function () {
                $(this).find('button[type="submit"]').prop('disabled', true);
            });

            $(document).on('click', '.void-payment-btn', function () {
                const url = $(this).data('url');
                Swal.fire({
                    title: 'Void this payment?',
                    text: 'The payment stays on record as voided.',
                    input: 'text',
                    inputPlaceholder: 'Reason (required)',
                    inputValidator: (value) => !value && 'Please enter a reason',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#f46a6a',
                    confirmButtonText: 'Void payment',
                }).then((result) => {
                    if (!result.isConfirmed) return;
                    const form = $('#voidForm').attr('action', url);
                    form.find('input[name="reason"]').val(result.value);
                    form.submit();
                });
            });

            @if(request('print'))
                window.open(@json(route('pos-billing.print', $order)), '_blank');
            @endif
        })();
    </script>
@endsection
