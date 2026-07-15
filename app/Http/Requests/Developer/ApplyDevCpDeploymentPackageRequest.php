<?php

namespace App\Http\Requests\Developer;

use Illuminate\Foundation\Http\FormRequest;

class ApplyDevCpDeploymentPackageRequest extends FormRequest
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
            'package_id' => ['required', 'integer', 'exists:platform_packages,id'],
        ];
    }
}
