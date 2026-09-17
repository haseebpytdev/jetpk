<?php

namespace Tests\Feature\Auth;

use App\Mail\LoginOtpMail;
use App\Models\ClientProfile;
use App\Models\ClientProfileBranding;
use App\Models\ClientProfileModule;
use App\Models\User;
use App\Support\Auth\ClientLoginOtpGate;
use App\Support\Client\ClientProfileConfigReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\Support\Auth\ConfiguresAuthTestEnvironment;
use Tests\TestCase;

class JetPkLoginOtpOffContractTest extends TestCase
{
    use ConfiguresAuthTestEnvironment;
    use RefreshDatabase;

    public function test_jetpk_otp_off_via_env_logs_in_without_otp_email(): void
    {
        Mail::fake();
        config([
            'ota_client.single_client_mode' => true,
            'ota_client.single_client_root' => true,
            'ota_client.slug' => 'jetpk',
            'ota_client.auth.require_login_otp' => false,
        ]);
        $this->makeJetPkProfile();

        $this->assertFalse(ClientLoginOtpGate::isRequired());

        $user = User::factory()->customer()->create([
            'email' => 'otp-off@example.test',
            'password' => Hash::make('SecretPass1'),
            'email_verified_at' => now(),
        ]);

        $this->post('/login', [
            'login' => $user->email,
            'password' => 'SecretPass1',
        ])->assertRedirect();

        $this->assertAuthenticatedAs($user);
        Mail::assertNotSent(LoginOtpMail::class);
        $this->assertFalse(session()->has('pending_login_otp'));
    }

    public function test_jetpk_otp_off_via_admin_profile_override_beats_env_on(): void
    {
        Mail::fake();
        config([
            'ota_client.single_client_mode' => true,
            'ota_client.single_client_root' => true,
            'ota_client.slug' => 'jetpk',
            'ota_client.auth.require_login_otp' => true,
        ]);
        $profile = $this->makeJetPkProfile([
            'auth' => ['require_login_otp' => false],
        ]);

        $this->assertFalse(ClientLoginOtpGate::isRequired());
        $this->assertNotNull($profile->branding);

        $user = User::factory()->customer()->create([
            'email' => 'otp-override-off@example.test',
            'password' => Hash::make('SecretPass1'),
            'email_verified_at' => now(),
        ]);

        $this->post('/login', [
            'login' => $user->email,
            'password' => 'SecretPass1',
        ])->assertRedirect();

        $this->assertAuthenticatedAs($user);
        Mail::assertNotSent(LoginOtpMail::class);
    }

    public function test_jetpk_otp_on_via_admin_profile_override_beats_env_off(): void
    {
        Mail::fake();
        config([
            'ota_client.single_client_mode' => true,
            'ota_client.single_client_root' => true,
            'ota_client.slug' => 'jetpk',
            'ota_client.auth.require_login_otp' => false,
        ]);
        $this->makeJetPkProfile([
            'auth' => ['require_login_otp' => true],
        ]);

        $this->assertTrue(ClientLoginOtpGate::isRequired());

        $user = User::factory()->customer()->create([
            'email' => 'otp-override-on@example.test',
            'password' => Hash::make('SecretPass1'),
        ]);

        $this->post('/login', [
            'login' => $user->email,
            'password' => 'SecretPass1',
        ])->assertRedirect('/login/otp');

        $this->assertGuest();
        Mail::assertSent(LoginOtpMail::class);
    }

    public function test_wrong_password_still_fails_when_otp_off(): void
    {
        Mail::fake();
        config([
            'ota_client.single_client_mode' => true,
            'ota_client.single_client_root' => true,
            'ota_client.slug' => 'jetpk',
            'ota_client.auth.require_login_otp' => false,
        ]);
        $this->makeJetPkProfile();

        $user = User::factory()->customer()->create([
            'email' => 'otp-off-badpass@example.test',
            'password' => Hash::make('SecretPass1'),
        ]);

        $this->post('/login', [
            'login' => $user->email,
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('login');

        $this->assertGuest();
        Mail::assertNothingSent();
    }

    public function test_stale_pending_otp_is_cleared_when_gate_disabled(): void
    {
        Mail::fake();
        config([
            'ota_client.single_client_mode' => true,
            'ota_client.single_client_root' => true,
            'ota_client.slug' => 'jetpk',
            'ota_client.auth.require_login_otp' => true,
        ]);
        $this->makeJetPkProfile();

        $user = User::factory()->customer()->create([
            'email' => 'stale-otp@example.test',
            'password' => Hash::make('SecretPass1'),
        ]);

        $this->post('/login', [
            'login' => $user->email,
            'password' => 'SecretPass1',
        ])->assertRedirect('/login/otp');

        $this->assertTrue(session()->has('pending_login_otp'));

        $profile = ClientProfile::query()->where('slug', 'jetpk')->firstOrFail();
        $profile->branding->forceFill([
            'config' => ['auth' => ['require_login_otp' => false]],
        ])->save();

        $this->assertFalse(ClientLoginOtpGate::isRequired());

        $this->get('/login/otp')->assertRedirect();
        $this->assertFalse(session()->has('pending_login_otp'));
        Mail::assertSent(LoginOtpMail::class, 1);
    }

    /**
     * @param  array<string, mixed>  $brandingConfig
     */
    private function makeJetPkProfile(array $brandingConfig = []): ClientProfile
    {
        $profile = ClientProfile::query()->create([
            'name' => 'JetPakistan',
            'slug' => 'jetpk',
            'domain' => 'jetpakistan.pk',
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
            'config' => $brandingConfig,
        ]);

        foreach (ClientProfileConfigReader::MODULE_KEYS as $moduleKey) {
            ClientProfileModule::query()->create([
                'client_profile_id' => $profile->id,
                'module_key' => $moduleKey,
                'enabled' => true,
            ]);
        }

        return $profile->fresh(['branding']);
    }
}
