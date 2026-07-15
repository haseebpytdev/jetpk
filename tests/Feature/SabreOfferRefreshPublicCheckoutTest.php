<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\SupplierProvider;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\BookingContact;
use App\Models\BookingFareBreakdown;
use App\Models\BookingPassenger;
use App\Services\FlightSearch\FlightSearchService;
use App\Support\Bookings\SabreOfferRefreshAcceptance;
use App\Support\PublicBooking;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Mockery;
use Tests\TestCase;

class SabreOfferRefreshPublicCheckoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        config([
            'suppliers.sabre.booking_enabled' => true,
            'suppliers.sabre.booking_live_call_enabled' => false,
            'suppliers.sabre.refresh_offer_before_public_pnr' => true,
            'suppliers.sabre.revalidate_before_booking' => false,
            'suppliers.sabre.passenger_records_fresh_shop_guard_before_live' => false,
        ]);
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_review_submit_with_unchanged_refresh_proceeds_without_modal(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        Http::fake();
        $booking = $this->sabreReviewBooking(100000.0);
        $this->mockSearchReturning($this->freshConnectingOffer(['O', 'Y'], 100000.0));

        $this->withSession([PublicBooking::SESSION_BOOKING_ID => $booking->id])
            ->post(route('booking.review'), ['booking_method' => 'pay_later'])
            ->assertRedirect(route('booking.confirmation'));

        Http::assertNothingSent();
        $this->assertFalse(SabreOfferRefreshAcceptance::requiresAcceptance($booking->fresh()));
    }

    public function test_review_submit_with_price_change_blocks_pnr_and_shows_modal_state(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        Http::fake();
        $booking = $this->sabreReviewBooking(100000.0);
        $this->mockSearchReturning($this->freshConnectingOffer(['O', 'Y'], 118000.0));

        $response = $this->withSession([PublicBooking::SESSION_BOOKING_ID => $booking->id])
            ->post(route('booking.review'), ['booking_method' => 'pay_later']);

        $response->assertRedirect(route('booking.review'));
        $response->assertSessionHas('show_offer_refresh_modal', true);
        Http::assertNothingSent();

        $booking->refresh();
        $this->assertTrue(SabreOfferRefreshAcceptance::requiresAcceptance($booking));
        $this->assertSame(BookingStatus::Draft, $booking->status);
    }

    public function test_review_get_shows_modal_without_sabre_or_rbd_wording(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $booking = $this->sabreReviewBooking(100000.0);
        $meta = is_array($booking->meta) ? $booking->meta : [];
        SabreOfferRefreshAcceptance::writePriceChangeMeta($meta, 100000.0, 118000.0, 'PKR');
        $meta['flight_offer_snapshot'] = $this->freshConnectingOffer(['O', 'Y'], 118000.0);
        $meta['flight_offer_snapshot_refreshed_at'] = now()->toIso8601String();
        $meta[SabreOfferRefreshAcceptance::META_OLD_CUSTOMER_TOTAL] = 110000.0;
        $meta[SabreOfferRefreshAcceptance::META_NEW_CUSTOMER_TOTAL] = 125000.0;
        $meta[SabreOfferRefreshAcceptance::META_CUSTOMER_PRICE_DELTA] = 15000.0;
        $booking->forceFill(['meta' => $meta])->save();

        $html = $this->withSession([
            PublicBooking::SESSION_BOOKING_ID => $booking->id,
            'show_offer_refresh_modal' => true,
        ])->get(route('booking.review'))->assertOk()->getContent();

        $this->assertStringContainsString('Fare updated before airline hold', $html);
        $this->assertStringContainsString('Accept updated fare and continue', $html);
        $this->assertStringContainsString('ota-offer-refresh-modal', $html);
        $modalSnippet = (string) strstr($html, 'ota-offer-refresh-modal');
        $this->assertStringNotContainsStringIgnoringCase('rbd', substr($modalSnippet, 0, 2500));
        $this->assertStringNotContainsStringIgnoringCase('passenger record', substr($modalSnippet, 0, 2500));
    }

    public function test_accept_updated_fare_marks_accepted_and_updates_payable(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        Http::fake();
        $booking = $this->sabreReviewBooking(100000.0);
        $refreshed = $this->freshConnectingOffer(['O', 'Y'], 118000.0);
        $meta = is_array($booking->meta) ? $booking->meta : [];
        SabreOfferRefreshAcceptance::writePriceChangeMeta($meta, 100000.0, 118000.0, 'PKR');
        $meta['flight_offer_snapshot'] = $refreshed;
        $meta['flight_offer_snapshot_refreshed_at'] = now()->toIso8601String();
        $meta['search_criteria'] = [
            'origin' => 'LHE',
            'destination' => 'DXB',
            'depart_date' => '2026-05-30',
            'trip_type' => 'one_way',
            'cabin' => 'economy',
            'adults' => 1,
            'children' => 0,
            'infants' => 0,
            'currency' => 'PKR',
        ];
        $booking->forceFill(['meta' => $meta])->save();

        $this->withSession([PublicBooking::SESSION_BOOKING_ID => $booking->id])
            ->post(route('booking.accept-updated-fare', $booking))
            ->assertRedirect(route('booking.review'))
            ->assertSessionHas('offer_refresh_accepted', true);

        $booking->refresh();
        $this->assertTrue(SabreOfferRefreshAcceptance::isAccepted($booking));
        $this->assertFalse(SabreOfferRefreshAcceptance::requiresAcceptance($booking));
        $this->assertGreaterThan(100000.0, (float) ($booking->fareBreakdown?->total ?? 0));
    }

    public function test_decline_updated_fare_redirects_to_results_with_criteria(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $booking = $this->sabreReviewBooking(100000.0);
        $meta = is_array($booking->meta) ? $booking->meta : [];
        SabreOfferRefreshAcceptance::writePriceChangeMeta($meta, 100000.0, 118000.0, 'PKR');
        $meta['search_criteria'] = [
            'origin' => 'LHE',
            'destination' => 'DXB',
            'depart_date' => '2026-05-30',
            'trip_type' => 'one_way',
            'cabin' => 'economy',
            'adults' => 1,
        ];
        $booking->forceFill(['meta' => $meta])->save();

        $this->withSession([PublicBooking::SESSION_BOOKING_ID => $booking->id])
            ->post(route('booking.decline-updated-fare', $booking))
            ->assertRedirect(route('flights.results', [
                'from' => 'LHE',
                'to' => 'DXB',
                'depart' => '2026-05-30',
                'trip_type' => 'one_way',
                'cabin' => 'economy',
                'adults' => 1,
                'children' => 0,
                'infants' => 0,
                'search_id' => 'search-test-1',
            ]));
    }

    protected function sabreReviewBooking(float $customerTotal): Booking
    {
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $offer = $this->freshConnectingOffer(['O', 'Y'], $customerTotal);
        $offer['search_criteria'] = [
            'origin' => 'LHE',
            'destination' => 'DXB',
            'depart_date' => '2026-05-30',
            'trip_type' => 'one_way',
            'cabin' => 'economy',
            'adults' => 1,
            'children' => 0,
            'infants' => 0,
            'currency' => 'PKR',
        ];

        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'status' => BookingStatus::Draft,
            'supplier' => SupplierProvider::Sabre->value,
            'meta' => [
                'supplier_provider' => SupplierProvider::Sabre->value,
                'requires_price_change_confirmation' => false,
                'protection_mode' => 'hold_price_guaranteed',
                'flight_offer_snapshot' => $offer,
                'search_criteria' => $offer['search_criteria'],
                'checkout_search_id' => 'search-test-1',
            ],
        ]);

        BookingPassenger::factory()->create([
            'booking_id' => $booking->id,
            'passenger_index' => 1,
            'passenger_type' => 'adult',
            'is_lead_passenger' => true,
        ]);

        BookingContact::query()->create([
            'booking_id' => $booking->id,
            'email' => 'p3b-public@test.example',
            'phone' => '+923001234567',
        ]);

        BookingFareBreakdown::query()->create([
            'booking_id' => $booking->id,
            'base_fare' => $customerTotal * 0.8,
            'taxes' => $customerTotal * 0.1,
            'fees' => 0,
            'markup' => $customerTotal * 0.1,
            'discount' => 0,
            'total' => $customerTotal,
            'currency' => 'PKR',
            'breakdown' => [],
        ]);

        return $booking;
    }

    /**
     * @param  list<string>  $rbd
     * @return array<string, mixed>
     */
    protected function freshConnectingOffer(array $rbd, float $supplierTotal): array
    {
        $depart = '2026-05-30';

        return [
            'id' => 'p3b-fresh-offer',
            'supplier_provider' => 'sabre',
            'validating_carrier' => 'EK',
            'total' => $supplierTotal,
            'currency' => 'PKR',
            'segments' => [
                [
                    'origin' => 'LHE',
                    'destination' => 'KHI',
                    'carrier' => 'PK',
                    'flight_number' => '303',
                    'departure_at' => $depart.'T11:00:00',
                    'arrival_at' => $depart.'T12:30:00',
                    'booking_class' => $rbd[0] ?? 'O',
                    'fare_basis_code' => 'YOWPK7',
                ],
                [
                    'origin' => 'KHI',
                    'destination' => 'DXB',
                    'carrier' => 'EK',
                    'flight_number' => '2107',
                    'departure_at' => $depart.'T23:55:00',
                    'arrival_at' => '2026-05-31T02:10:00',
                    'booking_class' => $rbd[1] ?? 'Y',
                    'fare_basis_code' => 'YOWPK7',
                ],
            ],
            'fare_breakdown' => [
                'base_fare' => $supplierTotal * 0.8,
                'taxes' => $supplierTotal * 0.1,
                'supplier_total' => $supplierTotal,
                'currency' => 'PKR',
            ],
        ];
    }

    protected function mockSearchReturning(array $offer): void
    {
        $mock = Mockery::mock(FlightSearchService::class);
        $mock->shouldReceive('searchWithMeta')->andReturn([
            'offers' => [$offer],
            'meta' => [],
        ]);
        $this->app->instance(FlightSearchService::class, $mock);
    }
}
