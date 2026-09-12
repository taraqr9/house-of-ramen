<?php

namespace App\Http\Requests;

use App\Models\Brand;
use App\Services\PhoneImport\Normalization\SpecNormalizer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Shared by store() and update() - a phone's identity and its 1:1 spec
 * sheet are edited together on one admin screen (RAM/storage/price live
 * on variants instead, managed separately - see PhoneVariantController).
 */
class PhoneSaveRequest extends FormRequest
{
    public function authorize(): bool
    {
        $ability = $this->isMethod('POST') ? 'phone-create' : 'phone-edit';

        return auth()->user()->can($ability);
    }

    protected function prepareForValidation(): void
    {
        $brand = Brand::find($this->input('brand_id'));

        $this->merge([
            'slug' => $brand ? SpecNormalizer::phoneSlug($brand->name, (string) $this->input('name', '')) : null,
            'camera_has_ois' => (bool) $this->input('camera_has_ois'),
            'reverse_charging' => (bool) $this->input('reverse_charging'),
            'network_5g' => (bool) $this->input('network_5g'),
            'nfc' => (bool) $this->input('nfc'),
            'is_ai_generated_summary' => (bool) $this->input('is_ai_generated_summary'),
            'is_active' => (int) $this->input('is_active', 0),
            'updated_by' => auth()->id(),
            'created_by' => $this->isMethod('POST') ? auth()->id() : $this->input('created_by'),
        ]);
    }

    public function rules(): array
    {
        $phoneId = $this->route('phone')?->id;

        return [
            'brand_id' => ['required', 'exists:brands,id'],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', Rule::unique('phones', 'slug')->ignore($phoneId)],
            'model_number' => ['nullable', 'string', 'max:100'],
            'announced_date' => ['nullable', 'date'],
            // A phone can't go on sale before it's even been announced -
            // caught in production data twice (Galaxy A17 5G, Moto Edge 50
            // Pro) before this rule existed, both traced to a data-entry
            // slip on announced_date, not release_date.
            'release_date' => ['nullable', 'date', 'after_or_equal:announced_date'],
            'status' => ['required', 'string', 'in:upcoming,available,discontinued'],
            'category' => ['nullable', 'string', 'max:50'],
            'summary' => ['nullable', 'string', 'max:2000'],
            'is_ai_generated_summary' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],

            'processor' => ['nullable', 'string', 'max:255'],
            'chipset_manufacturer' => ['nullable', 'string', 'max:100'],
            'cpu' => ['nullable', 'string', 'max:255'],
            'gpu' => ['nullable', 'string', 'max:255'],

            'display_size' => ['nullable', 'numeric', 'min:0', 'max:20'],
            'display_resolution' => ['nullable', 'string', 'max:50'],
            'display_panel_type' => ['nullable', 'string', 'max:100'],
            'display_refresh_rate' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'display_protection' => ['nullable', 'string', 'max:100'],

            'main_camera' => ['nullable', 'string', 'max:255'],
            'ultrawide_camera' => ['nullable', 'string', 'max:255'],
            'telephoto_camera' => ['nullable', 'string', 'max:255'],
            'macro_camera' => ['nullable', 'string', 'max:255'],
            'front_camera' => ['nullable', 'string', 'max:255'],
            'camera_has_ois' => ['nullable', 'boolean'],
            'video_recording' => ['nullable', 'string', 'max:255'],

            'battery_capacity_mah' => ['nullable', 'integer', 'min:0', 'max:50000'],
            'charging_speed_w' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'wireless_charging_w' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'reverse_charging' => ['nullable', 'boolean'],

            'network_5g' => ['nullable', 'boolean'],
            'wifi' => ['nullable', 'string', 'max:50'],
            'bluetooth_version' => ['nullable', 'string', 'max:20'],
            'nfc' => ['nullable', 'boolean'],
            'usb_type' => ['nullable', 'string', 'max:100'],
            'sim_config' => ['nullable', 'string', 'max:150'],

            'weight_g' => ['nullable', 'integer', 'min:0', 'max:2000'],
            'build_materials' => ['nullable', 'string', 'max:255'],
            'ip_rating' => ['nullable', 'string', 'max:20'],

            'os' => ['nullable', 'string', 'max:150'],
            'current_os' => ['nullable', 'string', 'max:150'],
            'os_update_years' => ['nullable', 'integer', 'min:0', 'max:15'],
            'security_update_years' => ['nullable', 'integer', 'min:0', 'max:15'],

            'updated_by' => ['nullable', 'exists:users,id'],
            'created_by' => ['nullable', 'exists:users,id'],
        ];
    }
}
