<?php

namespace Tests\Feature\Finance;

use App\Enums\AccountType;
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
use App\Support\Agents\AgentPermission;
use App\Support\Staff\StaffPermission;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Finance\Concerns\BuildsOtaFinanceScenario;
use Tests\TestCase;

class LedgerVisibilityTest extends TestCase
{
    use BuildsOtaFinanceScenario;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
        $this->seedLedgerInfrastructure();
    }

    public function test_platform_admin_can_see_all_accounting_ledger_transactions(): void
    {
        $admin = $this->platformAdmin();
        [$txA, $txB] = $this->seedLedgerTransactionsForTwoAgencies();

        $this->actingAs($admin)->get(route('admin.accounting.ledger.index'))
            ->assertOk()
            ->assertSee('data-testid="accounting-ledger-table"', false)
            ->assertSee('data-testid="accounting-ledger-row-'.$txA->id.'"', false)
            ->assertSee('data-testid="accounting-ledger-row-'.$txB->id.'"', false);
    }

    public function test_platform_admin_can_view_transaction_detail_with_entries(): void
    {
        $admin = $this->platformAdmin();
        $tx = $this->seedPostedDepositLedgerTransaction();

        $this->actingAs($admin)->get(route('admin.accounting.ledger.show', $tx))
            ->assertOk()
            ->assertSee('data-testid="accounting-ledger-entries"', false)
            ->assertSee('data-testid="accounting-ledger-show-actor"', false)
            ->assertSee($tx->transaction_ref);
    }

    public function test_staff_with_ledger_permission_can_see_accounting_ledger(): void
    {
        $staff = $this->staffWithPermissions([StaffPermission::LedgerView]);
        $this->seedPostedDepositLedgerTransaction();

        $this->actingAs($staff)->get(route('staff.accounting.ledger.index'))->assertOk()
            ->assertSee('data-testid="accounting-ledger-table"', false);
    }

    public function test_staff_without_ledger_permission_gets_403(): void
    {
        $staff = $this->staffWithPermissions([StaffPermission::BookingsView]);

        $this->actingAs($staff)->get(route('staff.accounting.ledger.index'))->assertForbidden();
    }

    public function test_agent_owner_sees_only_own_agency_ledger_transactions(): void
    {
        [$agentUser, $agent] = $this->seedAgent();
        $ownTx = $this->seedPostedDepositLedgerTransaction($agent->agency_id);
        $otherAgency = Agency::factory()->create();
        $otherTx = $this->seedPostedDepositLedgerTransaction($otherAgency->id);

        $response = $this->actingAs($agentUser)->get(route('agent.accounting.ledger.index'));
        $response->assertOk()
            ->assertSee('data-testid="accounting-ledger-row-'.$ownTx->id.'"', false)
            ->assertDontSee('data-testid="accounting-ledger-row-'.$otherTx->id.'"', false);
    }

    public function test_agent_staff_with_permission_sees_only_own_agency_ledger_transactions(): void
    {
        [, $agent] = $this->seedAgent();
        $staff = $this->createAgentStaff($agent, 'acct-ledger@test', [AgentPermission::LedgerView]);
        $ownTx = $this->seedPostedDepositLedgerTransaction($agent->agency_id);

        $this->actingAs($staff)->get(route('agent.accounting.ledger.index'))
            ->assertOk()
            ->assertSee('data-testid="accounting-ledger-row-'.$ownTx->id.'"', false);
    }

    public function test_agent_staff_without_permission_gets_403(): void
    {
        [, $agent] = $this->seedAgent();
        $staff = $this->createAgentStaff($agent, 'no-acct@test', [AgentPermission::BookingsView]);

        $this->actingAs($staff)->get(route('agent.accounting.ledger.index'))->assertForbidden();
    }

    public function test_cross_agency_transaction_detail_access_blocked_for_agent(): void
    {
        [$agentUser] = $this->seedAgent();
        $otherAgency = Agency::factory()->create();
        $otherTx = $this->seedPostedDepositLedgerTransaction($otherAgency->id);

        $this->actingAs($agentUser)->get(route('agent.accounting.ledger.show', $otherTx))->assertForbidden();
    }

    public function test_filters_by_transaction_type_work(): void
    {
        $admin = $this->platformAdmin();
        $depositTx = $this->seedPostedDepositLedgerTransaction();

        $this->actingAs($admin)->get(route('admin.accounting.ledger.index', [
            'transaction_type' => LedgerTransactionType::AgencyDepositApproved->value,
        ]))
            ->assertOk()
            ->assertSee('data-testid="accounting-ledger-row-'.$depositTx->id.'"', false);

        $this->actingAs($admin)->get(route('admin.accounting.ledger.index', [
            'transaction_type' => LedgerTransactionType::BookingPaymentVerified->value,
        ]))
            ->assertOk()
            ->assertSee('data-testid="accounting-ledger-empty"', false);
    }

    public function test_filters_by_agency_work_for_admin(): void
    {
        $admin = $this->platformAdmin();
        $agencyA = Agency::factory()->create(['name' => 'Acct Agency A']);
        $agencyB = Agency::factory()->create(['name' => 'Acct Agency B']);
        $txA = $this->seedPostedDepositLedgerTransaction($agencyA->id);
        $txB = $this->seedPostedDepositLedgerTransaction($agencyB->id);

        $this->actingAs($admin)->get(route('admin.accounting.ledger.index', ['agency_id' => $agencyA->id]))
            ->assertOk()
            ->assertSee('data-testid="accounting-ledger-row-'.$txA->id.'"', false)
            ->assertDontSee('data-testid="accounting-ledger-row-'.$txB->id.'"', false);
    }

    public function test_empty_ledger_state_renders_without_error(): void
    {
        $admin = $this->platformAdmin();

        $this->actingAs($admin)->get(route('admin.accounting.ledger.index'))
            ->assertOk()
            ->assertSee('No double-entry ledger transactions yet', false)
            ->assertSee('data-testid="accounting-ledger-empty"', false);
    }

    public function test_balanced_filter_works_with_posted_data(): void
    {
        $admin = $this->platformAdmin();
        $tx = $this->seedPostedDepositLedgerTransaction();

        $this->actingAs($admin)->get(route('admin.accounting.ledger.index', ['balanced' => 'yes']))
            ->assertOk()
            ->assertSee('data-testid="accounting-ledger-row-'.$tx->id.'"', false);

        $this->actingAs($admin)->get(route('admin.accounting.ledger.index', ['balanced' => 'no']))
            ->assertOk()
            ->assertSee('data-testid="accounting-ledger-empty"', false);
    }

    public function test_unbalanced_filter_finds_manually_unbalanced_transaction(): void
    {
        $admin = $this->platformAdmin();
        $agency = Agency::factory()->create();
        $txId = DB::table('ledger_transactions')->insertGetId([
            'transaction_ref' => 'LT-TEST-UNBAL',
            'transaction_type' => LedgerTransactionType::WalletAdjustment->value,
            'status' => LedgerTransactionStatus::Posted->value,
            'agency_id' => $agency->id,
            'currency' => 'PKR',
            'amount_total' => 100,
            'posted_at' => now(),
            'occurred_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $accountId = DB::table('ledger_accounts')->where('code', 'AGENCY_WALLET_LIABILITY')->value('id');
        DB::table('ledger_entries')->insert([
            [
                'ledger_transaction_id' => $txId,
                'ledger_account_id' => $accountId,
                'agency_id' => $agency->id,
                'debit' => 100,
                'credit' => 0,
                'currency' => 'PKR',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'ledger_transaction_id' => $txId,
                'ledger_account_id' => $accountId,
                'agency_id' => $agency->id,
                'debit' => 0,
                'credit' => 50,
                'currency' => 'PKR',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
        $tx = LedgerTransaction::query()->findOrFail($txId);

        $this->actingAs($admin)->get(route('admin.accounting.ledger.index', ['balanced' => 'no']))
            ->assertOk()
            ->assertSee('data-testid="accounting-ledger-row-'.$tx->id.'"', false);
    }

    public function test_existing_master_ledger_routes_still_work(): void
    {
        $admin = $this->platformAdmin();
        [$agentUser] = $this->seedAgent();
        $staff = $this->staffWithPermissions([StaffPermission::LedgerView]);

        $this->actingAs($admin)->get(route('admin.ledger.index'))->assertOk();
        $this->actingAs($staff)->get(route('staff.ledger.index'))->assertOk();
        $this->actingAs($agentUser)->get(route('agent.ledger.index'))->assertOk();
    }

    public function test_viewing_pages_does_not_mutate_source_of_truth_tables(): void
    {
        $admin = $this->platformAdmin();
        $this->seedPostedDepositLedgerTransaction();

        $walletCount = AgentWallet::query()->count();
        $walletTxCount = AgentWalletTransaction::query()->count();
        $depositCount = AgentDepositRequest::query()->count();

        $this->actingAs($admin)->get(route('admin.accounting.ledger.index'))->assertOk();
        $tx = LedgerTransaction::query()->firstOrFail();
        $this->actingAs($admin)->get(route('admin.accounting.ledger.show', $tx))->assertOk();
        $this->actingAs($admin)->get(route('admin.accounting.reconciliation.index'))->assertOk();

        $this->assertSame($walletCount, AgentWallet::query()->count());
        $this->assertSame($walletTxCount, AgentWalletTransaction::query()->count());
        $this->assertSame($depositCount, AgentDepositRequest::query()->count());
    }

    protected function platformAdmin(): User
    {
        $admin = User::query()->where('email', 'admin@ota.demo')->firstOrFail();
        $admin->forceFill(['account_type' => AccountType::PlatformAdmin])->save();

        return $admin;
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

    protected function createAgentStaff(Agent $agent, string $email, array $permissions): User
    {
        return User::query()->create([
            'name' => 'Agent Staff',
            'username' => str_replace('@', '-', $email),
            'email' => $email,
            'password' => bcrypt('password'),
            'account_type' => AccountType::AgentStaff,
            'status' => UserAccountStatus::Active,
            'current_agency_id' => $agent->agency_id,
            'meta' => [
                'owner_agent_id' => $agent->id,
                'agent_permissions' => $permissions,
            ],
        ]);
    }

    protected function seedPostedDepositLedgerTransaction(?int $agencyId = null): LedgerTransaction
    {
        $agencyId ??= Agency::query()->where('slug', 'asif-travels')->value('id')
            ?? Agency::factory()->create()->id;

        $agent = Agent::query()->where('agency_id', $agencyId)->first()
            ?? Agent::factory()->create(['agency_id' => $agencyId]);

        $wallet = AgentWallet::query()->firstOrCreate(
            ['agent_id' => $agent->id],
            [
                'agency_id' => $agencyId,
                'user_id' => $agent->user_id,
                'balance' => 0,
                'currency' => 'PKR',
                'status' => 'active',
            ],
        );

        $admin = $this->platformAdmin();
        $deposit = AgentDepositRequest::query()->create([
            'agency_id' => $agencyId,
            'agent_id' => $agent->id,
            'user_id' => $agent->user_id,
            'agent_wallet_id' => $wallet->id,
            'amount' => 5000,
            'currency' => 'PKR',
            'reference' => 'DEP-UI-'.uniqid(),
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);

        app(AgentWalletService::class)->approveDeposit($deposit, $admin);

        return LedgerTransaction::query()
            ->where('source_id', $deposit->id)
            ->where('transaction_type', LedgerTransactionType::AgencyDepositApproved)
            ->firstOrFail();
    }

    /**
     * @return array{0: LedgerTransaction, 1: LedgerTransaction}
     */
    protected function seedLedgerTransactionsForTwoAgencies(): array
    {
        $agencyA = Agency::factory()->create();
        $agencyB = Agency::factory()->create();

        return [
            $this->seedPostedDepositLedgerTransaction($agencyA->id),
            $this->seedPostedDepositLedgerTransaction($agencyB->id),
        ];
    }
}
