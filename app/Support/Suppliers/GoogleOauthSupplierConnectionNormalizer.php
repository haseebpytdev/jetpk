<?php

namespace App\Support\Suppliers;

use App\Enums\SupplierProvider;
use App\Models\SupplierConnection;

/**
 * Maps Google OAuth connection credentials. Secrets stay encrypted; blank secret preserves stored.
 */
final class GoogleOauthSupplierConnectionNormalizer
{
    public const DEFAULT_NAME = 'Google Sign-In / Google OAuth';

    public const DEFAULT_REDIRECT_PATH = '/auth/google/callback';

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function normalizePayload(array $payload, ?SupplierConnection $existing = null): array
    {
        if (($payload['provider'] ?? '') !== SupplierProvider::GoogleOauth->value) {
            return $payload;
        }

        $credentials = is_array($payload['credentials'] ?? null) ? $payload['credentials'] : [];
        $existingCredentials = ($existing !== null && is_array($existing->credentials)) ? $existing->credentials : [];

        foreach (['client_id', 'client_secret', 'redirect_uri'] as $key) {
            $incoming = trim((string) ($credentials[$key] ?? ''));
            if ($incoming === '' && isset($existingCredentials[$key])) {
                $credentials[$key] = $existingCredentials[$key];
            } elseif ($incoming !== '') {
                $credentials[$key] = $incoming;
            } else {
                unset($credentials[$key]);
            }
        }

        $payload['credentials'] = $credentials;
        $payload['base_url'] = null;

        $settings = is_array($payload['settings'] ?? null) ? $payload['settings'] : [];
        $settings['module'] = 'auth';
        $settings['oauth_source'] = $settings['oauth_source'] ?? 'db';
        $payload['settings'] = $settings;

        if (trim((string) ($payload['environment'] ?? '')) === '') {
            $payload['environment'] = 'live';
        }

        return $payload;
    }

    /**
     * Canonical callback used when redirect_uri override is blank.
     */
    public static function defaultRedirectUri(): string
    {
        $appUrl = rtrim((string) config('app.url', ''), '/');

        return $appUrl !== ''
            ? $appUrl.self::DEFAULT_REDIRECT_PATH
            : self::DEFAULT_REDIRECT_PATH;
    }

    /**
     * Resolved redirect for a connection (override or canonical default).
     */
    public static function resolvedRedirectUri(SupplierConnection $connection): string
    {
        $credentials = is_array($connection->credentials) ? $connection->credentials : [];
        $override = trim((string) ($credentials['redirect_uri'] ?? ''));

        return $override !== '' ? $override : self::defaultRedirectUri();
    }

    /**
     * Safe metadata for UI/audit — never includes secret plaintext.
     *
     * @return array<string, mixed>
     */
    public static function safeSummary(SupplierConnection $connection): array
    {
        $credentials = is_array($connection->credentials) ? $connection->credentials : [];
        $clientId = trim((string) ($credentials['client_id'] ?? ''));
        $clientSecret = trim((string) ($credentials['client_secret'] ?? ''));
        $redirectOverride = trim((string) ($credentials['redirect_uri'] ?? ''));

        return [
            'client_id_present' => $clientId !== '',
            'client_id_hint' => $clientId !== ''
                ? (strlen($clientId) > 8 ? substr($clientId, 0, 4).'…'.substr($clientId, -4) : '••••')
                : '',
            'client_secret_present' => $clientSecret !== '',
            'redirect_uri' => self::resolvedRedirectUri($connection),
            'redirect_uri_override' => $redirectOverride,
            'default_redirect_uri' => self::defaultRedirectUri(),
            'oauth_source' => is_array($connection->settings)
                ? (string) ($connection->settings['oauth_source'] ?? 'db')
                : 'db',
        ];
    }
}
