<?php

namespace Tests\Feature\Dashboard;

use App\Enums\AccountType;
use App\Models\User;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\Support\Auth\ConfiguresAuthTestEnvironment;
use Tests\Support\PlatformAdminTestHelpers;
use Tests\TestCase;

/**
 * JP-DASH-03 remember-login persistence and negative auth security proofs.
 * Isolated fixture environment — does not alter production session configuration.
 */
class JpDash03AuthRecoverySecurityTest extends TestCase
{
    use ConfiguresAuthTestEnvironment;
    use PlatformAdminTestHelpers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutLoginOtpGate();
    }

    public function test_remember_login_restores_same_platform_admin_after_logout(): void
    {
        $admin = $this->platformAdmin();

        $login = $this->post('/login', [
            'login' => $admin->email,
            'password' => 'password',
            'remember' => '1',
        ]);

        $login->assertRedirect();

        $rememberCookie = null;
        foreach ($login->headers->getCookies() as $cookie) {
            if (str_starts_with($cookie->getName(), 'remember_')) {
                $rememberCookie = $cookie;
                break;
            }
        }

        $this->assertNotNull($rememberCookie, 'Expected Laravel remember cookie after remember=true login.');

        session()->flush();
        Auth::guard('web')->forgetUser();
        $this->assertGuest();

        $this->withUnencryptedCookie($rememberCookie->getName(), $rememberCookie->getValue())
            ->get(route('admin.dashboard'))
            ->assertOk();

        $this->assertAuthenticatedAs($admin);
        $this->assertSame(AccountType::PlatformAdmin, $this->app['auth']->user()->account_type);
    }

    public function test_remember_login_restores_same_staff_after_logout(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $staff = User::query()->where('email', 'staff@ota.demo')->firstOrFail();

        $login = $this->post('/login', [
            'login' => $staff->email,
            'password' => 'password',
            'remember' => '1',
        ]);

        $login->assertRedirect();

        $rememberCookie = null;
        foreach ($login->headers->getCookies() as $cookie) {
            if (str_starts_with($cookie->getName(), 'remember_')) {
                $rememberCookie = $cookie;
                break;
            }
        }

        $this->assertNotNull($rememberCookie);

        session()->flush();
        Auth::guard('web')->forgetUser();
        $this->assertGuest();

        $this->withUnencryptedCookie($rememberCookie->getName(), $rememberCookie->getValue())
            ->get(route('staff.dashboard'))
            ->assertOk();

        $this->assertAuthenticatedAs($staff);
        $this->assertSame(AccountType::Staff, $this->app['auth']->user()->account_type);
    }

    public function test_anonymous_browser_cannot_access_admin_dashboard(): void
    {
        $this->get('/admin/dashboard')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_customer_cannot_access_admin_dashboard_api(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $customer = User::query()->where('email', 'customer@ota.demo')->firstOrFail();

        $this->actingAs($customer)
            ->getJson(route('api.dashboard.overview'))
            ->assertForbidden();
    }

    public function test_agent_cannot_access_admin_portal_session_api(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $agent = User::query()->where('email', 'agent@ota.demo')->firstOrFail();

        $this->actingAs($agent)
            ->getJson(route('api.dashboard.session', ['portal' => 'admin']))
            ->assertForbidden();
    }

    public function test_staff_cannot_access_admin_portal_session_api(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $staff = User::query()->where('email', 'staff@ota.demo')->firstOrFail();

        $this->actingAs($staff)
            ->getJson(route('api.dashboard.session', ['portal' => 'admin']))
            ->assertForbidden();
    }

    public function test_platform_admin_can_access_admin_portal_session_api(): void
    {
        $admin = $this->platformAdmin();

        $this->actingAs($admin)
            ->getJson(route('api.dashboard.session', ['portal' => 'admin']))
            ->assertOk()
            ->assertJsonPath('data.platformRole', 'platform_admin');
    }
}
