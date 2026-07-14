<?php

namespace App\Console\Commands;

use App\Services\Agencies\AgencyReconciliationService;
use Illuminate\Console\Command;

class OtaDiagnoseAgencyLinksCommand extends Command
{
    protected $signature = 'ota:diagnose-agency-links';

    protected $description = 'Read-only report of approved applications and agency owners with broken linkage';

    public function handle(AgencyReconciliationService $service): int
    {
        $issues = $service->diagnose();

        if ($issues === []) {
            $this->info('No agency linkage issues detected.');

            return self::SUCCESS;
        }

        $this->warn(count($issues).' issue(s) found:');
        foreach ($issues as $row) {
            $this->line(sprintf(
                '- [%s] %s (%s) issues: %s',
                (string) ($row['type'] ?? 'record'),
                (string) ($row['email'] ?? '—'),
                (string) ($row['company_name'] ?? $row['agency_name'] ?? '—'),
                implode(', ', (array) ($row['issues'] ?? []))
            ));
        }

        $this->comment('Run `php artisan ota:reconcile-agencies --dry-run` then `--force` to repair safely.');

        return self::SUCCESS;
    }
}
