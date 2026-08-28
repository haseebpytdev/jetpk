<?php

namespace Tests\Feature\Auth;

use App\Models\Booking;
use App\Models\User;
use App\Support\Auth\BestEffortEmailVerification;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Support\FailingMailTransport;
use Tests\Support\PublicBookingPassengersPayload;
use Tests\Support\PublicCheckoutTestDoubles;
use Tests\TestCase;

/**
 * Proves SMTP / verification delivery failures never 500 or roll back customer/Draft creation.
 */
class MailFailureGracefulRegistrationTest extends TestCase
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

        $this->installFailingMailer();
    }

    public function test_registration_mail_failure_keeps_unverified_customer_without_http_500(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $this->withoutMiddleware([ValidateCsrfToken::class]);

        $email = 'reg-mail-fail@example.test';

        $response = $this->withSession(['register_security_answer' => 7])->post('/register', [
            'first_name' => 'Reg',
            'last_name' => 'Fail',
            'email' => $email,
            'mobile_country_code' => '+92',
            'mobile' => '3001234567',
            'password' => 'SecurePass99!',
            'password_confirmation' => 'SecurePass99!',
            'security_answer' => '7',
            'terms' => '1',
        ]);

        $response->assertRedirect(route('verification.notice', absolute: false));
        $response->assertSessionHas('status', 'verification-delivery-failed');
        $response->assertSessionHas('verification_delivery_failed', BestEffortEmailVerification::FAILURE_MESSAGE);

        $customer = User::query()->where('email', $email)->firstOrFail();
        $this->assertTrue($customer->isCustomer());
        $this->assertNull($customer->email_verified_at);
        $this->assertAuthenticatedAs($customer);
        $this->assertNotSame(500, $response->status());
    }

    public function test_checkout_inline_account_mail_failure_commits_draft_without_http_500(): void
    {
        $depart = now()->addWeek()->format('Y-m-d');
        $this->seed(OtaFoundationSeeder::class);
        PublicCheckoutTestDoubles::bind($this, $depart, 'LHE', 'DXB');
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $email = 'checkout-mail-fail@example.test';
        $password = 'SecurePass99!';

        $response = $this->post('/booking/passengers', array_merge(
            PublicBookingPassengersPayload::merge([
                'flight_id' => PublicCheckoutTestDoubles::OFFER_ID,
                'offer_id' => PublicCheckoutTestDoubles::OFFER_ID,
                'from' => 'LHE',
                'to' => 'DXB',
                'depart' => $depart,
                'email' => $email,
                'create_account' => '1',
                'password' => $password,
                'password_confirmation' => $password,
            ]),
            PublicBookingPassengersPayload::internationalDocuments(),
        ));

        $response->assertRedirect(route('booking.review'));
        $this->assertNotSame(500, $response->status());

        $user = User::query()->where('email', $email)->firstOrFail();
        $this->assertTrue($user->isCustomer());
        $this->assertNull($user->email_verified_at);
        $this->assertAuthenticatedAs($user);

        $booking = Booking::query()->firstOrFail();
        $this->assertSame($user->id, $booking->customer_id);
        $this->assertSame(BestEffortEmailVerification::DELIVERY_FAILURE, session(BestEffortEmailVerification::SESSION_DELIVERY_KEY));
    }

    private function installFailingMailer(): void
    {
        Mail::extend('failing', static fn () => new FailingMailTransport);

        config([
            'mail.default' => 'failing',
            'mail.mailers.failing' => [
                'transport' => 'failing',
            ],
        ]);
    }
}
