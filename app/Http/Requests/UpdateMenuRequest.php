<?php

namespace App\Http\Requests;

use App\Enums\StatusEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMenuRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $menuId = $this->route('menu')?->id;

        return [
            'title' => ['required', 'string', 'max:255'],
            'icon' => ['nullable', 'string', 'max:255'],
            'route' => ['nullable', 'string', 'max:255'],
            'url' => ['nullable', 'string', 'max:255'],
            'permission' => ['nullable', 'string', 'max:255'],
            'parent_id' => ['nullable', 'exists:menus,id', 'not_in:'.$menuId],
            'serial' => ['required', 'integer', 'min:0'],
            'is_active' => ['nullable', Rule::enum(StatusEnum::class)],
            'active_routes' => ['nullable', 'array'],
            'active_routes.*' => ['nullable', 'string', 'max:255'],
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
            'updated_by' => auth()->id(),
        ]);
    }
}
