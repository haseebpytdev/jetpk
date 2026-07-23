<?php

namespace App\Services\Suppliers\OneApi\Support;

use App\Enums\SupplierEnvironment;
use App\Models\SupplierConnection;
use App\Services\Suppliers\OneApi\Exceptions\OneApiValidationException;

/**
 * Resolves One API connection credentials and operational settings.
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

        $username = trim((string) ($credentials['username'] ?? ''));
        $password = trim((string) ($credentials['password'] ?? ''));
        $agentCode = trim((string) ($credentials['agent_code'] ?? ''));
        $restAuthUrl = trim((string) ($credentials['rest_auth_url'] ?? ''));
        $restSearchUrl = trim((string) ($credentials['rest_search_url'] ?? ''));
        $soapUrl = trim((string) ($credentials['soap_url'] ?? ''));

        if ($username === '' || $password === '' || $agentCode === '' || $restAuthUrl === '' || $restSearchUrl === '') {
            throw new OneApiValidationException(
                'configuration_error',
                422,
                'One API connection is missing required credentials.',
            );
        }

        $environment = $connection->environment?->value ?? 'sandbox';
        $isStaging = in_array($connection->environment, [SupplierEnvironment::Demo, SupplierEnvironment::Sandbox], true)
            || strtolower($environment) !== 'production';

        return [
            'connection_id' => (int) $connection->id,
            'environment' => $environment,
            'is_staging' => $isStaging,
            'username' => $username,
            'password' => $password,
            'agent_code' => $agentCode,
            'agent_preferred_currency' => strtoupper(trim((string) ($credentials['agent_preferred_currency'] ?? 'AED'))),
            'pos_country' => strtoupper(trim((string) ($credentials['pos_country'] ?? ''))),
            'pos_station' => strtoupper(trim((string) ($credentials['pos_station'] ?? ''))),
            'sales_channel' => trim((string) ($credentials['sales_channel'] ?? 'OTA')) ?: 'OTA',
            'terminal_id' => trim((string) ($credentials['terminal_id'] ?? '')),
            'rest_auth_url' => $restAuthUrl,
            'rest_search_url' => $restSearchUrl,
            'soap_url' => $soapUrl,
            'soap_action_price' => trim((string) ($credentials['soap_action_price'] ?? '')),
            'soap_action_baggage' => trim((string) ($credentials['soap_action_baggage'] ?? '')),
            'soap_action_meal' => trim((string) ($credentials['soap_action_meal'] ?? '')),
            'soap_action_seat_map' => trim((string) ($credentials['soap_action_seat_map'] ?? '')),
            'soap_action_book' => trim((string) ($credentials['soap_action_book'] ?? '')),
            'soap_action_read' => trim((string) ($credentials['soap_action_read'] ?? '')),
            'soap_action_modify' => trim((string) ($credentials['soap_action_modify'] ?? '')),
            'connect_timeout_seconds' => $this->intValue($credentials, 'connect_timeout_seconds', (int) config('suppliers.one_api.connect_timeout_seconds', 10)),
            'request_timeout_seconds' => $this->intValue($credentials, 'request_timeout_seconds', (int) config('suppliers.one_api.request_timeout_seconds', 60)),
            'search_timeout_seconds' => $this->intValue($credentials, 'search_timeout_seconds', (int) config('suppliers.one_api.search_timeout_seconds', 90)),
            'verify_tls' => $this->boolValue($credentials, 'verify_tls', true),
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
            'max_search_results' => $this->intValue($credentials, 'max_search_results', 200),
            'log_payloads' => $this->boolValue($credentials, 'log_payloads', false),
            'notes' => trim((string) ($credentials['notes'] ?? '')),
            'settings' => $settings,
            'meta' => $meta,
        ];
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
