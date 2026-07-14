<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\SupplierProvider;
use App\Http\Controllers\Frontend\BookingController;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\BookingContact;
use App\Models\BookingFareBreakdown;
use App\Models\BookingPassenger;
use App\Models\SupplierBookingAttempt;
use App\Support\Bookings\SabrePreCheckoutKnownFailureSoftBlock;
use App\Support\Bookings\SabrePreCheckoutSellabilityDryRun;
use App\Support\Bookings\SabreSafeRefreshContext;
use App\Support\FlightSearch\FlightOfferDisplayPresenter;
use App\Support\PublicBooking;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SabrePreCheckoutSoftBlockCheckoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
        $this->withoutMiddleware(ValidateCsrfToken::class);
        Http::fake();
        config([
            'suppliers.sabre.cpnr_connecting_same_carrier_gds_enabled' => true,
            'suppliers.sabre.cpnr_connecting_same_carrier_public_checkout_enabled' => false,
            'suppliers.sabre.verified_multiseg_auto_pnr_enabled' => false,
            'suppliers.sabre.ticketing_enabled' => false,
            'suppliers.sabre.booking_enabled' => true,
            'suppliers.sabre.booking_live_call_enabled' => false,
            'suppliers.sabre.refresh_offer_before_public_pnr' => false,
            'suppliers.sabre.revalidate_before_booking' => false,
            'suppliers.sabre.certified_route_selector_public_checkout_enabled' => false,
            'suppliers.sabre.passenger_records_fresh_shop_guard_before_live' => false,
        ]);
    }

    public function test_prepare_checkout_booking_fixture_builds_without_error(): void
    {
        $booking = $this->prepareCheckoutBooking($this->gfBooking46LikeConnectingBooking());
        $this->assertNotNull($booking->id);
        $this->assertIsArray($booking->meta['pre_checkout_sellability_dry_run'] ?? null);
    }

    public function test_config_false_booking_46_like_review_submit_is_not_soft_blocked(): void
    {
        config(['suppliers.sabre.precheckout_known_failure_soft_block_enabled' => false]);
        $booking = $this->prepareCheckoutBooking($this->gfBooking46LikeConnectingBooking());

        $response = $this->submitReviewForBooking($booking);

        $this->assertNotSame(
            SabrePreCheckoutKnownFailureSoftBlock::customerRedirectMessage(),
            (string) session('errors')?->first('flight_id')
        );
        $this->assertNotNull(session(PublicBooking::SESSION_BOOKING_ID));
        $this->assertTrue($response->isRedirect());
        $this->assertSame(0, SupplierBookingAttempt::query()->where('booking_id', $booking->id)->where('action', 'create_pnr')->count());
    }

    public function test_config_true_booking_46_like_review_submit_soft_blocks_to_results(): void
    {
        config(['suppliers.sabre.precheckout_known_failure_soft_block_enabled' => true]);
        $booking = $this->prepareCheckoutBooking($this->gfBooking46LikeConnectingBooking());

        $response = $this->submitReviewForBooking($booking);

        $this->assertTrue($response->isRedirect());
        $this->assertStringContainsString(route('flights.results'), (string) $response->headers->get('Location'));
        $this->assertSame(
            SabrePreCheckoutKnownFailureSoftBlock::customerRedirectMessage(),
            (string) session('errors')?->first('flight_id')
        );
        $this->assertNull(session(PublicBooking::SESSION_BOOKING_ID));
        $this->assertSame(0, SupplierBookingAttempt::query()->where('booking_id', $booking->id)->where('action', 'create_pnr')->count());
    }

    public function test_config_true_booking_43_like_review_submit_soft_blocks_with_safe_message(): void
    {
        config(['suppliers.sabre.precheckout_known_failure_soft_block_enabled' => true]);
        $booking = $this->prepareCheckoutBooking($this->pkHostNoopConnectingBooking());

        $response = $this->submitReviewForBooking($booking);

        $this->assertTrue($response->isRedirect());
        $this->assertStringContainsString(route('flights.results'), (string) $response->headers->get('Location'));
        $this->assertSame(
            SabrePreCheckoutKnownFailureSoftBlock::customerRedirectMessage(),
            (string) session('errors')?->first('flight_id')
        );
    }

    public function test_config_true_booking_45_like_insufficient_evidence_is_not_hard_blocked(): void
    {
        config(['suppliers.sabre.precheckout_known_failure_soft_block_enabled' => true]);
        $booking = $this->prepareCheckoutBooking($this->gfBooking45LikeInsufficientConnectingBooking());

        $response = $this->submitReviewForBooking($booking);

        $this->assertTrue($response->isRedirect());
        $this->assertNotSame(
            SabrePreCheckoutKnownFailureSoftBlock::customerRedirectMessage(),
            (string) session('errors')?->first('flight_id')
        );
        $this->assertNotNull(session(PublicBooking::SESSION_BOOKING_ID));
    }

    public function test_config_true_booking_44_like_success_evidence_is_not_hard_blocked(): void
    {
        config(['suppliers.sabre.precheckout_known_failure_soft_block_enabled' => true]);
        $booking = $this->prepareCheckoutBooking($this->gfVerifiedConnectingBooking());

        $response = $this->submitReviewForBooking($booking);

        $this->assertTrue($response->isRedirect());
        $this->assertNotSame(
            SabrePreCheckoutKnownFailureSoftBlock::customerRedirectMessage(),
            (string) session('errors')?->first('flight_id')
        );
        $this->assertNotNull(session(PublicBooking::SESSION_BOOKING_ID));
        $this->assertSame(0, SupplierBookingAttempt::query()->where('booking_id', $booking->id)->where('action', 'create_pnr')->count());
    }

    protected function submitReviewForBooking(Booking $booking): RedirectResponse
    {
        $session = $this->app['session.store'];
        $session->start();
        $session->put(PublicBooking::SESSION_BOOKING_ID, $booking->id);

        $request = Request::create(route('booking.review'), 'POST', [
            'booking_method' => 'pay_later',
        ]);
        $request->setLaravelSession($session);

        $response = app(BookingController::class)->review($request);
        $this->assertInstanceOf(RedirectResponse::class, $response);

        return $response;
    }

    protected function prepareCheckoutBooking(Booking $fixtureBooking): Booking
    {
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $meta = is_array($fixtureBooking->meta) ? $fixtureBooking->meta : [];
        $snapshot = is_array($meta['normalized_offer_snapshot'] ?? null) ? $meta['normalized_offer_snapshot'] : [];
        $criteria = is_array($meta['search_criteria'] ?? null) ? $meta['search_criteria'] : [];
        $criteria = array_merge([
            'trip_type' => 'one_way',
            'origin' => 'LHE',
            'destination' => 'JED',
            'depart_date' => '2026-07-31',
            'cabin' => 'economy',
            'adults' => 1,
            'children' => 0,
            'infants' => 0,
        ], $criteria);
        $refreshMeta = is_array($meta[SabreSafeRefreshContext::META_KEY] ?? null)
            ? $meta[SabreSafeRefreshContext::META_KEY]
            : [];
        $checkoutSearchId = (string) ($refreshMeta['checkout_search_id'] ?? 'soft-block-search');
        $firstSegment = is_array($snapshot['segments'][0] ?? null) ? $snapshot['segments'][0] : [];
        $departAt = (string) ($firstSegment['departure_at'] ?? ($criteria['depart_date'].'T08:00:00Z'));
        $offer = array_merge($snapshot, [
            'id' => (string) ($refreshMeta['checkout_offer_id'] ?? 'soft-block-offer'),
            'supplier_provider' => SupplierProvider::Sabre->value,
            'airline_code' => (string) ($snapshot['validating_carrier'] ?? 'GF'),
            'depart_at' => $departAt,
            'arrive_at' => $departAt,
            'total' => 100000,
            'currency' => 'PKR',
        ]);
        $offer = FlightOfferDisplayPresenter::enrichOfferSnapshotForBooking($offer, $criteria);

        $fixtureBooking->forceFill([
            'agency_id' => $agency->id,
            'status' => BookingStatus::Draft,
            'supplier' => SupplierProvider::Sabre->value,
            'meta' => array_merge($meta, [
                'supplier_provider' => SupplierProvider::Sabre->value,
                'requires_price_change_confirmation' => false,
                'protection_mode' => 'none',
                'flight_offer_snapshot' => $offer,
                'normalized_offer_snapshot' => $snapshot,
                'search_criteria' => $criteria,
                'checkout_search_id' => $checkoutSearchId,
                'original_offer_id' => (string) ($offer['id'] ?? 'soft-block-offer'),
            ]),
        ])->save();

        if ($fixtureBooking->fareBreakdown === null) {
            BookingFareBreakdown::query()->create([
                'booking_id' => $fixtureBooking->id,
                'base_fare' => 80000,
                'taxes' => 10000,
                'fees' => 0,
                'markup' => 10000,
                'discount' => 0,
                'total' => 100000,
                'currency' => 'PKR',
                'breakdown' => [],
            ]);
        }

        app(SabrePreCheckoutSellabilityDryRun::class)->evaluateAndPersist($fixtureBooking->fresh());

        return $fixtureBooking->fresh(['passengers', 'contact', 'fareBreakdown']);
    }

    protected function gfVerifiedConnectingBooking(): Booking
    {
        return $this->buildFixtureBooking([
            'segments' => [
                ['origin' => 'LHE', 'destination' => 'BAH', 'carrier' => 'GF', 'flight_number' => '767', 'booking_class' => 'W', 'fare_basis_code' => 'WDLIT3PK', 'departure_at' => '2026-07-29T22:00:00'],
                ['origin' => 'BAH', 'destination' => 'JED', 'carrier' => 'GF', 'flight_number' => '171', 'booking_class' => 'W', 'fare_basis_code' => 'WDLIT3PK', 'departure_at' => '2026-07-30T10:05:00'],
            ],
            'depart_date' => '2026-07-29',
            'search_id' => 'gf-verified-soft-block-search',
            'offer_id' => 'gf-verified-soft-block-offer',
        ]);
    }

    protected function gfBooking46LikeConnectingBooking(): Booking
    {
        return $this->buildFixtureBooking([
            'segments' => [
                ['origin' => 'LHE', 'destination' => 'BAH', 'carrier' => 'GF', 'flight_number' => '765', 'booking_class' => 'W', 'fare_basis_code' => 'WDLIT3PK', 'departure_at' => '2026-07-31T15:10:00'],
                ['origin' => 'BAH', 'destination' => 'JED', 'carrier' => 'GF', 'flight_number' => '173', 'booking_class' => 'W', 'fare_basis_code' => 'WDLIT3PK', 'departure_at' => '2026-08-01T18:05:00'],
            ],
            'depart_date' => '2026-07-31',
            'search_id' => 'gf-booking-46-soft-block-search',
            'offer_id' => 'gf-booking-46-soft-block-offer',
        ]);
    }

    protected function gfBooking45LikeInsufficientConnectingBooking(): Booking
    {
        return $this->buildFixtureBooking([
            'segments' => [
                ['origin' => 'LHE', 'destination' => 'BAH', 'carrier' => 'GF', 'flight_number' => '765', 'booking_class' => 'N', 'fare_basis_code' => 'NDLIT3PK', 'departure_at' => '2026-07-31T15:10:00'],
                ['origin' => 'BAH', 'destination' => 'JED', 'carrier' => 'GF', 'flight_number' => '181', 'booking_class' => 'N', 'fare_basis_code' => 'NDLIT3PK', 'departure_at' => '2026-08-01T18:05:00'],
            ],
            'depart_date' => '2026-07-31',
            'search_id' => 'gf-booking-45-soft-block-search',
            'offer_id' => 'gf-booking-45-soft-block-offer',
        ]);
    }

    protected function pkHostNoopConnectingBooking(): Booking
    {
        $booking = Booking::factory()->create([
            'status' => BookingStatus::Draft,
            'meta' => [
                'supplier_provider' => 'sabre',
                'supplier_connection_id' => 1,
                'offer_validation_status' => 'valid',
                'search_criteria' => [
                    'trip_type' => 'one_way',
                    'origin' => 'LHE',
                    'destination' => 'JED',
                    'depart_date' => '2026-07-23',
                ],
                'normalized_offer_snapshot' => [
                    'validating_carrier' => 'PK',
                    'segments' => [
                        ['origin' => 'LHE', 'destination' => 'KHI', 'carrier' => 'PK', 'flight_number' => '301', 'booking_class' => 'V', 'fare_basis_code' => 'VDLIT3PK', 'departure_at' => '2026-07-23T08:00:00Z'],
                        ['origin' => 'KHI', 'destination' => 'JED', 'carrier' => 'PK', 'flight_number' => '741', 'booking_class' => 'V', 'fare_basis_code' => 'VDLIT3PK', 'departure_at' => '2026-07-24T02:30:00Z'],
                    ],
                ],
            ],
        ]);

        $this->attachPassengerAndContact($booking);

        return $booking->fresh(['passengers', 'contact']);
    }

    /**
     * @param  array{segments: list<array<string, mixed>>, depart_date: string, search_id: string, offer_id: string}  $config
     */
    protected function buildFixtureBooking(array $config): Booking
    {
        $booking = Booking::factory()->create([
            'status' => BookingStatus::Draft,
            'meta' => [
                'supplier_provider' => 'sabre',
                'supplier_connection_id' => 1,
                'create_payload_strategy_version' => 'E5A_SAFE_STRUCTURE_V1',
                'offer_validation_status' => 'valid',
                'search_criteria' => [
                    'trip_type' => 'one_way',
                    'origin' => 'LHE',
                    'destination' => 'JED',
                    'depart_date' => $config['depart_date'],
                ],
                'normalized_offer_snapshot' => [
                    'supplier_provider' => 'sabre',
                    'validating_carrier' => 'GF',
                    'segments' => $config['segments'],
                ],
            ],
        ]);

        $meta = is_array($booking->meta) ? $booking->meta : [];
        $snapshot = is_array($meta['normalized_offer_snapshot'] ?? null) ? $meta['normalized_offer_snapshot'] : [];
        $meta[SabreSafeRefreshContext::META_KEY] = app(SabreSafeRefreshContext::class)->buildFromCheckout($snapshot, [
            'trip_type' => 'one_way',
            'origin' => 'LHE',
            'destination' => 'JED',
            'depart_date' => $config['depart_date'],
            'adults' => 1,
        ], [
            'checkout_search_id' => $config['search_id'],
            'checkout_offer_id' => $config['offer_id'],
            'supplier_total' => 100.0,
            'supplier_currency' => 'PKR',
        ]);
        $booking->forceFill(['meta' => $meta])->save();
        $this->attachPassengerAndContact($booking);

        return $booking->fresh(['passengers', 'contact']);
    }

    protected function attachPassengerAndContact(Booking $booking): void
    {
        if ($booking->passengers()->count() === 0) {
            BookingPassenger::factory()->for($booking)->create([
                'passenger_index' => 0,
                'is_lead_passenger' => true,
                'first_name' => 'Test',
                'last_name' => 'Passenger',
                'date_of_birth' => now()->subYears(30)->toDateString(),
                'gender' => 'male',
                'passenger_type' => 'adult',
            ]);
        }

        if ($booking->contact === null) {
            BookingContact::query()->create([
                'booking_id' => $booking->id,
                'email' => 'guest@example.test',
                'phone' => '+923001234567',
            ]);
        }
    }
}
