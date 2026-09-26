{{-- Order identity strip shared by the terminal, billing and history screens. --}}
<div class="d-flex flex-wrap align-items-center gap-2">
    <h4 class="mb-0 me-2">{{ $order->displayTable() }}</h4>
    <span class="badge {{ $order->status->badgeClass() }} fs-6">{{ $order->status->label() }}</span>
    <span class="text-muted">{{ $order->order_number }}</span>
    <span class="text-muted">· {{ $order->order_type->label() }}</span>
    @if($order->guest_count)
        <span class="text-muted">· {{ $order->guest_count }} guest{{ $order->guest_count > 1 ? 's' : '' }}</span>
    @endif
    <span class="text-muted">· opened {{ $order->opened_at?->format('d M, h:i A') }}</span>
</div>
@if($order->general_note)
    <div class="mt-2"><span class="pos-note"><i class="bx bx-note"></i> {{ $order->general_note }}</span></div>
@endif
