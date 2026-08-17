<?php

namespace Tests\Feature\Guest;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Services\Customer\GuestBookingAccessService;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuestBookingDetailJsonTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_token_receives_authoritative_guest_detail_json(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $booking = $this->guestBooking();
        $token = $this->guestToken($booking);

        $this->getJson(route('guest.bookings.show', ['booking' => $booking, 'token' => $token]))
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('source', 'guest_lookup')
            ->assertJsonPath('booking_reference', $booking->booking_reference)
            ->assertJsonStructure([
                'payment_status' => ['code', 'label'],
                'booking_status' => ['code', 'label'],
                'ticketing_status' => ['code', 'label'],
                'capabilities' => ['mutation_urls', 'blade_fallback_urls'],
                'blade_fallback_url',
            ])
            ->assertJsonMissing(['supplier_payload', 'gateway_secret']);
    }

    public function test_invalid_token_receives_generic_denial_json(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $booking = $this->guestBooking();

        $this->getJson(route('guest.bookings.show', ['booking' => $booking, 'token' => 'invalid-token']))
            ->assertForbidden()
            ->assertJsonPath('ok', false)
            ->assertJsonPath('code', 'access_denied')
            ->assertJsonPath('message', 'Access denied.');
    }

    public function test_mismatched_booking_token_pair_rejected(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $bookingA = $this->guestBooking(['booking_reference' => 'GUEST-A-01']);
        $bookingB = $this->guestBooking(['booking_reference' => 'GUEST-B-01']);
        $tokenForA = $this->guestToken($bookingA);

        $this->getJson(route('guest.bookings.show', ['booking' => $bookingB, 'token' => $tokenForA]))
            ->assertForbidden()
            ->assertJsonPath('ok', false);
    }

    public function test_blade_html_fallback_remains_available(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $booking = $this->guestBooking();
        $token = $this->guestToken($booking);

        // HTML presentation is owned by Next; Laravel keeps guest-safe JSON.
        $this->get(route('guest.bookings.show', ['booking' => $booking, 'token' => $token]))
            ->assertRedirect();

        $payload = $this->getJson(route('guest.bookings.show', ['booking' => $booking, 'token' => $token]).'?format=json')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->json();

        $this->assertSame($booking->booking_reference, $payload['booking_reference'] ?? null);
        $this->assertNotEmpty($payload['blade_fallback_url'] ?? null);
    }

    public function test_json_and_blade_reference_same_booking(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $booking = $this->guestBooking();
        $token = $this->guestToken($booking);

        $json = $this->getJson(route('guest.bookings.show', ['booking' => $booking, 'token' => $token]))
            ->assertOk()
            ->json('booking_reference');

        $this->get(route('guest.bookings.show', ['booking' => $booking, 'token' => $token]))
            ->assertRedirect();

        $redirectedJson = $this->getJson(route('guest.bookings.show', ['booking' => $booking, 'token' => $token]).'?format=json')
            ->assertOk()
            ->json('booking_reference');

        $this->assertSame($booking->booking_reference, $json);
        $this->assertSame($booking->booking_reference, $redirectedJson);
    }

    public function test_lookup_json_request_returns_safe_internal_redirect_url(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        config([
            'services.turnstile.enabled' => false,
            'services.turnstile.site_key' => null,
            'services.turnstile.secret_key' => null,
        ]);

        $booking = $this->guestBooking();

        $response = $this->postJson(route('lookup-booking.submit'), [
            'booking_reference' => $booking->booking_reference,
            'email' => $booking->contact?->email,
        ])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonStructure(['redirect_url']);

        $redirectUrl = (string) $response->json('redirect_url');
        $this->assertStringContainsString('/guest/bookings/'.$booking->id.'/access/', $redirectUrl);
    }

    public function test_guest_payment_proof_json_mutation_requires_valid_token(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $booking = $this->guestBooking();
        $token = $this->guestToken($booking);

        $this->postJson(route('guest.bookings.payment-proof', ['booking' => $booking, 'token' => $token]), [
            'method' => 'bank_transfer',
            'amount' => 5000,
            'payment_reference' => 'REF-01',
        ])
            ->assertCreated()
            ->assertJsonPath('ok', true);

        $this->postJson(route('guest.bookings.payment-proof', ['booking' => $booking, 'token' => 'bad-token']), [
            'method' => 'bank_transfer',
            'amount' => 5000,
        ])
            ->assertForbidden()
            ->assertJsonPath('ok', false);
    }

    protected function guestBooking(array $overrides = []): Booking
    {
        $booking = Booking::factory()->create(array_merge([
            'customer_id' => null,
            'status' => BookingStatus::PaymentPending,
            'payment_status' => 'unpaid',
            'balance_due' => 5000,
            'booking_reference' => 'GUEST-'.strtoupper((string) fake()->unique()->numberBetween(1000, 9999)),
        ], $overrides));

        if (! $booking->contact) {
            $booking->contact()->create([
                'email' => 'guestmatch@example.test',
                'phone' => '03001234567',
                'country' => 'PK',
            ]);
        }

        if ($booking->passengers()->count() === 0) {
            $booking->passengers()->create([
                'passenger_index' => 0,
                'title' => 'Mr',
                'first_name' => 'Ali',
                'last_name' => 'Khan',
                'is_lead_passenger' => true,
            ]);
        }

        $booking->fareBreakdown()->firstOrCreate([], [
            'base_fare' => 4000,
            'taxes' => 500,
            'fees' => 0,
            'markup' => 500,
            'discount' => 0,
            'total' => 5000,
            'currency' => 'PKR',
        ]);

        return $booking->fresh(['contact', 'passengers', 'fareBreakdown']);
    }

    protected function guestToken(Booking $booking): string
    {
        return app(GuestBookingAccessService::class)->createTokenForBooking(
            $booking,
            $booking->contact?->email,
            $booking->contact?->phone,
        );
    }
}
