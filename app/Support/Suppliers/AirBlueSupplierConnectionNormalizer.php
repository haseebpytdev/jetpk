<?php

namespace App\Support\Suppliers;

use App\Enums\AirBlueApiChannel;
use App\Enums\SupplierProvider;
use App\Models\SupplierConnection;

/**
 * Canonical AirBlue API settings mapping for admin forms.
 */
final class AirBlueSupplierConnectionNormalizer
{
    public static function normalizeEnvironment(string $environment): string
    {
        return strtolower(trim($environment)) === 'live' ? 'live' : 'sandbox';
    }

    public static function defaultConnectionName(?string $agencyName): string
    {
        $name = trim((string) $agencyName);

        return $name !== '' ? 'AirBlue / '.$name : 'AirBlue / OTA';
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function normalizePayload(array $payload, ?SupplierConnection $existing = null): array
    {
        if (($payload['provider'] ?? '') !== SupplierProvider::Airblue->value) {
            return $payload;
        }

        $environment = self::normalizeEnvironment((string) ($payload['environment'] ?? 'sandbox'));
        $payload['environment'] = $environment;
        $isTest = $environment !== 'live';

        $credentials = is_array($payload['credentials'] ?? null) ? $payload['credentials'] : [];
        $existingCredentials = ($existing !== null && is_array($existing->credentials)) ? $existing->credentials : [];
        $channel = AirBlueApiChannel::fromCredentials($credentials);
        $credentials['api_channel'] = $channel->value;

        $baseUrl = trim((string) ($payload['base_url'] ?? $existing?->base_url ?? ''));
        if ($baseUrl === '') {
            $baseUrl = $channel === AirBlueApiChannel::ZapwaysOta
                ? ($isTest
                    ? (string) config('suppliers.airblue.default_ota_qa_base_url', '')
                    : (string) config('suppliers.airblue.default_ota_base_url', ''))
                : (string) config('suppliers.airblue.default_ndc_base_url', '');
        }
        $payload['base_url'] = $baseUrl;

        if ($channel === AirBlueApiChannel::CraneNdc) {
            foreach (['username', 'password', 'agency_id', 'agency_name', 'owner_code'] as $key) {
                $incoming = trim((string) ($credentials[$key] ?? ''));
                if ($incoming === '' && isset($existingCredentials[$key])) {
                    $credentials[$key] = $existingCredentials[$key];
                }
            }
            foreach (['carrier_code' => 'PA', 'currency' => 'PKR', 'language_code' => 'EN'] as $key => $default) {
                if (trim((string) ($credentials[$key] ?? '')) === '') {
                    $credentials[$key] = trim((string) ($existingCredentials[$key] ?? '')) ?: $default;
                }
            }
        } else {
            foreach (['client_id', 'client_key', 'agent_type', 'agent_id', 'agent_password'] as $key) {
                $incoming = trim((string) ($credentials[$key] ?? ''));
                if ($incoming === '' && isset($existingCredentials[$key])) {
                    $credentials[$key] = $existingCredentials[$key];
                }
            }
            foreach (['service_target' => 'Production', 'service_version' => '1.04'] as $key => $default) {
                if (trim((string) ($credentials[$key] ?? '')) === '') {
                    $credentials[$key] = trim((string) ($existingCredentials[$key] ?? '')) ?: $default;
                }
            }
        }

        $payload['credentials'] = $credentials;
        $payload['settings'] = is_array($payload['settings'] ?? null) ? $payload['settings'] : [];

        return $payload;
    }
}
