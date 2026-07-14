<?php

namespace App\Http\Requests\Developer;

use App\Models\ClientProfile;

class UpdateDevCpClientProfileThemeRequest extends DevCpAuthorizedRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var ClientProfile|null $profile */
        $profile = $this->route('clientProfile');

        $rules = [
            'active_frontend_theme' => ['required', 'string', 'max:64'],
            'active_admin_theme' => ['nullable', 'string', 'max:64'],
            'active_staff_theme' => ['nullable', 'string', 'max:64'],
            'asset_profile' => ['required', 'string', 'max:255'],
            'preview_path' => ['nullable', 'string', 'max:255'],
        ];

        if ($profile?->is_master_profile) {
            $rules['confirm_master_edit'] = ['required', 'in:1'];
        }

        return $rules;
    }
}
