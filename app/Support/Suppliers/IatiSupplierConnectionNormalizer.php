<?php

namespace App\Support\Suppliers;

use App\Enums\SupplierProvider;
use App\Models\SupplierConnection;

/**
 * Canonical IATI API settings mapping for admin forms — environment → flight base URL,
 * default language_code, and cert/live labels stored as sandbox/live internally.
 * Flight v2 auth uses auth_code as Bearer token; secret is optional (legacy exchange only).
 */
final class IatiSupplierConnectionNormalizer
{
    public const CERT_FLIGHT_BASE = 'https://testapi.iati.com/rest/flight/v2';

    public const LIVE_FLIGHT_BASE = 'https://api.iati.com/rest/flight/v2';

    public const CERT_AUTH_BASE = 'https://testapi.iati.com/rest/auth';

    public const LIVE_AUTH_BASE = 'https://api.iati.com/rest/auth';

    public static function flightBaseUrlForEnvironment(string $environment): string
    {
        return self::normalizeEnvironment($environment) === 'live'
            ? self::LIVE_FLIGHT_BASE
            : self::CERT_FLIGHT_BASE;
    }

    public static function authBaseUrlForEnvironment(string $environment): string
    {
        return self::normalizeEnvironment($environment) === 'live'
            ? self::LIVE_AUTH_BASE
            : self::CERT_AUTH_BASE;
    }

    public static function normalizeEnvironment(string $environment): string
    {
        return strtolower(trim($environment)) === 'live' ? 'live' : 'sandbox';
    }

    public static function defaultConnectionName(?string $agencyName): string
    {
        $name = trim((string) $agencyName);

        return $name !== '' ? 'IATI / '.$name : 'IATI / OTA';
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function normalizePayload(array $payload, ?SupplierConnection $existing = null): array
    {
        if (($payload['provider'] ?? '') !== SupplierProvider::Iati->value) {
            return $payload;
        }

        $environment = self::normalizeEnvironment((string) ($payload['environment'] ?? 'sandbox'));
        $payload['environment'] = $environment;
        $payload['base_url'] = self::flightBaseUrlForEnvironment($environment);

        $credentials = is_array($payload['credentials'] ?? null) ? $payload['credentials'] : [];
        $existingCredentials = ($existing !== null && is_array($existing->credentials)) ? $existing->credentials : [];

        foreach (['auth_code', 'secret', 'organization_id'] as $key) {
            $incoming = trim((string) ($credentials[$key] ?? ''));
            if ($incoming === '' && isset($existingCredentials[$key])) {
                $credentials[$key] = $existingCredentials[$key];
            }
        }

        if (trim((string) ($credentials['language_code'] ?? '')) === '') {
            $credentials['language_code'] = trim((string) ($existingCredentials['language_code'] ?? '')) ?: 'en';
        }

        $payload['credentials'] = $credentials;
        $payload['settings'] = is_array($payload['settings'] ?? null) ? $payload['settings'] : [];

        return $payload;
    }
}
