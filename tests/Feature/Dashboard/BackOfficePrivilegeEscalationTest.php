<?php

namespace Tests\Feature\Dashboard;

use App\Enums\AccountType;
use App\Models\User;
use App\Support\Staff\StaffPermission;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\PlatformAdminTestHelpers;
use Tests\TestCase;

class BackOfficePrivilegeEscalationTest extends TestCase
{
    use PlatformAdminTestHelpers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_staff_cannot_access_admin_deposit_approve_endpoint(): void
    {
        $staff = $this->staffWithPermissions([StaffPermission::PaymentsVerify]);

        $this->actingAs($staff)
            ->get(route('admin.agent-deposits.index'))
            ->assertForbidden();
    }

    public function test_staff_cannot_access_admin_user_management_routes(): void
    {
        $staff = $this->staffWithPermissions([StaffPermission::BookingsView]);

        $this->actingAs($staff)
            ->get(route('admin.users.index'))
            ->assertForbidden();
    }

    public function test_staff_cannot_promote_self_to_platform_admin_via_profile_update(): void
    {
        $staff = $this->staffWithPermissions([StaffPermission::BookingsView]);

        $this->actingAs($staff)
            ->patch(route('profile.update'), [
                'name' => $staff->name,
                'email' => $staff->email,
                'username' => $staff->username ?? 'staffuser',
                'account_type' => AccountType::PlatformAdmin->value,
            ]);

        $this->assertFalse($staff->fresh()->isPlatformAdmin());
    }

    public function test_staff_cannot_modify_platform_admin_account(): void
    {
        $admin = $this->platformAdmin();
        $staff = $this->staffWithPermissions([StaffPermission::BookingsView]);

        $this->actingAs($staff)
            ->patch(route('admin.users.update', $admin), [
                'name' => 'Compromised Admin',
            ])
            ->assertForbidden();
    }

    public function test_staff_cannot_activate_self_after_suspension_via_admin_route(): void
    {
        $staff = User::query()->where('email', 'staff@ota.demo')->firstOrFail();
        $staff->forceFill(['status' => \App\Enums\UserAccountStatus::Suspended])->save();

        $this->actingAs($staff->fresh())
            ->patch(route('admin.users.activate', $staff))
            ->assertForbidden();
    }

    public function test_staff_with_revoked_permission_cannot_call_admin_only_endpoint(): void
    {
        $staff = User::query()->where('email', 'staff@ota.demo')->firstOrFail();
        $staff->forceFill(['meta' => ['staff_permissions' => []]])->save();

        $this->actingAs($staff->fresh())
            ->get(route('admin.agent-deposits.index'))
            ->assertForbidden();
    }

    /**
     * @param  list<string>  $permissions
     */
    protected function staffWithPermissions(array $permissions): User
    {
        $staff = User::query()->where('email', 'staff@ota.demo')->firstOrFail();
        $staff->forceFill(['meta' => ['staff_permissions' => $permissions]])->save();

        return $staff->fresh();
    }
}
