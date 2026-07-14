<?php

namespace App\Console\Commands;

use App\Services\Finance\Ledger\LedgerReconciliationService;
use Illuminate\Console\Command;

class LedgerProjectExistingCommand extends Command
{
    protected $signature = 'ledger:project-existing
                            {--agency= : Limit projection to one agency ID}
                            {--dry-run : Preview only (default)}';

    protected $description = 'Project proposed journal lines from existing deposits, payments, refunds, and commissions';

    public function handle(LedgerReconciliationService $service): int
    {
        $agencyId = $this->option('agency') !== null ? (int) $this->option('agency') : null;

        $this->info('Projecting ledger entries from existing finance records (dry run).');

        $result = $service->projectExistingEvents($agencyId);

        if ($result['projections'] === []) {
            $this->warn('No projectable events found.');

            return self::SUCCESS;
        }

        foreach ($result['projections'] as $projection) {
            $this->newLine();
            $this->line(sprintf(
                '[%s] %s — %.2f PKR',
                $projection['event_type'],
                $projection['label'],
                $projection['amount'],
            ));
            $this->line(sprintf(
                '  Dr %s / Cr %s (agency=%s)',
                $projection['debit_account'],
                $projection['credit_account'],
                $projection['context']['agency_id'] ?? '—',
            ));
            if (! empty($projection['context']['actor_identifier'])) {
                $this->line('  Actor: '.$projection['context']['actor_identifier']);
            }
        }

        if ($result['skipped'] !== []) {
            $this->newLine();
            $this->comment('Skipped: '.count($result['skipped']).' item(s) (duplicates or covered by deposit).');
        }

        $this->newLine();
        $this->info('Total projections: '.count($result['projections']));

        return self::SUCCESS;
    }
}
