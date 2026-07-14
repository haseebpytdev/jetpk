<?php

namespace Tests\Feature\Agent;

use App\Enums\AccountType;
use App\Enums\UserAccountStatus;
use App\Models\Agent;
use App\Models\Booking;
use App\Models\User;
use App\Support\Agents\AgentPermission;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgentStaffPermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_agent_admin_can_access_core_agent_portal_routes(): void
    {
        [$agentUser, $agent] = $this->seedAgent();

        $booking = Booking::factory()->create([
            'agency_id' => $agent->agency_id,
            'agent_id' => $agent->id,
        ]);

        $this->actingAs($agentUser)->get(route('agent.dashboard'))->assertOk();
        $this->actingAs($agentUser)->get(route('agent.bookings.index'))->assertOk();
        $this->actingAs($agentUser)->get(route('agent.bookings.create'))->assertOk();
        $this->actingAs($agentUser)->get(route('agent.bookings.show', $booking))->assertOk();
        $this->actingAs($agentUser)->get(route('agent.wallet.show'))->assertOk();
        $this->actingAs($agentUser)->get(route('agent.ledger.index'))->assertOk();
        $this->actingAs($agentUser)->get(route('agent.deposits.index'))->assertOk();
        $this->actingAs($agentUser)->get(route('agent.deposits.create'))->assertOk();
        $this->actingAs($agentUser)->get(route('agent.commissions.index'))->assertOk();
        $this->actingAs($agentUser)->get(route('agent.travelers.index'))->assertOk();
        $this->actingAs($agentUser)->get(route('agent.support.tickets.index'))->assertOk();
        $this->actingAs($agentUser)->get(route('agent.agency.show'))->assertOk();
        $this->actingAs($agentUser)->get(route('agent.agency.edit'))->assertOk();
        $this->actingAs($agentUser)->get(route('agent.staff.index'))->assertOk();
        $this->actingAs($agentUser)->get(route('profile.edit'))->assertOk();
    }

    public function test_agent_staff_without_permissions_can_access_dashboard_and_profile_only(): void
    {
        [, $agent] = $this->seedAgent();
        $staff = $this->createStaffForAgent($agent, 'bare@agency.test', []);

        $this->actingAs($staff)->get(route('agent.dashboard'))->assertOk();
        $this->actingAs($staff)->get(route('profile.edit'))->assertOk();

        $this->actingAs($staff)->get(route('agent.bookings.index'))->assertForbidden();
        $this->actingAs($staff)->get(route('agent.bookings.create'))->assertForbidden();
        $this->actingAs($staff)->get(route('agent.wallet.show'))->assertForbidden();
        $this->actingAs($staff)->get(route('agent.deposits.index'))->assertForbidden();
        $this->actingAs($staff)->get(route('agent.travelers.index'))->assertForbidden();
        $this->actingAs($staff)->get(route('agent.support.tickets.index'))->assertForbidden();
        $this->actingAs($staff)->get(route('agent.agency.show'))->assertForbidden();
        $this->actingAs($staff)->get(route('agent.agency.edit'))->assertForbidden();
        $this->actingAs($staff)->get(route('agent.staff.index'))->assertForbidden();
        $this->actingAs($staff)->get(route('agent.commissions.index'))->assertForbidden();
    }

    public function test_agent_staff_with_bookings_view_can_access_index_and_show_not_create(): void
    {
        [, $agent] = $this->seedAgent();
        $staff = $this->createStaffForAgent($agent, 'viewer@agency.test', [AgentPermission::BookingsView]);

        $booking = Booking::factory()->create([
            'agency_id' => $agent->agency_id,
            'agent_id' => $agent->id,
        ]);

        $this->actingAs($staff)->get(route('agent.bookings.index'))->assertOk();
        $this->actingAs($staff)->get(route('agent.bookings.show', $booking))->assertOk();
        $this->actingAs($staff)->get(route('agent.bookings.create'))->assertForbidden();
        $this->actingAs($staff)->post(route('agent.bookings.store'), [])->assertForbidden();
    }

    public function test_agent_staff_with_bookings_create_can_open_create_flow(): void
    {
        [, $agent] = $this->seedAgent();
        $staff = $this->createStaffForAgent($agent, 'creator@agency.test', [
            AgentPermission::BookingsCreate,
            AgentPermission::BookingsView,
        ]);

        $this->actingAs($staff)->get(route('agent.bookings.create'))->assertOk();
    }

    public function test_agent_staff_with_wallet_view_can_access_wallet(): void
    {
        [, $agent] = $this->seedAgent();
        $staff = $this->createStaffForAgent($agent, 'wallet@agency.test', [AgentPermission::WalletView]);

        $this->actingAs($staff)->get(route('agent.wallet.show'))->assertOk();
        $this->actingAs($staff)->get(route('agent.deposits.index'))->assertOk();
        $this->actingAs($staff)->get(route('agent.deposits.create'))->assertForbidden();
        $this->actingAs($staff)->get(route('agent.ledger.index'))->assertForbidden();
    }

    public function test_agent_staff_with_payments_upload_can_create_deposit(): void
    {
        [, $agent] = $this->seedAgent();
        $staff = $this->createStaffForAgent($agent, 'uploader@agency.test', [
            AgentPermission::WalletView,
            AgentPermission::PaymentsUpload,
        ]);

        $this->actingAs($staff)->get(route('agent.deposits.create'))->assertOk();
    }

    public function test_agent_staff_without_bookings_create_does_not_see_create_button_on_index(): void
    {
        [, $agent] = $this->seedAgent();
        $staff = $this->createStaffForAgent($agent, 'viewer-only@agency.test', [AgentPermission::BookingsView]);

        $this->actingAs($staff)->get(route('agent.bookings.index'))
            ->assertOk()
            ->assertDontSee('data-testid="agent-bookings-create-link"', false)
            ->assertDontSee('Create booking request', false);
    }

    public function test_agent_staff_with_bookings_create_sees_create_button_on_index(): void
    {
        [, $agent] = $this->seedAgent();
        $staff = $this->createStaffForAgent($agent, 'creator-ui@agency.test', [
            AgentPermission::BookingsView,
            AgentPermission::BookingsCreate,
        ]);

        $this->actingAs($staff)->get(route('agent.bookings.index'))
            ->assertOk()
            ->assertSee('data-testid="agent-bookings-create-link"', false);
    }

    public function test_agent_staff_without_agency_edit_does_not_see_edit_agency_button(): void
    {
        [, $agent] = $this->seedAgent();
        $staff = $this->createStaffForAgent($agent, 'agency-read@agency.test', [AgentPermission::AgencyView]);

        $this->actingAs($staff)->get(route('agent.agency.show'))
            ->assertOk()
            ->assertDontSee('data-testid="agent-agency-edit-link"', false);
    }

    public function test_agent_staff_without_wallet_view_gets_forbidden_on_wallet(): void
    {
        [, $agent] = $this->seedAgent();
        $staff = $this->createStaffForAgent($agent, 'nowallet@agency.test', [AgentPermission::BookingsView]);

        $this->actingAs($staff)->get(route('agent.wallet.show'))->assertForbidden();
    }

    public function test_agent_staff_with_agency_view_can_see_agency_details(): void
    {
        [, $agent] = $this->seedAgent();
        $staff = $this->createStaffForAgent($agent, 'agency-view@agency.test', [AgentPermission::AgencyView]);

        $this->actingAs($staff)->get(route('agent.agency.show'))
            ->assertOk()
            ->assertSee('data-testid="agent-agency-details"', false);
    }

    public function test_agent_staff_without_agency_edit_cannot_open_agency_edit(): void
    {
        [, $agent] = $this->seedAgent();
        $staff = $this->createStaffForAgent($agent, 'no-edit@agency.test', [AgentPermission::AgencyView]);

        $this->actingAs($staff)->get(route('agent.agency.edit'))->assertForbidden();
        $this->actingAs($staff)->patch(route('agent.agency.update'), [
            'agency_name' => 'Blocked',
        ])->assertForbidden();
    }

    public function test_agent_staff_with_staff_manage_can_access_staff_management(): void
    {
        [, $agent] = $this->seedAgent();
        $staff = $this->createStaffForAgent($agent, 'manager@agency.test', [AgentPermission::StaffManage]);

        $this->actingAs($staff)->get(route('agent.staff.index'))->assertOk();
        $this->actingAs($staff)->get(route('agent.staff.create'))->assertOk();
    }

    public function test_agent_staff_cannot_access_admin_or_staff_dashboards(): void
    {
        [, $agent] = $this->seedAgent();
        $staff = $this->createStaffForAgent($agent, 'portal-only@agency.test', [
            AgentPermission::BookingsView,
        ]);

        $this->actingAs($staff)->get(route('admin.dashboard'))->assertForbidden();
        $this->actingAs($staff)->get(route('staff.dashboard'))->assertForbidden();
    }

    public function test_customer_cannot_access_agent_portal_routes(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $customer = User::query()->where('account_type', AccountType::Customer)->firstOrFail();

        $this->actingAs($customer)->get(route('agent.dashboard'))->assertForbidden();
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
