<?php

namespace App\Console\Commands;

use App\Services\Finance\Ledger\LedgerReconciliationService;
use Illuminate\Console\Command;

class LedgerBackfillCommand extends Command
{
    protected $signature = 'ledger:backfill
                            {--agency= : Limit backfill to one agency ID}
                            {--dry-run : Preview only (default when --force omitted)}
                            {--force : Write ledger transactions}';

    protected $description = 'Backfill posted ledger transactions from existing finance records';

    public function handle(LedgerReconciliationService $service): int
    {
        $dryRun = ! (bool) $this->option('force');
        $agencyId = $this->option('agency') !== null ? (int) $this->option('agency') : null;

        if ($dryRun) {
            $this->info('Dry run — no ledger transactions will be written. Pass --force to backfill.');
        } else {
            $this->warn('Writing ledger transactions from existing records.');
        }

        $result = $service->backfillExistingEvents($agencyId, $dryRun);

        if ($dryRun && isset($result['projections'])) {
            $this->info('Projections: '.count($result['projections']));
        } else {
            $this->table(
                ['Metric', 'Value'],
                [
                    ['Posted', (string) ($result['posted'] ?? 0)],
                    ['Skipped (already posted)', (string) ($result['skipped'] ?? 0)],
                    ['Errors', (string) count($result['errors'] ?? [])],
                ],
            );
        }

        foreach ($result['errors'] ?? [] as $error) {
            $this->error($error);
        }

        return ($result['errors'] ?? []) === [] ? self::SUCCESS : self::FAILURE;
    }
}
