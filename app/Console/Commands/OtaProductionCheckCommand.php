<?php

namespace App\Console\Commands;

use App\Enums\MarkupRuleType;
use App\Enums\SupplierProvider;
use App\Models\Agency;
use App\Models\Airline;
use App\Models\Airport;
use App\Models\MarkupRule;
use App\Models\SupplierConnection;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class OtaProductionCheckCommand extends Command
{
    protected $signature = 'ota:production-check';

    protected $description = 'Run production/staging readiness checks for OTA deployment';

    public function handle(): int
    {
        $criticalFailures = 0;

        $criticalFailures += $this->check((bool) config('app.debug') === false, 'APP_DEBUG must be false', true);
        $criticalFailures += $this->check(trim((string) config('app.key')) !== '', 'APP_KEY is configured', true);
        $criticalFailures += $this->check(trim((string) config('app.url')) !== '', 'APP_URL is configured', true);
        $criticalFailures += $this->check($this->databaseWorks(), 'Database connection is healthy', true);
        $criticalFailures += $this->check(is_link(public_path('storage')) || is_dir(public_path('storage')), 'Storage link exists', false);

        $defaultAgencySlug = (string) config('ota.default_agency_slug', 'asif-travels');
        $criticalFailures += $this->check(
            Agency::query()->where('slug', $defaultAgencySlug)->exists(),
            'Default agency exists ('.$defaultAgencySlug.')',
            true
        );
        $criticalFailures += $this->check(
            User::query()->where('username', 'admin')->orWhere('email', 'admin@ota.demo')->exists(),
            'Admin user exists',
            true
        );
        $criticalFailures += $this->check(Airport::query()->count() > 0, 'Airports table has records', true);
        $criticalFailures += $this->check(Airline::query()->count() > 0, 'Airlines table has records', true);

        $criticalFailures += $this->check($this->activeSuppliersHaveRequiredCredentials(), 'Active suppliers have required credentials', true);
        $criticalFailures += $this->check($this->activeDuffelHasAccessToken(), 'Active Duffel supplier has access_token', true);
        $criticalFailures += $this->check($this->hasNoInvalidMarkupRuleTypes(), 'No invalid markup rule enum values found', true);
        $criticalFailures += $this->check($this->errorViewsExist(), 'Public error pages are present', true);

        $this->check(trim((string) config('queue.default')) !== '', 'Queue configuration is set', false);
        $this->check(trim((string) config('mail.default')) !== '', 'Mail configuration is set', false);

        return $criticalFailures === 0 ? self::SUCCESS : self::FAILURE;
    }

    protected function check(bool $ok, string $label, bool $critical): int
    {
        if ($ok) {
            $this->line('[OK] '.$label);

            return 0;
        }

        $prefix = $critical ? '[FAIL]' : '[WARN]';
        $this->line($prefix.' '.$label);

        return $critical ? 1 : 0;
    }

    protected function databaseWorks(): bool
    {
        try {
            DB::connection()->getPdo();

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    protected function activeSuppliersHaveRequiredCredentials(): bool
    {
        $active = SupplierConnection::query()
            ->where(function ($q): void {
                $q->where('is_active', true)->orWhere('status', 'active');
            })
            ->get();

        foreach ($active as $connection) {
            $provider = $connection->provider instanceof SupplierProvider
                ? $connection->provider->value
                : strtolower((string) $connection->provider);
            $fields = (array) config('supplier_credentials.providers.'.$provider.'.fields', []);
            $credentials = is_array($connection->credentials) ? $connection->credentials : [];

            foreach ($fields as $key => $meta) {
                if (! ((bool) ($meta['required'] ?? false))) {
                    continue;
                }
                $value = trim((string) ($credentials[$key] ?? ''));
                if ($value === '') {
                    return false;
                }
            }
        }

        return true;
    }

    protected function activeDuffelHasAccessToken(): bool
    {
        $activeDuffel = SupplierConnection::query()
            ->where('provider', SupplierProvider::Duffel->value)
            ->where(function ($q): void {
                $q->where('is_active', true)->orWhere('status', 'active');
            })
            ->get();

        foreach ($activeDuffel as $connection) {
            $credentials = is_array($connection->credentials) ? $connection->credentials : [];
            if (trim((string) ($credentials['access_token'] ?? '')) === '') {
                return false;
            }
        }

        return true;
    }

    protected function hasNoInvalidMarkupRuleTypes(): bool
    {
        $validTypes = array_map(static fn (MarkupRuleType $type): string => $type->value, MarkupRuleType::cases());

        return ! MarkupRule::query()
            ->whereNotIn('rule_type', $validTypes)
            ->exists();
    }

    protected function errorViewsExist(): bool
    {
        $required = ['401', '403', '404', '419', '429', '500', '503'];
        foreach ($required as $code) {
            if (! file_exists(resource_path('views/errors/'.$code.'.blade.php'))) {
                return false;
            }
        }

        return true;
    }
}
