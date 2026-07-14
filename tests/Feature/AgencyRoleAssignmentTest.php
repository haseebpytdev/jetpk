<?php

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Enums\AgencyRole;
use App\Enums\UserAccountStatus;
use App\Models\Agency;
use App\Models\AgencyUser;
use App\Models\Agent;
use App\Models\User;
use App\Support\Agents\AgentPermission;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgencyRoleAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_admin_can_update_staff_agency_role(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        [$admin] = $this->platformAdmin();
        [, $agent] = $this->seedAgent();
        $staff = $this->createStaffForAgent($agent, 'role-staff@agency.test');
        $this->createAgencyMembership($staff, $agent->agency_id, AgencyRole::Viewer);

        $this->actingAs($admin)
            ->patch(route('admin.agencies.users.agency-role.update', [
                'agency' => $agent->agency_id,
                'user' => $staff,
            ]), [
                'agency_role' => AgencyRole::Accountant->value,
            ])
            ->assertRedirect()
            ->assertSessionHas('status');

        $membership = AgencyUser::query()
            ->where('agency_id', $agent->agency_id)
            ->where('user_id', $staff->id)
            ->firstOrFail();

        $this->assertSame(AgencyRole::Accountant, $membership->agency_role);
        $this->assertSame(AccountType::AgentStaff->value, $membership->role);
        $this->assertSame(AccountType::AgentStaff, $staff->fresh()->account_type);
        $this->assertSame(
            [AgentPermission::BookingsView],
            $staff->fresh()->meta['agent_permissions'] ?? [],
        );
    }

    public function test_agency_owner_can_update_other_staff_agency_role(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        [$agentUser, $agent] = $this->seedAgent();
        $staff = $this->createStaffForAgent($agent, 'other-role@agency.test');
        $this->createAgencyMembership($staff, $agent->agency_id, AgencyRole::Viewer);

        $this->actingAs($agentUser)
            ->patch(route('agent.staff.agency-role.update', $staff), [
                'agency_role' => AgencyRole::SalesAgent->value,
            ])
            ->assertRedirect(route('agent.staff.index'))
            ->assertSessionHas('status', 'agency-role-updated');

        $this->assertSame(
            AgencyRole::SalesAgent,
            AgencyUser::query()
                ->where('agency_id', $agent->agency_id)
                ->where('user_id', $staff->id)
                ->value('agency_role'),
        );
    }

    public function test_restricted_staff_cannot_update_own_agency_role(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        [, $agent] = $this->seedAgent();
        $staff = $this->createStaffForAgent($agent, 'self-role@agency.test', [
            AgentPermission::StaffManage,
            AgentPermission::BookingsView,
        ]);
        $this->createAgencyMembership($staff, $agent->agency_id, AgencyRole::Viewer);

        $this->actingAs($staff)
            ->patch(route('agent.staff.agency-role.update', $staff), [
                'agency_role' => AgencyRole::Manager->value,
            ])
            ->assertForbidden();

        $this->assertSame(
            AgencyRole::Viewer,
            AgencyUser::query()
                ->where('user_id', $staff->id)
                ->value('agency_role'),
        );
    }

    public function test_agent_owner_cannot_assign_owner_role(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        [$agentUser, $agent] = $this->seedAgent();
        $staff = $this->createStaffForAgent($agent, 'no-owner@agency.test');
        $this->createAgencyMembership($staff, $agent->agency_id, AgencyRole::Viewer);

        $this->actingAs($agentUser)
            ->patch(route('agent.staff.agency-role.update', $staff), [
                'agency_role' => AgencyRole::Owner->value,
            ])
            ->assertSessionHasErrors('agency_role');

        $this->assertSame(
            AgencyRole::Viewer,
            AgencyUser::query()
                ->where('user_id', $staff->id)
                ->value('agency_role'),
        );
    }

    public function test_invalid_agency_role_is_rejected(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        [$admin] = $this->platformAdmin();
        [, $agent] = $this->seedAgent();
        $staff = $this->createStaffForAgent($agent, 'invalid-role@agency.test');
        $this->createAgencyMembership($staff, $agent->agency_id, AgencyRole::Viewer);

        $this->actingAs($admin)
            ->patch(route('admin.agencies.users.agency-role.update', [
                'agency' => $agent->agency_id,
                'user' => $staff,
            ]), [
                'agency_role' => 'superuser',
            ])
            ->assertSessionHasErrors('agency_role');
    }

    public function test_last_owner_cannot_be_demoted_even_by_platform_admin(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        [$admin, $agency, $owner] = $this->platformAdminWithOwner();
        $this->createAgencyMembership($owner, $agency->id, AgencyRole::Owner);

        $this->actingAs($admin)
            ->patch(route('admin.agencies.users.agency-role.update', [
                'agency' => $agency,
                'user' => $owner,
            ]), [
                'agency_role' => AgencyRole::Manager->value,
            ])
            ->assertSessionHasErrors('agency_role');

        $this->assertSame(
            AgencyRole::Owner,
            AgencyUser::query()
                ->where('user_id', $owner->id)
                ->value('agency_role'),
        );
    }

    public function test_role_update_does_not_alter_agent_permissions_meta(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        [$agentUser, $agent] = $this->seedAgent();
        $permissions = [AgentPermission::BookingsView, AgentPermission::SupportManage];
        $staff = $this->createStaffForAgent($agent, 'perms-unchanged@agency.test', $permissions);
        $this->createAgencyMembership($staff, $agent->agency_id, AgencyRole::SupportStaff);

        $this->actingAs($agentUser)
            ->patch(route('agent.staff.agency-role.update', $staff), [
                'agency_role' => AgencyRole::Accountant->value,
            ])
            ->assertRedirect(route('agent.staff.index'));

        $this->assertEqualsCanonicalizing($permissions, $staff->fresh()->meta['agent_permissions'] ?? []);
    }

    protected function createAgencyMembership(User $user, int $agencyId, AgencyRole $role): AgencyUser
    {
        return AgencyUser::query()->updateOrCreate(
            ['agency_id' => $agencyId, 'user_id' => $user->id],
            [
                'role' => $user->account_type->value,
                'agency_role' => $role->value,
            ],
        );
    }

    protected function createStaffForAgent(Agent $agent, string $email, array $permissions = [AgentPermission::BookingsView]): User
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
     * @return array{0: User}
     */
    protected function platformAdmin(): array
    {
        $this->seed(OtaFoundationSeeder::class);
        $admin = User::query()->where('email', 'admin@ota.demo')->firstOrFail();
        if ($admin->account_type !== AccountType::PlatformAdmin) {
            $admin->forceFill(['account_type' => AccountType::PlatformAdmin])->save();
            $admin = $admin->fresh();
        }

        return [$admin];
    }

    /**
     * @return array{0: User, 1: Agency, 2: User}
     */
    protected function platformAdminWithOwner(): array
    {
        [$admin] = $this->platformAdmin();
        $agency = Agency::query()->firstOrFail();
        $owner = User::query()->where('email', 'agent@ota.demo')->firstOrFail();

        return [$admin, $agency, $owner];
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
