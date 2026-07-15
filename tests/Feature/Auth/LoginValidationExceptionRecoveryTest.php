<?php

namespace Tests\Feature\Auth;

use App\Models\ClientProfile;
use App\Models\ClientProfileBranding;
use App\Models\ClientProfileModule;
use App\Models\User;
use App\Support\Client\ClientProfileConfigReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LoginValidationExceptionRecoveryTest extends TestCase
{
    use RefreshDatabase;

    private const GENERIC_LOGIN_FAILURE = 'These credentials do not match our records.';

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.debug' => false]);
    }

    public function test_html_invalid_login_unknown_email_redirects_back_with_login_error_and_old_input(): void
    {
        $this->makeJetPkProfile();

        $this->get('/jetpk/login');

        $response = $this->withHeaders(['Accept' => 'text/html,application/xhtml+xml'])
            ->post('/login', [
                'login' => 'unknown-user@example.test',
                'password' => 'NotTheRightPassword1',
            ]);

        $response->assertRedirect('/jetpk/login');
        $response->assertSessionHasErrors([
            'login' => self::GENERIC_LOGIN_FAILURE,
        ]);
        $response->assertSessionHasInput('login', 'unknown-user@example.test');
        $this->assertFalse(session()->hasOldInput('password'));
        $this->assertGuest();
    }

    public function test_html_invalid_login_wrong_password_uses_same_generic_message_without_enumeration(): void
    {
        $this->makeJetPkProfile();
        $user = User::factory()->customer()->create([
            'email' => 'known-user@example.test',
            'password' => Hash::make('SecretPass1'),
        ]);

        $this->get('/jetpk/login');

        $unknownResponse = $this->withHeaders(['Accept' => 'text/html,application/xhtml+xml'])
            ->post('/login', [
                'login' => 'unknown-user@example.test',
                'password' => 'NotTheRightPassword1',
            ]);

        $this->get('/jetpk/login');

        $wrongPasswordResponse = $this->withHeaders(['Accept' => 'text/html,application/xhtml+xml'])
            ->post('/login', [
                'login' => $user->email,
                'password' => 'NotTheRightPassword1',
            ]);

        $unknownResponse->assertSessionHasErrors([
            'login' => self::GENERIC_LOGIN_FAILURE,
        ]);
        $wrongPasswordResponse->assertSessionHasErrors([
            'login' => self::GENERIC_LOGIN_FAILURE,
        ]);
        $wrongPasswordResponse->assertRedirect('/jetpk/login');
        $wrongPasswordResponse->assertSessionHasInput('login', $user->email);
        $this->assertFalse(session()->hasOldInput('password'));
        $this->assertGuest();
    }

    public function test_json_invalid_login_returns_422_with_login_error_key(): void
    {
        $this->makeJetPkProfile();

        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'X-Requested-With' => 'XMLHttpRequest',
        ])->post('/login', [
            'login' => 'unknown-user@example.test',
            'password' => 'NotTheRightPassword1',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['login']);
        $response->assertJsonPath('message', self::GENERIC_LOGIN_FAILURE);
        $this->assertGuest();
    }

    public function test_rate_limited_login_preserves_throttle_validation_response(): void
    {
        $this->makeJetPkProfile();

        $this->get('/jetpk/login');

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->withHeaders(['Accept' => 'text/html,application/xhtml+xml'])
                ->post('/login', [
                    'login' => 'throttle-user@example.test',
                    'password' => 'NotTheRightPassword1',
                ])
                ->assertRedirect('/jetpk/login')
                ->assertSessionHasErrors('login');
        }

        $response = $this->withHeaders(['Accept' => 'text/html,application/xhtml+xml'])
            ->post('/login', [
                'login' => 'throttle-user@example.test',
                'password' => 'NotTheRightPassword1',
            ]);

        $response->assertRedirect('/jetpk/login');
        $response->assertSessionHasErrors('login');
        $this->assertStringContainsString(
            'Too many login attempts',
            (string) session('errors')->first('login'),
        );
        $this->assertGuest();
    }

    public function test_html_login_rule_validation_is_not_converted_to_custom_500_page(): void
    {
        $this->makeJetPkProfile();

        $this->get('/jetpk/login');

        $response = $this->withHeaders(['Accept' => 'text/html,application/xhtml+xml'])
            ->post('/login', [
                'login' => 'missing-password@example.test',
            ]);

        $response->assertRedirect('/jetpk/login');
        $response->assertSessionHasErrors('password');
        $response->assertStatus(302);
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
