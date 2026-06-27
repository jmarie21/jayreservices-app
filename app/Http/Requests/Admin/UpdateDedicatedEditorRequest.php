<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDedicatedEditorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'editor_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where('role', 'editor'),
            ],
        ];
    }
}
