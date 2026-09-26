{{-- Shared POS screen styles: large touch targets for phone/tablet use. --}}
<style>
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
    .pos-category-nav .btn { white-space: nowrap; }
    @keyframes posFlash { 0%, 100% { box-shadow: none; } 50% { box-shadow: 0 0 0 6px rgba(241,180,76,.45); } }
</style>
