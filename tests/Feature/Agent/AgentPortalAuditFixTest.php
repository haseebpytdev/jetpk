<?php

namespace Tests\Feature\Agent;

use App\Enums\AccountType;
use App\Enums\UserAccountStatus;
use App\Models\Agent;
use App\Models\Booking;
use App\Models\SupportTicket;
use App\Models\User;
use App\Support\Agents\AgentPermission;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgentPortalAuditFixTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_permission_form_does_not_include_profile_manage(): void
    {
        [, $agent] = $this->seedAgent();
        $admin = User::query()->where('email', 'agent@ota.demo')->firstOrFail();

        $this->actingAs($admin)->get(route('agent.staff.create'))
            ->assertOk()
            ->assertDontSee('agent.profile.manage', false)
            ->assertDontSee('Manage profile settings', false);
    }

    public function test_staff_without_bookings_create_does_not_see_create_booking_on_dashboard(): void
    {
        [, $agent] = $this->seedAgent();
        $staff = $this->createStaffForAgent($agent, 'dash-no-create@test', [AgentPermission::BookingsView]);

        $this->actingAs($staff)->get(route('agent.dashboard'))
            ->assertOk()
            ->assertDontSee('data-testid="agent-dashboard-deposit-quick"', false)
            ->assertDontSee('New booking', false);
    }

    public function test_staff_with_bookings_create_sees_create_booking_on_dashboard(): void
    {
        [, $agent] = $this->seedAgent();
        $staff = $this->createStaffForAgent($agent, 'dash-create@test', [
            AgentPermission::BookingsView,
            AgentPermission::BookingsCreate,
        ]);

        $this->actingAs($staff)->get(route('agent.dashboard'))
            ->assertOk()
            ->assertSee('New booking', false);
    }

    public function test_staff_without_wallet_view_does_not_see_wallet_kpis_or_finance_summary(): void
    {
        [, $agent] = $this->seedAgent();
        $staff = $this->createStaffForAgent($agent, 'dash-nowallet@test', [AgentPermission::BookingsView]);

        $this->actingAs($staff)->get(route('agent.dashboard'))
            ->assertOk()
            ->assertDontSee('Wallet balance', false)
            ->assertDontSee('data-testid="agent-finance-summary"', false)
            ->assertDontSee('data-testid="agent-dashboard-wallet-quick"', false);
    }

    public function test_staff_without_ledger_view_does_not_see_ledger_link_on_dashboard(): void
    {
        [, $agent] = $this->seedAgent();
        $staff = $this->createStaffForAgent($agent, 'dash-no-ledger@test', [AgentPermission::WalletView]);

        $this->actingAs($staff)->get(route('agent.dashboard'))
            ->assertOk()
            ->assertDontSee('data-testid="agent-dashboard-ledger-quick"', false)
            ->assertDontSee('data-testid="agent-dashboard-view-ledger"', false);
    }

    public function test_staff_without_payments_upload_does_not_see_deposit_actions(): void
    {
        [, $agent] = $this->seedAgent();
        $staff = $this->createStaffForAgent($agent, 'dash-no-deposit@test', [AgentPermission::WalletView]);

        $this->actingAs($staff)->get(route('agent.dashboard'))
            ->assertOk()
            ->assertDontSee('data-testid="agent-dashboard-deposit-quick"', false)
            ->assertDontSee('data-testid="agent-dashboard-request-deposit"', false);

        $this->actingAs($staff)->get(route('agent.deposits.index'))
            ->assertOk()
            ->assertDontSee('data-testid="agent-deposits-create-link"', false);
    }

    public function test_staff_without_agency_edit_does_not_see_agency_edit_button(): void
    {
        [, $agent] = $this->seedAgent();
        $staff = $this->createStaffForAgent($agent, 'dash-agency-view@test', [AgentPermission::AgencyView]);

        $this->actingAs($staff)->get(route('agent.agency.show'))
            ->assertOk()
            ->assertDontSee('data-testid="agent-agency-edit-link"', false);
    }

    public function test_staff_without_staff_manage_does_not_see_staff_actions(): void
    {
        [, $agent] = $this->seedAgent();
        $staff = $this->createStaffForAgent($agent, 'dash-no-staff@test', [AgentPermission::BookingsView]);

        $this->actingAs($staff)->get(route('agent.staff.index'))->assertForbidden();
    }

    public function test_staff_without_travelers_manage_does_not_see_traveler_actions(): void
    {
        [, $agent] = $this->seedAgent();
        $staff = $this->createStaffForAgent($agent, 'dash-no-travelers@test', [AgentPermission::BookingsView]);

        $this->actingAs($staff)->get(route('agent.travelers.index'))->assertForbidden();
    }

    public function test_staff_without_support_manage_does_not_see_support_create(): void
    {
        [, $agent] = $this->seedAgent();
        $staff = $this->createStaffForAgent($agent, 'dash-no-support@test', [AgentPermission::BookingsView]);

        $this->actingAs($staff)->get(route('agent.support.tickets.index'))->assertForbidden();
    }

    public function test_wallet_and_ledger_render_with_no_transactions(): void
    {
        [$agentUser, $agent] = $this->seedAgent();

        $this->actingAs($agentUser)->get(route('agent.wallet.show'))
            ->assertOk()
            ->assertSee('No transactions yet', false);

        $this->actingAs($agentUser)->get(route('agent.ledger.index'))
            ->assertOk()
            ->assertSee('No ledger entries', false);
    }

    public function test_agent_admin_can_see_staff_created_support_ticket(): void
    {
        [, $agent] = $this->seedAgent();
        $admin = User::query()->where('email', 'agent@ota.demo')->firstOrFail();
        $staff = $this->createStaffForAgent($agent, 'support-staff@test', [AgentPermission::SupportManage]);

        $ticket = SupportTicket::query()->create([
            'agency_id' => $agent->agency_id,
            'created_by_user_id' => $staff->id,
            'subject' => 'Staff ticket for admin visibility',
            'category' => 'other',
            'priority' => 'normal',
            'status' => 'open',
        ]);

        $this->actingAs($admin)->get(route('agent.support.tickets.index'))
            ->assertOk()
            ->assertSee('Staff ticket for admin visibility', false);

        $this->actingAs($admin)->get(route('agent.support.tickets.show', $ticket))
            ->assertOk();
    }

    public function test_agent_staff_cannot_view_another_agents_booking(): void
    {
        $this->seed(OtaFoundationSeeder::class);

        $agentA = Agent::query()->whereHas('user', fn ($q) => $q->where('email', 'agent@ota.demo'))->firstOrFail();
        $agentBUser = User::query()->where('email', 'agent2@ota.demo')->first();
        if ($agentBUser === null) {
            $this->markTestSkipped('Second demo agent not seeded.');
        }
        $agentB = Agent::query()->where('user_id', $agentBUser->id)->firstOrFail();

        $bookingB = Booking::factory()->create([
            'agency_id' => $agentB->agency_id,
            'agent_id' => $agentB->id,
        ]);

        $staffA = $this->createStaffForAgent($agentA, 'cross-agent@test', [AgentPermission::BookingsView]);

        $this->actingAs($staffA)->get(route('agent.bookings.show', $bookingB))->assertForbidden();
    }

    public function test_agent_admin_still_sees_full_dashboard_actions(): void
    {
        [$agentUser] = $this->seedAgent();

        $this->actingAs($agentUser)->get(route('agent.dashboard'))
            ->assertOk()
            ->assertSee('Wallet balance', false)
            ->assertSee('Commission earned', false)
            ->assertSee('data-testid="agent-dashboard-wallet-quick"', false);
    }

    protected function createStaffForAgent(Agent $agent, string $email, array $permissions = []): User
    {
        return User::query()->create([
            'name' => 'Staff User',
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
}
