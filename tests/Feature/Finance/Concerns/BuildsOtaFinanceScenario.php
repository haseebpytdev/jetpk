<?php

namespace Tests\Feature\Finance\Concerns;

use App\Models\Agency;
use App\Models\AgentWallet;
use App\Services\Agents\AgentWalletService;
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

    /**
     * Mark the legacy-heuristic operational wallet as the explicit canonical row.
     * Historical siblings remain canonical_for_agency = NULL.
     */
    protected function markHeuristicWalletCanonical(Agency|int $agency): ?AgentWallet
    {
        $wallet = app(AgentWalletService::class)->canonicalWalletForAgency($agency);
        if ($wallet === null) {
            return null;
        }

        if (! $wallet->isCanonicalForAgency()) {
            $wallet->forceFill(['canonical_for_agency' => AgentWallet::CANONICAL_MARKER])->save();
        }

        return $wallet->fresh();
    }
}
