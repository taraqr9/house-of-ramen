@extends('layout.master', ['page_title' => $page_title])

@section('CSSheet')
    @include('pos.partials.styles')
@endsection

@section('content')
    <div class="page-content">
        <div class="container-fluid">

            @include('partials.breadcrumb', [
                'items' => [
                    ['label' => 'Terminal', 'url' => route('pos-terminal.index')],
                    ['label' => $order->order_number],
                ],
            ])

            <div class="card">
                <div class="card-body py-3">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <div>@include('pos.orders._header')</div>
                        <div class="d-flex flex-wrap gap-2">
                            <a href="{{ route('pos-orders.show', $order) }}" class="btn btn-light"><i class="bx bx-refresh"></i> Refresh</a>
                            @can('order-edit')
                                <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#orderDetailsModal"><i class="bx bx-edit"></i> Details</button>
                            @endcan
                            @can('billing-view')
                                <a href="{{ route('pos-billing.show', $order) }}" class="btn btn-warning"><i class="bx bx-receipt"></i> Bill / Pay</a>
                            @endcan
                            @can('order-cancel')
                                <form action="{{ route('pos-orders.cancel', $order) }}" method="POST" class="d-inline" id="cancelOrderForm">
                                    @csrf
                                    <input type="hidden" name="reason">
                                    <button type="button" class="btn btn-outline-danger" id="cancelOrderBtn"><i class="bx bx-x-circle"></i> Cancel Order</button>
                                </form>
                            @endcan
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                @can('order-create')
                    {{-- Menu --}}
                    <div class="col-lg-7">
                        <div class="card">
                            <div class="card-body">
                                <input type="search" id="menuSearch" class="form-control form-control-lg mb-2" placeholder="Search menu...">
                                <div class="pos-category-nav mb-2">
                                    <button type="button" class="btn btn-primary btn-sm category-filter" data-category="all">All</button>
                                    @foreach($categories as $category)
                                        <button type="button" class="btn btn-outline-primary btn-sm category-filter" data-category="{{ $category->id }}">{{ $category->name }}</button>
                                    @endforeach
                                </div>

                                <div class="row g-2" id="menuGrid">
                                    @forelse($categories as $category)
                                        @foreach($category->menuItems as $menuItem)
                                            <div class="col-6 col-sm-4 col-xl-3 menu-item-col" data-category="{{ $category->id }}" data-name="{{ strtolower($menuItem->name) }}">
                                                <button type="button" class="pos-tile pos-tile-item add-item-btn"
                                                        data-id="{{ $menuItem->id }}"
                                                        data-name="{{ $menuItem->name }}"
                                                        data-price="{{ $menuItem->price }}">
                                                    <span class="fw-semibold">{{ $menuItem->name }}</span>
                                                    <span class="pos-tile-sub">{{ number_format($menuItem->price, 2) }}</span>
                                                </button>
                                            </div>
                                        @endforeach
                                    @empty
                                        <div class="col-12 text-center text-muted py-4">No available menu items. Check Restaurant Settings → Menu Items.</div>
                                    @endforelse
                                </div>
                            </div>
                        </div>
                    </div>
                @endcan

                <div class="{{ auth()->user()->can('order-create') ? 'col-lg-5' : 'col-lg-12' }}">
                    @can('order-create')
                        {{-- Next round (client-side cart until sent) --}}
                        <div class="card border border-primary" id="cartCard">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <h5 class="card-title mb-0">New Round <span class="text-muted small">(not sent yet)</span></h5>
                                    <button type="button" class="btn btn-sm btn-link text-danger" id="clearCartBtn">Clear</button>
                                </div>
                                <div id="cartLines"></div>
                                <div id="cartEmpty" class="text-muted text-center py-3">Tap menu items to add them.</div>
                                <div class="d-flex justify-content-between fw-semibold border-top pt-2 mt-2">
                                    <span>Round total (before charges)</span><span id="cartTotal">0.00</span>
                                </div>
                                <button type="button" class="btn btn-success w-100 pos-btn-lg mt-3" id="sendRoundBtn" disabled>
                                    <i class="bx bx-send me-1"></i> Send to Kitchen
                                </button>
                            </div>
                        </div>
                    @endcan

                    {{-- Sent rounds --}}
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title mb-3">Sent to Kitchen</h5>
                            @forelse($order->items->groupBy('round_no') as $roundNo => $roundItems)
                                <div class="pos-ticket mb-3">
                                    <div class="pos-ticket-head">
                                        <strong>Round {{ $roundNo }}</strong>
                                        <span class="text-muted small">{{ $roundItems->first()->sent_at?->format('h:i A') }}</span>
                                    </div>
                                    @foreach($roundItems as $item)
                                        <div class="pos-ticket-row d-flex justify-content-between align-items-start gap-2 {{ $item->isCancelled() ? 'text-decoration-line-through text-muted' : '' }}">
                                            <div>
                                                <div class="fw-semibold">{{ $item->quantity }} × {{ $item->item_name }}</div>
                                                @if($item->note)<span class="pos-note">{{ $item->note }}</span>@endif
                                                @if($item->isCancelled())
                                                    <div class="small text-danger">Cancelled {{ $item->cancelled_at?->format('h:i A') }}{{ $item->cancelledBy ? ' by '.$item->cancelledBy->name : '' }}: {{ $item->cancellation_reason }}</div>
                                                @endif
                                            </div>
                                            <div class="text-end">
                                                <span class="badge {{ $item->kitchen_status->badgeClass() }}">{{ $item->kitchen_status->label() }}</span>
                                                <div class="small">{{ number_format($item->line_total, 2) }}</div>
                                                @if($item->kitchen_status->isCancellable())
                                                    @can('order_item-cancel')
                                                        <button type="button" class="btn btn-link btn-sm text-danger p-0 cancel-item-btn"
                                                                data-url="{{ route('pos-order-items.cancel', $item) }}" data-name="{{ $item->item_name }}">Cancel</button>
                                                    @endcan
                                                @endif
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @empty
                                <div class="text-muted text-center py-3">Nothing sent yet.</div>
                            @endforelse

                            <div class="border-top pt-2">
                                @include('pos.orders._summary')
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        @can('order-create')
            {{-- Phone: keep the send action reachable while scrolling the menu --}}
            <div class="pos-sticky-bar d-lg-none p-2">
                <div class="d-flex gap-2">
                    <a href="#cartCard" class="btn btn-outline-primary pos-btn-lg flex-grow-1">Cart (<span id="cartCountMobile">0</span>)</a>
                    <button type="button" class="btn btn-success pos-btn-lg flex-grow-1" id="sendRoundBtnMobile" disabled>Send</button>
                </div>
            </div>
        @endcan
    </div>

    @can('order-edit')
        <div class="modal fade" id="orderDetailsModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <form action="{{ route('pos-orders.update', $order) }}" method="POST" class="modal-content">
                    @csrf
                    @method('PATCH')
                    <div class="modal-header">
                        <h5 class="modal-title">Order Details</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <label class="form-label">Guests</label>
                        <input type="number" name="guest_count" min="1" max="100" value="{{ $order->guest_count }}" class="form-control mb-3">
                        <label class="form-label">General Note</label>
                        <textarea name="general_note" rows="3" maxlength="1000" class="form-control">{{ $order->general_note }}</textarea>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary">Save</button>
                    </div>
                </form>
            </div>
        </div>
    @endcan
