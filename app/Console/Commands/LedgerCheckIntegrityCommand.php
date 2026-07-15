<?php

namespace App\Console\Commands;

use App\Services\Finance\Ledger\LedgerIntegrityService;
use Illuminate\Console\Command;

class LedgerCheckIntegrityCommand extends Command
{
    protected $signature = 'ledger:check-integrity';

    protected $description = 'Detect unbalanced transactions, missing entries, duplicate source postings, and invalid posted states';

    public function handle(LedgerIntegrityService $service): int
    {
        $result = $service->checkIntegrity();

        if ($result['passed']) {
            $this->info('Ledger integrity check passed.');

            return self::SUCCESS;
        }

        $this->error('Ledger integrity issues found: '.count($result['issues']));
        foreach ($result['issues'] as $issue) {
            $this->line(json_encode($issue));
        }

        return self::FAILURE;
    }
}
