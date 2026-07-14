<?php

namespace Tests\Unit\Support\Bookings;

use App\Data\NormalizedFlightOfferData;
use App\Data\OfferValidationResultData;
use App\Enums\BookingStatus;
use App\Enums\SupplierConnectionStatus;
use App\Enums\SupplierEnvironment;
use App\Enums\SupplierProvider;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\BookingContact;
use App\Models\BookingPassenger;
use App\Models\SupplierBookingAttempt;
use App\Models\SupplierConnection;
use App\Models\User;
use App\Services\Suppliers\Iati\IatiFareRevalidationService;
use App\Services\Suppliers\Iati\IatiResponseNormalizer;
use App\Services\Suppliers\SupplierBookingService;
use App\Support\Bookings\IatiReservationLifecycleService;
use App\Support\Bookings\SupplierBookingAttemptGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SupplierBookingAttemptGuardTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        SupplierBookingAttemptGuard::resetInFlightAttemptId();
        Cache::flush();
        parent::tearDown();
    }

    #[Test]
    public function test_failed_attempts_do_not_block_retry(): void
    {
        [$booking, $provider] = $this->bookingWithProvider();

        SupplierBookingAttempt::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'provider' => $provider,
            'action' => 'create_pnr',
            'status' => 'failed',
            'error_code' => 'supplier_booking_in_progress',
            'error_message' => 'Supplier booking already in progress.',
            'attempted_at' => now()->subMinute(),
            'completed_at' => now()->subMinute(),
        ]);

        $guard = app(SupplierBookingAttemptGuard::class);
        $this->assertNull($guard->resolveActiveAttempt($booking, $provider));
        $this->assertFalse($guard->assertRetryAllowed($booking, $provider)['blocked']);
        $this->assertTrue(app(IatiReservationLifecycleService::class)->assertSupplierBookAllowed($booking->fresh())['allowed']);
    }

    #[Test]
    public function test_completed_attempts_do_not_block_retry(): void
    {
        [$booking, $provider] = $this->bookingWithProvider();

        SupplierBookingAttempt::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'provider' => $provider,
            'action' => 'create_pnr',
            'status' => 'success',
            'attempted_at' => now()->subHour(),
            'completed_at' => now()->subHour(),
        ]);

        $guard = app(SupplierBookingAttemptGuard::class);
        $this->assertNull($guard->resolveActiveAttempt($booking, $provider));
        $this->assertFalse($guard->assertRetryAllowed($booking, $provider)['blocked']);
    }

    #[Test]
    public function test_active_processing_attempt_blocks_retry(): void
    {
        [$booking, $provider] = $this->bookingWithProvider();

        $attempt = SupplierBookingAttempt::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'provider' => $provider,
            'action' => 'create_pnr',
            'status' => 'processing',
            'attempted_at' => now(),
            'completed_at' => null,
        ]);

        $guard = app(SupplierBookingAttemptGuard::class);
        $active = $guard->resolveActiveAttempt($booking, $provider);
        $this->assertNotNull($active);
        $this->assertSame($attempt->id, $active->id);
        $this->assertTrue($guard->assertRetryAllowed($booking, $provider)['blocked']);
    }

    #[Test]
    public function test_stale_processing_attempt_is_released_and_retry_allowed(): void
    {
        [$booking, $provider] = $this->bookingWithProvider();

        SupplierBookingAttempt::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'provider' => $provider,
            'action' => 'create_pnr',
            'status' => 'processing',
            'attempted_at' => now()->subMinutes(15),
            'completed_at' => null,
        ]);

        $guard = app(SupplierBookingAttemptGuard::class);
        $this->assertNull($guard->resolveActiveAttempt($booking->fresh(), $provider));
        $attempt = SupplierBookingAttempt::query()->where('booking_id', $booking->id)->first();
        $this->assertSame('failed', $attempt?->status);
        $this->assertSame('supplier_booking_stale_attempt', $attempt?->error_code);
    }

    #[Test]
    public function test_in_flight_attempt_id_is_excluded_from_active_check(): void
    {
        [$booking, $provider] = $this->bookingWithProvider();

        $attempt = SupplierBookingAttempt::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'provider' => $provider,
            'action' => 'create_pnr',
            'status' => 'processing',
            'attempted_at' => now(),
            'completed_at' => null,
        ]);

        $guard = app(SupplierBookingAttemptGuard::class);
        $guard->setInFlightAttemptId($attempt->id);
        $this->assertNull($guard->resolveActiveAttempt($booking, $provider));
        $this->assertTrue(app(IatiReservationLifecycleService::class)->assertSupplierBookAllowed($booking->fresh())['allowed']);
    }

    #[Test]
    public function test_paid_eligible_iati_booking_with_only_failed_in_progress_attempts_reaches_provider(): void
    {
        [$booking, $connection, $actor] = $this->paidIatiFixture();

        SupplierBookingAttempt::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'supplier_connection_id' => $connection->id,
            'provider' => SupplierProvider::Iati->value,
            'action' => 'create_pnr',
            'status' => 'failed',
            'error_code' => 'supplier_booking_in_progress',
            'error_message' => 'Supplier booking already in progress.',
            'attempted_at' => now()->subMinute(),
            'completed_at' => now()->subMinute(),
        ]);

        $this->mockIatiRevalidationSameFare($booking);

        Http::fake([
            'https://testapi.iati.com/rest/auth/token' => Http::response(['access_token' => 'token-abc'], 200),
            'https://testapi.iati.com/rest/flight/v2/fare' => Http::response(
                file_get_contents(base_path('tests/Fixtures/iati/fare_response_multi_offers_total_match.json')),
                200,
            ),
            'https://testapi.iati.com/rest/flight/v2/book' => Http::response([
                'result' => ['books' => [['order_id' => 'order-retry-1', 'status' => 'BOOKED']]],
            ], 200),
            'https://testapi.iati.com/rest/flight/v2/order/order-retry-1' => Http::response(
                file_get_contents(base_path('tests/Fixtures/iati/order_retrieve_option_info_pnr.json')),
                200,
            ),
        ]);

        $result = app(SupplierBookingService::class)->createSupplierBooking(
            $booking->fresh(),
            $actor,
            adminOverride: false,
            explicitRetry: true,
            attemptSource: 'admin',
        );

        $this->assertTrue($result->success, (string) ($result->error_message ?: $result->error_code));
        Http::assertSent(fn ($request) => str_contains($request->url(), '/flight/v2/fare'));
        Http::assertSent(fn ($request) => str_contains($request->url(), '/flight/v2/book'));
    }

    #[Test]
    public function test_lock_is_released_when_iati_provider_throws(): void
    {
        [$booking, $connection, $actor] = $this->paidIatiFixture();

        $this->mockIatiRevalidationSameFare($booking);

        Http::fake([
            'https://testapi.iati.com/rest/auth/token' => Http::response(['access_token' => 'token-abc'], 200),
            'https://testapi.iati.com/rest/flight/v2/fare' => Http::response(['code' => 'ERR', 'message' => 'fail'], 500),
        ]);

        $guard = app(SupplierBookingAttemptGuard::class);
        $result = app(SupplierBookingService::class)->createSupplierBooking(
            $booking->fresh(),
            $actor,
            explicitRetry: true,
            attemptSource: 'admin',
        );

        $this->assertFalse($result->success);
        $this->assertFalse($guard->isLockActive($booking->fresh(), SupplierProvider::Iati->value));
    }

    /**
     * @return array{0: Booking, 1: string}
     */
    protected function bookingWithProvider(): array
    {
        $agency = Agency::factory()->create();
        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'supplier' => SupplierProvider::Iati->value,
            'payment_status' => 'paid',
            'meta' => [
                'supplier_provider' => SupplierProvider::Iati->value,
                'offer_validation_status' => 'valid',
                'validated_offer_snapshot' => ['offer_id' => 'x'],
                'iati_reservation' => [
                    'requires_instant_payment' => true,
                    'local_checkout_expires_at' => now()->addMinutes(15)->toIso8601String(),
                ],
            ],
        ]);

        return [$booking, SupplierProvider::Iati->value];
    }

    /**
     * @return array{0: Booking, 1: SupplierConnection, 2: User}
     */
    protected function paidIatiFixture(): array
    {
        $agency = Agency::factory()->create();
        $actor = User::factory()->create(['current_agency_id' => $agency->id]);
        $connection = SupplierConnection::factory()->create([
            'agency_id' => $agency->id,
            'provider' => SupplierProvider::Iati,
            'environment' => SupplierEnvironment::Sandbox,
            'status' => SupplierConnectionStatus::Active,
            'is_active' => true,
            'credentials' => ['auth_code' => 'test-code', 'secret' => 'test-secret'],
        ]);

        $fareFixture = json_decode((string) file_get_contents(base_path('tests/Fixtures/iati/fare_response_multi_offers_total_match.json')), true);
        $normalizedFare = app(IatiResponseNormalizer::class)->normalizeFareResponse($fareFixture, [
            'selected_branded_fare_id' => 'iati_brand_1',
            'selected_fare_option_id' => 'iati-fare-2-85158-1',
            'departure_fare_key' => 'dep-match-key',
        ]);
        $offerSnapshot = [
            'offer_id' => 'offer-58',
            'supplier_provider' => 'iati',
            'origin' => 'LHE',
            'destination' => 'DXB',
            'airline_code' => 'PK',
            'fare_breakdown' => [
                'supplier_total' => (float) ($normalizedFare['total'] ?? 85158),
                'currency' => (string) ($normalizedFare['currency'] ?? 'PKR'),
            ],
            'raw_payload' => [
                'provider_context' => array_merge($normalizedFare['provider_context'], [
                    'departure_fare_key' => 'dep-match-key',
                    'fare_detail_key' => $normalizedFare['fare_detail_key'],
                    'selected_branded_fare_id' => 'iati_brand_1',
                    'selected_fare_option_id' => 'iati-fare-2-85158-1',
                ]),
            ],
        ];

        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'supplier' => SupplierProvider::Iati->value,
            'status' => BookingStatus::Pending,
            'payment_status' => 'paid',
            'selected_fare_total' => (float) ($normalizedFare['total'] ?? 85158),
            'meta' => [
                'supplier_provider' => SupplierProvider::Iati->value,
                'supplier_connection_id' => $connection->id,
                'offer_validation_status' => 'valid',
                'requires_instant_payment' => true,
                'hold_supported' => false,
                'protection_mode' => 'instant_payment_required',
                'supplier_currency' => 'PKR',
                'search_criteria' => ['origin' => 'LHE', 'destination' => 'DXB', 'adults' => 1],
                'selected_branded_fare_id' => 'iati_brand_1',
                'selected_fare_option_id' => 'iati-fare-2-85158-1',
                'validated_offer_snapshot' => $offerSnapshot,
                'iati_reservation' => [
                    'requires_instant_payment' => true,
                    'hold_supported' => false,
                    'protection_mode' => 'instant_payment_required',
                    'local_checkout_expires_at' => now()->addMinutes(15)->toIso8601String(),
                ],
            ],
        ]);

        BookingContact::query()->create([
            'booking_id' => $booking->id,
            'email' => 'pax@example.com',
            'phone' => '3001234567',
            'phone_country_code' => '92',
        ]);

        BookingPassenger::factory()->create([
            'booking_id' => $booking->id,
            'first_name' => 'Test',
            'last_name' => 'Passenger',
            'date_of_birth' => '1990-01-01',
            'passport_number' => 'AB1234567',
            'passport_expiry_date' => '2030-01-01',
            'nationality' => 'PK',
            'passenger_type' => 'adult',
        ]);

        return [$booking, $connection, $actor];
    }

    protected function mockIatiRevalidationSameFare(Booking $booking): void
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $snapshot = is_array($meta['validated_offer_snapshot'] ?? null) ? $meta['validated_offer_snapshot'] : [];
        $offer = NormalizedFlightOfferData::fromArray($snapshot);
        $mock = Mockery::mock(IatiFareRevalidationService::class);
        $mock->shouldReceive('revalidate')->andReturn(new OfferValidationResultData(
            is_valid: true,
            status: 'same',
            price_changed: false,
            old_total: (float) $booking->selected_fare_total,
            new_total: (float) $booking->selected_fare_total,
            currency: 'PKR',
            validated_offer: $offer,
        ));
        $this->app->instance(IatiFareRevalidationService::class, $mock);
    }
}
