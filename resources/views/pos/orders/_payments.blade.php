{{-- Payment rows for an order, including voided ones (permanent record). --}}
<div class="table-responsive">
    <table class="table table-sm align-middle mb-0">
        <thead class="table-light">
        <tr>
            <th>Time</th>
            <th>Method</th>
            <th class="text-end">Amount</th>
            <th>Details</th>
            <th>Received By</th>
            @isset($showVoid)<th></th>@endisset
        </tr>
        </thead>
        <tbody>
        @forelse($order->payments as $payment)
            <tr class="{{ $payment->status->value === 'voided' ? 'text-muted text-decoration-line-through' : '' }}">
                <td class="small">{{ $payment->paid_at?->format('d M, h:i A') }}</td>
                <td>{{ $payment->payment_method->label() }}
                    @if($payment->status->value === 'voided')<span class="badge bg-danger">Voided</span>@endif
                </td>
                <td class="text-end">{{ number_format($payment->amount, 2) }}</td>
                <td class="small">
                    @if($payment->tendered_amount !== null && (float) $payment->change_amount > 0)
                        Tendered {{ number_format($payment->tendered_amount, 2) }}, change {{ number_format($payment->change_amount, 2) }}<br>
                    @endif
                    @if($payment->reference_no)Ref: {{ $payment->reference_no }}<br>@endif
                    @if($payment->remarks){{ $payment->remarks }}<br>@endif
                    @if($payment->status->value === 'voided')
                        Voided by {{ $payment->voidedBy?->name }}: {{ $payment->void_reason }}
                    @endif
                </td>
                <td class="small">{{ $payment->receivedBy?->name }}</td>
                @isset($showVoid)
                    <td class="text-end">
                        @if($showVoid && $payment->status->value === 'completed')
                            <button type="button" class="btn btn-link btn-sm text-danger p-0 void-payment-btn"
                                    data-url="{{ route('pos-payments.void', $payment) }}">Void</button>
                        @endif
                    </td>
                @endisset
            </tr>
        @empty
            <tr><td colspan="6" class="text-center text-muted">No payments yet.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
