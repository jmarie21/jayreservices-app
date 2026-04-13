<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAddonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'service_addon_group_id' => ['nullable', 'exists:service_addon_groups,id'],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('service_addons', 'slug')],
            'addon_type' => ['required', Rule::in(['boolean', 'quantity', 'checkbox_group'])],
            'client_price' => ['required', 'numeric', 'min:0'],
            'editor_price' => ['required', 'numeric', 'min:0'],
            'has_quantity' => ['nullable', 'boolean'],
            'is_rush_option' => ['nullable', 'boolean'],
            'sample_link' => ['nullable', 'string', 'max:2048'],
            'group' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
