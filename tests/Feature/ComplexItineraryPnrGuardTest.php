<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\SupplierConnectionStatus;
use App\Enums\SupplierProvider;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\BookingContact;
use App\Models\BookingFareBreakdown;
use App\Models\BookingPassenger;
use App\Models\SupplierConnection;
use App\Services\Suppliers\Sabre\SabreBookingService;
use App\Support\Bookings\ComplexItineraryPolicy;
use App\Support\PublicBooking;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ComplexItineraryPnrGuardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    private function assertNoPassengerRecordsHttpPost(): void
    {
        $recorded = Http::recorded();
        $pairs = $recorded instanceof Collection ? $recorded->all() : (array) $recorded;
        foreach ($pairs as $pair) {
            $request = is_array($pair) ? ($pair[0] ?? null) : $pair;
            if ($request instanceof Request && str_contains((string) $request->url(), '/passenger/records')) {
                $this->fail('Unexpected Passenger Records HTTP POST during test.');
            }
        }
    }

    public function test_one_way_review_submit_still_runs_sabre_dry_run_path(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        Http::fake();
        $this->withoutMiddleware(ValidateCsrfToken::class);
        config([
            'suppliers.sabre.booking_enabled' => true,
            'suppliers.sabre.booking_live_call_enabled' => false,
            'suppliers.sabre.booking_path' => '/v1/trip/orders/createBooking',
            'suppliers.sabre.booking_schema' => null,
            'suppliers.sabre.complex_itinerary_pnr_enabled' => false,
            'suppliers.sabre.refresh_offer_before_public_pnr' => false,
            'suppliers.sabre.revalidate_before_booking' => false,
            'suppliers.sabre.certified_route_selector_public_checkout_enabled' => false,
        ]);

        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $sabreConn = SupplierConnection::query()
            ->where('agency_id', $agency->id)
            ->where('provider', 'sabre')
            ->firstOrFail();
        $sabreConn->update([
            'is_active' => true,
            'status' => SupplierConnectionStatus::Active,
            'base_url' => 'https://example.sabre.test',
            'credentials' => ['client_id' => 'cid', 'client_secret' => 'sec'],
        ]);

        $depart = now()->addDays(10)->toDateString();
        $offer = [
            'id' => 'sabre-offer-1',
            'supplier_provider' => 'sabre',
            'supplier_connection_id' => $sabreConn->id,
            'airline_code' => 'EK',
            'airline_name' => 'Emirates',
            'validating_carrier' => 'EK',
            'depart_at' => $depart.'T08:00:00Z',
            'arrive_at' => $depart.'T14:00:00Z',
            'total' => 100000,
            'currency' => 'PKR',
            'segments' => [[
                'origin' => 'LHE',
                'destination' => 'DXB',
                'carrier' => 'EK',
                'flight_number' => '601',
                'departure_at' => $depart.'T08:00:00Z',
                'arrival_at' => $depart.'T14:00:00Z',
                'booking_class' => 'Y',
                'fare_basis_code' => 'YLITE',
                'cabin' => 'economy',
                'brand_code' => 'ECON',
            ]],
            'fare_breakdown' => [
                'supplier_total' => 100000,
                'currency' => 'PKR',
                'passenger_counts' => ['adults' => 1, 'children' => 0, 'infants' => 0],
            ],
        ];

        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'status' => BookingStatus::Draft,
            'supplier' => SupplierProvider::Sabre->value,
            'meta' => [
                'supplier_provider' => SupplierProvider::Sabre->value,
                'supplier_connection_id' => $sabreConn->id,
                'requires_price_change_confirmation' => false,
                'protection_mode' => 'hold_price_guaranteed',
                'flight_offer_snapshot' => $offer,
                'normalized_offer_snapshot' => $offer,
                'validated_offer_snapshot' => $offer,
                'revalidation_status' => 'success',
                'selected_offer_revalidation_status' => 'success',
                'selected_fare_family_option' => [
                    'brand_code' => 'ECON',
                    'booking_classes_by_segment' => ['Y'],
                    'fare_basis_codes_by_segment' => ['YLITE'],
                    'cabin_by_segment' => ['economy'],
                ],
                'search_criteria' => [
                    'origin' => 'LHE',
                    'destination' => 'DXB',
                    'depart_date' => $depart,
                    'trip_type' => 'one_way',
                    'cabin' => 'economy',
                    'adults' => 1,
                    'children' => 0,
                    'infants' => 0,
                ],
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
            'email' => 'sabre-review-dry-run@example.com',
            'phone' => '+923001234567',
            'country' => 'Pakistan',
            'address_line' => null,
            'meta' => [],
        ]);
        BookingFareBreakdown::query()->create([
            'booking_id' => $booking->id,
            'base_fare' => 80000,
            'taxes' => 10000,
            'fees' => 0,
            'markup' => 10000,
            'discount' => 0,
            'total' => 100000,
            'currency' => 'PKR',
            'breakdown' => [],
        ]);

        $this->withSession([PublicBooking::SESSION_BOOKING_ID => $booking->id])
            ->post(route('booking.review'), ['booking_method' => 'pay_later'])
            ->assertRedirect(route('booking.confirmation'));

        $booking->refresh();
        $this->assertSame('dry_run', data_get($booking->meta, 'sabre_checkout_outcome.status'));
    }

    public function test_round_trip_run_public_review_dry_run_defers_without_http(): void
    {
        Http::fake();
        config([
            'suppliers.sabre.booking_enabled' => true,
            'suppliers.sabre.booking_live_call_enabled' => true,
            'suppliers.sabre.complex_itinerary_pnr_enabled' => false,
        ]);

        $depart = now()->addDays(10)->toDateString();
        $return = now()->addDays(17)->toDateString();
        $offer = [
            'id' => 'sabre-offer-rt',
            'supplier_provider' => 'sabre',
            'segments' => [
                ['origin' => 'LHE', 'destination' => 'DXB', 'depart_at' => $depart.'T08:00:00Z'],
                ['origin' => 'DXB', 'destination' => 'LHE', 'depart_at' => $return.'T10:00:00Z'],
            ],
            'total' => 100000,
            'currency' => 'PKR',
        ];

        $booking = Booking::factory()->create([
            'status' => BookingStatus::Draft,
            'supplier' => SupplierProvider::Sabre->value,
            'meta' => [
                'supplier_provider' => SupplierProvider::Sabre->value,
                'normalized_offer_snapshot' => $offer,
                'search_criteria' => [
                    'trip_type' => 'round_trip',
                    'origin' => 'LHE',
                    'destination' => 'DXB',
                    'depart_date' => $depart,
                    'return_date' => $return,
                ],
            ],
        ]);
        BookingPassenger::factory()->create(['booking_id' => $booking->id, 'passenger_index' => 1]);
        BookingContact::query()->create([
            'booking_id' => $booking->id,
            'email' => 'rt@example.com',
            'phone' => '+923001234567',
        ]);

        $outcome = app(SabreBookingService::class)->runPublicReviewDryRun(
            $booking->fresh(['passengers', 'contact', 'fareBreakdown'])
        );

        $this->assertSame(ComplexItineraryPolicy::ERROR_CODE, $outcome['error_code'] ?? null);
        $this->assertFalse((bool) ($outcome['live_call_attempted'] ?? true));
        $booking->refresh();
        $this->assertSame('manual_review', $booking->supplier_booking_status);
        $this->assertNoPassengerRecordsHttpPost();
    }

    public function test_multi_city_run_public_review_dry_run_defers_without_http(): void
    {
        Http::fake();
        config([
            'suppliers.sabre.booking_enabled' => true,
            'suppliers.sabre.booking_live_call_enabled' => true,
            'suppliers.sabre.complex_itinerary_pnr_enabled' => false,
        ]);

        $depart = now()->addDays(10)->toDateString();
        $offer = [
            'id' => 'sabre-offer-mc',
            'segments' => [
                ['origin' => 'LHE', 'destination' => 'DXB', 'depart_at' => $depart.'T08:00:00Z'],
            ],
            'total' => 120000,
            'currency' => 'PKR',
        ];

        $booking = Booking::factory()->create([
            'status' => BookingStatus::Draft,
            'supplier' => SupplierProvider::Sabre->value,
            'meta' => [
                'supplier_provider' => SupplierProvider::Sabre->value,
                'normalized_offer_snapshot' => $offer,
                'search_criteria' => [
                    'trip_type' => 'multi_city',
                    'segments' => [
                        ['origin' => 'LHE', 'destination' => 'DXB', 'depart_date' => $depart],
                    ],
                ],
            ],
        ]);
        BookingPassenger::factory()->create(['booking_id' => $booking->id, 'passenger_index' => 1]);
        BookingContact::query()->create([
            'booking_id' => $booking->id,
            'email' => 'mc@example.com',
            'phone' => '+923001234567',
        ]);

        $outcome = app(SabreBookingService::class)->runPublicReviewDryRun(
            $booking->fresh(['passengers', 'contact', 'fareBreakdown'])
        );

        $this->assertSame(ComplexItineraryPolicy::ERROR_CODE, $outcome['error_code'] ?? null);
        $this->assertFalse((bool) ($outcome['live_call_attempted'] ?? true));
        $booking->refresh();
        $this->assertSame('manual_review', $booking->supplier_booking_status);
        $this->assertNoPassengerRecordsHttpPost();
    }
}
