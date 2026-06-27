<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateClientDedicatedEditorsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'service_id' => ['nullable', 'integer', Rule::exists('services', 'id')],
            'editor_ids' => ['nullable', 'array'],
            'editor_ids.*' => [
                'integer',
                Rule::exists('users', 'id')->where('role', 'editor'),
            ],
        ];
    }
}
