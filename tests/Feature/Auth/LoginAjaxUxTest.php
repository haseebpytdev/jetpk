<?php

namespace Tests\Feature\Auth;

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

class LoginAjaxUxTest extends TestCase
{
    use RefreshDatabase;

    private const GENERIC_LOGIN_FAILURE = 'These credentials do not match our records.';

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.debug' => false]);
    }

    public function test_jetpk_login_get_returns_200(): void
    {
        $this->makeJetPkProfile();

        $this->followingRedirects()
            ->get('/jetpk/login')
            ->assertOk();
    }

    public function test_json_valid_credentials_return_safe_otp_redirect_without_password_leak(): void
    {
        Mail::fake();
        $this->makeJetPkProfile();
        $user = User::factory()->customer()->create([
            'email' => 'ajax-user@example.test',
            'password' => Hash::make('SecretPass1'),
        ]);

        $this->get('/jetpk/login');

        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'X-Requested-With' => 'XMLHttpRequest',
        ])->post('/login', [
            'login' => $user->email,
            'password' => 'SecretPass1',
            'client_slug' => 'jetpk',
        ]);

        $response->assertOk();
        $response->assertJson([
            'ok' => true,
            'requires_otp' => true,
        ]);
        $redirect = (string) $response->json('redirect');
        $this->assertStringStartsWith('/', $redirect);
        $this->assertStringEndsWith('/login/otp', $redirect);
        $this->assertStringNotContainsString('//', $redirect);
        $this->assertStringNotContainsString('SecretPass1', (string) $response->getContent());
        $this->assertGuest();
        Mail::assertSent(LoginOtpMail::class);
    }

    public function test_json_invalid_credentials_return_422_with_generic_login_error(): void
    {
        $this->makeJetPkProfile();

        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'X-Requested-With' => 'XMLHttpRequest',
        ])->post('/login', [
            'login' => 'unknown-user@example.test',
            'password' => 'NotTheRightPassword1',
            'client_slug' => 'jetpk',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['login']);
        $response->assertJsonPath('message', self::GENERIC_LOGIN_FAILURE);
        $this->assertGuest();
    }

    public function test_json_unknown_user_and_wrong_password_share_identical_generic_message(): void
    {
        $this->makeJetPkProfile();
        $user = User::factory()->customer()->create([
            'email' => 'known-user@example.test',
            'password' => Hash::make('SecretPass1'),
        ]);

        $unknownResponse = $this->withHeaders([
            'Accept' => 'application/json',
            'X-Requested-With' => 'XMLHttpRequest',
        ])->post('/login', [
            'login' => 'unknown-user@example.test',
            'password' => 'NotTheRightPassword1',
            'client_slug' => 'jetpk',
        ]);

        $wrongPasswordResponse = $this->withHeaders([
            'Accept' => 'application/json',
            'X-Requested-With' => 'XMLHttpRequest',
        ])->post('/login', [
            'login' => $user->email,
            'password' => 'NotTheRightPassword1',
            'client_slug' => 'jetpk',
        ]);

        $unknownResponse->assertJsonPath('errors.login.0', self::GENERIC_LOGIN_FAILURE);
        $wrongPasswordResponse->assertJsonPath('errors.login.0', self::GENERIC_LOGIN_FAILURE);
    }

    public function test_json_validation_errors_use_predictable_field_structure(): void
    {
        $this->makeJetPkProfile();

        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'X-Requested-With' => 'XMLHttpRequest',
        ])->post('/login', [
            'login' => 'missing-password@example.test',
            'client_slug' => 'jetpk',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['password']);
        $this->assertGuest();
    }

    public function test_login_page_includes_csrf_field_for_async_submission(): void
    {
        $this->makeJetPkProfile();

        $this->followingRedirects()
            ->get('/jetpk/login')
            ->assertOk()
            ->assertSee('name="_token"', false);
    }

    public function test_json_inactive_account_uses_generic_credential_failure(): void
    {
        $this->makeJetPkProfile();
        $user = User::factory()->customer()->create([
            'email' => 'inactive-user@example.test',
            'password' => Hash::make('SecretPass1'),
            'status' => UserAccountStatus::Inactive,
        ]);

        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'X-Requested-With' => 'XMLHttpRequest',
        ])->post('/login', [
            'login' => $user->email,
            'password' => 'SecretPass1',
            'client_slug' => 'jetpk',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonPath('errors.login.0', self::GENERIC_LOGIN_FAILURE);
        $this->assertGuest();
    }

    public function test_html_invalid_credentials_still_redirect_back_with_302(): void
    {
        $this->makeJetPkProfile();
        $this->get('/jetpk/login');

        $response = $this->withHeaders(['Accept' => 'text/html,application/xhtml+xml'])
            ->post('/login', [
                'login' => 'unknown-user@example.test',
                'password' => 'NotTheRightPassword1',
                'client_slug' => 'jetpk',
            ]);

        $response->assertRedirect('/jetpk/login');
        $response->assertSessionHasErrors([
            'login' => self::GENERIC_LOGIN_FAILURE,
        ]);
        $this->assertGuest();
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
