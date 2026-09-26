@extends('layout.master', ['page_title' => $page_title])

@section('CSSheet')
    @include('pos.partials.styles')
@endsection

@section('content')
    <div class="page-content">
        <div class="container-fluid">

            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                <div>
                    <h4 class="mb-0">Ready to Serve</h4>
                    <span class="text-muted small">Food the kitchen has finished · <span id="lastSync">connecting...</span></span>
                </div>
                <div class="d-flex gap-2 align-items-center">
                    <span class="badge bg-info fs-6">Ready <span id="countReady">0</span></span>
                    <button type="button" class="btn btn-outline-primary" id="soundBtn"><i class="bx bx-volume-mute"></i> Enable sound</button>
                </div>
            </div>

            <div class="card border border-danger mb-3" id="cancelledPanel" style="display: none;">
                <div class="card-body py-2">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <h5 class="card-title text-danger mb-0"><i class="bx bx-error"></i> Cancelled items - tell the table</h5>
                        <button type="button" class="btn btn-sm btn-outline-danger" id="dismissAllCancelled">Dismiss all</button>
                    </div>
                    <div id="cancelledList"></div>
                </div>
            </div>

            <div class="row g-3" id="groups"></div>
            <div class="text-center text-muted py-5" id="emptyState">Nothing ready right now.</div>

        </div>
    </div>
@endsection

