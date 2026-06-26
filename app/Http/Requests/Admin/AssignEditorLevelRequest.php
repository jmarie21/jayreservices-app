<?php

namespace App\Http\Requests\Admin;

use App\Enums\EditorLevel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignEditorLevelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'level' => ['nullable', Rule::enum(EditorLevel::class)],
            'user_ids' => ['required', 'array', 'min:1'],
            'user_ids.*' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where('role', 'editor'),
            ],
        ];
    }
}
