<?php

namespace App\Http\Requests\Admin;

use App\Enums\SupplierConnectionStatus;
use App\Enums\SupplierEnvironment;
use App\Enums\SupplierProvider;
use App\Support\Suppliers\GroupSupplierCredentialValidator;
use App\Support\Suppliers\SupplierIntegrationCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSupplierConnectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'provider' => ['required', 'string', Rule::in(SupplierIntegrationCatalog::createableKeys())],
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('supplier_connections', 'name')->where(function ($query): void {
                    $user = $this->user();
                    if ($user === null || $user->current_agency_id === null) {
                        $query->whereRaw('1 = 0');

                        return;
                    }
                    $query->where('agency_id', $user->current_agency_id)
                        ->where('provider', (string) $this->input('provider'));
                }),
            ],
            'environment' => ['required', Rule::enum(SupplierEnvironment::class)],
            'status' => ['nullable', Rule::enum(SupplierConnectionStatus::class)],
            'base_url' => ['nullable', 'url', 'max:500'],
            'credentials' => ['nullable', 'array'],
            'credentials.*' => ['nullable', 'string', 'max:2000'],
            'settings_json' => ['nullable', 'json'],
            'meta' => ['nullable', 'array'],
            'sabre_gds_enabled' => ['nullable', 'boolean'],
            'sabre_ndc_enabled' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $provider = (string) $this->input('provider');
            $credentials = $this->input('credentials', []);
            if (! is_array($credentials)) {
                $credentials = [];
            }
            $filled = array_filter($credentials, fn ($value): bool => trim((string) $value) !== '');
            $keys = array_map('strtolower', array_keys($filled));
            $providerFields = (array) config('supplier_credentials.providers.'.$provider.'.fields', []);

            foreach ($providerFields as $fieldKey => $meta) {
                $required = (bool) ($meta['required'] ?? false);
                if (! $required) {
                    continue;
                }
                if (! in_array(strtolower($fieldKey), $keys, true)) {
                    $validator->errors()->add('credentials.'.$fieldKey, 'This field is required for '.strtoupper($provider).'.');
                }
            }

            if ($provider === SupplierProvider::PiaNdc->value) {
                foreach (['username', 'password', 'agency_id', 'agency_name', 'owner_code'] as $field) {
                    if (! in_array($field, $keys, true)) {
                        $validator->errors()->add('credentials.'.$field, 'This field is required for PIA NDC.');
                    }
                }
                if (trim((string) $this->input('base_url', '')) === '') {
                    $validator->errors()->add('base_url', 'PIA NDC base URL is required.');
                }
            }

            if ($provider === SupplierProvider::Airblue->value) {
                foreach (['client_id', 'client_key', 'agent_id', 'agent_password'] as $field) {
                    if (! in_array($field, $keys, true)) {
                        $validator->errors()->add('credentials.'.$field, 'This field is required for AirBlue Zapways OTA.');
                    }
                }

                return;
            }

            if ($provider === SupplierProvider::Iati->value) {
                // auth_code required; organization_id optional (matches resolver/config contract).
                if (! in_array('auth_code', $keys, true)) {
                    $validator->errors()->add('credentials.auth_code', 'This field is required for IATI.');
                }
            }

            if (in_array($provider, [SupplierProvider::AlHaider->value, SupplierProvider::AmeerEMillat->value], true)) {
                GroupSupplierCredentialValidator::validate(
                    $validator,
                    $provider,
                    $credentials,
                    [],
                    false,
                );
            }
        });
    }
}
