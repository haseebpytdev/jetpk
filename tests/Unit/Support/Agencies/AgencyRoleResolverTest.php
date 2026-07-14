<?php

namespace Tests\Unit\Support\Agencies;

use App\Enums\AccountType;
use App\Enums\AgencyRole;
use App\Enums\UserAccountStatus;
use App\Models\Agency;
use App\Models\AgencyUser;
use App\Models\User;
use App\Support\Agencies\AgencyRoleResolver;
use App\Support\Agents\AgentPermission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgencyRoleResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolves_agent_account_type_to_owner(): void
    {
        $agency = Agency::factory()->create();
        $user = User::factory()->create([
            'account_type' => AccountType::Agent,
            'current_agency_id' => $agency->id,
        ]);

        AgencyUser::query()->create([
            'agency_id' => $agency->id,
            'user_id' => $user->id,
            'role' => AccountType::Agent->value,
        ]);

        $this->assertSame(AgencyRole::Owner, AgencyRoleResolver::resolve($user, $agency->id));
    }

    public function test_resolves_blank_agent_staff_to_viewer(): void
    {
        $agency = Agency::factory()->create();
        $user = User::factory()->create([
            'account_type' => AccountType::AgentStaff,
            'current_agency_id' => $agency->id,
            'meta' => ['agent_permissions' => []],
        ]);

        AgencyUser::query()->create([
            'agency_id' => $agency->id,
            'user_id' => $user->id,
            'role' => AccountType::AgentStaff->value,
        ]);

        $this->assertSame(AgencyRole::Viewer, AgencyRoleResolver::resolve($user, $agency->id));
    }

    public function test_prefers_stored_agency_role_over_inference(): void
    {
        $agency = Agency::factory()->create();
        $user = User::factory()->create([
            'account_type' => AccountType::AgentStaff,
            'current_agency_id' => $agency->id,
            'meta' => ['agent_permissions' => [AgentPermission::StaffManage]],
        ]);

        AgencyUser::query()->create([
            'agency_id' => $agency->id,
            'user_id' => $user->id,
            'role' => AccountType::AgentStaff->value,
            'agency_role' => AgencyRole::Accountant->value,
        ]);

        $this->assertSame(AgencyRole::Accountant, AgencyRoleResolver::resolve($user, $agency->id));
        $this->assertTrue(AgencyRoleResolver::isStoredRole($user, $agency->id));
    }

    public function test_infers_manager_from_staff_manage_permission(): void
    {
        $role = AgencyRoleResolver::inferFromAgentStaffPermissions([AgentPermission::StaffManage]);

        $this->assertSame(AgencyRole::Manager, $role);
    }

    public function test_infers_accountant_from_ledger_manage_permission(): void
    {
        $role = AgencyRoleResolver::inferFromAgentStaffPermissions([AgentPermission::LedgerManage]);

        $this->assertSame(AgencyRole::Accountant, $role);
    }

    public function test_infers_sales_agent_from_bookings_and_travelers_permissions(): void
    {
        $role = AgencyRoleResolver::inferFromAgentStaffPermissions([
            AgentPermission::BookingsCreate,
            AgentPermission::TravelersManage,
        ]);

        $this->assertSame(AgencyRole::SalesAgent, $role);
    }

    public function test_infers_support_staff_from_support_manage_only(): void
    {
        $role = AgencyRoleResolver::inferFromAgentStaffPermissions([AgentPermission::SupportManage]);

        $this->assertSame(AgencyRole::SupportStaff, $role);
    }

    public function test_agency_user_model_casts_agency_role(): void
    {
        $agency = Agency::factory()->create();
        $user = User::factory()->create([
            'account_type' => AccountType::AgentStaff,
            'status' => UserAccountStatus::Active,
        ]);

        $membership = AgencyUser::query()->create([
            'agency_id' => $agency->id,
            'user_id' => $user->id,
            'role' => AccountType::AgentStaff->value,
            'agency_role' => AgencyRole::TicketingStaff->value,
        ]);

        $membership->refresh();

        $this->assertSame(AgencyRole::TicketingStaff, $membership->agency_role);
    }
}
