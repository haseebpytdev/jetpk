<?php

namespace Tests\Feature\Guest;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\GuestBookingAccessToken;
use App\Services\Customer\GuestBookingAccessService;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuestTokenHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_token_rotation_invalidates_prior_active_token_atomically(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $booking = $this->guestBooking();
        $svc = app(GuestBookingAccessService::class);

        $first = $svc->createTokenForBooking($booking, 'a@example.test', null);
        $this->assertTrue($svc->validateToken($booking, $first));

        $second = $svc->createTokenForBooking($booking, 'a@example.test', null);
        $this->assertTrue($svc->validateToken($booking, $second));
        $this->assertFalse($svc->validateToken($booking, $first));

        $rows = GuestBookingAccessToken::query()->where('booking_id', $booking->id)->orderBy('id')->get();
        $this->assertCount(2, $rows);
        $this->assertTrue($rows[0]->expires_at !== null && $rows[0]->expires_at->lte(now()));
        $this->assertTrue($rows[1]->expires_at === null || $rows[1]->expires_at->gt(now()));
        $this->assertSame(hash('sha256', $second), $rows[1]->token_hash);
        $this->assertNotSame(hash('sha256', $first), $rows[1]->token_hash);
        // Raw token never stored; only sha256 hash.
        $this->assertDatabaseMissing('guest_booking_access_tokens', ['token_hash' => $first]);
    }

    public function test_rotation_does_not_affect_other_booking_or_agency_tokens(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $bookingA = $this->guestBooking(['booking_reference' => 'ROT-A-01']);
        $bookingB = $this->guestBooking(['booking_reference' => 'ROT-B-01']);
        $svc = app(GuestBookingAccessService::class);

        $tokenA = $svc->createTokenForBooking($bookingA, 'a@example.test', null);
        $tokenB = $svc->createTokenForBooking($bookingB, 'b@example.test', null);

        $svc->createTokenForBooking($bookingA, 'a@example.test', null);

        $this->assertFalse($svc->validateToken($bookingA, $tokenA));
        $this->assertTrue($svc->validateToken($bookingB, $tokenB));
    }

    public function test_lookup_redirects_to_token_free_url_and_binds_session(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        config([
            'services.turnstile.enabled' => false,
            'services.turnstile.site_key' => null,
            'services.turnstile.secret_key' => null,
        ]);

        $booking = $this->guestBooking();

        $response = $this->post(route('lookup-booking.submit'), [
            'booking_reference' => $booking->booking_reference,
            'email' => $booking->contact?->email,
        ]);

        $response->assertRedirect('/guest/bookings/'.$booking->id);
        $response->assertHeader('Referrer-Policy', 'no-referrer');
        $response->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->assertStringNotContainsString('/access/', (string) $response->headers->get('Location'));
        $response->assertSessionHas('guest_booking_access.'.$booking->id);
    }

    public function test_legacy_exchange_redirects_to_clean_url_without_token(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $booking = $this->guestBooking();
        $token = $this->guestToken($booking);

        $this->get(route('guest.bookings.exchange', ['booking' => $booking, 'token' => $token]))
            ->assertRedirect(route('guest.bookings.show', ['booking' => $booking]))
            ->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertSessionHas('guest_booking_access.'.$booking->id);

        $location = (string) $this->get(route('guest.bookings.exchange', ['booking' => $booking, 'token' => $token]))
            ->headers->get('Location');
        // Second hit: first token still valid until rotation; just ensure location token-free
        $this->assertStringNotContainsString($token, $location);
        $this->assertStringNotContainsString('/access/', $location);
    }

    public function test_session_authorizes_clean_detail_and_denies_without_session(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $booking = $this->guestBooking();
        $token = $this->guestToken($booking);

        $this->getJson(route('guest.bookings.show', ['booking' => $booking]))
            ->assertForbidden();

        $this->withSession([
            'guest_booking_access.'.$booking->id => $token,
        ])->getJson(route('guest.bookings.show', ['booking' => $booking]))
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('source', 'guest_lookup');
    }

    public function test_guest_json_does_not_leak_raw_token_in_urls_or_payload(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $booking = $this->guestBooking();
        $token = $this->guestToken($booking);

        $payload = $this->withSession([
            'guest_booking_access.'.$booking->id => $token,
        ])->getJson(route('guest.bookings.show', ['booking' => $booking]))
            ->assertOk()
            ->json();

        $encoded = json_encode($payload);
        $this->assertIsString($encoded);
        $this->assertStringNotContainsString($token, $encoded);
        $this->assertStringNotContainsString('/access/', $encoded);
        $this->assertStringNotContainsString('?token=', $encoded);
        $fallback = (string) ($payload['blade_fallback_url'] ?? '');
        $this->assertStringContainsString('/guest/bookings/'.$booking->id, $fallback);
        $this->assertStringNotContainsString('/access/', $fallback);
    }

    public function test_failed_lookup_creates_no_token(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        config([
            'services.turnstile.enabled' => false,
            'services.turnstile.site_key' => null,
            'services.turnstile.secret_key' => null,
        ]);

        $booking = $this->guestBooking();
        $before = GuestBookingAccessToken::query()->count();

        $this->post(route('lookup-booking.submit'), [
            'booking_reference' => $booking->booking_reference,
            'email' => 'wrong@example.invalid',
        ])->assertSessionHasErrors('lookup');

        $this->assertSame($before, GuestBookingAccessToken::query()->count());
    }

    public function test_cross_booking_session_denied(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $bookingA = $this->guestBooking(['booking_reference' => 'XB-A']);
        $bookingB = $this->guestBooking(['booking_reference' => 'XB-B']);
        $tokenA = $this->guestToken($bookingA);

        $this->withSession([
            'guest_booking_access.'.$bookingA->id => $tokenA,
        ])->getJson(route('guest.bookings.show', ['booking' => $bookingB]))
            ->assertForbidden();
    }

    public function test_payment_proof_authorized_via_session_without_token_in_url(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $booking = $this->guestBooking();
        $token = $this->guestToken($booking);

        $this->withSession([
            'guest_booking_access.'.$booking->id => $token,
        ])->postJson(route('guest.bookings.payment-proof', ['booking' => $booking]), [
            'method' => 'bank_transfer',
            'amount' => 5000,
            'payment_reference' => 'REF-HARDEN',
        ])
            ->assertCreated()
            ->assertJsonPath('ok', true);
    }

    protected function guestBooking(array $overrides = []): Booking
    {
        $booking = Booking::factory()->create(array_merge([
            'customer_id' => null,
            'status' => BookingStatus::PaymentPending,
            'payment_status' => 'unpaid',
            'balance_due' => 5000,
            'booking_reference' => 'GTH-'.strtoupper((string) fake()->unique()->numberBetween(1000, 9999)),
        ], $overrides));

        if (! $booking->contact) {
            $booking->contact()->create([
                'email' => 'guestmatch@example.test',
                'phone' => '03001234567',
                'country' => 'PK',
            ]);
        }

        return $booking->fresh(['contact']);
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
