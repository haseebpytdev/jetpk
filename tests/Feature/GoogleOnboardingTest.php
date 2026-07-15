<?php

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Enums\UserAccountStatus;
use App\Mail\GoogleCustomerWelcomeMail;
use App\Models\SocialAccount;
use App\Models\User;
use App\Support\Auth\GoogleOnboarding;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Tests\Support\PublicCheckoutTestDoubles;
use Tests\TestCase;

class GoogleOnboardingTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_google_user_redirects_to_complete_profile(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $this->configureGoogleOAuth();
        $this->mockCallbackDriver($this->fakeSocialiteUser('gid-new', 'new-google@example.com', 'Social New'));

        $this->get('/auth/google/callback')
            ->assertRedirect(route('auth.google.complete-profile'));

        $this->assertAuthenticated();
        $this->assertTrue(session(GoogleOnboarding::SESSION_REQUIRED));
        $this->assertTrue(session(GoogleOnboarding::SESSION_IS_NEW));
    }

    public function test_complete_profile_saves_name_and_phone_without_changing_email(): void
    {
        Mail::fake();
        $this->seed(OtaFoundationSeeder::class);
        $user = $this->createNewGoogleCustomer('complete@example.com', 'Complete Me');

        $response = $this->actingAs($user)
            ->withSession([
                GoogleOnboarding::SESSION_REQUIRED => true,
                GoogleOnboarding::SESSION_IS_NEW => true,
            ])
            ->post(route('auth.google.complete-profile.store'), [
                'first_name' => 'Complete',
                'last_name' => 'Me',
                'mobile_country_code' => '+92',
                'mobile' => '3001234567',
            ]);

        $response->assertRedirect('/customer/bookings');
        $user->refresh();
        $this->assertSame('complete@example.com', $user->email);
        $this->assertSame('Complete Me', $user->name);
        $this->assertSame('Complete', $user->meta['first_name']);
        $this->assertSame('Me', $user->meta['last_name']);
        $this->assertSame('+923001234567', $user->meta['phone']);
        $this->assertSame('+923001234567', $user->profile?->phone);
        $this->assertFalse(session()->has(GoogleOnboarding::SESSION_REQUIRED));
        Mail::assertQueued(GoogleCustomerWelcomeMail::class, function (GoogleCustomerWelcomeMail $mail) use ($user): bool {
            return $mail->hasTo($user->email);
        });
    }

    public function test_welcome_email_uses_expected_subject(): void
    {
        Mail::fake();
        $user = User::factory()->customer()->create([
            'email' => 'welcome@example.com',
            'name' => 'Welcome User',
        ]);

        Mail::to($user->email)->queue(GoogleCustomerWelcomeMail::forUser($user));

        Mail::assertQueued(GoogleCustomerWelcomeMail::class, function (GoogleCustomerWelcomeMail $mail): bool {
            return str_contains($mail->envelope()->subject, 'your account is ready');
        });
    }

    public function test_intended_checkout_redirect_preserved_after_completion(): void
    {
        Mail::fake();
        $this->seed(OtaFoundationSeeder::class);
        $checkoutPath = '/booking/passengers?flight_id='.PublicCheckoutTestDoubles::OFFER_ID.'&offer_id='.PublicCheckoutTestDoubles::OFFER_ID.'&from=LHE&to=DXB&depart=2026-06-20&trip_type=one_way&adults=1&children=0&infants=0';
        $user = $this->createNewGoogleCustomer('checkout-onboard@example.com', 'Checkout Onboard');

        $this->actingAs($user)
            ->withSession([
                GoogleOnboarding::SESSION_REQUIRED => true,
                GoogleOnboarding::SESSION_IS_NEW => true,
                'url.intended' => url($checkoutPath),
            ])
            ->post(route('auth.google.complete-profile.store'), [
                'first_name' => 'Checkout',
                'last_name' => 'Onboard',
                'mobile_country_code' => '+92',
                'mobile' => '3001111222',
            ])
            ->assertRedirect($checkoutPath);
    }

    public function test_existing_customer_with_complete_profile_skips_onboarding(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $this->configureGoogleOAuth();
        $user = User::factory()->create([
            'email' => 'complete-linked@example.com',
            'account_type' => AccountType::Customer,
            'status' => UserAccountStatus::Active,
            'meta' => [
                'first_name' => 'Complete',
                'last_name' => 'Linked',
                'phone' => '+923001111111',
            ],
        ]);
        SocialAccount::query()->create([
            'user_id' => $user->id,
            'provider' => 'google',
            'provider_id' => 'gid-complete',
        ]);
        $this->mockCallbackDriver($this->fakeSocialiteUser('gid-complete', 'complete-linked@example.com', 'Complete Linked'));

        $this->get('/auth/google/callback')
            ->assertRedirect('/customer/bookings');
        $this->assertFalse(session()->has(GoogleOnboarding::SESSION_REQUIRED));
    }

    public function test_existing_customer_with_incomplete_profile_is_sent_to_onboarding(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $this->configureGoogleOAuth();
        $user = User::factory()->create([
            'email' => 'incomplete@example.com',
            'name' => 'Incomplete Only',
            'account_type' => AccountType::Customer,
            'status' => UserAccountStatus::Active,
        ]);
        SocialAccount::query()->create([
            'user_id' => $user->id,
            'provider' => 'google',
            'provider_id' => 'gid-incomplete',
        ]);
        $this->mockCallbackDriver($this->fakeSocialiteUser('gid-incomplete', 'incomplete@example.com', 'Incomplete Only'));

        $this->get('/auth/google/callback')
            ->assertRedirect(route('auth.google.complete-profile'));
        $this->assertTrue(session(GoogleOnboarding::SESSION_REQUIRED));
        $this->assertNotTrue(session(GoogleOnboarding::SESSION_IS_NEW));
    }

    public function test_promoted_privileged_linked_user_skips_customer_onboarding(): void
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
            'provider_id' => 'gid-promoted',
        ]);
        $this->mockCallbackDriver($this->fakeSocialiteUser('gid-promoted', 'promoted@example.com', 'Promoted User'));

        $this->get('/auth/google/callback')
            ->assertRedirect('/staff');
        $this->assertFalse(session()->has(GoogleOnboarding::SESSION_REQUIRED));
    }

    public function test_non_linked_privileged_email_is_still_blocked(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $this->configureGoogleOAuth();
        User::factory()->create([
            'email' => 'agent-only@example.com',
            'account_type' => AccountType::Agent,
            'status' => UserAccountStatus::Active,
        ]);
        $this->mockCallbackDriver($this->fakeSocialiteUser('gid-agent', 'agent-only@example.com', 'Agent User'));

        $this->get('/auth/google/callback')
            ->assertRedirect('/login')
            ->assertSessionHasErrors('social');
        $this->assertGuest();
    }

    public function test_no_duplicate_user_is_created_on_google_callback(): void
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
                'phone' => '+923009999999',
            ],
        ]);
        $beforeCount = User::query()->count();
        $this->mockCallbackDriver($this->fakeSocialiteUser('gid-dup', 'linked@example.com', 'Linked User'));

        $this->get('/auth/google/callback')->assertRedirect('/customer/bookings');

        $this->assertSame($beforeCount, User::query()->count());
    }

    public function test_middleware_redirects_incomplete_onboarding_away_from_customer_portal(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $user = User::factory()->create([
            'account_type' => AccountType::Customer,
            'status' => UserAccountStatus::Active,
            'email_verified_at' => now(),
        ]);

        $this->actingAs($user)
            ->withSession([GoogleOnboarding::SESSION_REQUIRED => true])
            ->get('/customer/bookings')
            ->assertRedirect(route('auth.google.complete-profile'));
    }

    private function createNewGoogleCustomer(string $email, string $name): User
    {
        $user = User::factory()->create([
            'email' => $email,
            'name' => $name,
            'account_type' => AccountType::Customer,
            'status' => UserAccountStatus::Active,
            'email_verified_at' => now(),
        ]);
        SocialAccount::query()->create([
            'user_id' => $user->id,
            'provider' => 'google',
            'provider_id' => 'gid-'.md5($email),
            'provider_name' => $name,
        ]);

        return $user;
    }

    private function configureGoogleOAuth(): void
    {
        config([
            'services.google.client_id' => 'google-client-id',
            'services.google.client_secret' => 'google-client-secret',
            'services.google.redirect' => 'https://example.test/auth/google/callback',
        ]);
    }

    private function mockCallbackDriver(SocialiteUser $socialiteUser): void
    {
        $driver = \Mockery::mock(Provider::class);
        $driver->shouldReceive('redirectUrl')
            ->with('https://example.test/auth/google/callback')
            ->andReturnSelf();
        $driver->shouldReceive('user')->once()->andReturn($socialiteUser);
        Socialite::shouldReceive('driver')->with('google')->andReturn($driver);
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
}
