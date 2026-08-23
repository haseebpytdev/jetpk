<?php

namespace Tests\Feature\Dashboard;

use App\Enums\AccountType;
use App\Enums\UserAccountStatus;
use App\Models\User;
use App\Support\Staff\StaffPermission;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\PlatformAdminTestHelpers;
use Tests\TestCase;

class BackOfficeSessionContractTest extends TestCase
{
    use PlatformAdminTestHelpers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
    }

    public function test_admin_session_returns_platform_admin_role_and_navigation(): void
    {
        $admin = $this->platformAdmin();

        $this->actingAs($admin)
            ->getJson(route('api.dashboard.session', ['portal' => 'admin']))
            ->assertOk()
            ->assertJsonPath('data.platformRole', 'platform_admin')
            ->assertJsonPath('data.portalType', 'admin')
            ->assertJsonPath('data.sessionUsable', true)
            ->assertJsonStructure(['data' => ['navigation', 'capabilities', 'permissions']]);
    }

    public function test_staff_session_returns_staff_platform_role(): void
    {
        $staff = User::query()->where('email', 'staff@ota.demo')->firstOrFail();

        $this->actingAs($staff)
            ->getJson(route('api.dashboard.session', ['portal' => 'staff']))
            ->assertOk()
            ->assertJsonPath('data.platformRole', 'staff')
            ->assertJsonPath('data.portalType', 'staff')
            ->assertJsonPath('data.sessionUsable', true);
    }

    public function test_staff_session_includes_grouped_navigation_without_admin_only_items(): void
    {
        $staff = User::query()->where('email', 'staff@ota.demo')->firstOrFail();

        $response = $this->actingAs($staff)
            ->getJson(route('api.dashboard.session', ['portal' => 'staff']))
            ->assertOk();

        $groups = $response->json('data.navigationGroups') ?? [];
        $this->assertNotEmpty($groups);

        $groupLabels = collect($groups)->pluck('label')->all();
        $this->assertContains('Overview', $groupLabels);
        $this->assertContains('Operations', $groupLabels);

        $itemLabels = collect($groups)
            ->flatMap(static fn (array $group): array => collect($group['items'] ?? [])->pluck('label')->all())
            ->all();

        $this->assertContains('Bookings', $itemLabels);
        $this->assertNotContains('Markups', $itemLabels);
        $this->assertNotContains('Go-live checklist', $itemLabels);
        $this->assertNotContains('Staff', $itemLabels);
        $this->assertNotContains('API Connections', $itemLabels);
        $this->assertNotContains('CMS', $itemLabels);
    }

    public function test_admin_session_includes_grouped_navigation(): void
    {
        $admin = $this->platformAdmin();

        $response = $this->actingAs($admin)
            ->getJson(route('api.dashboard.session', ['portal' => 'admin']))
            ->assertOk();

        $groups = $response->json('data.navigationGroups') ?? [];
        $this->assertNotEmpty($groups);

        $groupLabels = collect($groups)->pluck('label')->all();
        $this->assertContains('Finance', $groupLabels);
        $this->assertContains('Operations', $groupLabels);
        $this->assertContains('Website', $groupLabels);
        $this->assertContains('Suppliers', $groupLabels);

        $itemLabels = collect($groups)
            ->flatMap(static fn (array $group): array => collect($group['items'] ?? [])->pluck('label')->all())
            ->all();
        $this->assertContains('Integrations', $itemLabels);
        $this->assertNotContains('API Connections', $itemLabels);
        $this->assertNotContains('CMS', $itemLabels);
    }

    public function test_customer_is_denied_dashboard_session(): void
    {
        $customer = User::factory()->create([
            'account_type' => AccountType::Customer,
            'status' => UserAccountStatus::Active,
        ]);

        $this->actingAs($customer)
            ->getJson(route('api.dashboard.session'))
            ->assertForbidden();
    }

    public function test_agent_owner_is_denied_dashboard_session(): void
    {
        $agent = User::query()->where('email', 'agent@ota.demo')->firstOrFail();

        $this->actingAs($agent)
            ->getJson(route('api.dashboard.session'))
            ->assertForbidden();
    }

    public function test_inactive_staff_is_denied_dashboard_session(): void
    {
        $staff = User::query()->where('email', 'staff@ota.demo')->firstOrFail();
        $staff->forceFill(['status' => UserAccountStatus::Inactive])->save();

        $this->actingAs($staff->fresh())
            ->getJson(route('api.dashboard.session', ['portal' => 'staff']))
            ->assertForbidden();
    }

    public function test_suspended_staff_is_denied_dashboard_session(): void
    {
        $staff = User::query()->where('email', 'staff@ota.demo')->firstOrFail();
        $staff->forceFill(['status' => UserAccountStatus::Suspended])->save();

        $this->actingAs($staff->fresh())
            ->getJson(route('api.dashboard.session', ['portal' => 'staff']))
            ->assertForbidden();
    }

    public function test_staff_cannot_access_admin_portal_session(): void
    {
        $staff = User::query()->where('email', 'staff@ota.demo')->firstOrFail();

        $this->actingAs($staff)
            ->getJson(route('api.dashboard.session', ['portal' => 'admin']))
            ->assertForbidden()
            ->assertJsonPath('error.code', 'admin_only');
    }

    public function test_revoked_staff_permission_reflected_on_next_session_request(): void
    {
        $staff = User::query()->where('email', 'staff@ota.demo')->firstOrFail();
        $staff->forceFill([
            'meta' => ['staff_permissions' => [StaffPermission::PaymentsVerify]],
        ])->save();

        $this->actingAs($staff->fresh())
            ->getJson(route('api.dashboard.session', ['portal' => 'staff']))
            ->assertOk();

        $staff->forceFill([
            'meta' => ['staff_permissions' => []],
        ])->save();

        $this->actingAs($staff->fresh())
            ->getJson(route('api.dashboard.session', ['portal' => 'staff']))
            ->assertOk()
            ->assertJsonMissingPath('data.permissions.'.StaffPermission::PaymentsVerify);
    }

    public function test_admin_overview_includes_authoritative_kpi_keys(): void
    {
        $admin = $this->platformAdmin();

        $response = $this->actingAs($admin)
            ->getJson(route('api.dashboard.overview'))
            ->assertOk();

        $queues = $response->json('data.operationalQueues') ?? [];
        $keys = collect($queues)->pluck('key')->all();
        foreach (['pending_deposits', 'payment_review', 'cancellations_pending', 'refunds_pending'] as $required) {
            $this->assertContains($required, $keys, "Missing KPI key: {$required}");
        }
    }

    public function test_staff_without_finance_permissions_omits_restricted_kpis(): void
    {
        $staff = User::query()->where('email', 'staff@ota.demo')->firstOrFail();
        $staff->forceFill([
            'meta' => ['staff_permissions' => [StaffPermission::BookingsView]],
        ])->save();

        $response = $this->actingAs($staff->fresh())
            ->getJson(route('api.dashboard.overview'));

        if ($response->status() === 200) {
            $queues = $response->json('data.operationalQueues') ?? [];
            $keys = collect($queues)->pluck('key')->all();
            $this->assertNotContains('pending_deposits', $keys);
        } else {
            $response->assertForbidden();
        }
    }
}
