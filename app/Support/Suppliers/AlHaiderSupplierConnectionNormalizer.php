<?php

namespace App\Support\Suppliers;

use App\Enums\SupplierProvider;
use App\Models\SupplierConnection;

/**
 * Normalizes Al-Haider API connection credentials for manual-token and future auto-token modes.
 */
final class AlHaiderSupplierConnectionNormalizer
{
    public const AUTH_MODE_MANUAL = 'manual_token';

    public const AUTH_MODE_AUTO = 'credentials_auto_token';

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function normalizePayload(array $payload, ?SupplierConnection $existing = null): array
    {
        if (($payload['provider'] ?? '') !== SupplierProvider::AlHaider->value) {
            return $payload;
        }

        $credentials = is_array($payload['credentials'] ?? null) ? $payload['credentials'] : [];
        $existingCredentials = ($existing !== null && is_array($existing->credentials)) ? $existing->credentials : [];

        $authMode = strtolower(trim((string) ($credentials['auth_mode'] ?? $existingCredentials['auth_mode'] ?? self::AUTH_MODE_MANUAL)));
        if (! in_array($authMode, [self::AUTH_MODE_MANUAL, self::AUTH_MODE_AUTO], true)) {
            $authMode = self::AUTH_MODE_MANUAL;
        }
        $credentials['auth_mode'] = $authMode;

        $clearToken = trim((string) ($credentials['clear_existing_token'] ?? '0')) === '1';
        unset($credentials['clear_existing_token']);

        if ($authMode === self::AUTH_MODE_MANUAL) {
            $incomingToken = trim((string) ($credentials['existing_token'] ?? ''));
            if ($clearToken) {
                unset($credentials['existing_token']);
            } elseif ($incomingToken === '' && isset($existingCredentials['existing_token'])) {
                $credentials['existing_token'] = $existingCredentials['existing_token'];
            }

            foreach (['username', 'password'] as $key) {
                unset($credentials[$key]);
            }
        } else {
            foreach (['username', 'password'] as $key) {
                $incoming = trim((string) ($credentials[$key] ?? ''));
                if ($incoming === '' && isset($existingCredentials[$key])) {
                    $credentials[$key] = $existingCredentials[$key];
                }
            }
            unset($credentials['existing_token'], $credentials['token_expires_at']);
        }

        $expires = trim((string) ($credentials['token_expires_at'] ?? ''));
        if ($authMode === self::AUTH_MODE_MANUAL && $expires === '' && isset($existingCredentials['token_expires_at'])) {
            $credentials['token_expires_at'] = $existingCredentials['token_expires_at'];
        }

        $payload['credentials'] = $credentials;

        if (trim((string) ($payload['base_url'] ?? '')) === '' && filled($existing?->base_url)) {
            $payload['base_url'] = $existing->base_url;
        }

        return $payload;
    }
}
