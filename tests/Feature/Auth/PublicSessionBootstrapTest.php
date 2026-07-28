<?php

namespace Tests\Feature\Auth;

use App\Enums\AccountType;
use App\Enums\UserAccountStatus;
use App\Mail\LoginOtpMail;
use App\Models\ClientProfile;
use App\Models\ClientProfileBranding;
use App\Models\ClientProfileModule;
use App\Models\User;
use App\Support\Client\ClientProfileConfigReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PublicSessionBootstrapTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_session_bootstrap_returns_unauthenticated(): void
    {
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'X-Requested-With' => 'XMLHttpRequest',
        ])->get('/api/public/auth/session');

        $response->assertOk();
        $response->assertJson([
            'authenticated' => false,
        ]);
    }

    public function test_authenticated_customer_session_bootstrap_includes_dashboard_url(): void
    {
        $user = User::factory()->customer()->create([
            'email_verified_at' => now(),
        ]);

        $response = $this->actingAs($user)->withHeaders([
            'Accept' => 'application/json',
            'X-Requested-With' => 'XMLHttpRequest',
        ])->get('/api/public/auth/session');

        $response->assertOk();
        $response->assertJsonPath('authenticated', true);
        $response->assertJsonPath('user.account_type', AccountType::Customer->value);
        $dashboardUrl = (string) $response->json('dashboard_url');
        $this->assertTrue(
            str_starts_with($dashboardUrl, '/customer'),
            'Expected customer dashboard path, got: '.$dashboardUrl,
        );
    }

    public function test_agent_session_bootstrap_maps_to_agent_dashboard(): void
    {
        $user = User::factory()->create([
            'account_type' => AccountType::Agent,
        ]);

        $response = $this->actingAs($user)->getJson('/api/public/auth/session');

        $response->assertOk();
        $response->assertJsonPath('dashboard_url', '/agent');
    }

    public function test_admin_session_bootstrap_maps_to_admin_dashboard(): void
    {
        $user = User::factory()->create([
            'account_type' => AccountType::PlatformAdmin,
        ]);

        $response = $this->actingAs($user)->getJson('/api/public/auth/session');

        $response->assertOk();
        $response->assertJsonPath('dashboard_url', '/admin/dashboard');
    }

    public function test_staff_session_bootstrap_maps_to_staff_dashboard(): void
    {
        $user = User::factory()->create([
            'account_type' => AccountType::Staff,
        ]);

        $response = $this->actingAs($user)->getJson('/api/public/auth/session');

        $response->assertOk();
        $response->assertJsonPath('dashboard_url', '/staff/dashboard');
    }

    public function test_json_logout_returns_safe_redirect(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->withHeaders([
            'Accept' => 'application/json',
            'X-Requested-With' => 'XMLHttpRequest',
        ])->post('/logout')->assertOk()->assertJson([
            'ok' => true,
            'redirect' => '/',
        ]);

        $this->assertGuest();
    }

    public function test_json_otp_verify_completes_login(): void
    {
        Mail::fake();
        $this->makeJetPkProfile();
        $user = User::factory()->customer()->create([
            'email' => 'otp-json@example.test',
            'password' => Hash::make('SecretPass1'),
            'email_verified_at' => now(),
        ]);

        $this->get('/jetpk/login');

        $loginResponse = $this->post('/login', [
            'login' => $user->email,
            'password' => 'SecretPass1',
            'client_slug' => 'jetpk',
        ]);

        $loginResponse->assertRedirect();
        $this->assertStringEndsWith('/login/otp', (string) $loginResponse->headers->get('Location'));

        $sentCode = null;
        Mail::assertSent(LoginOtpMail::class, function (LoginOtpMail $mail) use (&$sentCode): bool {
            $sentCode = $mail->otpCode;

            return is_string($sentCode) && strlen($sentCode) === 6;
        });

        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'X-Requested-With' => 'XMLHttpRequest',
        ])->post('/login/otp', ['otp' => $sentCode]);

        $response->assertOk();
        $response->assertJsonPath('ok', true);
        $this->assertAuthenticatedAs($user);
    }

    public function test_json_forgot_password_returns_generic_success(): void
    {
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'X-Requested-With' => 'XMLHttpRequest',
        ])->post('/forgot-password', [
            'email' => 'unknown@example.test',
        ]);

        $response->assertOk();
        $response->assertJsonPath('ok', true);
        $response->assertJsonStructure(['message']);
    }

    public function test_inactive_account_bootstrap_still_reports_status(): void
    {
        $user = User::factory()->customer()->create([
            'status' => UserAccountStatus::Inactive,
            'email_verified_at' => now(),
        ]);

        $response = $this->actingAs($user)->getJson('/api/public/auth/session');

        $response->assertOk();
        $response->assertJsonPath('account_status', UserAccountStatus::Inactive->value);
    }

    private function makeJetPkProfile(): ClientProfile
    {
        $profile = ClientProfile::query()->create([
            'name' => 'JetPakistan',
            'slug' => 'jetpk',
            'domain' => 'jetpakistan.com',
            'environment' => 'production',
            'active_frontend_theme' => 'jetpakistan',
            'active_admin_theme' => 'jetpakistan',
            'active_staff_theme' => 'default-staff',
            'asset_profile' => 'jetpk-assets',
            'default_locale' => 'en',
            'timezone' => 'Asia/Karachi',
            'currency' => 'PKR',
            'is_master_profile' => false,
            'is_active' => true,
        ]);

        ClientProfileBranding::query()->create([
            'client_profile_id' => $profile->id,
            'company_name' => 'JetPakistan',
        ]);

        foreach (ClientProfileConfigReader::MODULE_KEYS as $moduleKey) {
            ClientProfileModule::query()->create([
                'client_profile_id' => $profile->id,
                'module_key' => $moduleKey,
                'enabled' => true,
            ]);
        }

        return $profile;
    }
}
