<?php

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Enums\AgencyRole;
use App\Enums\UserAccountStatus;
use App\Models\AgencyUser;
use App\Models\Agent;
use App\Models\AuditLog;
use App\Models\User;
use App\Support\Agents\AgentPermission;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgencyStaffPermissionAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_admin_can_update_agent_staff_permission_matrix(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        [$admin] = $this->platformAdmin();
        [, $agent] = $this->seedAgent();
        $staff = $this->createStaffForAgent($agent, 'admin-matrix@agency.test', [AgentPermission::BookingsView]);
        $this->createAgencyMembership($staff, $agent->agency_id, AgencyRole::Viewer);

        $this->actingAs($admin)
            ->patch(route('admin.agencies.users.agent-permissions.update', [
                'agency' => $agent->agency_id,
                'user' => $staff,
            ]), [
                'permissions' => [AgentPermission::WalletView, AgentPermission::SupportManage],
            ])
            ->assertRedirect()
            ->assertSessionHas('status', 'agent-permissions-updated');

        $this->assertEqualsCanonicalizing(
            [AgentPermission::WalletView, AgentPermission::SupportManage],
            $staff->fresh()->meta['agent_permissions'] ?? [],
        );
    }

    public function test_platform_admin_permission_update_does_not_change_account_type_or_agency_user_pivot(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        [$admin] = $this->platformAdmin();
        [, $agent] = $this->seedAgent();
        $staff = $this->createStaffForAgent($agent, 'pivot-safe@agency.test');
        $membership = $this->createAgencyMembership($staff, $agent->agency_id, AgencyRole::SalesAgent);

        $this->actingAs($admin)
            ->patch(route('admin.agencies.users.agent-permissions.update', [
                'agency' => $agent->agency_id,
                'user' => $staff,
            ]), [
                'permissions' => [AgentPermission::BookingsCreate, AgentPermission::AgencyView],
            ])
            ->assertRedirect();

        $fresh = $staff->fresh();
        $this->assertSame(AccountType::AgentStaff, $fresh->account_type);
        $membership->refresh();
        $this->assertSame(AccountType::AgentStaff->value, $membership->role);
        $this->assertSame(AgencyRole::SalesAgent, $membership->agency_role);
    }

    public function test_agency_owner_can_update_own_staff_permissions(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        [$agentUser, $agent] = $this->seedAgent();
        $staff = $this->createStaffForAgent($agent, 'owner-update@agency.test', [AgentPermission::BookingsView]);

        $this->actingAs($agentUser)
            ->patch(route('agent.staff.permissions.update', $staff), [
                'permissions' => [AgentPermission::WalletView],
            ])
            ->assertRedirect()
            ->assertSessionHas('status', 'staff-permissions-updated');

        $this->assertSame([AgentPermission::WalletView], $staff->fresh()->meta['agent_permissions']);
    }

    public function test_staff_with_staff_manage_can_update_another_staff_permissions(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        [, $agent] = $this->seedAgent();
        $manager = $this->createStaffForAgent($agent, 'manager@agency.test', [
            AgentPermission::StaffManage,
            AgentPermission::BookingsView,
        ]);
        $other = $this->createStaffForAgent($agent, 'other@agency.test', [AgentPermission::BookingsView]);

        $this->actingAs($manager)
            ->patch(route('agent.staff.permissions.update', $other), [
                'permissions' => [AgentPermission::SupportManage, AgentPermission::AgencyView],
            ])
            ->assertRedirect();

        $this->assertEqualsCanonicalizing(
            [AgentPermission::SupportManage, AgentPermission::AgencyView],
            $other->fresh()->meta['agent_permissions'] ?? [],
        );
    }

    public function test_restricted_staff_cannot_update_own_permissions(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        [, $agent] = $this->seedAgent();
        $staff = $this->createStaffForAgent($agent, 'self@agency.test', [
            AgentPermission::StaffManage,
            AgentPermission::BookingsView,
        ]);

        $this->actingAs($staff)
            ->patch(route('agent.staff.permissions.update', $staff), [
                'permissions' => [AgentPermission::WalletView],
            ])
            ->assertForbidden();

        $this->assertEqualsCanonicalizing(
            [AgentPermission::StaffManage, AgentPermission::BookingsView],
            $staff->fresh()->meta['agent_permissions'] ?? [],
        );
    }

    public function test_restricted_staff_cannot_update_another_staff_member(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        [, $agent] = $this->seedAgent();
        $viewer = $this->createStaffForAgent($agent, 'viewer@agency.test', [AgentPermission::BookingsView]);
        $other = $this->createStaffForAgent($agent, 'target@agency.test', [AgentPermission::BookingsView]);

        $this->actingAs($viewer)
            ->patch(route('agent.staff.permissions.update', $other), [
                'permissions' => [AgentPermission::WalletView],
            ])
            ->assertForbidden();
    }

    public function test_invalid_permission_is_rejected(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        [$admin] = $this->platformAdmin();
        [, $agent] = $this->seedAgent();
        $staff = $this->createStaffForAgent($agent, 'invalid-perm@agency.test');

        $this->actingAs($admin)
            ->patch(route('admin.agencies.users.agent-permissions.update', [
                'agency' => $agent->agency_id,
                'user' => $staff,
            ]), [
                'permissions' => [AgentPermission::AgencyEdit],
            ])
            ->assertSessionHasErrors('permissions.0');

        $this->assertSame([], $staff->fresh()->meta['agent_permissions'] ?? []);
    }

    public function test_template_apply_requires_confirm_template_apply(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        [$admin] = $this->platformAdmin();
        [, $agent] = $this->seedAgent();
        $staff = $this->createStaffForAgent($agent, 'no-confirm@agency.test', [AgentPermission::BookingsView]);
        $this->createAgencyMembership($staff, $agent->agency_id, AgencyRole::SalesAgent);

        $this->actingAs($admin)
            ->post(route('admin.agencies.users.agent-permissions.apply-template', [
                'agency' => $agent->agency_id,
                'user' => $staff,
            ]), [])
            ->assertSessionHasErrors('confirm_template_apply');

        $this->assertSame([AgentPermission::BookingsView], $staff->fresh()->meta['agent_permissions']);
    }

    public function test_template_apply_updates_meta_agent_permissions_only(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        [$admin] = $this->platformAdmin();
        [, $agent] = $this->seedAgent();
        $staff = $this->createStaffForAgent($agent, 'template@agency.test', [AgentPermission::BookingsView]);
        $membership = $this->createAgencyMembership($staff, $agent->agency_id, AgencyRole::SalesAgent);

        $this->actingAs($admin)
            ->post(route('admin.agencies.users.agent-permissions.apply-template', [
                'agency' => $agent->agency_id,
                'user' => $staff,
            ]), [
                'confirm_template_apply' => '1',
            ])
            ->assertRedirect()
            ->assertSessionHas('status', 'agent-permissions-template-applied');

        $this->assertEqualsCanonicalizing(
            [
                AgentPermission::BookingsView,
                AgentPermission::BookingsCreate,
                AgentPermission::TravelersManage,
                AgentPermission::AgencyView,
            ],
            $staff->fresh()->meta['agent_permissions'] ?? [],
        );
        $membership->refresh();
        $this->assertSame(AgencyRole::SalesAgent, $membership->agency_role);
        $this->assertSame(AccountType::AgentStaff->value, $membership->role);
        $this->assertSame(AccountType::AgentStaff, $staff->fresh()->account_type);
    }

    public function test_owner_template_on_agent_staff_is_blocked(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        [$admin] = $this->platformAdmin();
        [, $agent] = $this->seedAgent();
        $staff = $this->createStaffForAgent($agent, 'owner-role-staff@agency.test', [AgentPermission::BookingsView]);
        $this->createAgencyMembership($staff, $agent->agency_id, AgencyRole::Owner);

        $this->actingAs($admin)
            ->post(route('admin.agencies.users.agent-permissions.apply-template', [
                'agency' => $agent->agency_id,
                'user' => $staff,
            ]), [
                'confirm_template_apply' => '1',
            ])
            ->assertSessionHasErrors('confirm_template_apply');

        $this->assertSame([AgentPermission::BookingsView], $staff->fresh()->meta['agent_permissions']);
    }

    public function test_role_change_still_does_not_update_permissions(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        [$agentUser, $agent] = $this->seedAgent();
        $permissions = [AgentPermission::BookingsView, AgentPermission::SupportManage];
        $staff = $this->createStaffForAgent($agent, 'role-separate@agency.test', $permissions);
        $this->createAgencyMembership($staff, $agent->agency_id, AgencyRole::SupportStaff);

        $this->actingAs($agentUser)
            ->patch(route('agent.staff.agency-role.update', $staff), [
                'agency_role' => AgencyRole::Accountant->value,
            ])
            ->assertRedirect(route('agent.staff.index'));

        $this->assertEqualsCanonicalizing($permissions, $staff->fresh()->meta['agent_permissions'] ?? []);
    }

    public function test_permission_update_writes_audit_log(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        [$admin] = $this->platformAdmin();
        [, $agent] = $this->seedAgent();
        $staff = $this->createStaffForAgent($agent, 'audit@agency.test', [AgentPermission::BookingsView]);

        $this->actingAs($admin)
            ->patch(route('admin.agencies.users.agent-permissions.update', [
                'agency' => $agent->agency_id,
                'user' => $staff,
            ]), [
                'permissions' => [AgentPermission::AgencyView],
            ])
            ->assertRedirect();

        $log = AuditLog::query()
            ->where('action', 'agent_permissions.updated')
            ->where('auditable_id', $staff->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($log);
        $this->assertSame('manual', $log->properties['new_values']['source'] ?? null);
        $this->assertEqualsCanonicalizing(
            [AgentPermission::BookingsView],
            $log->properties['new_values']['old_permissions'] ?? [],
        );
    }

    public function test_template_apply_writes_audit_log_with_role_template_source(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        [$admin] = $this->platformAdmin();
        [, $agent] = $this->seedAgent();
        $staff = $this->createStaffForAgent($agent, 'audit-template@agency.test', [AgentPermission::BookingsView]);
        $this->createAgencyMembership($staff, $agent->agency_id, AgencyRole::SalesAgent);

        $this->actingAs($admin)
            ->post(route('admin.agencies.users.agent-permissions.apply-template', [
                'agency' => $agent->agency_id,
                'user' => $staff,
            ]), [
                'confirm_template_apply' => '1',
            ])
            ->assertRedirect();

        $log = AuditLog::query()
            ->where('action', 'agent_permissions.updated')
            ->where('auditable_id', $staff->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($log);
        $this->assertSame('role_template', $log->properties['new_values']['source'] ?? null);
        $this->assertSame(AgencyRole::SalesAgent->value, $log->properties['new_values']['agency_role'] ?? null);
    }

    public function test_admin_user_show_displays_access_clarification_copy(): void
    {
        [$admin] = $this->platformAdmin();
        [, $agent] = $this->seedAgent();
        $staff = $this->createStaffForAgent($agent, 'clarify@agency.test', [AgentPermission::BookingsView]);
        $this->createAgencyMembership($staff, $agent->agency_id, AgencyRole::Viewer);

        $this->actingAs($admin)
            ->get(route('admin.users.show', $staff))
            ->assertOk()
            ->assertSee('data-testid="agent-staff-access-clarification"', false)
            ->assertSee('Agency Role</strong> is a business label', false)
            ->assertSee('Permission Matrix</strong> controls actual portal access', false)
            ->assertSee('Apply Template</strong> copies suggested permissions', false);
    }

    public function test_admin_user_show_displays_owner_label_warning_for_staff_with_owner_role(): void
    {
        [$admin] = $this->platformAdmin();
        [, $agent] = $this->seedAgent();
        $staff = $this->createStaffForAgent($agent, 'owner-label@agency.test', [AgentPermission::BookingsView]);
        $this->createAgencyMembership($staff, $agent->agency_id, AgencyRole::Owner);

        $this->actingAs($admin)
            ->get(route('admin.users.show', $staff))
            ->assertOk()
            ->assertSee('data-testid="agent-staff-owner-label-warning"', false)
            ->assertSee('still an Agency Staff account', false)
            ->assertDontSee('data-testid="admin-user-agent-permissions-apply-template"', false);
    }

    public function test_admin_user_show_displays_recent_permission_changes_panel_when_audit_exists(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        [$admin] = $this->platformAdmin();
        [, $agent] = $this->seedAgent();
        $staff = $this->createStaffForAgent($agent, 'audit-panel@agency.test', [AgentPermission::BookingsView]);

        $this->actingAs($admin)
            ->patch(route('admin.agencies.users.agent-permissions.update', [
                'agency' => $agent->agency_id,
                'user' => $staff,
            ]), [
                'permissions' => [AgentPermission::AgencyView, AgentPermission::WalletView],
            ])
            ->assertRedirect();

        $this->actingAs($admin)
            ->get(route('admin.users.show', $staff))
            ->assertOk()
            ->assertSee('data-testid="recent-permission-changes-panel"', false)
            ->assertSee('Recent permission changes', false)
            ->assertSee('Manual', false)
            ->assertSee('1 → 2', false);
    }

    public function test_agent_staff_edit_displays_access_clarification_copy(): void
    {
        [$agentUser, $agent] = $this->seedAgent();
        $staff = $this->createStaffForAgent($agent, 'agent-edit-clarify@agency.test', [
            AgentPermission::BookingsView,
            AgentPermission::StaffManage,
        ]);
        $this->createAgencyMembership($staff, $agent->agency_id, AgencyRole::Viewer);

        $this->actingAs($agentUser)
            ->get(route('agent.staff.edit', $staff))
            ->assertOk()
            ->assertSee('data-testid="agent-staff-access-clarification"', false)
            ->assertSee('Permission Matrix</strong> controls actual portal access', false);
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
