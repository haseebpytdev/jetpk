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

        if (! array_key_exists('certification_status', $credentials) && ! array_key_exists('search_certified', $credentials)) {
            $existingCertification = trim((string) ($existingCredentials['certification_status'] ?? ''));
            $credentials['certification_status'] = $existingCertification !== '' ? $existingCertification : 'pending';
        }

        $protocolConfig = (array) config('suppliers.airblue.protocol_versions.'.$protocol->value, []);
        $payload['base_url'] = $isTest
            ? (string) ($protocolConfig['default_qa_base_url'] ?? config('suppliers.airblue.default_ota_qa_base_url', ''))
            : (string) ($protocolConfig['default_base_url'] ?? config('suppliers.airblue.default_ota_base_url', ''));

        foreach (['client_id', 'client_key', 'agent_id', 'agent_password'] as $key) {
            $incoming = trim((string) ($credentials[$key] ?? ''));
            if ($incoming === '' && isset($existingCredentials[$key])) {
                $credentials[$key] = $existingCredentials[$key];
            }
        }

        if (trim((string) ($credentials['agent_type'] ?? '')) === '') {
            $credentials['agent_type'] = trim((string) ($existingCredentials['agent_type'] ?? '')) ?: '29';
        }

        foreach (['tls_cert_path' => 'default_tls_cert_path', 'tls_key_path' => 'default_tls_key_path'] as $credentialKey => $configKey) {
            if (trim((string) ($credentials[$credentialKey] ?? '')) === '') {
                $existingPath = trim((string) ($existingCredentials[$credentialKey] ?? ''));
                if ($existingPath !== '') {
                    $credentials[$credentialKey] = $existingPath;
                    continue;
                }
                $runtimeDefault = trim((string) config('suppliers.airblue.'.$configKey, ''));
                if ($runtimeDefault !== '') {
                    $credentials[$credentialKey] = $runtimeDefault;
                }
            }
        }

        $defaultTarget = $isTest ? 'Test' : 'Production';
        foreach (['service_target' => $defaultTarget, 'service_version' => '1.04'] as $key => $default) {
            if (trim((string) ($credentials[$key] ?? '')) === '') {
                $credentials[$key] = trim((string) ($existingCredentials[$key] ?? '')) ?: $default;
            }
        }

        foreach (['carrier_code' => 'PA', 'currency' => 'PKR'] as $key => $default) {
            if (trim((string) ($credentials[$key] ?? '')) === '') {
                $credentials[$key] = trim((string) ($existingCredentials[$key] ?? '')) ?: $default;
            }
        }

        $payload['credentials'] = $credentials;
        $payload['settings'] = is_array($payload['settings'] ?? null) ? $payload['settings'] : [];

        return $payload;
    }
}
