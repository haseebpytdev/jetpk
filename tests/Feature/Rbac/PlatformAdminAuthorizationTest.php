<?php

namespace Tests\Feature\Rbac;

use App\Enums\AccountType;
use App\Enums\UserAccountStatus;
use App\Models\SupplierConnection;
use App\Models\User;
use App\Support\Staff\StaffPermission;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class PlatformAdminAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_admin_can_access_admin_dashboard(): void
    {
        [$admin] = $this->platformAdmin();

        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();
    }

    public function test_platform_admin_can_access_admin_reports(): void
    {
        [$admin] = $this->platformAdmin();

        $this->actingAs($admin)->get(route('admin.reports'))->assertOk();
    }

    public function test_platform_admin_can_access_agent_applications(): void
    {
        [$admin] = $this->platformAdmin();

        $this->actingAs($admin)->get(route('admin.agent-applications.index'))->assertOk();
    }

    public function test_platform_admin_can_access_admin_agencies(): void
    {
        [$admin] = $this->platformAdmin();

        $this->actingAs($admin)->get(route('admin.agencies.index'))->assertOk();
    }

    public function test_platform_admin_can_access_admin_users(): void
    {
        [$admin] = $this->platformAdmin();

        $this->actingAs($admin)->get(route('admin.users.index'))->assertOk();
    }

    public function test_platform_admin_can_access_admin_api_settings(): void
    {
        [$admin] = $this->platformAdmin();

        $this->actingAs($admin)->get(route('admin.api-settings'))->assertOk();
    }

    public function test_platform_admin_can_access_system_health(): void
    {
        [$admin] = $this->platformAdmin();

        $this->actingAs($admin)->get(route('admin.system-health'))->assertOk();
    }

    public function test_platform_admin_can_access_deployment_checklist(): void
    {
        [$admin] = $this->platformAdmin();

        $this->actingAs($admin)->get(route('admin.deployment-checklist'))->assertOk();
    }

    public function test_platform_admin_can_access_go_live_checklist(): void
    {
        [$admin] = $this->platformAdmin();

        $this->actingAs($admin)->get(route('admin.go-live-checklist'))->assertOk();
    }

    public function test_platform_admin_can_access_roles_permissions_page(): void
    {
        [$admin] = $this->platformAdmin();

        $this->actingAs($admin)->get(route('admin.roles-permissions'))->assertOk();
    }

    public function test_platform_admin_can_access_settings_hub(): void
    {
        [$admin] = $this->platformAdmin();

        $this->actingAs($admin)->get(route('admin.settings.index'))->assertOk();
    }

    public function test_staff_cannot_access_admin_users(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $staff = User::query()->where('email', 'staff@ota.demo')->firstOrFail();

        $this->actingAs($staff)->get(route('admin.users.index'))->assertForbidden();
    }

    public function test_staff_cannot_access_admin_api_settings(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $staff = User::query()->where('email', 'staff@ota.demo')->firstOrFail();

        $this->actingAs($staff)->get(route('admin.api-settings'))->assertForbidden();
    }

    public function test_agent_cannot_access_admin_users_or_api_settings(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $agent = User::query()->where('email', 'agent@ota.demo')->firstOrFail();

        $this->actingAs($agent)->get(route('admin.users.index'))->assertForbidden();
        $this->actingAs($agent)->get(route('admin.api-settings'))->assertForbidden();
    }

    public function test_customer_cannot_access_admin_dashboard(): void
    {
        $customer = User::factory()->create([
            'account_type' => AccountType::Customer,
            'status' => UserAccountStatus::Active,
        ]);

        $this->actingAs($customer)->get(route('admin.dashboard'))->assertForbidden();
    }

    public function test_platform_admin_passes_user_management_policy(): void
    {
        [$admin] = $this->platformAdmin();
        $target = User::factory()->create([
            'account_type' => AccountType::Staff,
            'current_agency_id' => $admin->current_agency_id,
            'status' => UserAccountStatus::Active,
        ]);

        $this->assertTrue(Gate::forUser($admin)->allows('viewAny', User::class));
        $this->assertTrue(Gate::forUser($admin)->allows('view', $target));
        $this->assertTrue(Gate::forUser($admin)->allows('create', User::class));
        $this->assertTrue(Gate::forUser($admin)->allows('update', $target));
    }

    public function test_platform_admin_passes_supplier_connection_policy(): void
    {
        [$admin] = $this->platformAdmin();

        $this->assertTrue(Gate::forUser($admin)->allows('viewAny', SupplierConnection::class));
        $this->assertTrue(Gate::forUser($admin)->allows('create', SupplierConnection::class));
        $this->assertTrue(Gate::forUser($admin)->allows('platform.admin'));
    }

    public function test_staff_with_all_permissions_cannot_access_admin_routes(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $staff = User::query()->where('email', 'staff@ota.demo')->firstOrFail();
        $meta = is_array($staff->meta) ? $staff->meta : [];
        $meta['staff_permissions'] = StaffPermission::all();
        $staff->forceFill(['meta' => $meta])->save();
        $staff = $staff->fresh();

        $this->actingAs($staff)->get(route('admin.dashboard'))->assertForbidden();
        $this->actingAs($staff)->get(route('admin.settings.index'))->assertForbidden();
    }

    /**
     * @return array{0: User}
     */
    protected function platformAdmin(): array
    {
        $admin = User::query()->where('email', 'admin@ota.demo')->first();
        if ($admin === null) {
            $this->seed(OtaFoundationSeeder::class);
            $admin = User::query()->where('email', 'admin@ota.demo')->firstOrFail();
        }

        if ($admin->account_type !== AccountType::PlatformAdmin) {
            $admin->forceFill(['account_type' => AccountType::PlatformAdmin])->save();
            $admin = $admin->fresh();
        }

        return [$admin];
    }
}
