<?php

namespace Tests\Feature\Dashboard;

use App\Enums\AccountType;
use App\Enums\UserAccountStatus;
use App\Models\User;
use App\Support\Staff\StaffPermission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardStaffCreateJsonContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_load_user_create_json_catalog_for_staff(): void
    {
        $admin = User::factory()->create([
            'account_type' => AccountType::PlatformAdmin,
            'current_agency_id' => null,
        ]);

        $response = $this->actingAs($admin)
            ->getJson(route('admin.users.create').'?format=json');

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('isEdit', false)
            ->assertJsonPath('user.account_type', AccountType::Staff->value);

        $this->assertIsArray($response->json('groupedStaffPermissions'));
        $this->assertContains(AccountType::Staff->value, $response->json('accountTypeOptions'));
    }

    public function test_admin_can_create_staff_user_via_json_store(): void
    {
        $admin = User::factory()->create([
            'account_type' => AccountType::PlatformAdmin,
            'current_agency_id' => null,
        ]);

        $selectable = StaffPermission::staffSelectable();
        $chosen = array_values(array_slice($selectable, 0, min(2, count($selectable))));

        $response = $this->actingAs($admin)
            ->postJson(route('admin.users.store').'?format=json', [
                'name' => 'QA Staff Create Target',
                'email' => 'qa.staff.create.target@example.test',
                'account_type' => AccountType::Staff->value,
                'status' => UserAccountStatus::Active->value,
                'role_title' => 'QA Operations',
                'department' => 'Support',
                'phone' => '+92000000001',
                'staff_permissions_configured' => true,
                'staff_permissions' => $chosen,
                'send_invite' => false,
            ]);

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('message', 'User created.')
            ->assertJsonPath('user.account_type', AccountType::Staff->value)
            ->assertJsonPath('user.email', 'qa.staff.create.target@example.test');

        $user = User::query()->where('email', 'qa.staff.create.target@example.test')->first();
        $this->assertNotNull($user);
        $this->assertSame(AccountType::Staff, $user->account_type);
        $this->assertSame('QA Operations', $user->meta['role_title'] ?? null);
        $this->assertSame('Support', $user->meta['department'] ?? null);
        $this->assertSame($chosen, array_values($user->meta['staff_permissions'] ?? []));

        $directory = $this->actingAs($admin)
            ->getJson(route('admin.staff').'?format=json');

        $directory->assertOk()->assertJsonPath('ok', true);
        $ids = collect($directory->json('staff') ?? [])->pluck('user_id')->all();
        $this->assertContains($user->id, $ids);
    }

    public function test_admin_cannot_suspend_self_via_json(): void
    {
        $admin = User::factory()->create([
            'account_type' => AccountType::PlatformAdmin,
            'current_agency_id' => null,
            'status' => UserAccountStatus::Active,
        ]);

        $this->actingAs($admin)
            ->patchJson(route('admin.users.suspend', $admin).'?format=json')
            ->assertStatus(422)
            ->assertJsonPath('ok', false);
    }

    public function test_admin_cannot_suspend_last_active_platform_admin_via_peer(): void
    {
        $sole = User::factory()->create([
            'account_type' => AccountType::PlatformAdmin,
            'current_agency_id' => null,
            'status' => UserAccountStatus::Active,
            'email' => 'sole.admin@example.test',
        ]);
        $peer = User::factory()->create([
            'account_type' => AccountType::PlatformAdmin,
            'current_agency_id' => null,
            'status' => UserAccountStatus::Active,
            'email' => 'peer.admin@example.test',
        ]);

        $this->actingAs($peer)
            ->patchJson(route('admin.users.suspend', $sole).'?format=json')
            ->assertOk();

        // peer is now the only active platform admin; suspending peer must fail (self).
        // Recreate sole as active so peer can attempt last-admin suspend of sole after peer is sole.
        $sole->forceFill(['status' => UserAccountStatus::Suspended])->save();
        $this->assertSame(1, User::query()
            ->where('account_type', AccountType::PlatformAdmin)
            ->where('status', UserAccountStatus::Active)
            ->count());

        // Make sole active again and suspend peer first so sole is last, then peer (still platform_admin) tries.
        $sole->forceFill(['status' => UserAccountStatus::Active])->save();
        $this->actingAs($sole)
            ->patchJson(route('admin.users.suspend', $peer).'?format=json')
            ->assertOk();

        $this->actingAs($peer)
            ->patchJson(route('admin.users.suspend', $sole).'?format=json')
            ->assertStatus(422)
            ->assertJsonPath('message', 'Cannot suspend the last active platform admin.');
    }
}
