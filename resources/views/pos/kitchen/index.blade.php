@extends('layout.master', ['page_title' => $page_title])

@section('CSSheet')
    @include('pos.partials.styles')
@endsection

@section('content')
    <div class="page-content">
        <div class="container-fluid">

            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                <div>
                    <h4 class="mb-0">Kitchen</h4>
                    <span class="text-muted small">Updates every few seconds · <span id="lastSync">connecting...</span></span>
                </div>
                <div class="d-flex gap-2 align-items-center">
                    <span class="badge bg-secondary fs-6">Pending <span id="countPending">0</span></span>
                    <span class="badge bg-warning fs-6">Preparing <span id="countPreparing">0</span></span>
                    <button type="button" class="btn btn-outline-primary" id="soundBtn"><i class="bx bx-volume-mute"></i> Enable sound</button>
                </div>
            </div>

            <div class="row g-3" id="tickets"></div>
            <div class="text-center text-muted py-5" id="emptyState">No items waiting. New orders appear here automatically.</div>

        </div>
    </div>
@endsection

@section('JScript')
    @include('pos.partials.scripts')
    <script>
        (function () {
            const feedUrl = @json(route('pos-kitchen.feed'));
            const updateUrl = @json(route('pos-kitchen.update', ['order_item' => '__ID__']));
            const canUpdate = @json(auth()->user()->can('kitchen-update'));
            const kitchenStatuses = ['pending', 'preparing'];
            const items = new Map();
            let cursor = null;
            let polling = false;
            let firstLoad = true;

            function ticketKey(item) {
                return item.order_id + '-' + item.round_no;
            }

            function renderTicket(key) {
                const ticketItems = [...items.values()].filter((i) => ticketKey(i) === key && kitchenStatuses.includes(i.status));
                let el = document.getElementById('ticket-' + key);

                if (ticketItems.length === 0) {
                    if (el) el.closest('.ticket-col').remove();
                    return;
                }

                const first = ticketItems[0];
                const hasPending = ticketItems.some((i) => i.status === 'pending');
                const hasPreparing = ticketItems.some((i) => i.status === 'preparing');

                const rows = ticketItems.map((i) => `
                    <div class="pos-ticket-row d-flex justify-content-between align-items-center gap-2">
                        <div>
                            <div class="fs-5 fw-bold">${i.quantity} × ${posEscape(i.name)}</div>
                            ${i.note ? `<span class="pos-note">${posEscape(i.note)}</span>` : ''}
                        </div>
                        ${canUpdate ? (i.status === 'pending'
                            ? `<button class="btn btn-warning pos-btn-lg status-btn" data-id="${i.id}" data-status="preparing">Start</button>`
                            : `<button class="btn btn-success pos-btn-lg status-btn" data-id="${i.id}" data-status="ready">Ready</button>`)
                            : `<span class="badge ${i.status === 'pending' ? 'bg-secondary' : 'bg-warning'}">${i.status}</span>`}
                    </div>`).join('');

                const html = `
                    <div class="pos-ticket h-100" id="ticket-${key}">
                        <div class="pos-ticket-head ${hasPreparing && !hasPending ? 'bg-warning bg-opacity-25' : 'bg-light'}">
                            <div>
                                <div class="fs-4 fw-bold">${posEscape(first.table)}</div>
                                <div class="small text-muted">${posEscape(first.order_number)} · Round ${first.round_no}</div>
                            </div>
                            <div class="text-end small">
                                <div>${first.sent_at ? new Date(first.sent_at).toLocaleTimeString([], {hour: '2-digit', minute: '2-digit'}) : ''}</div>
                                <div class="fw-semibold ticket-age" data-sent="${first.sent_at}">${posMinutesAgo(first.sent_at)}</div>
                            </div>
                        </div>
                        ${first.order_note ? `<div class="px-3 pt-2"><span class="pos-note"><i class="bx bx-note"></i> ${posEscape(first.order_note)}</span></div>` : ''}
                        ${rows}
                        ${canUpdate ? `<div class="p-2 border-top d-flex gap-2">
                            ${hasPending ? `<button class="btn btn-outline-warning flex-grow-1 bulk-btn" data-key="${key}" data-from="pending" data-status="preparing">Start all</button>` : ''}
                            ${hasPreparing ? `<button class="btn btn-outline-success flex-grow-1 bulk-btn" data-key="${key}" data-from="preparing" data-status="ready">Ready all</button>` : ''}
                        </div>` : ''}
                    </div>`;

                if (el) {
                    const wasNew = el.classList.contains('is-new');
                    el.outerHTML = html;
                    if (wasNew) document.getElementById('ticket-' + key).classList.add('is-new');
                } else {
                    const col = document.createElement('div');
                    col.className = 'col-12 col-md-6 col-xl-4 ticket-col';
                    col.dataset.sent = first.sent_at || '';
                    col.innerHTML = html;
                    // Oldest ticket first.
                    const later = [...document.querySelectorAll('#tickets .ticket-col')].find((c) => c.dataset.sent > col.dataset.sent);
                    document.getElementById('tickets').insertBefore(col, later || null);
                }
            }

            function refreshCounts() {
                const all = [...items.values()];
                $('#countPending').text(all.filter((i) => i.status === 'pending').reduce((s, i) => s + i.quantity, 0));
                $('#countPreparing').text(all.filter((i) => i.status === 'preparing').reduce((s, i) => s + i.quantity, 0));
                $('#emptyState').toggle(document.querySelectorAll('#tickets .ticket-col').length === 0);
            }

            function applyItems(list, full) {
                const changedKeys = new Set();
                const newKeys = new Set();

                if (full) {
                    items.forEach((i) => changedKeys.add(ticketKey(i)));
                    items.clear();
                }

                list.forEach((incoming) => {
                    const current = items.get(incoming.id);
                    if (current && current.updated_at === incoming.updated_at && current.status === incoming.status) return;

                    if (!current && incoming.status === 'pending' && !firstLoad) newKeys.add(ticketKey(incoming));

                    if (kitchenStatuses.includes(incoming.status)) {
                        items.set(incoming.id, incoming);
                    } else {
                        items.delete(incoming.id);
                    }
                    changedKeys.add(ticketKey(incoming));
                });

                changedKeys.forEach(renderTicket);
                newKeys.forEach((key) => document.getElementById('ticket-' + key)?.classList.add('is-new'));
                if (newKeys.size) posBeep();
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

            function setStatus(id, status) {
                return posRequest(updateUrl.replace('__ID__', id), 'PATCH', {status: status})
                    .then(() => {
                        const item = items.get(id);
                        if (!item) return;
                        item.status = status;
                        if (!kitchenStatuses.includes(status)) items.delete(id);
                        renderTicket(ticketKey(item));
                        refreshCounts();
                    });
            }

            $(document).on('click', '.status-btn', function () {
                const btn = $(this).prop('disabled', true);
                setStatus(parseInt(btn.data('id')), btn.data('status'))
                    .catch((error) => { btn.prop('disabled', false); posToast('error', error.message); poll(); });
            });

            $(document).on('click', '.bulk-btn', function () {
                const btn = $(this).prop('disabled', true);
                const targets = [...items.values()].filter((i) => ticketKey(i) === String(btn.data('key')) && i.status === btn.data('from'));
                Promise.allSettled(targets.map((i) => setStatus(i.id, btn.data('status'))))
                    .then((results) => {
                        if (results.some((r) => r.status === 'rejected')) posToast('error', 'Some items could not be updated');
                        poll();
                    });
            });

            $(document).on('click', '.pos-ticket.is-new', function () {
                this.classList.remove('is-new');
            });

            $('#soundBtn').on('click', function () {
                if (posEnableSound()) $(this).removeClass('btn-outline-primary').addClass('btn-primary').html('<i class="bx bx-volume-full"></i> Sound on');
            });

            setInterval(() => $('.ticket-age').each(function () { $(this).text(posMinutesAgo($(this).data('sent'))); }), 30000);

            poll();
            setInterval(poll, 4000);
        })();
    </script>
@endsection
