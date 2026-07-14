<?php

namespace App\Http\Requests\Developer;

use Illuminate\Validation\Rule;

class StoreDevCpClientProfileRequest extends DevCpAuthorizedRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'alpha_dash', Rule::unique('client_profiles', 'slug')],
            'domain' => ['nullable', 'string', 'max:255'],
            'environment' => ['required', 'string', 'max:64'],
            'default_locale' => ['required', 'string', 'max:16'],
            'timezone' => ['required', 'string', 'max:64'],
            'currency' => ['required', 'string', 'max:8'],
            'is_active' => ['sometimes', 'boolean'],
            'active_frontend_theme' => ['nullable', 'string', 'max:64'],
            'asset_profile' => ['nullable', 'string', 'max:255'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active'),
        ]);
    }
}
