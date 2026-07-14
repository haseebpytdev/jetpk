<?php

namespace App\Console\Commands;

use App\Services\Finance\Ledger\LedgerReconciliationService;
use Illuminate\Console\Command;

class LedgerReconcileCommand extends Command
{
    protected $signature = 'ledger:reconcile
                            {--agency= : Compare wallet vs ledger for one agency}';

    protected $description = 'Compare existing wallet balances against ledger agency wallet liabilities';

    public function handle(LedgerReconciliationService $service): int
    {
        $agencyId = $this->option('agency') !== null ? (int) $this->option('agency') : null;
        $results = $service->reconcileWalletTransactions($agencyId);

        if ($results === []) {
            $this->warn('No agency wallets found.');

            return self::SUCCESS;
        }

        $rows = [];
        $hasMismatch = false;
        foreach ($results as $row) {
            $rows[] = [
                $row['agency_id'],
                number_format($row['wallet_balance'], 2),
                number_format($row['ledger_balance'], 2),
                number_format($row['difference'], 2),
                $row['matches'] ? 'OK' : 'MISMATCH',
            ];
            if (! $row['matches']) {
                $hasMismatch = true;
            }
        }

        $this->table(['Agency ID', 'Wallet', 'Ledger', 'Diff', 'Status'], $rows);

        return $hasMismatch ? self::FAILURE : self::SUCCESS;
    }
}
