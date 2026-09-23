<?php

namespace App\Support\Suppliers;

use App\Enums\SupplierProvider;
use App\Models\SupplierConnection;

/**
 * Normalizes Ameer-e-Millat API connection credentials for manual, managed, and auto-token modes.
 */
final class AmeerEMillatSupplierConnectionNormalizer
{
    public const AUTH_MODE_MANUAL = 'manual_token';

    public const AUTH_MODE_MANAGED = 'managed_token';

    public const AUTH_MODE_AUTO = 'credentials_auto_token';

    /**
     * @return list<string>
     */
    public static function supportedAuthModes(): array
    {
        return [self::AUTH_MODE_MANUAL, self::AUTH_MODE_MANAGED, self::AUTH_MODE_AUTO];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function normalizePayload(array $payload, ?SupplierConnection $existing = null): array
    {
        if (($payload['provider'] ?? '') !== SupplierProvider::AmeerEMillat->value) {
            return $payload;
        }

        $credentials = is_array($payload['credentials'] ?? null) ? $payload['credentials'] : [];
        $existingCredentials = ($existing !== null && is_array($existing->credentials)) ? $existing->credentials : [];

        $authMode = strtolower(trim((string) ($credentials['auth_mode'] ?? $existingCredentials['auth_mode'] ?? self::AUTH_MODE_MANUAL)));
        if (! in_array($authMode, self::supportedAuthModes(), true)) {
            $authMode = self::AUTH_MODE_MANUAL;
        }
        $credentials['auth_mode'] = $authMode;

        $clearToken = trim((string) ($credentials['clear_existing_token'] ?? '0')) === '1';
        unset($credentials['clear_existing_token']);

        $credentials = self::normalizeLoginIdentity($credentials, $existingCredentials);

        if ($authMode === self::AUTH_MODE_MANUAL) {
            $incomingToken = trim((string) ($credentials['existing_token'] ?? ''));
            if ($clearToken) {
                unset($credentials['existing_token']);
            } elseif ($incomingToken === '' && isset($existingCredentials['existing_token'])) {
                $credentials['existing_token'] = $existingCredentials['existing_token'];
            }

            foreach (['username', 'email', 'password', 'auto_renew'] as $key) {
                unset($credentials[$key]);
            }
        } elseif ($authMode === self::AUTH_MODE_MANAGED) {
            $incomingToken = trim((string) ($credentials['existing_token'] ?? ''));
            if ($clearToken) {
                unset($credentials['existing_token']);
            } elseif ($incomingToken === '' && isset($existingCredentials['existing_token'])) {
                $credentials['existing_token'] = $existingCredentials['existing_token'];
            }

            foreach (['username', 'email', 'password'] as $key) {
                $incoming = trim((string) ($credentials[$key] ?? ''));
                if ($incoming === '' && isset($existingCredentials[$key])) {
                    $credentials[$key] = $existingCredentials[$key];
                }
            }

            if (! array_key_exists('auto_renew', $credentials) && array_key_exists('auto_renew', $existingCredentials)) {
                $credentials['auto_renew'] = $existingCredentials['auto_renew'];
            } elseif (! array_key_exists('auto_renew', $credentials)) {
                $credentials['auto_renew'] = '0';
            }
        } else {
            foreach (['username', 'email', 'password'] as $key) {
                $incoming = trim((string) ($credentials[$key] ?? ''));
                if ($incoming === '' && isset($existingCredentials[$key])) {
                    $credentials[$key] = $existingCredentials[$key];
                }
            }
            unset($credentials['existing_token'], $credentials['token_expires_at'], $credentials['auto_renew']);
        }

        $credentials = self::normalizeLoginIdentity($credentials, $existingCredentials);

        $expires = trim((string) ($credentials['token_expires_at'] ?? ''));
        if (in_array($authMode, [self::AUTH_MODE_MANUAL, self::AUTH_MODE_MANAGED], true) && $expires === '' && isset($existingCredentials['token_expires_at'])) {
            $credentials['token_expires_at'] = $existingCredentials['token_expires_at'];
        }

        if (
            in_array($authMode, [self::AUTH_MODE_MANUAL, self::AUTH_MODE_MANAGED], true)
            && trim((string) ($credentials['token_expires_at'] ?? '')) === ''
            && trim((string) ($credentials['existing_token'] ?? '')) !== ''
        ) {
            $derived = self::deriveJwtExpiryIso((string) $credentials['existing_token']);
            if ($derived !== null) {
                $credentials['token_expires_at'] = $derived;
            }
        }

        $payload['credentials'] = $credentials;

        $incomingBase = trim((string) ($payload['base_url'] ?? ''));
        $override = (bool) ($payload['advanced_base_url_override'] ?? false);
        $defaultBase = rtrim((string) config('suppliers.ameer_e_millat.default_base_url', 'https://fsdameeremillattourism.com'), '/');

        if ($override && $incomingBase !== '') {
            $payload['base_url'] = $incomingBase;
            $settings = is_array($payload['settings'] ?? null) ? $payload['settings'] : [];
            $settings['base_url_mode'] = 'explicit_override';
            $payload['settings'] = $settings;
        } elseif ($incomingBase !== '') {
            $payload['base_url'] = $incomingBase;
        } elseif (filled($existing?->base_url)) {
            $payload['base_url'] = $existing->base_url;
        } else {
            $payload['base_url'] = $defaultBase;
            $settings = is_array($payload['settings'] ?? null) ? $payload['settings'] : [];
            $settings['base_url_mode'] = 'provider_default';
            $payload['settings'] = $settings;
        }

        if ($existing !== null) {
            $settings = is_array($payload['settings'] ?? null) ? $payload['settings'] : [];
            $existingSettings = is_array($existing->settings) ? $existing->settings : [];
            if (! isset($settings['token_issuance']) && isset($existingSettings['token_issuance'])) {
                $settings['token_issuance'] = $existingSettings['token_issuance'];
                $payload['settings'] = $settings;
            }
        }

        return $payload;
    }

    /**
     * Prefer username, fall back to email for login identity.
     *
     * @param  array<string, mixed>  $credentials
     * @param  array<string, mixed>  $existingCredentials
     * @return array<string, mixed>
     */
    private static function normalizeLoginIdentity(array $credentials, array $existingCredentials): array
    {
        $username = trim((string) ($credentials['username'] ?? ''));
        $email = trim((string) ($credentials['email'] ?? ''));
        $existingUsername = trim((string) ($existingCredentials['username'] ?? ''));
        $existingEmail = trim((string) ($existingCredentials['email'] ?? ''));

        if ($username === '' && $email !== '') {
            $credentials['username'] = $email;
        } elseif ($username === '' && $existingUsername !== '') {
            $credentials['username'] = $existingUsername;
        } elseif ($username === '' && $existingEmail !== '') {
            $credentials['username'] = $existingEmail;
        }

        if ($email === '' && $username !== '') {
            $credentials['email'] = $username;
        } elseif ($email === '' && $existingEmail !== '') {
            $credentials['email'] = $existingEmail;
        } elseif ($email === '' && $existingUsername !== '') {
            $credentials['email'] = $existingUsername;
        }

        return $credentials;
    }

    public static function deriveJwtExpiryIso(string $token): ?string
    {
        $parts = explode('.', trim($token));
        if (count($parts) < 2) {
            return null;
        }

        $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')) ?: '', true);
        if (! is_array($payload)) {
            return null;
        }

        $exp = (int) ($payload['exp'] ?? 0);
        if ($exp <= 0) {
            return null;
        }

        return gmdate('c', $exp);
    }
}
