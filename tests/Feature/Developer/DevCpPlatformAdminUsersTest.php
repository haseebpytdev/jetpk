<?php

namespace Tests\Feature\Developer;

use App\Enums\AccountType;
use App\Enums\UserAccountStatus;
use App\Models\DeveloperUser;
use App\Models\User;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class DevCpPlatformAdminUsersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('ota-developer.enabled', true);
    }

    public function test_guest_cannot_access_platform_admin_users(): void
    {
        $this->get(route('dev.cp.users.index'))
            ->assertRedirect(route('dev.cp.login'));
    }

    public function test_lists_only_platform_admin_users(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $developer = $this->developerUser();

        User::query()->create([
            'name' => 'Agent User',
            'email' => 'agent-only@example.com',
            'password' => bcrypt('secret'),
            'account_type' => AccountType::Agent,
            'status' => UserAccountStatus::Active,
        ]);

        User::query()->create([
            'name' => 'Extra Admin',
            'email' => 'extra-admin@example.com',
            'password' => bcrypt('secret'),
            'account_type' => AccountType::PlatformAdmin,
            'status' => UserAccountStatus::Active,
            'must_change_password' => false,
        ]);

        $this->withSession(['dev_cp_user_id' => $developer->id])
            ->get(route('dev.cp.users.index'))
            ->assertOk()
            ->assertSee('Platform Admin accounts', false)
            ->assertSee('extra-admin@example.com', false)
            ->assertDontSee('agent-only@example.com', false);
    }

    public function test_create_platform_admin_shows_temp_password_once(): void
    {
        $developer = $this->developerUser();

        $response = $this->withSession(['dev_cp_user_id' => $developer->id])
            ->post(route('dev.cp.users.store'), [
                'name' => 'Client Admin',
                'email' => 'client-admin@example.com',
            ]);

        $response->assertRedirect(route('dev.cp.users.index'));
        $response->assertSessionHas('dev_cp_temp_password');
        $response->assertSessionHas('dev_cp_temp_password_email', 'client-admin@example.com');

        $this->assertDatabaseHas('users', [
            'email' => 'client-admin@example.com',
            'account_type' => AccountType::PlatformAdmin->value,
            'must_change_password' => true,
        ]);

        $this->assertDatabaseHas('platform_audit_logs', [
            'action' => 'devcp.platform_admin.created',
        ]);

        $this->assertDatabaseHas('security_events', [
            'event_type' => 'devcp.platform_admin.created',
            'outcome' => 'success',
        ]);
    }

    public function test_reset_password_shows_new_temp_password(): void
    {
        $developer = $this->developerUser();
        $admin = User::query()->create([
            'name' => 'Admin',
            'email' => 'reset-me@example.com',
            'password' => bcrypt('old-secret'),
            'account_type' => AccountType::PlatformAdmin,
            'status' => UserAccountStatus::Active,
            'must_change_password' => false,
        ]);

        $response = $this->withSession(['dev_cp_user_id' => $developer->id])
            ->post(route('dev.cp.users.reset-password', $admin));

        $response->assertRedirect(route('dev.cp.users.index'));
        $response->assertSessionHas('dev_cp_temp_password');

        $admin->refresh();
        $this->assertTrue($admin->must_change_password);

        $this->assertDatabaseHas('platform_audit_logs', [
            'action' => 'devcp.platform_admin.password_reset',
        ]);
    }

    public function test_cannot_deactivate_last_active_platform_admin(): void
    {
        $developer = $this->developerUser();
        $admin = User::query()->create([
            'name' => 'Only Admin',
            'email' => 'only-admin@example.com',
            'password' => bcrypt('secret'),
            'account_type' => AccountType::PlatformAdmin,
            'status' => UserAccountStatus::Active,
        ]);

        $this->withSession(['dev_cp_user_id' => $developer->id])
            ->post(route('dev.cp.users.status', $admin), ['status' => 'inactive'])
            ->assertRedirect(route('dev.cp.users.index'))
            ->assertSessionHasErrors('user');

        $admin->refresh();
        $this->assertSame(UserAccountStatus::Active, $admin->status);
    }

    public function test_can_deactivate_when_multiple_active_platform_admins_exist(): void
    {
        $developer = $this->developerUser();

        $keep = User::query()->create([
            'name' => 'Keep',
            'email' => 'keep-admin@example.com',
            'password' => bcrypt('secret'),
            'account_type' => AccountType::PlatformAdmin,
            'status' => UserAccountStatus::Active,
        ]);

        $deactivate = User::query()->create([
            'name' => 'Remove',
            'email' => 'remove-admin@example.com',
            'password' => bcrypt('secret'),
            'account_type' => AccountType::PlatformAdmin,
            'status' => UserAccountStatus::Active,
        ]);

        $this->withSession(['dev_cp_user_id' => $developer->id])
            ->post(route('dev.cp.users.status', $deactivate), ['status' => 'inactive'])
            ->assertRedirect(route('dev.cp.users.index'))
            ->assertSessionHas('status');

        $deactivate->refresh();
        $keep->refresh();
        $this->assertSame(UserAccountStatus::Inactive, $deactivate->status);
        $this->assertSame(UserAccountStatus::Active, $keep->status);
    }

    public function test_non_platform_admin_reset_returns_404(): void
    {
        $developer = $this->developerUser();
        $agent = User::query()->create([
            'name' => 'Agent',
            'email' => 'agent@example.com',
            'password' => bcrypt('secret'),
            'account_type' => AccountType::Agent,
            'status' => UserAccountStatus::Active,
        ]);

        $this->withSession(['dev_cp_user_id' => $developer->id])
            ->post(route('dev.cp.users.reset-password', $agent))
            ->assertNotFound();
    }

    private function developerUser(): DeveloperUser
    {
        return DeveloperUser::query()->create([
            'name' => 'Dev',
            'email' => 'dev-users@example.com',
            'password' => 'secret-password',
            'is_active' => true,
        ]);
    }
}
