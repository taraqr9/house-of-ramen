<div class="row">
    <div class="col-md-4">
        <div class="mb-3">
            <label class="form-label">Table Name / Number <span class="text-danger">*</span></label>
            <input type="text" name="name" value="{{ old('name', $table?->name) }}"
                   class="form-control @error('name') is-invalid @enderror"
                   placeholder="e.g. T1" required>
            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </div>

    <div class="col-md-4">
        <div class="mb-3">
            <label class="form-label">Area / Floor</label>
            <input type="text" name="area" value="{{ old('area', $table?->area) }}"
                   class="form-control @error('area') is-invalid @enderror"
                   placeholder="e.g. Ground Floor, Rooftop">
            @error('area')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </div>

    <div class="col-md-2">
        <div class="mb-3">
            <label class="form-label">Seats <span class="text-danger">*</span></label>
            <input type="number" name="capacity" value="{{ old('capacity', $table?->capacity ?? 4) }}" min="1" max="100"
                   class="form-control @error('capacity') is-invalid @enderror" required>
            @error('capacity')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </div>

    <div class="col-md-2">
        <div class="mb-3">
            <label class="form-label">Display Order</label>
            <input type="number" name="display_order" value="{{ old('display_order', $table?->display_order ?? 0) }}" min="0"
                   class="form-control @error('display_order') is-invalid @enderror">
            @error('display_order')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </div>

    <div class="col-md-9">
        <div class="mb-3">
            <label class="form-label">Remarks</label>
            <textarea name="remarks" rows="2" class="form-control @error('remarks') is-invalid @enderror">{{ old('remarks', $table?->remarks) }}</textarea>
            @error('remarks')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </div>

    <div class="col-md-3">
        <label>Status</label>
        <div class="form-check form-switch mt-2">
            <input type="checkbox" name="is_active" value="1" class="form-check-input" id="isActive"
                   @checked((string) old('is_active', $table ? (int) $table->is_active : 1) === '1')>
            <label class="form-check-label" for="isActive">Active (shown on the POS terminal)</label>
        </div>
    </div>
</div>
