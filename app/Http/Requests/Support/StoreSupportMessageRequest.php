<?php

namespace App\Http\Requests\Support;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

class StoreSupportMessageRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $body = $this->input('body');

        if (! is_string($body)) {
            return;
        }

        $normalized = preg_replace("/\r\n?/", "\n", $body);

        $this->merge([
            'body' => trim((string) $normalized),
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'body' => ['nullable', 'string', 'max:2000'],
            'attachments' => ['nullable', 'array', 'max:3'],
            'attachments.*' => [
                'file',
                'mimes:jpg,jpeg,png,webp,mp4,mov,webm,quicktime',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! $value instanceof UploadedFile) {
                        return;
                    }

                    $mime = $value->getMimeType() ?: '';
                    $sizeBytes = $value->getSize();

                    if (str_starts_with($mime, 'image/') && $sizeBytes > 5 * 1024 * 1024) {
                        $fail('Images must be 5 MB or smaller.');

                        return;
                    }

                    if (str_starts_with($mime, 'video/') && $sizeBytes > 25 * 1024 * 1024) {
                        $fail('Videos must be 25 MB or smaller.');
                    }
                },
            ],
        ];
    }

    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        $validator->after(function (\Illuminate\Validation\Validator $validator) {
            $body = $this->input('body');
            $hasBody = is_string($body) && trim($body) !== '';
            $hasAttachments = count($this->allFiles()) > 0;

            if (! $hasBody && ! $hasAttachments) {
                $validator->errors()->add('body', 'Message cannot be empty.');
            }
        });
    }
}
