<?php

namespace Tests\Feature;

use App\Data\OfferValidationResultData;
use App\Enums\BookingStatus;
use App\Enums\SupplierConnectionStatus;
use App\Enums\SupplierProvider;
use App\Http\Controllers\Frontend\BookingController;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\PlatformModuleSetting;
use App\Models\SupplierConnection;
use App\Models\User;
use App\Services\Booking\BookingProviderRouter;
use App\Services\FlightSearch\FlightSearchResultStore;
use App\Services\FlightSearch\FlightSearchService;
use App\Services\Platform\PlatformModuleSettingsService;
use App\Services\Suppliers\Adapters\DuffelFlightSupplierAdapter;
use App\Services\Suppliers\Adapters\SabreFlightSupplierAdapter;
use App\Services\Suppliers\OfferValidationService;
use App\Support\Bookings\ComplexItineraryPolicy;
use App\Support\PublicBooking;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\Support\PlatformAdminTestHelpers;
use Tests\Support\PublicBookingPassengersPayload;
use Tests\Support\PublicCheckoutTestDoubles;
use Tests\TestCase;

class PublicCheckoutStabilizationTest extends TestCase
{
    use PlatformAdminTestHelpers;
    use RefreshDatabase;

    public function test_missing_selected_offer_snapshot_redirects_safely_from_passengers(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $depart = now()->addWeek()->format('Y-m-d');

        $this->get('/booking/passengers?flight_id=missing-offer-9d2'
            .'&offer_id=missing-offer-9d2'
            .'&from=LHE&to=DXB&depart='.$depart
            .'&trip_type=one_way&cabin=economy&adults=1&children=0&infants=0')
            ->assertRedirect(route('flights.search'))
            ->assertSessionHasErrors('flight_id');
    }

    public function test_stale_recovery_second_request_with_flag_skips_recovery_and_redirects_safely(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $depart = now()->addWeek()->format('Y-m-d');

        $unavailable = new OfferValidationResultData(
            is_valid: false,
            status: 'unavailable',
            original_offer_id: PublicCheckoutTestDoubles::OFFER_ID,
            warnings: ['Stale'],
        );

        $ovs = Mockery::mock(OfferValidationService::class);
        $ovs->shouldReceive('validateSelectedOffer')->once()->andReturn($unavailable);
        $ovs->shouldReceive('pricingSnapshotForCachedOffer')->andReturn(PublicCheckoutTestDoubles::pricingSnapshot());
        App::instance(OfferValidationService::class, $ovs);

        $offer = PublicCheckoutTestDoubles::searchOfferPayload($depart);
        $searchId = app(FlightSearchResultStore::class)->store([
            'origin' => 'LHE',
            'destination' => 'DXB',
            'depart_date' => $depart,
            'trip_type' => 'one_way',
            'cabin' => 'economy',
            'adults' => 1,
            'children' => 0,
            'infants' => 0,
        ], [$offer], []);

        $flightSearch = Mockery::mock(FlightSearchService::class);
        $flightSearch->shouldReceive('searchWithMeta')->never();
        $flightSearch->shouldReceive('search')->never();
        App::instance(FlightSearchService::class, $flightSearch);

        $this->withSession([
            BookingController::SESSION_BOOKING_AFTER_STALE_RECOVERY => true,
        ])->get('/booking/passengers?flight_id='.PublicCheckoutTestDoubles::OFFER_ID
            .'&offer_id='.PublicCheckoutTestDoubles::OFFER_ID
            .'&search_id='.$searchId
            .'&from=LHE&to=DXB&depart='.$depart.'&trip_type=one_way&cabin=economy&adults=1&children=0&infants=0')
            ->assertRedirect(route('flights.search'))
            ->assertSessionHasErrors('flight_id');
    }

