<?php

namespace App\Services\Integrations;

use App\Enums\SupplierProvider;
use App\Models\SupplierConnection;
use App\Support\Suppliers\SmtpSupplierConnectionNormalizer;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

/**
 * DB-first SMTP mailer resolution with ENV fallback. Disabling DB never deletes ENV.
 * Active but incomplete/invalid DB rows also fall back safely to ENV.
 */
final class SmtpMailConfigResolver
{
    public function applyRuntimeConfig(): string
    {
        $connection = $this->activeSmtpConnection();
        if ($connection === null) {
            return 'env_fallback';
        }

        if (! $this->isUsable($connection)) {
            Log::warning('smtp.db_connection_invalid_fallback', [
                'connection_id' => $connection->id,
                'reason' => $this->usabilityFailureReason($connection),
            ]);

            return 'env_fallback';
        }

        $summary = SmtpSupplierConnectionNormalizer::safeSummary($connection);
        $credentials = is_array($connection->credentials) ? $connection->credentials : [];
        $host = trim((string) ($summary['host'] ?? ''));

        Config::set('mail.default', 'smtp');
        Config::set('mail.mailers.smtp.transport', 'smtp');
        Config::set('mail.mailers.smtp.host', $host);
        Config::set('mail.mailers.smtp.port', (int) ($credentials['port'] ?? 587));
        Config::set('mail.mailers.smtp.username', $credentials['username'] ?? null);
        Config::set('mail.mailers.smtp.password', $credentials['password'] ?? null);

        $encryption = strtolower(trim((string) ($credentials['encryption'] ?? 'tls')));
        if (in_array($encryption, ['tls', 'ssl'], true)) {
            Config::set('mail.mailers.smtp.scheme', $encryption);
            Config::set('mail.mailers.smtp.encryption', $encryption);
        } elseif ($encryption === 'none' || $encryption === '') {
            Config::set('mail.mailers.smtp.scheme', null);
            Config::set('mail.mailers.smtp.encryption', null);
        }

        $fromAddress = trim((string) ($credentials['from_address'] ?? ''));
        if ($fromAddress !== '') {
            Config::set('mail.from.address', $fromAddress);
        }
        $fromName = trim((string) ($credentials['from_name'] ?? ''));
        if ($fromName !== '') {
            Config::set('mail.from.name', $fromName);
        }

        $timeout = trim((string) ($credentials['timeout'] ?? ''));
        if ($timeout !== '' && is_numeric($timeout)) {
            Config::set('mail.mailers.smtp.timeout', (int) $timeout);
        }

        return 'db_managed';
    }

    public function currentSource(): string
    {
        $connection = $this->activeSmtpConnection();
        if ($connection === null) {
            $any = SupplierConnection::query()
                ->where('provider', SupplierProvider::Smtp->value)
                ->orderByDesc('id')
                ->first();

            if ($any !== null && ! $any->is_active) {
                return 'env_fallback';
            }

            return 'env_fallback';
        }

        return $this->isUsable($connection) ? 'db_managed' : 'env_fallback';
    }

    /**
     * Explicit runtime usability validator — host alone is not enough.
     */
    public function isUsable(SupplierConnection $connection): bool
    {
        return $this->usabilityFailureReason($connection) === null;
    }

    public function usabilityFailureReason(SupplierConnection $connection): ?string
    {
        $credentials = is_array($connection->credentials) ? $connection->credentials : [];
        $host = trim((string) ($credentials['host'] ?? ''));
        if ($host === '' || ! $this->looksLikeHostname($host)) {
            return 'invalid_host';
        }

        $port = (int) ($credentials['port'] ?? 0);
        if ($port < 1 || $port > 65535) {
            return 'invalid_port';
        }

        $encryption = strtolower(trim((string) ($credentials['encryption'] ?? 'tls')));
        if (! in_array($encryption, ['tls', 'ssl', 'none', ''], true)) {
            return 'invalid_encryption';
        }

        $username = trim((string) ($credentials['username'] ?? ''));
        $password = trim((string) ($credentials['password'] ?? ''));
        // Authenticated SMTP (default) requires both; allow blank pair only for explicit none+no-auth local relays.
        if ($username !== '' xor $password !== '') {
            return 'incomplete_credentials';
        }

        $fromAddress = trim((string) ($credentials['from_address'] ?? ''));
        if ($fromAddress === '' || ! filter_var($fromAddress, FILTER_VALIDATE_EMAIL)) {
            return 'invalid_from_address';
        }

        return null;
    }

    public function activeSmtpConnection(): ?SupplierConnection
    {
        try {
            return SupplierConnection::query()
                ->where('provider', SupplierProvider::Smtp->value)
                ->where('is_active', true)
                ->orderByDesc('id')
                ->first();
        } catch (\Throwable $e) {
            Log::warning('smtp.mail_resolver_unavailable', ['message' => $e->getMessage()]);

            return null;
        }
    }

    private function looksLikeHostname(string $host): bool
    {
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return true;
        }

        return (bool) preg_match('/^[a-z0-9]([a-z0-9.-]*[a-z0-9])?$/i', $host);
    }
}
