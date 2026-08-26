<?php

namespace App\Services\Integrations;

use App\Enums\SupplierConnectionStatus;
use App\Enums\SupplierEnvironment;
use App\Enums\SupplierProvider;
use App\Models\Agency;
use App\Models\AuditLog;
use App\Models\SupplierConnection;
use App\Support\Suppliers\SmtpSupplierConnectionNormalizer;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Idempotent ENV → DB SMTP connection bootstrap. Never overwrites a managed DB row.
 */
final class SmtpEnvironmentImportService
{
    public function importIfNeeded(?Agency $agency = null): ?SupplierConnection
    {
        if (! Schema::hasTable('supplier_connections')) {
            return null;
        }

        if (! $this->envSmtpConfigured()) {
            return null;
        }

        $agency ??= Agency::query()->orderBy('id')->first();
        if ($agency === null) {
            return null;
        }

        $existing = SupplierConnection::query()
            ->where('provider', SupplierProvider::Smtp->value)
            ->where('agency_id', $agency->id)
            ->orderBy('id')
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $credentials = [
            'host' => (string) config('mail.mailers.smtp.host'),
            'port' => (string) config('mail.mailers.smtp.port'),
            'encryption' => (string) (config('mail.mailers.smtp.scheme') ?: config('mail.mailers.smtp.encryption') ?: 'tls'),
            'username' => (string) config('mail.mailers.smtp.username'),
            'password' => (string) config('mail.mailers.smtp.password'),
            'from_address' => (string) config('mail.from.address'),
            'from_name' => (string) config('mail.from.name'),
            'timeout' => (string) (config('mail.mailers.smtp.timeout') ?? ''),
        ];

        $connection = SupplierConnection::query()->create([
            'agency_id' => $agency->id,
            'provider' => SupplierProvider::Smtp,
            'name' => SmtpSupplierConnectionNormalizer::DEFAULT_NAME,
            'display_name' => SmtpSupplierConnectionNormalizer::DEFAULT_NAME,
            'environment' => SupplierEnvironment::Live,
            'status' => SupplierConnectionStatus::Active,
            'is_active' => true,
            'base_url' => null,
            'credentials' => $credentials,
            'settings' => [
                'module' => 'messaging',
                'mail_source' => 'env_import',
                'imported_from_environment' => true,
            ],
        ]);

        if (Schema::hasTable('audit_logs')) {
            AuditLog::query()->create([
                'agency_id' => $agency->id,
                'user_id' => null,
                'action' => 'integration.smtp_imported_from_environment',
                'auditable_type' => SupplierConnection::class,
                'auditable_id' => $connection->id,
                'properties' => [
                    'host' => $credentials['host'],
                    'port' => $credentials['port'],
                    'encryption' => $credentials['encryption'],
                    'from_address' => $credentials['from_address'],
                    'from_name' => $credentials['from_name'],
                    'username_present' => $credentials['username'] !== '',
                    'password_present' => $credentials['password'] !== '',
                ],
            ]);
        }

        Log::info('integration.smtp_imported_from_environment', [
            'connection_id' => $connection->id,
            'host' => $credentials['host'],
            'port' => $credentials['port'],
            'username_present' => $credentials['username'] !== '',
            'password_present' => $credentials['password'] !== '',
        ]);

        return $connection;
    }

    public function envSmtpConfigured(): bool
    {
        $mailer = strtolower((string) config('mail.default'));
        if (! in_array($mailer, ['smtp', 'failover'], true)) {
            // Still allow import when SMTP host is set even if default mailer is log in local.
            $host = trim((string) config('mail.mailers.smtp.host'));

            return $host !== '' && $host !== '127.0.0.1' && $host !== 'localhost';
        }

        $host = trim((string) config('mail.mailers.smtp.host'));

        return $host !== '';
    }
}
