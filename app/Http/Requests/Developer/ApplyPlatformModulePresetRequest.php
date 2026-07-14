<?php

namespace App\Http\Requests\Developer;

use App\Support\Platform\PlatformModuleRegistry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ApplyPlatformModulePresetRequest extends FormRequest
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
        return [
            'preset_key' => [
                'required',
                'string',
                Rule::in(array_keys(PlatformModuleRegistry::recommendedProductModes())),
            ],
        ];
    }
}
