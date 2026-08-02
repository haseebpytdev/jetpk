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
        $response->assertJsonPath('session_usable', false);
    }

    public function test_session_bootstrap_includes_canonical_contract_fields(): void
    {
        $user = User::factory()->customer()->create([
            'email_verified_at' => now(),
        ]);

        $response = $this->actingAs($user)->getJson('/api/public/auth/session');

        $response->assertOk();
        $response->assertHeader('Cache-Control', 'no-store, private');
        $response->assertJsonPath('authenticated', true);
        $response->assertJsonPath('portal_type', 'customer');
        $response->assertJsonPath('agency_role', null);
        $response->assertJsonPath('email_verified', true);
        $response->assertJsonPath('session_usable', true);
        $response->assertJsonPath('csrf_ready', true);
        $response->assertJsonPath('logout.method', 'POST');
        $response->assertJsonPath('logout.path', '/logout');
        $response->assertJsonStructure(['landing_route', 'dashboard_url', 'permissions']);
        $response->assertJsonMissing(['password', 'otp', 'remember_token']);
    }

    public function test_agent_owner_session_includes_agency_role_owner(): void
    {
        $user = User::factory()->create([
            'account_type' => AccountType::Agent,
            'current_agency_id' => null,
        ]);

        $response = $this->actingAs($user)->getJson('/api/public/auth/session');

        $response->assertOk();
        $response->assertJsonPath('portal_type', 'agent');
        $response->assertJsonPath('agency_role', 'owner');
        $response->assertJsonPath('user.account_type', AccountType::Agent->value);
    }

    public function test_agent_staff_session_includes_agency_role_staff(): void
    {
        $user = User::factory()->create([
            'account_type' => AccountType::AgentStaff,
            'current_agency_id' => null,
        ]);

        $response = $this->actingAs($user)->getJson('/api/public/auth/session');

        $response->assertOk();
        $response->assertJsonPath('portal_type', 'agent');
        $response->assertJsonPath('agency_role', 'staff');
        $response->assertJsonPath('user.account_type', AccountType::AgentStaff->value);
    }

    public function test_platform_admin_session_maps_portal_type_admin(): void
    {
        $user = User::factory()->create([
            'account_type' => AccountType::PlatformAdmin,
        ]);

        $response = $this->actingAs($user)->getJson('/api/public/auth/session');

        $response->assertOk();
        $response->assertJsonPath('portal_type', 'admin');
    }

    public function test_pending_otp_session_never_exposes_otp_value(): void
    {
        Mail::fake();
        $this->makeJetPkProfile();
        $user = User::factory()->customer()->create([
            'email' => 'otp-session@example.test',
            'password' => Hash::make('SecretPass1'),
        ]);

        $this->get('/jetpk/login');
        $this->post('/login', [
            'login' => $user->email,
            'password' => 'SecretPass1',
            'client_slug' => 'jetpk',
        ]);

        $response = $this->getJson('/api/public/auth/session');

        $response->assertOk();
        $response->assertJsonPath('authenticated', false);
        $response->assertJsonPath('requires_otp', true);
        $response->assertJsonStructure(['otp_challenge' => ['masked_email', 'resend_available_in']]);
        $body = $response->json();
        $this->assertArrayNotHasKey('otp', $body);
        $this->assertArrayNotHasKey('otp_code', $body);
        if (isset($body['otp_challenge']) && is_array($body['otp_challenge'])) {
            $this->assertArrayNotHasKey('otp', $body['otp_challenge']);
            $this->assertArrayNotHasKey('code', $body['otp_challenge']);
        }
    }

    public function test_csrf_token_endpoint_is_private_no_store(): void
    {
        $response = $this->getJson('/api/public/content/csrf-token');

        $response->assertOk();
        $response->assertHeader('Cache-Control', 'no-store, private');
        $response->assertJsonStructure(['csrf_token']);
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
