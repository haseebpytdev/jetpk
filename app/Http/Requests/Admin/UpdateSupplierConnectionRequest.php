<?php

namespace App\Http\Requests\Admin;

use App\Enums\SupplierProvider;
use App\Support\Suppliers\GroupSupplierCredentialValidator;
use App\Support\Suppliers\SabreSupplierConnectionNormalizer;
use App\Support\Suppliers\SupplierCredentialFormPresenter;
use Illuminate\Validation\Rule;

class UpdateSupplierConnectionRequest extends StoreSupplierConnectionRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = parent::rules();
        $connection = $this->route('supplierConnection');
        $rules['name'] = [
            'required', 'string', 'max:255',
            Rule::unique('supplier_connections', 'name')
                ->where(function ($query): void {
                    $user = $this->user();
                    if ($user === null || $user->current_agency_id === null) {
                        $query->whereRaw('1 = 0');

                        return;
                    }
                    $query->where('agency_id', $user->current_agency_id)
                        ->where('provider', (string) $this->input('provider'));
                })
                ->ignore($connection),
        ];

        return $rules;
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $provider = (string) $this->input('provider');
            $providerFields = (array) config('supplier_credentials.providers.'.$provider.'.fields', []);
            $credentials = $this->input('credentials', []);
            if (! is_array($credentials)) {
                $credentials = [];
            }

            if ($provider === SupplierProvider::Duffel->value) {
                $connection = $this->route('supplierConnection');
                $existingToken = null;
                if ($connection !== null && method_exists($connection, 'getAttribute')) {
                    $currentCreds = $connection->credentials;
                    if (is_array($currentCreds)) {
                        $existingToken = trim((string) ($currentCreds['access_token'] ?? ''));
                    }
                }
                $incomingToken = trim((string) ($credentials['access_token'] ?? ''));
                if ($incomingToken === '' && $existingToken === '') {
                    $validator->errors()->add('credentials.access_token', 'Duffel requires access_token.');
                }

                return;
            }

            if ($provider === SupplierProvider::Sabre->value) {
                $connection = $this->route('supplierConnection');
                $existingCanonical = ['sign_in' => '', 'password' => ''];
                if ($connection !== null && method_exists($connection, 'getAttribute')) {
                    $existingCanonical = SabreSupplierConnectionNormalizer::canonicalCredentialsFromConnection($connection);
                }
                $incomingSignIn = trim((string) ($credentials['sign_in'] ?? ''));
                $incomingPassword = trim((string) ($credentials['password'] ?? ''));
                if ($incomingSignIn === '' && $existingCanonical['sign_in'] === '') {
                    $validator->errors()->add('credentials.sign_in', 'Sabre sign in / client ID is required.');
                }
                if ($incomingPassword === '' && $existingCanonical['password'] === '') {
                    $validator->errors()->add('credentials.password', 'Sabre secret / password is required.');
                }

                return;
            }

            if ($provider === SupplierProvider::Iati->value) {
                $connection = $this->route('supplierConnection');
                $existingCredentials = [];
                if ($connection !== null && method_exists($connection, 'getAttribute')) {
                    $currentCreds = $connection->credentials;
                    if (is_array($currentCreds)) {
                        $existingCredentials = $currentCreds;
                    }
                }

                $incomingAuthCode = trim((string) ($credentials['auth_code'] ?? ''));
                if ($incomingAuthCode === '' && trim((string) ($existingCredentials['auth_code'] ?? '')) === '') {
                    $validator->errors()->add('credentials.auth_code', 'IATI auth code is required.');
                }

                return;
            }

            if ($provider === SupplierProvider::PiaNdc->value) {
                $existingCredentials = is_array($this->route('supplierConnection')?->credentials)
                    ? $this->route('supplierConnection')->credentials
                    : [];
                foreach (['username', 'agency_id', 'agency_name', 'owner_code'] as $field) {
                    if (SupplierCredentialFormPresenter::effectiveValue($field, $credentials, $existingCredentials) === '') {
                        $validator->errors()->add('credentials.'.$field, 'This field is required for PIA NDC.');
                    }
                }
                if (SupplierCredentialFormPresenter::effectiveValue('password', $credentials, $existingCredentials) === '') {
                    $validator->errors()->add('credentials.password', 'PIA NDC password is required.');
                }
                if (trim((string) $this->input('base_url', '')) === '' && trim((string) ($this->route('supplierConnection')?->base_url ?? '')) === '') {
                    $validator->errors()->add('base_url', 'PIA NDC base URL is required.');
                }

                return;
            }

            if ($provider === SupplierProvider::Airblue->value) {
                $existingCredentials = is_array($this->route('supplierConnection')?->credentials)
                    ? $this->route('supplierConnection')->credentials
                    : [];
                foreach (['client_id', 'client_key', 'agent_id'] as $field) {
                    if (SupplierCredentialFormPresenter::effectiveValue($field, $credentials, $existingCredentials) === '') {
                        $validator->errors()->add('credentials.'.$field, 'This field is required for AirBlue Zapways OTA.');
                    }
                }
                if (SupplierCredentialFormPresenter::effectiveValue('agent_password', $credentials, $existingCredentials) === '') {
                    $validator->errors()->add('credentials.agent_password', 'AirBlue agent password is required.');
                }

                return;
            }

            $existingCredentials = is_array($this->route('supplierConnection')?->credentials)
                ? $this->route('supplierConnection')->credentials
                : [];

            if (in_array($provider, [SupplierProvider::AlHaider->value, SupplierProvider::AmeerEMillat->value], true)) {
                GroupSupplierCredentialValidator::validate(
                    $validator,
                    $provider,
                    $credentials,
                    $existingCredentials,
                    true,
                );

                return;
            }

            foreach ($providerFields as $fieldKey => $meta) {
                $required = (bool) ($meta['required'] ?? false);
                if (! $required) {
                    continue;
                }
                if (SupplierCredentialFormPresenter::effectiveValue($fieldKey, $credentials, $existingCredentials) === '') {
                    $validator->errors()->add('credentials.'.$fieldKey, 'This field is required when updating this provider.');
                }
            }
        });
    }
}
