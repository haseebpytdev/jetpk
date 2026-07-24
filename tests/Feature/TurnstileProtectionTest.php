<?php

namespace Tests\Feature;

use App\Enums\SupportTicketCategory;
use App\Support\Security\TurnstileVerifier;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Support\PublicBookingPassengersPayload;
use Tests\Support\PublicCheckoutTestDoubles;
use Tests\TestCase;

class TurnstileProtectionTest extends TestCase
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

    protected function enableTurnstile(): void
    {
        config([
            'services.turnstile.enabled' => true,
            'services.turnstile.site_key' => 'test-site-key',
            'services.turnstile.secret_key' => 'test-secret-key',
        ]);
    }

    protected function canonicalRequest(): static
    {
        return $this;
    }

    public function test_support_page_includes_turnstile_widget_when_enabled(): void
    {
        $this->enableTurnstile();
        $this->seed(OtaFoundationSeeder::class);

        $html = $this->get(route('support'))->assertOk()->getContent();

        $this->assertStringContainsString('cf-turnstile', $html);
    }

    public function test_support_submission_requires_turnstile_when_enabled(): void
    {
        $this->enableTurnstile();
        $this->seed(OtaFoundationSeeder::class);

        $this->post(route('support.store'), [
            'form_type' => 'support',
            'name' => 'Jane Guest',
            'email' => 'guest@example.com',
            'subject' => 'Payment help',
            'category' => SupportTicketCategory::Payment->value,
            'body' => 'I need help uploading payment proof.',
        ])->assertSessionHasErrors(TurnstileVerifier::RESPONSE_FIELD, null, 'supportRequest');
    }

    public function test_booking_lookup_includes_turnstile_widget_when_enabled(): void
    {
        $this->enableTurnstile();

        $html = $this->get(route('booking.lookup'))->assertOk()->getContent();

        $this->assertStringContainsString('cf-turnstile', $html);
        $this->assertStringContainsString('jp-page--lookup', $html);
    }

    public function test_booking_lookup_submission_requires_turnstile_when_enabled(): void
    {
        $this->enableTurnstile();

        $this->post(route('lookup-booking.submit'), [
            'booking_reference' => 'ABC123',
            'email' => 'guest@example.com',
        ])->assertSessionHasErrors(TurnstileVerifier::RESPONSE_FIELD);
    }

    public function test_checkout_review_desktop_does_not_include_turnstile_when_enabled(): void
    {
        $this->enableTurnstile();
        $this->seed(OtaFoundationSeeder::class);
        PublicCheckoutTestDoubles::bind($this, now()->addWeek()->format('Y-m-d'), 'LHE', 'DXB');
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $depart = now()->addWeek()->format('Y-m-d');
        $this->post('/booking/passengers', array_merge(
            PublicBookingPassengersPayload::merge([
                'flight_id' => PublicCheckoutTestDoubles::OFFER_ID,
                'offer_id' => PublicCheckoutTestDoubles::OFFER_ID,
                'from' => 'LHE',
                'to' => 'DXB',
                'depart' => $depart,
                'email' => 'checkout@example.com',
            ]),
            PublicBookingPassengersPayload::internationalDocuments(),
        ))->assertRedirect(route('booking.review'));

        $html = $this->get(route('booking.review'))->assertOk()->getContent();

        $this->assertStringNotContainsString('cf-turnstile', $html);
        $this->assertStringNotContainsString('challenges.cloudflare.com/turnstile/v0/api.js', $html);
    }

    public function test_checkout_review_does_not_include_turnstile_when_enabled(): void
    {
        $this->enableTurnstile();
        $this->seed(OtaFoundationSeeder::class);
        PublicCheckoutTestDoubles::bind($this, now()->addWeek()->format('Y-m-d'), 'LHE', 'DXB');
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $depart = now()->addWeek()->format('Y-m-d');
        $this->post('/booking/passengers', array_merge(
            PublicBookingPassengersPayload::merge([
                'flight_id' => PublicCheckoutTestDoubles::OFFER_ID,
                'offer_id' => PublicCheckoutTestDoubles::OFFER_ID,
                'from' => 'LHE',
                'to' => 'DXB',
                'depart' => $depart,
                'email' => 'checkout-mobile@example.com',
            ]),
            PublicBookingPassengersPayload::internationalDocuments(),
        ))->assertRedirect(route('booking.review'));

        $html = $this->canonicalRequest()
            ->get(route('booking.review'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('cf-turnstile', $html);
        $this->assertStringContainsString('jp-page--checkout', $html);
    }

    public function test_checkout_passengers_page_does_not_include_turnstile_when_enabled(): void
    {
        $this->enableTurnstile();
        $this->seed(OtaFoundationSeeder::class);
        PublicCheckoutTestDoubles::bind($this, now()->addWeek()->format('Y-m-d'), 'LHE', 'DXB');
        $depart = now()->addWeek()->format('Y-m-d');
        $url = '/booking/passengers?flight_id='.PublicCheckoutTestDoubles::OFFER_ID.'&offer_id='.PublicCheckoutTestDoubles::OFFER_ID.'&search_id=sid-turnstile&from=LHE&to=DXB&depart='.$depart;

        $desktopHtml = $this->get($url)->assertOk()->getContent();
        $canonicalHtml = $this->canonicalRequest()->get($url)->assertOk()->getContent();

        $this->assertStringNotContainsString('cf-turnstile', $desktopHtml);
        $this->assertStringNotContainsString('cf-turnstile', $canonicalHtml);
    }

    public function test_registration_desktop_does_not_include_turnstile_when_enabled(): void
    {
        $this->enableTurnstile();

        $html = $this->get(route('register'))->assertOk()->getContent();

        $this->assertStringNotContainsString('cf-turnstile', $html);
    }

    public function test_registration_does_not_include_turnstile_when_enabled(): void
    {
        $this->enableTurnstile();

        $html = $this->canonicalRequest()
            ->get(route('register'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('cf-turnstile', $html);
        $this->assertStringContainsString('jp-auth-form', $html);
    }

    public function test_registration_does_not_require_turnstile_when_enabled(): void
    {
        $this->enableTurnstile();

        $this->post(route('register'), [
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => 'jane@example.com',
            'mobile_country_code' => '+92',
            'mobile' => '3001234567',
            'password' => 'SecurePass1!',
            'password_confirmation' => 'SecurePass1!',
            'security_answer' => 5,
            'terms' => '1',
        ])->assertSessionDoesntHaveErrors(TurnstileVerifier::RESPONSE_FIELD);
    }

    public function test_agent_registration_desktop_does_not_include_turnstile_when_enabled(): void
    {
        $this->enableTurnstile();

        $html = $this->get(route('agent.register.form'))->assertOk()->getContent();

        $this->assertStringNotContainsString('cf-turnstile', $html);
    }

    public function test_agent_registration_does_not_include_turnstile_when_enabled(): void
    {
        $this->enableTurnstile();

        $html = $this->canonicalRequest()
            ->get(route('agent.register.form'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('cf-turnstile', $html);
    }

    public function test_forgot_password_desktop_does_not_include_or_require_turnstile_when_enabled(): void
    {
        $this->enableTurnstile();

        $html = $this->get(route('password.request'))->assertOk()->getContent();
        $this->assertStringNotContainsString('cf-turnstile', $html);

        $this->post(route('password.email'), [
            'email' => 'user@example.com',
        ])->assertSessionDoesntHaveErrors(TurnstileVerifier::RESPONSE_FIELD);
    }

    public function test_forgot_password_does_not_include_or_require_turnstile_when_enabled(): void
    {
        $this->enableTurnstile();

        $html = $this->canonicalRequest()
            ->get(route('password.request'))
            ->assertOk()
            ->getContent();
        $this->assertStringNotContainsString('cf-turnstile', $html);
        $this->assertStringContainsString('jp-auth-form', $html);

        $this->canonicalRequest()->post(route('password.email'), [
            'email' => 'user@example.com',
        ])->assertSessionDoesntHaveErrors(TurnstileVerifier::RESPONSE_FIELD);
    }

    public function test_reset_password_desktop_does_not_include_or_require_turnstile_when_enabled(): void
    {
        $this->enableTurnstile();

        $html = $this->get(route('password.reset', ['token' => 'reset-token']))->assertOk()->getContent();
        $this->assertStringNotContainsString('cf-turnstile', $html);

        $this->post(route('password.store'), $this->resetPasswordPayload())
            ->assertSessionDoesntHaveErrors(TurnstileVerifier::RESPONSE_FIELD);
    }

    public function test_reset_password_does_not_include_or_require_turnstile_when_enabled_on_canonical_view(): void
    {
        $this->enableTurnstile();

        $html = $this->canonicalRequest()
            ->get(route('password.reset', ['token' => 'reset-token']))
            ->assertOk()
            ->getContent();
        $this->assertStringNotContainsString('cf-turnstile', $html);

        $this->canonicalRequest()->post(route('password.store'), $this->resetPasswordPayload())
            ->assertSessionDoesntHaveErrors(TurnstileVerifier::RESPONSE_FIELD);
    }

    public function test_final_booking_submit_does_not_require_turnstile_when_enabled(): void
    {
        $this->enableTurnstile();
        $this->seed(OtaFoundationSeeder::class);
        PublicCheckoutTestDoubles::bind($this, now()->addWeek()->format('Y-m-d'), 'LHE', 'DXB');
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $depart = now()->addWeek()->format('Y-m-d');
        $this->post('/booking/passengers', array_merge(
            PublicBookingPassengersPayload::merge([
                'flight_id' => PublicCheckoutTestDoubles::OFFER_ID,
                'offer_id' => PublicCheckoutTestDoubles::OFFER_ID,
                'from' => 'LHE',
                'to' => 'DXB',
                'depart' => $depart,
                'email' => 'submit@example.com',
            ]),
            PublicBookingPassengersPayload::internationalDocuments(),
        ))->assertRedirect(route('booking.review'));

        $response = $this->post(route('booking.review'), ['booking_method' => 'pay_later']);

        $response->assertSessionDoesntHaveErrors(TurnstileVerifier::RESPONSE_FIELD);
    }

    public function test_login_page_does_not_include_turnstile_widget_when_enabled(): void
    {
        $this->enableTurnstile();

        $html = $this->get(route('login'))->assertOk()->getContent();

        $this->assertStringNotContainsString('cf-turnstile', $html);
        $this->assertStringNotContainsString('challenges.cloudflare.com/turnstile/v0/api.js', $html);
    }

    public function test_enabled_valid_mock_token_passes_turnstile_gate_on_booking_lookup(): void
    {
        $this->enableTurnstile();

        Http::fake([
            'challenges.cloudflare.com/*' => Http::response(['success' => true]),
        ]);

        $this->post(route('lookup-booking.submit'), [
            'booking_reference' => 'ABC123',
            'email' => 'guest@example.com',
            TurnstileVerifier::RESPONSE_FIELD => 'valid-mock-token',
        ])->assertSessionDoesntHaveErrors(TurnstileVerifier::RESPONSE_FIELD);
    }

    /**
     * @return array<string, string>
     */
    protected function resetPasswordPayload(): array
    {
        return [
            'token' => 'invalid-token',
            'email' => 'user@example.com',
            'password' => 'New-Secure-Password1!',
            'password_confirmation' => 'New-Secure-Password1!',
        ];
    }
}
