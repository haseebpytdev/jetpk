<?php

namespace Tests\Feature\Auth;

use App\Enums\AccountType;
use App\Enums\UserAccountStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
        $response->assertSee('Email or username', false);
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('admin.dashboard', absolute: false));
    }

    public function test_platform_admin_can_login_by_email(): void
    {
        $user = User::factory()->create([
            'email' => 'owner@example.test',
            'username' => 'admin',
            'account_type' => AccountType::PlatformAdmin,
        ]);

        $response = $this->post('/login', [
            'login' => 'owner@example.test',
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('admin.dashboard', absolute: false));
    }

    public function test_platform_admin_can_login_by_username_admin(): void
    {
        $user = User::factory()->create([
            'email' => 'myworkhaseeb@gmail.com',
            'username' => 'admin',
            'account_type' => AccountType::PlatformAdmin,
        ]);

        $this->post('/login', [
            'login' => 'admin',
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($user);
    }

    public function test_admin_demo_user_can_login_by_username_platformdemo(): void
    {
        $user = User::factory()->create([
            'email' => 'admin@ota.demo',
            'username' => 'platformdemo',
            'account_type' => AccountType::PlatformAdmin,
        ]);

        $this->post('/login', [
            'login' => 'platformdemo',
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($user);
    }

    public function test_agent_can_login_by_username_agencyowner(): void
    {
        $user = User::factory()->create([
            'email' => 'agent@demo.ota',
            'username' => 'agencyowner',
            'account_type' => AccountType::Agent,
        ]);

        $response = $this->post('/login', [
            'login' => 'agencyowner',
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('agent.dashboard', absolute: false));
    }

    public function test_agent_staff_can_login_by_username_agentstaff(): void
    {
        $user = User::factory()->create([
            'email' => 'agent.staff@demo.ota',
            'username' => 'agentstaff',
            'account_type' => AccountType::AgentStaff,
        ]);

        $response = $this->post('/login', [
            'login' => 'agentstaff',
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('agent.dashboard', absolute: false));
    }

    public function test_staff_can_login_by_username_staffdemo(): void
    {
        $user = User::factory()->create([
            'email' => 'staff@demo.ota',
            'username' => 'staffdemo',
            'account_type' => AccountType::Staff,
        ]);

        $response = $this->post('/login', [
            'login' => 'staffdemo',
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('staff.dashboard', absolute: false));
    }

    public function test_customer_can_login_by_username_customerdemo(): void
    {
        $user = User::factory()->create([
            'email' => 'customer@demo.ota',
            'username' => 'customerdemo',
            'account_type' => AccountType::Customer,
        ]);

        $response = $this->post('/login', [
            'login' => 'customerdemo',
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('customer.bookings.index', absolute: false));
    }

    public function test_users_can_authenticate_using_username(): void
    {
        $user = User::factory()->create(['username' => 'admin']);

        $response = $this->post('/login', [
            'login' => 'admin',
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('admin.dashboard', absolute: false));
    }

    public function test_username_login_is_case_insensitive(): void
    {
        $user = User::factory()->create([
            'username' => 'platformdemo',
            'account_type' => AccountType::PlatformAdmin,
        ]);

        $this->post('/login', [
            'login' => 'PlatformDemo',
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($user);
    }

    public function test_email_login_is_case_insensitive(): void
    {
        $user = User::factory()->create([
            'email' => 'admin@ota.demo',
            'username' => 'platformdemo',
        ]);

        $this->post('/login', [
            'login' => 'Admin@OTA.demo',
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($user);
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'login' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_inactive_users_cannot_login(): void
    {
        User::factory()->create([
            'username' => 'inactiveuser',
            'status' => UserAccountStatus::Inactive,
        ]);

        $this->post('/login', [
            'login' => 'inactiveuser',
            'password' => 'password',
        ]);

        $this->assertGuest();
    }

    public function test_suspended_users_cannot_login(): void
    {
        User::factory()->create([
            'username' => 'suspendeduser',
            'status' => UserAccountStatus::Suspended,
        ]);

        $this->post('/login', [
            'login' => 'suspendeduser',
            'password' => 'password',
        ]);

        $this->assertGuest();
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/');
    }

    public function test_legacy_agency_admin_login_redirects_to_legacy_notice(): void
    {
        $user = User::factory()->create([
            'account_type' => AccountType::AgencyAdmin,
        ]);

        $response = $this->post('/login', [
            'login' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('account.legacy', absolute: false));
    }

    public function test_agent_cannot_access_admin_dashboard(): void
    {
        $agent = User::factory()->create([
            'account_type' => AccountType::Agent,
        ]);

        $this->actingAs($agent)->get(route('admin.dashboard'))->assertForbidden();
    }

    public function test_agent_staff_cannot_access_admin_dashboard(): void
    {
        $agentStaff = User::factory()->create([
            'account_type' => AccountType::AgentStaff,
        ]);

        $this->actingAs($agentStaff)->get(route('admin.dashboard'))->assertForbidden();
    }

    public function test_customer_cannot_access_admin_or_agent_protected_pages(): void
    {
        $customer = User::factory()->create([
            'account_type' => AccountType::Customer,
        ]);

        $this->actingAs($customer)->get(route('admin.dashboard'))->assertForbidden();
        $this->actingAs($customer)->get(route('agent.dashboard'))->assertForbidden();
    }

    public function test_staff_cannot_access_supplier_api_settings(): void
    {
        $staff = User::factory()->create([
            'account_type' => AccountType::Staff,
        ]);

        $this->actingAs($staff)->get(route('admin.api-settings'))->assertForbidden();
    }

    public function test_platform_admin_can_access_supplier_api_settings(): void
    {
        $platformAdmin = User::factory()->create([
            'account_type' => AccountType::PlatformAdmin,
        ]);

        $this->actingAs($platformAdmin)->get(route('admin.api-settings'))->assertOk();
    }
}
