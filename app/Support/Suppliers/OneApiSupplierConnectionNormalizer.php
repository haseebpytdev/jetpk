<?php

namespace App\Support\Suppliers;

use App\Enums\SupplierProvider;
use App\Models\SupplierConnection;

/**
 * Canonical One API admin form mapping — preserve vendor-supplied REST/SOAP URLs, merge defaults.
 */
final class OneApiSupplierConnectionNormalizer
{
    public static function normalizeEnvironment(string $environment): string
    {
        return strtolower(trim($environment)) === 'live' ? 'live' : 'sandbox';
    }

    public static function defaultConnectionName(?string $agencyName): string
    {
        $name = trim((string) $agencyName);

        return $name !== '' ? 'One API / '.$name : 'One API / OTA';
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function normalizePayload(array $payload, ?SupplierConnection $existing = null): array
    {
        if (($payload['provider'] ?? '') !== SupplierProvider::OneApi->value) {
            return $payload;
        }

        $payload['environment'] = self::normalizeEnvironment((string) ($payload['environment'] ?? 'sandbox'));

        $credentials = is_array($payload['credentials'] ?? null) ? $payload['credentials'] : [];
        $existingCredentials = ($existing !== null && is_array($existing->credentials)) ? $existing->credentials : [];

        foreach (['username', 'password', 'agent_code', 'rest_auth_url', 'rest_search_url', 'soap_url'] as $key) {
            $incoming = trim((string) ($credentials[$key] ?? ''));
            if ($incoming === '' && isset($existingCredentials[$key])) {
                $credentials[$key] = $existingCredentials[$key];
            }
        }

        foreach (['agent_preferred_currency' => 'AED', 'pos_country' => 'AE', 'pos_station' => 'DXB', 'sales_channel' => 'OTA'] as $key => $default) {
            if (trim((string) ($credentials[$key] ?? '')) === '') {
                $credentials[$key] = trim((string) ($existingCredentials[$key] ?? '')) ?: $default;
            }
        }

        $payload['credentials'] = $credentials;
        $payload['settings'] = is_array($payload['settings'] ?? null) ? $payload['settings'] : [];

        return $payload;
    }
}
