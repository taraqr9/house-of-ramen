<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PhoneImageUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->can('phone-edit');
    }

    public function rules(): array
    {
        return [
            // Re-encoded to WebP by PhoneImageCollector::attachUploaded()
            // regardless of the source format, so jpeg/png/webp cover
            // every realistic product-photo source without needing GD's
            // less common decoders. 8MB comfortably covers a modern phone
            // camera photo while still being a deliberate cap, not the
            // server's full post_max_size.
            'image' => ['required', 'image', 'mimes:jpeg,jpg,png,webp', 'max:8192'],
            'variant_id' => ['nullable', 'integer', 'exists:phone_variants,id'],
        ];
    }
}
