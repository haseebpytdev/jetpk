<?php

namespace Tests\Feature\Rbac;

use App\Enums\AccountType;
use App\Models\Agency;
use App\Models\Role;
use App\Models\User;
use App\Services\Rbac\RbacInstallService;
use App\Services\Rbac\RbacWriteService;
use App\Support\Rbac\RbacGuardException;
use App\Support\Staff\StaffPermission;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\Support\PlatformAdminTestHelpers;
use Tests\TestCase;

class RbacPersistenceTest extends TestCase
{
    use PlatformAdminTestHelpers;
    use RefreshDatabase;

    public function test_schema_creates_rbac_tables_and_unique_indexes(): void
    {
        $this->assertTrue(Schema::hasTable('roles'));
        $this->assertTrue(Schema::hasTable('role_permissions'));
        $this->assertTrue(Schema::hasTable('role_user'));
        $this->assertTrue(Schema::hasColumn('roles', 'scope_key'));
    }

    public function test_install_seeds_system_roles_and_account_type_mapping_without_staff_drift(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $staff = User::query()->where('email', 'staff@ota.demo')->firstOrFail();
        $staff->forceFill([
            'meta' => ['staff_permissions' => [StaffPermission::BookingsView, StaffPermission::SupportView]],
        ])->save();
        $before = $staff->fresh()->staffPermissions();

        $result = app(RbacInstallService::class)->seedAndBackfill();

        $this->assertSame(0, $result['drift']);
        $this->assertSame(6, Role::query()->where('is_system', true)->count());
        $this->assertTrue(Role::query()->where('slug', 'platform_admin')->where('is_protected', true)->exists());
        $this->assertSame($before, $staff->fresh()->staffPermissions());
        $this->assertTrue($staff->fresh()->rbacRoles()->where('slug', 'staff')->exists());
    }

    public function test_platform_roles_cannot_share_slug_and_agencies_can(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        app(RbacInstallService::class)->seedAndBackfill();
        $agencyA = Agency::query()->firstOrFail();
        $agencyB = Agency::factory()->create();
        $admin = $this->platformAdmin();
        $writes = app(RbacWriteService::class);

        $writes->createCustomRole($admin, 'Ops', 'ops_desk', (int) $agencyA->id, ['dashboard.view']);
        $writes->createCustomRole($admin, 'Ops', 'ops_desk', (int) $agencyB->id, ['dashboard.view']);

        $this->assertSame(2, Role::query()->where('slug', 'ops_desk')->count());

        $this->expectException(\Illuminate\Database\QueryException::class);
        Role::query()->create([
            'agency_id' => null,
            'name' => 'Dup',
            'slug' => 'platform_admin',
            'is_system' => true,
            'is_protected' => true,
        ]);
    }

    public function test_custom_role_crud_clone_permissions_and_assignment(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        app(RbacInstallService::class)->seedAndBackfill();
        $admin = $this->platformAdmin();
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $staff = User::query()->where('email', 'staff@ota.demo')->firstOrFail();

        $this->actingAs($admin)->postJson('/api/dashboard/roles', [
            'name' => 'QA Ops',
            'slug' => 'qa_ops',
            'agency_id' => $agency->id,
            'permission_keys' => ['dashboard.view', 'bookings.view'],
        ])->assertOk()->assertJsonPath('data.name', 'QA Ops');

        $role = Role::query()->where('slug', 'qa_ops')->firstOrFail();

        $this->actingAs($admin)->patchJson('/api/dashboard/roles/'.$role->id, [
            'name' => 'QA Ops Edited',
            'permission_keys' => ['dashboard.view', 'reports.view'],
        ])->assertOk()->assertJsonPath('data.name', 'QA Ops Edited');

        $this->actingAs($admin)->postJson('/api/dashboard/roles/'.$role->id.'/clone', [
            'name' => 'QA Ops Clone',
            'slug' => 'qa_ops_clone',
            'agency_id' => $agency->id,
        ])->assertOk()->assertJsonPath('data.key', 'qa_ops_clone');

        $this->actingAs($admin)->postJson('/api/dashboard/roles/'.$role->id.'/assign', [
            'user_id' => $staff->id,
        ])->assertOk();
        $this->assertTrue($staff->fresh()->rbacRoles()->where('roles.id', $role->id)->exists());

        $this->actingAs($admin)->postJson('/api/dashboard/roles/'.$role->id.'/unassign', [
            'user_id' => $staff->id,
        ])->assertOk();
        $this->assertFalse($staff->fresh()->rbacRoles()->where('roles.id', $role->id)->exists());

        $this->actingAs($admin)->deleteJson('/api/dashboard/roles/'.$role->id)->assertOk();
        $this->assertNull(Role::query()->find($role->id));
    }

