<?php

namespace Database\Seeders;

use App\Services\Finance\Ledger\LedgerAccountService;
use Illuminate\Database\Seeder;

class LedgerChartOfAccountsSeeder extends Seeder
{
    public function run(): void
    {
        app(LedgerAccountService::class)->seedSystemAccounts(dryRun: false);
    }
}
