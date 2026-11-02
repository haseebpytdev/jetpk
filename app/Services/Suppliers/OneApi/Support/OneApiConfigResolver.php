<?php

namespace App\Services\Suppliers\OneApi\Support;

use App\Enums\SupplierEnvironment;
use App\Models\SupplierConnection;
use App\Services\Suppliers\OneApi\Exceptions\OneApiValidationException;
use App\Support\Suppliers\OneApiAccountContext;

/**
 * Resolves One API connection credentials and operational settings.
 *
 * REST/SOAP endpoints default from system config; legacy credential URL overrides remain supported.
 */
class OneApiConfigResolver
{
    /**
     * @return array<string, mixed>
     */
    public function resolve(SupplierConnection $connection): array
    {
        $credentials = is_array($connection->credentials) ? $connection->credentials : [];
        $settings = is_array($connection->settings) ? $connection->settings : [];
        $meta = is_array($connection->meta) ? $connection->meta : [];

        $legacyUsername = trim((string) ($credentials['username'] ?? ''));
        $legacyPassword = (string) ($credentials['password'] ?? '');

        $restUsername = trim((string) ($credentials['rest_username'] ?? ''));
        if ($restUsername === '') {
            $restUsername = $legacyUsername;
        }

        $restPassword = (string) ($credentials['rest_password'] ?? '');
        if (trim($restPassword) === '') {
            $restPassword = $legacyPassword;
        }

        $soapUsername = trim((string) ($credentials['soap_username'] ?? ''));
        if ($soapUsername === '') {
            $soapUsername = $legacyUsername;
        }

        $soapPassword = (string) ($credentials['soap_password'] ?? '');
        if (trim($soapPassword) === '') {
            $soapPassword = $legacyPassword;
        }

        $agentCode = trim((string) ($credentials['agent_code'] ?? ''));

        $endpoints = $this->resolveEndpointUrls($connection, $credentials);
        $restAuthUrl = $endpoints['rest_auth_url'];
        $restSearchUrl = $endpoints['rest_search_url'];
        $soapUrl = $endpoints['soap_url'];
        $wsdlUrl = $endpoints['wsdl_url'];

        if ($restUsername === '' || trim($restPassword) === '' || $agentCode === '' || $restAuthUrl === '' || $restSearchUrl === '') {
            throw new OneApiValidationException(
                'configuration_error',
                422,
                'One API connection is missing required credentials.',
            );
        }

        $accountContext = OneApiAccountContext::resolve($connection);

        $environment = $connection->environment?->value ?? 'sandbox';
        $isStaging = in_array($connection->environment, [SupplierEnvironment::Demo, SupplierEnvironment::Sandbox], true)
            || strtolower($environment) !== 'production';

        $soapOperations = (array) config('suppliers.one_api.soap_operations', []);

        return [
            'connection_id' => (int) $connection->id,
            'environment' => $environment,
            'is_staging' => $isStaging,
            'rest_username' => $restUsername,
            'rest_password' => $restPassword,
            'soap_username' => $soapUsername,
            'soap_password' => $soapPassword,
            'username' => $restUsername,
            'password' => $restPassword,
            'agent_code' => $agentCode,
            'agent_preferred_currency' => $accountContext['agent_preferred_currency'],
            'pos_country' => $accountContext['pos_country'],
            'pos_station' => $accountContext['pos_station'],
            'account_context_status' => $accountContext['account_context_status'],
            'account_context_confirmed' => $accountContext['account_context_confirmed'],
            'sales_channel' => trim((string) ($credentials['sales_channel'] ?? ''))
                ?: trim((string) config('suppliers.one_api.sales_channel', 'OTA'))
                ?: 'OTA',
            'terminal_id' => trim((string) ($credentials['terminal_id'] ?? '')),
            'rest_auth_url' => $restAuthUrl,
            'rest_search_url' => $restSearchUrl,
            'soap_url' => $soapUrl,
            'wsdl_url' => $wsdlUrl,
            'soap_action_price' => $this->soapAction($credentials, $soapOperations, 'price'),
            'soap_action_baggage' => $this->soapAction($credentials, $soapOperations, 'baggage'),
            'soap_action_meal' => $this->soapAction($credentials, $soapOperations, 'meal'),
            'soap_action_seat_map' => $this->soapAction($credentials, $soapOperations, 'seat_map'),
            'soap_action_book' => $this->soapAction($credentials, $soapOperations, 'book'),
            'soap_action_read' => $this->soapAction($credentials, $soapOperations, 'read'),
            'soap_action_modify' => $this->soapAction($credentials, $soapOperations, 'modify'),
            'connect_timeout_seconds' => $this->intValue($credentials, 'connect_timeout_seconds', (int) config('suppliers.one_api.connect_timeout_seconds', 10)),
            'request_timeout_seconds' => $this->intValue($credentials, 'request_timeout_seconds', (int) config('suppliers.one_api.request_timeout_seconds', 60)),
            'search_timeout_seconds' => $this->intValue($credentials, 'search_timeout_seconds', (int) config('suppliers.one_api.search_timeout_seconds', 90)),
            'verify_tls' => $this->boolValue($credentials, 'verify_tls', (bool) config('suppliers.one_api.verify_tls', true)),
            'carrier_allowlist' => $this->listValue($credentials, 'carrier_allowlist'),
            'marketing_carrier_allowlist' => $this->listValue($credentials, 'marketing_carrier_allowlist'),
            'operating_carrier_allowlist' => $this->listValue($credentials, 'operating_carrier_allowlist'),
            'allow_interline' => $this->boolValue($credentials, 'allow_interline', false),
            'on_hold_enabled' => $this->boolValue($credentials, 'on_hold_enabled', false),
            'hold_payment_enabled' => $this->boolValue($credentials, 'hold_payment_enabled', false),
            'direct_bill_enabled' => $this->boolValue($credentials, 'direct_bill_enabled', true),
            'live_search_enabled' => $this->boolValue($credentials, 'live_search_enabled', (bool) config('suppliers.one_api.live_search_enabled', false)),
            'live_booking_enabled' => $this->boolValue($credentials, 'live_booking_enabled', (bool) config('suppliers.one_api.live_booking_enabled', false)),
            'live_payment_modification_enabled' => $this->boolValue($credentials, 'live_payment_modification_enabled', (bool) config('suppliers.one_api.live_payment_modification_enabled', false)),
            'max_search_results' => $this->intValue($credentials, 'max_search_results', (int) config('suppliers.one_api.max_search_results', 200)),
            'log_payloads' => $this->boolValue($credentials, 'log_payloads', (bool) config('suppliers.one_api.log_payloads', false)),
            'notes' => trim((string) ($credentials['notes'] ?? '')),
            'settings' => $settings,
            'meta' => $meta,
        ];
    }

