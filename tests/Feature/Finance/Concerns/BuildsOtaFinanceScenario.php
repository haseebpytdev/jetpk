<?php

namespace Tests\Feature\Finance\Concerns;

use App\Support\Finance\OtaFinanceDemoScenario;
use Database\Seeders\LedgerChartOfAccountsSeeder;
use Database\Seeders\LedgerPostingRulesSeeder;

/**
 * @phpstan-import-type from OtaFinanceDemoScenario
 */
trait BuildsOtaFinanceScenario
{
    /**
     * @return array<string, mixed>
     */
    protected function buildOtaFinanceScenario(): array
    {
        return (new OtaFinanceDemoScenario)->build();
    }

    protected function seedLedgerInfrastructure(): void
    {
        (new LedgerChartOfAccountsSeeder)->run();
        (new LedgerPostingRulesSeeder)->run();
    }
}
