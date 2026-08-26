<?php

namespace App\Support\Suppliers;

use App\Enums\SupplierProvider;
use App\Models\SupplierConnection;

/**
 * Maps SMTP connection credentials to Laravel mailer fields. Secrets stay encrypted.
 */
final class SmtpSupplierConnectionNormalizer
{
    public const DEFAULT_NAME = 'SMTP JetPakistan LIVE';

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function normalizePayload(array $payload, ?SupplierConnection $existing = null): array
    {
        if (($payload['provider'] ?? '') !== SupplierProvider::Smtp->value) {
            return $payload;
        }

        $credentials = is_array($payload['credentials'] ?? null) ? $payload['credentials'] : [];
        $existingCredentials = ($existing !== null && is_array($existing->credentials)) ? $existing->credentials : [];

        foreach (['host', 'port', 'encryption', 'username', 'password', 'from_address', 'from_name', 'timeout'] as $key) {
            $incoming = trim((string) ($credentials[$key] ?? ''));
            if ($incoming === '' && isset($existingCredentials[$key])) {
                $credentials[$key] = $existingCredentials[$key];
            } elseif ($incoming !== '') {
                $credentials[$key] = $incoming;
            }
        }

        if (trim((string) ($credentials['port'] ?? '')) === '') {
            $credentials['port'] = (string) ($existingCredentials['port'] ?? '587');
        }

        if (trim((string) ($credentials['encryption'] ?? '')) === '') {
            $credentials['encryption'] = (string) ($existingCredentials['encryption'] ?? 'tls');
        }

        $payload['credentials'] = $credentials;
        $payload['base_url'] = null;

        $settings = is_array($payload['settings'] ?? null) ? $payload['settings'] : [];
        $settings['module'] = 'messaging';
        $settings['mail_source'] = $settings['mail_source'] ?? 'db';
        $payload['settings'] = $settings;

        if (trim((string) ($payload['environment'] ?? '')) === '') {
            $payload['environment'] = 'live';
        }

        return $payload;
    }

    /**
     * Safe metadata for UI/audit — never includes password.
     *
     * @return array<string, mixed>
     */
    public static function safeSummary(SupplierConnection $connection): array
    {
        $credentials = is_array($connection->credentials) ? $connection->credentials : [];

        return [
            'host' => (string) ($credentials['host'] ?? ''),
            'port' => (string) ($credentials['port'] ?? ''),
            'encryption' => (string) ($credentials['encryption'] ?? ''),
            'from_address' => (string) ($credentials['from_address'] ?? ''),
            'from_name' => (string) ($credentials['from_name'] ?? ''),
            'username_present' => trim((string) ($credentials['username'] ?? '')) !== '',
            'password_present' => trim((string) ($credentials['password'] ?? '')) !== '',
            'mail_source' => is_array($connection->settings)
                ? (string) ($connection->settings['mail_source'] ?? 'db')
                : 'db',
        ];
    }
}
