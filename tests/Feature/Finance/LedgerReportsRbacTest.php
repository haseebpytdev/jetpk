<?php

namespace Tests\Feature\Finance;

use App\Enums\AccountType;
use App\Enums\UserAccountStatus;
use App\Models\Agency;
use App\Models\Agent;
use App\Models\AgentWallet;
use App\Models\AgentWalletTransaction;
use App\Models\User;
use App\Support\Agents\AgentPermission;
use App\Support\Staff\StaffPermission;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LedgerReportsRbacTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_admin_can_access_master_ledger_and_reports(): void
    {
        $admin = $this->platformAdmin();

        $this->actingAs($admin)->get(route('admin.ledger.index'))->assertOk()
            ->assertSee('data-testid="master-ledger-table"', false);
        $this->actingAs($admin)->get(route('admin.reports'))->assertOk();
    }

    public function test_platform_admin_ledger_lists_multiple_agencies(): void
    {
        $admin = $this->platformAdmin();
        $agencyA = Agency::factory()->create(['name' => 'Ledger Agency A']);
        $agencyB = Agency::factory()->create(['name' => 'Ledger Agency B']);
        $this->seedTransactionForAgency($agencyA);
        $this->seedTransactionForAgency($agencyB);

        $response = $this->actingAs($admin)->get(route('admin.ledger.index'));
        $response->assertOk()
            ->assertSee('data-testid="ledger-row-', false);
    }

    public function test_platform_admin_can_filter_ledger_by_agency(): void
    {
        $admin = $this->platformAdmin();
        $agencyA = Agency::factory()->create(['name' => 'Filter Agency A']);
        $agencyB = Agency::factory()->create(['name' => 'Filter Agency B']);
        $txA = $this->seedTransactionForAgency($agencyA);
        $txB = $this->seedTransactionForAgency($agencyB);

        $this->actingAs($admin)->get(route('admin.ledger.index', ['agency_id' => $agencyA->id]))
            ->assertOk()
            ->assertSee('data-testid="ledger-row-'.$txA->id.'"', false)
            ->assertDontSee('data-testid="ledger-row-'.$txB->id.'"', false);
    }

    public function test_staff_with_ledger_view_can_access_staff_ledger(): void
    {
        $staff = $this->staffWithPermissions([StaffPermission::LedgerView]);

        $this->assertTrue($staff->hasStaffPermission(StaffPermission::LedgerView));
        $this->assertTrue($staff->can('viewAny', AgentWalletTransaction::class));

        $this->actingAs($staff)->get(route('staff.ledger.index'))->assertOk();
    }

    public function test_staff_without_ledger_view_cannot_access_staff_ledger(): void
    {
        $staff = $this->staffWithPermissions([StaffPermission::BookingsView]);

        $this->actingAs($staff)->get(route('staff.ledger.index'))->assertForbidden();
    }

    public function test_staff_with_reports_view_can_access_staff_reports(): void
    {
        $staff = $this->staffWithPermissions([StaffPermission::ReportsView]);

        $this->actingAs($staff)->get(route('staff.reports.index'))->assertOk();
    }

    public function test_staff_without_reports_view_cannot_access_staff_reports(): void
    {
        $staff = $this->staffWithPermissions([StaffPermission::BookingsView]);

        $this->actingAs($staff)->get(route('staff.reports.index'))->assertForbidden();
    }

    public function test_staff_without_reports_export_cannot_export(): void
    {
        $staff = $this->staffWithPermissions([StaffPermission::ReportsView]);

        $this->actingAs($staff)->get(route('staff.reports.export', 'sales'))->assertForbidden();
    }

    public function test_agent_owner_can_view_agency_ledger(): void
    {
        [$agentUser, $agent] = $this->seedAgent();
        $this->seedWalletTransaction($agent);

        $this->actingAs($agentUser)->get(route('agent.ledger.index'))->assertOk()
            ->assertSee('My Ledger', false);
    }

    public function test_agent_staff_without_ledger_permission_cannot_view_ledger(): void
    {
        [, $agent] = $this->seedAgent();
        $staff = $this->createAgentStaff($agent, 'no-ledger@test', [AgentPermission::BookingsView]);

        $this->actingAs($staff)->get(route('agent.ledger.index'))->assertForbidden();
    }

    public function test_agent_staff_with_ledger_view_can_view_ledger(): void
    {
        [, $agent] = $this->seedAgent();
        $this->seedWalletTransaction($agent);
        $staff = $this->createAgentStaff($agent, 'ledger-ok@test', [AgentPermission::LedgerView]);

        $this->actingAs($staff)->get(route('agent.ledger.index'))->assertOk();
    }

    public function test_agent_owner_can_view_agency_reports(): void
    {
        [$agentUser] = $this->seedAgent();

        $this->actingAs($agentUser)->get(route('agent.reports.index'))->assertOk()
            ->assertSee('Agency Reports', false);
    }

    public function test_agent_staff_without_reports_cannot_view_reports(): void
    {
        [, $agent] = $this->seedAgent();
        $staff = $this->createAgentStaff($agent, 'no-reports@test', [AgentPermission::BookingsView]);

        $this->actingAs($staff)->get(route('agent.reports.index'))->assertForbidden();
    }

    public function test_agent_cannot_access_admin_master_ledger(): void
    {
        [$agentUser] = $this->seedAgent();

        $this->actingAs($agentUser)->get(route('admin.ledger.index'))->assertForbidden();
    }

    public function test_customer_cannot_access_admin_or_agent_ledger(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $customer = User::query()->where('email', 'customer@ota.demo')->firstOrFail();

        $this->actingAs($customer)->get(route('admin.ledger.index'))->assertForbidden();
        $this->actingAs($customer)->get(route('agent.ledger.index'))->assertForbidden();
    }

    public function test_ledger_row_renders_actor_identifier(): void
    {
        $admin = $this->platformAdmin();
        $agency = Agency::factory()->create();
        $agent = Agent::factory()->create(['agency_id' => $agency->id]);
        $tx = $this->seedWalletTransaction($agent, $admin);

        $this->actingAs($admin)->get(route('admin.ledger.index'))
            ->assertOk()
            ->assertSee('data-testid="ledger-actor-'.$tx->id.'"', false)
            ->assertSee('ADM-', false);
    }

    public function test_invalid_ledger_status_filter_does_not_crash(): void
    {
        $admin = $this->platformAdmin();

        $this->actingAs($admin)->get(route('admin.ledger.index', ['status' => 'not-a-real-status']))
            ->assertOk()
            ->assertSee('data-testid="master-ledger-empty"', false);
    }

    protected function platformAdmin(): User
    {
        $this->seed(OtaFoundationSeeder::class);
        $admin = User::query()->where('email', 'admin@ota.demo')->firstOrFail();
        $admin->forceFill(['account_type' => AccountType::PlatformAdmin])->save();

        return $admin;
    }

    protected function staffWithPermissions(array $permissions): User
    {
        $this->seed(OtaFoundationSeeder::class);
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
        $this->seed(OtaFoundationSeeder::class);
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

    protected function seedWalletTransaction(Agent $agent, ?User $creator = null): AgentWalletTransaction
    {
        $wallet = AgentWallet::query()->firstOrCreate(
            ['agent_id' => $agent->id],
            [
                'agency_id' => $agent->agency_id,
                'user_id' => $agent->user_id,
                'balance' => 100,
                'currency' => 'PKR',
                'status' => 'active',
            ],
        );

        return AgentWalletTransaction::query()->create([
            'agency_id' => $agent->agency_id,
            'agent_id' => $agent->id,
            'user_id' => $agent->user_id,
            'agent_wallet_id' => $wallet->id,
            'type' => 'deposit_approved',
            'amount' => 50,
            'balance_before' => 50,
            'balance_after' => 100,
            'status' => 'posted',
            'reference' => 'TEST-REF-'.$agent->id,
            'description' => 'Test deposit',
            'created_by' => $creator?->id,
        ]);
    }

    protected function seedTransactionForAgency(Agency $agency): AgentWalletTransaction
    {
        $user = User::factory()->create([
            'account_type' => AccountType::Agent,
            'current_agency_id' => $agency->id,
        ]);
        $agent = Agent::factory()->create([
            'agency_id' => $agency->id,
            'user_id' => $user->id,
        ]);

        return $this->seedWalletTransaction($agent);
    }
}
