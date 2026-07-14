<?php

namespace App\Http\Requests\Developer;

use App\Models\ClientProfile;
use Illuminate\Validation\Rule;

class DuplicateDevCpClientProfileRequest extends DevCpAuthorizedRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var ClientProfile|null $profile */
        $profile = $this->route('clientProfile');

        $rules = [
            'new_name' => ['required', 'string', 'max:255'],
            'new_slug' => ['required', 'string', 'max:255', 'alpha_dash', Rule::unique('client_profiles', 'slug')],
            'copy_credentials' => ['sometimes', 'boolean'],
        ];

        if ($profile?->is_master_profile) {
            $rules['confirm_master_edit'] = ['required', 'in:1'];
        }

        return $rules;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'copy_credentials' => $this->boolean('copy_credentials'),
        ]);
    }
}
