<?php

namespace Tests\Feature\Finance;

use App\Enums\AccountType;
use App\Enums\AgentDepositRequestStatus;
use App\Enums\AgentWalletTransactionType;
use App\Enums\LedgerTransactionStatus;
use App\Enums\LedgerTransactionType;
use App\Enums\UserAccountStatus;
use App\Models\Agency;
use App\Models\Agent;
use App\Models\AgentDepositRequest;
use App\Models\AgentWallet;
use App\Models\AgentWalletTransaction;
use App\Models\LedgerTransaction;
use App\Models\User;
use App\Services\Agents\AgentWalletService;
use App\Support\Staff\StaffPermission;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Finance\Concerns\BuildsOtaFinanceScenario;
use Tests\TestCase;

/**
 * Finance-Reports-6: first real ledger event gate — agency deposit approval via HTTP workflow.
 */
class FirstRealLedgerEventGateTest extends TestCase
{
    use BuildsOtaFinanceScenario;
    use RefreshDatabase;

    private const DEPOSIT_AMOUNT = 4150.00;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
        $this->seedLedgerInfrastructure();
    }

    public function test_http_deposit_submit_and_admin_approve_creates_single_balanced_ledger_transaction(): void
    {
        [$agentUser, $agent, $admin, $deposit] = $this->runHttpDepositGateWorkflow();

        $deposit->refresh();
        $this->assertSame(AgentDepositRequestStatus::Approved, $deposit->status);
        $this->assertNotNull($deposit->reviewed_by);
        $this->assertSame($admin->id, $deposit->reviewed_by);

        $wallet = AgentWallet::query()->where('agent_id', $agent->id)->firstOrFail();
        $this->assertSame(self::DEPOSIT_AMOUNT, (float) $wallet->balance);

        $this->assertDatabaseHas('agent_wallet_transactions', [
            'agent_deposit_request_id' => $deposit->id,
            'type' => AgentWalletTransactionType::DepositApproved->value,
            'amount' => self::DEPOSIT_AMOUNT,
        ]);

        $this->assertSame(1, LedgerTransaction::query()
            ->where('transaction_type', LedgerTransactionType::AgencyDepositApproved)
            ->where('source_id', $deposit->id)
            ->count());

        $tx = LedgerTransaction::query()
            ->where('source_id', $deposit->id)
            ->where('transaction_type', LedgerTransactionType::AgencyDepositApproved)
            ->firstOrFail();

        $this->assertSame(LedgerTransactionStatus::Posted, $tx->status);
        $this->assertSame(self::DEPOSIT_AMOUNT, (float) $tx->amount_total);
        $this->assertSame((new AgentDepositRequest)->getMorphClass(), $tx->source_type);
        $this->assertNotNull($tx->actor_identifier);
        $this->assertStringContainsString((string) $admin->id, (string) $tx->actor_identifier);

        $this->assertCount(2, $tx->entries);
        $debit = round((float) $tx->entries->sum('debit'), 2);
        $credit = round((float) $tx->entries->sum('credit'), 2);
        $this->assertSame($debit, $credit);
        $this->assertSame(self::DEPOSIT_AMOUNT, $debit);

        $cashLine = $tx->entries->first(fn ($e) => $e->account->code === 'PLATFORM_CASH' && (float) $e->debit > 0);
        $liabilityLine = $tx->entries->first(fn ($e) => $e->account->code === 'AGENCY_WALLET_LIABILITY' && (float) $e->credit > 0);
        $this->assertNotNull($cashLine);
        $this->assertNotNull($liabilityLine);
        $this->assertSame($agent->agency_id, $liabilityLine->agency_id);
    }

    public function test_admin_approve_retry_after_http_gate_does_not_duplicate_ledger(): void
    {
        [, , $admin, $deposit] = $this->runHttpDepositGateWorkflow();

        $this->actingAs($admin)
            ->patch(route('admin.agent-deposits.approve', $deposit))
            ->assertRedirect();

        $this->assertSame(1, LedgerTransaction::query()
            ->where('source_id', $deposit->id)
            ->where('transaction_type', LedgerTransactionType::AgencyDepositApproved)
            ->count());
    }

    public function test_accounting_ui_rbac_and_reconciliation_after_http_deposit_gate(): void
    {
        [$agentUser, $agent, $admin, $deposit] = $this->runHttpDepositGateWorkflow();
        $tx = LedgerTransaction::query()
            ->where('source_id', $deposit->id)
            ->where('transaction_type', LedgerTransactionType::AgencyDepositApproved)
            ->firstOrFail();

        $this->actingAs($admin)->get(route('admin.accounting.ledger.index'))
            ->assertOk()
            ->assertSee('data-testid="accounting-ledger-row-'.$tx->id.'"', false);

        $this->actingAs($admin)->get(route('admin.accounting.ledger.show', $tx))
            ->assertOk()
            ->assertSee('data-testid="accounting-ledger-entries"', false);

        $this->actingAs($admin)->get(route('admin.accounting.reconciliation.index'))
            ->assertOk()
            ->assertSee('data-testid="reconciliation-agency-'.$agent->agency_id.'"', false)
            ->assertSee('Matched', false);

        $this->actingAs($agentUser)->get(route('agent.accounting.ledger.index'))
            ->assertOk()
            ->assertSee('data-testid="accounting-ledger-row-'.$tx->id.'"', false);

        $otherAgentUser = $this->seedOtherAgencyAgentUser();
        $this->actingAs($otherAgentUser)->get(route('agent.accounting.ledger.index'))
            ->assertOk()
            ->assertDontSee('data-testid="accounting-ledger-row-'.$tx->id.'"', false);

        $staffAllowed = $this->staffWithPermissions([StaffPermission::LedgerView]);
        $this->actingAs($staffAllowed)->get(route('staff.accounting.ledger.index'))
            ->assertOk()
            ->assertSee('data-testid="accounting-ledger-row-'.$tx->id.'"', false);

        $staffDenied = $this->staffWithPermissions([StaffPermission::BookingsView]);
        $this->actingAs($staffDenied)->get(route('staff.accounting.ledger.index'))->assertForbidden();

        $this->actingAs($admin)->get(route('admin.ledger.index'))->assertOk();
        $this->actingAs($agentUser)->get(route('agent.ledger.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.reports'))->assertOk();
    }

    public function test_viewing_accounting_pages_does_not_mutate_source_of_truth_after_gate(): void
    {
        [, , $admin] = $this->runHttpDepositGateWorkflow();

        $walletCount = AgentWallet::query()->count();
        $walletTxCount = AgentWalletTransaction::query()->count();
        $depositCount = AgentDepositRequest::query()->count();
        $ledgerTxCount = LedgerTransaction::query()->count();

        $tx = LedgerTransaction::query()->firstOrFail();
        $this->actingAs($admin)->get(route('admin.accounting.ledger.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.accounting.ledger.show', $tx))->assertOk();
        $this->actingAs($admin)->get(route('admin.accounting.reconciliation.index'))->assertOk();

        $this->assertSame($walletCount, AgentWallet::query()->count());
        $this->assertSame($walletTxCount, AgentWalletTransaction::query()->count());
        $this->assertSame($depositCount, AgentDepositRequest::query()->count());
        $this->assertSame($ledgerTxCount, LedgerTransaction::query()->count());
    }

    /**
     * @return array{0: User, 1: Agent, 2: User, 3: AgentDepositRequest}
     */
    protected function runHttpDepositGateWorkflow(): array
    {
        [$agentUser, $agent] = $this->seedPrimaryAgent();
        $admin = $this->platformAdmin();
        $reference = 'GATE-FR6-'.uniqid();

        $walletBefore = (float) app(AgentWalletService::class)->walletFor($agent)->balance;

        $this->actingAs($agentUser)
            ->post(route('agent.deposits.store'), [
                'amount' => self::DEPOSIT_AMOUNT,
                'payment_method' => 'bank_transfer',
                'reference' => $reference,
                'agent_note' => 'Finance-Reports-6 gate deposit',
            ])
            ->assertRedirect(route('agent.deposits.index'));

        $deposit = AgentDepositRequest::query()
            ->where('reference', $reference)
            ->where('agent_id', $agent->id)
            ->firstOrFail();

        $this->assertSame(AgentDepositRequestStatus::Submitted, $deposit->status);
        $this->assertSame(self::DEPOSIT_AMOUNT, (float) $deposit->amount);
        $this->assertSame($walletBefore, (float) app(AgentWalletService::class)->walletFor($agent)->balance);

        $this->actingAs($admin)
            ->patch(route('admin.agent-deposits.approve', $deposit))
            ->assertRedirect(route('admin.agent-deposits.show', $deposit));

        return [$agentUser, $agent, $admin, $deposit];
    }

    /**
     * @return array{0: User, 1: Agent}
     */
    protected function seedPrimaryAgent(): array
    {
        $agentUser = User::query()->where('email', 'agent@ota.demo')->firstOrFail();
        $agent = Agent::query()->where('user_id', $agentUser->id)->firstOrFail();

        return [$agentUser, $agent];
    }

    protected function platformAdmin(): User
    {
        $admin = User::query()->where('email', 'admin@ota.demo')->firstOrFail();
        $admin->forceFill(['account_type' => AccountType::PlatformAdmin])->save();

        return $admin->fresh();
    }

    protected function staffWithPermissions(array $permissions): User
    {
        $staff = User::query()->where('email', 'staff@ota.demo')->firstOrFail();
        $staff->forceFill([
            'meta' => array_merge($staff->meta ?? [], ['staff_permissions' => $permissions]),
        ])->save();

        return $staff->fresh();
    }

    protected function seedOtherAgencyAgentUser(): User
    {
        $agency = Agency::factory()->create(['name' => 'Other Gate Agency']);
        $user = User::factory()->create([
            'email' => 'other-gate-agent@ota.test',
            'account_type' => AccountType::Agent,
            'status' => UserAccountStatus::Active,
            'current_agency_id' => $agency->id,
        ]);
        Agent::factory()->create([
            'agency_id' => $agency->id,
            'user_id' => $user->id,
        ]);
        $user->agencies()->syncWithoutDetaching([$agency->id => ['role' => 'agent']]);

        return $user;
    }
}