@section('JScript')
    @include('pos.partials.scripts')
    <script>
        (function () {
            const feedUrl = @json(route('pos-serving.feed'));
            const serveUrl = @json(route('pos-serving.serve', ['order_item' => '__ID__']));
            const ackUrl = @json(route('pos-serving.acknowledge', ['order_item' => '__ID__']));
            const canServe = @json(auth()->user()->can('serving-update'));
            const items = new Map();
            const cancelled = new Map();
            // Dismissed alerts are remembered per browser only (a
            // convenience - the cancellation itself is on the order).
            let dismissed = new Set();
            try { dismissed = new Set(JSON.parse(localStorage.getItem('pos-dismissed-cancellations') || '[]')); } catch (e) { /* storage unavailable */ }

            function saveDismissed() {
                try { localStorage.setItem('pos-dismissed-cancellations', JSON.stringify([...dismissed].slice(-300))); } catch (e) { /* storage unavailable */ }
            }

            function renderCancelled() {
                const list = [...cancelled.values()].filter((i) => !dismissed.has(i.id));
                $('#cancelledPanel').toggle(list.length > 0);
                $('#cancelledList').html(list.map((i) => `
                    <div class="d-flex justify-content-between align-items-center gap-2 border-top py-2">
                        <div>
                            <div class="fw-bold">${posEscape(i.table)} · ${i.quantity} × ${posEscape(i.name)}</div>
                            <div class="small">${posEscape(i.cancellation_reason)}${i.cancelled_by ? ' · by ' + posEscape(i.cancelled_by) : ''} · ${posMinutesAgo(i.cancelled_at)}</div>
                        </div>
                        <button type="button" class="btn btn-sm btn-light dismiss-cancel-btn" data-id="${i.id}">OK</button>
                    </div>`).join(''));
            }
            let cursor = null;
            let polling = false;
            let firstLoad = true;

            // One card per order, so a waiter carries a whole table's ready
            // food in one trip.
            function renderGroup(orderId) {
                const groupItems = [...items.values()].filter((i) => i.order_id === orderId);
                let el = document.getElementById('group-' + orderId);

                if (groupItems.length === 0) {
                    if (el) el.closest('.group-col').remove();
                    return;
                }

                const first = groupItems[0];
                const unacked = groupItems.some((i) => !i.acknowledged);

                const rows = groupItems.map((i) => `
                    <div class="pos-ticket-row d-flex justify-content-between align-items-center gap-2 ${i.acknowledged ? '' : 'bg-info bg-opacity-10'}">
                        <div>
                            <div class="fs-5 fw-bold">${i.quantity} × ${posEscape(i.name)}</div>
                            <div class="small text-muted">Round ${i.round_no} · ready ${posMinutesAgo(i.ready_at)}</div>
                            ${i.note ? `<span class="pos-note">${posEscape(i.note)}</span>` : ''}
                        </div>
                        ${canServe ? `<button class="btn btn-success pos-btn-lg serve-btn" data-id="${i.id}">Served</button>` : ''}
                    </div>`).join('');

                const html = `
                    <div class="pos-ticket h-100 ${unacked ? 'is-new' : ''}" id="group-${orderId}">
                        <div class="pos-ticket-head bg-info bg-opacity-25">
                            <div>
                                <div class="fs-4 fw-bold">${posEscape(first.table)}</div>
                                <div class="small">${posEscape(first.order_number)}</div>
                            </div>
                            ${canServe && unacked ? `<button class="btn btn-outline-dark ack-btn" data-order="${orderId}">Got it</button>` : ''}
                        </div>
                        ${rows}
                        ${canServe && groupItems.length > 1 ? `<div class="p-2 border-top"><button class="btn btn-outline-success w-100 serve-all-btn" data-order="${orderId}">Serve all</button></div>` : ''}
                    </div>`;

                if (el) {
                    el.outerHTML = html;
                } else {
                    const col = document.createElement('div');
                    col.className = 'col-12 col-md-6 col-xl-4 group-col';
                    col.innerHTML = html;
                    document.getElementById('groups').appendChild(col);
                }
            }

            function refreshCounts() {
                $('#countReady').text([...items.values()].reduce((s, i) => s + i.quantity, 0));
                $('#emptyState').toggle(items.size === 0);
            }

            function applyItems(list, full) {
                const changed = new Set();
                let arrived = false;
                let newCancellation = false;

                if (full) {
                    items.forEach((i) => changed.add(i.order_id));
                    items.clear();
                }

                list.forEach((incoming) => {
                    const current = items.get(incoming.id);
                    if (current && current.updated_at === incoming.updated_at && current.status === incoming.status) return;

                    if (incoming.status === 'ready') {
                        if (!current && !firstLoad) arrived = true;
                        items.set(incoming.id, incoming);
                    } else {
                        items.delete(incoming.id);
                    }

                    // Item-level cancellations on running orders (a whole
                    // cancelled order isn't news to the floor).
                    if (incoming.status === 'cancelled' && incoming.order_active && !cancelled.has(incoming.id)) {
                        cancelled.set(incoming.id, incoming);
                        if (!firstLoad && !dismissed.has(incoming.id)) newCancellation = true;
                    }
                    changed.add(incoming.order_id);
                });

                changed.forEach(renderGroup);
                renderCancelled();
                if (arrived || newCancellation) posBeep();
                refreshCounts();
            }

            function poll() {
                if (polling) return;
                polling = true;

                fetch(feedUrl + (cursor ? '?since=' + encodeURIComponent(cursor) : ''), {headers: {'Accept': 'application/json'}})
                    .then((r) => {
                        if (r.status === 401 || r.status === 419) { window.location.reload(); }
                        if (!r.ok) throw new Error('feed');
                        return r.json();
                    })
                    .then((data) => {
                        applyItems(data.items, data.full);
                        cursor = data.server_time;
                        firstLoad = false;
                        $('#lastSync').text('synced ' + new Date().toLocaleTimeString());
                    })
                    .catch(() => $('#lastSync').html('<span class="text-danger">connection lost, retrying...</span>'))
                    .finally(() => { polling = false; });
            }

            function serve(id) {
                return posRequest(serveUrl.replace('__ID__', id), 'PATCH').then(() => {
                    const item = items.get(id);
                    items.delete(id);
                    if (item) renderGroup(item.order_id);
                    refreshCounts();
                });
            }

            $(document).on('click', '.serve-btn', function () {
                const btn = $(this).prop('disabled', true);
                serve(parseInt(btn.data('id'))).catch((error) => { btn.prop('disabled', false); posToast('error', error.message); poll(); });
            });

            $(document).on('click', '.serve-all-btn', function () {
                const orderId = parseInt($(this).prop('disabled', true).data('order'));
                const targets = [...items.values()].filter((i) => i.order_id === orderId);
                Promise.allSettled(targets.map((i) => serve(i.id))).then(poll);
            });

            $(document).on('click', '.ack-btn', function () {
                const orderId = parseInt($(this).prop('disabled', true).data('order'));
                const targets = [...items.values()].filter((i) => i.order_id === orderId && !i.acknowledged);
                Promise.allSettled(targets.map((i) => posRequest(ackUrl.replace('__ID__', i.id), 'PATCH').then(() => { i.acknowledged = true; })))
                    .then(() => renderGroup(orderId));
            });

            $(document).on('click', '.dismiss-cancel-btn', function () {
                dismissed.add(parseInt($(this).data('id')));
                saveDismissed();
                renderCancelled();
            });

            $('#dismissAllCancelled').on('click', function () {
                cancelled.forEach((i) => dismissed.add(i.id));
                saveDismissed();
                renderCancelled();
            });

            $('#soundBtn').on('click', function () {
                if (posEnableSound()) $(this).removeClass('btn-outline-primary').addClass('btn-primary').html('<i class="bx bx-volume-full"></i> Sound on');
            });

            poll();
            setInterval(poll, 4000);
        })();
    </script>
@endsection
