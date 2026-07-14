<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAboutUsSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'plain' => ['nullable', 'string', 'max:50000'],
            'html_override' => ['nullable', 'string', 'max:50000'],
            'html_active' => ['nullable', 'boolean'],
        ];
    }
}
