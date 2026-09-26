{{-- Discount form (PATCH pos-billing.discount). Used by the desktop Discount
     card and the phone summary panel on billing/show - same fields, same
     endpoint. --}}
<form action="{{ route('pos-billing.discount', $order) }}" method="POST" class="row g-2 align-items-end">
    @csrf
    @method('PATCH')
    <div class="col-12 col-sm-5">
        <label class="form-label">Type</label>
        <select name="discount_type" class="form-select">
            @foreach($discountTypes as $value => $label)
                <option value="{{ $value }}" @selected(($order->discount_type?->value ?? 'fixed') === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-7 col-sm-4">
        <label class="form-label">Value</label>
        <input type="number" name="discount_value" step="0.01" min="0" value="{{ (float) $order->discount_value ?: '' }}" class="form-control" placeholder="0">
    </div>
    <div class="col-5 col-sm-3">
        <button type="submit" class="btn btn-outline-primary w-100">Apply</button>
    </div>
    <div class="form-text">Set 0 to remove the discount.</div>
</form>
