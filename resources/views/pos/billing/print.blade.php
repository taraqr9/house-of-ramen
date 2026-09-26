<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $order->status->value === 'completed' ? 'Receipt' : 'Bill' }} {{ $order->order_number }}</title>
    {{-- Standalone page (no admin layout) sized for 80mm thermal printers;
         also prints fine on A4 via the browser's print dialog. --}}
    <style>
        * { box-sizing: border-box; }
        body { font-family: "Courier New", monospace; font-size: 12px; color: #000; margin: 0; background: #f4f4f4; }
        .receipt { width: 80mm; max-width: 100%; margin: 12px auto; background: #fff; padding: 10px 12px; }
        .center { text-align: center; }
        .right { text-align: right; }
        h1 { font-size: 16px; margin: 0 0 2px; }
        .muted { font-size: 11px; }
        hr { border: 0; border-top: 1px dashed #000; margin: 6px 0; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 2px 0; vertical-align: top; }
        .total td { font-size: 14px; font-weight: bold; }
        .actions { text-align: center; margin: 12px; }
        .actions button { font-size: 14px; padding: 8px 18px; }
        @media print {
            body { background: #fff; }
            .receipt { margin: 0; width: 100%; padding: 0; }
            .actions { display: none; }
            @page { margin: 4mm; }
        }
    </style>
</head>
<body>
<div class="actions">
    <button type="button" onclick="window.print()">Print</button>
    <button type="button" onclick="window.close()">Close</button>
</div>

<div class="receipt">
    <div class="center">
        <h1>{{ $restaurant?->name ?? config('app.name') }}</h1>
        @if($restaurant?->address)<div class="muted">{{ $restaurant->address }}</div>@endif
        @if($restaurant?->phone)<div class="muted">{{ $restaurant->phone }}</div>@endif
        <div style="margin-top: 4px; font-weight: bold;">{{ $order->status->value === 'completed' ? 'RECEIPT' : 'BILL' }}</div>
    </div>
    <hr>
    <table>
        <tr><td>Order</td><td class="right">{{ $order->order_number }}</td></tr>
        <tr><td>Table</td><td class="right">{{ $order->displayTable() }}</td></tr>
        @if($order->guest_count)<tr><td>Guests</td><td class="right">{{ $order->guest_count }}</td></tr>@endif
        <tr><td>Date</td><td class="right">{{ ($order->completed_at ?? now())->format('d/m/Y h:i A') }}</td></tr>
    </table>
    <hr>
    <table>
        @foreach($lines as $line)
            <tr><td colspan="2">{{ $line->name }}</td></tr>
            <tr>
                <td>&nbsp;&nbsp;{{ $line->quantity }} x {{ number_format($line->unit_price, 2) }}</td>
                <td class="right">{{ number_format($line->total, 2) }}</td>
            </tr>
        @endforeach
    </table>
    <hr>
    <table>
        <tr><td>Subtotal</td><td class="right">{{ number_format($order->subtotal, 2) }}</td></tr>
        @if((float) $order->discount > 0)
            <tr><td>Discount</td><td class="right">-{{ number_format($order->discount, 2) }}</td></tr>
        @endif
        @if((float) $order->service_charge > 0)
            <tr><td>Service Charge ({{ (float) $order->service_charge_percent }}%)</td><td class="right">{{ number_format($order->service_charge, 2) }}</td></tr>
        @endif
        @if((float) $order->vat > 0)
            <tr><td>VAT ({{ (float) $order->vat_percent }}%)</td><td class="right">{{ number_format($order->vat, 2) }}</td></tr>
        @endif
        <tr class="total"><td>TOTAL</td><td class="right">{{ number_format($order->grand_total, 2) }}</td></tr>
    </table>
    @if($order->completedPayments->isNotEmpty())
        <hr>
        <table>
            @foreach($order->completedPayments as $payment)
                <tr><td>{{ $payment->payment_method->label() }}</td><td class="right">{{ number_format($payment->amount, 2) }}</td></tr>
                @if((float) $payment->change_amount > 0)
                    <tr><td class="muted">&nbsp;&nbsp;Tendered / Change</td><td class="right muted">{{ number_format($payment->tendered_amount, 2) }} / {{ number_format($payment->change_amount, 2) }}</td></tr>
                @endif
            @endforeach
            @if($order->balanceDue() > 0)
                <tr><td><strong>Due</strong></td><td class="right"><strong>{{ number_format($order->balanceDue(), 2) }}</strong></td></tr>
            @endif
        </table>
    @endif
    <hr>
    <div class="center muted">Thank you for dining with us!</div>
</div>

<script>
    window.addEventListener('load', () => setTimeout(() => window.print(), 300));
</script>
</body>
</html>
