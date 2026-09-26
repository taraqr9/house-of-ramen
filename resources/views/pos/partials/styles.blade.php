{{-- Shared POS screen styles: large touch targets for phone/tablet use. --}}
<style>
    /* The theme's .main-content {overflow: hidden} makes it a scroll
       container, which silently disables position: sticky for everything
       inside (POS tabs, menu search, bottom Send bar). clip still clips but
       isn't a scroll container; flow-root keeps the same BFC behaviour. */
    .main-content { overflow: clip; display: flow-root; }

    .pos-tile {
        display: flex; flex-direction: column; justify-content: center; align-items: center;
        min-height: 96px; padding: 10px; border-radius: 10px; border: 2px solid transparent;
        text-align: center; cursor: pointer; user-select: none; width: 100%;
        transition: transform .08s ease, box-shadow .08s ease;
    }
    .pos-tile:active { transform: scale(.97); }
    .pos-tile .pos-tile-title { font-size: 1.15rem; font-weight: 700; line-height: 1.2; }
    .pos-tile .pos-tile-sub { font-size: .8rem; opacity: .85; }
    .pos-tile-free { background: #e8f8f1; border-color: #34c38f; color: #1f7a57; }
    .pos-tile-busy { background: #fdecec; border-color: #f46a6a; color: #a52a2a; }
    .pos-tile-bill { background: #fff6e0; border-color: #f1b44c; color: #8a6116; }
    .pos-tile-item { background: #f3f5fb; border-color: #dfe3ee; color: #343a40; min-height: 84px; }
    .pos-tile-item.added { border-color: #556ee6; background: #eef0fd; }
    .pos-btn-lg { min-height: 48px; font-size: 1rem; font-weight: 600; }
    .pos-qty-btn { width: 40px; height: 40px; font-size: 1.2rem; line-height: 1; padding: 0; }
    .pos-ticket { border-radius: 10px; border: 2px solid #dfe3ee; background: #fff; }
    .pos-ticket.is-new { animation: posFlash 1s ease-in-out 3; border-color: #f1b44c; }
    .pos-ticket-head { padding: 8px 12px; border-bottom: 1px solid #eef0f4; display: flex; justify-content: space-between; align-items: center; gap: 8px; }
    .pos-ticket-row { padding: 10px 12px; border-bottom: 1px dashed #eef0f4; }
    .pos-ticket-row:last-child { border-bottom: 0; }
    .pos-note { background: #fff6e0; color: #8a6116; border-radius: 6px; padding: 2px 8px; font-size: .85rem; display: inline-block; }
    .pos-sticky-bar { position: sticky; bottom: 0; z-index: 10; background: #fff; box-shadow: 0 -4px 12px rgba(0,0,0,.06); }
    .pos-category-nav { display: flex; gap: 6px; overflow-x: auto; padding-bottom: 6px; -webkit-overflow-scrolling: touch; }
    .pos-category-nav .btn { white-space: nowrap; flex: 0 0 auto; }
    .pos-tile .pos-tile-code { font-size: .95rem; white-space: nowrap; }
    .pos-kitchen-cancel { font-size: 1.35rem; line-height: 1; }

    /* Phones + portrait tablets (< 992px): the order screen shows one panel
       at a time (Menu / New Round / Order) via .pos-pane-tabs; the menu's
       search + categories stay pinned under the fixed 70px topbar. */
    @media (max-width: 991.98px) {
        .pos-panes [data-pane]:not(.is-active) { display: none !important; }
        .pos-pane-tabs {
            position: sticky; top: 70px; z-index: 6; display: flex; gap: 6px;
            padding: 8px 0; margin: -8px 0 8px; background: var(--bs-body-bg, #f8f8fb);
        }
        .pos-pane-tabs .btn { flex: 1 1 0; min-height: 46px; font-weight: 600; padding: 4px 6px; line-height: 1.2; }
        .pos-menu-toolbar {
            position: sticky; top: 132px; z-index: 5; background: var(--bs-card-bg, #fff);
            margin: -4px -4px 4px; padding: 4px 4px 0;
        }
        .pos-header-actions { width: 100%; }
        .pos-header-actions > * { flex: 1 1 0; min-width: 0; }
        .pos-header-actions > * > .btn, .pos-header-actions > .btn {
            width: 100%; min-height: 44px; display: inline-flex; align-items: center; justify-content: center; gap: 4px;
        }
    }

    @media (max-width: 575.98px) {
        .pos-pane-tabs .btn { font-size: .8rem; }
        .pos-pane-tabs .btn .small { display: block; }
        .pos-header-actions > * { flex: 1 1 calc(50% - 8px); }
        .pos-full-sm, .pos-full-sm .btn { width: 100%; }
        .pos-tile .pos-tile-title { font-size: 1.05rem; }
    }

    @keyframes posFlash { 0%, 100% { box-shadow: none; } 50% { box-shadow: 0 0 0 6px rgba(241,180,76,.45); } }
</style>
