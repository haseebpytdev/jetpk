<?php

namespace Tests\Feature\Dashboard;

use App\Enums\AccountType;
use App\Enums\UserAccountStatus;
use App\Models\User;
use App\Support\Staff\StaffPermission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardStaffPermissionsJsonContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_load_staff_user_edit_json_catalog(): void
    {
        $admin = User::factory()->create([
            'account_type' => AccountType::PlatformAdmin,
            'current_agency_id' => null,
        ]);
        $staff = User::factory()->create([
            'account_type' => AccountType::Staff,
            'status' => UserAccountStatus::Active,
            'name' => 'QA Staff Edit Target',
            'meta' => [
                'staff_permissions' => [],
            ],
        ]);

        $response = $this->actingAs($admin)
            ->getJson(route('admin.users.edit', $staff).'?format=json');

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('user.account_type', AccountType::Staff->value)
            ->assertJsonPath('isStaffPermissions', true);

        $this->assertIsArray($response->json('groupedStaffPermissions'));
        $this->assertIsArray($response->json('staffPresetLabels'));
        $this->assertIsArray($response->json('staffPresetPermissions'));
    }

    public function test_admin_can_persist_staff_permissions_via_json_update(): void
    {
        $admin = User::factory()->create([
            'account_type' => AccountType::PlatformAdmin,
            'current_agency_id' => null,
        ]);
        $staff = User::factory()->create([
            'account_type' => AccountType::Staff,
            'status' => UserAccountStatus::Active,
            'name' => 'QA Staff Perm Target',
            'email' => 'qa.staff.perm.target@example.test',
            'meta' => [],
        ]);

        $selectable = StaffPermission::staffSelectable();
        $this->assertNotEmpty($selectable);
        $chosen = array_values(array_slice($selectable, 0, min(2, count($selectable))));

        $response = $this->actingAs($admin)
            ->patchJson(route('admin.users.update', $staff).'?format=json', [
                'name' => $staff->name,
                'email' => $staff->email,
                'account_type' => AccountType::Staff->value,
                'status' => UserAccountStatus::Active->value,
                'staff_permissions_configured' => true,
                'staff_permissions' => $chosen,
            ]);

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('message', 'User updated.');

        $staff->refresh();
        $this->assertSame($chosen, array_values($staff->meta['staff_permissions'] ?? []));
        $this->assertSame($chosen, array_values($response->json('selectedStaffPermissions') ?? []));
    }

    public function test_staff_cannot_edit_platform_admin_via_json(): void
    {
        $staff = User::factory()->create([
            'account_type' => AccountType::Staff,
            'current_agency_id' => null,
        ]);
        $admin = User::factory()->create([
            'account_type' => AccountType::PlatformAdmin,
            'current_agency_id' => null,
        ]);

        $this->actingAs($staff)
            ->getJson(route('admin.users.edit', $admin).'?format=json')
            ->assertForbidden();
    }
}