    /**
     * @param  array<string, mixed>  $credentials
     * @return array{rest_auth_url: string, rest_search_url: string, soap_url: string, wsdl_url: string}
     */
    public function resolveEndpointUrls(SupplierConnection $connection, array $credentials): array
    {
        $environment = strtolower((string) ($connection->environment?->value ?? 'sandbox'));
        $bucket = in_array($environment, ['production', 'live'], true) ? 'production' : 'sandbox';
        $system = (array) config('suppliers.one_api.endpoints.'.$bucket, []);

        return [
            'rest_auth_url' => $this->endpointOverride($credentials, 'rest_auth_url', $system),
            'rest_search_url' => $this->endpointOverride($credentials, 'rest_search_url', $system),
            'soap_url' => $this->endpointOverride($credentials, 'soap_url', $system),
            'wsdl_url' => $this->endpointOverride($credentials, 'wsdl_url', $system),
        ];
    }

    /**
     * @param  array<string, mixed>  $credentials
     * @param  array<string, mixed>  $system
     */
    private function endpointOverride(array $credentials, string $key, array $system): string
    {
        $override = trim((string) ($credentials[$key] ?? ''));
        if ($override !== '') {
            return $override;
        }

        return trim((string) ($system[$key] ?? ''));
    }

    /**
     * @param  array<string, mixed>  $credentials
     * @param  array<string, mixed>  $soapOperations
     */
    private function soapAction(array $credentials, array $soapOperations, string $operation): string
    {
        $credentialKey = 'soap_action_'.$operation;
        $fromCredential = trim((string) ($credentials[$credentialKey] ?? ''));
        if ($fromCredential !== '') {
            return $fromCredential;
        }

        return trim((string) data_get($soapOperations, $operation.'.soap_action', ''));
    }

    /**
     * @param  array<string, mixed>  $credentials
     */
    private function boolValue(array $credentials, string $key, bool $default): bool
    {
        if (! array_key_exists($key, $credentials)) {
            return $default;
        }

        $raw = strtolower(trim((string) $credentials[$key]));
        if ($raw === '') {
            return $default;
        }

        return in_array($raw, ['1', 'true', 'yes', 'on'], true);
    }

    /**
     * @param  array<string, mixed>  $credentials
     */
    private function intValue(array $credentials, string $key, int $default): int
    {
        $raw = trim((string) ($credentials[$key] ?? ''));
        if ($raw === '' || ! is_numeric($raw)) {
            return $default;
        }

        return max(1, (int) $raw);
    }

    /**
     * @param  array<string, mixed>  $credentials
     * @return list<string>
     */
    private function listValue(array $credentials, string $key): array
    {
        $raw = trim((string) ($credentials[$key] ?? ''));
        if ($raw === '') {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn (string $part): string => strtoupper(trim($part)),
            preg_split('/[\s,;]+/', $raw) ?: [],
        )));
    }
}
