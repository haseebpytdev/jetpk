<?php

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Enums\UserAccountStatus;
use App\Http\Middleware\PersistClientPreviewContext;
use App\Mail\LoginOtpMail;
use App\Models\ClientProfile;
use App\Models\ClientProfileBranding;
use App\Models\ClientProfileModule;
use App\Models\SocialAccount;
use App\Models\User;
use App\Support\Client\ClientProfileConfigReader;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Tests\Support\PublicCheckoutTestDoubles;
use Tests\TestCase;

class SocialAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_redirect_route_works_for_google_and_facebook(): void
    {
        foreach (['google', 'facebook'] as $provider) {
            config([
                'services.'.$provider.'.client_id' => $provider.'-client-id',
                'services.'.$provider.'.client_secret' => $provider.'-client-secret',
                'services.'.$provider.'.redirect' => 'https://example.test/auth/'.$provider.'/callback',
            ]);

            $driver = \Mockery::mock(Provider::class);
            $driver->shouldReceive('redirectUrl')
                ->with('https://example.test/auth/'.$provider.'/callback')
                ->andReturnSelf();
            $driver->shouldReceive('redirect')->once()->andReturn(redirect('https://oauth.example/'.$provider));
            Socialite::shouldReceive('driver')->with($provider)->andReturn($driver);

            $this->get('/auth/'.$provider.'/redirect')
                ->assertRedirect('https://oauth.example/'.$provider);
        }
    }

    public function test_redirect_is_blocked_when_provider_credentials_are_missing(): void
    {
        foreach (['google', 'facebook'] as $provider) {
            config([
                'services.'.$provider.'.client_id' => null,
                'services.'.$provider.'.client_secret' => null,
                'services.'.$provider.'.redirect' => null,
            ]);

            $this->get('/auth/'.$provider.'/redirect')
                ->assertRedirect(route('login'))
                ->assertSessionHasErrors('social');
        }
    }

    public function test_callback_is_blocked_when_provider_credentials_are_missing(): void
    {
        config([
            'services.google.client_id' => null,
            'services.google.client_secret' => null,
            'services.google.redirect' => null,
        ]);

        $this->get('/auth/google/callback')
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('social');
    }

    public function test_unsupported_provider_is_rejected(): void
    {
        $this->get('/auth/instagram/redirect')->assertNotFound();
        $this->get('/auth/instagram/callback')->assertNotFound();
    }

    public function test_callback_creates_customer_user_and_social_account(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $this->configureGoogleOAuth();
        $socialiteUser = $this->fakeSocialiteUser('gid-100', 'social-new@example.com', 'Social New');
        $this->mockCallbackDriver('google', $socialiteUser);

        $response = $this->get('/auth/google/callback');

        $response->assertRedirect(route('auth.google.complete-profile'));
        $user = User::query()->where('email', 'social-new@example.com')->firstOrFail();
        $this->assertSame(AccountType::Customer, $user->account_type);
        $this->assertSame(UserAccountStatus::Active, $user->status);
        $this->assertNotNull($user->email_verified_at);
        $this->assertDatabaseHas('social_accounts', [
            'user_id' => $user->id,
            'provider' => 'google',
            'provider_id' => 'gid-100',
        ]);
    }

    public function test_existing_email_links_social_account(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $this->configureGoogleOAuth();
        $user = User::factory()->create([
            'email' => 'linked@example.com',
            'account_type' => AccountType::Customer,
            'status' => UserAccountStatus::Active,
            'meta' => [
                'first_name' => 'Linked',
                'last_name' => 'User',
                'phone' => '+923001234567',
            ],
        ]);
        $socialiteUser = $this->fakeSocialiteUser('gid-200', 'linked@example.com', 'Linked User');
        $this->mockCallbackDriver('google', $socialiteUser);

        $response = $this->get('/auth/google/callback');

        $response->assertRedirect('/customer/bookings');
        $this->assertDatabaseHas('social_accounts', [
            'user_id' => $user->id,
            'provider' => 'google',
            'provider_id' => 'gid-200',
        ]);
    }

    public function test_existing_social_account_logs_user_in(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $this->configureFacebookOAuth();
        $user = User::factory()->create([
            'account_type' => AccountType::Customer,
            'status' => UserAccountStatus::Active,
        ]);
        SocialAccount::query()->create([
            'user_id' => $user->id,
            'provider' => 'facebook',
            'provider_id' => 'fb-300',
        ]);
        $socialiteUser = $this->fakeSocialiteUser('fb-300', 'ignored@example.com', 'Existing Social');
        $this->mockCallbackDriver('facebook', $socialiteUser);

        $response = $this->get('/auth/facebook/callback');

        $response->assertRedirect('/customer/bookings');
        $this->assertAuthenticatedAs($user);
    }

    public function test_callback_redirects_by_login_destination(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $this->configureGoogleOAuth();
        $socialiteUser = $this->fakeSocialiteUser('gid-500', 'dest@example.com', 'Dest User');
        $this->mockCallbackDriver('google', $socialiteUser);

        $this->get('/auth/google/callback')->assertRedirect(route('auth.google.complete-profile'));
    }

    public function test_existing_staff_admin_or_agent_email_cannot_social_login(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $this->configureGoogleOAuth();
        User::factory()->create([
            'email' => 'staff-only@example.com',
            'account_type' => AccountType::Staff,
            'status' => UserAccountStatus::Active,
        ]);
        $socialiteUser = $this->fakeSocialiteUser('gid-700', 'staff-only@example.com', 'Staff User');
        $this->mockCallbackDriver('google', $socialiteUser);

        $this->get('/auth/google/callback')
            ->assertRedirect('/login')
            ->assertSessionHasErrors('social');
        $this->assertGuest();
        $this->assertDatabaseMissing('social_accounts', [
            'provider' => 'google',
            'provider_id' => 'gid-700',
        ]);
    }

    public function test_privileged_user_without_social_link_shows_profile_link_message(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $this->configureGoogleOAuth();
        User::factory()->create([
            'email' => 'agent-only@example.com',
            'account_type' => AccountType::Agent,
            'status' => UserAccountStatus::Active,
        ]);
        $socialiteUser = $this->fakeSocialiteUser('gid-701', 'agent-only@example.com', 'Agent User');
        $this->mockCallbackDriver('google', $socialiteUser);

        $this->get('/auth/google/callback')
            ->assertRedirect('/login')
            ->assertSessionHasErrors([
                'social' => 'For admin, staff, or agent accounts, please log in with your password first and link Google from your profile.',
            ]);
    }

    public function test_promoted_customer_with_linked_google_can_social_login(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $this->configureGoogleOAuth();
        $user = User::factory()->create([
            'email' => 'promoted@example.com',
            'account_type' => AccountType::Staff,
            'status' => UserAccountStatus::Active,
        ]);
        SocialAccount::query()->create([
            'user_id' => $user->id,
            'provider' => 'google',
            'provider_id' => 'gid-800',
        ]);
        $socialiteUser = $this->fakeSocialiteUser('gid-800', 'promoted@example.com', 'Promoted User');
        $this->mockCallbackDriver('google', $socialiteUser);

        $this->get('/auth/google/callback')
            ->assertRedirect('/staff');
        $this->assertAuthenticatedAs($user);
    }

    public function test_existing_customer_email_does_not_create_duplicate_user(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $this->configureGoogleOAuth();
        User::factory()->create([
            'email' => 'linked@example.com',
            'account_type' => AccountType::Customer,
            'status' => UserAccountStatus::Active,
            'meta' => [
                'first_name' => 'Linked',
                'last_name' => 'User',
                'phone' => '+923001234567',
            ],
        ]);
        $beforeCount = User::query()->count();
        $socialiteUser = $this->fakeSocialiteUser('gid-201', 'linked@example.com', 'Linked User');
        $this->mockCallbackDriver('google', $socialiteUser);

        $this->get('/auth/google/callback')->assertRedirect('/customer/bookings');

        $this->assertSame($beforeCount, User::query()->count());
    }

    public function test_authenticated_user_can_link_google_from_profile(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $this->configureGoogleOAuth();
        $user = User::factory()->create([
            'email' => 'staff-link@example.com',
            'account_type' => AccountType::Staff,
            'status' => UserAccountStatus::Active,
        ]);
        $socialiteUser = $this->fakeSocialiteUser('gid-900', 'staff-link@example.com', 'Staff Link');
        $callbackDriver = \Mockery::mock(Provider::class);
        $callbackDriver->shouldReceive('redirectUrl')
            ->with('https://example.test/auth/google/callback')
            ->andReturnSelf();
        $callbackDriver->shouldReceive('user')->once()->andReturn($socialiteUser);
        Socialite::shouldReceive('driver')->with('google')->andReturn($callbackDriver);

        $this->actingAs($user)
            ->withSession(['social.link_intent' => 'google'])
            ->get('/auth/google/callback')
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHas('status', 'social-linked');

        $this->assertDatabaseHas('social_accounts', [
            'user_id' => $user->id,
            'provider' => 'google',
            'provider_id' => 'gid-900',
        ]);
    }

    public function test_jetpk_redirect_stores_client_slug_for_shared_callback(): void
    {
        $this->makeJetPkProfile();
        $this->get('/jetpk/login')->assertOk();
        $this->configureGoogleOAuth();

        $driver = \Mockery::mock(Provider::class);
        $driver->shouldReceive('redirectUrl')
            ->with('https://example.test/auth/google/callback')
            ->andReturnSelf();
        $driver->shouldReceive('redirect')->once()->andReturn(redirect('https://oauth.example/google'));
        Socialite::shouldReceive('driver')->with('google')->andReturn($driver);

        $this->get('/auth/google/redirect')
            ->assertRedirect('https://oauth.example/google')
            ->assertSessionHas(PersistClientPreviewContext::SESSION_KEY, 'jetpk');
    }

    public function test_client_parity_google_redirect_route_is_registered(): void
    {
        $this->makeJetPkProfile();
        $this->assertTrue(Route::has('client.parity.social.redirect'));
        $this->get('/jetpk/auth/google/link')->assertRedirect('/jetpk/login');
    }

    public function test_client_prefixed_google_callback_redirects_to_client_customer_dashboard(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $this->makeProfile(['slug' => 'demo', 'name' => 'Demo Client']);
        $this->configureGoogleOAuth();
        $user = User::factory()->create([
            'email' => 'demo-google@example.com',
            'account_type' => AccountType::Customer,
            'status' => UserAccountStatus::Active,
            'meta' => [
                'first_name' => 'Demo',
                'last_name' => 'Customer',
                'phone' => '+923001234567',
            ],
        ]);
        SocialAccount::query()->create([
            'user_id' => $user->id,
            'provider' => 'google',
            'provider_id' => 'gid-demo',
        ]);
        $this->mockCallbackDriver('google', $this->fakeSocialiteUser('gid-demo', 'demo-google@example.com', 'Demo Customer'));

        $this->withSession([
            PersistClientPreviewContext::SESSION_KEY => 'demo',
        ])
            ->get('/auth/google/callback')
            ->assertRedirect('/demo/customer/bookings');

        $this->assertAuthenticatedAs($user);
    }

    public function test_jetpk_google_callback_requires_login_otp_when_configured(): void
    {
        Mail::fake();
        $this->seed(OtaFoundationSeeder::class);
        $this->makeJetPkProfile();
        $this->configureGoogleOAuth();
        $user = User::factory()->create([
            'email' => 'jetpk-otp@example.com',
            'account_type' => AccountType::Customer,
            'status' => UserAccountStatus::Active,
            'meta' => [
                'first_name' => 'Jet',
                'last_name' => 'Otp',
                'phone' => '+923001234567',
            ],
        ]);
        SocialAccount::query()->create([
            'user_id' => $user->id,
            'provider' => 'google',
            'provider_id' => 'gid-jetpk-otp',
        ]);
        $this->mockCallbackDriver('google', $this->fakeSocialiteUser('gid-jetpk-otp', 'jetpk-otp@example.com', 'Jet Otp'));

        $this->withSession([
            PersistClientPreviewContext::SESSION_KEY => 'jetpk',
        ])
            ->get('/auth/google/callback')
            ->assertRedirect('/jetpk/login/otp')
            ->assertSessionHas('status');

        $this->assertGuest();
        Mail::assertSent(LoginOtpMail::class);
    }

    public function test_client_prefixed_oauth_callback_route_is_not_registered(): void
    {
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('client.parity.social.callback'));
    }

    public function test_social_checkout_redirects_back_to_checkout(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $this->configureGoogleOAuth();
        $checkoutPath = '/booking/passengers?flight_id='.PublicCheckoutTestDoubles::OFFER_ID.'&offer_id='.PublicCheckoutTestDoubles::OFFER_ID.'&from=LHE&to=DXB&depart=2026-06-20&trip_type=one_way&adults=1&children=0&infants=0';

        $callbackDriver = \Mockery::mock(Provider::class);
        $callbackDriver->shouldReceive('redirectUrl')
            ->with('https://example.test/auth/google/callback')
            ->andReturnSelf();
        $callbackDriver->shouldReceive('user')->once()->andReturn(
            $this->fakeSocialiteUser('gid-901', 'checkout-social@example.com', 'Checkout Social')
        );
        Socialite::shouldReceive('driver')->with('google')->andReturn($callbackDriver);

        $this->withSession(['url.intended' => url($checkoutPath)])
            ->get('/auth/google/callback')
            ->assertRedirect(route('auth.google.complete-profile'));

        $this->assertSame(url($checkoutPath), session('url.intended'));
    }

    private function configureGoogleOAuth(): void
    {
        config([
            'services.google.client_id' => 'google-client-id',
            'services.google.client_secret' => 'google-client-secret',
            'services.google.redirect' => 'https://example.test/auth/google/callback',
        ]);
    }

    private function configureFacebookOAuth(): void
    {
        config([
            'services.facebook.client_id' => 'facebook-client-id',
            'services.facebook.client_secret' => 'facebook-client-secret',
            'services.facebook.redirect' => 'https://example.test/auth/facebook/callback',
        ]);
    }

    private function mockCallbackDriver(string $provider, SocialiteUser $socialiteUser): void
    {
        $driver = \Mockery::mock(Provider::class);
        $driver->shouldReceive('redirectUrl')
            ->with(config('services.'.$provider.'.redirect'))
            ->andReturnSelf();
        $driver->shouldReceive('user')->once()->andReturn($socialiteUser);
        Socialite::shouldReceive('driver')->with($provider)->andReturn($driver);
    }

    private function fakeSocialiteUser(string $id, string $email, string $name): SocialiteUser
    {
        $user = new SocialiteUser;
        $user->id = $id;
        $user->name = $name;
        $user->email = $email;
        $user->avatar = 'https://example.test/avatar.png';
        $user->user = ['email_verified' => true];

        return $user;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeProfile(array $overrides = []): ClientProfile
    {
        $profile = ClientProfile::query()->create(array_merge([
            'name' => 'Test Client',
            'slug' => 'test-client-'.uniqid(),
            'domain' => null,
            'environment' => 'staging',
            'active_frontend_theme' => 'v1-classic',
            'active_admin_theme' => 'v1-classic',
            'active_staff_theme' => 'v1-classic',
            'asset_profile' => 'test-assets',
            'default_locale' => 'en',
            'timezone' => 'Asia/Karachi',
            'currency' => 'PKR',
            'is_master_profile' => false,
            'is_active' => true,
        ], $overrides));

        foreach (ClientProfileConfigReader::MODULE_KEYS as $moduleKey) {
            ClientProfileModule::query()->create([
                'client_profile_id' => $profile->id,
                'module_key' => $moduleKey,
                'enabled' => false,
            ]);
        }

        return $profile;
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
