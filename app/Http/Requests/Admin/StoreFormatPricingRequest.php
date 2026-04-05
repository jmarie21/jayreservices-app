<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFormatPricingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'service_sub_style_id' => ['required', 'exists:service_sub_styles,id'],
            'format_name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('service_format_pricing', 'format_name')
                    ->where(fn ($query) => $query->where('service_sub_style_id', $this->input('service_sub_style_id'))),
            ],
            'format_label' => ['required', 'string', 'max:255'],
            'client_price' => ['required', 'numeric', 'min:0'],
            'editor_price' => ['required', 'numeric', 'min:0'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
