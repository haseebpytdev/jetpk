<?php

namespace Tests\Feature\Guest;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Services\Customer\GuestBookingAccessService;
use App\Support\GuestBooking\LegacyGuestTokenUsageTelemetry;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class GuestLegacyTokenTelemetryPrivacyTest extends TestCase
{
    use RefreshDatabase;

    private const TEST_RAW_TOKEN = 'TEST_RAW_TOKEN_DO_NOT_LOG_123456789';

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        LegacyGuestTokenUsageTelemetry::drainTestSink();
    }

    public function test_legacy_exchange_success_emits_sanitized_telemetry(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        LegacyGuestTokenUsageTelemetry::enableTestSink();
        $booking = $this->guestBooking();
        $token = $this->guestToken($booking);

        $this->get(route('guest.bookings.exchange', ['booking' => $booking, 'token' => $token]))
            ->assertRedirect(route('guest.bookings.show', ['booking' => $booking]));

        $records = LegacyGuestTokenUsageTelemetry::drainTestSink();
        $this->assertLegacyRecord($records, 'guest_detail_exchange', 'success', 'GET');
        $this->assertNoForbiddenContent($records, [(string) $booking->id, $token, (string) $booking->booking_reference]);
    }

    public function test_legacy_exchange_invalid_token_emits_denied_without_token(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        LegacyGuestTokenUsageTelemetry::enableTestSink();
        $booking = $this->guestBooking();

        $this->get(route('guest.bookings.exchange', [
            'booking' => $booking,
            'token' => self::TEST_RAW_TOKEN,
        ]))->assertForbidden();

        $records = LegacyGuestTokenUsageTelemetry::drainTestSink();
        $this->assertLegacyRecord($records, 'guest_detail_exchange', 'denied', 'GET');
        $this->assertNoForbiddenContent($records, [(string) $booking->id, self::TEST_RAW_TOKEN]);
    }

    public function test_legacy_mutation_routes_emit_denied_telemetry_for_invalid_token(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $booking = $this->guestBooking();

        $cases = [
            ['guest.bookings.payment-proof.legacy', 'payment_proof', ['method' => 'bank_transfer', 'amount' => 1]],
            ['guest.bookings.promo.apply.legacy', 'promo_apply', ['code' => 'PROMOCODE_DO_NOT_LOG']],
            ['guest.bookings.promo.remove.legacy', 'promo_remove', []],
            ['guest.bookings.abhipay.start.legacy', 'abhipay_start', []],
            ['guest.bookings.cancellations.store.legacy', 'cancellation', ['reason' => 'CANCEL_REASON_DO_NOT_LOG']],
        ];

        foreach ($cases as [$routeName, $category, $payload]) {
            Cache::flush();
            LegacyGuestTokenUsageTelemetry::enableTestSink();

            $this->postJson(route($routeName, [
                'booking' => $booking,
                'token' => self::TEST_RAW_TOKEN,
            ]), $payload)->assertForbidden();

            $records = LegacyGuestTokenUsageTelemetry::drainTestSink();
            $this->assertLegacyRecord($records, $category, 'denied', 'POST');
            $this->assertNoForbiddenContent($records, [
                (string) $booking->id,
                self::TEST_RAW_TOKEN,
                'PROMOCODE_DO_NOT_LOG',
                'CANCEL_REASON_DO_NOT_LOG',
            ]);
        }
    }

    public function test_legacy_valid_payment_proof_emits_sanitized_outcome(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        LegacyGuestTokenUsageTelemetry::enableTestSink();
        $booking = $this->guestBooking();
        $token = $this->guestToken($booking);

        $response = $this->postJson(route('guest.bookings.payment-proof.legacy', [
            'booking' => $booking,
            'token' => $token,
        ]), [
            'method' => 'bank_transfer',
            'amount' => 5000,
            'payment_reference' => 'REF_LEGACY_TELEM_DO_NOT_LOG',
        ]);

        $this->assertTrue(in_array($response->status(), [201, 200, 422], true), 'unexpected status '.$response->status());

        $records = LegacyGuestTokenUsageTelemetry::drainTestSink();
        $outcome = LegacyGuestTokenUsageTelemetry::outcomeFromStatus($response->status());
        $this->assertLegacyRecord($records, 'payment_proof', $outcome, 'POST');
        $this->assertNoForbiddenContent($records, [
            (string) $booking->id,
            $token,
            'REF_LEGACY_TELEM_DO_NOT_LOG',
        ]);
    }

    public function test_clean_routes_do_not_emit_legacy_telemetry(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $booking = $this->guestBooking();
        $token = $this->guestToken($booking);

        LegacyGuestTokenUsageTelemetry::enableTestSink();
        $this->withSession(['guest_booking_access.'.$booking->id => $token])
            ->getJson(route('guest.bookings.show', ['booking' => $booking]))
            ->assertOk();
        $this->assertSame([], LegacyGuestTokenUsageTelemetry::drainTestSink());

        LegacyGuestTokenUsageTelemetry::enableTestSink();
        $this->withSession(['guest_booking_access.'.$booking->id => $token])
            ->postJson(route('guest.bookings.payment-proof', ['booking' => $booking]), [
                'method' => 'bank_transfer',
                'amount' => 5000,
                'payment_reference' => 'REF-CLEAN',
            ])->assertCreated();
        $this->assertSame([], LegacyGuestTokenUsageTelemetry::drainTestSink());

        LegacyGuestTokenUsageTelemetry::enableTestSink();
        $this->withSession(['guest_booking_access.'.$booking->id => $token])
            ->postJson(route('guest.bookings.promo.apply', ['booking' => $booking]), ['code' => 'NOPE']);
        $this->assertSame([], LegacyGuestTokenUsageTelemetry::drainTestSink());

        LegacyGuestTokenUsageTelemetry::enableTestSink();
        $this->withSession(['guest_booking_access.'.$booking->id => $token])
            ->postJson(route('guest.bookings.promo.remove', ['booking' => $booking]));
        $this->assertSame([], LegacyGuestTokenUsageTelemetry::drainTestSink());

        LegacyGuestTokenUsageTelemetry::enableTestSink();
        $this->withSession(['guest_booking_access.'.$booking->id => $token])
            ->postJson(route('guest.bookings.cancellations.store', ['booking' => $booking]), [
                'reason' => 'clean-route',
            ]);
        $this->assertSame([], LegacyGuestTokenUsageTelemetry::drainTestSink());

        LegacyGuestTokenUsageTelemetry::enableTestSink();
        $this->withSession(['guest_booking_access.'.$booking->id => $token])
            ->postJson(route('guest.bookings.abhipay.start', ['booking' => $booking]));
        $this->assertSame([], LegacyGuestTokenUsageTelemetry::drainTestSink());
    }

    public function test_authorization_semantics_unchanged_for_legacy_and_clean(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $bookingA = $this->guestBooking(['booking_reference' => 'TELEM-A']);
        $bookingB = $this->guestBooking(['booking_reference' => 'TELEM-B']);
        $tokenA = $this->guestToken($bookingA);
        $tokenB = $this->guestToken($bookingB);

        $this->get(route('guest.bookings.exchange', ['booking' => $bookingA, 'token' => $tokenA]))
            ->assertRedirect(route('guest.bookings.show', ['booking' => $bookingA]));

        $this->get(route('guest.bookings.exchange', ['booking' => $bookingA, 'token' => self::TEST_RAW_TOKEN]))
            ->assertForbidden();

        $fresh = $this->guestToken($bookingA);
        $this->get(route('guest.bookings.exchange', ['booking' => $bookingA, 'token' => $tokenA]))
            ->assertForbidden();
        $this->get(route('guest.bookings.exchange', ['booking' => $bookingA, 'token' => $fresh]))
            ->assertRedirect();

        $this->get(route('guest.bookings.exchange', ['booking' => $bookingA, 'token' => $tokenB]))
            ->assertForbidden();

        $this->withSession(['guest_booking_access.'.$bookingB->id => $tokenB])
            ->getJson(route('guest.bookings.show', ['booking' => $bookingB]))
            ->assertOk();
    }

    /**
     * @param  list<array{event: string, route_category: string, method: string, outcome: string}>  $records
     */
    protected function assertLegacyRecord(array $records, string $category, string $outcome, string $method): void
    {
        $this->assertNotEmpty($records);
        $match = collect($records)->first(
            fn (array $r): bool => ($r['route_category'] ?? null) === $category
                && ($r['outcome'] ?? null) === $outcome
                && ($r['method'] ?? null) === $method
                && ($r['event'] ?? null) === LegacyGuestTokenUsageTelemetry::EVENT
        );
        $this->assertNotNull($match, 'missing telemetry for '.$category.'/'.$outcome);
        $this->assertSame(
            LegacyGuestTokenUsageTelemetry::ALLOWED_CONTEXT_KEYS,
            array_keys($match),
        );
    }

    /**
     * @param  list<array<string, string>>  $records
     * @param  list<string>  $forbidden
     */
    protected function assertNoForbiddenContent(array $records, array $forbidden): void
    {
        $blob = json_encode($records, JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('/access/', $blob);
        $this->assertStringNotContainsString('?token=', $blob);
        $this->assertStringNotContainsString('guest_token', $blob);
        $this->assertStringNotContainsString('session_id', $blob);
        $this->assertStringNotContainsString('REQUEST_URI', $blob);
        $this->assertStringNotContainsString('User-Agent', $blob);
        $this->assertStringNotContainsString('Referer', $blob);
        $this->assertStringNotContainsString('Authorization', $blob);

        foreach ($forbidden as $needle) {
            if ($needle === '' || $needle === '0') {
                continue;
            }
            $this->assertStringNotContainsString($needle, $blob);
        }
    }

    protected function guestBooking(array $overrides = []): Booking
    {
        $booking = Booking::factory()->create(array_merge([
            'customer_id' => null,
            'status' => BookingStatus::PaymentPending,
            'payment_status' => 'unpaid',
            'balance_due' => 5000,
            'booking_reference' => 'GLT-'.strtoupper((string) fake()->unique()->numberBetween(1000, 9999)),
        ], $overrides));

        if (! $booking->contact) {
            $booking->contact()->create([
                'email' => 'legacytelem@example.test',
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