@endsection

@section('JScript')
    @include('pos.partials.scripts')
    <script>
        (function () {
            const orderId = @json($order->id);
            const sendUrl = @json(route('pos-orders.items.store', $order));
            const storageKey = 'pos-cart-' + orderId;
            let cart = [];
            let submissionKey = null;
            let sending = false;

            // A refresh/accidental navigation shouldn't lose an unsent
            // round. Per-browser convenience only - the server is the
            // source of truth once sent.
            try { cart = JSON.parse(localStorage.getItem(storageKey) || '[]'); } catch (e) { cart = []; }

            function persist() {
                try { localStorage.setItem(storageKey, JSON.stringify(cart)); } catch (e) { /* storage unavailable */ }
            }

            function render() {
                const wrap = $('#cartLines').empty();
                let total = 0;
                let count = 0;

                cart.forEach((line, index) => {
                    total += line.price * line.quantity;
                    count += line.quantity;

                    wrap.append(`
                        <div class="border-bottom py-2" data-index="${index}">
                            <div class="d-flex justify-content-between align-items-center gap-2">
                                <div class="flex-grow-1">
                                    <div class="fw-semibold">${posEscape(line.name)}</div>
                                    <div class="small text-muted">${line.price.toFixed(2)} × ${line.quantity} = ${(line.price * line.quantity).toFixed(2)}</div>
                                </div>
                                <button type="button" class="btn btn-light pos-qty-btn qty-minus">−</button>
                                <span class="fw-bold" style="min-width: 24px; text-align: center;">${line.quantity}</span>
                                <button type="button" class="btn btn-light pos-qty-btn qty-plus">+</button>
                            </div>
                            <input type="text" class="form-control form-control-sm mt-2 line-note" maxlength="255"
                                   placeholder="Kitchen note (e.g. less spicy)" value="${posEscape(line.note)}">
                        </div>`);
                });

                $('#cartEmpty').toggle(cart.length === 0);
                $('#cartTotal').text(total.toFixed(2));
                $('#cartCountMobile').text(count);
                $('#sendRoundBtn, #sendRoundBtnMobile').prop('disabled', cart.length === 0 || sending);
                $('.add-item-btn').removeClass('added');
                cart.forEach((line) => $(`.add-item-btn[data-id="${line.id}"]`).addClass('added'));
            }

            // Tapping an item bumps its note-less line; once a line has a
            // note, the next tap starts a new line - so the same dish can go
            // out twice with different instructions.
            $(document).on('click', '.add-item-btn', function () {
                const btn = $(this);
                const id = parseInt(btn.data('id'));
                const existing = cart.find((line) => line.id === id && !line.note);

                if (existing) {
                    existing.quantity++;
                } else {
                    cart.push({id: id, name: btn.attr('data-name'), price: parseFloat(btn.attr('data-price')), quantity: 1, note: ''});
                }

                submissionKey = null;
                persist();
                render();
            });

            $(document).on('click', '.qty-plus, .qty-minus', function () {
                const index = $(this).closest('[data-index]').data('index');
                cart[index].quantity += $(this).hasClass('qty-plus') ? 1 : -1;
                if (cart[index].quantity < 1) cart.splice(index, 1);
                submissionKey = null;
                persist();
                render();
            });

            $(document).on('change', '.line-note', function () {
                const index = $(this).closest('[data-index]').data('index');
                cart[index].note = $(this).val().trim();
                submissionKey = null;
                persist();
            });

            $('#clearCartBtn').on('click', function () {
                cart = [];
                persist();
                render();
            });

            function sendRound() {
                if (sending || cart.length === 0) return;

                // Commit any note still being typed.
                $('.line-note').trigger('change');

                // Same key for retries of this exact cart - the server
                // ignores a repeat, so a double tap can't send twice.
                submissionKey = submissionKey || posUuid();
                sending = true;
                render();

                posRequest(sendUrl, 'POST', {
                    submission_key: submissionKey,
                    items: cart.map((line) => ({menu_item_id: line.id, quantity: line.quantity, note: line.note || null})),
                })
                    .then((data) => {
                        cart = [];
                        persist();
                        posToast('success', data.message);
                        setTimeout(() => window.location.reload(), 600);
                    })
                    .catch((error) => {
                        sending = false;
                        render();
                        Swal.fire({icon: 'error', title: 'Not sent', text: error.message});
                    });
            }

            $('#sendRoundBtn, #sendRoundBtnMobile').on('click', sendRound);

            $('#menuSearch').on('input', function () {
                const term = $(this).val().toLowerCase().trim();
                $('.category-filter').removeClass('btn-primary').addClass('btn-outline-primary');
                $('.category-filter[data-category="all"]').addClass('btn-primary').removeClass('btn-outline-primary');
                $('.menu-item-col').each(function () {
                    $(this).toggle(!term || $(this).data('name').includes(term));
                });
            });

            $('.category-filter').on('click', function () {
                const category = String($(this).data('category'));
                $('#menuSearch').val('');
                $('.category-filter').removeClass('btn-primary').addClass('btn-outline-primary');
                $(this).addClass('btn-primary').removeClass('btn-outline-primary');
                $('.menu-item-col').each(function () {
                    $(this).toggle(category === 'all' || String($(this).data('category')) === category);
                });
            });

            $(document).on('click', '.cancel-item-btn', function () {
                const btn = $(this);
                Swal.fire({
                    title: 'Cancel ' + btn.data('name') + '?',
                    input: 'text',
                    inputPlaceholder: 'Reason (required)',
                    inputValidator: (value) => !value && 'Please enter a reason',
                    showCancelButton: true,
                    confirmButtonColor: '#f46a6a',
                    confirmButtonText: 'Cancel item',
                    cancelButtonText: 'Keep',
                }).then((result) => {
                    if (!result.isConfirmed) return;
                    posRequest(btn.data('url'), 'PATCH', {reason: result.value})
                        .then(() => window.location.reload())
                        .catch((error) => Swal.fire({icon: 'error', title: 'Not cancelled', text: error.message}));
                });
            });

            $('#cancelOrderBtn').on('click', function () {
                Swal.fire({
                    title: 'Cancel the whole order?',
                    text: 'Unserved items are cancelled and the table is released.',
                    input: 'text',
                    inputPlaceholder: 'Reason (required)',
                    inputValidator: (value) => !value && 'Please enter a reason',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#f46a6a',
                    confirmButtonText: 'Cancel order',
                    cancelButtonText: 'Keep order',
                }).then((result) => {
                    if (!result.isConfirmed) return;
                    const form = $('#cancelOrderForm');
                    form.find('input[name="reason"]').val(result.value);
                    form.submit();
                });
            });

            render();
        })();
    </script>
@endsection