    public function test_unknown_permission_is_rejected(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        app(RbacInstallService::class)->seedAndBackfill();
        $admin = $this->platformAdmin();
        $agency = Agency::query()->firstOrFail();

        $this->actingAs($admin)->postJson('/api/dashboard/roles', [
            'name' => 'Bad',
            'agency_id' => $agency->id,
            'permission_keys' => ['not.a.real.permission'],
        ])->assertStatus(422);
    }

    public function test_protected_system_role_cannot_be_deleted_or_unsafely_edited(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        app(RbacInstallService::class)->seedAndBackfill();
        $admin = $this->platformAdmin();
        $role = Role::query()->where('slug', 'platform_admin')->firstOrFail();

        $this->actingAs($admin)->deleteJson('/api/dashboard/roles/'.$role->id)->assertForbidden();
        $this->actingAs($admin)->patchJson('/api/dashboard/roles/'.$role->id, [
            'name' => 'Hacked',
        ])->assertForbidden();
    }

    public function test_last_admin_and_self_lockout_guards(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        app(RbacInstallService::class)->seedAndBackfill();
        $admin = $this->platformAdmin();
        $role = Role::query()->where('slug', 'platform_admin')->firstOrFail();
        $writes = app(RbacWriteService::class);

        try {
            $writes->unassignUser($admin, $role, $admin);
            $this->fail('Expected last-admin or self-lockout');
        } catch (RbacGuardException $e) {
            $this->assertContains($e->codeKey, ['rbac_last_admin', 'rbac_self_lockout']);
        }

        User::factory()->create([
            'account_type' => AccountType::PlatformAdmin,
            'current_agency_id' => $admin->current_agency_id,
        ]);

        try {
            $writes->unassignUser($admin, $role, $admin);
            $this->fail('Expected self-lockout');
        } catch (RbacGuardException $e) {
            $this->assertSame('rbac_self_lockout', $e->codeKey);
        }
    }

    public function test_staff_agent_and_customer_cannot_manage_rbac(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        app(RbacInstallService::class)->seedAndBackfill();
        $agency = Agency::query()->firstOrFail();
        $staff = User::query()->where('email', 'staff@ota.demo')->firstOrFail();
        $agent = User::query()->where('email', 'agent@ota.demo')->firstOrFail();
        $customer = User::query()->where('email', 'customer@ota.demo')->firstOrFail();

        $payload = ['name' => 'X', 'agency_id' => $agency->id, 'permission_keys' => ['dashboard.view']];
        $this->actingAs($staff)->postJson('/api/dashboard/roles', $payload)->assertForbidden();
        $this->actingAs($agent)->postJson('/api/dashboard/roles', $payload)->assertForbidden();
        $this->actingAs($customer)->postJson('/api/dashboard/roles', $payload)->assertForbidden();
    }

    public function test_agency_role_cannot_assign_outside_agency(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        app(RbacInstallService::class)->seedAndBackfill();
        $admin = $this->platformAdmin();
        $agencyA = Agency::query()->firstOrFail();
        $agencyB = Agency::factory()->create();
        $writes = app(RbacWriteService::class);
        $role = $writes->createCustomRole($admin, 'Local', 'local_ops', (int) $agencyA->id, ['dashboard.view']);
        $foreign = User::factory()->create([
            'account_type' => AccountType::Staff,
            'current_agency_id' => $agencyB->id,
        ]);

        $this->expectException(RbacGuardException::class);
        $writes->assignUser($admin, $role, $foreign);
    }

    public function test_dashboard_roles_index_lists_seeded_roles(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        app(RbacInstallService::class)->seedAndBackfill();
        $admin = $this->platformAdmin();

        $this->actingAs($admin)->getJson('/api/dashboard/roles')->assertOk()
            ->assertJsonPath('data.summary.protectedSystemRoles', 6);
    }
}
