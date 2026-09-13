<?php

namespace App\Support\Suppliers;

use App\Enums\AirBlueApiChannel;
use App\Enums\AirBlueZapwaysProtocolVersion;
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

    public static function defaultConnectionName(?string $agencyName, ?string $protocolVersion = null): string
    {
        $name = trim((string) $agencyName);
        $protocol = trim((string) ($protocolVersion ?? AirBlueZapwaysProtocolVersion::V2->value));
        $suffix = $protocol === AirBlueZapwaysProtocolVersion::V3->value ? 'v3' : 'v2';

        return $name !== '' ? 'AirBlue / Zapways '.$suffix.' / '.$name : 'AirBlue / Zapways '.$suffix;
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
        $credentials['api_channel'] = AirBlueApiChannel::ZapwaysOta->value;

        $protocol = AirBlueZapwaysProtocolVersion::fromCredentials(
            array_merge($existingCredentials, $credentials),
        );
        if (trim((string) ($credentials['protocol_version'] ?? '')) === '') {
            $credentials['protocol_version'] = $protocol->value;
        }

        if ($protocol->isV3() && ! array_key_exists('certification_status', $credentials) && ! array_key_exists('search_certified', $credentials)) {
            $credentials['certification_status'] = 'pending';
        }

        $baseUrl = trim((string) ($payload['base_url'] ?? $existing?->base_url ?? ''));
        if ($baseUrl === '') {
            $protocolConfig = (array) config('suppliers.airblue.protocol_versions.'.$protocol->value, []);
            $baseUrl = $isTest
                ? (string) ($protocolConfig['default_qa_base_url'] ?? config('suppliers.airblue.default_ota_qa_base_url', ''))
                : (string) ($protocolConfig['default_base_url'] ?? config('suppliers.airblue.default_ota_base_url', ''));
        }
        $payload['base_url'] = $baseUrl;

        foreach (['client_id', 'client_key', 'agent_type', 'agent_id', 'agent_password', 'tls_cert_path', 'tls_key_path'] as $key) {
            $incoming = trim((string) ($credentials[$key] ?? ''));
            if ($incoming === '' && isset($existingCredentials[$key])) {
                $credentials[$key] = $existingCredentials[$key];
            }
        }
        $defaultTarget = $isTest ? 'Test' : 'Production';
        foreach (['service_target' => $defaultTarget, 'service_version' => '1.04'] as $key => $default) {
            if (trim((string) ($credentials[$key] ?? '')) === '') {
                $credentials[$key] = trim((string) ($existingCredentials[$key] ?? '')) ?: $default;
            }
        }

        $payload['credentials'] = $credentials;
        $payload['settings'] = is_array($payload['settings'] ?? null) ? $payload['settings'] : [];

        return $payload;
    }
}
