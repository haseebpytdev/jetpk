<?php

namespace App\Http\Requests\Developer;

use App\Models\ClientProfile;
use App\Support\Client\ClientProfileConfigReader;

class UpdateDevCpClientProfileModulesRequest extends DevCpAuthorizedRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var ClientProfile|null $profile */
        $profile = $this->route('clientProfile');

        $rules = [
            'modules' => ['required', 'array'],
        ];

        foreach (ClientProfileConfigReader::MODULE_KEYS as $moduleKey) {
            $rules['modules.'.$moduleKey] = ['sometimes', 'boolean'];
        }

        if ($profile?->is_master_profile) {
            $rules['confirm_master_edit'] = ['required', 'in:1'];
        }

        return $rules;
    }

    protected function prepareForValidation(): void
    {
        $modules = $this->input('modules', []);
        if (! is_array($modules)) {
            return;
        }

        $normalized = [];
        foreach (ClientProfileConfigReader::MODULE_KEYS as $moduleKey) {
            if (array_key_exists($moduleKey, $modules)) {
                $normalized[$moduleKey] = filter_var($modules[$moduleKey], FILTER_VALIDATE_BOOL);
            }
        }

        $this->merge(['modules' => $normalized]);
    }
}
