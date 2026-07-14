<?php

namespace App\Http\Requests\Developer;

use App\Enums\SupplierEnvironment;
use App\Enums\SupplierProvider;
use App\Models\ClientProfile;
use Illuminate\Validation\Rule;

class UpdateDevCpClientProfileSuppliersRequest extends DevCpAuthorizedRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var ClientProfile|null $profile */
        $profile = $this->route('clientProfile');

        $rules = [
            'suppliers' => ['required', 'array'],
        ];

        foreach (SupplierProvider::cases() as $provider) {
            $key = $provider->value;
            $rules['suppliers.'.$key.'.enabled'] = ['sometimes', 'boolean'];
            $rules['suppliers.'.$key.'.mode'] = ['nullable', 'string', Rule::enum(SupplierEnvironment::class)];
            $rules['suppliers.'.$key.'.credentials'] = ['nullable', 'array'];
            $rules['suppliers.'.$key.'.credentials.*'] = ['nullable', 'string', 'max:500'];
        }

        if ($profile?->is_master_profile) {
            $rules['confirm_master_edit'] = ['required', 'in:1'];
        }

        return $rules;
    }

    protected function prepareForValidation(): void
    {
        $suppliers = $this->input('suppliers', []);
        if (! is_array($suppliers)) {
            return;
        }

        $normalized = [];
        foreach (SupplierProvider::cases() as $provider) {
            $key = $provider->value;
            if (! isset($suppliers[$key]) || ! is_array($suppliers[$key])) {
                continue;
            }

            $row = $suppliers[$key];
            $normalized[$key] = [
                'enabled' => filter_var($row['enabled'] ?? false, FILTER_VALIDATE_BOOL),
                'mode' => $row['mode'] ?? null,
                'credentials' => is_array($row['credentials'] ?? null) ? $row['credentials'] : null,
            ];
        }

        $this->merge(['suppliers' => $normalized]);
    }
}
