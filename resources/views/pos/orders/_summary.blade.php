{{-- Money summary for an order - server-calculated values only. --}}
<table class="table table-sm mb-0">
    <tbody>
    <tr><td>Subtotal</td><td class="text-end">{{ number_format($order->subtotal, 2) }}</td></tr>
    @if((float) $order->discount > 0)
        <tr>
            <td>Discount
                @if($order->discount_type?->value === 'percent')
                    <span class="text-muted small">({{ rtrim(rtrim(number_format($order->discount_value, 2), '0'), '.') }}%)</span>
                @endif
            </td>
            <td class="text-end text-danger">-{{ number_format($order->discount, 2) }}</td>
        </tr>
    @endif
    @if((float) $order->service_charge > 0 || (float) $order->service_charge_percent > 0)
        <tr><td>Service Charge <span class="text-muted small">({{ (float) $order->service_charge_percent }}%)</span></td><td class="text-end">{{ number_format($order->service_charge, 2) }}</td></tr>
    @endif
    @if((float) $order->vat > 0 || (float) $order->vat_percent > 0)
        <tr><td>VAT <span class="text-muted small">({{ (float) $order->vat_percent }}%)</span></td><td class="text-end">{{ number_format($order->vat, 2) }}</td></tr>
    @endif
    <tr class="fw-bold fs-5"><td>Grand Total</td><td class="text-end">{{ number_format($order->grand_total, 2) }}</td></tr>
    @if((float) $order->paid_total > 0 || ($alwaysShowPaid ?? false))
        <tr class="text-success"><td>Paid</td><td class="text-end">{{ number_format($order->paid_total, 2) }}</td></tr>
        <tr class="fw-bold {{ $order->balanceDue() > 0 ? 'text-danger' : 'text-success' }}"><td>Balance Due</td><td class="text-end">{{ number_format($order->balanceDue(), 2) }}</td></tr>
    @endif
    </tbody>
</table>
