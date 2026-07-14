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
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Phase B22 — Trip Orders createBooking requires {@code flightOffer} or {@code flightDetails}; payload styles + inspect.
 */
class SabreBookingTripOrdersFlightProductB22Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'suppliers.sabre.revalidate_before_booking' => false,
            'suppliers.sabre.allow_createbooking_without_revalidation' => false,
        ]);
    }

    protected function seedPayloadInspectBooking(): Booking
    {
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $sabreConn = SupplierConnection::query()
            ->where('agency_id', $agency->id)
            ->where('provider', 'sabre')
            ->firstOrFail();

        $depart = now()->addDays(12)->toDateString();
        $snapshot = [
            'offer_id' => 'b22-inspect-1',
            'supplier_offer_id' => 'b22-inspect-1',
            'supplier_provider' => 'sabre',
            'supplier_connection_id' => $sabreConn->id,
            'airline_code' => 'PK',
            'segments' => [
                [
                    'origin' => 'LHE',
                    'destination' => 'DXB',
                    'departure_at' => $depart.'T05:00:00',
                    'arrival_at' => $depart.'T08:45:00',
                    'carrier' => 'PK',
                    'flight_number' => '303',
                    'booking_class' => 'Y',
                    'fare_basis_code' => 'YOWPK',
                ],
            ],
            'fare_breakdown' => [
                'supplier_total' => 150000,
                'currency' => 'PKR',
                'base_fare' => 120000,
                'taxes' => 30000,
                'passenger_counts' => ['adults' => 1, 'children' => 0, 'infants' => 0],
            ],
            'raw_payload' => [
                'sabre_shop_context' => [
                    'itinerary_ref' => 'itin-b22',
                ],
            ],
        ];

        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'status' => BookingStatus::Draft,
            'supplier' => SupplierProvider::Sabre->value,
            'meta' => [
                'supplier_provider' => SupplierProvider::Sabre->value,
                'supplier_connection_id' => $sabreConn->id,
                'normalized_offer_snapshot' => $snapshot,
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
            'email' => 'b22-inspect@example.com',
            'phone' => '+923001234567',
            'country' => 'Pakistan',
            'address_line' => null,
            'meta' => [],
        ]);

        BookingFareBreakdown::query()->create([
            'booking_id' => $booking->id,
            'base_fare' => 120000,
            'taxes' => 30000,
            'fees' => 0,
            'markup' => 0,
            'discount' => 0,
            'total' => 150000,
            'currency' => 'PKR',
            'breakdown' => [],
        ]);

        return $booking;
    }

    public function test_current_style_keeps_validation_ok_with_flight_product_warning(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        config(['suppliers.sabre.createbooking_payload_style' => 'trip_orders_create_booking_v1_current']);
        $booking = $this->seedPayloadInspectBooking();
        $svc = $this->app->make(SabreBookingService::class);
        $shape = $svc->inspectBookingPayloadShapeForCommand($booking);
        $this->assertTrue($shape['validation_ok']);
        $this->assertFalse((bool) ($shape['has_required_booking_product_object'] ?? true));
        $this->assertNotNull($shape['inspect_warning_trip_orders_flight_product'] ?? null);
    }

    public function test_flight_offer_style_sets_has_flight_offer_and_required_product(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        config(['suppliers.sabre.createbooking_payload_style' => 'trip_orders_flight_offer_v1']);
        $booking = $this->seedPayloadInspectBooking();
        $svc = $this->app->make(SabreBookingService::class);
        $shape = $svc->inspectBookingPayloadShapeForCommand($booking);
        $this->assertTrue((bool) ($shape['has_flight_offer'] ?? false));
        $this->assertFalse((bool) ($shape['has_flight_details'] ?? true));
        $this->assertTrue((bool) ($shape['has_required_booking_product_object'] ?? false));
        $this->assertTrue((bool) ($shape['has_segments_inside_flight_offer'] ?? false));
        $this->assertTrue($shape['validation_ok']);
    }

    public function test_flight_details_style_sets_has_flight_details_and_required_product(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        config(['suppliers.sabre.createbooking_payload_style' => 'trip_orders_flight_details_v1']);
        $booking = $this->seedPayloadInspectBooking();
        $svc = $this->app->make(SabreBookingService::class);
        $shape = $svc->inspectBookingPayloadShapeForCommand($booking);
        $this->assertTrue((bool) ($shape['has_flight_details'] ?? false));
        $this->assertFalse((bool) ($shape['has_flight_offer'] ?? true));
        $this->assertTrue((bool) ($shape['has_required_booking_product_object'] ?? false));
        $this->assertTrue((bool) ($shape['has_segments_inside_flight_details'] ?? false));
        $this->assertTrue($shape['validation_ok']);
    }

    public function test_inspect_artisan_includes_payload_style_and_passenger_counts_flag(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        config(['suppliers.sabre.createbooking_payload_style' => 'trip_orders_flight_offer_v1']);
        $booking = $this->seedPayloadInspectBooking();
        Artisan::call('sabre:inspect-booking-payload', ['--booking' => (string) $booking->id]);
        $out = Artisan::output();
        $this->assertStringContainsString('payload_style=trip_orders_flight_offer_v1', $out);
        $this->assertStringContainsString('has_required_booking_product_object=true', $out);
        $this->assertStringContainsString('has_passenger_counts=true', $out);
    }

    public function test_preview_json_does_not_contain_authorization_or_raw_traveler_names(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        config(['suppliers.sabre.createbooking_payload_style' => 'trip_orders_flight_offer_v1']);
        $booking = $this->seedPayloadInspectBooking();
        Artisan::call('sabre:inspect-booking-payload', [
            '--booking' => (string) $booking->id,
            '--preview-json' => true,
        ]);
        $out = Artisan::output();
        $this->assertStringNotContainsString('Authorization', $out);
        $this->assertStringNotContainsString('b22-inspect@example.com', $out);
        $this->assertStringContainsString('"travelers"', $out);
        $this->assertStringContainsString('"redacted"', $out);
    }

    public function test_compare_createbooking_styles_command_runs_shape_only(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $booking = $this->seedPayloadInspectBooking();
        Artisan::call('sabre:compare-createbooking-styles', ['--booking' => (string) $booking->id]);
        $out = Artisan::output();
        $this->assertStringContainsString('style=trip_orders_flight_offer_v1', $out);
        $this->assertStringContainsString('style=trip_orders_flight_details_v1', $out);
        $this->assertStringContainsString('http_status=not_attempted', $out);
    }

    public function test_http_200_pnr_success_does_not_enable_ticketing(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $sabreBase = rtrim((string) config('suppliers.sabre.default_base_url'), '/');
        $tokenPath = (string) config('suppliers.sabre.token_path', '/v2/auth/token');
        $bookingPath = '/v1/trip/orders/createBooking';
        Http::fake([
            $sabreBase.$tokenPath => Http::response(['access_token' => 'tok-hidden', 'expires_in' => 3600], 200),
            $sabreBase.$bookingPath => Http::response(['recordLocator' => 'ABC12X'], 200),
        ]);
        config([
            'suppliers.sabre.booking_enabled' => true,
            'suppliers.sabre.booking_live_call_enabled' => true,
            'suppliers.sabre.ticketing_enabled' => false,
            'suppliers.sabre.booking_path' => $bookingPath,
            'suppliers.sabre.booking_schema' => null,
            'suppliers.sabre.revalidate_before_booking' => false,
            'suppliers.sabre.createbooking_payload_style' => 'trip_orders_flight_offer_v1',
        ]);

        $booking = $this->seedPayloadInspectBooking();
        $conn = SupplierConnection::query()->findOrFail((int) $booking->meta['supplier_connection_id']);
        $conn->update([
            'is_active' => true,
            'status' => SupplierConnectionStatus::Active,
            'credentials' => ['client_id' => 'cid', 'client_secret' => 'sec'],
        ]);

        $svc = $this->app->make(SabreBookingService::class);
        $outcome = $svc->runPublicReviewDryRun($booking->fresh());
        $this->assertTrue((bool) ($outcome['success'] ?? false));
        $this->assertSame('pending_payment_or_ticketing', (string) ($outcome['status'] ?? ''));
        $this->assertSame('ABC12X', strtoupper(trim((string) ($outcome['pnr'] ?? ''))));
        $booking->refresh();
        $this->assertSame('ABC12X', strtoupper(trim((string) ($booking->pnr ?? ''))));
        $this->assertFalse((bool) config('suppliers.sabre.ticketing_enabled'));
    }

    public function test_http_200_order_id_without_pnr_sets_supplier_api_booking_id_and_needs_review(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $sabreBase = rtrim((string) config('suppliers.sabre.default_base_url'), '/');
        $tokenPath = (string) config('suppliers.sabre.token_path', '/v2/auth/token');
        $bookingPath = '/v1/trip/orders/createBooking';
        Http::fake([
            $sabreBase.$tokenPath => Http::response(['access_token' => 'tok-hidden', 'expires_in' => 3600], 200),
            $sabreBase.$bookingPath => Http::response(['orderId' => 'ord-12345'], 200),
        ]);
        config([
            'suppliers.sabre.booking_enabled' => true,
            'suppliers.sabre.booking_live_call_enabled' => true,
            'suppliers.sabre.ticketing_enabled' => false,
            'suppliers.sabre.booking_path' => $bookingPath,
            'suppliers.sabre.booking_schema' => null,
            'suppliers.sabre.revalidate_before_booking' => false,
            'suppliers.sabre.createbooking_payload_style' => 'trip_orders_flight_offer_v1',
        ]);

        $booking = $this->seedPayloadInspectBooking();
        $conn = SupplierConnection::query()->findOrFail((int) $booking->meta['supplier_connection_id']);
        $conn->update([
            'is_active' => true,
            'status' => SupplierConnectionStatus::Active,
            'credentials' => ['client_id' => 'cid', 'client_secret' => 'sec'],
        ]);

        $svc = $this->app->make(SabreBookingService::class);
        $outcome = $svc->runPublicReviewDryRun($booking->fresh());
        $this->assertTrue((bool) ($outcome['success'] ?? false));
        $this->assertSame('needs_review', (string) ($outcome['status'] ?? ''));
        $this->assertSame('ord-12345', trim((string) ($outcome['provider_booking_id'] ?? '')));

        $booking->refresh();
        $this->assertSame('ord-12345', (string) ($booking->supplier_api_booking_id ?? ''));
        $this->assertSame('manual_review', (string) ($booking->supplier_booking_status ?? ''));
    }

    public function test_duffel_booking_inspect_returns_not_sabre(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $depart = now()->addDays(5)->toDateString();
        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'status' => BookingStatus::Draft,
            'supplier' => SupplierProvider::Duffel->value,
            'meta' => [
                'supplier_provider' => SupplierProvider::Duffel->value,
                'flight_offer_snapshot' => [
                    'id' => 'duffel-offer',
                    'supplier_provider' => 'duffel',
                    'total' => 100,
                    'currency' => 'USD',
                ],
            ],
        ]);
        $svc = $this->app->make(SabreBookingService::class);
        $shape = $svc->inspectBookingPayloadShapeForCommand($booking);
        $this->assertSame('booking_not_sabre', $shape['error'] ?? null);
    }
}
