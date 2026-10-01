<?php

namespace App\Support\Integrations;

/**
 * Env-backed platform integration status for API Connections (not SupplierConnection).
 * Secrets are never returned — only presence / safe metadata.
 */
final class PlatformIntegrationStatusPresenter
{
    /**
     * @return list<array<string, mixed>>
     */
    public static function present(): array
    {
        return [
            self::smtp(),
            self::googleOauth(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function smtp(): array
    {
        $activeMailer = trim((string) config('mail.default', ''));
        $mailers = (array) config('mail.mailers', []);
        $smtp = is_array($mailers['smtp'] ?? null) ? $mailers['smtp'] : [];
        $username = trim((string) ($smtp['username'] ?? ''));
        $password = trim((string) ($smtp['password'] ?? ''));
        $host = trim((string) ($smtp['host'] ?? ''));
        $scheme = trim((string) ($smtp['scheme'] ?? ''));
        $fromAddress = trim((string) config('mail.from.address', ''));
        $fromName = trim((string) config('mail.from.name', ''));

        $smtpConfigurationPresent = $host !== '' && ($username !== '' || $password !== '');

        $configured = $activeMailer === 'smtp' && $smtpConfigurationPresent;
        $active = $configured;

        $statusLabel = match (true) {
            $activeMailer === 'log', $activeMailer === 'array' => 'Log/array mailer active (SMTP not configured)',
            $activeMailer === 'smtp' && $configured => 'Configured via environment',
            $activeMailer === 'smtp' => 'SMTP mailer selected but settings incomplete',
            default => 'Not configured',
        };

        return [
            'key' => 'smtp',
            'label' => 'SMTP / Email',
            'kind' => 'platform_integration',
            'source' => 'environment',
            'readOnly' => true,
            'activeMailer' => $activeMailer !== '' ? $activeMailer : null,
            'smtpConfigurationPresent' => $smtpConfigurationPresent,
            'configured' => $configured,
            'active' => $active,
            'status' => $configured ? 'configured' : 'not_configured',
            'statusLabel' => $statusLabel,
            'host' => $host !== '' ? $host : null,
            'port' => isset($smtp['port']) ? (int) $smtp['port'] : null,
            'scheme' => $scheme !== '' ? $scheme : null,
            'fromAddress' => $fromAddress !== '' ? $fromAddress : null,
            'fromName' => $fromName !== '' ? $fromName : null,
            'usernamePresent' => $username !== '',
            'passwordPresent' => $password !== '',
            'supportsCreate' => false,
            'supportsUpdate' => false,
            'supportsDelete' => false,
            'supportsTest' => false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function googleOauth(): array
    {
        $google = (array) config('services.google', []);
        $clientId = trim((string) ($google['client_id'] ?? ''));
        $clientSecret = trim((string) ($google['client_secret'] ?? ''));
        $redirect = trim((string) ($google['redirect'] ?? ''));
        $configured = $clientId !== '' && $clientSecret !== '';

        return [
            'key' => 'google_oauth',
            'label' => 'Google OAuth',
            'kind' => 'platform_integration',
            'source' => 'environment',
            'provider' => 'Google',
            'readOnly' => true,
            'configured' => $configured,
            'status' => $configured ? 'configured' : 'not_configured',
            'statusLabel' => $configured ? 'Configured via environment' : 'Not configured',
            'clientIdPresent' => $clientId !== '',
            'clientSecretPresent' => $clientSecret !== '',
            'redirectUri' => $redirect !== '' ? $redirect : null,
            'supportsCreate' => false,
            'supportsUpdate' => false,
            'supportsDelete' => false,
            'supportsTest' => false,
        ];
    }
}
