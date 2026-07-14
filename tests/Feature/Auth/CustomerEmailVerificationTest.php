<?php

namespace Tests\Feature\Auth;

use App\Enums\AccountType;
use App\Enums\UserAccountStatus;
use App\Models\User;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Auth\Events\Verified;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class CustomerEmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.turnstile.enabled' => false,
            'services.turnstile.site_key' => null,
            'services.turnstile.secret_key' => null,
        ]);
    }

    public function test_customer_registration_sends_initial_verification_notification(): void
    {
        Notification::fake();
        Mail::fake();
        $this->seed(OtaFoundationSeeder::class);
        $this->withoutMiddleware([ValidateCsrfToken::class]);

        $response = $this->withSession(['register_security_answer' => 7])->post('/register', $this->validRegistrationPayload([
            'email' => 'new.customer@example.test',
            'security_answer' => '7',
        ]));

        $customer = User::query()->where('email', 'new.customer@example.test')->firstOrFail();

        $response->assertRedirect(route('verification.notice', absolute: false));
        $this->assertAuthenticatedAs($customer);
        $this->assertNull($customer->email_verified_at);
        Notification::assertSentTo($customer, VerifyEmail::class);
    }

    public function test_resend_sends_verification_notification(): void
    {
        Notification::fake();
        $customer = $this->customer(['email_verified_at' => null]);

        $this->actingAs($customer)
            ->post(route('verification.send'))
            ->assertRedirect();

        Notification::assertSentTo($customer, VerifyEmail::class);
    }

    public function test_signed_verification_link_marks_customer_email_verified(): void
    {
        $customer = $this->customer(['email_verified_at' => null]);

        $this->actingAs($customer)
            ->get($this->verificationUrlFor($customer))
            ->assertRedirect(route('dashboard', absolute: false).'?verified=1');

        $this->assertNotNull($customer->fresh()->email_verified_at);
    }

    public function test_logged_out_customer_clicking_signed_verification_link_is_verified_and_logged_in(): void
    {
        Event::fake([Verified::class]);
        $customer = $this->customer([
            'email' => 'verify-login@example.test',
            'email_verified_at' => null,
        ]);

        $this->get($this->verificationUrlFor($customer))
            ->assertRedirect(route('dashboard', absolute: false).'?verified=1');

        Event::assertDispatched(Verified::class);
        $this->assertAuthenticatedAs($customer);
        $this->assertNotNull($customer->fresh()->email_verified_at);

        $this->get(route('customer.dashboard'))->assertOk();
    }

    public function test_wrong_logged_in_user_clicking_customer_verification_link_switches_to_target_customer(): void
    {
        $wrongUser = $this->customer([
            'email' => 'wrong-session@example.test',
            'email_verified_at' => now(),
        ]);
        $targetCustomer = $this->customer([
            'email' => 'target-session@example.test',
            'email_verified_at' => null,
        ]);

        $this->actingAs($wrongUser)
            ->get($this->verificationUrlFor($targetCustomer))
            ->assertRedirect(route('dashboard', absolute: false).'?verified=1');

        $this->assertAuthenticatedAs($targetCustomer);
        $this->assertNotNull($targetCustomer->fresh()->email_verified_at);
    }

    public function test_signed_verification_link_rejects_tampered_hash(): void
    {
        $customer = $this->customer(['email_verified_at' => null]);

        $this->get($this->verificationUrlFor($customer, sha1('wrong-email')))
            ->assertForbidden();

        $this->assertGuest();
        $this->assertNull($customer->fresh()->email_verified_at);
    }

    public function test_signed_verification_link_rejects_expired_signature(): void
    {
        $customer = $this->customer(['email_verified_at' => null]);

        $this->get($this->verificationUrlFor($customer, expiresAt: now()->subMinute()))
            ->assertForbidden();

        $this->assertGuest();
        $this->assertNull($customer->fresh()->email_verified_at);
    }

    public function test_verified_customer_can_access_dashboard_and_unverified_customer_gets_notice(): void
    {
        $verified = $this->customer(['email_verified_at' => now()]);
        $unverified = $this->customer(['email_verified_at' => null]);

        $this->actingAs($verified)
            ->get(route('customer.dashboard'))
            ->assertOk();

        $this->actingAs($unverified)
            ->get(route('customer.dashboard'))
            ->assertRedirect(route('verification.notice'));
    }

    public function test_verification_route_is_not_behind_customer_portal_middleware(): void
    {
        $middleware = Route::getRoutes()->getByName('verification.verify')?->gatherMiddleware() ?? [];

        $this->assertNotContains('auth', $middleware);
        $this->assertContains('signed:relative', $middleware);
        $this->assertContains('throttle:6,1', $middleware);
        $this->assertNotContains('account.type:customer', $middleware);
        $this->assertNotContains('customer.email.portal.verified', $middleware);
        $this->assertNotContains('platform.module:customer_portal', $middleware);
    }

    public function test_guest_booking_lookup_stays_public(): void
    {
        $this->get(route('booking.lookup'))->assertOk();
        $this->post(route('lookup-booking.submit'), ['booking_reference' => 'ABC'])->assertSessionHasErrors('email');
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function customer(array $overrides = []): User
    {
        $this->seed(OtaFoundationSeeder::class);

        return User::factory()->create(array_merge([
            'account_type' => AccountType::Customer,
            'status' => UserAccountStatus::Active,
        ], $overrides));
    }

    private function verificationUrlFor(User $customer, ?string $hash = null, mixed $expiresAt = null): string
    {
        $path = URL::temporarySignedRoute(
            'verification.verify',
            $expiresAt ?? now()->addMinutes(30),
            [
                'id' => $customer->getKey(),
                'hash' => $hash ?? sha1($customer->getEmailForVerification()),
            ],
            false
        );

        return url($path);
    }

    /**
     * @param  array<string, mixed>  $override
     * @return array<string, mixed>
     */
    private function validRegistrationPayload(array $override = []): array
    {
        return array_merge([
            'first_name' => 'Ali',
            'last_name' => 'Khan',
            'email' => 'ali.khan@example.test',
            'mobile_country_code' => '+92',
            'mobile' => '3001234567',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'security_answer' => '7',
            'terms' => '1',
        ], $override);
    }
}
