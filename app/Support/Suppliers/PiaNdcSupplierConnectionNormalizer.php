<?php

namespace App\Support\Suppliers;

use App\Enums\SupplierProvider;
use App\Models\SupplierConnection;

/**
 * Canonical PIA NDC API settings mapping for admin forms.
 */
final class PiaNdcSupplierConnectionNormalizer
{
    public static function normalizeEnvironment(string $environment): string
    {
        return strtolower(trim($environment)) === 'live' ? 'live' : 'sandbox';
    }

    public static function defaultConnectionName(?string $agencyName): string
    {
        $name = trim((string) $agencyName);

        return $name !== '' ? 'PIA NDC / '.$name : 'PIA NDC / OTA';
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function normalizePayload(array $payload, ?SupplierConnection $existing = null): array
    {
        if (($payload['provider'] ?? '') !== SupplierProvider::PiaNdc->value) {
            return $payload;
        }

        $environment = self::normalizeEnvironment((string) ($payload['environment'] ?? 'sandbox'));
        $payload['environment'] = $environment;

        $baseUrl = trim((string) ($payload['base_url'] ?? $existing?->base_url ?? ''));
        if ($baseUrl === '') {
            $baseUrl = trim((string) ($existing?->base_url ?? ''));
        }
        $payload['base_url'] = $baseUrl;

        $credentials = is_array($payload['credentials'] ?? null) ? $payload['credentials'] : [];
        $existingCredentials = ($existing !== null && is_array($existing->credentials)) ? $existing->credentials : [];

        $credentials = SupplierCredentialFormPresenter::preserveConfiguredCredentials(
            $credentials,
            $existingCredentials,
            SupplierProvider::PiaNdc->value,
            [
                'carrier_code' => 'PK',
                'currency' => 'PKR',
                'language_code' => 'EN',
                'payment_type' => 'MCO',
            ],
        );

        $payload['credentials'] = $credentials;
        $payload['settings'] = is_array($payload['settings'] ?? null) ? $payload['settings'] : [];

        return $payload;
    }
}
