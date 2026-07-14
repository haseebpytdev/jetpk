<?php

namespace App\Console\Commands;

use App\Services\Finance\Ledger\LedgerAccountService;
use Illuminate\Console\Command;

class LedgerSeedAccountsCommand extends Command
{
    protected $signature = 'ledger:seed-accounts
                            {--dry-run : Preview accounts without writing (default when --force omitted)}
                            {--force : Write accounts to the database}';

    protected $description = 'Seed system chart-of-accounts for the double-entry ledger';

    public function handle(LedgerAccountService $service): int
    {
        $dryRun = ! (bool) $this->option('force');

        if ($dryRun) {
            $this->info('Dry run — no accounts will be written. Pass --force to seed.');
        }

        $result = $service->seedSystemAccounts($dryRun);

        $this->table(
            ['Metric', 'Value'],
            [
                ['Would create / created', (string) $result['created']],
                ['Already exist (skipped)', (string) $result['skipped']],
                ['Account codes', implode(', ', $result['accounts'])],
            ],
        );

        return self::SUCCESS;
    }
}