    public function test_expired_hold_returns_hold_expired_on_review_post(): void
    {
        Http::fake();
        $this->seed(OtaFoundationSeeder::class);
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $depart = now()->addWeek()->format('Y-m-d');
        $offer = PublicCheckoutTestDoubles::searchOfferPayload($depart);

        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'status' => BookingStatus::Draft,
            'supplier' => SupplierProvider::Duffel->value,
            'meta' => [
                'supplier_provider' => SupplierProvider::Duffel->value,
                'protection_mode' => 'hold_price_guaranteed',
                'offer_expires_at' => now()->subHour()->toIso8601String(),
                'flight_offer_snapshot' => $offer,
                'search_criteria' => [
                    'origin' => 'LHE',
                    'destination' => 'DXB',
                    'depart_date' => $depart,
                    'trip_type' => 'one_way',
                    'adults' => 1,
                    'children' => 0,
                    'infants' => 0,
                ],
                'requires_price_change_confirmation' => false,
            ],
        ]);

        $this->withSession([PublicBooking::SESSION_BOOKING_ID => $booking->id])
            ->post(route('booking.review'), ['booking_method' => 'pay_later'])
            ->assertRedirect(route('booking.review'))
            ->assertSessionHasErrors('flight_id')
            ->assertSessionHas('recheck_required', true);

        Http::assertNothingSent();
    }

    public function test_double_review_post_does_not_create_duplicate_booking(): void
    {
        Http::fake();
        $this->seed(OtaFoundationSeeder::class);
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $depart = now()->addWeek()->format('Y-m-d');
        PublicCheckoutTestDoubles::bind($this, $depart, 'LHE', 'DXB');

        $this->post('/booking/passengers', array_merge(
            PublicBookingPassengersPayload::merge([
                'flight_id' => PublicCheckoutTestDoubles::OFFER_ID,
                'offer_id' => PublicCheckoutTestDoubles::OFFER_ID,
                'from' => 'LHE',
                'to' => 'DXB',
                'depart' => $depart,
                'email' => 'dup-review@example.com',
            ]),
            PublicBookingPassengersPayload::internationalDocuments(),
        ))->assertRedirect(route('booking.review'));

        $this->assertSame(1, Booking::query()->count());

        $this->post('/booking/review', ['booking_method' => 'pay_later'])
            ->assertRedirect(route('booking.confirmation'));

        $this->post('/booking/review', ['booking_method' => 'pay_later'])
            ->assertRedirect(route('booking.confirmation'));

        $this->assertSame(1, Booking::query()->count());
        $booking = Booking::query()->firstOrFail();
        $this->assertSame(BookingStatus::Pending, $booking->fresh()->status);
        $this->assertNotNull($booking->booking_reference);

        Http::assertNothingSent();
    }

    public function test_supplier_booking_off_blocks_router_pnr_without_http(): void
    {
        Http::fake();
        $this->seed(OtaFoundationSeeder::class);
        PlatformModuleSetting::query()->create([
            'module_key' => 'supplier_booking',
            'enabled' => false,
        ]);
        app(PlatformModuleSettingsService::class)->forgetCache();

        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        SupplierConnection::query()
            ->where('agency_id', $agency->id)
            ->where('provider', SupplierProvider::Duffel)
            ->update(['is_active' => true, 'status' => SupplierConnectionStatus::Active]);

        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'status' => BookingStatus::Pending,
            'supplier' => SupplierProvider::Duffel->value,
            'meta' => [
                'supplier_provider' => SupplierProvider::Duffel->value,
                'distribution_channel' => 'GDS',
            ],
        ]);

        $admin = User::query()->where('email', 'admin@ota.demo')->firstOrFail();
        $result = app(BookingProviderRouter::class)->createSupplierBooking($booking, $admin);

        $this->assertFalse($result->success);
        $this->assertSame('platform_module_disabled', $result->error_code);
        Http::assertNothingSent();
    }

    public function test_supplier_search_off_blocks_offer_validation_without_adapter_calls(): void
    {
        Http::fake();
        $this->seed(OtaFoundationSeeder::class);
        PlatformModuleSetting::query()->create([
            'module_key' => 'supplier_search',
            'enabled' => false,
        ]);
        app(PlatformModuleSettingsService::class)->forgetCache();

        $this->mock(DuffelFlightSupplierAdapter::class, function ($mock): void {
            $mock->shouldReceive('validateOffer')->never();
        });
        $this->mock(SabreFlightSupplierAdapter::class, function ($mock): void {
            $mock->shouldReceive('validateOffer')->never();
        });

        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $offer = PublicCheckoutTestDoubles::searchOfferPayload(now()->addDays(10)->toDateString());

        $result = app(OfferValidationService::class)->validateSelectedOffer($agency, $offer, [
            'origin' => 'LHE',
            'destination' => 'DXB',
            'depart_date' => now()->addDays(10)->toDateString(),
            'source_channel' => 'public_guest',
        ]);

        $this->assertFalse($result->is_valid);
        Http::assertNothingSent();
    }

    public function test_sabre_cached_validation_does_not_invoke_duffel_adapter(): void
    {
        Http::fake();
        config([
            'suppliers.sabre.booking_enabled' => false,
            'suppliers.sabre.booking_live_call_enabled' => false,
        ]);
        $this->seed(OtaFoundationSeeder::class);

        $this->mock(DuffelFlightSupplierAdapter::class, function ($mock): void {
            $mock->shouldReceive('validateOffer')->never();
        });
        $this->mock(SabreFlightSupplierAdapter::class, function ($mock): void {
            $mock->shouldReceive('validateOffer')->never();
        });

        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $sabreConn = SupplierConnection::query()
            ->where('agency_id', $agency->id)
            ->where('provider', SupplierProvider::Sabre)
            ->firstOrFail();
        $sabreConn->update([
            'is_active' => true,
            'status' => SupplierConnectionStatus::Active,
        ]);

        $depart = now()->addDays(14)->toDateString();
        $result = app(OfferValidationService::class)->validateSelectedOffer($agency, [
            'id' => 'sabre-9d2-cache',
            'offer_id' => 'sabre-9d2-cache',
            'supplier_offer_id' => 'sabre-ref-9d2',
            'supplier_provider' => 'sabre',
            'distribution_channel' => 'GDS',
            'supplier_connection_id' => $sabreConn->id,
            'airline_code' => 'EK',
            'final_customer_price' => 120000,
            'pricing_currency' => 'PKR',
            'conversion_status' => 'same_currency',
            'depart_at' => $depart.'T08:00:00Z',
            'arrive_at' => $depart.'T14:00:00Z',
            'stops' => 0,
        ], [
            'origin' => 'LHE',
            'destination' => 'DXB',
            'depart_date' => $depart,
            'trip_type' => 'one_way',
            'source_channel' => 'public_guest',
        ]);

        $this->assertTrue($result->is_valid);
        $this->assertTrue((bool) ($result->meta['sabre_checkout_cache_only'] ?? false));
        Http::assertNothingSent();
    }

    public function test_complex_sabre_round_trip_defers_auto_pnr_in_booking_meta(): void
    {
        Http::fake();
        $this->seed(OtaFoundationSeeder::class);
        $this->withoutMiddleware(ValidateCsrfToken::class);
        config([
            'suppliers.sabre.booking_enabled' => false,
            'suppliers.sabre.booking_live_call_enabled' => false,
            'suppliers.sabre.complex_itinerary_pnr_enabled' => false,
        ]);

        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $sabreConn = SupplierConnection::query()
            ->where('agency_id', $agency->id)
            ->where('provider', SupplierProvider::Sabre)
            ->firstOrFail();
        $sabreConn->update(['is_active' => true, 'status' => SupplierConnectionStatus::Active]);

        $depart = now()->addDays(12)->format('Y-m-d');
        $return = now()->addDays(19)->format('Y-m-d');
        $offerId = 'sabre-rt-offer-9d2';
        $offer = [
            'id' => $offerId,
            'offer_id' => $offerId,
            'supplier_offer_id' => 'sabre-rt-ref',
            'supplier_provider' => 'sabre',
            'distribution_channel' => 'GDS',
            'supplier_connection_id' => $sabreConn->id,
            'airline_code' => 'EK',
            'airline_name' => 'Emirates',
            'origin' => 'LHE',
            'destination' => 'DXB',
            'depart_at' => $depart.'T08:00:00Z',
            'arrive_at' => $depart.'T14:00:00Z',
            'final_customer_price' => 150000,
            'pricing_currency' => 'PKR',
            'conversion_status' => 'same_currency',
            'stops' => 0,
            'cabin' => 'economy',
        ];

        $searchId = app(FlightSearchResultStore::class)->store([
            'origin' => 'LHE',
            'destination' => 'DXB',
            'depart_date' => $depart,
            'return_date' => $return,
            'trip_type' => 'round_trip',
            'cabin' => 'economy',
            'adults' => 1,
            'children' => 0,
            'infants' => 0,
        ], [$offer], []);

        $this->post('/booking/passengers', array_merge(
            PublicBookingPassengersPayload::merge([
                'flight_id' => $offerId,
                'offer_id' => $offerId,
                'search_id' => $searchId,
                'from' => 'LHE',
                'to' => 'DXB',
                'depart' => $depart,
                'return_date' => $return,
                'trip_type' => 'round_trip',
                'email' => 'rt-sabre@example.com',
            ]),
            PublicBookingPassengersPayload::internationalDocuments(),
        ))->assertRedirect(route('booking.review'));

        $meta = Booking::query()->firstOrFail()->meta ?? [];
        $this->assertTrue((bool) ($meta['defer_supplier_booking_to_manual_review'] ?? false));
        $this->assertSame(ComplexItineraryPolicy::DEFER_REASON, $meta['supplier_pnr_deferred_reason'] ?? null);
        $this->assertTrue((bool) ($meta['complex_itinerary_requires_staff_confirmation'] ?? false));

        Http::assertNothingSent();
    }
}
