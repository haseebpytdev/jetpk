<?php

namespace Tests\Feature\Finance;

use App\Enums\AccountType;
use App\Enums\AgentWalletStatus;
use App\Enums\LedgerTransactionType;
use App\Models\Agency;
use App\Models\Agent;
use App\Models\AgentWallet;
use App\Models\User;
use App\Services\Finance\Ledger\LedgerBalanceService;
use App\Services\Finance\Ledger\LedgerPostingService;
use App\Services\Finance\Ledger\LedgerReconciliationDashboardService;
use App\Services\Finance\Ledger\LedgerTransactionFactory;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\Feature\Finance\Concerns\BuildsOtaFinanceScenario;
use Tests\TestCase;

/**
 * Finance-Reports-6A: agency wallet balance reconciliation must sum all agent_wallets per agency.
 */
class LedgerMultiWalletReconciliationTest extends TestCase
{
    use BuildsOtaFinanceScenario;
    use RefreshDatabase;

    private const LIABILITY_AMOUNT = 100.0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
        $this->seedLedgerInfrastructure();
    }

    public function test_verify_balances_passes_when_multi_wallet_sum_matches_ledger_liability(): void
    {
        $agencyId = $this->seedMultiWalletAgencyWithLedgerLiability();

        $compare = app(LedgerBalanceService::class)->compareWalletToLedger($agencyId);
        $this->assertTrue($compare['matches']);
        $this->assertSame(self::LIABILITY_AMOUNT, $compare['wallet_balance']);
        $this->assertSame(self::LIABILITY_AMOUNT, $compare['ledger_balance']);
        $this->assertSame(0.0, $compare['difference']);

        $exit = Artisan::call('ledger:verify-balances', ['--agency' => $agencyId]);
        $this->assertSame(0, $exit);

        $exit = Artisan::call('ledger:reconcile', ['--agency' => $agencyId]);
        $this->assertSame(0, $exit);
    }

    public function test_reconciliation_dashboard_shows_matched_for_multi_wallet_agency(): void
    {
        $agencyId = $this->seedMultiWalletAgencyWithLedgerLiability();

        $dashboard = app(LedgerReconciliationDashboardService::class)->buildPlatformDashboard();
        $row = collect($dashboard['agency_breakdown'])->firstWhere('agency_id', $agencyId);

        $this->assertNotNull($row);
        $this->assertSame('matched', $row['status']);
        $this->assertSame(self::LIABILITY_AMOUNT, $row['wallet_balance']);
        $this->assertSame(self::LIABILITY_AMOUNT, $row['ledger_liability']);
        $this->assertSame(0.0, $row['difference']);
    }

    public function test_verify_balances_detects_mismatch_when_multi_wallet_sum_differs_from_ledger(): void
    {
        $agencyId = $this->seedMultiWalletAgencyWithLedgerLiability();

        AgentWallet::query()
            ->where('agency_id', $agencyId)
            ->orderBy('id')
            ->skip(2)
            ->first()
            ->update(['balance' => 150]);

        $compare = app(LedgerBalanceService::class)->compareWalletToLedger($agencyId);
        $this->assertFalse($compare['matches']);
        $this->assertSame(150.0, $compare['wallet_balance']);
        $this->assertSame(self::LIABILITY_AMOUNT, $compare['ledger_balance']);

        $exit = Artisan::call('ledger:verify-balances', ['--agency' => $agencyId]);
        $this->assertSame(1, $exit);

        $exit = Artisan::call('ledger:reconcile', ['--agency' => $agencyId]);
        $this->assertSame(1, $exit);
    }

    public function test_single_wallet_agency_reconciliation_unchanged(): void
    {
        $scenario = $this->buildOtaFinanceScenario();
        Artisan::call('ledger:backfill', ['--force' => true]);

        $agencyId = $scenario['agencies']['et']['agency']->id;
        $compare = app(LedgerBalanceService::class)->compareWalletToLedger($agencyId);

        $this->assertTrue($compare['matches']);
        $this->assertSame(0, Artisan::call('ledger:verify-balances', ['--agency' => $agencyId]));
    }

    protected function seedMultiWalletAgencyWithLedgerLiability(): int
    {
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $primaryAgent = Agent::query()->where('agency_id', $agency->id)->firstOrFail();

        $walletBalances = [0.0, 0.0, self::LIABILITY_AMOUNT];
        $agents = [$primaryAgent];

        for ($i = 1; $i < count($walletBalances); $i++) {
            $user = User::factory()->create([
                'current_agency_id' => $agency->id,
                'account_type' => AccountType::Agent,
            ]);
            $agents[] = Agent::query()->create([
                'agency_id' => $agency->id,
                'user_id' => $user->id,
                'status' => 'active',
            ]);
        }

        foreach ($walletBalances as $index => $balance) {
            AgentWallet::query()->updateOrCreate(
                ['agent_id' => $agents[$index]->id],
                [
                    'agency_id' => $agency->id,
                    'user_id' => $agents[$index]->user_id,
                    'balance' => $balance,
                    'currency' => 'PKR',
                    'status' => AgentWalletStatus::Active,
                ],
            );
        }

        $factory = app(LedgerTransactionFactory::class);
        $posting = app(LedgerPostingService::class);

        $transaction = $factory->createDraftTransaction([
            'transaction_type' => LedgerTransactionType::AgencyDepositApproved,
            'agency_id' => $agency->id,
            'amount_total' => self::LIABILITY_AMOUNT,
            'occurred_at' => now(),
        ]);

        $posting->begin($transaction)
            ->addDebit('PLATFORM_CASH', self::LIABILITY_AMOUNT, agencyId: $agency->id)
            ->addCredit('AGENCY_WALLET_LIABILITY', self::LIABILITY_AMOUNT, agencyId: $agency->id)
            ->post();

        return (int) $agency->id;
    }
}
