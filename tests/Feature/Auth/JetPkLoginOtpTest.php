<?php

namespace Tests\Feature\Auth;

use App\Mail\LoginOtpMail;
use App\Models\ClientProfile;
use App\Models\ClientProfileBranding;
use App\Models\ClientProfileModule;
use App\Models\User;
use App\Support\Client\ClientProfileConfigReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class JetPkLoginOtpTest extends TestCase
{
    use RefreshDatabase;

    public function test_jetpk_login_redirects_to_otp_challenge_on_valid_credentials(): void
    {
        Mail::fake();
        $this->makeJetPkProfile();
        $user = User::factory()->customer()->create([
            'email' => 'jetpk-user@example.test',
            'password' => Hash::make('SecretPass1'),
        ]);

        $this->get('/jetpk/login');

        $response = $this->post('/login', [
            'login' => $user->email,
            'password' => 'SecretPass1',
        ]);

        $response->assertRedirect('/jetpk/login/otp');
        $this->assertGuest();
        Mail::assertSent(LoginOtpMail::class, function (LoginOtpMail $mail) use ($user): bool {
            return $mail->hasTo($user->email)
                && $mail->hasFrom(config('mail.from.address'), 'JetPakistan')
                && $mail->envelope()->subject === 'Your JetPakistan login OTP';
        });
    }

    public function test_jetpk_wrong_password_returns_validation_error_without_otp(): void
    {
        Mail::fake();
        $this->makeJetPkProfile();
        $user = User::factory()->customer()->create([
            'email' => 'jetpk-user@example.test',
            'password' => Hash::make('SecretPass1'),
        ]);

        $this->get('/jetpk/login');

        $response = $this->post('/login', [
            'login' => $user->email,
            'password' => 'wrong-password',
        ]);

        $response->assertSessionHasErrors('login');
        $this->assertGuest();
        Mail::assertNothingSent();
    }

    public function test_jetpk_correct_otp_completes_login(): void
    {
        Mail::fake();
        $this->makeJetPkProfile();
        $user = User::factory()->customer()->create([
            'email' => 'jetpk-user@example.test',
            'password' => Hash::make('SecretPass1'),
            'email_verified_at' => now(),
        ]);

        $this->get('/jetpk/login');

        $this->post('/login', [
            'login' => $user->email,
            'password' => 'SecretPass1',
        ])->assertRedirect('/jetpk/login/otp');

        $sentCode = null;
        Mail::assertSent(LoginOtpMail::class, function (LoginOtpMail $mail) use (&$sentCode): bool {
            $sentCode = $mail->otpCode;

            return is_string($sentCode) && strlen($sentCode) === 6;
        });

        $this->assertNotNull($sentCode);

        $this->post('/login/otp', ['otp' => $sentCode])
            ->assertRedirect();

        $this->assertAuthenticatedAs($user);
    }

    public function test_jetpk_wrong_otp_is_rejected(): void
    {
        Mail::fake();
        $this->makeJetPkProfile();
        $user = User::factory()->customer()->create([
            'email' => 'jetpk-user@example.test',
            'password' => Hash::make('SecretPass1'),
        ]);

        $this->get('/jetpk/login');

        $this->post('/login', [
            'login' => $user->email,
            'password' => 'SecretPass1',
        ]);

        $this->post('/login/otp', ['otp' => '000000'])
            ->assertSessionHasErrors('otp');

        $this->assertGuest();
    }

    public function test_master_login_does_not_require_otp(): void
    {
        Mail::fake();
        $user = User::factory()->customer()->create([
            'email' => 'master-user@example.test',
            'password' => Hash::make('SecretPass1'),
            'email_verified_at' => now(),
        ]);

        $this->post('/login', [
            'login' => $user->email,
            'password' => 'SecretPass1',
        ])->assertRedirect();

        $this->assertAuthenticatedAs($user);
        Mail::assertNothingSent();
    }

    public function test_jetpk_login_otp_without_pending_session_redirects_to_login(): void
    {
        $this->makeJetPkProfile();

        $this->get('/jetpk/login/otp')
            ->assertRedirect('/jetpk/login');
    }

    public function test_jetpk_login_otp_with_pending_session_renders_200(): void
    {
        Mail::fake();
        $this->makeJetPkProfile();
        $user = User::factory()->customer()->create([
            'email' => 'jetpk-user@example.test',
            'password' => Hash::make('SecretPass1'),
        ]);

        $this->get('/jetpk/login');
        $this->post('/login', [
            'login' => $user->email,
            'password' => 'SecretPass1',
        ])->assertRedirect('/jetpk/login/otp');

        $this->get('/jetpk/login/otp')
            ->assertOk()
            ->assertSee('Verify your sign-in', false);
    }

    public function test_jetpk_unknown_route_renders_jetpk_404(): void
    {
        $this->makeJetPkProfile();

        $this->get('/jetpk/this-page-does-not-exist-jetpk-hotfix')
            ->assertNotFound()
            ->assertSee('Page not found', false)
            ->assertSee('JetPakistan', false);
    }

    public function test_jetpk_error_views_compile(): void
    {
        $this->makeJetPkProfile();
        $this->get('/jetpk/login');

        foreach (['403', '404', '419', '429', '500', '503'] as $code) {
            $view = 'themes.frontend.jetpakistan.errors.'.$code;
            $this->assertTrue(view()->exists($view), 'Missing view: '.$view);
            $html = view($view, ['message' => 'Test message'])->render();
            $this->assertStringContainsString('JetPakistan', $html);
        }

        $this->assertTrue(view()->exists('themes.frontend.jetpakistan.errors.partials.shell'));
        $otpView = client_view('auth.login-otp', 'frontend');
        $this->assertTrue(view()->exists($otpView), 'Missing OTP view: '.$otpView);
        view($otpView, [
            'maskedEmail' => 'j***@example.test',
            'resendAvailableIn' => 0,
        ])->render();
    }

    public function test_login_otp_routes_are_registered_for_client_parity(): void
    {
        $this->assertTrue(Route::has('login.otp'));
        $this->assertTrue(Route::has('login.otp.verify'));
        $this->assertTrue(Route::has('login.otp.resend'));
        $this->assertTrue(Route::has('client.parity.login.otp'));
    }

    public function test_jetpk_platform_admin_can_receive_otp_mail(): void
    {
        Mail::fake();
        $this->makeJetPkProfile();
        $user = User::factory()->create([
            'email' => 'admin-otp@example.test',
            'password' => Hash::make('SecretPass1'),
            'account_type' => \App\Enums\AccountType::PlatformAdmin,
            'email_verified_at' => now(),
        ]);

        $this->get('/jetpk/login');

        $this->post('/login', [
            'login' => $user->email,
            'password' => 'SecretPass1',
        ])->assertRedirect('/jetpk/login/otp');

        Mail::assertSent(LoginOtpMail::class, function (LoginOtpMail $mail) use ($user): bool {
            return $mail->hasTo($user->email)
                && $mail->envelope()->subject === 'Your JetPakistan login OTP';
        });
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
