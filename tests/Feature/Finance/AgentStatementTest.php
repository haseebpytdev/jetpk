<?php

namespace Tests\Feature\Finance;

use App\Enums\AccountType;
use App\Enums\UserAccountStatus;
use App\Models\Agency;
use App\Models\Agent;
use App\Models\AgentDepositRequest;
use App\Models\AgentWallet;
use App\Models\AgentWalletTransaction;
use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\BookingRefund;
use App\Models\LedgerTransaction;
use App\Models\User;
use App\Policies\FinanceStatementPolicy;
use App\Services\Agents\AgentWalletService;
use App\Services\Finance\Statements\AgentStatementService;
use App\Support\Agents\AgentPermission;
use App\Support\Staff\StaffPermission;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Finance\Concerns\BuildsOtaFinanceScenario;
use Tests\TestCase;

class AgentStatementTest extends TestCase
{
    use BuildsOtaFinanceScenario;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
        $this->seedLedgerInfrastructure();
    }

    public function test_admin_can_view_statements_index(): void
    {
        $admin = $this->platformAdmin();

        $this->actingAs($admin)->get(route('admin.finance.statements.index'))
            ->assertOk()
            ->assertSee('data-testid="finance-statements-index-title"', false);
    }

    public function test_admin_can_view_agency_statement(): void
    {
        $admin = $this->platformAdmin();
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();

        $this->actingAs($admin)->get(route('admin.finance.statements.show', $agency))
            ->assertOk()
            ->assertSee('data-testid="finance-statement-show-title"', false);
    }

    public function test_staff_with_reports_view_can_view_statements(): void
    {
        $staff = $this->staffWithPermissions([StaffPermission::ReportsView]);
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();

        $this->actingAs($staff)->get(route('staff.finance.statements.index'))->assertOk();
        $this->actingAs($staff)->get(route('staff.finance.statements.show', $agency))->assertOk();
    }

    public function test_staff_without_reports_view_gets_403(): void
    {
        $staff = $this->staffWithPermissions([StaffPermission::BookingsView]);

        $this->actingAs($staff)->get(route('staff.finance.statements.index'))->assertForbidden();
    }

    public function test_agent_can_view_own_agency_statement(): void
    {
        [$agentUser] = $this->seedAgent();
        $agentUser->forceFill([
            'meta' => array_merge($agentUser->meta ?? [], [
                'agent_permissions' => [AgentPermission::ReportsView],
            ]),
        ])->save();

        $this->actingAs($agentUser->fresh())->get(route('agent.finance.statement.show'))
            ->assertOk()
            ->assertSee('data-testid="agent-finance-statement-title"', false);
    }

    public function test_other_agent_cannot_access_another_agency_statement_via_policy(): void
    {
        $agencyA = Agency::factory()->create(['name' => 'Statement Agency A']);
        $agencyB = Agency::factory()->create(['name' => 'Statement Agency B']);
        $agentB = $this->createAgentForAgency($agencyB);
        $agentBUser = User::query()->findOrFail($agentB->user_id);

        $policy = app(FinanceStatementPolicy::class);

        $this->assertFalse($policy->view($agentBUser, $agencyA));
    }

    public function test_agent_statement_includes_approved_deposit_movement(): void
    {
        $admin = $this->platformAdmin();
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $agent = Agent::query()->where('agency_id', $agency->id)->firstOrFail();
        $wallet = AgentWallet::query()->firstOrCreate(
            ['agent_id' => $agent->id],
            [
                'agency_id' => $agency->id,
                'user_id' => $agent->user_id,
                'balance' => 0,
                'currency' => 'PKR',
                'status' => 'active',
            ],
        );

        $deposit = AgentDepositRequest::query()->create([
            'agency_id' => $agency->id,
            'agent_id' => $agent->id,
            'user_id' => $agent->user_id,
            'agent_wallet_id' => $wallet->id,
            'amount' => 250,
            'currency' => 'PKR',
            'reference' => 'DEP-STMT-'.uniqid(),
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);
        app(AgentWalletService::class)->approveDeposit($deposit, $admin);

        $from = now()->subDay()->toDateString();
        $to = now()->addDay()->toDateString();

        $this->actingAs($admin)->get(route('admin.finance.statements.show', [
            'agency' => $agency,
            'date_from' => $from,
            'date_to' => $to,
        ]))
            ->assertOk()
            ->assertSee('Deposit approved', false);
    }

    public function test_opening_balance_before_date_range_is_calculated(): void
    {
        $agency = Agency::factory()->create();
        $agent = $this->createAgentForAgency($agency);
        $wallet = AgentWallet::query()->create([
            'agency_id' => $agency->id,
            'agent_id' => $agent->id,
            'user_id' => $agent->user_id,
            'balance' => 500,
            'currency' => 'PKR',
            'status' => 'active',
        ]);

        $prior = AgentWalletTransaction::query()->create([
            'agency_id' => $agency->id,
            'agent_id' => $agent->id,
            'user_id' => $agent->user_id,
            'agent_wallet_id' => $wallet->id,
            'type' => 'deposit_approved',
            'amount' => 500,
            'balance_before' => 0,
            'balance_after' => 500,
            'status' => 'posted',
            'reference' => 'OPEN-1',
            'description' => 'Prior deposit',
        ]);
        $prior->forceFill(['created_at' => now()->subDays(10), 'updated_at' => now()->subDays(10)])->save();

        $service = app(AgentStatementService::class);
        $from = now()->subDays(5)->startOfDay();
        $to = now()->endOfDay();
        $statement = $service->buildStatement($agency, $from, $to);

        $this->assertSame(500.0, $statement['opening_balance']);
    }

    public function test_closing_balance_after_movements_is_calculated(): void
    {
        $agency = Agency::factory()->create();
        $agent = $this->createAgentForAgency($agency);
        $wallet = AgentWallet::query()->create([
            'agency_id' => $agency->id,
            'agent_id' => $agent->id,
            'user_id' => $agent->user_id,
            'balance' => 700,
            'currency' => 'PKR',
            'status' => 'active',
        ]);

        $at = now()->subDay();
        $credit = AgentWalletTransaction::query()->create([
            'agency_id' => $agency->id,
            'agent_id' => $agent->id,
            'user_id' => $agent->user_id,
            'agent_wallet_id' => $wallet->id,
            'type' => 'deposit_approved',
            'amount' => 500,
            'balance_before' => 0,
            'balance_after' => 500,
            'status' => 'posted',
            'reference' => 'CLS-1',
            'description' => 'Credit',
        ]);
        $credit->forceFill(['created_at' => $at, 'updated_at' => $at])->save();
        $hold = AgentWalletTransaction::query()->create([
            'agency_id' => $agency->id,
            'agent_id' => $agent->id,
            'user_id' => $agent->user_id,
            'agent_wallet_id' => $wallet->id,
            'type' => 'booking_hold',
            'amount' => 200,
            'balance_before' => 500,
            'balance_after' => 300,
            'status' => 'posted',
            'reference' => 'CLS-2',
            'description' => 'Hold',
        ]);
        $hold->forceFill(['created_at' => $at->copy()->addHour(), 'updated_at' => $at->copy()->addHour()])->save();

        $service = app(AgentStatementService::class);
        $statement = $service->buildStatement($agency, now()->subDays(2)->startOfDay(), now()->endOfDay());

        $this->assertSame(300.0, $statement['closing_balance']);
        $this->assertSame(500.0, $statement['total_credits']);
        $this->assertSame(200.0, $statement['total_debits']);
    }

    public function test_csv_export_works_for_admin(): void
    {
        $admin = $this->platformAdmin();
        $agency = Agency::query()->firstOrFail();

        $this->actingAs($admin)->get(route('admin.finance.statements.export', $agency))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    public function test_csv_export_works_for_agent_own_agency(): void
    {
        [$agentUser] = $this->seedAgent();
        $agentUser->forceFill([
            'meta' => array_merge($agentUser->meta ?? [], [
                'agent_permissions' => [AgentPermission::LedgerView],
            ]),
        ])->save();

        $this->actingAs($agentUser->fresh())->get(route('agent.finance.statement.export'))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    public function test_reconciliation_summary_shows_matched_when_wallet_equals_ledger(): void
    {
        $admin = $this->platformAdmin();
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $agent = Agent::query()->where('agency_id', $agency->id)->firstOrFail();
        $wallet = AgentWallet::query()->firstOrCreate(
            ['agent_id' => $agent->id],
            [
                'agency_id' => $agency->id,
                'user_id' => $agent->user_id,
                'balance' => 0,
                'currency' => 'PKR',
                'status' => 'active',
            ],
        );

        $deposit = AgentDepositRequest::query()->create([
            'agency_id' => $agency->id,
            'agent_id' => $agent->id,
            'user_id' => $agent->user_id,
            'agent_wallet_id' => $wallet->id,
            'amount' => 100,
            'currency' => 'PKR',
            'reference' => 'DEP-MATCH-'.uniqid(),
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);
        app(AgentWalletService::class)->approveDeposit($deposit, $admin);

        $statement = app(AgentStatementService::class)->buildStatement(
            $agency,
            now()->subDay()->startOfDay(),
            now()->addDay()->endOfDay(),
        );

        $this->assertSame('matched', $statement['reconciliation']['status']);
    }

    public function test_reconciliation_summary_shows_mismatch_when_values_differ(): void
    {
        $admin = $this->platformAdmin();
        $agency = Agency::factory()->create();
        $agent = $this->createAgentForAgency($agency);
        $wallet = AgentWallet::query()->create([
            'agency_id' => $agency->id,
            'agent_id' => $agent->id,
            'user_id' => $agent->user_id,
            'balance' => 0,
            'currency' => 'PKR',
            'status' => 'active',
        ]);

        $deposit = AgentDepositRequest::query()->create([
            'agency_id' => $agency->id,
            'agent_id' => $agent->id,
            'user_id' => $agent->user_id,
            'agent_wallet_id' => $wallet->id,
            'amount' => 100,
            'currency' => 'PKR',
            'reference' => 'DEP-MIS-'.uniqid(),
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);
        app(AgentWalletService::class)->approveDeposit($deposit, $admin);
        $wallet->update(['balance' => 1000]);

        $statement = app(AgentStatementService::class)->buildStatement(
            $agency,
            now()->startOfMonth()->startOfDay(),
            now()->endOfDay(),
        );

        $this->assertSame('mismatch', $statement['reconciliation']['status']);
    }

    public function test_empty_period_shows_clean_empty_state(): void
    {
        $admin = $this->platformAdmin();
        $agency = Agency::factory()->create();

        $this->actingAs($admin)->get(route('admin.finance.statements.show', [
            'agency' => $agency,
            'date_from' => '2099-01-01',
            'date_to' => '2099-01-31',
        ]))
            ->assertOk()
            ->assertSee('data-testid="finance-statement-empty"', false)
            ->assertSee('No statement movements found for this period.', false);
    }

    public function test_viewing_statements_does_not_create_finance_rows(): void
    {
        $admin = $this->platformAdmin();
        $agency = Agency::query()->firstOrFail();

        $walletCount = AgentWallet::query()->count();
        $txCount = AgentWalletTransaction::query()->count();
        $ledgerTxCount = LedgerTransaction::query()->count();
        $paymentCount = BookingPayment::query()->count();
        $refundCount = BookingRefund::query()->count();
        $bookingCount = Booking::query()->count();

        $this->actingAs($admin)->get(route('admin.finance.statements.show', $agency))->assertOk();
        $this->actingAs($admin)->get(route('admin.finance.statements.export', $agency))->assertOk();

        $this->assertSame($walletCount, AgentWallet::query()->count());
        $this->assertSame($txCount, AgentWalletTransaction::query()->count());
        $this->assertSame($ledgerTxCount, LedgerTransaction::query()->count());
        $this->assertSame($paymentCount, BookingPayment::query()->count());
        $this->assertSame($refundCount, BookingRefund::query()->count());
        $this->assertSame($bookingCount, Booking::query()->count());
    }

    public function test_existing_ledger_routes_still_work(): void
    {
        $admin = $this->platformAdmin();
        [$agentUser] = $this->seedAgent();

        $this->actingAs($admin)->get(route('admin.ledger.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.accounting.ledger.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.accounting.reconciliation.index'))->assertOk();
        $this->actingAs($agentUser)->get(route('agent.ledger.index'))->assertOk();
        $this->actingAs($agentUser)->get(route('agent.accounting.ledger.index'))->assertOk();
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

    /**
     * @return array{0: User, 1: Agent}
     */
    protected function seedAgent(): array
    {
        $agentUser = User::query()->where('email', 'agent@ota.demo')->firstOrFail();
        $agent = Agent::query()->where('user_id', $agentUser->id)->firstOrFail();

        return [$agentUser, $agent];
    }

    protected function createAgentForAgency(Agency $agency): Agent
    {
        $user = User::query()->create([
            'name' => 'Agent '.$agency->id,
            'username' => 'agent-stmt-'.$agency->id,
            'email' => 'agent-stmt-'.$agency->id.'@example.test',
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
}
