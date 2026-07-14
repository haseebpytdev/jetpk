<?php

namespace App\Console\Commands;

use App\Services\Finance\Ledger\LedgerIntegrityService;
use Illuminate\Console\Command;

class LedgerVerifyBalancesCommand extends Command
{
    protected $signature = 'ledger:verify-balances
                            {--agency= : Verify one agency wallet vs ledger}';

    protected $description = 'Verify wallet balances match ledger and platform exposure equals agency liabilities';

    public function handle(LedgerIntegrityService $service): int
    {
        $agencyId = $this->option('agency') !== null ? (int) $this->option('agency') : null;
        $result = $service->verifyBalances($agencyId);

        if ($result['passed']) {
            $this->info('Balance verification passed.');

            return self::SUCCESS;
        }

        $this->error('Balance mismatches found: '.count($result['mismatches']));
        foreach ($result['mismatches'] as $mismatch) {
            $this->line(json_encode($mismatch));
        }

        return self::FAILURE;
    }
}
