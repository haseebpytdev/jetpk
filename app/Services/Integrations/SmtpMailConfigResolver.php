<?php

namespace App\Services\Integrations;

use App\Enums\SupplierProvider;
use App\Models\SupplierConnection;
use App\Support\Suppliers\SmtpSupplierConnectionNormalizer;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

/**
 * DB-first SMTP mailer resolution with ENV fallback. Disabling DB never deletes ENV.
 */
final class SmtpMailConfigResolver
{
    public function applyRuntimeConfig(): string
    {
        $connection = $this->activeSmtpConnection();
        if ($connection === null) {
            return 'env_fallback';
        }

        $summary = SmtpSupplierConnectionNormalizer::safeSummary($connection);
        $host = trim((string) ($summary['host'] ?? ''));
        if ($host === '') {
            return 'env_legacy_fallback';
        }

        $credentials = is_array($connection->credentials) ? $connection->credentials : [];

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

            return 'env_legacy_fallback';
        }

        $host = trim((string) (SmtpSupplierConnectionNormalizer::safeSummary($connection)['host'] ?? ''));

        return $host !== '' ? 'db_managed' : 'env_legacy_fallback';
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
}
