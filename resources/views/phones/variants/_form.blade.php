@php
    $val = fn ($field, $default = null) => old($field, data_get($variant, $field, $default));
    $currentRegion = $val('region');
    $knownRegions = ['Global', 'Chinese'];
@endphp

<div class="row">
    <div class="col-md-3"><div class="mb-3"><label class="form-label">RAM (GB)</label><input type="number" name="ram_gb" value="{{ $val('ram_gb') }}" class="form-control @error('ram_gb') is-invalid @enderror">@error('ram_gb')<div class="invalid-feedback">{{ $message }}</div>@enderror</div></div>
    <div class="col-md-3"><div class="mb-3"><label class="form-label">Storage (GB)</label><input type="number" name="storage_gb" value="{{ $val('storage_gb') }}" class="form-control @error('storage_gb') is-invalid @enderror">@error('storage_gb')<div class="invalid-feedback">{{ $message }}</div>@enderror</div></div>
    <div class="col-md-3"><div class="mb-3"><label class="form-label">Storage Type</label><input type="text" name="storage_type" value="{{ $val('storage_type') }}" class="form-control" placeholder="UFS 3.1"></div></div>
    <div class="col-md-3"><div class="mb-3"><label class="form-label">Color</label><input type="text" name="color" value="{{ $val('color') }}" class="form-control"></div></div>

    <div class="col-md-3">
        <div class="mb-3">
            <label class="form-label">Version (public site)</label>
            <select name="region" class="form-control select2">
                <option value="">Not set</option>
                <option value="Global" @selected($currentRegion === 'Global')>Global</option>
                <option value="Chinese" @selected($currentRegion === 'Chinese')>Chinese</option>
                @if($currentRegion && ! in_array($currentRegion, $knownRegions, true))
                    {{-- A legacy free-text value (e.g. "Bangladesh") from before this
                         field was a controlled select - kept selectable and selected
                         so simply re-saving this variant never silently rewrites it
                         to Global/blank or creates a duplicate variant row (the
                         phone_variants unique key includes region). --}}
                    <option value="{{ $currentRegion }}" selected>{{ $currentRegion }} (legacy value, kept as-is)</option>
                @endif
            </select>
            <div class="form-text">Controls whether this variant shows under "Global" or "Chinese" on the public site. RAM/storage/color are unaffected.</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="mb-3">
            <label class="form-label">Status</label>
            <select name="status" class="form-control select2">
                <option value="available" @selected($val('status', 'available') === 'available')>Available</option>
                <option value="discontinued" @selected($val('status') === 'discontinued')>Discontinued</option>
            </select>
        </div>
    </div>
    <div class="col-md-3">
        <div class="form-check form-switch mt-4">
            <input type="checkbox" name="is_official_bd" value="1" class="form-check-input" id="isOfficialBd" @checked($val('is_official_bd'))>
            <label class="form-check-label" for="isOfficialBd">Official Bangladesh Import</label>
        </div>
    </div>
    <div class="col-md-3">
        <div class="form-check form-switch mt-4">
            <input type="checkbox" name="is_active" value="1" class="form-check-input" id="isActive" @checked(old('is_active', $variant->is_active ?? true))>
            <label class="form-check-label" for="isActive">Active</label>
        </div>
    </div>
</div>

<hr class="my-4">
<h6 class="text-uppercase text-muted mb-3">Bangladesh Pricing & Availability</h6>
<div class="row">
    <div class="col-md-4">
        <div class="mb-3">
            <label class="form-label">Official BD Price (৳)</label>
            <input type="number" step="0.01" name="official_bd_price" value="{{ old('official_bd_price', $officialPrice->amount ?? '') }}" class="form-control @error('official_bd_price') is-invalid @enderror">
            @error('official_bd_price')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </div>
    <div class="col-md-4">
        <div class="mb-3">
            <label class="form-label">Unofficial BD Price (৳)</label>
            <input type="number" step="0.01" name="unofficial_bd_price" value="{{ old('unofficial_bd_price', $unofficialPrice->amount ?? '') }}" class="form-control @error('unofficial_bd_price') is-invalid @enderror">
            @error('unofficial_bd_price')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </div>
    <div class="col-md-4">
        <div class="mb-3">
            <label class="form-label">Store</label>
            <select name="store_id" class="form-control select2">
                <option value="">Select store</option>
                @foreach($stores as $store)
                    <option value="{{ $store->id }}" @selected(old('store_id', $officialPrice->store_id ?? $unofficialPrice->store_id ?? null) == $store->id)>{{ $store->name }}</option>
                @endforeach
            </select>
        </div>
    </div>
    <div class="col-md-4">
        <div class="mb-3">
            <label class="form-label">Availability</label>
            <select name="availability_status" class="form-control select2">
                <option value="">Not tracked</option>
                @foreach(\App\Enums\AvailabilityStatusEnum::options() as $value => $label)
                    <option value="{{ $value }}" @selected(old('availability_status', $availability->status->value ?? null) === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
    </div>
</div>
