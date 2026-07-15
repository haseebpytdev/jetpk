<?php

namespace App\Http\Requests\Developer;

use App\Models\ClientProfile;

class UpdateDevCpClientProfileRequest extends DevCpAuthorizedRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var ClientProfile|null $profile */
        $profile = $this->route('clientProfile');

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'domain' => ['nullable', 'string', 'max:255'],
            'environment' => ['required', 'string', 'max:64'],
            'default_locale' => ['required', 'string', 'max:16'],
            'timezone' => ['required', 'string', 'max:64'],
            'currency' => ['required', 'string', 'max:8'],
            'is_active' => ['sometimes', 'boolean'],
        ];

        if ($profile?->is_master_profile) {
            $rules['confirm_master_edit'] = ['required', 'in:1'];
        }

        return $rules;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active'),
        ]);
    }
}
