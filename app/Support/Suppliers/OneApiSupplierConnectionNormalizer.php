<?php

namespace App\Support\Suppliers;

use App\Enums\SupplierProvider;
use App\Models\SupplierConnection;

/**
 * Canonical One API admin form mapping — preserve legacy credentials; account context lives in settings.
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

        foreach ([
            'rest_username',
            'rest_password',
            'soap_username',
            'soap_password',
            'username',
            'password',
            'agent_code',
            'rest_auth_url',
            'rest_search_url',
            'soap_url',
        ] as $key) {
            $incoming = trim((string) ($credentials[$key] ?? ''));
            if ($incoming === '' && isset($existingCredentials[$key])) {
                $credentials[$key] = $existingCredentials[$key];
            }
        }

        if (trim((string) ($credentials['sales_channel'] ?? '')) === '') {
            $existingChannel = trim((string) ($existingCredentials['sales_channel'] ?? ''));
            if ($existingChannel !== '') {
                $credentials['sales_channel'] = $existingChannel;
            }
        }

        $payload['credentials'] = $credentials;

        $settings = is_array($payload['settings'] ?? null) ? $payload['settings'] : [];
        $existingSettings = ($existing !== null && is_array($existing->settings)) ? $existing->settings : [];
        $confirmChecked = (bool) ($payload['account_context_confirm'] ?? false);
        $payload['settings'] = OneApiAccountContext::mergeSettings($settings, $existingSettings, $confirmChecked);
        unset($payload['account_context_confirm']);

        return $payload;
    }
}
