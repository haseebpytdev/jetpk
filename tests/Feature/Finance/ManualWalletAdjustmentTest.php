<?php

namespace Tests\Feature\Finance;

use App\Enums\AccountType;
use App\Enums\AgentWalletTransactionType;
use App\Enums\LedgerTransactionStatus;
use App\Enums\LedgerTransactionType;
use App\Enums\UserAccountStatus;
use App\Models\Agency;
use App\Models\Agent;
use App\Models\AgentWallet;
use App\Models\AgentWalletTransaction;
use App\Models\LedgerTransaction;
use App\Models\User;
use App\Services\Agents\AgentWalletService;
use App\Services\Finance\Ledger\LedgerBalanceService;
use App\Support\Agents\AgentPermission;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Feature\Finance\Concerns\BuildsOtaFinanceScenario;
use Tests\TestCase;

class ManualWalletAdjustmentTest extends TestCase
{
    use BuildsOtaFinanceScenario;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
        $this->seedLedgerInfrastructure();
    }

    public function test_platform_admin_can_open_create_page(): void
    {
        $this->actingAs($this->platformAdmin())
            ->get(route('admin.finance.adjustments.create'))
            ->assertOk()
            ->assertSee('data-testid="finance-adjustment-warning"', false);
    }

    public function test_non_admin_cannot_open_create_page(): void
    {
        $staff = User::query()->where('email', 'staff@ota.demo')->firstOrFail();

        $this->actingAs($staff)->get(route('admin.finance.adjustments.create'))->assertForbidden();
    }

    public function test_platform_admin_can_create_manual_credit(): void
    {
        [$agency, $wallet] = $this->seedAgencyWallet(50);

        $this->actingAs($this->platformAdmin())->post(route('admin.finance.adjustments.store'), $this->validPayload($agency, $wallet, 'manual_credit', 25))
            ->assertRedirect()
            ->assertSessionHas('status', 'adjustment-created');
    }

    public function test_manual_credit_increases_wallet_balance(): void
    {
        [$agency, $wallet] = $this->seedAgencyWallet(50);
        $this->postAdjustment($agency, $wallet, 'manual_credit', 25);

        $this->assertSame(75.0, (float) $wallet->fresh()->balance);
    }

    public function test_manual_credit_creates_wallet_transaction_with_type_manual_credit(): void
    {
        [$agency, $wallet] = $this->seedAgencyWallet(0);
        $this->postAdjustment($agency, $wallet, 'manual_credit', 40);

        $this->assertDatabaseHas('agent_wallet_transactions', [
            'agent_wallet_id' => $wallet->id,
            'type' => AgentWalletTransactionType::ManualCredit->value,
            'amount' => 40,
            'status' => 'posted',
        ]);
    }

    public function test_manual_credit_creates_balanced_ledger_transaction(): void
    {
        [$agency, $wallet] = $this->seedAgencyWallet(0);
        $this->postAdjustment($agency, $wallet, 'manual_credit', 40);

        $tx = AgentWalletTransaction::query()->where('agent_wallet_id', $wallet->id)->latest('id')->firstOrFail();
        $ledger = LedgerTransaction::query()
            ->where('source_id', $tx->id)
            ->where('transaction_type', LedgerTransactionType::ManualWalletCredit)
            ->firstOrFail();

        $this->assertSame(LedgerTransactionStatus::Posted, $ledger->status);
        $this->assertSame(40.0, (float) $ledger->amount_total);
        $this->assertLedgerBalanced($ledger);
    }

    public function test_manual_credit_appears_in_admin_statement(): void
    {
        [$agency, $wallet] = $this->seedAgencyWallet(0);
        $this->postAdjustment($agency, $wallet, 'manual_credit', 15);

        $this->actingAs($this->platformAdmin())
            ->get(route('admin.finance.statements.show', [
                'agency' => $agency,
                'date_from' => now()->subDay()->toDateString(),
                'date_to' => now()->addDay()->toDateString(),
            ]))
            ->assertOk()
            ->assertSee('Manual wallet credit', false);
    }

    public function test_manual_credit_appears_in_agent_statement(): void
    {
        [$agency, $wallet] = $this->seedAgencyWallet(0);
        $agentUser = User::query()->findOrFail($wallet->user_id);
        $agentUser->forceFill([
            'meta' => array_merge($agentUser->meta ?? [], [
                'agent_permissions' => [AgentPermission::ReportsView],
            ]),
        ])->save();
        $this->postAdjustment($agency, $wallet, 'manual_credit', 12);

        $this->actingAs($agentUser->fresh())
            ->get(route('agent.finance.statement.show', [
                'date_from' => now()->subDay()->toDateString(),
                'date_to' => now()->addDay()->toDateString(),
            ]))
            ->assertOk()
            ->assertSee('Manual wallet credit', false);
    }

    public function test_manual_debit_decreases_wallet_balance(): void
    {
        [$agency, $wallet] = $this->seedAgencyWallet(100);
        $this->postAdjustment($agency, $wallet, 'manual_debit', 30);

        $this->assertSame(70.0, (float) $wallet->fresh()->balance);
    }

    public function test_manual_debit_creates_wallet_transaction_with_type_manual_debit(): void
    {
        [$agency, $wallet] = $this->seedAgencyWallet(80);
        $this->postAdjustment($agency, $wallet, 'manual_debit', 20);

        $this->assertDatabaseHas('agent_wallet_transactions', [
            'agent_wallet_id' => $wallet->id,
            'type' => AgentWalletTransactionType::ManualDebit->value,
            'amount' => 20,
        ]);
    }

    public function test_manual_debit_creates_balanced_ledger_transaction(): void
    {
        [$agency, $wallet] = $this->seedAgencyWallet(80);
        $this->postAdjustment($agency, $wallet, 'manual_debit', 20);

        $tx = AgentWalletTransaction::query()->where('agent_wallet_id', $wallet->id)->latest('id')->firstOrFail();
        $ledger = LedgerTransaction::query()
            ->where('source_id', $tx->id)
            ->where('transaction_type', LedgerTransactionType::ManualWalletDebit)
            ->firstOrFail();

        $this->assertLedgerBalanced($ledger);
    }

    public function test_manual_debit_appears_in_statements(): void
    {
        [$agency, $wallet] = $this->seedAgencyWallet(50);
        $this->postAdjustment($agency, $wallet, 'manual_debit', 10);

        $this->actingAs($this->platformAdmin())
            ->get(route('admin.finance.statements.show', [
                'agency' => $agency,
                'date_from' => now()->subDay()->toDateString(),
                'date_to' => now()->addDay()->toDateString(),
            ]))
            ->assertOk()
            ->assertSee('Manual wallet debit', false);
    }

    public function test_manual_debit_cannot_exceed_balance_without_credit_limit(): void
    {
        [$agency, $wallet] = $this->seedAgencyWallet(10);

        $this->actingAs($this->platformAdmin())
            ->from(route('admin.finance.adjustments.create'))
            ->post(route('admin.finance.adjustments.store'), $this->validPayload($agency, $wallet, 'manual_debit', 50))
            ->assertRedirect()
            ->assertSessionHasErrors('adjustment');

        $this->assertSame(10.0, (float) $wallet->fresh()->balance);
    }

    public function test_missing_reason_fails_validation(): void
    {
        [$agency, $wallet] = $this->seedAgencyWallet(0);
        $payload = $this->validPayload($agency, $wallet, 'manual_credit', 10);
        unset($payload['adjustment_reason']);

        $this->actingAs($this->platformAdmin())
            ->post(route('admin.finance.adjustments.store'), $payload)
            ->assertSessionHasErrors('adjustment_reason');
    }

    public function test_missing_confirmation_fails_validation(): void
    {
        [$agency, $wallet] = $this->seedAgencyWallet(0);
        $payload = $this->validPayload($agency, $wallet, 'manual_credit', 10);
        unset($payload['confirmation']);

        $this->actingAs($this->platformAdmin())
            ->post(route('admin.finance.adjustments.store'), $payload)
            ->assertSessionHasErrors('confirmation');
    }

    public function test_agency_with_multiple_wallets_uses_canonical_without_wallet_selector(): void
    {
        $agency = Agency::factory()->create();
        $agentA = $this->createAgentForAgency($agency);
        $agentB = $this->createAgentForAgency($agency);
        $walletA = AgentWallet::query()->create([
            'agency_id' => $agency->id,
            'agent_id' => $agentA->id,
            'user_id' => $agentA->user_id,
            'balance' => 10,
            'currency' => 'PKR',
            'status' => 'active',
        ]);
        $walletB = AgentWallet::query()->create([
            'agency_id' => $agency->id,
            'agent_id' => $agentB->id,
            'user_id' => $agentB->user_id,
            'balance' => 20,
            'currency' => 'PKR',
            'status' => 'active',
        ]);

        $canonical = app(AgentWalletService::class)->canonicalWalletForAgency($agency);
        $this->assertSame((int) $walletB->id, (int) $canonical?->id);

        $payload = $this->validPayload($agency, $walletA, 'manual_credit', 5);
        unset($payload['wallet_id']);

        $this->actingAs($this->platformAdmin())
            ->post(route('admin.finance.adjustments.store'), $payload)
            ->assertRedirect();

        $this->assertSame(25.0, (float) $walletB->fresh()->balance);
        $this->assertSame(10.0, (float) $walletA->fresh()->balance);
    }

    public function test_duplicate_post_with_same_idempotency_key_is_idempotent(): void
    {
        [$agency, $wallet] = $this->seedAgencyWallet(100);
        $payload = $this->validPayload($agency, $wallet, 'manual_credit', 5);

        $this->actingAs($this->platformAdmin())->post(route('admin.finance.adjustments.store'), $payload);
        $this->actingAs($this->platformAdmin())->post(route('admin.finance.adjustments.store'), $payload);

        $this->assertSame(1, AgentWalletTransaction::query()->where('agent_wallet_id', $wallet->id)->where('type', 'manual_credit')->count());
        $this->assertSame(105.0, (float) $wallet->fresh()->balance);
    }

    public function test_reconciliation_remains_matched_after_credit_and_debit(): void
    {
        [$agency, $wallet] = $this->seedAgencyWallet(0);
        $this->postAdjustment($agency, $wallet, 'manual_credit', 100);
        $this->postAdjustment($agency, $wallet->fresh(), 'manual_debit', 25);

        $compare = app(LedgerBalanceService::class)->compareWalletToLedger($agency->id);
        $this->assertTrue($compare['matches']);
        $this->assertSame(75.0, $compare['wallet_balance']);
        $this->assertSame(75.0, $compare['ledger_balance']);
    }

    public function test_accounting_ledger_detail_shows_entries(): void
    {
        [$agency, $wallet] = $this->seedAgencyWallet(0);
        $this->postAdjustment($agency, $wallet, 'manual_credit', 33);

        $ledger = LedgerTransaction::query()->where('agency_id', $agency->id)->latest('id')->firstOrFail();

        $this->actingAs($this->platformAdmin())
            ->get(route('admin.accounting.ledger.show', $ledger))
            ->assertOk()
            ->assertSee($ledger->transaction_ref, false);
    }

    public function test_viewing_adjustment_pages_does_not_mutate_balances(): void
    {
        [$agency, $wallet] = $this->seedAgencyWallet(42);
        $this->postAdjustment($agency, $wallet, 'manual_credit', 8);
        $tx = AgentWalletTransaction::query()->where('agent_wallet_id', $wallet->id)->latest('id')->firstOrFail();
        $balance = (float) $wallet->fresh()->balance;

        $admin = $this->platformAdmin();
        $this->actingAs($admin)->get(route('admin.finance.adjustments.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.finance.adjustments.show', $tx))->assertOk();
        $this->actingAs($admin)->get(route('admin.finance.adjustments.create'))->assertOk();

        $this->assertSame($balance, (float) $wallet->fresh()->balance);
        $this->assertSame(1, AgentWalletTransaction::query()->where('agent_wallet_id', $wallet->id)->where('type', 'manual_credit')->count());
    }

    public function test_adjustment_writes_audit_log(): void
    {
        [$agency, $wallet] = $this->seedAgencyWallet(0);
        $this->postAdjustment($agency, $wallet, 'manual_credit', 5);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'finance.manual_wallet_adjustment',
            'agency_id' => $agency->id,
        ]);
    }

    /**
     * @return array{0: Agency, 1: AgentWallet}
     */
    protected function seedAgencyWallet(float $balance): array
    {
        $agency = Agency::factory()->create();
        $agent = $this->createAgentForAgency($agency);
        $wallet = AgentWallet::query()->create([
            'agency_id' => $agency->id,
            'agent_id' => $agent->id,
            'user_id' => $agent->user_id,
            'balance' => $balance,
            'currency' => 'PKR',
            'status' => 'active',
        ]);

        return [$agency, $wallet];
    }

    protected function createAgentForAgency(Agency $agency): Agent
    {
        $user = User::query()->create([
            'name' => 'Agent '.$agency->id,
            'username' => 'adj-agent-'.$agency->id.'-'.uniqid(),
            'email' => 'adj-'.$agency->id.'-'.uniqid().'@example.test',
            'password' => bcrypt('password'),
            'account_type' => AccountType::Agent,
            'status' => UserAccountStatus::Active,
            'current_agency_id' => $agency->id,
        ]);

        return Agent::query()->create([
            'agency_id' => $agency->id,
            'user_id' => $user->id,
            'is_active' => true,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function validPayload(Agency $agency, AgentWallet $wallet, string $type, float $amount, ?string $idempotencyKey = null): array
    {
        return [
            'agency_id' => $agency->id,
            'wallet_id' => $wallet->id,
            'adjustment_type' => $type,
            'amount' => $amount,
            'adjustment_reason' => 'bank_correction',
            'adjustment_note' => 'Test adjustment',
            'idempotency_key' => $idempotencyKey ?? (string) Str::uuid(),
            'confirmation' => '1',
        ];
    }

    protected function postAdjustment(Agency $agency, AgentWallet $wallet, string $type, float $amount): void
    {
        $this->actingAs($this->platformAdmin())
            ->post(route('admin.finance.adjustments.store'), $this->validPayload($agency, $wallet, $type, $amount))
            ->assertRedirect();
    }

    protected function platformAdmin(): User
    {
        $admin = User::query()->where('email', 'admin@ota.demo')->firstOrFail();
        $admin->forceFill(['account_type' => AccountType::PlatformAdmin])->save();

        return $admin->fresh();
    }

    protected function assertLedgerBalanced(LedgerTransaction $ledger): void
    {
        $ledger->load('entries');
        $debit = round((float) $ledger->entries->sum('debit'), 2);
        $credit = round((float) $ledger->entries->sum('credit'), 2);
        $this->assertEqualsWithDelta($debit, $credit, 0.01);
        $this->assertGreaterThan(0, $debit);
    }
}
