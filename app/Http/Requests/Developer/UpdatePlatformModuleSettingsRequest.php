<?php

namespace App\Http\Requests\Developer;

use App\Support\Platform\PlatformModuleRegistry;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePlatformModuleSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->session()->has('dev_cp_user_id');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = [
            'modules' => ['required', 'array'],
            'notes' => ['sometimes', 'array'],
            'notes.*' => ['nullable', 'string', 'max:2000'],
        ];

        foreach (PlatformModuleRegistry::all() as $module) {
            $rules["modules.{$module->key}"] = ['required', 'boolean'];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'modules.required' => 'Module states are required.',
        ];
    }
}
