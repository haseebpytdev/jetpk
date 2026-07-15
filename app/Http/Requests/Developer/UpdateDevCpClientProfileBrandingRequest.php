<?php

namespace App\Http\Requests\Developer;

use App\Models\ClientProfile;

class UpdateDevCpClientProfileBrandingRequest extends DevCpAuthorizedRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var ClientProfile|null $profile */
        $profile = $this->route('clientProfile');

        $rules = [
            'company_name' => ['required', 'string', 'max:255'],
            'logo_path' => ['nullable', 'string', 'max:512'],
            'favicon_path' => ['nullable', 'string', 'max:512'],
            'primary_color' => ['nullable', 'string', 'max:32'],
            'secondary_color' => ['nullable', 'string', 'max:32'],
            'accent_color' => ['nullable', 'string', 'max:32'],
            'phone' => ['nullable', 'string', 'max:64'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:2000'],
            'footer_text' => ['nullable', 'string', 'max:2000'],
        ];

        if ($profile?->is_master_profile) {
            $rules['confirm_master_edit'] = ['required', 'in:1'];
        }

        return $rules;
    }
}
