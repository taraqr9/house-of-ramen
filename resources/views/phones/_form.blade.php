@php
    $phone = $phone ?? null;
    $spec = $spec ?? null;
    $val = fn ($field, $default = null) => old($field, data_get($phone, $field, data_get($spec, $field, $default)));
@endphp

<h6 class="text-uppercase text-muted mb-3">Identity</h6>
<div class="row">
    <div class="col-md-4">
        <div class="mb-3">
            <label class="form-label">Brand <span class="text-danger">*</span></label>
            <select name="brand_id" class="form-control select2 @error('brand_id') is-invalid @enderror">
                <option value="">Select Brand</option>
                @foreach($brands as $brand)
                    <option value="{{ $brand->id }}" @selected($val('brand_id') == $brand->id)>{{ $brand->name }}</option>
                @endforeach
            </select>
            @error('brand_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>
    </div>
    <div class="col-md-4">
        <div class="mb-3">
            <label class="form-label">Model Name <span class="text-danger">*</span></label>
            <input type="text" name="name" value="{{ $val('name') }}" class="form-control @error('name') is-invalid @enderror" placeholder="e.g. Galaxy S24 Ultra">
            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </div>
    <div class="col-md-4">
        <div class="mb-3">
            <label class="form-label">Model Number</label>
            <input type="text" name="model_number" value="{{ $val('model_number') }}" class="form-control @error('model_number') is-invalid @enderror">
            @error('model_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </div>

    <div class="col-md-3">
        <div class="mb-3">
            <label class="form-label">Announced Date</label>
            <input type="date" name="announced_date" value="{{ $val('announced_date') }}" class="form-control @error('announced_date') is-invalid @enderror">
            @error('announced_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </div>
    <div class="col-md-3">
        <div class="mb-3">
            <label class="form-label">Release Date</label>
            <input type="date" name="release_date" value="{{ $val('release_date') }}" class="form-control @error('release_date') is-invalid @enderror">
            @error('release_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </div>
    <div class="col-md-3">
        <div class="mb-3">
            <label class="form-label">Status <span class="text-danger">*</span></label>
            <select name="status" class="form-control select2 @error('status') is-invalid @enderror">
                @foreach(\App\Enums\PhoneStatusEnum::options() as $value => $label)
                    <option value="{{ $value }}" @selected($val('status', 'available') === $value)>{{ $label }}</option>
                @endforeach
            </select>
            @error('status')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>
    </div>
    <div class="col-md-3">
        <div class="mb-3">
            <label class="form-label">Category</label>
            <select name="category" class="form-control select2 @error('category') is-invalid @enderror">
                <option value="">Select</option>
                @foreach(['flagship' => 'Flagship', 'midrange' => 'Midrange', 'budget' => 'Budget', 'entry' => 'Entry'] as $value => $label)
                    <option value="{{ $value }}" @selected($val('category') === $value)>{{ $label }}</option>
                @endforeach
            </select>
            @error('category')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>
    </div>

    <div class="col-md-12">
        <div class="mb-3">
            <label class="form-label">Summary</label>
            <textarea name="summary" rows="2" class="form-control @error('summary') is-invalid @enderror" placeholder="Short editorial summary shown to buyers">{{ $val('summary') }}</textarea>
            @error('summary')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </div>

    <div class="col-md-4">
        <div class="form-check form-switch mt-2">
            <input type="checkbox" name="is_ai_generated_summary" value="1" class="form-check-input" id="isAiSummary" @checked($val('is_ai_generated_summary'))>
            <label class="form-check-label" for="isAiSummary">Summary is AI-generated (not yet human-verified)</label>
        </div>
    </div>
    <div class="col-md-4">
        <div class="form-check form-switch mt-2">
            <input type="checkbox" name="is_active" value="1" class="form-check-input" id="isActive" @checked(old('is_active', $phone->is_active ?? true))>
            <label class="form-check-label" for="isActive">Active (visible in the dataset)</label>
        </div>
    </div>
</div>

<hr class="my-4">
<h6 class="text-uppercase text-muted mb-3">Hardware</h6>
<div class="row">
    <div class="col-md-4"><div class="mb-3"><label class="form-label">Processor</label><input type="text" name="processor" value="{{ $val('processor') }}" class="form-control" placeholder="e.g. Snapdragon 8 Gen 3"></div></div>
    <div class="col-md-4"><div class="mb-3"><label class="form-label">Chipset Manufacturer</label><input type="text" name="chipset_manufacturer" value="{{ $val('chipset_manufacturer') }}" class="form-control" placeholder="e.g. Qualcomm"></div></div>
    <div class="col-md-4"><div class="mb-3"><label class="form-label">GPU</label><input type="text" name="gpu" value="{{ $val('gpu') }}" class="form-control"></div></div>
    <div class="col-md-12"><div class="mb-3"><label class="form-label">CPU Detail</label><input type="text" name="cpu" value="{{ $val('cpu') }}" class="form-control" placeholder="e.g. Octa-core (1x3.3 GHz + ...)"></div></div>
</div>

<hr class="my-4">
<h6 class="text-uppercase text-muted mb-3">Display</h6>
<div class="row">
    <div class="col-md-2"><div class="mb-3"><label class="form-label">Size (in)</label><input type="number" step="0.1" name="display_size" value="{{ $val('display_size') }}" class="form-control"></div></div>
    <div class="col-md-3"><div class="mb-3"><label class="form-label">Resolution</label><input type="text" name="display_resolution" value="{{ $val('display_resolution') }}" class="form-control" placeholder="1080 x 2412"></div></div>
    <div class="col-md-3"><div class="mb-3"><label class="form-label">Panel Type</label><input type="text" name="display_panel_type" value="{{ $val('display_panel_type') }}" class="form-control" placeholder="AMOLED"></div></div>
    <div class="col-md-2"><div class="mb-3"><label class="form-label">Refresh Rate (Hz)</label><input type="number" name="display_refresh_rate" value="{{ $val('display_refresh_rate') }}" class="form-control"></div></div>
    <div class="col-md-2"><div class="mb-3"><label class="form-label">Protection</label><input type="text" name="display_protection" value="{{ $val('display_protection') }}" class="form-control"></div></div>
</div>

<hr class="my-4">
<h6 class="text-uppercase text-muted mb-3">Camera</h6>
<div class="row">
    <div class="col-md-6"><div class="mb-3"><label class="form-label">Main Camera</label><input type="text" name="main_camera" value="{{ $val('main_camera') }}" class="form-control" placeholder="50 MP, f/1.8, OIS"></div></div>
    <div class="col-md-6"><div class="mb-3"><label class="form-label">Ultrawide</label><input type="text" name="ultrawide_camera" value="{{ $val('ultrawide_camera') }}" class="form-control"></div></div>
    <div class="col-md-4"><div class="mb-3"><label class="form-label">Telephoto</label><input type="text" name="telephoto_camera" value="{{ $val('telephoto_camera') }}" class="form-control"></div></div>
    <div class="col-md-4"><div class="mb-3"><label class="form-label">Macro</label><input type="text" name="macro_camera" value="{{ $val('macro_camera') }}" class="form-control"></div></div>
    <div class="col-md-4"><div class="mb-3"><label class="form-label">Front Camera</label><input type="text" name="front_camera" value="{{ $val('front_camera') }}" class="form-control"></div></div>
    <div class="col-md-6"><div class="mb-3"><label class="form-label">Video Recording</label><input type="text" name="video_recording" value="{{ $val('video_recording') }}" class="form-control" placeholder="4K@60fps"></div></div>
    <div class="col-md-6">
        <div class="form-check form-switch mt-4">
            <input type="checkbox" name="camera_has_ois" value="1" class="form-check-input" id="cameraOis" @checked($val('camera_has_ois'))>
            <label class="form-check-label" for="cameraOis">OIS (Optical Image Stabilization)</label>
        </div>
    </div>
</div>

<hr class="my-4">
<h6 class="text-uppercase text-muted mb-3">Battery</h6>
<div class="row">
    <div class="col-md-3"><div class="mb-3"><label class="form-label">Capacity (mAh)</label><input type="number" name="battery_capacity_mah" value="{{ $val('battery_capacity_mah') }}" class="form-control"></div></div>
    <div class="col-md-3"><div class="mb-3"><label class="form-label">Charging (W)</label><input type="number" name="charging_speed_w" value="{{ $val('charging_speed_w') }}" class="form-control"></div></div>
    <div class="col-md-3"><div class="mb-3"><label class="form-label">Wireless Charging (W)</label><input type="number" name="wireless_charging_w" value="{{ $val('wireless_charging_w') }}" class="form-control"></div></div>
    <div class="col-md-3">
        <div class="form-check form-switch mt-4">
            <input type="checkbox" name="reverse_charging" value="1" class="form-check-input" id="reverseCharging" @checked($val('reverse_charging'))>
            <label class="form-check-label" for="reverseCharging">Reverse Charging</label>
        </div>
    </div>
</div>

<hr class="my-4">
<h6 class="text-uppercase text-muted mb-3">Connectivity</h6>
<div class="row">
    <div class="col-md-3"><div class="mb-3"><label class="form-label">Wi-Fi</label><input type="text" name="wifi" value="{{ $val('wifi') }}" class="form-control" placeholder="Wi-Fi 6"></div></div>
    <div class="col-md-3"><div class="mb-3"><label class="form-label">Bluetooth</label><input type="text" name="bluetooth_version" value="{{ $val('bluetooth_version') }}" class="form-control" placeholder="5.3"></div></div>
    <div class="col-md-3"><div class="mb-3"><label class="form-label">USB Type</label><input type="text" name="usb_type" value="{{ $val('usb_type') }}" class="form-control"></div></div>
    <div class="col-md-3"><div class="mb-3"><label class="form-label">SIM Configuration</label><input type="text" name="sim_config" value="{{ $val('sim_config') }}" class="form-control"></div></div>
    <div class="col-md-3">
        <div class="form-check form-switch mt-2">
            <input type="checkbox" name="network_5g" value="1" class="form-check-input" id="network5g" @checked($val('network_5g'))>
            <label class="form-check-label" for="network5g">5G</label>
        </div>
    </div>
    <div class="col-md-3">
        <div class="form-check form-switch mt-2">
            <input type="checkbox" name="nfc" value="1" class="form-check-input" id="nfc" @checked($val('nfc'))>
            <label class="form-check-label" for="nfc">NFC</label>
        </div>
    </div>
</div>

<hr class="my-4">
<h6 class="text-uppercase text-muted mb-3">Physical & Software</h6>
<div class="row">
    <div class="col-md-2"><div class="mb-3"><label class="form-label">Weight (g)</label><input type="number" name="weight_g" value="{{ $val('weight_g') }}" class="form-control"></div></div>
    <div class="col-md-4"><div class="mb-3"><label class="form-label">Build Materials</label><input type="text" name="build_materials" value="{{ $val('build_materials') }}" class="form-control"></div></div>
    <div class="col-md-2"><div class="mb-3"><label class="form-label">IP Rating</label><input type="text" name="ip_rating" value="{{ $val('ip_rating') }}" class="form-control" placeholder="IP68"></div></div>
    <div class="col-md-4"><div class="mb-3"><label class="form-label">Launch OS</label><input type="text" name="os" value="{{ $val('os') }}" class="form-control" placeholder="Android 14, One UI 6.1"></div></div>
    <div class="col-md-4"><div class="mb-3"><label class="form-label">Current OS</label><input type="text" name="current_os" value="{{ $val('current_os') }}" class="form-control"></div></div>
    <div class="col-md-4"><div class="mb-3"><label class="form-label">Promised OS Updates (years)</label><input type="number" name="os_update_years" value="{{ $val('os_update_years') }}" class="form-control"></div></div>
    <div class="col-md-4"><div class="mb-3"><label class="form-label">Security Update Duration (years)</label><input type="number" name="security_update_years" value="{{ $val('security_update_years') }}" class="form-control"></div></div>
</div>
