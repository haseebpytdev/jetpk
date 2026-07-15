<?php

namespace Tests\Feature\Finance;

use App\Enums\LedgerTransactionStatus;
use App\Enums\LedgerTransactionType;
use App\Models\AgentDepositRequest;
use App\Models\LedgerAccount;
use App\Models\LedgerTransaction;
use App\Services\Finance\Ledger\LedgerAccountService;
use App\Services\Finance\Ledger\LedgerBalanceService;
use App\Services\Finance\Ledger\LedgerIntegrityService;
use App\Services\Finance\Ledger\LedgerPostingService;
use App\Services\Finance\Ledger\LedgerReversalService;
use App\Services\Finance\Ledger\LedgerTransactionFactory;
use App\Support\Finance\OtaFinanceDemoScenario;
use App\Support\Identity\ActorIdentifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use RuntimeException;
use Tests\Feature\Finance\Concerns\BuildsOtaFinanceScenario;
use Tests\TestCase;

class LedgerDoubleEntryTest extends TestCase
{
    use BuildsOtaFinanceScenario;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedLedgerInfrastructure();
    }

    public function test_ledger_accounts_seed_correctly(): void
    {
        $this->assertSame(count(LedgerAccountService::SYSTEM_ACCOUNTS), LedgerAccount::query()->count());
        $this->assertNotNull(app(LedgerAccountService::class)->findByCode('AGENCY_WALLET_LIABILITY'));
    }

    public function test_transaction_ref_generation_is_unique(): void
    {
        $factory = app(LedgerTransactionFactory::class);
        $refs = collect(range(1, 5))->map(fn () => $factory->generateRef())->all();

        $this->assertCount(5, array_unique($refs));
        $this->assertMatchesRegularExpression('/^L[A-Z2-9]{9}$/', $refs[0]);
    }

    public function test_balanced_transaction_can_post(): void
    {
        $factory = app(LedgerTransactionFactory::class);
        $posting = app(LedgerPostingService::class);

        $transaction = $factory->createDraftTransaction([
            'transaction_type' => LedgerTransactionType::AgencyDepositApproved,
            'amount_total' => 1000,
            'occurred_at' => now(),
        ]);

        $posting->begin($transaction)
            ->addDebit('PLATFORM_CASH', 1000)
            ->addCredit('AGENCY_WALLET_LIABILITY', 1000)
            ->post();

        $transaction->refresh();
        $this->assertSame(LedgerTransactionStatus::Posted, $transaction->status);
        $this->assertCount(2, $transaction->entries);
    }

    public function test_unbalanced_transaction_cannot_post(): void
    {
        $factory = app(LedgerTransactionFactory::class);
        $posting = app(LedgerPostingService::class);

        $transaction = $factory->createDraftTransaction([
            'transaction_type' => LedgerTransactionType::AgencyDepositApproved,
            'amount_total' => 1000,
            'occurred_at' => now(),
        ]);

        $posting->begin($transaction)
            ->addDebit('PLATFORM_CASH', 1000)
            ->addCredit('AGENCY_WALLET_LIABILITY', 500);

        $this->expectException(RuntimeException::class);
        $posting->post();
    }

    public function test_posted_transaction_cannot_be_edited(): void
    {
        $factory = app(LedgerTransactionFactory::class);
        $posting = app(LedgerPostingService::class);

        $transaction = $factory->createDraftTransaction([
            'transaction_type' => LedgerTransactionType::WalletAdminCredit,
            'amount_total' => 500,
            'occurred_at' => now(),
        ]);

        $posting->begin($transaction)
            ->addDebit('PLATFORM_CASH', 500)
            ->addCredit('AGENCY_WALLET_LIABILITY', 500)
            ->post();

        $this->expectException(RuntimeException::class);
        $transaction->update(['description' => 'changed']);
    }

    public function test_reversal_creates_equal_opposite_entries(): void
    {
        $scenario = $this->buildOtaFinanceScenario();
        $admin = $scenario['platform']['admin'];
        $agencyId = $scenario['agencies']['et']['agency']->id;

        $factory = app(LedgerTransactionFactory::class);
        $posting = app(LedgerPostingService::class);
        $reversal = app(LedgerReversalService::class);

        $transaction = $factory->createDraftTransaction([
            'transaction_type' => LedgerTransactionType::AgencyDepositApproved,
            'agency_id' => $agencyId,
            'amount_total' => 2500,
            'occurred_at' => now(),
        ]);

        $posting->begin($transaction)
            ->addDebit('PLATFORM_CASH', 2500, agencyId: $agencyId)
            ->addCredit('AGENCY_WALLET_LIABILITY', 2500, agencyId: $agencyId)
            ->post();

        $reversed = $reversal->reverse($transaction->fresh('entries.account'), $admin, 'Test reversal');

        $originalDebit = (float) $transaction->entries->sum('debit');
        $reversalCredit = (float) $reversed->entries->sum('credit');
        $this->assertSame($originalDebit, $reversalCredit);
        $this->assertSame(LedgerTransactionStatus::Reversed, $transaction->fresh()->status);
    }

    public function test_duplicate_source_posting_is_blocked(): void
    {
        $posting = app(LedgerPostingService::class);

        $context = [
            'source_type' => 'App\\Models\\AgentDepositRequest',
            'source_id' => 99,
            'agency_id' => null,
            'transaction_type' => LedgerTransactionType::AgencyDepositApproved,
            'occurred_at' => now(),
        ];

        $posting->postFromRule('agency_deposit_approved', 1000, $context, persist: true);

        $this->expectException(RuntimeException::class);
        $posting->postFromRule('agency_deposit_approved', 1000, $context, persist: true);
    }

    public function test_dry_run_seed_command_writes_nothing(): void
    {
        LedgerAccount::query()->delete();

        $exit = Artisan::call('ledger:seed-accounts');
        $this->assertSame(0, $exit);
        $this->assertSame(0, LedgerAccount::query()->count());
    }

    public function test_project_existing_command_outputs_proposed_entries(): void
    {
        $this->buildOtaFinanceScenario();

        $exit = Artisan::call('ledger:project-existing');
        $output = Artisan::output();

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('agency_deposit_approved', $output);
        $this->assertStringContainsString('AGENCY_WALLET_LIABILITY', $output);
    }

    public function test_backfill_dry_run_writes_nothing(): void
    {
        $this->buildOtaFinanceScenario();

        Artisan::call('ledger:backfill');
        $this->assertSame(0, LedgerTransaction::query()->count());
    }

    public function test_backfill_force_writes_idempotently(): void
    {
        $this->buildOtaFinanceScenario();

        Artisan::call('ledger:backfill', ['--force' => true]);
        $firstCount = LedgerTransaction::query()->where('status', LedgerTransactionStatus::Posted)->count();
        $this->assertGreaterThan(0, $firstCount);

        Artisan::call('ledger:backfill', ['--force' => true]);
        $secondCount = LedgerTransaction::query()->where('status', LedgerTransactionStatus::Posted)->count();
        $this->assertSame($firstCount, $secondCount);
    }

    public function test_reconcile_detects_mismatch_before_backfill(): void
    {
        $scenario = $this->buildOtaFinanceScenario();
        $agencyId = $scenario['agencies']['et']['agency']->id;

        $exit = Artisan::call('ledger:reconcile', ['--agency' => $agencyId]);
        $this->assertSame(1, $exit);
    }

    public function test_verify_balances_passes_after_backfill(): void
    {
        $this->buildOtaFinanceScenario();
        Artisan::call('ledger:backfill', ['--force' => true]);

        $exit = Artisan::call('ledger:verify-balances');
        $this->assertSame(0, $exit);
    }

    public function test_verify_balances_fails_on_mismatch(): void
    {
        $scenario = $this->buildOtaFinanceScenario();
        Artisan::call('ledger:backfill', ['--force' => true]);

        $scenario['agencies']['et']['wallet']->update(['balance' => 999_999]);

        $exit = Artisan::call('ledger:verify-balances', [
            '--agency' => $scenario['agencies']['et']['agency']->id,
        ]);
        $this->assertSame(1, $exit);
    }

    public function test_realistic_demo_agency_wallet_balances_match_expected(): void
    {
        $scenario = $this->buildOtaFinanceScenario();
        Artisan::call('ledger:backfill', ['--force' => true]);

        $balances = app(LedgerBalanceService::class);
        $expected = OtaFinanceDemoScenario::DOUBLE_ENTRY_WALLET_BALANCES;

        $this->assertSame(
            $expected['et'],
            $balances->getAgencyWalletBalance($scenario['agencies']['et']['agency']->id),
        );
        $this->assertSame(
            $expected['jp'],
            $balances->getAgencyWalletBalance($scenario['agencies']['jp']['agency']->id),
        );
        $this->assertSame(
            $expected['dt'],
            $balances->getAgencyWalletBalance($scenario['agencies']['dt']['agency']->id),
        );
    }

    public function test_platform_exposure_equals_agency_liability_total(): void
    {
        $scenario = $this->buildOtaFinanceScenario();
        Artisan::call('ledger:backfill', ['--force' => true]);

        $balances = app(LedgerBalanceService::class);
        $expected = OtaFinanceDemoScenario::DOUBLE_ENTRY_WALLET_BALANCES['platform_exposure'];

        $this->assertSame($expected, $balances->getPlatformExposure());
    }

    public function test_posted_transactions_have_balanced_debits_and_credits(): void
    {
        $this->buildOtaFinanceScenario();
        Artisan::call('ledger:backfill', ['--force' => true]);

        $transactions = LedgerTransaction::query()
            ->where('status', LedgerTransactionStatus::Posted)
            ->with('entries')
            ->get();

        foreach ($transactions as $transaction) {
            $debit = round((float) $transaction->entries->sum('debit'), 2);
            $credit = round((float) $transaction->entries->sum('credit'), 2);
            $this->assertSame($debit, $credit, 'Unbalanced transaction: '.$transaction->transaction_ref);
        }
    }

    public function test_actor_identifiers_stored_on_backfilled_deposits(): void
    {
        $scenario = $this->buildOtaFinanceScenario();
        Artisan::call('ledger:backfill', ['--force' => true]);

        $owner = $scenario['agencies']['et']['owner'];
        $tx = LedgerTransaction::query()
            ->where('transaction_type', LedgerTransactionType::AgencyDepositApproved)
            ->where('agency_id', $scenario['agencies']['et']['agency']->id)
            ->whereNotNull('actor_identifier')
            ->first();

        $this->assertNotNull($tx);
        $this->assertSame(ActorIdentifier::forUser($owner), $tx->actor_identifier);
    }

    public function test_guest_key_and_guest_actor_on_payment_projection(): void
    {
        $scenario = $this->buildOtaFinanceScenario();
        Artisan::call('ledger:backfill', ['--force' => true]);

        $tx = LedgerTransaction::query()
            ->where('transaction_type', LedgerTransactionType::BookingPaymentVerified)
            ->where('guest_key', 'guest:9001')
            ->first();

        $this->assertNotNull($tx);
        $this->assertSame('GU-9001-Guest', $tx->actor_identifier);
    }

    public function test_cross_agency_ledger_projection_isolated(): void
    {
        $scenario = $this->buildOtaFinanceScenario();
        Artisan::call('ledger:backfill', ['--force' => true]);

        $etId = $scenario['agencies']['et']['agency']->id;
        $jpId = $scenario['agencies']['jp']['agency']->id;

        $etEntries = LedgerTransaction::query()
            ->where('agency_id', $etId)
            ->where('transaction_type', LedgerTransactionType::AgencyDepositApproved)
            ->count();

        $jpInEt = LedgerTransaction::query()
            ->where('agency_id', $etId)
            ->whereHas('entries', fn ($q) => $q->where('agency_id', $jpId))
            ->count();

        $this->assertGreaterThan(0, $etEntries);
        $this->assertSame(0, $jpInEt);
    }

    public function test_check_integrity_passes_after_backfill(): void
    {
        $this->buildOtaFinanceScenario();
        Artisan::call('ledger:backfill', ['--force' => true]);

        $result = app(LedgerIntegrityService::class)->checkIntegrity();
        $this->assertTrue($result['passed']);
    }

    public function test_rejected_and_pending_deposits_do_not_affect_posted_wallet_balance(): void
    {
        $scenario = $this->buildOtaFinanceScenario();
        $balances = app(LedgerBalanceService::class);
        $agencyId = $scenario['agencies']['et']['agency']->id;

        Artisan::call('ledger:backfill', ['--force' => true]);
        $before = $balances->getAgencyWalletBalance($agencyId);

        $pendingCount = LedgerTransaction::query()
            ->where('source_type', (new AgentDepositRequest)->getMorphClass())
            ->whereIn('source_id', [
                $scenario['agencies']['et']['ledger']['deposits']['pending']->id,
            ])
            ->count();

        $this->assertSame(0, $pendingCount);
        $this->assertSame(OtaFinanceDemoScenario::DOUBLE_ENTRY_WALLET_BALANCES['et'], $before);
    }

    public function test_finance_reports_rbac_unchanged_for_staff_without_ledger(): void
    {
        $scenario = $this->buildOtaFinanceScenario();
        $staffOps = $scenario['platform']['staffOps'];

        $this->actingAs($staffOps)->get(route('staff.ledger.index'))->assertForbidden();
        $this->actingAs($staffOps)->get(route('staff.reports.index'))->assertForbidden();
    }
}
