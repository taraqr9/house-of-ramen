<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMenuRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'icon' => ['nullable', 'string', 'max:255'],
            'route' => ['nullable', 'string', 'max:255'],
            'url' => ['nullable', 'string', 'max:255'],
            'permission' => ['nullable', 'string', 'max:255'],
            'parent_id' => ['nullable', 'exists:menus,id'],
            'serial' => ['nullable', 'integer', 'min:0'],
            'active_routes' => ['nullable', 'array'],
            'active_routes.*' => ['nullable', 'string', 'max:255'],
            'created_by' => ['nullable', 'exists:users,id'],
            'updated_by' => ['nullable', 'exists:users,id'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $activeRoutes = $this->input('active_routes');

        if (is_string($activeRoutes)) {
            $activeRoutes = collect(
                preg_split('/\r\n|\r|\n/', $activeRoutes)
            )
                ->map(fn ($route) => trim($route))
                ->filter()
                ->unique()
                ->values()
                ->all();
        }

        $this->merge([
            'active_routes' => ! empty($activeRoutes)
                ? $activeRoutes
                : null,

            'is_active' => (int) $this->input('is_active', 0),
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);
    }
}
