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
use App\Models\SupplierBookingAttempt;
use App\Models\SupplierConnection;
use App\Services\Suppliers\Sabre\Core\SabreBookingClient;
use App\Services\Suppliers\Sabre\SabreBookingPayloadBuilder;
use App\Services\Suppliers\Sabre\SabreBookingService;
use App\Support\Suppliers\SabreTraditionalCpnrIatiWireStructureDiagnostic;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SabreBookingWirePhaseB23Test extends TestCase
{
    use RefreshDatabase;

    protected function seedWireTestBooking(): Booking
    {
        $this->seed(OtaFoundationSeeder::class);
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $sabreConn = SupplierConnection::query()
            ->where('agency_id', $agency->id)
            ->where('provider', 'sabre')
            ->firstOrFail();

        $depart = now()->addDays(14)->toDateString();
        $snapshot = [
            'offer_id' => 'wire-b23-1',
            'supplier_offer_id' => 'wire-b23-1',
            'supplier_provider' => 'sabre',
            'supplier_connection_id' => $sabreConn->id,
            'airline_code' => 'EK',
            'validating_carrier' => 'EK',
            'segments' => [
                [
                    'origin' => 'LHE',
                    'destination' => 'DXB',
                    'departure_at' => $depart.'T08:00:00Z',
                    'arrival_at' => $depart.'T14:00:00Z',
                    'carrier' => 'EK',
                    'flight_number' => '615',
                    'booking_class' => 'K',
                    'fare_basis_code' => 'KLITE1',
                ],
            ],
            'fare_breakdown' => [
                'supplier_total' => 100000,
                'currency' => 'PKR',
                'base_fare' => 80000,
                'taxes' => 20000,
                'passenger_counts' => ['adults' => 1, 'children' => 0, 'infants' => 0],
            ],
            'baggage' => ['summary' => '1PC'],
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

        BookingPassenger::factory()->create(array_merge([
            'booking_id' => $booking->id,
            'passenger_index' => 1,
            'passenger_type' => 'adult',
            'is_lead_passenger' => true,
            'first_name' => 'WireTest',
            'last_name' => 'Passenger',
        ], [
            'passport_number' => 'XT8888888',
            'passport_issuing_country' => 'PK',
            'passport_expiry_date' => '2035-06-01',
            'nationality' => 'PK',
            'document_type' => 'passport',
        ]));

        BookingContact::query()->create([
            'booking_id' => $booking->id,
            'email' => 'wire-b23-test@example.com',
            'phone' => '+923001234567',
            'country' => 'Pakistan',
            'address_line' => null,
            'meta' => [],
        ]);

        BookingFareBreakdown::query()->create([
            'booking_id' => $booking->id,
            'base_fare' => 80000,
            'taxes' => 20000,
            'fees' => 0,
            'markup' => 0,
            'discount' => 0,
            'total' => 100000,
            'currency' => 'PKR',
            'breakdown' => [],
        ]);

        return $booking;
    }

    protected function seedWireTestBookingTwoSegment(): Booking
    {
        $this->seed(OtaFoundationSeeder::class);
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $sabreConn = SupplierConnection::query()
            ->where('agency_id', $agency->id)
            ->where('provider', 'sabre')
            ->firstOrFail();

        $depart = now()->addDays(14)->toDateString();
        $nextDay = now()->addDays(15)->toDateString();
        $snapshot = [
            'offer_id' => 'wire-b75-ms',
            'supplier_offer_id' => 'wire-b75-ms',
            'supplier_provider' => 'sabre',
            'supplier_connection_id' => $sabreConn->id,
            'airline_code' => 'PK',
            'validating_carrier' => 'PK',
            'segments' => [
                [
                    'origin' => 'LHE',
                    'destination' => 'KHI',
                    'departure_at' => $depart.'T05:00:00Z',
                    'arrival_at' => $depart.'T06:45:00Z',
                    'carrier' => 'PK',
                    'flight_number' => '303',
                    'booking_class' => 'V',
                    'fare_basis_code' => 'VOW1',
                ],
                [
                    'origin' => 'KHI',
                    'destination' => 'JED',
                    'departure_at' => $nextDay.'T05:00:00Z',
                    'arrival_at' => $nextDay.'T09:15:00Z',
                    'carrier' => 'PK',
                    'flight_number' => '831',
                    'booking_class' => 'U',
                    'fare_basis_code' => 'UOWSKPK',
                ],
            ],
            'fare_breakdown' => [
                'supplier_total' => 100000,
                'currency' => 'PKR',
                'base_fare' => 80000,
                'taxes' => 20000,
                'passenger_counts' => ['adults' => 1, 'children' => 0, 'infants' => 0],
            ],
            'baggage' => ['summary' => '1PC'],
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
                    'destination' => 'JED',
                    'depart_date' => $depart,
                    'trip_type' => 'one_way',
                    'cabin' => 'economy',
                    'adults' => 1,
                    'children' => 0,
                    'infants' => 0,
                ],
            ],
        ]);

        BookingPassenger::factory()->create(array_merge([
            'booking_id' => $booking->id,
            'passenger_index' => 1,
            'passenger_type' => 'adult',
            'is_lead_passenger' => true,
            'first_name' => 'WireTest',
            'last_name' => 'Passenger',
        ], [
            'passport_number' => 'XT8888888',
            'passport_issuing_country' => 'PK',
            'passport_expiry_date' => '2035-06-01',
            'nationality' => 'PK',
            'document_type' => 'passport',
        ]));

        BookingContact::query()->create([
            'booking_id' => $booking->id,
            'email' => 'wire-b23-test@example.com',
            'phone' => '+923001234567',
            'country' => 'Pakistan',
            'address_line' => null,
            'meta' => [],
        ]);

        BookingFareBreakdown::query()->create([
            'booking_id' => $booking->id,
            'base_fare' => 80000,
            'taxes' => 20000,
            'fees' => 0,
            'markup' => 0,
            'discount' => 0,
            'total' => 100000,
            'currency' => 'PKR',
            'breakdown' => [],
        ]);

        return $booking;
    }

    public function test_wire_summarize_strict_root_requires_flight_hotel_or_car_at_root(): void
    {
        $builder = app(SabreBookingPayloadBuilder::class);
        $nestedOnly = [
            'createBooking' => [
                'flightOffer' => ['segments' => [['origin' => 'LHE']]],
            ],
        ];
        $d = $builder->summarizeTripOrdersWirePostBody($nestedOnly);
        $this->assertFalse($d['wire_has_required_product_at_root']);
        $this->assertTrue($d['wire_has_required_booking_product_nested']);
        $this->assertSame('createBooking.flightOffer', $d['wire_flight_offer_path']);
    }

    public function test_trip_orders_flight_offer_root_v1_puts_flight_offer_at_wire_root(): void
    {
        $builder = app(SabreBookingPayloadBuilder::class);
        $offer = [
            'id' => 'o1',
            'supplier_connection_id' => 1,
            'supplier_offer_id' => 'so1',
            'airline_code' => 'EK',
            'validating_carrier' => 'EK',
            'segments' => [[
                'origin' => 'LHE',
                'destination' => 'DXB',
                'departure_at' => '2026-06-01T08:00:00Z',
                'arrival_at' => '2026-06-01T14:00:00Z',
                'carrier' => 'EK',
                'flight_number' => '615',
                'booking_class' => 'K',
                'fare_basis_code' => 'KLITE1',
            ]],
            'fare_breakdown' => [
                'supplier_total' => 100,
                'currency' => 'USD',
                'base_fare' => 80,
                'taxes' => 20,
                'passenger_counts' => ['adults' => 1, 'children' => 0, 'infants' => 0],
            ],
        ];
        $passengerData = [
            'passengers' => [['passenger_type' => 'adult', 'first_name' => 'A', 'last_name' => 'B']],
            'contact' => ['email' => 'a@example.com', 'phone' => '+1000000000'],
        ];
        $draft = $builder->buildInternalDraft($offer, $passengerData);
        unset($draft['_valid']);
        $env = $builder->buildTripOrdersCreateBookingEnvelope($draft, [], 'trip_orders_flight_offer_root_v1');
        $wire = $builder->tripOrdersWirePostBodyFromEnvelope($env);
        $this->assertArrayHasKey('flightOffer', $wire);
        $this->assertArrayNotHasKey('createBooking', $wire);
        $sum = $builder->summarizeTripOrdersWirePostBody($wire);
        $this->assertTrue($sum['wire_has_flight_offer_at_root']);
        $this->assertArrayNotHasKey('remarks', $wire);
        $this->assertFalse($sum['wire_has_remarks']);
        $this->assertSame(0, $sum['wire_remarks_count']);
    }

    public function test_trip_orders_flight_details_root_v1_puts_flight_details_at_wire_root(): void
    {
        $builder = app(SabreBookingPayloadBuilder::class);
        $offer = [
            'id' => 'o2',
            'supplier_connection_id' => 1,
            'supplier_offer_id' => 'so2',
            'airline_code' => 'EK',
            'validating_carrier' => 'EK',
            'segments' => [[
                'origin' => 'LHE',
                'destination' => 'DXB',
                'departure_at' => '2026-06-01T08:00:00Z',
                'arrival_at' => '2026-06-01T14:00:00Z',
                'carrier' => 'EK',
                'flight_number' => '615',
                'booking_class' => 'K',
                'fare_basis_code' => 'KLITE1',
            ]],
            'fare_breakdown' => [
                'supplier_total' => 100,
                'currency' => 'USD',
                'base_fare' => 80,
                'taxes' => 20,
                'passenger_counts' => ['adults' => 1, 'children' => 0, 'infants' => 0],
            ],
        ];
        $passengerData = [
            'passengers' => [['passenger_type' => 'adult', 'first_name' => 'A', 'last_name' => 'B']],
            'contact' => ['email' => 'a@example.com', 'phone' => '+1000000000'],
        ];
        $draft = $builder->buildInternalDraft($offer, $passengerData);
        unset($draft['_valid']);
        $env = $builder->buildTripOrdersCreateBookingEnvelope($draft, [], 'trip_orders_flight_details_root_v1');
        $wire = $builder->tripOrdersWirePostBodyFromEnvelope($env);
        $this->assertArrayHasKey('flightDetails', $wire);
        $this->assertArrayNotHasKey('createBooking', $wire);
        $this->assertTrue($builder->summarizeTripOrdersWirePostBody($wire)['wire_has_flight_details_at_root']);
    }

    public function test_artisan_wire_preview_json_prints_wire_root_keys(): void
    {
        $booking = $this->seedWireTestBooking();
        config([
            'suppliers.sabre.booking_path' => '/v1/trip/orders/createBooking',
            'suppliers.sabre.booking_schema' => 'trip_orders_create_booking',
            'suppliers.sabre.createbooking_payload_style' => 'trip_orders_flight_offer_root_v1',
        ]);

        Artisan::call('sabre:inspect-booking-payload', [
            '--booking' => (string) $booking->id,
            '--wire-preview-json' => true,
        ]);
        $out = Artisan::output();
        $this->assertStringContainsString('wire_root_keys=', $out);
        $this->assertStringContainsString('flightOffer', $out);
        foreach (['wire-b23-test@example.com', 'WireTest', 'Bearer ', '_ota_payload_schema'] as $needle) {
            $this->assertStringNotContainsString($needle, $out, 'wire preview must not leak: '.$needle);
        }
    }

    public function test_write_wire_preview_omits_internal_ota_keys(): void
    {
        $booking = $this->seedWireTestBooking();
        config([
            'suppliers.sabre.booking_path' => '/v1/trip/orders/createBooking',
            'suppliers.sabre.booking_schema' => 'trip_orders_create_booking',
            'suppliers.sabre.createbooking_payload_style' => 'trip_orders_flight_offer_root_v1',
        ]);
        $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'sabre-wire-preview-b23-'.uniqid('', true).'.json';
        Artisan::call('sabre:inspect-booking-payload', [
            '--booking' => (string) $booking->id,
            '--write-wire-preview' => $path,
        ]);
        $this->assertFileExists($path);
        $raw = (string) file_get_contents($path);
        foreach (['_ota_createbooking_payload_style', '_ota_payload_schema', 'wire-b23-test@example.com', 'WireTest'] as $needle) {
            $this->assertStringNotContainsString($needle, $raw, 'file must omit/leak: '.$needle);
        }
        $decoded = json_decode($raw, true);
        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('wire_request_body', $decoded);
        $this->assertArrayHasKey('flightOffer', $decoded['wire_request_body']);
        @unlink($path);
    }

    public function test_compare_createbooking_styles_invalid_style_fails_artisan(): void
    {
        $booking = $this->seedWireTestBooking();
        $exit = Artisan::call('sabre:compare-createbooking-styles', [
            '--booking' => (string) $booking->id,
            '--style' => 'not_a_real_style',
        ]);
        $this->assertSame(1, $exit);
        $out = Artisan::output();
        $this->assertStringContainsString('Invalid --style', $out);
        $this->assertStringContainsString('trip_orders_flight_offer_v1', $out);
    }

    public function test_compare_createbooking_styles_legacy_current_style_is_rejected(): void
    {
        $booking = $this->seedWireTestBooking();
        $exit = Artisan::call('sabre:compare-createbooking-styles', [
            '--booking' => (string) $booking->id,
            '--style' => 'trip_orders_create_booking_v1_current',
        ]);
        $this->assertSame(1, $exit);
        $out = Artisan::output();
        $this->assertStringContainsString('Invalid --style', $out);
    }

    public function test_compare_createbooking_styles_with_style_emits_single_block(): void
    {
        $booking = $this->seedWireTestBooking();
        config([
            'suppliers.sabre.booking_path' => '/v1/trip/orders/createBooking',
            'suppliers.sabre.booking_schema' => 'trip_orders_create_booking',
        ]);
        Artisan::call('sabre:compare-createbooking-styles', [
            '--booking' => (string) $booking->id,
            '--style' => 'trip_orders_flight_offer_root_v1',
        ]);
        $out = Artisan::output();
        $this->assertStringContainsString('style=trip_orders_flight_offer_root_v1', $out);
        $this->assertSame(1, substr_count($out, '---'));
    }

    public function test_compare_createbooking_styles_without_style_emits_one_block_per_compare_style(): void
    {
        $booking = $this->seedWireTestBooking();
        config([
            'suppliers.sabre.booking_path' => '/v1/trip/orders/createBooking',
            'suppliers.sabre.booking_schema' => 'trip_orders_create_booking',
        ]);
        Artisan::call('sabre:compare-createbooking-styles', [
            '--booking' => (string) $booking->id,
        ]);
        $out = Artisan::output();
        $expected = count(SabreBookingPayloadBuilder::TRIP_ORDERS_CREATEBOOKING_COMPARE_STYLES);
        $this->assertSame($expected, substr_count($out, '---'));
    }

    public function test_root_style_wire_diagnostics_show_nonzero_segment_and_traveler_counts(): void
    {
        $booking = $this->seedWireTestBooking();
        $builder = app(SabreBookingPayloadBuilder::class);
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $snapshot = is_array($meta['normalized_offer_snapshot'] ?? null) ? $meta['normalized_offer_snapshot'] : [];
        $svc = app(SabreBookingService::class);
        $draft = $svc->prepareBookingPayload($snapshot, [
            'contact' => ['email' => 't@example.com', 'phone' => '+1000000000'],
            'passengers' => [['passenger_type' => 'adult', 'first_name' => 'A', 'last_name' => 'B']],
        ]);
        $this->assertTrue((bool) ($draft['_valid'] ?? false));
        unset($draft['_valid']);
        $env = $builder->buildTripOrdersCreateBookingEnvelope($draft, [], 'trip_orders_flight_offer_root_v1');
        $d = $builder->summarizeTripOrdersWirePostBodyForEnvelope($env);
        $this->assertGreaterThanOrEqual(1, (int) ($d['wire_traveler_count'] ?? 0));
        $this->assertGreaterThanOrEqual(1, (int) ($d['wire_flight_offer_segment_count'] ?? 0));
        $this->assertGreaterThanOrEqual(1, (int) ($d['wire_fare_basis_count'] ?? 0));
        $this->assertTrue($d['wire_has_validating_carrier'] ?? false);
        $this->assertTrue($d['wire_has_amount'] ?? false);
        $this->assertTrue($d['wire_has_currency'] ?? false);
    }

    public function test_compare_send_without_style_refuses(): void
    {
        $booking = $this->seedWireTestBooking();
        $exit = Artisan::call('sabre:compare-createbooking-styles', [
            '--booking' => (string) $booking->id,
            '--send' => true,
        ]);
        $this->assertSame(1, $exit);
        $this->assertStringContainsString('Live send requires --style or --send-all.', Artisan::output());
    }

    public function test_compare_send_with_send_all_runs_when_live_disabled(): void
    {
        $booking = $this->seedWireTestBooking();
        config([
            'suppliers.sabre.booking_enabled' => true,
            'suppliers.sabre.booking_live_call_enabled' => false,
            'suppliers.sabre.booking_path' => '/v1/trip/orders/createBooking',
            'suppliers.sabre.booking_schema' => 'trip_orders_create_booking',
        ]);
        $exit = Artisan::call('sabre:compare-createbooking-styles', [
            '--booking' => (string) $booking->id,
            '--send' => true,
            '--send-all' => true,
        ]);
        $this->assertSame(0, $exit);
        $this->assertStringContainsString('send_skipped', Artisan::output());
    }

    public function test_compare_send_style_records_attempt_with_http_400_digest(): void
    {
        $booking = $this->seedWireTestBooking();
        $sabreBase = rtrim((string) config('suppliers.sabre.default_base_url'), '/');
        $tokenPath = (string) config('suppliers.sabre.token_path', '/v2/auth/token');
        $bookingPath = '/v1/trip/orders/createBooking';
        Http::fake([
            $sabreBase.$tokenPath => Http::response(['access_token' => 'tok', 'expires_in' => 3600], 200),
            $sabreBase.$bookingPath => Http::response([
                'errorCode' => 'SOME_CODE',
                'type' => 'Application',
                'message' => 'Root validation failed',
                'additionalMessages' => ['Extra context'],
                'errors' => [
                    [
                        'code' => 'MANDATORY_DATA_MISSING',
                        'message' => 'Missing product',
                        'field' => 'flightOffer',
                        'source' => ['pointer' => '/travelers/0/givenName'],
                    ],
                ],
            ], 400),
        ]);
        config([
            'suppliers.sabre.booking_enabled' => true,
            'suppliers.sabre.booking_live_call_enabled' => true,
            'suppliers.sabre.booking_path' => $bookingPath,
            'suppliers.sabre.booking_schema' => 'trip_orders_create_booking',
            'suppliers.sabre.ticketing_enabled' => false,
        ]);
        $conn = SupplierConnection::query()->findOrFail((int) $booking->meta['supplier_connection_id']);
        $conn->forceFill([
            'is_active' => true,
            'status' => SupplierConnectionStatus::Active,
        ]);
        $conn->credentials = ['client_id' => 'cid', 'client_secret' => 'sec'];
        $conn->save();

        Artisan::call('sabre:compare-createbooking-styles', [
            '--booking' => (string) $booking->id,
            '--send' => true,
            '--style' => 'trip_orders_flight_offer_root_v1',
        ]);
        $attempt = SupplierBookingAttempt::query()->where('booking_id', $booking->id)
            ->where('action', 'compare_trip_orders_createbooking_style')
            ->orderByDesc('id')
            ->first();
        $this->assertNotNull($attempt);
        $safe = is_array($attempt->safe_summary) ? $attempt->safe_summary : [];
        $this->assertSame(400, (int) ($safe['http_status'] ?? 0));
        $this->assertContains('MANDATORY_DATA_MISSING', $safe['response_error_codes'] ?? []);
        $this->assertContains('flightOffer', $safe['response_error_fields'] ?? []);
        $this->assertContains('errorCode', $safe['response_top_level_keys'] ?? []);
        $this->assertSame('SOME_CODE', $safe['response_top_level_error_code'] ?? null);
        $this->assertGreaterThanOrEqual(1, (int) ($safe['wire_traveler_count'] ?? 0));
        $this->assertFalse((bool) ($safe['wire_has_remarks'] ?? true));
        $this->assertSame(0, (int) ($safe['wire_remarks_count'] ?? -1));
        $this->assertContains('/travelers/0/givenName', $safe['response_error_paths'] ?? []);
        $this->assertTrue((bool) ($safe['ticketing_disabled'] ?? false));
        $reportPath = storage_path('app/sabre-createbooking-style-compare-booking-'.$booking->id.'.json');
        $this->assertFileExists($reportPath);
        @unlink($reportPath);
    }

    public function test_map_to_sabre_trip_orders_gender_enum_variants(): void
    {
        $b = app(SabreBookingPayloadBuilder::class);
        $this->assertSame('MALE', $b->mapToSabreTripOrdersGenderEnum('M'));
        $this->assertSame('MALE', $b->mapToSabreTripOrdersGenderEnum('male'));
        $this->assertSame('MALE', $b->mapToSabreTripOrdersGenderEnum('Male'));
        $this->assertSame('MALE', $b->mapToSabreTripOrdersGenderEnum('MALE'));
        $this->assertSame('FEMALE', $b->mapToSabreTripOrdersGenderEnum('F'));
        $this->assertSame('FEMALE', $b->mapToSabreTripOrdersGenderEnum('female'));
        $this->assertSame('FEMALE', $b->mapToSabreTripOrdersGenderEnum('Female'));
        $this->assertSame('INFANT_MALE', $b->mapToSabreTripOrdersGenderEnum('IM'));
        $this->assertSame('INFANT_MALE', $b->mapToSabreTripOrdersGenderEnum('infant_male'));
        $this->assertSame('INFANT_FEMALE', $b->mapToSabreTripOrdersGenderEnum('IF'));
        $this->assertSame('INFANT_FEMALE', $b->mapToSabreTripOrdersGenderEnum('infant_female'));
        $this->assertSame('UNDISCLOSED', $b->mapToSabreTripOrdersGenderEnum('unknown'));
        $this->assertSame('UNDISCLOSED', $b->mapToSabreTripOrdersGenderEnum(null));
        $this->assertSame('UNDISCLOSED', $b->mapToSabreTripOrdersGenderEnum(''));
    }

    public function test_trip_orders_wire_traveler_gender_is_sabre_enum(): void
    {
        $builder = app(SabreBookingPayloadBuilder::class);
        $offer = [
            'id' => 'o-g1',
            'supplier_connection_id' => 1,
            'supplier_offer_id' => 'so-g1',
            'airline_code' => 'EK',
            'validating_carrier' => 'EK',
            'segments' => [[
                'origin' => 'LHE',
                'destination' => 'DXB',
                'departure_at' => '2026-06-01T08:00:00Z',
                'arrival_at' => '2026-06-01T14:00:00Z',
                'carrier' => 'EK',
                'flight_number' => '615',
                'booking_class' => 'K',
                'fare_basis_code' => 'KLITE1',
            ]],
            'fare_breakdown' => [
                'supplier_total' => 100,
                'currency' => 'USD',
                'base_fare' => 80,
                'taxes' => 20,
                'passenger_counts' => ['adults' => 1, 'children' => 0, 'infants' => 0],
            ],
        ];
        $passengerData = [
            'passengers' => [[
                'passenger_type' => 'adult',
                'first_name' => 'A',
                'last_name' => 'B',
                'gender' => 'M',
            ]],
            'contact' => ['email' => 'a@example.com', 'phone' => '+1000000000'],
        ];
        $draft = $builder->buildInternalDraft($offer, $passengerData);
        $this->assertSame('MALE', $draft['passengers'][0]['gender'] ?? null);
        unset($draft['_valid']);
        $env = $builder->buildTripOrdersCreateBookingEnvelope($draft, [], 'trip_orders_flight_offer_root_v1');
        $wire = $builder->tripOrdersWirePostBodyFromEnvelope($env);
        $this->assertSame('MALE', $wire['travelers'][0]['gender'] ?? null);
        $summary = $builder->summarizeTripOrdersWirePostBody($wire);
        $this->assertTrue($summary['wire_gender_enum_valid']);
        $this->assertSame(['MALE'], $summary['wire_gender_values_sanitized']);
        $this->assertArrayNotHasKey('remarks', $wire);
        $this->assertFalse($summary['wire_has_remarks']);
        $this->assertSame(0, $summary['wire_remarks_count']);
    }

    public function test_artisan_wire_preview_prints_gender_enum_diagnostics(): void
    {
        $booking = $this->seedWireTestBooking();
        BookingPassenger::query()->where('booking_id', $booking->id)->update(['gender' => 'F']);
        config([
            'suppliers.sabre.booking_path' => '/v1/trip/orders/createBooking',
            'suppliers.sabre.booking_schema' => 'trip_orders_create_booking',
            'suppliers.sabre.createbooking_payload_style' => 'trip_orders_flight_offer_root_v1',
        ]);
        Artisan::call('sabre:inspect-booking-payload', [
            '--booking' => (string) $booking->id,
            '--wire-preview-json' => true,
            '--style' => 'trip_orders_flight_offer_root_v1',
        ]);
        $out = Artisan::output();
        $this->assertStringContainsString('wire_gender_enum_valid=true', $out);
        $this->assertStringContainsString('wire_gender_values_sanitized=', $out);
        $this->assertStringContainsString('FEMALE', $out);
        $this->assertStringContainsString('wire_ticketing_enabled=false', $out);
        $this->assertStringContainsString('wire_has_remarks=false', $out);
        $this->assertStringContainsString('wire_remarks_count=0', $out);
        foreach (['wire-b23-test@example.com', 'WireTest', 'XT8888888', '1990'] as $needle) {
            $this->assertStringNotContainsString($needle, $out, 'wire preview must not leak: '.$needle);
        }
        $jsonLine = null;
        foreach (explode("\n", $out) as $line) {
            if (str_starts_with($line, 'redacted_wire_request_body=')) {
                $jsonLine = substr($line, strlen('redacted_wire_request_body='));

                break;
            }
        }
        $this->assertIsString($jsonLine);
        $decoded = json_decode($jsonLine, true);
        $this->assertIsArray($decoded);
        $g = $decoded['travelers'][0]['gender'] ?? null;
        $this->assertSame('FEMALE', $g);
        $this->assertSame('[redacted]', $decoded['travelers'][0]['given_name'] ?? null);
        $this->assertArrayNotHasKey('remarks', $decoded);
    }

    public function test_trip_orders_wire_send_remarks_true_uses_object_rows_not_strings(): void
    {
        config(['suppliers.sabre.createbooking_send_remarks' => true]);
        $builder = app(SabreBookingPayloadBuilder::class);
        $offer = [
            'id' => 'o-rm1',
            'supplier_connection_id' => 1,
            'supplier_offer_id' => 'so-rm1',
            'airline_code' => 'EK',
            'validating_carrier' => 'EK',
            'segments' => [[
                'origin' => 'LHE',
                'destination' => 'DXB',
                'departure_at' => '2026-06-01T08:00:00Z',
                'arrival_at' => '2026-06-01T14:00:00Z',
                'carrier' => 'EK',
                'flight_number' => '615',
                'booking_class' => 'K',
                'fare_basis_code' => 'KLITE1',
            ]],
            'fare_breakdown' => [
                'supplier_total' => 100,
                'currency' => 'USD',
                'base_fare' => 80,
                'taxes' => 20,
                'passenger_counts' => ['adults' => 1, 'children' => 0, 'infants' => 0],
            ],
            'baggage' => ['summary' => '1PC'],
        ];
        $passengerData = [
            'passengers' => [[
                'passenger_type' => 'adult',
                'first_name' => 'A',
                'last_name' => 'B',
                'gender' => 'M',
            ]],
            'contact' => ['email' => 'a@example.com', 'phone' => '+1000000000'],
        ];
        $draft = $builder->buildInternalDraft($offer, $passengerData);
        unset($draft['_valid']);
        $env = $builder->buildTripOrdersCreateBookingEnvelope($draft, [], 'trip_orders_flight_offer_root_v1');
        $wire = $builder->tripOrdersWirePostBodyFromEnvelope($env);
        $this->assertArrayHasKey('remarks', $wire);
        $this->assertIsArray($wire['remarks']);
        $this->assertNotEmpty($wire['remarks']);
        $first = $wire['remarks'][0];
        $this->assertIsArray($first);
        $this->assertArrayHasKey('type', $first);
        $this->assertArrayHasKey('text', $first);
        $this->assertIsString($first['text']);
        $summary = $builder->summarizeTripOrdersWirePostBody($wire);
        $this->assertTrue($summary['wire_has_remarks']);
        $this->assertGreaterThanOrEqual(1, $summary['wire_remarks_count']);
        config(['suppliers.sabre.createbooking_send_remarks' => false]);
    }

    public function test_international_wire_traveler_validation_fails_without_passport_data(): void
    {
        $booking = $this->seedWireTestBooking();
        $builder = app(SabreBookingPayloadBuilder::class);
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $snapshot = is_array($meta['normalized_offer_snapshot'] ?? null) ? $meta['normalized_offer_snapshot'] : [];
        $svc = app(SabreBookingService::class);
        $draft = $svc->prepareBookingPayload($snapshot, [
            'contact' => ['email' => 't@example.com', 'phone' => '+1000000000'],
            'passengers' => [[
                'passenger_type' => 'adult',
                'first_name' => 'WireTest',
                'last_name' => 'Passenger',
            ]],
        ]);
        $this->assertTrue((bool) ($draft['_valid'] ?? false));
        $this->assertTrue((bool) ($draft['_requires_passport_doc'] ?? false));
        unset($draft['_valid']);
        $env = $builder->buildTripOrdersCreateBookingEnvelope($draft, [], 'trip_orders_flight_offer_root_v1');
        $sum = $builder->summarizeTripOrdersWirePostBodyForEnvelope($env);
        $this->assertFalse((bool) ($sum['wire_traveler_required_fields_valid'] ?? true));
        $this->assertContains('traveler_1_passport', $sum['wire_invalid_traveler_field_keys'] ?? []);
    }

    public function test_trip_orders_wire_strips_digits_from_traveler_names(): void
    {
        $builder = app(SabreBookingPayloadBuilder::class);
        $offer = [
            'id' => 'o-n1',
            'supplier_connection_id' => 1,
            'supplier_offer_id' => 'so-n1',
            'airline_code' => 'EK',
            'validating_carrier' => 'EK',
            'segments' => [[
                'origin' => 'LHE',
                'destination' => 'DXB',
                'departure_at' => '2026-06-01T08:00:00Z',
                'arrival_at' => '2026-06-01T14:00:00Z',
                'carrier' => 'EK',
                'flight_number' => '615',
                'booking_class' => 'K',
                'fare_basis_code' => 'KLITE1',
            ]],
            'fare_breakdown' => [
                'supplier_total' => 100,
                'currency' => 'USD',
                'base_fare' => 80,
                'taxes' => 20,
                'passenger_counts' => ['adults' => 1, 'children' => 0, 'infants' => 0],
            ],
        ];
        $passengerData = [
            'passengers' => [[
                'passenger_type' => 'adult',
                'first_name' => 'John2',
                'last_name' => 'Doe3',
                'gender' => 'M',
            ]],
            'contact' => ['email' => 'a@example.com', 'phone' => '+1000000000'],
        ];
        $draft = $builder->buildInternalDraft($offer, $passengerData);
        unset($draft['_valid']);
        $env = $builder->buildTripOrdersCreateBookingEnvelope($draft, [], 'trip_orders_flight_offer_root_v1');
        $wire = $builder->tripOrdersWirePostBodyFromEnvelope($env);
        $this->assertSame('John', $wire['travelers'][0]['given_name'] ?? null);
        $this->assertSame('Doe', $wire['travelers'][0]['surname'] ?? null);
        $this->assertTrue(SabreBookingPayloadBuilder::sabreTripOrdersPersonNamePatternValid((string) ($wire['travelers'][0]['given_name'] ?? '')));
    }

    public function test_wire_traveler_validation_flags_blank_names(): void
    {
        $builder = app(SabreBookingPayloadBuilder::class);
        $wire = [
            'travelers' => [[
                'given_name' => '',
                'surname' => 'X',
                'gender' => 'MALE',
                'birth_date' => '1990-01-01',
            ]],
        ];
        $d = $builder->summarizeTripOrdersWirePostBody($wire, false);
        $this->assertFalse((bool) ($d['wire_traveler_required_fields_valid'] ?? true));
        $this->assertContains('traveler_1_given_name', $d['wire_invalid_traveler_field_keys'] ?? []);
    }

    public function test_wire_traveler_validation_flags_empty_passport_document_type_when_passport_present(): void
    {
        $builder = app(SabreBookingPayloadBuilder::class);
        $wire = [
            'travelers' => [[
                'given_name' => 'Test',
                'surname' => 'User',
                'gender' => 'MALE',
                'birth_date' => '1990-01-01',
                'passport' => [
                    'document_type' => '',
                    'issuing_country' => 'PK',
                    'nationality' => 'PK',
                    'expiry_date' => '2030-01-01',
                    'number' => 'X123',
                ],
            ]],
        ];
        $d = $builder->summarizeTripOrdersWirePostBody($wire, true);
        $this->assertFalse((bool) ($d['wire_traveler_required_fields_valid'] ?? true));
        $this->assertContains('traveler_1_passport.document_type', $d['wire_invalid_traveler_field_keys'] ?? []);
    }

    public function test_passport_document_type_pp_maps_to_configured_sabre_value(): void
    {
        config(['suppliers.sabre.document_type_passport_value' => 'PASSPORTX']);
        $builder = app(SabreBookingPayloadBuilder::class);
        $offer = [
            'id' => 'o-dt1',
            'supplier_connection_id' => 1,
            'supplier_offer_id' => 'so-dt1',
            'airline_code' => 'EK',
            'validating_carrier' => 'EK',
            'segments' => [[
                'origin' => 'LHE',
                'destination' => 'DXB',
                'departure_at' => '2026-06-01T08:00:00Z',
                'arrival_at' => '2026-06-01T14:00:00Z',
                'carrier' => 'EK',
                'flight_number' => '615',
                'booking_class' => 'K',
                'fare_basis_code' => 'KLITE1',
            ]],
            'fare_breakdown' => [
                'supplier_total' => 100,
                'currency' => 'USD',
                'base_fare' => 80,
                'taxes' => 20,
                'passenger_counts' => ['adults' => 1, 'children' => 0, 'infants' => 0],
            ],
        ];
        $passengerData = [
            'passengers' => [[
                'passenger_type' => 'adult',
                'first_name' => 'A',
                'last_name' => 'B',
                'gender' => 'M',
                'document_type' => 'PP',
                'passport_number' => 'AB1234567',
                'passport_issuing_country' => 'PK',
                'passport_expiry_date' => '2030-06-01',
                'nationality' => 'PK',
            ]],
            'contact' => ['email' => 'a@example.com', 'phone' => '+1000000000'],
        ];
        $draft = $builder->buildInternalDraft($offer, $passengerData);
        unset($draft['_valid']);
        $env = $builder->buildTripOrdersCreateBookingEnvelope($draft, [], 'trip_orders_flight_offer_root_v1');
        $wire = $builder->tripOrdersWirePostBodyFromEnvelope($env);
        $this->assertSame('PASSPORTX', $wire['travelers'][0]['passport']['document_type'] ?? null);
        config(['suppliers.sabre.document_type_passport_value' => 'PASSPORT']);
    }

    public function test_compare_send_skips_live_http_when_traveler_payload_invalid(): void
    {
        Http::fake();
        $booking = $this->seedWireTestBooking();
        BookingPassenger::query()->where('booking_id', $booking->id)->update([
            'passport_number' => null,
            'passport_issuing_country' => null,
            'passport_expiry_date' => null,
            'document_type' => null,
        ]);
        $conn = SupplierConnection::query()->findOrFail((int) $booking->meta['supplier_connection_id']);
        $conn->forceFill([
            'is_active' => true,
            'status' => SupplierConnectionStatus::Active,
        ]);
        $conn->credentials = ['client_id' => 'cid', 'client_secret' => 'sec'];
        $conn->save();
        config([
            'suppliers.sabre.booking_enabled' => true,
            'suppliers.sabre.booking_live_call_enabled' => true,
            'suppliers.sabre.booking_path' => '/v1/trip/orders/createBooking',
            'suppliers.sabre.booking_schema' => 'trip_orders_create_booking',
            'suppliers.sabre.ticketing_enabled' => false,
        ]);
        Artisan::call('sabre:compare-createbooking-styles', [
            '--booking' => (string) $booking->id,
            '--send' => true,
            '--style' => 'trip_orders_flight_offer_root_v1',
        ]);
        $out = Artisan::output();
        $this->assertStringContainsString('status=payload_validation_failed', $out);
        $this->assertStringContainsString('http_status=not_sent', $out);
        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/v1/trip/orders/createBooking'));
    }

    public function test_artisan_wire_preview_includes_traveler_field_diagnostics_without_pii(): void
    {
        $booking = $this->seedWireTestBooking();
        config([
            'suppliers.sabre.booking_path' => '/v1/trip/orders/createBooking',
            'suppliers.sabre.booking_schema' => 'trip_orders_create_booking',
            'suppliers.sabre.createbooking_payload_style' => 'trip_orders_flight_offer_root_v1',
        ]);
        Artisan::call('sabre:inspect-booking-payload', [
            '--booking' => (string) $booking->id,
            '--wire-preview-json' => true,
        ]);
        $out = Artisan::output();
        $this->assertStringContainsString('wire_traveler_required_fields_valid=', $out);
        $this->assertStringContainsString('traveler_1_has_given_name=', $out);
        foreach (['WireTest', 'wire-b23-test@', '1990', 'XT8888888'] as $needle) {
            $this->assertStringNotContainsString($needle, $out, 'wire preview must not leak: '.$needle);
        }
    }

    public function test_create_booking_short_circuits_without_http_when_traveler_payload_invalid(): void
    {
        Http::fake();
        $booking = $this->seedWireTestBooking();
        BookingPassenger::query()->where('booking_id', $booking->id)->update([
            'passport_number' => null,
            'passport_issuing_country' => null,
            'passport_expiry_date' => null,
            'document_type' => null,
        ]);
        $conn = SupplierConnection::query()->findOrFail((int) $booking->meta['supplier_connection_id']);
        $conn->forceFill([
            'is_active' => true,
            'status' => SupplierConnectionStatus::Active,
        ]);
        $conn->credentials = ['client_id' => 'cid', 'client_secret' => 'sec'];
        $conn->save();
        config([
            'suppliers.sabre.booking_enabled' => true,
            'suppliers.sabre.booking_live_call_enabled' => true,
            'suppliers.sabre.booking_path' => '/v1/trip/orders/createBooking',
            'suppliers.sabre.booking_schema' => 'trip_orders_create_booking',
            'suppliers.sabre.createbooking_payload_style' => 'trip_orders_flight_offer_root_v1',
            'suppliers.sabre.ticketing_enabled' => false,
        ]);
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $snapshot = is_array($meta['normalized_offer_snapshot'] ?? null) ? $meta['normalized_offer_snapshot'] : [];
        $result = app(SabreBookingService::class)->createBooking($snapshot, [
            'contact' => ['email' => 'x@example.com', 'phone' => '+1000000000'],
            'passengers' => [[
                'passenger_type' => 'adult',
                'first_name' => 'WireTest',
                'last_name' => 'Passenger',
            ]],
        ], $booking->id);
        $this->assertFalse((bool) ($result['success'] ?? true));
        $this->assertSame('payload_validation_failed', (string) ($result['status'] ?? ''));
        $this->assertSame('sabre_booking_payload_validation_failed', (string) ($result['error_code'] ?? ''));
        $this->assertFalse((bool) ($result['live_call_attempted'] ?? true));
        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/v1/trip/orders/createBooking'));
    }

    public function test_b28_summarize_envelope_flags_contract_invalid_when_pricing_total_nulled(): void
    {
        $builder = app(SabreBookingPayloadBuilder::class);
        $booking = $this->seedWireTestBooking();
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $snapshot = is_array($meta['normalized_offer_snapshot'] ?? null) ? $meta['normalized_offer_snapshot'] : [];
        $draft = $builder->buildInternalDraft($snapshot, [
            'passengers' => [[
                'passenger_type' => 'adult',
                'first_name' => 'WireTest',
                'last_name' => 'Passenger',
                'gender' => 'M',
                'date_of_birth' => '1990-01-01',
                'passport_number' => 'XT8888888',
                'passport_issuing_country' => 'PK',
                'passport_expiry_date' => '2035-06-01',
                'nationality' => 'PK',
                'document_type' => 'passport',
            ]],
            'contact' => ['email' => 'wire-b23-test@example.com', 'phone' => '+923001234567'],
        ]);
        unset($draft['_valid']);
        $env = $builder->buildTripOrdersCreateBookingEnvelope($draft, [], 'trip_orders_flight_offer_root_v1');
        $env['pricing']['total'] = null;
        $wireDiag = $builder->summarizeTripOrdersWirePostBodyForEnvelope($env);
        $this->assertFalse((bool) ($wireDiag['wire_contract_valid'] ?? true));
        $this->assertContains('pricing.total', $wireDiag['wire_invalid_contract_keys'] ?? []);
        $envelopeDiag = $builder->summarizeEnvelopeForDiagnostics($env);
        $this->assertFalse((bool) ($envelopeDiag['validation_ok'] ?? true));
    }

    public function test_b28_final_wire_drops_null_optional_shop_context_keys(): void
    {
        $builder = app(SabreBookingPayloadBuilder::class);
        $booking = $this->seedWireTestBooking();
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $snapshot = is_array($meta['normalized_offer_snapshot'] ?? null) ? $meta['normalized_offer_snapshot'] : [];
        $draft = $builder->buildInternalDraft($snapshot, [
            'passengers' => [[
                'passenger_type' => 'adult',
                'first_name' => 'WireTest',
                'last_name' => 'Passenger',
                'gender' => 'M',
                'date_of_birth' => '1990-01-01',
                'passport_number' => 'XT8888888',
                'passport_issuing_country' => 'PK',
                'passport_expiry_date' => '2035-06-01',
                'nationality' => 'PK',
                'document_type' => 'passport',
            ]],
            'contact' => ['email' => 'wire-b23-test@example.com', 'phone' => '+923001234567'],
        ]);
        unset($draft['_valid']);
        $env = $builder->buildTripOrdersCreateBookingEnvelope($draft, [], 'trip_orders_flight_offer_root_v1');
        $env['shop_context'] = array_merge(
            is_array($env['shop_context'] ?? null) ? $env['shop_context'] : [],
            ['probe_null' => null, 'probe_keep' => 'x'],
        );
        $sum = $builder->summarizeTripOrdersWirePostBodyForEnvelope($env);
        $this->assertTrue($sum['wire_has_any_nulls']);
        $this->assertTrue($sum['wire_payload_null_free']);
        $final = $builder->tripOrdersFinalWirePostBodyFromEnvelope($env);
        $this->assertArrayNotHasKey('probe_null', is_array($final['shop_context'] ?? null) ? $final['shop_context'] : []);
        $this->assertSame('x', is_array($final['shop_context'] ?? null) ? ($final['shop_context']['probe_keep'] ?? null) : null);
    }

    public function test_b28_artisan_wire_preview_emits_null_scanner_keys(): void
    {
        $booking = $this->seedWireTestBooking();
        config([
            'suppliers.sabre.booking_path' => '/v1/trip/orders/createBooking',
            'suppliers.sabre.booking_schema' => 'trip_orders_create_booking',
            'suppliers.sabre.createbooking_payload_style' => 'trip_orders_flight_offer_root_v1',
        ]);
        Artisan::call('sabre:inspect-booking-payload', [
            '--booking' => (string) $booking->id,
            '--wire-preview-json' => true,
            '--style' => 'trip_orders_flight_offer_root_v1',
        ]);
        $out = Artisan::output();
        $this->assertStringContainsString('wire_payload_null_free=', $out);
        $this->assertStringContainsString('wire_null_path_count=', $out);
        $this->assertStringContainsString('wire_contract_valid=', $out);
    }

    public function test_b29_trip_orders_flight_details_camel_v1_wire_uses_camel_traveler_keys(): void
    {
        $builder = app(SabreBookingPayloadBuilder::class);
        $offer = [
            'id' => 'o-camel-1',
            'supplier_connection_id' => 1,
            'supplier_offer_id' => 'so-camel',
            'airline_code' => 'EK',
            'validating_carrier' => 'EK',
            'segments' => [[
                'origin' => 'LHE',
                'destination' => 'DXB',
                'departure_at' => '2026-06-01T08:00:00Z',
                'arrival_at' => '2026-06-01T14:00:00Z',
                'carrier' => 'EK',
                'flight_number' => '615',
                'booking_class' => 'K',
                'fare_basis_code' => 'KLITE1',
            ]],
            'fare_breakdown' => [
                'supplier_total' => 100,
                'currency' => 'USD',
                'base_fare' => 80,
                'taxes' => 20,
                'passenger_counts' => ['adults' => 1, 'children' => 0, 'infants' => 0],
            ],
        ];
        $passengerData = [
            'passengers' => [[
                'passenger_type' => 'adult',
                'first_name' => 'Zayn',
                'last_name' => 'CamelCase',
                'gender' => 'M',
                'date_of_birth' => '1991-02-02',
                'passport_number' => 'AB1234567',
                'passport_issuing_country' => 'PK',
                'passport_expiry_date' => '2030-05-05',
                'nationality' => 'PK',
                'document_type' => 'passport',
            ]],
            'contact' => ['email' => 'z@example.com', 'phone' => '+1000000000'],
        ];
        $draft = $builder->buildInternalDraft($offer, $passengerData);
        unset($draft['_valid']);
        $env = $builder->buildTripOrdersCreateBookingEnvelope($draft, [], 'trip_orders_flight_details_camel_v1');
        $wire = $builder->tripOrdersWirePostBodyFromEnvelope($env);
        $this->assertArrayHasKey('flightDetails', $wire);
        $t0 = is_array($wire['travelers'][0] ?? null) ? $wire['travelers'][0] : [];
        $this->assertArrayHasKey('givenName', $t0);
        $this->assertArrayNotHasKey('given_name', $t0);
        $this->assertArrayHasKey('birthDate', $t0);
        $this->assertArrayNotHasKey('birth_date', $t0);
        $this->assertArrayHasKey('passengerTypeCode', $t0);
        $this->assertArrayNotHasKey('passenger_type_code', $t0);
        $pp = is_array($t0['passport'] ?? null) ? $t0['passport'] : [];
        $this->assertArrayHasKey('documentType', $pp);
        $this->assertArrayHasKey('issuingCountry', $pp);
        $this->assertArrayHasKey('expiryDate', $pp);
        $this->assertArrayNotHasKey('document_type', $pp);
        $d = $builder->summarizeTripOrdersWirePostBody($wire, true, 'trip_orders_flight_details_camel_v1');
        $this->assertSame('camelCase', $d['wire_traveler_field_style']);
        $this->assertTrue($d['wire_gender_enum_valid']);
        $this->assertFalse($d['wire_ticketing_enabled']);
        $red = $builder->redactTripOrdersWireJsonForPreview($wire);
        $redJson = json_encode($red);
        $this->assertStringNotContainsString('Zayn', (string) $redJson);
        $this->assertStringNotContainsString('AB1234567', (string) $redJson);
    }

    public function test_b29_trip_orders_flight_offer_camel_v1_root_has_flight_offer(): void
    {
        $builder = app(SabreBookingPayloadBuilder::class);
        $offer = [
            'id' => 'o-camel-fo',
            'supplier_connection_id' => 1,
            'supplier_offer_id' => 'so-cfo',
            'airline_code' => 'EK',
            'validating_carrier' => 'EK',
            'segments' => [[
                'origin' => 'LHE',
                'destination' => 'DXB',
                'departure_at' => '2026-06-01T08:00:00Z',
                'arrival_at' => '2026-06-01T14:00:00Z',
                'carrier' => 'EK',
                'flight_number' => '615',
                'booking_class' => 'K',
                'fare_basis_code' => 'KLITE1',
            ]],
            'fare_breakdown' => [
                'supplier_total' => 100,
                'currency' => 'USD',
                'base_fare' => 80,
                'taxes' => 20,
                'passenger_counts' => ['adults' => 1, 'children' => 0, 'infants' => 0],
            ],
        ];
        $passengerData = [
            'passengers' => [['passenger_type' => 'adult', 'first_name' => 'A', 'last_name' => 'B', 'gender' => 'M', 'date_of_birth' => '1990-01-01']],
            'contact' => ['email' => 'a@example.com', 'phone' => '+1'],
        ];
        $draft = $builder->buildInternalDraft($offer, $passengerData);
        unset($draft['_valid']);
        $env = $builder->buildTripOrdersCreateBookingEnvelope($draft, [], 'trip_orders_flight_offer_camel_v1');
        $wire = $builder->tripOrdersWirePostBodyFromEnvelope($env);
        $this->assertArrayHasKey('flightOffer', $wire);
        $this->assertArrayHasKey('givenName', $wire['travelers'][0]);
    }

    public function test_b29_missing_given_name_blocks_traveler_validation_for_camel_style(): void
    {
        $builder = app(SabreBookingPayloadBuilder::class);
        $offer = [
            'id' => 'o-miss',
            'supplier_connection_id' => 1,
            'supplier_offer_id' => 'x',
            'airline_code' => 'EK',
            'validating_carrier' => 'EK',
            'segments' => [[
                'origin' => 'LHE',
                'destination' => 'DXB',
                'departure_at' => '2026-06-01T08:00:00Z',
                'arrival_at' => '2026-06-01T14:00:00Z',
                'carrier' => 'EK',
                'flight_number' => '615',
                'booking_class' => 'K',
                'fare_basis_code' => 'KLITE1',
            ]],
            'fare_breakdown' => [
                'supplier_total' => 100,
                'currency' => 'USD',
                'base_fare' => 80,
                'taxes' => 20,
                'passenger_counts' => ['adults' => 1, 'children' => 0, 'infants' => 0],
            ],
        ];
        $draft = $builder->buildInternalDraft($offer, [
            'passengers' => [['passenger_type' => 'adult', 'first_name' => '', 'last_name' => 'B', 'gender' => 'M', 'date_of_birth' => '1990-01-01']],
            'contact' => ['email' => 'a@example.com', 'phone' => '+1'],
        ]);
        unset($draft['_valid']);
        $env = $builder->buildTripOrdersCreateBookingEnvelope($draft, [], 'trip_orders_flight_details_camel_v1');
        $sum = $builder->summarizeTripOrdersWirePostBodyForEnvelope($env);
        $this->assertFalse($sum['wire_traveler_required_fields_valid']);
        $this->assertContains('traveler_1_givenName', $sum['wire_invalid_traveler_field_keys']);
    }

    public function test_b29_compare_styles_list_includes_camel_variants(): void
    {
        $this->assertContains('trip_orders_flight_details_camel_v1', SabreBookingPayloadBuilder::TRIP_ORDERS_CREATEBOOKING_COMPARE_STYLES);
        $this->assertContains('trip_orders_flight_offer_camel_v1', SabreBookingPayloadBuilder::TRIP_ORDERS_CREATEBOOKING_COMPARE_STYLES);
        $this->assertContains('trip_orders_flight_details_full_camel_v1', SabreBookingPayloadBuilder::TRIP_ORDERS_CREATEBOOKING_COMPARE_STYLES);
    }

    public function test_b30_trip_orders_flight_details_camel_v1_contract_accepts_departure_datetime_and_marketing_airline(): void
    {
        $builder = app(SabreBookingPayloadBuilder::class);
        $offer = [
            'id' => 'o-b30-camel',
            'supplier_connection_id' => 1,
            'supplier_offer_id' => 'so-b30',
            'airline_code' => 'EK',
            'validating_carrier' => 'EK',
            'segments' => [[
                'origin' => 'LHE',
                'destination' => 'DXB',
                'departure_at' => '2026-06-01T08:00:00Z',
                'arrival_at' => '2026-06-01T14:00:00Z',
                'carrier' => 'EK',
                'flight_number' => '615',
                'booking_class' => 'K',
                'fare_basis_code' => 'KLITE1',
            ]],
            'fare_breakdown' => [
                'supplier_total' => 100,
                'currency' => 'USD',
                'base_fare' => 80,
                'taxes' => 20,
                'passenger_counts' => ['adults' => 1, 'children' => 0, 'infants' => 0],
            ],
        ];
        $passengerData = [
            'passengers' => [[
                'passenger_type' => 'adult',
                'first_name' => 'Wire',
                'last_name' => 'Test',
                'gender' => 'M',
                'date_of_birth' => '1991-02-02',
                'passport_number' => 'AB1234567',
                'passport_issuing_country' => 'PK',
                'passport_expiry_date' => '2030-05-05',
                'nationality' => 'PK',
                'document_type' => 'passport',
            ]],
            'contact' => ['email' => 'wire-b30@example.com', 'phone' => '+1000000000'],
        ];
        $draft = $builder->buildInternalDraft($offer, $passengerData);
        unset($draft['_valid']);
        $env = $builder->buildTripOrdersCreateBookingEnvelope($draft, [], 'trip_orders_flight_details_camel_v1');
        $sum = $builder->summarizeTripOrdersWirePostBodyForEnvelope($env);
        $this->assertTrue($sum['wire_contract_valid']);
        $this->assertTrue($sum['wire_segment_required_fields_valid']);
        $this->assertSame('flightDetails_datetime_airline', $sum['wire_segment_field_style']);
        $this->assertSame([], $sum['wire_invalid_segment_field_keys']);
        $this->assertTrue($sum['wire_traveler_required_fields_valid']);
        $this->assertFalse($sum['wire_ticketing_enabled']);
    }

    public function test_b30_flight_details_camel_missing_departure_datetime_blocks_segment_validation(): void
    {
        $builder = app(SabreBookingPayloadBuilder::class);
        $offer = [
            'id' => 'o-b30-miss-dep',
            'supplier_connection_id' => 1,
            'supplier_offer_id' => 'x',
            'airline_code' => 'EK',
            'validating_carrier' => 'EK',
            'segments' => [[
                'origin' => 'LHE',
                'destination' => 'DXB',
                'departure_at' => '2026-06-01T08:00:00Z',
                'arrival_at' => '2026-06-01T14:00:00Z',
                'carrier' => 'EK',
                'flight_number' => '615',
                'booking_class' => 'K',
                'fare_basis_code' => 'KLITE1',
            ]],
            'fare_breakdown' => [
                'supplier_total' => 100,
                'currency' => 'USD',
                'base_fare' => 80,
                'taxes' => 20,
                'passenger_counts' => ['adults' => 1, 'children' => 0, 'infants' => 0],
            ],
        ];
        $draft = $builder->buildInternalDraft($offer, [
            'passengers' => [['passenger_type' => 'adult', 'first_name' => 'A', 'last_name' => 'B', 'gender' => 'M', 'date_of_birth' => '1990-01-01']],
            'contact' => ['email' => 'a@example.com', 'phone' => '+1'],
        ]);
        unset($draft['_valid']);
        $env = $builder->buildTripOrdersCreateBookingEnvelope($draft, [], 'trip_orders_flight_details_camel_v1');
        $fd = is_array($env['flightDetails'] ?? null) ? $env['flightDetails'] : [];
        $segs = is_array($fd['segments'] ?? null) ? $fd['segments'] : [];
        $this->assertNotSame([], $segs);
        $segs[0]['departure_datetime'] = '';
        $env['flightDetails']['segments'] = $segs;
        $sum = $builder->summarizeTripOrdersWirePostBodyForEnvelope($env);
        $this->assertFalse($sum['wire_segment_required_fields_valid']);
        $this->assertContains('flightDetails.segments.0.departure_datetime', $sum['wire_invalid_segment_field_keys']);
        $this->assertFalse($sum['wire_contract_valid']);
    }

    public function test_b30_flight_details_camel_missing_marketing_airline_blocks_segment_validation(): void
    {
        $builder = app(SabreBookingPayloadBuilder::class);
        $offer = [
            'id' => 'o-b30-miss-mkt',
            'supplier_connection_id' => 1,
            'supplier_offer_id' => 'y',
            'airline_code' => 'EK',
            'validating_carrier' => 'EK',
            'segments' => [[
                'origin' => 'LHE',
                'destination' => 'DXB',
                'departure_at' => '2026-06-01T08:00:00Z',
                'arrival_at' => '2026-06-01T14:00:00Z',
                'carrier' => 'EK',
                'flight_number' => '615',
                'booking_class' => 'K',
                'fare_basis_code' => 'KLITE1',
            ]],
            'fare_breakdown' => [
                'supplier_total' => 100,
                'currency' => 'USD',
                'base_fare' => 80,
                'taxes' => 20,
                'passenger_counts' => ['adults' => 1, 'children' => 0, 'infants' => 0],
            ],
        ];
        $draft = $builder->buildInternalDraft($offer, [
            'passengers' => [['passenger_type' => 'adult', 'first_name' => 'A', 'last_name' => 'B', 'gender' => 'M', 'date_of_birth' => '1990-01-01']],
            'contact' => ['email' => 'a@example.com', 'phone' => '+1'],
        ]);
        unset($draft['_valid']);
        $env = $builder->buildTripOrdersCreateBookingEnvelope($draft, [], 'trip_orders_flight_details_camel_v1');
        $fd = is_array($env['flightDetails'] ?? null) ? $env['flightDetails'] : [];
        $segs = is_array($fd['segments'] ?? null) ? $fd['segments'] : [];
        $segs[0]['marketing_airline'] = '';
        $env['flightDetails']['segments'] = $segs;
        $sum = $builder->summarizeTripOrdersWirePostBodyForEnvelope($env);
        $this->assertFalse($sum['wire_segment_required_fields_valid']);
        $this->assertContains('flightDetails.segments.0.marketing_airline', $sum['wire_invalid_segment_field_keys']);
        $this->assertFalse($sum['wire_contract_valid']);
    }

    public function test_b30_trip_orders_flight_details_full_camel_v1_emits_sabre_like_segment_keys(): void
    {
        $builder = app(SabreBookingPayloadBuilder::class);
        $offer = [
            'id' => 'o-b30-full',
            'supplier_connection_id' => 1,
            'supplier_offer_id' => 'so-full',
            'airline_code' => 'EK',
            'validating_carrier' => 'EK',
            'segments' => [[
                'origin' => 'LHE',
                'destination' => 'DXB',
                'departure_at' => '2026-06-01T08:00:00Z',
                'arrival_at' => '2026-06-01T14:00:00Z',
                'carrier' => 'EK',
                'flight_number' => '615',
                'booking_class' => 'K',
                'fare_basis_code' => 'KLITE1',
            ]],
            'fare_breakdown' => [
                'supplier_total' => 100,
                'currency' => 'USD',
                'base_fare' => 80,
                'taxes' => 20,
                'passenger_counts' => ['adults' => 1, 'children' => 0, 'infants' => 0],
            ],
        ];
        $draft = $builder->buildInternalDraft($offer, [
            'passengers' => [[
                'passenger_type' => 'adult', 'first_name' => 'A', 'last_name' => 'B', 'gender' => 'M', 'date_of_birth' => '1990-01-01',
                'passport_number' => 'CD7654321', 'passport_issuing_country' => 'PK', 'passport_expiry_date' => '2031-01-01', 'nationality' => 'PK', 'document_type' => 'passport',
            ]],
            'contact' => ['email' => 'a@example.com', 'phone' => '+1'],
        ]);
        unset($draft['_valid']);
        $env = $builder->buildTripOrdersCreateBookingEnvelope($draft, [], 'trip_orders_flight_details_full_camel_v1');
        $wire = $builder->tripOrdersWirePostBodyFromEnvelope($env);
        $seg0 = is_array($wire['flightDetails']['segments'][0] ?? null) ? $wire['flightDetails']['segments'][0] : [];
        $this->assertArrayHasKey('departureDateTime', $seg0);
        $this->assertArrayHasKey('marketingAirline', $seg0);
        $this->assertArrayHasKey('classOfService', $seg0);
        $sum = $builder->summarizeTripOrdersWirePostBodyForEnvelope($env);
        $this->assertTrue($sum['wire_contract_valid']);
        $this->assertSame('flightDetails_full_camel', $sum['wire_segment_field_style']);
    }

    public function test_b31_trip_orders_flight_details_sabre_v1_wire_uses_passenger_code_not_passenger_type_code(): void
    {
        config([
            'suppliers.sabre.agency_phone' => '+19997770001',
            'suppliers.sabre.agency_phone_country_code' => 'US',
            'suppliers.sabre.agency_phone_type' => 'AGENCY',
        ]);
        $builder = app(SabreBookingPayloadBuilder::class);
        $offer = [
            'id' => 'o-b31-sabre',
            'supplier_connection_id' => 1,
            'supplier_offer_id' => 'so-b31',
            'airline_code' => 'EK',
            'validating_carrier' => 'EK',
            'segments' => [[
                'origin' => 'LHE',
                'destination' => 'DXB',
                'departure_at' => '2026-06-01T08:00:00Z',
                'arrival_at' => '2026-06-01T14:00:00Z',
                'carrier' => 'EK',
                'flight_number' => '615',
                'booking_class' => 'K',
                'fare_basis_code' => 'KLITE1',
            ]],
            'fare_breakdown' => [
                'supplier_total' => 100,
                'currency' => 'USD',
                'base_fare' => 80,
                'taxes' => 20,
                'passenger_counts' => ['adults' => 1, 'children' => 0, 'infants' => 0],
            ],
        ];
        $passengerData = [
            'passengers' => [[
                'passenger_type' => 'adult',
                'first_name' => 'SabreCode',
                'last_name' => 'Traveler',
                'gender' => 'M',
                'date_of_birth' => '1991-02-02',
                'passport_number' => 'AB1234567',
                'passport_issuing_country' => 'PK',
                'passport_expiry_date' => '2030-05-05',
                'nationality' => 'PK',
                'document_type' => 'passport',
            ]],
            'contact' => ['email' => 'sabre-b31@example.com', 'phone' => '+1000000000'],
        ];
        $draft = $builder->buildInternalDraft($offer, $passengerData);
        unset($draft['_valid']);
        $env = $builder->buildTripOrdersCreateBookingEnvelope($draft, [], 'trip_orders_flight_details_sabre_v1');
        $wire = $builder->tripOrdersWirePostBodyFromEnvelope($env);
        $this->assertArrayHasKey('flightDetails', $wire);
        $this->assertArrayHasKey('contactInfo', $wire);
        $this->assertArrayNotHasKey('contact', $wire);
        $this->assertArrayHasKey('agencyContactInfo', $wire);
        $aci = is_array($wire['agencyContactInfo'] ?? null) ? $wire['agencyContactInfo'] : [];
        $this->assertSame('US', $aci['phoneCountryCode'] ?? null);
        $this->assertSame('AGENCY', $aci['phoneType'] ?? null);
        $this->assertNotSame('', trim((string) ($aci['phone'] ?? '')));
        $ci = is_array($wire['contactInfo'] ?? null) ? $wire['contactInfo'] : [];
        $this->assertNotSame('', trim((string) ($ci['email'] ?? '')));
        $this->assertNotSame('', trim((string) ($ci['phone'] ?? '')));
        $t0 = is_array($wire['travelers'][0] ?? null) ? $wire['travelers'][0] : [];
        $this->assertArrayHasKey('passengerCode', $t0);
        $this->assertSame('ADT', $t0['passengerCode']);
        $this->assertArrayNotHasKey('passengerTypeCode', $t0);
        $this->assertSame('MALE', $t0['gender']);
        $this->assertArrayHasKey('givenName', $t0);
        $this->assertArrayHasKey('birthDate', $t0);
        $pp = is_array($t0['passport'] ?? null) ? $t0['passport'] : [];
        $this->assertArrayHasKey('documentType', $pp);
        $this->assertArrayHasKey('issuingCountry', $pp);
        $this->assertArrayHasKey('expiryDate', $pp);
        $tick = is_array($wire['ticketing'] ?? null) ? $wire['ticketing'] : [];
        $this->assertArrayHasKey('enabled', $tick);
        $this->assertFalse((bool) ($tick['enabled'] ?? true));
        $sum = $builder->summarizeTripOrdersWirePostBodyForEnvelope($env);
        $this->assertSame('sabreTripOrders', $sum['wire_traveler_field_style']);
        $this->assertTrue($sum['wire_has_passengerCode']);
        $this->assertFalse($sum['wire_has_passengerTypeCode']);
        $this->assertTrue($sum['traveler_1_has_passengerCode']);
        $this->assertFalse($sum['traveler_1_has_passengerTypeCode']);
        $this->assertTrue($sum['wire_contract_valid']);
        $this->assertTrue($sum['wire_has_contactInfo']);
        $this->assertFalse($sum['wire_has_contact']);
        $this->assertSame('contactInfo', $sum['wire_contact_field_style']);
        $this->assertTrue($sum['wire_has_contact_email']);
        $this->assertTrue($sum['wire_has_contact_phone']);
        $this->assertTrue($sum['wire_has_customer_contact_phone']);
        $this->assertTrue($sum['wire_has_agency_phone']);
        $this->assertSame('agencyContactInfo.phone', $sum['wire_agency_phone_field_style']);
        $this->assertContains('agencyContactInfo.phone', $sum['wire_agency_phone_paths']);
        $this->assertTrue($sum['wire_agency_phone_redacted']);
        $this->assertTrue($sum['wire_agency_phone_ok']);
        $red = $builder->redactTripOrdersWireJsonForPreview($wire);
        $redJson = json_encode($red);
        $this->assertStringNotContainsString('SabreCode', (string) $redJson);
        $this->assertStringNotContainsString('AB1234567', (string) $redJson);
        $this->assertStringNotContainsString('sabre-b31@', (string) $redJson);
        $this->assertStringNotContainsString('+1000000000', (string) $redJson);
        $this->assertStringNotContainsString('+19997770001', (string) $redJson);
    }

    public function test_b31_missing_passenger_code_invalidates_wire_contract_for_sabre_style(): void
    {
        config([
            'suppliers.sabre.agency_phone' => '+19997770001',
            'suppliers.sabre.agency_phone_country_code' => 'PK',
            'suppliers.sabre.agency_phone_type' => 'AGENCY',
        ]);
        $builder = app(SabreBookingPayloadBuilder::class);
        $offer = [
            'id' => 'o-b31-miss-pc',
            'supplier_connection_id' => 1,
            'supplier_offer_id' => 'x',
            'airline_code' => 'EK',
            'validating_carrier' => 'EK',
            'segments' => [[
                'origin' => 'LHE',
                'destination' => 'DXB',
                'departure_at' => '2026-06-01T08:00:00Z',
                'arrival_at' => '2026-06-01T14:00:00Z',
                'carrier' => 'EK',
                'flight_number' => '615',
                'booking_class' => 'K',
                'fare_basis_code' => 'KLITE1',
            ]],
            'fare_breakdown' => [
                'supplier_total' => 100,
                'currency' => 'USD',
                'base_fare' => 80,
                'taxes' => 20,
                'passenger_counts' => ['adults' => 1, 'children' => 0, 'infants' => 0],
            ],
        ];
        $passengerData = [
            'passengers' => [[
                'passenger_type' => 'adult',
                'first_name' => 'A',
                'last_name' => 'B',
                'gender' => 'M',
                'date_of_birth' => '1990-01-01',
                'passport_number' => 'ZZ9999999',
                'passport_issuing_country' => 'PK',
                'passport_expiry_date' => '2030-01-01',
                'nationality' => 'PK',
                'document_type' => 'passport',
            ]],
            'contact' => ['email' => 'a@example.com', 'phone' => '+1'],
        ];
        $draft = $builder->buildInternalDraft($offer, $passengerData);
        unset($draft['_valid']);
        $env = $builder->buildTripOrdersCreateBookingEnvelope($draft, [], 'trip_orders_flight_details_sabre_v1');
        $t0 = &$env['travelers'][0];
        unset($t0['passengerCode']);
        $sum = $builder->summarizeTripOrdersWirePostBodyForEnvelope($env);
        $this->assertFalse($sum['wire_contract_valid']);
        $this->assertContains('travelers.0.passengerCode', $sum['wire_invalid_contract_keys']);
    }

    public function test_b32_missing_contact_info_invalidates_wire_contract_for_sabre_style(): void
    {
        config([
            'suppliers.sabre.agency_phone' => '+19997770001',
            'suppliers.sabre.agency_phone_country_code' => 'PK',
            'suppliers.sabre.agency_phone_type' => 'AGENCY',
        ]);
        $builder = app(SabreBookingPayloadBuilder::class);
        $offer = [
            'id' => 'o-b32-miss-ci',
            'supplier_connection_id' => 1,
            'supplier_offer_id' => 'x',
            'airline_code' => 'EK',
            'validating_carrier' => 'EK',
            'segments' => [[
                'origin' => 'LHE',
                'destination' => 'DXB',
                'departure_at' => '2026-06-01T08:00:00Z',
                'arrival_at' => '2026-06-01T14:00:00Z',
                'carrier' => 'EK',
                'flight_number' => '615',
                'booking_class' => 'K',
                'fare_basis_code' => 'KLITE1',
            ]],
            'fare_breakdown' => [
                'supplier_total' => 100,
                'currency' => 'USD',
                'base_fare' => 80,
                'taxes' => 20,
                'passenger_counts' => ['adults' => 1, 'children' => 0, 'infants' => 0],
            ],
        ];
        $passengerData = [
            'passengers' => [[
                'passenger_type' => 'adult',
                'first_name' => 'A',
                'last_name' => 'B',
                'gender' => 'M',
                'date_of_birth' => '1990-01-01',
                'passport_number' => 'ZZ9999999',
                'passport_issuing_country' => 'PK',
                'passport_expiry_date' => '2030-01-01',
                'nationality' => 'PK',
                'document_type' => 'passport',
            ]],
            'contact' => ['email' => 'a@example.com', 'phone' => '+1'],
        ];
        $draft = $builder->buildInternalDraft($offer, $passengerData);
        unset($draft['_valid']);
        $env = $builder->buildTripOrdersCreateBookingEnvelope($draft, [], 'trip_orders_flight_details_sabre_v1');
        unset($env['contactInfo']);
        $sum = $builder->summarizeTripOrdersWirePostBodyForEnvelope($env);
        $this->assertFalse($sum['wire_contract_valid']);
        $invalid = $sum['wire_invalid_contract_keys'] ?? [];
        $this->assertTrue(
            in_array('contactInfo', $invalid, true) || in_array('contactInfo.email_or_phone', $invalid, true),
            'expected contactInfo contract violation'
        );
    }

    public function test_b31_artisan_wire_preview_shows_passenger_code_diagnostics_for_sabre_style(): void
    {
        $booking = $this->seedWireTestBooking();
        config([
            'suppliers.sabre.booking_path' => '/v1/trip/orders/createBooking',
            'suppliers.sabre.booking_schema' => 'trip_orders_create_booking',
            'suppliers.sabre.agency_phone' => '+19997770001',
            'suppliers.sabre.agency_phone_country_code' => 'PK',
            'suppliers.sabre.agency_phone_type' => 'AGENCY',
        ]);
        Artisan::call('sabre:inspect-booking-payload', [
            '--booking' => (string) $booking->id,
            '--wire-preview-json' => true,
            '--style' => 'trip_orders_flight_details_sabre_v1',
        ]);
        $out = Artisan::output();
        $this->assertStringContainsString('traveler_1_has_passengerCode=true', $out);
        $this->assertStringContainsString('traveler_1_has_passengerTypeCode=false', $out);
        $this->assertStringContainsString('wire_has_passengerCode=true', $out);
        $this->assertStringContainsString('wire_has_passengerTypeCode=false', $out);
        $this->assertStringContainsString('wire_traveler_field_style=sabreTripOrders', $out);
        $this->assertStringContainsString('wire_has_contactInfo=true', $out);
        $this->assertStringContainsString('wire_has_contact=false', $out);
        $this->assertStringContainsString('wire_contact_field_style=contactInfo', $out);
        $this->assertStringContainsString('wire_has_contact_email=true', $out);
        $this->assertStringContainsString('wire_has_contact_phone=true', $out);
        $this->assertStringContainsString('wire_has_agency_phone=true', $out);
        $this->assertStringContainsString('wire_agency_phone_field_style=agencyContactInfo.phone', $out);
        $this->assertStringContainsString('wire_agency_phone_paths=', $out);
        $this->assertStringContainsString('agencyContactInfo.phone', $out);
        $this->assertStringContainsString('wire_agency_phone_redacted=true', $out);
        $this->assertStringContainsString('wire_has_customer_contact_phone=true', $out);
        foreach (['wire-b23-test@example.com', 'WireTest', 'XT8888888', 'Bearer ', '+19997770001'] as $needle) {
            $this->assertStringNotContainsString($needle, $out, 'wire preview meta must not leak: '.$needle);
        }
    }

    public function test_b31_compare_styles_list_includes_sabre_v1(): void
    {
        $this->assertContains('trip_orders_flight_details_sabre_v1', SabreBookingPayloadBuilder::TRIP_ORDERS_CREATEBOOKING_COMPARE_STYLES);
        $this->assertContains('trip_orders_flight_details_sabre_agency_v1', SabreBookingPayloadBuilder::TRIP_ORDERS_CREATEBOOKING_COMPARE_STYLES);
        $this->assertContains('trip_orders_flight_details_sabre_agencyInfo_v1', SabreBookingPayloadBuilder::TRIP_ORDERS_CREATEBOOKING_COMPARE_STYLES);
        $this->assertContains('trip_orders_flight_details_sabre_agencyPhoneNumber_v1', SabreBookingPayloadBuilder::TRIP_ORDERS_CREATEBOOKING_COMPARE_STYLES);
        $this->assertContains('trip_orders_flight_details_sabre_agencyPhonesArray_v1', SabreBookingPayloadBuilder::TRIP_ORDERS_CREATEBOOKING_COMPARE_STYLES);
        $this->assertContains('trip_orders_flight_details_sabre_rootAgencyPhone_v1', SabreBookingPayloadBuilder::TRIP_ORDERS_CREATEBOOKING_COMPARE_STYLES);
        $this->assertContains('trip_orders_flight_details_sabre_phoneNumbers_v1', SabreBookingPayloadBuilder::TRIP_ORDERS_CREATEBOOKING_COMPARE_STYLES);
        $this->assertContains('trip_orders_flight_details_sabre_rootPhones_v1', SabreBookingPayloadBuilder::TRIP_ORDERS_CREATEBOOKING_COMPARE_STYLES);
        $this->assertContains('trip_orders_flight_details_sabre_rootPhoneNumbers_v1', SabreBookingPayloadBuilder::TRIP_ORDERS_CREATEBOOKING_COMPARE_STYLES);
        $this->assertContains('trip_orders_flight_details_sabre_contactInfoPhones_v1', SabreBookingPayloadBuilder::TRIP_ORDERS_CREATEBOOKING_COMPARE_STYLES);
        $this->assertContains('trip_orders_flight_details_sabre_agencyPhoneUseType_v1', SabreBookingPayloadBuilder::TRIP_ORDERS_CREATEBOOKING_COMPARE_STYLES);
        $this->assertContains('trip_orders_flight_details_sabre_phone_use_business_v1', SabreBookingPayloadBuilder::TRIP_ORDERS_CREATEBOOKING_COMPARE_STYLES);
        $this->assertContains('trip_orders_flight_details_sabre_phone_use_agency_v1', SabreBookingPayloadBuilder::TRIP_ORDERS_CREATEBOOKING_COMPARE_STYLES);
        $this->assertContains('trip_orders_flight_details_sabre_phoneLine_v1', SabreBookingPayloadBuilder::TRIP_ORDERS_CREATEBOOKING_COMPARE_STYLES);
        $this->assertContains('trip_orders_flight_details_sabre_phoneLines_v1', SabreBookingPayloadBuilder::TRIP_ORDERS_CREATEBOOKING_COMPARE_STYLES);
        $this->assertContains('trip_orders_flight_details_sabre_contactNumbers_v1', SabreBookingPayloadBuilder::TRIP_ORDERS_CREATEBOOKING_COMPARE_STYLES);
        $this->assertContains('trip_orders_flight_details_sabre_pnrContact_v1', SabreBookingPayloadBuilder::TRIP_ORDERS_CREATEBOOKING_COMPARE_STYLES);
        $this->assertContains('trip_orders_flight_details_sabre_reservationContact_v1', SabreBookingPayloadBuilder::TRIP_ORDERS_CREATEBOOKING_COMPARE_STYLES);
        $this->assertContains('trip_orders_flight_details_sabre_contactInfo_phoneLine_v1', SabreBookingPayloadBuilder::TRIP_ORDERS_CREATEBOOKING_COMPARE_STYLES);
        $this->assertContains('trip_orders_flight_details_sabre_travelers_phone_v1', SabreBookingPayloadBuilder::TRIP_ORDERS_CREATEBOOKING_COMPARE_STYLES);
    }

    public function test_compare_createbooking_styles_with_sabre_style_emits_single_block(): void
    {
        $booking = $this->seedWireTestBooking();
        config([
            'suppliers.sabre.booking_path' => '/v1/trip/orders/createBooking',
            'suppliers.sabre.booking_schema' => 'trip_orders_create_booking',
            'suppliers.sabre.agency_phone' => '+19997770001',
            'suppliers.sabre.agency_phone_country_code' => 'PK',
            'suppliers.sabre.agency_phone_type' => 'AGENCY',
        ]);
        Artisan::call('sabre:compare-createbooking-styles', [
            '--booking' => (string) $booking->id,
            '--style' => 'trip_orders_flight_details_sabre_v1',
        ]);
        $out = Artisan::output();
        $this->assertStringContainsString('style=trip_orders_flight_details_sabre_v1', $out);
        $this->assertSame(1, substr_count($out, '---'));
    }

    public function test_b33_missing_agency_phone_invalidates_wire_for_sabre_traditional_styles(): void
    {
        config(['suppliers.sabre.agency_phone' => '']);
        $builder = app(SabreBookingPayloadBuilder::class);
        $offer = [
            'id' => 'o-b33-ag',
            'supplier_connection_id' => 1,
            'supplier_offer_id' => 'so-b33',
            'airline_code' => 'EK',
            'validating_carrier' => 'EK',
            'segments' => [[
                'origin' => 'LHE',
                'destination' => 'DXB',
                'departure_at' => '2026-06-01T08:00:00Z',
                'arrival_at' => '2026-06-01T14:00:00Z',
                'carrier' => 'EK',
                'flight_number' => '615',
                'booking_class' => 'K',
                'fare_basis_code' => 'KLITE1',
            ]],
            'fare_breakdown' => [
                'supplier_total' => 100,
                'currency' => 'USD',
                'base_fare' => 80,
                'taxes' => 20,
                'passenger_counts' => ['adults' => 1, 'children' => 0, 'infants' => 0],
            ],
        ];
        $passengerData = [
            'passengers' => [[
                'passenger_type' => 'adult',
                'first_name' => 'A',
                'last_name' => 'B',
                'gender' => 'M',
                'date_of_birth' => '1991-02-02',
                'passport_number' => 'AB1234567',
                'passport_issuing_country' => 'PK',
                'passport_expiry_date' => '2030-05-05',
                'nationality' => 'PK',
                'document_type' => 'passport',
            ]],
            'contact' => ['email' => 'a@example.com', 'phone' => '+1000000000'],
        ];
        $draft = $builder->buildInternalDraft($offer, $passengerData);
        unset($draft['_valid']);
        $env = $builder->buildTripOrdersCreateBookingEnvelope($draft, [], 'trip_orders_flight_details_sabre_v1');
        $sum = $builder->summarizeTripOrdersWirePostBodyForEnvelope($env);
        $this->assertFalse($sum['wire_has_agency_phone']);
        $this->assertSame('none', $sum['wire_agency_phone_field_style']);
        $this->assertFalse($sum['wire_agency_phone_redacted']);
        $this->assertFalse($sum['wire_agency_phone_ok']);
        $this->assertFalse($sum['wire_contract_valid']);
        $diag = $builder->summarizeEnvelopeForDiagnostics($env);
        $this->assertFalse($diag['validation_ok']);
    }

    public function test_b33_trip_orders_flight_details_sabre_agency_v1_matches_sabre_v1_wire_shape(): void
    {
        config([
            'suppliers.sabre.agency_phone' => '+19997770002',
            'suppliers.sabre.agency_phone_country_code' => 'PK',
            'suppliers.sabre.agency_phone_type' => 'AGENCY',
        ]);
        $builder = app(SabreBookingPayloadBuilder::class);
        $offer = [
            'id' => 'o-b33-ag2',
            'supplier_connection_id' => 1,
            'supplier_offer_id' => 'so-b33-2',
            'airline_code' => 'EK',
            'validating_carrier' => 'EK',
            'segments' => [[
                'origin' => 'LHE',
                'destination' => 'DXB',
                'departure_at' => '2026-06-01T08:00:00Z',
                'arrival_at' => '2026-06-01T14:00:00Z',
                'carrier' => 'EK',
                'flight_number' => '615',
                'booking_class' => 'K',
                'fare_basis_code' => 'KLITE1',
            ]],
            'fare_breakdown' => [
                'supplier_total' => 100,
                'currency' => 'USD',
                'base_fare' => 80,
                'taxes' => 20,
                'passenger_counts' => ['adults' => 1, 'children' => 0, 'infants' => 0],
            ],
        ];
        $passengerData = [
            'passengers' => [[
                'passenger_type' => 'adult',
                'first_name' => 'A',
                'last_name' => 'B',
                'gender' => 'M',
                'date_of_birth' => '1991-02-02',
                'passport_number' => 'AB1234567',
                'passport_issuing_country' => 'PK',
                'passport_expiry_date' => '2030-05-05',
                'nationality' => 'PK',
                'document_type' => 'passport',
            ]],
            'contact' => ['email' => 'a@example.com', 'phone' => '+1000000000'],
        ];
        $draft = $builder->buildInternalDraft($offer, $passengerData);
        unset($draft['_valid']);
        $env = $builder->buildTripOrdersCreateBookingEnvelope($draft, [], 'trip_orders_flight_details_sabre_agency_v1');
        $wire = $builder->tripOrdersWirePostBodyFromEnvelope($env);
        $this->assertArrayHasKey('agencyContactInfo', $wire);
        $this->assertArrayHasKey('contactInfo', $wire);
        $this->assertTrue($builder->summarizeTripOrdersWirePostBodyForEnvelope($env)['wire_agency_phone_ok']);
    }

    public function test_compare_createbooking_styles_sabre_agency_v1_emits_single_block(): void
    {
        $booking = $this->seedWireTestBooking();
        config([
            'suppliers.sabre.booking_path' => '/v1/trip/orders/createBooking',
            'suppliers.sabre.booking_schema' => 'trip_orders_create_booking',
            'suppliers.sabre.agency_phone' => '+19997770001',
            'suppliers.sabre.agency_phone_country_code' => 'PK',
            'suppliers.sabre.agency_phone_type' => 'AGENCY',
        ]);
        Artisan::call('sabre:compare-createbooking-styles', [
            '--booking' => (string) $booking->id,
            '--style' => 'trip_orders_flight_details_sabre_agency_v1',
        ]);
        $out = Artisan::output();
        $this->assertStringContainsString('style=trip_orders_flight_details_sabre_agency_v1', $out);
        $this->assertSame(1, substr_count($out, '---'));
    }

    public function test_b34_alternate_agency_phone_styles_shape_and_diagnostics(): void
    {
        config([
            'suppliers.sabre.agency_phone' => '+19997770003',
            'suppliers.sabre.agency_phone_country_code' => 'PK',
            'suppliers.sabre.agency_phone_type' => 'AGENCY',
        ]);
        $builder = app(SabreBookingPayloadBuilder::class);
        $offer = [
            'id' => 'o-b34-ag',
            'supplier_connection_id' => 1,
            'supplier_offer_id' => 'so-b34',
            'airline_code' => 'EK',
            'validating_carrier' => 'EK',
            'segments' => [[
                'origin' => 'LHE',
                'destination' => 'DXB',
                'departure_at' => '2026-06-01T08:00:00Z',
                'arrival_at' => '2026-06-01T14:00:00Z',
                'carrier' => 'EK',
                'flight_number' => '615',
                'booking_class' => 'K',
                'fare_basis_code' => 'KLITE1',
            ]],
            'fare_breakdown' => [
                'supplier_total' => 100,
                'currency' => 'USD',
                'base_fare' => 80,
                'taxes' => 20,
                'passenger_counts' => ['adults' => 1, 'children' => 0, 'infants' => 0],
            ],
        ];
        $passengerData = [
            'passengers' => [[
                'passenger_type' => 'adult',
                'first_name' => 'B34',
                'last_name' => 'Style',
                'gender' => 'M',
                'date_of_birth' => '1991-02-02',
                'passport_number' => 'AB1234567',
                'passport_issuing_country' => 'PK',
                'passport_expiry_date' => '2030-05-05',
                'nationality' => 'PK',
                'document_type' => 'passport',
            ]],
            'contact' => ['email' => 'b34-style@example.com', 'phone' => '+1000000000'],
        ];
        $draft = $builder->buildInternalDraft($offer, $passengerData);
        unset($draft['_valid']);

        $styles = [
            'trip_orders_flight_details_sabre_agencyInfo_v1' => function (array $wire): void {
                $this->assertArrayHasKey('agencyInfo', $wire);
                $this->assertArrayNotHasKey('agencyContactInfo', $wire);
            },
            'trip_orders_flight_details_sabre_agencyPhoneNumber_v1' => function (array $wire): void {
                $aci = is_array($wire['agencyContactInfo'] ?? null) ? $wire['agencyContactInfo'] : [];
                $this->assertArrayHasKey('phoneNumber', $aci);
                $this->assertArrayNotHasKey('phone', $aci);
            },
            'trip_orders_flight_details_sabre_agencyPhonesArray_v1' => function (array $wire): void {
                $aci = is_array($wire['agencyContactInfo'] ?? null) ? $wire['agencyContactInfo'] : [];
                $phones = is_array($aci['phones'] ?? null) ? $aci['phones'] : [];
                $this->assertArrayHasKey(0, $phones);
                $this->assertArrayHasKey('number', is_array($phones[0]) ? $phones[0] : []);
            },
            'trip_orders_flight_details_sabre_rootAgencyPhone_v1' => function (array $wire): void {
                $this->assertArrayHasKey('agencyPhone', $wire);
                $this->assertArrayHasKey('agencyPhoneCountryCode', $wire);
            },
            'trip_orders_flight_details_sabre_phoneNumbers_v1' => function (array $wire): void {
                $pn = is_array($wire['phoneNumbers'] ?? null) ? $wire['phoneNumbers'] : [];
                $this->assertArrayHasKey(0, $pn);
                $row = is_array($pn[0]) ? $pn[0] : [];
                $this->assertArrayHasKey('number', $row);
                $this->assertSame('AGENCY', $row['type'] ?? null);
            },
        ];

        foreach ($styles as $style => $shapeAssert) {
            $expectedPaths = $builder->expectedSabreAgencyPhoneDotPathsForStyle($style);
            $this->assertNotSame([], $expectedPaths, $style);
            $env = $builder->buildTripOrdersCreateBookingEnvelope($draft, [], $style);
            $wire = $builder->tripOrdersWirePostBodyFromEnvelope($env);
            $this->assertArrayHasKey('flightDetails', $wire);
            $this->assertArrayHasKey('contactInfo', $wire);
            $this->assertArrayNotHasKey('contact', $wire);
            $t0 = is_array($wire['travelers'][0] ?? null) ? $wire['travelers'][0] : [];
            $this->assertArrayHasKey('passengerCode', $t0);
            $tick = is_array($wire['ticketing'] ?? null) ? $wire['ticketing'] : [];
            $this->assertFalse((bool) ($tick['enabled'] ?? true));
            $shapeAssert($wire);
            $sum = $builder->summarizeTripOrdersWirePostBodyForEnvelope($env);
            $this->assertTrue($sum['wire_has_agency_phone'], $style);
            $this->assertSame($expectedPaths[0], $sum['wire_agency_phone_field_style'], $style);
            foreach ($expectedPaths as $p) {
                $this->assertContains($p, $sum['wire_agency_phone_paths'], $style.' '.$p);
            }
            $this->assertTrue($sum['wire_agency_phone_redacted'], $style);
            $this->assertTrue($sum['wire_agency_phone_ok'], $style);
            $red = $builder->redactTripOrdersWireJsonForPreview($wire);
            $redJson = json_encode($red);
            $this->assertStringNotContainsString('B34', (string) $redJson, $style);
            $this->assertStringNotContainsString('AB1234567', (string) $redJson, $style);
            $this->assertStringNotContainsString('b34-style@', (string) $redJson, $style);
            $this->assertStringNotContainsString('+19997770003', (string) $redJson, $style);
        }
    }

    public function test_b34_missing_agency_phone_invalidates_expected_path_for_alternate_style(): void
    {
        config(['suppliers.sabre.agency_phone' => '']);
        $builder = app(SabreBookingPayloadBuilder::class);
        $offer = [
            'id' => 'o-b34-miss',
            'supplier_connection_id' => 1,
            'supplier_offer_id' => 'x',
            'airline_code' => 'EK',
            'validating_carrier' => 'EK',
            'segments' => [[
                'origin' => 'LHE',
                'destination' => 'DXB',
                'departure_at' => '2026-06-01T08:00:00Z',
                'arrival_at' => '2026-06-01T14:00:00Z',
                'carrier' => 'EK',
                'flight_number' => '615',
                'booking_class' => 'K',
                'fare_basis_code' => 'KLITE1',
            ]],
            'fare_breakdown' => [
                'supplier_total' => 100,
                'currency' => 'USD',
                'base_fare' => 80,
                'taxes' => 20,
                'passenger_counts' => ['adults' => 1, 'children' => 0, 'infants' => 0],
            ],
        ];
        $passengerData = [
            'passengers' => [[
                'passenger_type' => 'adult',
                'first_name' => 'A',
                'last_name' => 'B',
                'gender' => 'M',
                'date_of_birth' => '1991-02-02',
                'passport_number' => 'AB1234567',
                'passport_issuing_country' => 'PK',
                'passport_expiry_date' => '2030-05-05',
                'nationality' => 'PK',
                'document_type' => 'passport',
            ]],
            'contact' => ['email' => 'a@example.com', 'phone' => '+1000000000'],
        ];
        $draft = $builder->buildInternalDraft($offer, $passengerData);
        unset($draft['_valid']);
        $style = 'trip_orders_flight_details_sabre_agencyPhoneNumber_v1';
        $env = $builder->buildTripOrdersCreateBookingEnvelope($draft, [], $style);
        $sum = $builder->summarizeTripOrdersWirePostBodyForEnvelope($env);
        $this->assertFalse($sum['wire_has_agency_phone']);
        $this->assertSame('none', $sum['wire_agency_phone_field_style']);
        $this->assertFalse($sum['wire_agency_phone_ok']);
        $this->assertContains('agencyContactInfo.phoneNumber', $sum['wire_invalid_contract_keys']);
    }

    public function test_compare_createbooking_styles_agency_info_v1_emits_single_block(): void
    {
        $booking = $this->seedWireTestBooking();
        config([
            'suppliers.sabre.booking_path' => '/v1/trip/orders/createBooking',
            'suppliers.sabre.booking_schema' => 'trip_orders_create_booking',
            'suppliers.sabre.agency_phone' => '+19997770001',
            'suppliers.sabre.agency_phone_country_code' => 'PK',
            'suppliers.sabre.agency_phone_type' => 'AGENCY',
        ]);
        Artisan::call('sabre:compare-createbooking-styles', [
            '--booking' => (string) $booking->id,
            '--style' => 'trip_orders_flight_details_sabre_agencyInfo_v1',
        ]);
        $out = Artisan::output();
        $this->assertStringContainsString('style=trip_orders_flight_details_sabre_agencyInfo_v1', $out);
        $this->assertSame(1, substr_count($out, '---'));
        $this->assertStringContainsString('wire_agency_phone_paths=', $out);
        foreach (['wire-b23-test@example.com', 'WireTest', 'XT8888888', 'Bearer ', '+19997770001'] as $needle) {
            $this->assertStringNotContainsString($needle, $out, 'compare output must not leak: '.$needle);
        }
    }

    public function test_b35_wire_preview_phone_use_type_and_paths_for_pnr_phone_styles(): void
    {
        $booking = $this->seedWireTestBooking();
        config([
            'suppliers.sabre.booking_path' => '/v1/trip/orders/createBooking',
            'suppliers.sabre.booking_schema' => 'trip_orders_create_booking',
            'suppliers.sabre.agency_phone' => '+19997770004',
            'suppliers.sabre.agency_phone_country_code' => 'PK',
            'suppliers.sabre.agency_phone_type' => 'AGENCY',
        ]);
        foreach ([
            'trip_orders_flight_details_sabre_rootPhones_v1' => ['phones.0.phoneNumber'],
            'trip_orders_flight_details_sabre_rootPhoneNumbers_v1' => ['phoneNumbers.0.phoneNumber'],
            'trip_orders_flight_details_sabre_contactInfoPhones_v1' => ['contactInfo.phones.0.phoneNumber'],
            'trip_orders_flight_details_sabre_agencyPhoneUseType_v1' => ['agencyContactInfo.phones.0.phoneNumber'],
        ] as $style => $paths) {
            Artisan::call('sabre:inspect-booking-payload', [
                '--booking' => (string) $booking->id,
                '--wire-preview-json' => true,
                '--style' => $style,
            ]);
            $out = Artisan::output();
            $this->assertStringContainsString('wire_phone_use_type_values_sanitized=["A"]', $out, $style);
            $this->assertStringContainsString('wire_agency_phone_field_style='.$paths[0], $out, $style);
            foreach ($paths as $p) {
                $this->assertStringContainsString($p, $out, $style);
            }
            $this->assertStringContainsString('wire_has_agency_phone=true', $out, $style);
            $this->assertStringContainsString('wire_agency_phone_redacted=true', $out, $style);
            $this->assertStringContainsString('wire_has_contactInfo=true', $out, $style);
            $this->assertStringContainsString('wire_has_customer_contact_phone=true', $out, $style);
            $this->assertStringContainsString('traveler_1_has_passengerCode=true', $out, $style);
            $this->assertStringContainsString('wire_ticketing_enabled=false', $out, $style);
            foreach (['wire-b23-test@example.com', 'WireTest', 'XT8888888', '+19997770004', 'Bearer '] as $needle) {
                $this->assertStringNotContainsString($needle, $out, $style.' leak:'.$needle);
            }
        }
        foreach ([
            'trip_orders_flight_details_sabre_phone_use_business_v1' => 'BUSINESS',
            'trip_orders_flight_details_sabre_phone_use_agency_v1' => 'AGENCY',
        ] as $style => $expect) {
            Artisan::call('sabre:inspect-booking-payload', [
                '--booking' => (string) $booking->id,
                '--wire-preview-json' => true,
                '--style' => $style,
            ]);
            $out = Artisan::output();
            $this->assertStringContainsString('wire_phone_use_type_values_sanitized=["'.$expect.'"]', $out, $style);
            $this->assertStringContainsString('wire_has_agency_phone=true', $out, $style);
            $this->assertStringNotContainsString('+19997770004', $out, $style);
        }
    }

    public function test_b35_builder_wire_shapes_and_redacts_agency_phone_digits(): void
    {
        config([
            'suppliers.sabre.agency_phone' => '+19997770005',
            'suppliers.sabre.agency_phone_country_code' => 'PK',
            'suppliers.sabre.agency_phone_type' => 'AGENCY',
        ]);
        $builder = app(SabreBookingPayloadBuilder::class);
        $offer = [
            'id' => 'o-b35',
            'supplier_connection_id' => 1,
            'supplier_offer_id' => 'so-b35',
            'airline_code' => 'EK',
            'validating_carrier' => 'EK',
            'segments' => [[
                'origin' => 'LHE',
                'destination' => 'DXB',
                'departure_at' => '2026-06-01T08:00:00Z',
                'arrival_at' => '2026-06-01T14:00:00Z',
                'carrier' => 'EK',
                'flight_number' => '615',
                'booking_class' => 'K',
                'fare_basis_code' => 'KLITE1',
            ]],
            'fare_breakdown' => [
                'supplier_total' => 100,
                'currency' => 'USD',
                'base_fare' => 80,
                'taxes' => 20,
                'passenger_counts' => ['adults' => 1, 'children' => 0, 'infants' => 0],
            ],
        ];
        $passengerData = [
            'passengers' => [[
                'passenger_type' => 'adult',
                'first_name' => 'B35',
                'last_name' => 'Wire',
                'gender' => 'M',
                'date_of_birth' => '1991-02-02',
                'passport_number' => 'AB1234567',
                'passport_issuing_country' => 'PK',
                'passport_expiry_date' => '2030-05-05',
                'nationality' => 'PK',
                'document_type' => 'passport',
            ]],
            'contact' => ['email' => 'b35-wire@example.com', 'phone' => '+1000000000'],
        ];
        $draft = $builder->buildInternalDraft($offer, $passengerData);
        unset($draft['_valid']);

        $pathChecks = [
            'trip_orders_flight_details_sabre_rootPhones_v1' => 'phones.0.phoneNumber',
            'trip_orders_flight_details_sabre_rootPhoneNumbers_v1' => 'phoneNumbers.0.phoneNumber',
            'trip_orders_flight_details_sabre_contactInfoPhones_v1' => 'contactInfo.phones.0.phoneNumber',
            'trip_orders_flight_details_sabre_agencyPhoneUseType_v1' => 'agencyContactInfo.phones.0.phoneNumber',
            'trip_orders_flight_details_sabre_phone_use_business_v1' => 'phones.0.number',
            'trip_orders_flight_details_sabre_phone_use_agency_v1' => 'phones.0.number',
        ];
        foreach ($pathChecks as $style => $dotPath) {
            $env = $builder->buildTripOrdersCreateBookingEnvelope($draft, [], $style);
            $wire = $builder->tripOrdersWirePostBodyFromEnvelope($env);
            $this->assertNotEmpty(data_get($wire, $dotPath), $style.' '.$dotPath);
            $this->assertArrayHasKey('flightDetails', $wire);
            $t0 = is_array($wire['travelers'][0] ?? null) ? $wire['travelers'][0] : [];
            $this->assertArrayHasKey('passengerCode', $t0, $style);
            $tick = is_array($wire['ticketing'] ?? null) ? $wire['ticketing'] : [];
            $this->assertFalse((bool) ($tick['enabled'] ?? true), $style);
            $red = $builder->redactTripOrdersWireJsonForPreview($wire);
            $rj = json_encode($red);
            $this->assertStringNotContainsString('+19997770005', (string) $rj, $style);
            $this->assertStringNotContainsString('B35', (string) $rj, $style);
            $this->assertStringNotContainsString('AB1234567', (string) $rj, $style);
        }
    }

    public function test_b36_pos_agency_styles_shapes_wire_flags_and_redaction(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $conn = SupplierConnection::query()
            ->where('provider', SupplierProvider::Sabre)
            ->orderBy('id')
            ->first();
        $this->assertNotNull($conn);
        $conn->credentials = ['pcc' => 'PCCFORTEST'];
        $conn->save();

        config([
            'suppliers.sabre.agency_phone' => '+19997770006',
            'suppliers.sabre.agency_phone_country_code' => 'PK',
            'suppliers.sabre.agency_pos_phone_use_type' => 'A',
            'suppliers.sabre.agency_country' => 'PK',
            'suppliers.sabre.agency_name' => 'WireAgencyDisplay',
        ]);
        $builder = app(SabreBookingPayloadBuilder::class);
        $offer = [
            'id' => 'o-b36',
            'supplier_connection_id' => $conn->id,
            'supplier_offer_id' => 'so-b36',
            'airline_code' => 'EK',
            'validating_carrier' => 'EK',
            'segments' => [[
                'origin' => 'LHE',
                'destination' => 'DXB',
                'departure_at' => '2026-06-01T08:00:00Z',
                'arrival_at' => '2026-06-01T14:00:00Z',
                'carrier' => 'EK',
                'flight_number' => '615',
                'booking_class' => 'K',
                'fare_basis_code' => 'KLITE1',
            ]],
            'fare_breakdown' => [
                'supplier_total' => 100,
                'currency' => 'USD',
                'base_fare' => 80,
                'taxes' => 20,
                'passenger_counts' => ['adults' => 1, 'children' => 0, 'infants' => 0],
            ],
        ];
        $passengerData = [
            'passengers' => [[
                'passenger_type' => 'adult',
                'first_name' => 'B36',
                'last_name' => 'Pos',
                'gender' => 'M',
                'date_of_birth' => '1991-02-02',
                'passport_number' => 'AB7654321',
                'passport_issuing_country' => 'PK',
                'passport_expiry_date' => '2030-05-05',
                'nationality' => 'PK',
                'document_type' => 'passport',
            ]],
            'contact' => ['email' => 'b36-pos@example.com', 'phone' => '+1000000000'],
        ];
        $draft = $builder->buildInternalDraft($offer, $passengerData);
        unset($draft['_valid']);

        $envPos = $builder->buildTripOrdersCreateBookingEnvelope($draft, [], 'trip_orders_flight_details_sabre_pos_source_phone_v1');
        $wirePos = $builder->tripOrdersWirePostBodyFromEnvelope($envPos);
        $this->assertArrayHasKey('POS', $wirePos);
        $src0 = is_array($wirePos['POS']['Source'][0] ?? null) ? $wirePos['POS']['Source'][0] : [];
        $this->assertSame('PCCFORTEST', $src0['PseudoCityCode'] ?? null);
        $ap = is_array($src0['AgencyPhone'] ?? null) ? $src0['AgencyPhone'] : [];
        $this->assertSame('+19997770006', $ap['PhoneNumber'] ?? null);
        $sumPos = $builder->summarizeTripOrdersWirePostBodyForEnvelope($envPos);
        $this->assertTrue($sumPos['wire_has_POS']);
        $this->assertTrue($sumPos['wire_pcc_present']);
        $this->assertTrue($sumPos['wire_agency_config_phone_present']);
        $this->assertTrue($sumPos['wire_has_contactInfo']);

        $envPosLower = $builder->buildTripOrdersCreateBookingEnvelope($draft, [], 'trip_orders_flight_details_sabre_pos_phone_v1');
        $wireLower = $builder->tripOrdersWirePostBodyFromEnvelope($envPosLower);
        $this->assertArrayHasKey('pos', $wireLower);
        $sumLower = $builder->summarizeTripOrdersWirePostBodyForEnvelope($envPosLower);
        $this->assertTrue($sumLower['wire_has_pos']);

        $envAg = $builder->buildTripOrdersCreateBookingEnvelope($draft, [], 'trip_orders_flight_details_sabre_agency_root_camel_v1');
        $wAg = $builder->tripOrdersWirePostBodyFromEnvelope($envAg);
        $this->assertSame('+19997770006', data_get($wAg, 'agency.phoneNumber'));
        $this->assertTrue($builder->summarizeTripOrdersWirePostBodyForEnvelope($envAg)['wire_has_agency_block']);

        $envTa = $builder->buildTripOrdersCreateBookingEnvelope($draft, [], 'trip_orders_flight_details_sabre_travelAgency_v1');
        $this->assertSame('+19997770006', data_get($builder->tripOrdersWirePostBodyFromEnvelope($envTa), 'travelAgency.phoneNumber'));
        $this->assertTrue($builder->summarizeTripOrdersWirePostBodyForEnvelope($envTa)['wire_has_travelAgency']);

        $envCi = $builder->buildTripOrdersCreateBookingEnvelope($draft, [], 'trip_orders_flight_details_sabre_customerInfo_phone_v1');
        $wCi = $builder->tripOrdersWirePostBodyFromEnvelope($envCi);
        $this->assertArrayHasKey('contactInfo', $wCi);
        $this->assertSame('+19997770006', data_get($wCi, 'customerInfo.agencyPhone'));
        $this->assertTrue($builder->summarizeTripOrdersWirePostBodyForEnvelope($envCi)['wire_has_customerInfo']);

        $red = $builder->redactTripOrdersWireJsonForPreview($wirePos);
        $rj = json_encode($red);
        $this->assertStringNotContainsString('+19997770006', (string) $rj);
        $this->assertStringNotContainsString('PCCFORTEST', (string) $rj);
        $this->assertStringNotContainsString('B36', (string) $rj);

        $conn->credentials = null;
        $conn->save();
        $envNoPcc = $builder->buildTripOrdersCreateBookingEnvelope($draft, [], 'trip_orders_flight_details_sabre_pos_source_phone_v1');
        $this->assertFalse($builder->summarizeTripOrdersWirePostBodyForEnvelope($envNoPcc)['wire_pcc_present']);
    }

    public function test_b36_missing_agency_phone_blocks_pos_and_agency_metadata_styles(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $conn = SupplierConnection::query()
            ->where('provider', SupplierProvider::Sabre)
            ->orderBy('id')
            ->first();
        $this->assertNotNull($conn);

        config(['suppliers.sabre.agency_phone' => '']);
        $builder = app(SabreBookingPayloadBuilder::class);
        $offer = [
            'id' => 'o-b36-m',
            'supplier_connection_id' => $conn->id,
            'supplier_offer_id' => 'so-b36-m',
            'airline_code' => 'EK',
            'validating_carrier' => 'EK',
            'segments' => [[
                'origin' => 'LHE',
                'destination' => 'DXB',
                'departure_at' => '2026-06-01T08:00:00Z',
                'arrival_at' => '2026-06-01T14:00:00Z',
                'carrier' => 'EK',
                'flight_number' => '615',
                'booking_class' => 'K',
                'fare_basis_code' => 'KLITE1',
            ]],
            'fare_breakdown' => [
                'supplier_total' => 100,
                'currency' => 'USD',
                'base_fare' => 80,
                'taxes' => 20,
                'passenger_counts' => ['adults' => 1, 'children' => 0, 'infants' => 0],
            ],
        ];
        $passengerData = [
            'passengers' => [[
                'passenger_type' => 'adult',
                'first_name' => 'A',
                'last_name' => 'B',
                'gender' => 'M',
                'date_of_birth' => '1991-02-02',
                'passport_number' => 'AB1234567',
                'passport_issuing_country' => 'PK',
                'passport_expiry_date' => '2030-05-05',
                'nationality' => 'PK',
                'document_type' => 'passport',
            ]],
            'contact' => ['email' => 'a@example.com', 'phone' => '+1000000000'],
        ];
        $draft = $builder->buildInternalDraft($offer, $passengerData);
        unset($draft['_valid']);
        foreach ([
            'trip_orders_flight_details_sabre_pos_source_phone_v1',
            'trip_orders_flight_details_sabre_pos_phone_v1',
            'trip_orders_flight_details_sabre_agency_root_camel_v1',
            'trip_orders_flight_details_sabre_travelAgency_v1',
            'trip_orders_flight_details_sabre_customerInfo_phone_v1',
        ] as $style) {
            $env = $builder->buildTripOrdersCreateBookingEnvelope($draft, [], $style);
            $sum = $builder->summarizeTripOrdersWirePostBodyForEnvelope($env);
            $this->assertFalse($sum['wire_has_agency_phone'], $style);
            $this->assertFalse($sum['wire_agency_phone_ok'], $style);
            $wire = $builder->tripOrdersWirePostBodyFromEnvelope($env);
            $this->assertArrayHasKey('contactInfo', $wire, $style);
            $t0 = is_array($wire['travelers'][0] ?? null) ? $wire['travelers'][0] : [];
            $this->assertArrayHasKey('passengerCode', $t0, $style);
            $tick = is_array($wire['ticketing'] ?? null) ? $wire['ticketing'] : [];
            $this->assertFalse((bool) ($tick['enabled'] ?? true), $style);
        }
    }

    public function test_b37_pnr_phone_line_styles_wire_contract_and_redacted_preview(): void
    {
        config([
            'suppliers.sabre.agency_phone' => '+19997776666',
            'suppliers.sabre.agency_phone_country_code' => 'PK',
            'suppliers.sabre.agency_phone_type' => 'AGENCY',
            'suppliers.sabre.agency_phone_location' => 'DXB',
        ]);
        $builder = app(SabreBookingPayloadBuilder::class);
        $offer = [
            'id' => 'o-b37',
            'supplier_connection_id' => 1,
            'supplier_offer_id' => 'so-b37',
            'airline_code' => 'EK',
            'validating_carrier' => 'EK',
            'segments' => [[
                'origin' => 'LHE',
                'destination' => 'DXB',
                'departure_at' => '2026-06-01T08:00:00Z',
                'arrival_at' => '2026-06-01T14:00:00Z',
                'carrier' => 'EK',
                'flight_number' => '615',
                'booking_class' => 'K',
                'fare_basis_code' => 'KLITE1',
            ]],
            'fare_breakdown' => [
                'supplier_total' => 100,
                'currency' => 'USD',
                'base_fare' => 80,
                'taxes' => 20,
                'passenger_counts' => ['adults' => 1, 'children' => 0, 'infants' => 0],
            ],
        ];
        $passengerData = [
            'passengers' => [[
                'passenger_type' => 'adult',
                'first_name' => 'B37',
                'last_name' => 'Pnr',
                'gender' => 'M',
                'date_of_birth' => '1991-02-02',
                'passport_number' => 'AB7654321',
                'passport_issuing_country' => 'PK',
                'passport_expiry_date' => '2030-05-05',
                'nationality' => 'PK',
                'document_type' => 'passport',
            ]],
            'contact' => ['email' => 'b37-pnr@example.com', 'phone' => '+1000000000'],
        ];
        $draft = $builder->buildInternalDraft($offer, $passengerData);
        unset($draft['_valid']);
        $assertions = [
            'trip_orders_flight_details_sabre_phoneLine_v1' => function (array $wire): void {
                $pl = is_array($wire['phoneLine'] ?? null) ? $wire['phoneLine'] : [];
                $this->assertArrayHasKey('Number', $pl);
                $this->assertArrayHasKey('Type', $pl);
                $this->assertSame('DXB', $pl['LocationCode'] ?? null);
            },
            'trip_orders_flight_details_sabre_phoneLines_v1' => function (array $wire): void {
                $rows = is_array($wire['phoneLines'] ?? null) ? $wire['phoneLines'] : [];
                $this->assertArrayHasKey(0, $rows);
                $this->assertArrayHasKey('Number', is_array($rows[0]) ? $rows[0] : []);
            },
            'trip_orders_flight_details_sabre_contactNumbers_v1' => function (array $wire): void {
                $rows = is_array($wire['contactNumbers'] ?? null) ? $wire['contactNumbers'] : [];
                $r0 = is_array($rows[0] ?? null) ? $rows[0] : [];
                $this->assertArrayHasKey('Number', $r0);
                $this->assertArrayHasKey('PhoneUseType', $r0);
            },
            'trip_orders_flight_details_sabre_pnrContact_v1' => function (array $wire): void {
                $pnr = is_array($wire['pnrContact'] ?? null) ? $wire['pnrContact'] : [];
                $ph = is_array($pnr['phone'] ?? null) ? $pnr['phone'] : [];
                $this->assertArrayHasKey('Number', $ph);
            },
            'trip_orders_flight_details_sabre_reservationContact_v1' => function (array $wire): void {
                $rc = is_array($wire['reservationContact'] ?? null) ? $wire['reservationContact'] : [];
                $ph = is_array($rc['phones'] ?? null) ? $rc['phones'] : [];
                $this->assertArrayHasKey('Number', is_array($ph[0] ?? null) ? $ph[0] : []);
            },
            'trip_orders_flight_details_sabre_contactInfo_phoneLine_v1' => function (array $wire): void {
                $ci = is_array($wire['contactInfo'] ?? null) ? $wire['contactInfo'] : [];
                $ap = is_array($ci['agencyPhone'] ?? null) ? $ci['agencyPhone'] : [];
                $this->assertArrayHasKey('Number', $ap);
            },
            'trip_orders_flight_details_sabre_travelers_phone_v1' => function (array $wire): void {
                $t0 = is_array($wire['travelers'][0] ?? null) ? $wire['travelers'][0] : [];
                $ph = is_array($t0['phone'] ?? null) ? $t0['phone'] : [];
                $this->assertArrayHasKey('Number', $ph);
            },
        ];
        foreach ($assertions as $style => $fn) {
            $expected = $builder->expectedSabreAgencyPhoneDotPathsForStyle($style);
            $this->assertNotSame([], $expected, $style);
            $env = $builder->buildTripOrdersCreateBookingEnvelope($draft, [], $style);
            $wire = $builder->tripOrdersWirePostBodyFromEnvelope($env);
            $this->assertArrayHasKey('flightDetails', $wire, $style);
            $this->assertArrayHasKey('contactInfo', $wire, $style);
            $fn($wire);
            $sum = $builder->summarizeTripOrdersWirePostBodyForEnvelope($env);
            $this->assertTrue($sum['wire_has_agency_phone'], $style);
            $this->assertSame($expected[0], $sum['wire_agency_phone_field_style'], $style);
            $this->assertContains('DXB', $sum['wire_phone_location_values_sanitized'], $style);
            $this->assertContains('A', $sum['wire_phone_use_type_values_sanitized'], $style);
            $this->assertTrue($sum['wire_has_contactInfo'], $style);
            $this->assertTrue($sum['wire_has_customer_contact_phone'], $style);
            $t0 = is_array($wire['travelers'][0] ?? null) ? $wire['travelers'][0] : [];
            $this->assertArrayHasKey('passengerCode', $t0, $style);
            $tick = is_array($wire['ticketing'] ?? null) ? $wire['ticketing'] : [];
            $this->assertFalse((bool) ($tick['enabled'] ?? true), $style);
            $red = $builder->redactTripOrdersWireJsonForPreview($wire);
            $redJson = json_encode($red);
            $this->assertStringNotContainsString('19997776666', (string) $redJson, $style);
            $this->assertStringNotContainsString('B37', (string) $redJson, $style);
            $this->assertStringNotContainsString('b37-pnr@', (string) $redJson, $style);
        }
    }

    public function test_b37_compare_without_send_emits_agency_phone_error_cleared_false(): void
    {
        $booking = $this->seedWireTestBooking();
        config([
            'suppliers.sabre.booking_path' => '/v1/trip/orders/createBooking',
            'suppliers.sabre.booking_schema' => 'trip_orders_create_booking',
            'suppliers.sabre.agency_phone' => '+19997775555',
            'suppliers.sabre.agency_phone_country_code' => 'PK',
            'suppliers.sabre.agency_phone_type' => 'AGENCY',
        ]);
        Artisan::call('sabre:compare-createbooking-styles', [
            '--booking' => (string) $booking->id,
            '--style' => 'trip_orders_flight_details_sabre_phoneLine_v1',
        ]);
        $out = Artisan::output();
        $this->assertStringContainsString('agency_phone_error_cleared=false', $out);
        $this->assertStringContainsString('agency_phone_error=false', $out);
        $this->assertStringContainsString('likely_profile_level_agency_phone_issue=false', $out);
        $this->assertStringContainsString('wire_phone_location_values_sanitized', $out);
        foreach (['wire-b23-test@example.com', '+19997775555', 'Bearer '] as $needle) {
            $this->assertStringNotContainsString($needle, $out, 'compare output must not leak: '.$needle);
        }
    }

    public function test_b38_compare_booking_endpoints_defaults_to_inspect_only_matrix(): void
    {
        $booking = $this->seedWireTestBooking();
        Artisan::call('sabre:compare-booking-endpoints', ['--booking' => (string) $booking->id]);
        $out = Artisan::output();
        $this->assertStringContainsString('http_status=not_sent', $out);
        $this->assertStringContainsString('access_result=inspect_only', $out);
        $this->assertStringContainsString('/v2/passenger/create', $out);
        $this->assertStringContainsString('/v2.5.0/passenger/records?mode=create', $out);
        $this->assertStringContainsString('/v1/trip/orders/createBooking', $out);
    }

    public function test_p4_compare_matrix_lists_passenger_records_pricing_experiment_styles(): void
    {
        $booking = $this->seedWireTestBooking();
        Artisan::call('sabre:compare-booking-endpoints', [
            '--booking' => (string) $booking->id,
            '--skip-trip-orders' => true,
        ]);
        $out = Artisan::output();
        foreach (SabreBookingPayloadBuilder::BOOKING_ENDPOINT_COMPARE_PASSENGER_RECORDS_P4_STYLES as $style) {
            $this->assertStringContainsString('payload_style='.$style, $out);
        }
        $this->assertStringContainsString('wire_contract_valid=true', $out);
        $this->assertStringContainsString('/v2.4.0/passenger/records?mode=create', $out);
    }

    public function test_p4_retry_rebook_compare_style_wire_contract_valid_without_production_config(): void
    {
        config(['suppliers.sabre.traditional_cpnr_airbook_retry_redisplay' => false]);
        $booking = $this->seedWireTestBooking();
        $booking->load(['passengers', 'contact']);
        $snapshot = is_array($booking->meta['normalized_offer_snapshot'] ?? null)
            ? $booking->meta['normalized_offer_snapshot']
            : [];
        $p = $booking->passengers->first();
        $c = $booking->contact;
        $this->assertNotNull($p);
        $this->assertNotNull($c);
        $expiry = $p->passport_expiry_date;
        $expiryStr = $expiry instanceof \DateTimeInterface ? $expiry->format('Y-m-d') : (string) $expiry;
        $passengerData = [
            'passengers' => [[
                'type' => 'ADT',
                'first_name' => (string) $p->first_name,
                'last_name' => (string) $p->last_name,
                'passport_number' => (string) $p->passport_number,
                'passport_issuing_country' => (string) $p->passport_issuing_country,
                'passport_expiry_date' => $expiryStr,
                'nationality' => (string) $p->nationality,
                'document_type' => (string) $p->document_type,
            ]],
            'contact' => [
                'email' => (string) $c->email,
                'phone' => (string) $c->phone,
            ],
        ];
        $builder = app(SabreBookingPayloadBuilder::class);
        $draft = $builder->buildInternalDraft($snapshot, $passengerData);
        $this->assertTrue($draft['_valid'] ?? false);
        unset($draft['_valid']);
        $wire = $builder->stripOtaInternalKeysFromBookingWire(
            $builder->buildTraditionalPnrCreatePassengerNameRecordV1AirbookRetryRebookRedisplayCompareWire($draft),
        );
        $sum = $builder->summarizeTraditionalPnrWirePostBody(
            $wire,
            is_array($booking->meta) ? $booking->meta : [],
            SabreBookingPayloadBuilder::TRADITIONAL_PNR_CREATE_PASSENGER_NAME_RECORD_V1_AIRBOOK_RETRY_REBOOK_REDISPLAY_COMPARE_V1,
        );
        $this->assertTrue($sum['wire_traditional_pnr_contract_valid'] ?? false);
        $this->assertTrue($sum['wire_airbook_retry_rebook_contract_valid'] ?? false);
    }

    public function test_b38_compare_booking_endpoints_skip_trip_orders_excludes_createbooking_row(): void
    {
        $booking = $this->seedWireTestBooking();
        Artisan::call('sabre:compare-booking-endpoints', [
            '--booking' => (string) $booking->id,
            '--skip-trip-orders' => true,
        ]);
        $this->assertStringNotContainsString('/v1/trip/orders/createBooking', Artisan::output());
    }

    public function test_b38_compare_booking_endpoints_send_without_endpoint_style_fails_fast(): void
    {
        $booking = $this->seedWireTestBooking();
        config([
            'suppliers.sabre.booking_enabled' => true,
            'suppliers.sabre.booking_live_call_enabled' => true,
        ]);
        Artisan::call('sabre:compare-booking-endpoints', [
            '--booking' => (string) $booking->id,
            '--send' => true,
        ]);
        $this->assertStringContainsString('send_requires_endpoint_and_style', Artisan::output());
    }

    public function test_b38_inspect_booking_config_suggested_flow_traditional_after_attempt_digest(): void
    {
        $booking = $this->seedWireTestBooking();
        SupplierBookingAttempt::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'supplier_connection_id' => (int) data_get($booking->meta, 'supplier_connection_id'),
            'provider' => SupplierProvider::Sabre->value,
            'action' => 'compare_trip_orders_createbooking_style',
            'status' => 'failed',
            'error_code' => null,
            'error_message' => null,
            'supplier_reference' => null,
            'safe_summary' => ['response_error_messages' => ['AGENCY_PHONE_MISSING office phone']],
            'attempted_by' => null,
            'attempted_at' => now(),
            'completed_at' => now(),
        ]);
        Artisan::call('sabre:inspect-booking-config', ['--booking' => (string) $booking->id]);
        $out = Artisan::output();
        $this->assertStringContainsString('trip_orders_agency_phone_still_rejected=true', $out);
        $this->assertStringContainsString('suggested_booking_flow=traditional_pnr_candidate', $out);
    }

    public function test_b38_traditional_pnr_preview_redacts_pii(): void
    {
        $booking = $this->seedWireTestBooking();
        config(['suppliers.sabre.agency_phone' => '+19997776665']);
        Artisan::call('sabre:inspect-booking-payload', [
            '--booking' => (string) $booking->id,
            '--preview-json' => true,
            '--style' => SabreBookingPayloadBuilder::TRADITIONAL_PNR_CREATE_PASSENGER_NAME_RECORD_V1,
        ]);
        $json = Artisan::output();
        $this->assertStringNotContainsString('WireTest', $json);
        $this->assertStringNotContainsString('wire-b23-test@', $json);
        $this->assertStringNotContainsString('XT8888888', $json);
        $this->assertStringNotContainsString('+19997776665', $json);
        $this->assertStringContainsString('traditional_pnr_create_passenger_name_record_v1', $json);
    }

    public function test_b38_compare_send_agency_phone_missing_sets_likely_profile_when_agency_wire_ok(): void
    {
        $booking = $this->seedWireTestBooking();
        $cid = (int) data_get($booking->meta, 'supplier_connection_id');
        $conn = SupplierConnection::query()->findOrFail($cid);
        $conn->credentials = ['client_id' => 'test_client', 'client_secret' => 'test_secret'];
        $conn->save();

        config([
            'suppliers.sabre.booking_enabled' => true,
            'suppliers.sabre.booking_live_call_enabled' => true,
            'suppliers.sabre.booking_path' => '/v1/trip/orders/createBooking',
            'suppliers.sabre.booking_schema' => 'trip_orders_create_booking',
            'suppliers.sabre.agency_phone' => '+19997776660',
            'suppliers.sabre.agency_phone_country_code' => 'PK',
            'suppliers.sabre.agency_phone_type' => 'AGENCY',
        ]);

        Http::fake(function (Request $request) {
            if (str_contains($request->url(), '/v2/auth/token')) {
                return Http::response(['access_token' => 'fake-token', 'expires_in' => 3600], 200);
            }
            if (str_contains($request->url(), '/v1/trip/orders/createBooking')) {
                return Http::response([
                    'errors' => [['title' => 'AGENCY_PHONE_MISSING', 'detail' => 'Agency phone is needed']],
                ], 422);
            }

            return Http::response(['message' => 'unexpected'], 500);
        });

        Artisan::call('sabre:compare-createbooking-styles', [
            '--booking' => (string) $booking->id,
            '--send' => true,
            '--style' => 'trip_orders_flight_details_sabre_v1',
        ]);
        $out = Artisan::output();
        $this->assertStringContainsString('agency_phone_error=true', $out);
        $this->assertStringContainsString('likely_profile_level_agency_phone_issue=true', $out);
        $this->assertStringContainsString('suggested_next_path=traditional_pnr_fallback', $out);
        $this->assertStringContainsString('agency_phone_error_cleared=false', $out);
    }

    public function test_b39_inspect_booking_payload_wire_preview_honors_traditional_style(): void
    {
        $booking = $this->seedWireTestBooking();
        config(['suppliers.sabre.ticketing_enabled' => false]);
        Artisan::call('sabre:inspect-booking-payload', [
            '--booking' => (string) $booking->id,
            '--wire-preview-json' => true,
            '--style' => SabreBookingPayloadBuilder::TRADITIONAL_PNR_CREATE_PASSENGER_NAME_RECORD_V1,
        ]);
        $out = Artisan::output();
        $this->assertStringContainsString('payload_style=traditional_pnr_create_passenger_name_record_v1', $out);
        $this->assertStringContainsString('wire_root_keys=["CreatePassengerNameRecordRQ"]', $out);
        $this->assertStringContainsString('wire_has_create_passenger_name_record_rq=true', $out);
        $this->assertStringContainsString('wire_has_travel_itinerary_add_info=true', $out);
        $this->assertStringContainsString('wire_has_air_book=true', $out);
        $this->assertStringContainsString('wire_has_halt_on_air_price_error=true', $out);
        $this->assertStringContainsString('wire_has_halt_on_air_book_error=false', $out);
        $this->assertStringContainsString('wire_has_air_price=true', $out);
        $this->assertStringContainsString('wire_has_root_air_price=true', $out);
        $this->assertStringContainsString('wire_root_air_price_type=array', $out);
        $this->assertStringContainsString('wire_root_air_price_count=1', $out);
        $this->assertStringContainsString('wire_root_air_price_retain_present=true', $out);
        $this->assertStringContainsString('wire_air_price_has_optional_qualifiers=true', $out);
        $this->assertStringContainsString('wire_air_price_has_pricing_qualifiers=true', $out);
        $this->assertStringContainsString('wire_air_price_passenger_type_quantities_are_strings=true', $out);
        $this->assertStringContainsString('wire_air_price_passenger_type_contract_valid=true', $out);
        $this->assertStringContainsString('wire_iati_airprice_passenger_type_delta_closed=true', $out);
        $this->assertStringContainsString('wire_airbook_has_air_price=false', $out);
        $this->assertStringContainsString('wire_airbook_has_price_quote_information=false', $out);
        $this->assertStringContainsString('wire_airbook_has_fare_breakdown_summary=false', $out);
        $this->assertStringContainsString('wire_has_received_from=true', $out);
        $this->assertStringContainsString('wire_post_processing_has_end_transaction=true', $out);
        $this->assertStringContainsString('wire_post_processing_has_end_transaction_rq=false', $out);
        $this->assertStringContainsString('wire_post_processing_has_redisplay_reservation=true', $out);
        $this->assertStringContainsString('wire_has_target_city=false', $out);
        $this->assertStringContainsString('wire_has_email=true', $out);
        $this->assertStringContainsString('wire_customer_email_type=array', $out);
        $this->assertStringContainsString('wire_customer_email_type_valid=true', $out);
        $this->assertStringContainsString('wire_customer_email_has_type=true', $out);
        $this->assertStringContainsString('wire_ticketing_enabled=false', $out);
        $this->assertStringContainsString('wire_traditional_pnr_contract_valid=true', $out);
        $this->assertStringContainsString('wire_flight_segment_has_cabin_code=false', $out);
        $this->assertStringContainsString('wire_flight_segment_has_class_of_service=false', $out);
        $this->assertStringContainsString('wire_flight_segment_has_fare_basis_code=false', $out);
        $this->assertStringContainsString('wire_flight_segment_has_number=false', $out);
        $this->assertStringContainsString('wire_flight_segment_has_res_book_desig_code=true', $out);
        $this->assertStringContainsString('wire_flight_segment_number_in_party_type=string', $out);
        $this->assertStringContainsString('wire_flight_segment_number_in_party_valid=true', $out);
        $this->assertStringContainsString('wire_remark_type_enum_valid=true', $out);
        $this->assertStringContainsString('wire_has_general_remark=true', $out);
        $this->assertStringContainsString('wire_special_service_present=false', $out);
        $this->assertStringContainsString('wire_special_service_has_service=false', $out);
        $this->assertStringContainsString('wire_special_service_omitted=true', $out);
        $this->assertStringContainsString('wire_add_remark_present=true', $out);
        $this->assertStringContainsString('wire_agency_info_present=true', $out);
        $this->assertStringContainsString('wire_agency_info_has_telephone=false', $out);
        $this->assertStringContainsString('wire_customer_info_has_contact_numbers=true', $out);
        $this->assertStringContainsString('wire_customer_info_has_email=true', $out);
        $this->assertStringContainsString('wire_customer_person_name_type=array', $out);
        $this->assertStringContainsString('wire_customer_person_name_count=', $out);
        $this->assertStringContainsString('wire_customer_person_name_array_valid=true', $out);
        $this->assertStringContainsString('wire_remarks_count=', $out);
        $this->assertStringContainsString('redacted_wire_request_body=', $out);
        $this->assertStringContainsString('"CreatePassengerNameRecordRQ"', $out);
        $this->assertStringNotContainsString('WireTest', $out);
        $this->assertStringNotContainsString('+92300', $out);
    }

    public function test_b39_summarize_traditional_wire_invalid_when_cpnr_empty(): void
    {
        $builder = app(SabreBookingPayloadBuilder::class);
        $sum = $builder->summarizeTraditionalPnrWirePostBody(['CreatePassengerNameRecordRQ' => []]);
        $this->assertFalse($sum['wire_traditional_pnr_contract_valid']);
        $this->assertNotEmpty($sum['wire_invalid_traditional_pnr_contract_keys']);
        $this->assertSame('missing', $sum['wire_flight_segment_number_in_party_type'] ?? null);
        $this->assertFalse((bool) ($sum['wire_flight_segment_number_in_party_valid'] ?? true));
    }

    public function test_b46_traditional_cpnr_wire_omits_halt_on_air_book_error(): void
    {
        $booking = $this->seedWireTestBooking();
        config(['suppliers.sabre.ticketing_enabled' => false]);
        $svc = app(SabreBookingService::class);
        $preview = $svc->previewTripOrdersWireJsonForInspectCommand(
            $booking,
            SabreBookingPayloadBuilder::TRADITIONAL_PNR_CREATE_PASSENGER_NAME_RECORD_V1
        );
        $this->assertArrayNotHasKey('error', $preview);
        $redacted = $preview['redacted_wire_request_body'] ?? null;
        $this->assertIsArray($redacted);
        $wireJson = json_encode($redacted, JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('haltOnAirBookError', $wireJson);
        $this->assertFalse($preview['wire_has_halt_on_air_book_error'] ?? true);
        $this->assertTrue($preview['wire_has_halt_on_air_price_error'] ?? false);
    }

    public function test_b47_traditional_cpnr_air_book_excludes_pricing_root_air_price_retain(): void
    {
        $booking = $this->seedWireTestBooking();
        config(['suppliers.sabre.ticketing_enabled' => false]);
        $booking->load(['passengers', 'contact']);
        $snapshot = is_array($booking->meta['normalized_offer_snapshot'] ?? null)
            ? $booking->meta['normalized_offer_snapshot'] : [];
        $this->assertNotSame([], $snapshot);
        $p = $booking->passengers->first();
        $this->assertNotNull($p);
        $c = $booking->contact;
        $this->assertNotNull($c);
        $expiry = $p->passport_expiry_date;
        $expiryStr = $expiry instanceof \DateTimeInterface ? $expiry->format('Y-m-d') : (string) $expiry;
        $passengerData = [
            'passengers' => [[
                'type' => 'ADT',
                'first_name' => (string) $p->first_name,
                'last_name' => (string) $p->last_name,
                'passport_number' => (string) $p->passport_number,
                'passport_issuing_country' => (string) $p->passport_issuing_country,
                'passport_expiry_date' => $expiryStr,
                'nationality' => (string) $p->nationality,
                'document_type' => (string) $p->document_type,
            ]],
            'contact' => [
                'email' => (string) $c->email,
                'phone' => (string) $c->phone,
            ],
        ];
        $builder = app(SabreBookingPayloadBuilder::class);
        $draft = $builder->buildInternalDraft($snapshot, $passengerData);
        $this->assertTrue($draft['_valid'] ?? false);
        $apiDraft = $draft;
        unset($apiDraft['_valid']);
        $raw = $builder->buildTraditionalPnrCreatePassengerNameRecordV1Wire($apiDraft, []);
        $wire = $builder->stripOtaInternalKeysFromBookingWire($raw);
        $cpnr = is_array($wire['CreatePassengerNameRecordRQ'] ?? null) ? $wire['CreatePassengerNameRecordRQ'] : [];
        $airBook = is_array($cpnr['AirBook'] ?? null) ? $cpnr['AirBook'] : [];
        $this->assertArrayNotHasKey('AirPrice', $airBook);
        $this->assertArrayNotHasKey('OTAFareBreakdownSummary', $airBook);
        $this->assertArrayNotHasKey('PriceQuoteInformation', $airBook);
        $this->assertNotEmpty($airBook['OriginDestinationInformation']['FlightSegment'] ?? null);
        $ap = $cpnr['AirPrice'] ?? null;
        $this->assertIsArray($ap);
        $this->assertTrue(array_is_list($ap));
        $this->assertArrayHasKey(0, $ap);
        $this->assertIsArray($ap[0]);
        $this->assertTrue((bool) data_get($cpnr, 'AirPrice.0.PriceRequestInformation.Retain'));
        $pt = data_get($cpnr, 'AirPrice.0.PriceRequestInformation.OptionalQualifiers.PricingQualifiers.PassengerType');
        $this->assertIsArray($pt);
        $this->assertTrue(array_is_list($pt));
        $this->assertGreaterThanOrEqual(1, count($pt));
        $this->assertArrayNotHasKey('Brand', is_array(data_get($cpnr, 'AirPrice.0.PriceRequestInformation.OptionalQualifiers.PricingQualifiers')) ? data_get($cpnr, 'AirPrice.0.PriceRequestInformation.OptionalQualifiers.PricingQualifiers') : []);
        $pt0 = is_array($pt[0] ?? null) ? $pt[0] : [];
        $this->assertSame('ADT', $pt0['Code'] ?? null);
        $this->assertSame('1', $pt0['Quantity'] ?? null);
        $this->assertIsString($pt0['Quantity'] ?? null);
        $fsNip = data_get($cpnr, 'AirBook.OriginDestinationInformation.FlightSegment');
        $nipRows = is_array($fsNip) ? (array_is_list($fsNip) ? $fsNip : [$fsNip]) : [];
        foreach ($nipRows as $nipSeg) {
            if (is_array($nipSeg)) {
                $this->assertArrayHasKey('NumberInParty', $nipSeg);
                $this->assertIsString($nipSeg['NumberInParty']);
            }
        }
        $sum = $builder->summarizeTraditionalPnrWirePostBody($wire);
        $this->assertFalse($sum['wire_airbook_has_air_price']);
        $this->assertFalse($sum['wire_airbook_has_price_quote_information']);
        $this->assertFalse($sum['wire_airbook_has_fare_breakdown_summary']);
        $this->assertTrue($sum['wire_has_root_air_price']);
        $this->assertTrue($sum['wire_has_air_price']);
        $this->assertSame('array', $sum['wire_root_air_price_type'] ?? null);
        $this->assertSame(1, (int) ($sum['wire_root_air_price_count'] ?? 0));
        $this->assertTrue((bool) ($sum['wire_root_air_price_retain_present'] ?? false));
        $this->assertTrue($sum['wire_air_price_has_optional_qualifiers'] ?? false);
        $this->assertTrue($sum['wire_air_price_has_pricing_qualifiers'] ?? false);
        $this->assertTrue($sum['wire_air_price_passenger_type_quantities_are_strings'] ?? false);
        $this->assertTrue($sum['wire_air_price_passenger_type_contract_valid'] ?? false);
        $this->assertTrue($sum['wire_iati_airprice_passenger_type_delta_closed'] ?? false);
        $this->assertFalse((bool) ($sum['wire_airprice_has_validating_carrier'] ?? true));
        $this->assertSame([], $sum['wire_airprice_validating_carriers_sanitized'] ?? []);
        $this->assertNull($sum['wire_airprice_validating_carrier_invalid_pointer'] ?? null);
        $this->assertTrue($sum['wire_traditional_pnr_contract_valid']);
        $svc = app(SabreBookingService::class);
        $preview = $svc->previewTripOrdersWireJsonForInspectCommand(
            $booking,
            SabreBookingPayloadBuilder::TRADITIONAL_PNR_CREATE_PASSENGER_NAME_RECORD_V1
        );
        $this->assertArrayNotHasKey('error', $preview);
        $this->assertFalse($preview['wire_ticketing_enabled'] ?? true);
        $this->assertTrue($preview['wire_has_root_air_price'] ?? false);
        $this->assertSame('array', $preview['wire_root_air_price_type'] ?? null);
        $this->assertSame(1, (int) ($preview['wire_root_air_price_count'] ?? 0));
        $this->assertTrue((bool) ($preview['wire_root_air_price_retain_present'] ?? false));
        $this->assertTrue($preview['wire_air_price_passenger_type_contract_valid'] ?? false);
        $this->assertFalse((bool) ($preview['wire_airprice_has_validating_carrier'] ?? true));
        $this->assertSame([], $preview['wire_airprice_validating_carriers_sanitized'] ?? []);
        $wireJson = json_encode($preview['redacted_wire_request_body'] ?? [], JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString((string) $p->first_name, $wireJson);
        $this->assertStringNotContainsString('+92300', $wireJson);
    }

    public function test_b48_traditional_cpnr_air_book_flight_segment_sell_schema_and_diagnostics(): void
    {
        $booking = $this->seedWireTestBooking();
        config(['suppliers.sabre.ticketing_enabled' => false]);
        $booking->load(['passengers', 'contact']);
        $snapshot = is_array($booking->meta['normalized_offer_snapshot'] ?? null)
            ? $booking->meta['normalized_offer_snapshot'] : [];
        $this->assertNotSame([], $snapshot);
        $p = $booking->passengers->first();
        $this->assertNotNull($p);
        $c = $booking->contact;
        $this->assertNotNull($c);
        $expiry = $p->passport_expiry_date;
        $expiryStr = $expiry instanceof \DateTimeInterface ? $expiry->format('Y-m-d') : (string) $expiry;
        $passengerData = [
            'passengers' => [[
                'type' => 'ADT',
                'first_name' => (string) $p->first_name,
                'last_name' => (string) $p->last_name,
                'passport_number' => (string) $p->passport_number,
                'passport_issuing_country' => (string) $p->passport_issuing_country,
                'passport_expiry_date' => $expiryStr,
                'nationality' => (string) $p->nationality,
                'document_type' => (string) $p->document_type,
            ]],
            'contact' => [
                'email' => (string) $c->email,
                'phone' => (string) $c->phone,
            ],
        ];
        $builder = app(SabreBookingPayloadBuilder::class);
        $draft = $builder->buildInternalDraft($snapshot, $passengerData);
        $this->assertTrue($draft['_valid'] ?? false);
        $apiDraft = $draft;
        unset($apiDraft['_valid']);
        $raw = $builder->buildTraditionalPnrCreatePassengerNameRecordV1Wire($apiDraft, []);
        $wire = $builder->stripOtaInternalKeysFromBookingWire($raw);
        $cpnr = is_array($wire['CreatePassengerNameRecordRQ'] ?? null) ? $wire['CreatePassengerNameRecordRQ'] : [];
        $fs = data_get($cpnr, 'AirBook.OriginDestinationInformation.FlightSegment');
        $this->assertIsArray($fs);
        $rows = array_is_list($fs) ? $fs : [$fs];
        $this->assertNotEmpty($rows);
        foreach ($rows as $seg) {
            $this->assertIsArray($seg);
            $this->assertArrayNotHasKey('CabinCode', $seg);
            $this->assertArrayNotHasKey('ClassOfService', $seg);
            $this->assertArrayNotHasKey('FareBasisCode', $seg);
            $this->assertArrayNotHasKey('Number', $seg);
            $this->assertArrayHasKey('NumberInParty', $seg);
            $this->assertIsString($seg['NumberInParty']);
            $this->assertSame('1', (string) $seg['NumberInParty']);
        }
        $this->assertSame('K', (string) (data_get($cpnr, 'AirBook.OriginDestinationInformation.FlightSegment.0.ResBookDesigCode')
            ?? data_get($cpnr, 'AirBook.OriginDestinationInformation.FlightSegment.ResBookDesigCode')));
        $airBook = is_array($cpnr['AirBook'] ?? null) ? $cpnr['AirBook'] : [];
        $this->assertArrayNotHasKey('AirPrice', $airBook);
        $this->assertArrayNotHasKey('OTAFareBreakdownSummary', $airBook);
        $this->assertArrayNotHasKey('PriceQuoteInformation', $airBook);
        $this->assertTrue((bool) data_get($cpnr, 'AirPrice.0.PriceRequestInformation.Retain'));
        $sum = $builder->summarizeTraditionalPnrWirePostBody($wire);
        $this->assertFalse($sum['wire_flight_segment_has_cabin_code']);
        $this->assertFalse($sum['wire_flight_segment_has_class_of_service']);
        $this->assertFalse($sum['wire_flight_segment_has_fare_basis_code']);
        $this->assertFalse($sum['wire_flight_segment_has_number']);
        $this->assertTrue($sum['wire_flight_segment_has_res_book_desig_code']);
        $this->assertTrue($sum['wire_traditional_pnr_contract_valid']);
        $this->assertFalse($sum['wire_ticketing_enabled']);
        $this->assertSame('string', $sum['wire_flight_segment_number_in_party_type'] ?? null);
        $this->assertTrue((bool) ($sum['wire_flight_segment_number_in_party_valid'] ?? false));
        $svc = app(SabreBookingService::class);
        $preview = $svc->previewTripOrdersWireJsonForInspectCommand(
            $booking,
            SabreBookingPayloadBuilder::TRADITIONAL_PNR_CREATE_PASSENGER_NAME_RECORD_V1
        );
        $this->assertArrayNotHasKey('error', $preview);
        $this->assertFalse($preview['wire_flight_segment_has_cabin_code'] ?? true);
        $this->assertFalse($preview['wire_flight_segment_has_class_of_service'] ?? true);
        $this->assertFalse($preview['wire_flight_segment_has_fare_basis_code'] ?? true);
        $this->assertFalse($preview['wire_flight_segment_has_number'] ?? true);
        $this->assertTrue($preview['wire_flight_segment_has_res_book_desig_code'] ?? false);
        $this->assertSame('string', $preview['wire_flight_segment_number_in_party_type'] ?? null);
        $this->assertTrue((bool) ($preview['wire_flight_segment_number_in_party_valid'] ?? false));
        $wireJson = json_encode($preview['redacted_wire_request_body'] ?? [], JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('WireTest', $wireJson);
        $this->assertStringNotContainsString('wire-b23-test@', $wireJson);
        $this->assertStringNotContainsString('+923001234567', $wireJson);
    }

    public function test_b49_traditional_cpnr_flight_segment_number_in_party_string_schema_and_integer_diagnostic(): void
    {
        $booking = $this->seedWireTestBooking();
        config(['suppliers.sabre.ticketing_enabled' => false]);
        $booking->load(['passengers', 'contact']);
        $snapshot = is_array($booking->meta['normalized_offer_snapshot'] ?? null)
            ? $booking->meta['normalized_offer_snapshot'] : [];
        $this->assertNotSame([], $snapshot);
        $p = $booking->passengers->first();
        $this->assertNotNull($p);
        $c = $booking->contact;
        $this->assertNotNull($c);
        $expiry = $p->passport_expiry_date;
        $expiryStr = $expiry instanceof \DateTimeInterface ? $expiry->format('Y-m-d') : (string) $expiry;
        $passengerData = [
            'passengers' => [[
                'type' => 'ADT',
                'first_name' => (string) $p->first_name,
                'last_name' => (string) $p->last_name,
                'passport_number' => (string) $p->passport_number,
                'passport_issuing_country' => (string) $p->passport_issuing_country,
                'passport_expiry_date' => $expiryStr,
                'nationality' => (string) $p->nationality,
                'document_type' => (string) $p->document_type,
            ]],
            'contact' => [
                'email' => (string) $c->email,
                'phone' => (string) $c->phone,
            ],
        ];
        $builder = app(SabreBookingPayloadBuilder::class);
        $draft = $builder->buildInternalDraft($snapshot, $passengerData);
        $this->assertTrue($draft['_valid'] ?? false);
        $apiDraft = $draft;
        unset($apiDraft['_valid']);
        $raw = $builder->buildTraditionalPnrCreatePassengerNameRecordV1Wire($apiDraft, []);
        $wire = $builder->stripOtaInternalKeysFromBookingWire($raw);
        $sumOk = $builder->summarizeTraditionalPnrWirePostBody($wire);
        $this->assertSame('string', $sumOk['wire_flight_segment_number_in_party_type'] ?? null);
        $this->assertTrue((bool) ($sumOk['wire_flight_segment_number_in_party_valid'] ?? false));
        $this->assertTrue($sumOk['wire_traditional_pnr_contract_valid']);

        $bad = $wire;
        $fsPath = 'CreatePassengerNameRecordRQ.AirBook.OriginDestinationInformation.FlightSegment';
        $fsNode = data_get($bad, $fsPath);
        if (is_array($fsNode) && array_is_list($fsNode)) {
            data_set($bad, $fsPath.'.0.NumberInParty', 1);
        } else {
            data_set($bad, $fsPath.'.NumberInParty', 1);
        }
        $sumBad = $builder->summarizeTraditionalPnrWirePostBody($bad);
        $this->assertSame('integer', $sumBad['wire_flight_segment_number_in_party_type'] ?? null);
        $this->assertFalse((bool) ($sumBad['wire_flight_segment_number_in_party_valid'] ?? true));
        $this->assertFalse($sumBad['wire_traditional_pnr_contract_valid']);
        $this->assertContains('flight_segment_number_in_party_not_all_strings', $sumBad['wire_invalid_traditional_pnr_contract_keys']);

        $bad2 = $wire;
        $fsNode2 = data_get($bad2, $fsPath);
        $this->assertIsArray($fsNode2);
        $row0 = array_is_list($fsNode2) ? $fsNode2[0] : $fsNode2;
        $this->assertIsArray($row0);
        $row1 = $row0;
        $row0 = array_merge($row0, ['NumberInParty' => '1']);
        $row1 = array_merge($row1, ['NumberInParty' => 1]);
        data_set($bad2, $fsPath, [$row0, $row1]);
        $sumMix = $builder->summarizeTraditionalPnrWirePostBody($bad2);
        $this->assertSame('mixed', $sumMix['wire_flight_segment_number_in_party_type'] ?? null);
        $this->assertFalse((bool) ($sumMix['wire_flight_segment_number_in_party_valid'] ?? true));
    }

    public function test_b50_traditional_cpnr_root_air_price_json_array_shape_and_legacy_object_diagnostic(): void
    {
        $booking = $this->seedWireTestBooking();
        config(['suppliers.sabre.ticketing_enabled' => false]);
        $booking->load(['passengers', 'contact']);
        $snapshot = is_array($booking->meta['normalized_offer_snapshot'] ?? null)
            ? $booking->meta['normalized_offer_snapshot'] : [];
        $this->assertNotSame([], $snapshot);
        $p = $booking->passengers->first();
        $this->assertNotNull($p);
        $c = $booking->contact;
        $this->assertNotNull($c);
        $expiry = $p->passport_expiry_date;
        $expiryStr = $expiry instanceof \DateTimeInterface ? $expiry->format('Y-m-d') : (string) $expiry;
        $passengerData = [
            'passengers' => [[
                'type' => 'ADT',
                'first_name' => (string) $p->first_name,
                'last_name' => (string) $p->last_name,
                'passport_number' => (string) $p->passport_number,
                'passport_issuing_country' => (string) $p->passport_issuing_country,
                'passport_expiry_date' => $expiryStr,
                'nationality' => (string) $p->nationality,
                'document_type' => (string) $p->document_type,
            ]],
            'contact' => [
                'email' => (string) $c->email,
                'phone' => (string) $c->phone,
            ],
        ];
        $builder = app(SabreBookingPayloadBuilder::class);
        $draft = $builder->buildInternalDraft($snapshot, $passengerData);
        $this->assertTrue($draft['_valid'] ?? false);
        $apiDraft = $draft;
        unset($apiDraft['_valid']);
        $raw = $builder->buildTraditionalPnrCreatePassengerNameRecordV1Wire($apiDraft, []);
        $wire = $builder->stripOtaInternalKeysFromBookingWire($raw);
        $cpnr = is_array($wire['CreatePassengerNameRecordRQ'] ?? null) ? $wire['CreatePassengerNameRecordRQ'] : [];
        $ap = $cpnr['AirPrice'] ?? null;
        $this->assertIsArray($ap);
        $this->assertTrue(array_is_list($ap));
        $this->assertTrue((bool) data_get($cpnr, 'AirPrice.0.PriceRequestInformation.Retain'));
        $sum = $builder->summarizeTraditionalPnrWirePostBody($wire);
        $this->assertSame('array', $sum['wire_root_air_price_type'] ?? null);
        $this->assertSame(1, (int) ($sum['wire_root_air_price_count'] ?? 0));
        $this->assertTrue((bool) ($sum['wire_root_air_price_retain_present'] ?? false));
        $this->assertTrue($sum['wire_traditional_pnr_contract_valid']);

        $legacy = $wire;
        data_set($legacy, 'CreatePassengerNameRecordRQ.AirPrice', [
            'PriceRequestInformation' => ['Retain' => true],
        ]);
        $sumLegacy = $builder->summarizeTraditionalPnrWirePostBody($legacy);
        $this->assertSame('object', $sumLegacy['wire_root_air_price_type'] ?? null);
        $this->assertSame(1, (int) ($sumLegacy['wire_root_air_price_count'] ?? 0));
        $this->assertTrue((bool) ($sumLegacy['wire_root_air_price_retain_present'] ?? false));
        $this->assertFalse($sumLegacy['wire_traditional_pnr_contract_valid']);
        $this->assertContains('root_AirPrice_must_be_array', $sumLegacy['wire_invalid_traditional_pnr_contract_keys']);
    }

    public function test_b52_traditional_cpnr_post_processing_end_transaction_not_rq(): void
    {
        $booking = $this->seedWireTestBooking();
        config(['suppliers.sabre.ticketing_enabled' => false]);
        $booking->load(['passengers', 'contact']);
        $snapshot = is_array($booking->meta['normalized_offer_snapshot'] ?? null)
            ? $booking->meta['normalized_offer_snapshot'] : [];
        $this->assertNotSame([], $snapshot);
        $p = $booking->passengers->first();
        $this->assertNotNull($p);
        $c = $booking->contact;
        $this->assertNotNull($c);
        $expiry = $p->passport_expiry_date;
        $expiryStr = $expiry instanceof \DateTimeInterface ? $expiry->format('Y-m-d') : (string) $expiry;
        $passengerData = [
            'passengers' => [[
                'type' => 'ADT',
                'first_name' => (string) $p->first_name,
                'last_name' => (string) $p->last_name,
                'passport_number' => (string) $p->passport_number,
                'passport_issuing_country' => (string) $p->passport_issuing_country,
                'passport_expiry_date' => $expiryStr,
                'nationality' => (string) $p->nationality,
                'document_type' => (string) $p->document_type,
            ]],
            'contact' => [
                'email' => (string) $c->email,
                'phone' => (string) $c->phone,
            ],
        ];
        $builder = app(SabreBookingPayloadBuilder::class);
        $draft = $builder->buildInternalDraft($snapshot, $passengerData);
        $this->assertTrue($draft['_valid'] ?? false);
        $apiDraft = $draft;
        unset($apiDraft['_valid']);
        $raw = $builder->buildTraditionalPnrCreatePassengerNameRecordV1Wire($apiDraft, []);
        $wire = $builder->stripOtaInternalKeysFromBookingWire($raw);
        $cpnr = is_array($wire['CreatePassengerNameRecordRQ'] ?? null) ? $wire['CreatePassengerNameRecordRQ'] : [];
        $pp = is_array($cpnr['PostProcessing'] ?? null) ? $cpnr['PostProcessing'] : [];
        $this->assertArrayNotHasKey('EndTransactionRQ', $pp);
        $this->assertArrayHasKey('EndTransaction', $pp);
        $this->assertIsArray($pp['EndTransaction'] ?? null);
        $this->assertSame('OTA_WEB', (string) data_get($pp, 'EndTransaction.Source.ReceivedFrom'));
        $this->assertArrayNotHasKey('Ticketing', $pp['EndTransaction'] ?? []);
        $this->assertArrayNotHasKey('TicketingAgreement', $pp['EndTransaction'] ?? []);
        $this->assertArrayHasKey('RedisplayReservation', $pp);
        $this->assertIsArray($pp['RedisplayReservation'] ?? null);
        $ap = $cpnr['AirPrice'] ?? null;
        $this->assertIsArray($ap);
        $this->assertTrue(array_is_list($ap));
        $this->assertTrue((bool) data_get($cpnr, 'AirPrice.0.PriceRequestInformation.Retain'));
        $airBook = is_array($cpnr['AirBook'] ?? null) ? $cpnr['AirBook'] : [];
        $this->assertArrayNotHasKey('AirPrice', $airBook);
        $this->assertArrayNotHasKey('OTAFareBreakdownSummary', $airBook);
        $this->assertArrayNotHasKey('PriceQuoteInformation', $airBook);
        $fs = data_get($cpnr, 'AirBook.OriginDestinationInformation.FlightSegment');
        $rows = is_array($fs) ? (array_is_list($fs) ? $fs : [$fs]) : [];
        foreach ($rows as $seg) {
            if (is_array($seg)) {
                $this->assertArrayNotHasKey('CabinCode', $seg);
                $this->assertArrayNotHasKey('ClassOfService', $seg);
                $this->assertArrayNotHasKey('FareBasisCode', $seg);
                $this->assertArrayNotHasKey('Number', $seg);
                $this->assertArrayHasKey('NumberInParty', $seg);
                $this->assertIsString($seg['NumberInParty']);
            }
        }
        $sum = $builder->summarizeTraditionalPnrWirePostBody($wire);
        $this->assertTrue($sum['wire_post_processing_has_end_transaction'] ?? false);
        $this->assertFalse($sum['wire_post_processing_has_end_transaction_rq'] ?? true);
        $this->assertTrue($sum['wire_post_processing_has_redisplay_reservation'] ?? false);
        $this->assertTrue($sum['wire_traditional_pnr_contract_valid']);

        $bad = $wire;
        data_set($bad, 'CreatePassengerNameRecordRQ.PostProcessing.EndTransactionRQ', [
            'EndTransaction' => true,
            'Source' => ['ReceivedFrom' => 'OTA_WEB'],
        ]);
        $sumEt = $builder->summarizeTraditionalPnrWirePostBody($bad);
        $this->assertTrue($sumEt['wire_post_processing_has_end_transaction_rq'] ?? false);
        $this->assertFalse($sumEt['wire_traditional_pnr_contract_valid']);
        $this->assertContains('forbidden_PostProcessing_EndTransactionRQ', $sumEt['wire_invalid_traditional_pnr_contract_keys']);

        $noEt = $wire;
        data_forget($noEt, 'CreatePassengerNameRecordRQ.PostProcessing.EndTransaction');
        $sumNoEt = $builder->summarizeTraditionalPnrWirePostBody($noEt);
        $this->assertFalse($sumNoEt['wire_post_processing_has_end_transaction'] ?? true);
        $this->assertFalse($sumNoEt['wire_traditional_pnr_contract_valid']);
        $this->assertContains('missing_PostProcessing_EndTransaction', $sumNoEt['wire_invalid_traditional_pnr_contract_keys']);

        $svc = app(SabreBookingService::class);
        $preview = $svc->previewTripOrdersWireJsonForInspectCommand(
            $booking,
            SabreBookingPayloadBuilder::TRADITIONAL_PNR_CREATE_PASSENGER_NAME_RECORD_V1
        );
        $this->assertArrayNotHasKey('error', $preview);
        $this->assertFalse($preview['wire_post_processing_has_end_transaction_rq'] ?? true);
        $this->assertTrue($preview['wire_post_processing_has_end_transaction'] ?? false);
        $this->assertTrue($preview['wire_post_processing_has_redisplay_reservation'] ?? false);
        $this->assertFalse($preview['wire_ticketing_enabled'] ?? true);
        $wireJson = json_encode($preview['redacted_wire_request_body'] ?? [], JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString((string) $p->first_name, $wireJson);
        $this->assertStringNotContainsString('+92300', $wireJson);
    }

    public function test_b53_traditional_cpnr_remark_type_general_not_general_uppercase(): void
    {
        $booking = $this->seedWireTestBooking();
        config(['suppliers.sabre.ticketing_enabled' => false]);
        $booking->load(['passengers', 'contact']);
        $snapshot = is_array($booking->meta['normalized_offer_snapshot'] ?? null)
            ? $booking->meta['normalized_offer_snapshot'] : [];
        $this->assertNotSame([], $snapshot);
        $p = $booking->passengers->first();
        $this->assertNotNull($p);
        $c = $booking->contact;
        $this->assertNotNull($c);
        $expiry = $p->passport_expiry_date;
        $expiryStr = $expiry instanceof \DateTimeInterface ? $expiry->format('Y-m-d') : (string) $expiry;
        $passengerData = [
            'passengers' => [[
                'type' => 'ADT',
                'first_name' => (string) $p->first_name,
                'last_name' => (string) $p->last_name,
                'passport_number' => (string) $p->passport_number,
                'passport_issuing_country' => (string) $p->passport_issuing_country,
                'passport_expiry_date' => $expiryStr,
                'nationality' => (string) $p->nationality,
                'document_type' => (string) $p->document_type,
            ]],
            'contact' => [
                'email' => (string) $c->email,
                'phone' => (string) $c->phone,
            ],
        ];
        $builder = app(SabreBookingPayloadBuilder::class);
        $draft = $builder->buildInternalDraft($snapshot, $passengerData);
        $this->assertTrue($draft['_valid'] ?? false);
        $apiDraft = $draft;
        unset($apiDraft['_valid']);
        $raw = $builder->buildTraditionalPnrCreatePassengerNameRecordV1Wire($apiDraft, []);
        $wire = $builder->stripOtaInternalKeysFromBookingWire($raw);
        $cpnr = is_array($wire['CreatePassengerNameRecordRQ'] ?? null) ? $wire['CreatePassengerNameRecordRQ'] : [];
        $remarks = data_get($cpnr, 'SpecialReqDetails.AddRemark.RemarkInfo.Remark');
        $this->assertIsArray($remarks);
        $rows = array_is_list($remarks) ? $remarks : [$remarks];
        $this->assertNotEmpty($rows);
        $hasGeneral = false;
        foreach ($rows as $r) {
            $this->assertIsArray($r);
            $this->assertNotSame('GENERAL', $r['Type'] ?? null, 'Sabre enum must not use uppercase GENERAL');
            if (($r['Type'] ?? '') === 'General') {
                $hasGeneral = true;
            }
        }
        $this->assertTrue($hasGeneral);
        $wireJson = json_encode($wire, JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('"Type":"GENERAL"', $wireJson);
        $this->assertStringNotContainsString('"GENERAL"', $wireJson);
        $sum = $builder->summarizeTraditionalPnrWirePostBody($wire);
        $this->assertTrue($sum['wire_remark_type_enum_valid'] ?? false);
        $this->assertTrue($sum['wire_has_general_remark'] ?? false);
        $this->assertGreaterThan(0, (int) ($sum['wire_remarks_count'] ?? 0));
        $this->assertContains('General', $sum['wire_remark_type_values_sanitized'] ?? []);

        $bad = $wire;
        data_set($bad, 'CreatePassengerNameRecordRQ.SpecialReqDetails.AddRemark.RemarkInfo.Remark.0.Type', 'GENERAL');
        $sumBad = $builder->summarizeTraditionalPnrWirePostBody($bad);
        $this->assertFalse($sumBad['wire_remark_type_enum_valid'] ?? true);
        $this->assertContains('invalid_remark_Type_enum', $sumBad['wire_invalid_traditional_pnr_contract_keys']);
    }

    public function test_b54_traditional_cpnr_omits_special_service_service_adds_ttl_remark_and_augment_strips(): void
    {
        $booking = $this->seedWireTestBooking();
        config(['suppliers.sabre.ticketing_enabled' => false]);
        $booking->load(['passengers', 'contact']);
        $snapshot = is_array($booking->meta['normalized_offer_snapshot'] ?? null)
            ? $booking->meta['normalized_offer_snapshot'] : [];
        $this->assertNotSame([], $snapshot);
        $p = $booking->passengers->first();
        $this->assertNotNull($p);
        $c = $booking->contact;
        $this->assertNotNull($c);
        $expiry = $p->passport_expiry_date;
        $expiryStr = $expiry instanceof \DateTimeInterface ? $expiry->format('Y-m-d') : (string) $expiry;
        $passengerData = [
            'passengers' => [[
                'type' => 'ADT',
                'first_name' => (string) $p->first_name,
                'last_name' => (string) $p->last_name,
                'passport_number' => (string) $p->passport_number,
                'passport_issuing_country' => (string) $p->passport_issuing_country,
                'passport_expiry_date' => $expiryStr,
                'nationality' => (string) $p->nationality,
                'document_type' => (string) $p->document_type,
            ]],
            'contact' => [
                'email' => (string) $c->email,
                'phone' => (string) $c->phone,
            ],
        ];
        $builder = app(SabreBookingPayloadBuilder::class);
        $draft = $builder->buildInternalDraft($snapshot, $passengerData);
        $this->assertTrue($draft['_valid'] ?? false);
        $apiDraft = $draft;
        unset($apiDraft['_valid']);

        $hints = ['time_limit_iso' => '2026-06-15T12:00:00Z'];
        $raw = $builder->buildTraditionalPnrCreatePassengerNameRecordV1Wire($apiDraft, $hints);
        $wire = $builder->stripOtaInternalKeysFromBookingWire($raw);
        $cpnr = is_array($wire['CreatePassengerNameRecordRQ'] ?? null) ? $wire['CreatePassengerNameRecordRQ'] : [];
        $this->assertArrayNotHasKey('SpecialService', $cpnr['SpecialReqDetails'] ?? []);
        $wireJson = json_encode($wire, JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('"Service"', $wireJson);
        $this->assertStringNotContainsString('SpecialService', $wireJson);
        $remarks = data_get($cpnr, 'SpecialReqDetails.AddRemark.RemarkInfo.Remark');
        $this->assertIsArray($remarks);
        $rows = array_is_list($remarks) ? $remarks : [$remarks];
        $ttlFound = false;
        foreach ($rows as $r) {
            if (! is_array($r)) {
                continue;
            }
            if (($r['Type'] ?? '') === 'General' && str_starts_with((string) ($r['Text'] ?? ''), 'TTL ')) {
                $ttlFound = true;
            }
        }
        $this->assertTrue($ttlFound);

        $sum = $builder->summarizeTraditionalPnrWirePostBody($wire);
        $this->assertFalse($sum['wire_special_service_present'] ?? true);
        $this->assertFalse($sum['wire_special_service_has_service'] ?? true);
        $this->assertTrue($sum['wire_special_service_omitted'] ?? false);
        $this->assertTrue($sum['wire_add_remark_present'] ?? false);
        $this->assertTrue($sum['wire_traditional_pnr_contract_valid'] ?? false);

        $bad = $wire;
        data_set($bad, 'CreatePassengerNameRecordRQ.SpecialReqDetails.SpecialService', [
            'Service' => ['SSR_Code' => 'OTHS', 'Text' => 'X'],
        ]);
        $sumBad = $builder->summarizeTraditionalPnrWirePostBody($bad);
        $this->assertTrue($sumBad['wire_special_service_has_service'] ?? false);
        $this->assertFalse($sumBad['wire_traditional_pnr_contract_valid'] ?? true);
        $this->assertContains('forbidden_SpecialReqDetails_SpecialService_Service', $sumBad['wire_invalid_traditional_pnr_contract_keys']);

        $m = new \ReflectionMethod(SabreBookingPayloadBuilder::class, 'traditionalPnrV1AugmentCpnrBlock');
        $m->setAccessible(true);
        $cpnrPoisoned = [
            'SpecialReqDetails' => [
                'AddRemark' => [
                    'RemarkInfo' => [
                        'Remark' => [['Type' => 'General', 'Text' => 'KEEP']],
                    ],
                ],
                'SpecialService' => [
                    'Service' => ['SSR_Code' => 'OTHS', 'Text' => 'STRIP'],
                ],
            ],
            'TravelItineraryAddInfo' => [
                'CustomerInfo' => [
                    'PersonName' => [],
                    'ContactNumbers' => ['ContactNumber' => [['Phone' => '1', 'PhoneUseType' => 'H']]],
                    'Email' => [['Address' => 'x@y.z']],
                ],
            ],
            'AirBook' => [
                'OriginDestinationInformation' => [
                    'FlightSegment' => [[
                        'DepartureDateTime' => '2026-01-01T10:00:00',
                        'FlightNumber' => '100',
                        'NumberInParty' => '1',
                        'ResBookDesigCode' => 'Y',
                        'MarketingAirline' => ['Code' => 'XX', 'FlightNumber' => '100'],
                        'OriginLocation' => ['LocationCode' => 'AAA'],
                        'DestinationLocation' => ['LocationCode' => 'BBB'],
                    ]],
                ],
            ],
        ];
        $aug = $m->invoke($builder, $cpnrPoisoned, $apiDraft);
        $this->assertArrayNotHasKey('SpecialService', is_array($aug['SpecialReqDetails'] ?? null) ? $aug['SpecialReqDetails'] : []);
        $emAug = data_get($aug, 'TravelItineraryAddInfo.CustomerInfo.Email.0');
        $this->assertIsArray($emAug);
        $this->assertSame('TO', $emAug['Type'] ?? null);
    }

    public function test_b55_traditional_cpnr_agency_info_omits_telephone_augment_strips_and_customer_contact_unchanged(): void
    {
        $booking = $this->seedWireTestBooking();
        config([
            'suppliers.sabre.ticketing_enabled' => false,
            'suppliers.sabre.agency_phone' => '+15550002222',
        ]);
        $booking->load(['passengers', 'contact']);
        $snapshot = is_array($booking->meta['normalized_offer_snapshot'] ?? null)
            ? $booking->meta['normalized_offer_snapshot'] : [];
        $this->assertNotSame([], $snapshot);
        $p = $booking->passengers->first();
        $this->assertNotNull($p);
        $c = $booking->contact;
        $this->assertNotNull($c);
        $expiry = $p->passport_expiry_date;
        $expiryStr = $expiry instanceof \DateTimeInterface ? $expiry->format('Y-m-d') : (string) $expiry;
        $passengerData = [
            'passengers' => [[
                'type' => 'ADT',
                'first_name' => (string) $p->first_name,
                'last_name' => (string) $p->last_name,
                'passport_number' => (string) $p->passport_number,
                'passport_issuing_country' => (string) $p->passport_issuing_country,
                'passport_expiry_date' => $expiryStr,
                'nationality' => (string) $p->nationality,
                'document_type' => (string) $p->document_type,
            ]],
            'contact' => [
                'email' => (string) $c->email,
                'phone' => (string) $c->phone,
            ],
        ];
        $builder = app(SabreBookingPayloadBuilder::class);
        $draft = $builder->buildInternalDraft($snapshot, $passengerData);
        $this->assertTrue($draft['_valid'] ?? false);
        $apiDraft = $draft;
        unset($apiDraft['_valid']);
        $raw = $builder->buildTraditionalPnrCreatePassengerNameRecordV1Wire($apiDraft, []);
        $wire = $builder->stripOtaInternalKeysFromBookingWire($raw);
        $cpnr = is_array($wire['CreatePassengerNameRecordRQ'] ?? null) ? $wire['CreatePassengerNameRecordRQ'] : [];
        $agencyInfo = is_array(data_get($cpnr, 'TravelItineraryAddInfo.AgencyInfo')) ? data_get($cpnr, 'TravelItineraryAddInfo.AgencyInfo') : [];
        $this->assertNotSame([], $agencyInfo);
        $this->assertArrayNotHasKey('Telephone', $agencyInfo);
        $this->assertArrayHasKey('Ticketing', $agencyInfo);
        $this->assertNotNull(data_get($cpnr, 'TravelItineraryAddInfo.CustomerInfo.ContactNumbers'));
        $this->assertNotNull(data_get($cpnr, 'TravelItineraryAddInfo.CustomerInfo.Email'));
        $wireJson = json_encode($wire, JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('"Telephone"', $wireJson);
        $this->assertStringNotContainsString('+15550002222', $wireJson);

        $sum = $builder->summarizeTraditionalPnrWirePostBody($wire);
        $this->assertTrue($sum['wire_agency_info_present'] ?? false);
        $this->assertFalse($sum['wire_agency_info_has_telephone'] ?? true);
        $this->assertTrue($sum['wire_customer_info_has_contact_numbers'] ?? false);
        $this->assertTrue($sum['wire_customer_info_has_email'] ?? false);
        $this->assertTrue($sum['wire_traditional_pnr_contract_valid'] ?? false);

        $bad = $wire;
        data_set($bad, 'CreatePassengerNameRecordRQ.TravelItineraryAddInfo.AgencyInfo.Telephone', [
            'PhoneNumber' => '000',
        ]);
        $sumBad = $builder->summarizeTraditionalPnrWirePostBody($bad);
        $this->assertTrue($sumBad['wire_agency_info_has_telephone'] ?? false);
        $this->assertFalse($sumBad['wire_traditional_pnr_contract_valid'] ?? true);
        $this->assertContains('forbidden_TravelItineraryAddInfo_AgencyInfo_Telephone', $sumBad['wire_invalid_traditional_pnr_contract_keys']);

        $m = new \ReflectionMethod(SabreBookingPayloadBuilder::class, 'traditionalPnrV1AugmentCpnrBlock');
        $m->setAccessible(true);
        $cpnrPoisoned = [
            'TravelItineraryAddInfo' => [
                'AgencyInfo' => [
                    'Ticketing' => ['TicketType' => '7TAW'],
                    'Telephone' => ['PhoneNumber' => 'strip-me'],
                ],
                'CustomerInfo' => [
                    'PersonName' => [],
                    'ContactNumbers' => ['ContactNumber' => [['Phone' => '9', 'PhoneUseType' => 'H']]],
                    'Email' => [['Address' => 'a@b.c']],
                ],
            ],
            'AirBook' => [
                'OriginDestinationInformation' => [
                    'FlightSegment' => [[
                        'DepartureDateTime' => '2026-01-01T10:00:00',
                        'FlightNumber' => '100',
                        'NumberInParty' => '1',
                        'ResBookDesigCode' => 'Y',
                        'MarketingAirline' => ['Code' => 'XX', 'FlightNumber' => '100'],
                        'OriginLocation' => ['LocationCode' => 'AAA'],
                        'DestinationLocation' => ['LocationCode' => 'BBB'],
                    ]],
                ],
            ],
        ];
        $aug = $m->invoke($builder, $cpnrPoisoned, $apiDraft);
        $ai2 = is_array(data_get($aug, 'TravelItineraryAddInfo.AgencyInfo')) ? data_get($aug, 'TravelItineraryAddInfo.AgencyInfo') : [];
        $this->assertArrayNotHasKey('Telephone', $ai2);
        $this->assertArrayHasKey('Ticketing', $ai2);
    }

    public function test_b56_traditional_cpnr_customer_info_person_name_json_array_and_normalize_object(): void
    {
        $booking = $this->seedWireTestBooking();
        config(['suppliers.sabre.ticketing_enabled' => false]);
        $booking->load(['passengers', 'contact']);
        $snapshot = is_array($booking->meta['normalized_offer_snapshot'] ?? null)
            ? $booking->meta['normalized_offer_snapshot'] : [];
        $this->assertNotSame([], $snapshot);
        $p = $booking->passengers->first();
        $this->assertNotNull($p);
        $c = $booking->contact;
        $this->assertNotNull($c);
        $expiry = $p->passport_expiry_date;
        $expiryStr = $expiry instanceof \DateTimeInterface ? $expiry->format('Y-m-d') : (string) $expiry;
        $passengerData = [
            'passengers' => [
                [
                    'type' => 'ADT',
                    'first_name' => (string) $p->first_name,
                    'last_name' => (string) $p->last_name,
                    'passport_number' => (string) $p->passport_number,
                    'passport_issuing_country' => (string) $p->passport_issuing_country,
                    'passport_expiry_date' => $expiryStr,
                    'nationality' => (string) $p->nationality,
                    'document_type' => (string) $p->document_type,
                ],
                [
                    'type' => 'ADT',
                    'first_name' => 'SecondPax',
                    'last_name' => 'WireTest',
                    'passport_number' => 'S7654321',
                    'passport_issuing_country' => (string) $p->passport_issuing_country,
                    'passport_expiry_date' => $expiryStr,
                    'nationality' => (string) $p->nationality,
                    'document_type' => (string) $p->document_type,
                ],
            ],
            'contact' => [
                'email' => (string) $c->email,
                'phone' => (string) $c->phone,
            ],
        ];
        $builder = app(SabreBookingPayloadBuilder::class);
        $draft = $builder->buildInternalDraft($snapshot, $passengerData);
        $this->assertTrue($draft['_valid'] ?? false, (string) ($draft['code'] ?? 'draft_invalid'));
        $apiDraft = $draft;
        unset($apiDraft['_valid']);
        $raw = $builder->buildTraditionalPnrCreatePassengerNameRecordV1Wire($apiDraft, []);
        $wire = $builder->stripOtaInternalKeysFromBookingWire($raw);
        $cpnr = is_array($wire['CreatePassengerNameRecordRQ'] ?? null) ? $wire['CreatePassengerNameRecordRQ'] : [];
        $pn = data_get($cpnr, 'TravelItineraryAddInfo.CustomerInfo.PersonName');
        $this->assertIsArray($pn);
        $this->assertTrue(array_is_list($pn));
        $this->assertCount(2, $pn);
        $this->assertNotNull(data_get($cpnr, 'TravelItineraryAddInfo.CustomerInfo.ContactNumbers'));
        $this->assertNotNull(data_get($cpnr, 'TravelItineraryAddInfo.CustomerInfo.Email'));

        $sum = $builder->summarizeTraditionalPnrWirePostBody($wire);
        $this->assertSame('array', $sum['wire_customer_person_name_type'] ?? null);
        $this->assertSame(2, (int) ($sum['wire_customer_person_name_count'] ?? 0));
        $this->assertTrue($sum['wire_customer_person_name_array_valid'] ?? false);
        $this->assertTrue($sum['wire_traditional_pnr_contract_valid'] ?? false);

        $bad = $wire;
        data_set($bad, 'CreatePassengerNameRecordRQ.TravelItineraryAddInfo.CustomerInfo.PersonName', [
            'GivenName' => 'X',
            'Surname' => 'Y',
            'PassengerType' => 'ADT',
            'NameNumber' => '1.1',
        ]);
        $sumBad = $builder->summarizeTraditionalPnrWirePostBody($bad);
        $this->assertSame('object', $sumBad['wire_customer_person_name_type'] ?? null);
        $this->assertFalse($sumBad['wire_customer_person_name_array_valid'] ?? true);
        $this->assertContains('customer_info_PersonName_must_be_array', $sumBad['wire_invalid_traditional_pnr_contract_keys']);

        $mn = new \ReflectionMethod(SabreBookingPayloadBuilder::class, 'traditionalPnrNormalizeCustomerInfoPersonNameToArray');
        $mn->setAccessible(true);
        $wrapped = $mn->invoke($builder, [
            'PersonName' => [
                'GivenName' => 'Obj',
                'Surname' => 'Only',
                'PassengerType' => 'ADT',
                'NameNumber' => '1.1',
            ],
            'Email' => [['Address' => 'z@y.x']],
        ]);
        $pnWrap = $wrapped['PersonName'] ?? null;
        $this->assertIsArray($pnWrap);
        $this->assertTrue(array_is_list($pnWrap));
        $this->assertCount(1, $pnWrap);
        $this->assertIsArray($pnWrap[0] ?? null);
        $this->assertSame('Obj', $pnWrap[0]['GivenName'] ?? null);
    }

    public function test_b39_preview_redacted_trip_orders_command_routes_traditional_style(): void
    {
        $booking = $this->seedWireTestBooking();
        $svc = app(SabreBookingService::class);
        $out = $svc->previewRedactedTripOrdersCreateBookingForCommand(
            $booking,
            SabreBookingPayloadBuilder::TRADITIONAL_PNR_CREATE_PASSENGER_NAME_RECORD_V1
        );
        $this->assertSame(SabreBookingPayloadBuilder::TRADITIONAL_PNR_CREATE_PASSENGER_NAME_RECORD_V1, $out['payload_style'] ?? null);
        $this->assertArrayHasKey('CreatePassengerNameRecordRQ', $out);
    }

    public function test_b39_compare_booking_endpoints_403_shows_entitlement_hint(): void
    {
        $booking = $this->seedWireTestBooking();
        $cid = (int) data_get($booking->meta, 'supplier_connection_id');
        $conn = SupplierConnection::query()->findOrFail($cid);
        $conn->credentials = ['client_id' => 'test_client', 'client_secret' => 'test_secret'];
        $conn->save();

        config([
            'suppliers.sabre.booking_enabled' => true,
            'suppliers.sabre.booking_live_call_enabled' => true,
        ]);

        Http::fake(function (Request $request) {
            if (str_contains($request->url(), 'token')) {
                return Http::response(['access_token' => 'fake-token', 'expires_in' => 3600], 200);
            }

            return Http::response(['message' => 'forbidden'], 403);
        });

        Artisan::call('sabre:compare-booking-endpoints', [
            '--booking' => (string) $booking->id,
            '--send' => true,
            '--endpoint' => '/v2/passenger/create',
            '--style' => SabreBookingPayloadBuilder::TRADITIONAL_PNR_CREATE_PASSENGER_NAME_RECORD_V1,
        ]);
        $out = Artisan::output();
        $this->assertStringContainsString('http_status=403', $out);
        $this->assertStringContainsString('access_result=forbidden', $out);
        $this->assertStringContainsString('entitlement_hint=Endpoint reachable but credential not entitled or wrong product path.', $out);
        $this->assertSame(1, SupplierBookingAttempt::query()
            ->where('booking_id', $booking->id)
            ->where('action', 'compare_booking_endpoint')
            ->count());
    }

    public function test_b43_compare_send_preserves_query_string_in_post_url_and_attempt_summary(): void
    {
        $booking = $this->seedWireTestBooking();
        $cid = (int) data_get($booking->meta, 'supplier_connection_id');
        $conn = SupplierConnection::query()->findOrFail($cid);
        $conn->credentials = ['client_id' => 'test_client', 'client_secret' => 'test_secret'];
        $conn->base_url = 'https://example.sabre.test';
        $conn->save();

        config([
            'suppliers.sabre.booking_enabled' => true,
            'suppliers.sabre.booking_live_call_enabled' => true,
        ]);

        $seenUrl = '';
        $convId = null;
        Http::fake(function (Request $request) use (&$seenUrl, &$convId) {
            if (str_contains($request->url(), 'token')) {
                return Http::response(['access_token' => 'fake-token', 'expires_in' => 3600], 200);
            }
            $seenUrl = $request->url();
            $hdr = $request->header('Conversation-ID');
            $convId = is_array($hdr) ? ($hdr[0] ?? null) : $hdr;

            return Http::response([
                'CreatePassengerNameRecordRS' => [
                    'ApplicationResults' => [
                        'status' => 'Incomplete',
                        'Error' => [[
                            'SystemSpecificResults' => [[
                                'Message' => [
                                    ['code' => 'ERR.OTA.TEST', 'content' => 'Host validation excerpt'],
                                ],
                            ]],
                        ]],
                    ],
                ],
            ], 400);
        });

        Artisan::call('sabre:compare-booking-endpoints', [
            '--booking' => (string) $booking->id,
            '--send' => true,
            '--endpoint' => '/v2.5.0/passenger/records?mode=create',
            '--style' => SabreBookingPayloadBuilder::TRADITIONAL_PNR_CREATE_PASSENGER_NAME_RECORD_V1,
        ]);

        $this->assertStringContainsString('/v2.5.0/passenger/records?mode=create', $seenUrl);
        $this->assertIsString($convId);
        $this->assertStringStartsWith('ota-'.$booking->id.'-', $convId);
        $out = Artisan::output();
        $this->assertStringContainsString('http_status=400', $out);
        $this->assertStringContainsString('access_result=reachable_validation_error', $out);
        $this->assertStringContainsString('request_body_non_empty=true', $out);
        $this->assertStringContainsString('request_body_root_keys', $out);
        $this->assertStringContainsString('passenger_records_error_digest_present=true', $out);
        $this->assertStringContainsString('application_results_status=Incomplete', $out);
        $this->assertStringContainsString('ERR.OTA.TEST', $out);
        $att = SupplierBookingAttempt::query()
            ->where('booking_id', $booking->id)
            ->where('action', 'compare_booking_endpoint')
            ->orderByDesc('id')
            ->first();
        $this->assertNotNull($att);
        $ss = is_array($att->safe_summary) ? $att->safe_summary : [];
        $this->assertSame('/v2.5.0/passenger/records?mode=create', $ss['endpoint_path'] ?? null);
        $this->assertIsArray($ss['response_error_messages'] ?? null);
        $this->assertNotEmpty($ss['response_error_messages']);
        $this->assertTrue($ss['has_create_passenger_name_record_rq'] ?? false);
        $this->assertTrue($ss['passenger_records_error_digest_present'] ?? false);
    }

    public function test_b44_digest_passenger_records_application_results_extracts_messages(): void
    {
        $client = app(SabreBookingClient::class);
        $digest = $client->digestBookingResponseJsonForProbe([
            'CreatePassengerNameRecordRS' => [
                'ApplicationResults' => [
                    'status' => 'Incomplete',
                    'Error' => [[
                        'SystemSpecificResults' => [[
                            'Message' => [
                                ['code' => 'ERR.OTA.TEST', 'content' => 'Host validation excerpt'],
                            ],
                        ]],
                    ]],
                ],
            ],
        ]);
        $this->assertTrue($digest['passenger_records_error_digest_present']);
        $this->assertSame('Incomplete', $digest['application_results_status']);
        $this->assertContains('ERR.OTA.TEST', $digest['response_error_codes']);
        $hit = false;
        foreach ((array) ($digest['response_error_messages'] ?? []) as $m) {
            if (is_string($m) && str_contains($m, 'Host validation excerpt')) {
                $hit = true;
                break;
            }
        }
        $this->assertTrue($hit);
    }

    public function test_b44_digest_merges_errors_array_with_application_results(): void
    {
        $client = app(SabreBookingClient::class);
        $digest = $client->digestBookingResponseJsonForProbe([
            'errors' => [['code' => 'REST1', 'message' => 'bad json path']],
            'CreatePassengerNameRecordRS' => [
                'ApplicationResults' => [
                    'status' => 'NotProcessed',
                    'Error' => [[
                        'SystemSpecificResults' => [[
                            'Message' => [['code' => 'HOST.1', 'content' => 'host message']],
                        ]],
                    ]],
                ],
            ],
        ]);
        $this->assertContains('REST1', $digest['response_error_codes']);
        $this->assertContains('HOST.1', $digest['response_error_codes']);
        $this->assertTrue($digest['passenger_records_error_digest_present']);
    }

    public function test_b45_digest_passenger_records_top_level_rest_error_envelope(): void
    {
        $client = app(SabreBookingClient::class);
        $digest = $client->digestBookingResponseJsonForProbe([
            'errorCode' => 40012,
            'message' => 'Host validation blocked passenger record wire',
            'status' => 'NotProcessed',
            'type' => 'Application',
            'timeStamp' => '2026-01-15T12:00:00Z',
        ]);
        $this->assertTrue($digest['passenger_records_error_digest_present']);
        $this->assertContains('40012', $digest['response_error_codes']);
        $this->assertNotEmpty($digest['response_error_messages']);
        $this->assertSame('40012', $digest['response_top_level_error_code']);
        $this->assertStringContainsString('Host validation blocked', (string) ($digest['response_top_level_message'] ?? ''));
        $this->assertSame('NotProcessed', $digest['response_top_level_status']);
        $this->assertSame('Application', $digest['response_top_level_type']);
        $this->assertTrue($digest['response_timestamp_present']);
    }

    public function test_b45_digest_time_stamp_alone_does_not_set_passenger_records_digest_present(): void
    {
        $client = app(SabreBookingClient::class);
        $digest = $client->digestBookingResponseJsonForProbe([
            'timeStamp' => '2026-01-15T12:00:00Z',
        ]);
        $this->assertFalse($digest['passenger_records_error_digest_present']);
        $this->assertTrue($digest['response_timestamp_present']);
    }

    public function test_b45_digest_status_only_sets_passenger_records_digest_present(): void
    {
        $client = app(SabreBookingClient::class);
        $digest = $client->digestBookingResponseJsonForProbe([
            'status' => 'Failed',
        ]);
        $this->assertTrue($digest['passenger_records_error_digest_present']);
        $this->assertSame('Failed', $digest['response_top_level_status']);
    }

    public function test_b45_compare_send_passenger_records_top_level_rest_error_surfaces_in_output_and_safe_summary(): void
    {
        $booking = $this->seedWireTestBooking();
        $cid = (int) data_get($booking->meta, 'supplier_connection_id');
        $conn = SupplierConnection::query()->findOrFail($cid);
        $conn->credentials = ['client_id' => 'test_client', 'client_secret' => 'test_secret'];
        $conn->save();

        config([
            'suppliers.sabre.booking_enabled' => true,
            'suppliers.sabre.booking_live_call_enabled' => true,
        ]);

        Http::fake(function (Request $request) {
            if (str_contains($request->url(), 'token')) {
                return Http::response(['access_token' => 'fake-token', 'expires_in' => 3600], 200);
            }

            return Http::response([
                'errorCode' => 'PR.BAD.REQUEST',
                'message' => 'Structural validation on host side.',
                'status' => 'NotProcessed',
                'type' => 'Validation',
                'timeStamp' => '2026-05-14T10:00:00Z',
            ], 400);
        });

        Artisan::call('sabre:compare-booking-endpoints', [
            '--booking' => (string) $booking->id,
            '--send' => true,
            '--endpoint' => '/v2.5.0/passenger/records?mode=create',
            '--style' => SabreBookingPayloadBuilder::TRADITIONAL_PNR_CREATE_PASSENGER_NAME_RECORD_V1,
        ]);
        $out = Artisan::output();
        $this->assertStringContainsString('response_error_codes=', $out);
        $this->assertStringContainsString('PR.BAD.REQUEST', $out);
        $this->assertStringContainsString('response_error_messages=', $out);
        $this->assertStringContainsString('Structural validation on host side.', $out);
        $this->assertStringContainsString('response_top_level_error_code=PR.BAD.REQUEST', $out);
        $this->assertStringContainsString('response_top_level_message=Structural validation on host side.', $out);
        $this->assertStringContainsString('response_top_level_status=NotProcessed', $out);
        $this->assertStringContainsString('response_top_level_type=Validation', $out);
        $this->assertStringContainsString('response_timestamp_present=true', $out);
        $this->assertStringContainsString('passenger_records_error_digest_present=true', $out);
        $this->assertStringContainsString('ticketing_disabled=true', $out);
        $this->assertStringNotContainsString('fake-token', $out);

        $att = SupplierBookingAttempt::query()
            ->where('booking_id', $booking->id)
            ->where('action', 'compare_booking_endpoint')
            ->orderByDesc('id')
            ->first();
        $this->assertNotNull($att);
        $ss = is_array($att->safe_summary) ? $att->safe_summary : [];
        $this->assertSame('PR.BAD.REQUEST', $ss['response_top_level_error_code'] ?? null);
        $this->assertSame('Structural validation on host side.', $ss['response_top_level_message'] ?? null);
        $this->assertSame('NotProcessed', $ss['response_top_level_status'] ?? null);
        $this->assertSame('Validation', $ss['response_top_level_type'] ?? null);
        $this->assertTrue((bool) ($ss['response_timestamp_present'] ?? false));
        $this->assertTrue((bool) ($ss['request_body_non_empty'] ?? false));
        $this->assertTrue((bool) ($ss['wire_has_create_passenger_name_record_rq'] ?? false));
        $this->assertTrue((bool) ($ss['ticketing_disabled'] ?? false));
        $this->assertArrayNotHasKey('raw_response_body', $ss);
        $this->assertArrayNotHasKey('response_body', $ss);
    }

    public function test_b43_booking_capability_report_mode_create_non403_updates_recommended_action(): void
    {
        $booking = $this->seedWireTestBooking();
        $cid = (int) data_get($booking->meta, 'supplier_connection_id');
        SupplierBookingAttempt::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'supplier_connection_id' => $cid,
            'provider' => SupplierProvider::Sabre->value,
            'action' => 'compare_booking_endpoint',
            'status' => 'attempted',
            'error_code' => null,
            'error_message' => null,
            'supplier_reference' => null,
            'safe_summary' => [
                'source' => 'sabre_compare_booking_endpoints',
                'endpoint_path' => '/v2.5.0/passenger/records?mode=create',
                'http_status' => '422',
                'ticketing_disabled' => true,
            ],
            'attempted_by' => null,
            'attempted_at' => now(),
            'completed_at' => now(),
        ]);

        config([
            'suppliers.sabre.booking_path' => '/v1/trip/orders/createBooking',
            'suppliers.sabre.ticketing_enabled' => false,
        ]);

        Artisan::call('sabre:booking-capability-report', ['--booking' => (string) $booking->id]);
        $out = Artisan::output();
        $this->assertStringContainsString('/v2.5.0/passenger/records?mode=create => reachable_http_422', $out);
        $this->assertStringContainsString('Passenger Records POST /v2.5.0/passenger/records?mode=create returned a non-403 HTTP outcome', $out);
    }

    public function test_b40_booking_capability_report_lists_trip_orders_error_and_traditional_forbidden(): void
    {
        $booking = $this->seedWireTestBooking();
        $cid = (int) data_get($booking->meta, 'supplier_connection_id');
        config([
            'suppliers.sabre.booking_path' => '/v1/trip/orders/createBooking',
            'suppliers.sabre.agency_phone' => '+15550001111',
            'suppliers.sabre.ticketing_enabled' => false,
        ]);
        SupplierBookingAttempt::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'supplier_connection_id' => $cid,
            'provider' => SupplierProvider::Sabre->value,
            'action' => 'compare_trip_orders_createbooking_style',
            'status' => 'failed',
            'error_code' => null,
            'error_message' => null,
            'supplier_reference' => null,
            'safe_summary' => [
                'http_status' => '422',
                'wire_has_agency_phone' => true,
                'response_error_messages' => ['AGENCY_PHONE_MISSING office phone'],
            ],
            'attempted_by' => null,
            'attempted_at' => now(),
            'completed_at' => now(),
        ]);
        foreach ([
            '/v2/passengers/create',
            '/v2/passenger/create',
            '/v2.5.0/passenger/records',
            '/v2.4.0/passenger/records',
        ] as $ep) {
            SupplierBookingAttempt::query()->create([
                'agency_id' => $booking->agency_id,
                'booking_id' => $booking->id,
                'supplier_connection_id' => $cid,
                'provider' => SupplierProvider::Sabre->value,
                'action' => 'compare_booking_endpoint',
                'status' => 'forbidden',
                'error_code' => null,
                'error_message' => null,
                'supplier_reference' => null,
                'safe_summary' => [
                    'source' => 'sabre_compare_booking_endpoints',
                    'endpoint_path' => $ep,
                    'http_status' => '403',
                    'ticketing_disabled' => true,
                ],
                'attempted_by' => null,
                'attempted_at' => now(),
                'completed_at' => now(),
            ]);
        }

        Artisan::call('sabre:booking-capability-report', ['--booking' => (string) $booking->id]);
        $out = Artisan::output();
        $this->assertStringContainsString('trip_orders_latest_error=AGENCY_PHONE_MISSING', $out);
        $this->assertStringContainsString('trip_orders_likely_profile_level_agency_phone_issue=true', $out);
        $this->assertStringContainsString('/v2/passengers/create => forbidden', $out);
        $this->assertStringContainsString('/v2/passenger/create => forbidden', $out);
        $this->assertStringContainsString('/v2.5.0/passenger/records => forbidden', $out);
        $this->assertStringContainsString('/v2.4.0/passenger/records => forbidden', $out);
        $this->assertStringContainsString('/v2.5.0/passenger/records?mode=create => unknown_not_tested_after_b40', $out);
        $this->assertStringContainsString('/v2.4.0/passenger/records?mode=create => unknown_not_tested_after_b40', $out);
        $this->assertStringContainsString('/v2.3.0/passenger/records?mode=create => unknown_not_tested_after_b40', $out);
        $this->assertStringContainsString('ticketing_enabled=false', $out);
        $this->assertStringContainsString('search_available=true', $out);
        $this->assertStringContainsString('traditional_pnr_preview_valid=true', $out);
        $this->assertStringContainsString('traditional_pnr_endpoints_forbidden=true', $out);
        $this->assertStringContainsString('traditional_pnr_unknown_endpoint_count=3', $out);
        $this->assertStringContainsString('traditional_pnr_forbidden_endpoint_count=4', $out);
        $this->assertStringContainsString('Trip Orders is blocked by AGENCY_PHONE_MISSING; traditional Passenger/PNR endpoints are forbidden', $out);
        $this->assertStringContainsString('local_agency_phone_hints_found=', $out);
        $this->assertStringNotContainsString('+15550001111', $out);
    }

    public function test_b41_booking_capability_report_entitlement_unknown_not_tested_and_counts(): void
    {
        $booking = $this->seedWireTestBooking();
        config([
            'suppliers.sabre.booking_path' => '/v1/trip/orders/createBooking',
            'suppliers.sabre.ticketing_enabled' => false,
        ]);

        Artisan::call('sabre:booking-capability-report', ['--booking' => (string) $booking->id]);
        $out = Artisan::output();
        $this->assertStringContainsString('/v2/passengers/create => unknown_not_tested_after_b40', $out);
        $this->assertStringContainsString('/v2/passenger/create => unknown_not_tested_after_b40', $out);
        $this->assertStringContainsString('/v2.5.0/passenger/records => unknown_not_tested_after_b40', $out);
        $this->assertStringContainsString('/v2.4.0/passenger/records => unknown_not_tested_after_b40', $out);
        $this->assertStringContainsString('/v2.5.0/passenger/records?mode=create => unknown_not_tested_after_b40', $out);
        $this->assertStringContainsString('/v2.4.0/passenger/records?mode=create => unknown_not_tested_after_b40', $out);
        $this->assertStringContainsString('/v2.3.0/passenger/records?mode=create => unknown_not_tested_after_b40', $out);
        $this->assertStringContainsString('traditional_pnr_unknown_endpoint_count=7', $out);
        $this->assertStringContainsString('traditional_pnr_forbidden_endpoint_count=0', $out);
        $this->assertStringContainsString('traditional_pnr_endpoints_forbidden=false', $out);
        $this->assertStringContainsString('traditional_pnr_preview_valid=true', $out);
    }

    public function test_b41_booking_capability_report_single_forbidden_does_not_roll_up_all_forbidden(): void
    {
        $booking = $this->seedWireTestBooking();
        $cid = (int) data_get($booking->meta, 'supplier_connection_id');
        config([
            'suppliers.sabre.booking_path' => '/v1/trip/orders/createBooking',
            'suppliers.sabre.ticketing_enabled' => false,
        ]);
        SupplierBookingAttempt::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'supplier_connection_id' => $cid,
            'provider' => SupplierProvider::Sabre->value,
            'action' => 'compare_booking_endpoint',
            'status' => 'forbidden',
            'error_code' => null,
            'error_message' => null,
            'supplier_reference' => null,
            'safe_summary' => [
                'source' => 'sabre_compare_booking_endpoints',
                'endpoint_path' => '/v2/passengers/create',
                'http_status' => '403',
                'ticketing_disabled' => true,
            ],
            'attempted_by' => null,
            'attempted_at' => now(),
            'completed_at' => now(),
        ]);

        Artisan::call('sabre:booking-capability-report', ['--booking' => (string) $booking->id]);
        $out = Artisan::output();
        $this->assertStringContainsString('/v2/passengers/create => forbidden', $out);
        $this->assertStringContainsString('traditional_pnr_forbidden_endpoint_count=1', $out);
        $this->assertStringContainsString('traditional_pnr_unknown_endpoint_count=6', $out);
        $this->assertStringContainsString('traditional_pnr_endpoints_forbidden=false', $out);
    }

    public function test_b40_count_agency_phone_body_variant_failures(): void
    {
        $booking = $this->seedWireTestBooking();
        $cid = (int) data_get($booking->meta, 'supplier_connection_id');
        $styles = array_slice(SabreBookingPayloadBuilder::AGENCY_PHONE_BODY_VARIANT_COMPARE_STYLES, 0, 5);
        foreach ($styles as $st) {
            SupplierBookingAttempt::query()->create([
                'agency_id' => $booking->agency_id,
                'booking_id' => $booking->id,
                'supplier_connection_id' => $cid,
                'provider' => SupplierProvider::Sabre->value,
                'action' => 'compare_trip_orders_createbooking_style',
                'status' => 'failed',
                'error_code' => null,
                'error_message' => null,
                'supplier_reference' => null,
                'safe_summary' => [
                    'payload_style' => $st,
                    'agency_phone_error' => true,
                ],
                'attempted_by' => null,
                'attempted_at' => now(),
                'completed_at' => now(),
            ]);
        }
        $n = app(SabreBookingService::class)->countAgencyPhoneBodyVariantFailuresForBooking($booking);
        $this->assertSame(5, $n);
    }

    public function test_b40_compare_send_emits_blind_variant_warning_after_five_failures(): void
    {
        $booking = $this->seedWireTestBooking();
        $cid = (int) data_get($booking->meta, 'supplier_connection_id');
        $conn = SupplierConnection::query()->findOrFail($cid);
        $conn->credentials = ['client_id' => 'test_client', 'client_secret' => 'test_secret'];
        $conn->save();

        $styles = array_slice(SabreBookingPayloadBuilder::AGENCY_PHONE_BODY_VARIANT_COMPARE_STYLES, 0, 5);
        foreach ($styles as $st) {
            SupplierBookingAttempt::query()->create([
                'agency_id' => $booking->agency_id,
                'booking_id' => $booking->id,
                'supplier_connection_id' => $cid,
                'provider' => SupplierProvider::Sabre->value,
                'action' => 'compare_trip_orders_createbooking_style',
                'status' => 'failed',
                'error_code' => null,
                'error_message' => null,
                'supplier_reference' => null,
                'safe_summary' => [
                    'payload_style' => $st,
                    'agency_phone_error' => true,
                ],
                'attempted_by' => null,
                'attempted_at' => now(),
                'completed_at' => now(),
            ]);
        }

        config([
            'suppliers.sabre.booking_enabled' => true,
            'suppliers.sabre.booking_live_call_enabled' => true,
            'suppliers.sabre.booking_path' => '/v1/trip/orders/createBooking',
            'suppliers.sabre.booking_schema' => 'trip_orders_create_booking',
            'suppliers.sabre.agency_phone' => '+19997776660',
            'suppliers.sabre.agency_phone_country_code' => 'PK',
            'suppliers.sabre.agency_phone_type' => 'AGENCY',
        ]);

        $sixth = SabreBookingPayloadBuilder::AGENCY_PHONE_BODY_VARIANT_COMPARE_STYLES[5] ?? 'trip_orders_flight_details_sabre_agencyInfo_v1';

        Http::fake(function (Request $request) {
            if (str_contains($request->url(), '/v2/auth/token')) {
                return Http::response(['access_token' => 'fake-token', 'expires_in' => 3600], 200);
            }
            if (str_contains($request->url(), '/v1/trip/orders/createBooking')) {
                return Http::response([
                    'errors' => [['title' => 'AGENCY_PHONE_MISSING', 'detail' => 'Agency phone is needed']],
                ], 422);
            }

            return Http::response(['message' => 'unexpected'], 500);
        });

        $rows = app(SabreBookingService::class)->compareTripOrdersCreateBookingStylesForCommand($booking, true, $sixth);
        $this->assertNotEmpty($rows);
        $hit = false;
        foreach ($rows as $row) {
            if (! empty($row['blind_agency_phone_variant_warning'])) {
                $hit = true;
                $this->assertStringContainsString('Repeated agency phone body variants failed', (string) $row['blind_agency_phone_variant_warning']);
            }
        }
        $this->assertTrue($hit, 'Expected blind_agency_phone_variant_warning on sixth agency-variant send after five failures.');
    }

    public function test_b40_compare_attempt_marks_traditional_forbidden_when_all_four_paths_were_403(): void
    {
        $booking = $this->seedWireTestBooking();
        $cid = (int) data_get($booking->meta, 'supplier_connection_id');
        $conn = SupplierConnection::query()->findOrFail($cid);
        $conn->credentials = ['client_id' => 'test_client', 'client_secret' => 'test_secret'];
        $conn->save();

        foreach ([
            '/v2/passengers/create',
            '/v2/passenger/create',
            '/v2.5.0/passenger/records',
            '/v2.4.0/passenger/records',
        ] as $ep) {
            SupplierBookingAttempt::query()->create([
                'agency_id' => $booking->agency_id,
                'booking_id' => $booking->id,
                'supplier_connection_id' => $cid,
                'provider' => SupplierProvider::Sabre->value,
                'action' => 'compare_booking_endpoint',
                'status' => 'forbidden',
                'error_code' => null,
                'error_message' => null,
                'supplier_reference' => null,
                'safe_summary' => [
                    'endpoint_path' => $ep,
                    'http_status' => '403',
                    'ticketing_disabled' => true,
                ],
                'attempted_by' => null,
                'attempted_at' => now(),
                'completed_at' => now(),
            ]);
        }

        config([
            'suppliers.sabre.booking_enabled' => true,
            'suppliers.sabre.booking_live_call_enabled' => true,
            'suppliers.sabre.booking_path' => '/v1/trip/orders/createBooking',
            'suppliers.sabre.booking_schema' => 'trip_orders_create_booking',
            'suppliers.sabre.agency_phone' => '+19997776660',
            'suppliers.sabre.agency_phone_country_code' => 'PK',
            'suppliers.sabre.agency_phone_type' => 'AGENCY',
        ]);

        Http::fake(function (Request $request) {
            if (str_contains($request->url(), '/v2/auth/token')) {
                return Http::response(['access_token' => 'fake-token', 'expires_in' => 3600], 200);
            }
            if (str_contains($request->url(), '/v1/trip/orders/createBooking')) {
                return Http::response([
                    'errors' => [['title' => 'AGENCY_PHONE_MISSING', 'detail' => 'Agency phone is needed']],
                ], 422);
            }

            return Http::response(['message' => 'unexpected'], 500);
        });

        Artisan::call('sabre:compare-createbooking-styles', [
            '--booking' => (string) $booking->id,
            '--send' => true,
            '--style' => 'trip_orders_flight_details_sabre_v1',
        ]);

        $attempt = SupplierBookingAttempt::query()
            ->where('booking_id', $booking->id)
            ->where('action', 'compare_trip_orders_createbooking_style')
            ->orderByDesc('id')
            ->first();
        $this->assertNotNull($attempt);
        $ss = is_array($attempt->safe_summary) ? $attempt->safe_summary : [];
        $this->assertArrayHasKey('traditional_pnr_endpoints_forbidden', $ss);
        $this->assertTrue((bool) $ss['traditional_pnr_endpoints_forbidden']);
        $this->assertTrue((bool) ($ss['agency_phone_config_present'] ?? false));
        $this->assertTrue((bool) ($ss['wire_has_agency_phone'] ?? false));
    }

    public function test_b42_booking_capability_report_merges_expanded_endpoint_discovery_summary_when_json_file_exists(): void
    {
        $booking = $this->seedWireTestBooking();
        $path = storage_path('app/sabre-booking-endpoint-discovery.json');
        $prior = is_file($path) ? (string) file_get_contents($path) : null;
        @mkdir(dirname($path), 0755, true);
        file_put_contents($path, json_encode([
            'expanded_endpoint_discovery_summary' => [
                'total_tested' => 3,
                'ready_count' => 1,
                'validation_error_count' => 1,
                'forbidden_count' => 1,
                'not_found_count' => 0,
                'possible_create_candidates' => ['/v1/trip/orders/createBooking'],
            ],
        ], JSON_UNESCAPED_SLASHES));
        try {
            Artisan::call('sabre:booking-capability-report', ['--booking' => (string) $booking->id]);
            $out = Artisan::output();
            $this->assertStringContainsString('expanded_endpoint_discovery_summary=', $out);
            $this->assertStringContainsString('"total_tested":3', $out);
            $this->assertStringContainsString('/v1/trip/orders/createBooking', $out);
        } finally {
            if ($prior !== null) {
                file_put_contents($path, $prior);
            } elseif (is_file($path)) {
                unlink($path);
            }
        }
    }

    public function test_b57_digest_passenger_records_host_warning_modules_and_truncated_messages(): void
    {
        $client = app(SabreBookingClient::class);
        $digest = $client->digestBookingResponseJsonForProbe([
            'CreatePassengerNameRecordRS' => [
                'ApplicationResults' => [
                    'status' => 'Incomplete',
                    'Warning' => [[
                        'SystemSpecificResults' => [[
                            'Message' => [[
                                'code' => 'ERR.SP.PROVIDER_ERROR',
                                'content' => 'TravelItineraryAddInfoLLSRQ: .FRMT.NOT ENT BGNG WITH — EnhancedAirBookRQ: *NO FARES/RBD/CARRIER — phone 9230012345678901',
                            ]],
                        ]],
                    ]],
                ],
            ],
        ]);
        $this->assertTrue($digest['application_results_incomplete']);
        $this->assertContains('TravelItineraryAddInfoLLSRQ', $digest['host_warning_modules']);
        $this->assertContains('EnhancedAirBookRQ', $digest['host_warning_modules']);
        $this->assertContains('ERR.SP.PROVIDER_ERROR', $digest['host_warning_sabre_codes']);
        $joined = implode('|', (array) ($digest['host_warning_messages_truncated'] ?? []));
        $this->assertStringNotContainsString('9230012345678901', $joined);
        $this->assertStringContainsString('TravelItineraryAddInfoLLSRQ', $joined);
    }

    public function test_b58_traditional_cpnr_customer_info_email_type_to_matches_iati_key_union(): void
    {
        $booking = $this->seedWireTestBooking();
        config(['suppliers.sabre.ticketing_enabled' => false]);
        $booking->load(['passengers', 'contact']);
        $snapshot = is_array($booking->meta['normalized_offer_snapshot'] ?? null)
            ? $booking->meta['normalized_offer_snapshot'] : [];
        $this->assertNotSame([], $snapshot);
        $p = $booking->passengers->first();
        $this->assertNotNull($p);
        $c = $booking->contact;
        $this->assertNotNull($c);
        $expiry = $p->passport_expiry_date;
        $expiryStr = $expiry instanceof \DateTimeInterface ? $expiry->format('Y-m-d') : (string) $expiry;
        $passengerData = [
            'passengers' => [[
                'type' => 'ADT',
                'first_name' => (string) $p->first_name,
                'last_name' => (string) $p->last_name,
                'passport_number' => (string) $p->passport_number,
                'passport_issuing_country' => (string) $p->passport_issuing_country,
                'passport_expiry_date' => $expiryStr,
                'nationality' => (string) $p->nationality,
                'document_type' => (string) $p->document_type,
            ]],
            'contact' => [
                'email' => (string) $c->email,
                'phone' => (string) $c->phone,
            ],
        ];
        $builder = app(SabreBookingPayloadBuilder::class);
        $draft = $builder->buildInternalDraft($snapshot, $passengerData);
        $this->assertTrue($draft['_valid'] ?? false);
        $apiDraft = $draft;
        unset($apiDraft['_valid']);
        $raw = $builder->buildTraditionalPnrCreatePassengerNameRecordV1Wire($apiDraft, []);
        $wire = $builder->stripOtaInternalKeysFromBookingWire($raw);
        $cpnr = is_array($wire['CreatePassengerNameRecordRQ'] ?? null) ? $wire['CreatePassengerNameRecordRQ'] : [];
        $em = data_get($cpnr, 'TravelItineraryAddInfo.CustomerInfo.Email');
        $this->assertIsArray($em);
        $this->assertTrue(array_is_list($em));
        $this->assertSame('TO', (string) (data_get($em, '0.Type')));
        $this->assertNotSame('', trim((string) data_get($em, '0.Address')));

        $sum = $builder->summarizeTraditionalPnrWirePostBody($wire);
        $this->assertSame('array', $sum['wire_customer_email_type'] ?? null);
        $this->assertSame(1, (int) ($sum['wire_customer_email_count'] ?? 0));
        $this->assertTrue($sum['wire_customer_email_has_type'] ?? false);
        $this->assertSame(['TO'], $sum['wire_customer_email_type_values_sanitized'] ?? []);
        $this->assertTrue($sum['wire_customer_email_type_valid'] ?? false);
        $this->assertTrue($sum['wire_traditional_pnr_contract_valid'] ?? false);

        $inv = SabreTraditionalCpnrIatiWireStructureDiagnostic::cpnrKeyNameInventory($cpnr);
        $emInv = $inv['TravelItineraryAddInfo.CustomerInfo.Email'];
        $this->assertSame(['Address', 'Type'], $emInv['row_key_union_sorted']);

        $svc = app(SabreBookingService::class);
        $preview = $svc->previewTripOrdersWireJsonForInspectCommand(
            $booking,
            SabreBookingPayloadBuilder::TRADITIONAL_PNR_CREATE_PASSENGER_NAME_RECORD_V1
        );
        $this->assertArrayNotHasKey('error', $preview);
        $this->assertTrue($preview['wire_customer_email_type_valid'] ?? false);
        $red = json_encode($preview['redacted_wire_request_body'] ?? [], JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString((string) $c->email, $red);
        $this->assertStringContainsString('[redacted]', $red);

        $bad = $wire;
        data_set($bad, 'CreatePassengerNameRecordRQ.TravelItineraryAddInfo.CustomerInfo.Email.0.Type', 'CC');
        $sumBad = $builder->summarizeTraditionalPnrWirePostBody($bad);
        $this->assertFalse($sumBad['wire_customer_email_type_valid'] ?? true);
        $this->assertFalse($sumBad['wire_traditional_pnr_contract_valid'] ?? true);
        $this->assertContains('customer_info_Email_Type_must_be_TO', $sumBad['wire_invalid_traditional_pnr_contract_keys']);
    }

    public function test_b59_traditional_cpnr_root_air_price_passenger_type_optional_qualifiers_and_chd_maps_to_cnn(): void
    {
        $booking = $this->seedWireTestBooking();
        config(['suppliers.sabre.ticketing_enabled' => false]);
        $booking->load(['passengers', 'contact']);
        $snapshot = is_array($booking->meta['normalized_offer_snapshot'] ?? null)
            ? $booking->meta['normalized_offer_snapshot'] : [];
        $this->assertNotSame([], $snapshot);
        $p = $booking->passengers->first();
        $this->assertNotNull($p);
        $c = $booking->contact;
        $this->assertNotNull($c);
        $expiry = $p->passport_expiry_date;
        $expiryStr = $expiry instanceof \DateTimeInterface ? $expiry->format('Y-m-d') : (string) $expiry;
        $passengerData = [
            'passengers' => [
                [
                    'type' => 'ADT',
                    'first_name' => (string) $p->first_name,
                    'last_name' => (string) $p->last_name,
                    'passport_number' => (string) $p->passport_number,
                    'passport_issuing_country' => (string) $p->passport_issuing_country,
                    'passport_expiry_date' => $expiryStr,
                    'nationality' => (string) $p->nationality,
                    'document_type' => (string) $p->document_type,
                ],
                [
                    'passenger_type' => 'child',
                    'first_name' => 'Junior',
                    'last_name' => 'WireTest',
                    'passport_number' => 'CHILD123',
                    'passport_issuing_country' => 'PK',
                    'passport_expiry_date' => '2032-01-01',
                    'nationality' => 'PK',
                    'document_type' => 'passport',
                ],
            ],
            'contact' => [
                'email' => (string) $c->email,
                'phone' => (string) $c->phone,
            ],
        ];
        $builder = app(SabreBookingPayloadBuilder::class);
        $draft = $builder->buildInternalDraft($snapshot, $passengerData);
        $this->assertTrue($draft['_valid'] ?? false);
        $apiDraft = $draft;
        unset($apiDraft['_valid']);
        $raw = $builder->buildTraditionalPnrCreatePassengerNameRecordV1Wire($apiDraft, []);
        $wire = $builder->stripOtaInternalKeysFromBookingWire($raw);
        $cpnr = is_array($wire['CreatePassengerNameRecordRQ'] ?? null) ? $wire['CreatePassengerNameRecordRQ'] : [];
        $this->assertTrue((bool) data_get($cpnr, 'AirPrice.0.PriceRequestInformation.Retain'));
        $pq = data_get($cpnr, 'AirPrice.0.PriceRequestInformation.OptionalQualifiers.PricingQualifiers');
        $this->assertIsArray($pq);
        $this->assertArrayNotHasKey('Brand', $pq);
        $pt = $pq['PassengerType'] ?? null;
        $this->assertIsArray($pt);
        $this->assertTrue(array_is_list($pt));
        $byCode = [];
        foreach ($pt as $row) {
            if (is_array($row) && isset($row['Code'])) {
                $byCode[(string) $row['Code']] = $row['Quantity'] ?? null;
            }
        }
        $this->assertSame('1', $byCode['ADT'] ?? null);
        $this->assertSame('1', $byCode['CNN'] ?? null);
        $this->assertIsString($byCode['ADT'] ?? null);
        $this->assertIsString($byCode['CNN'] ?? null);
        $this->assertSame('TO', (string) data_get($cpnr, 'TravelItineraryAddInfo.CustomerInfo.Email.0.Type'));

        $sum = $builder->summarizeTraditionalPnrWirePostBody($wire);
        $this->assertTrue($sum['wire_air_price_has_optional_qualifiers'] ?? false);
        $this->assertTrue($sum['wire_air_price_has_pricing_qualifiers'] ?? false);
        $this->assertSame(2, (int) ($sum['wire_air_price_passenger_type_count'] ?? 0));
        $this->assertSame(['ADT', 'CNN'], $sum['wire_air_price_passenger_type_codes_sanitized'] ?? null);
        $this->assertTrue($sum['wire_air_price_passenger_type_quantities_are_strings'] ?? false);
        $this->assertTrue($sum['wire_air_price_passenger_type_contract_valid'] ?? false);
        $this->assertTrue($sum['wire_iati_airprice_passenger_type_delta_closed'] ?? false);
        $this->assertTrue($sum['wire_traditional_pnr_contract_valid'] ?? false);

        $inv = SabreTraditionalCpnrIatiWireStructureDiagnostic::cpnrKeyNameInventory($cpnr);
        $apInv = $inv['AirPrice'] ?? [];
        $this->assertContains('OptionalQualifiers', $apInv['price_request_information_keys'] ?? []);
        $this->assertContains('PricingQualifiers', $apInv['optional_qualifiers_keys'] ?? []);
        $this->assertContains('PassengerType', $apInv['optional_qualifiers_pricing_qualifiers_keys'] ?? []);

        $bad = $wire;
        data_set($bad, 'CreatePassengerNameRecordRQ.AirPrice.0.PriceRequestInformation.OptionalQualifiers.PricingQualifiers.PassengerType.0.Quantity', 1);
        $sumBad = $builder->summarizeTraditionalPnrWirePostBody($bad);
        $this->assertFalse($sumBad['wire_air_price_passenger_type_quantities_are_strings'] ?? true);
        $this->assertFalse($sumBad['wire_air_price_passenger_type_contract_valid'] ?? true);
        $this->assertContains('air_price_passenger_type_quantity_not_string', $sumBad['wire_invalid_traditional_pnr_contract_keys']);

        $svc = app(SabreBookingService::class);
        $preview = $svc->previewTripOrdersWireJsonForInspectCommand(
            $booking,
            SabreBookingPayloadBuilder::TRADITIONAL_PNR_CREATE_PASSENGER_NAME_RECORD_V1
        );
        $this->assertArrayNotHasKey('error', $preview);
        $this->assertTrue($preview['wire_air_price_passenger_type_contract_valid'] ?? false);
    }

    public function test_b60_traditional_cpnr_segment_sell_context_and_offer_diagnostics_safe(): void
    {
        $booking = $this->seedWireTestBooking();
        config(['suppliers.sabre.ticketing_enabled' => false]);
        $booking->load(['passengers', 'contact']);
        $snapshot = is_array($booking->meta['normalized_offer_snapshot'] ?? null)
            ? $booking->meta['normalized_offer_snapshot'] : [];
        $this->assertNotSame([], $snapshot);
        $p = $booking->passengers->first();
        $this->assertNotNull($p);
        $c = $booking->contact;
        $this->assertNotNull($c);
        $expiry = $p->passport_expiry_date;
        $expiryStr = $expiry instanceof \DateTimeInterface ? $expiry->format('Y-m-d') : (string) $expiry;
        $passengerData = [
            'passengers' => [[
                'type' => 'ADT',
                'first_name' => (string) $p->first_name,
                'last_name' => (string) $p->last_name,
                'passport_number' => (string) $p->passport_number,
                'passport_issuing_country' => (string) $p->passport_issuing_country,
                'passport_expiry_date' => $expiryStr,
                'nationality' => (string) $p->nationality,
                'document_type' => (string) $p->document_type,
            ]],
            'contact' => [
                'email' => (string) $c->email,
                'phone' => (string) $c->phone,
            ],
        ];
        $builder = app(SabreBookingPayloadBuilder::class);
        $draft = $builder->buildInternalDraft($snapshot, $passengerData);
        $this->assertTrue($draft['_valid'] ?? false);
        $apiDraft = $draft;
        unset($apiDraft['_valid']);
        $raw = $builder->buildTraditionalPnrCreatePassengerNameRecordV1Wire($apiDraft, []);
        $wire = $builder->stripOtaInternalKeysFromBookingWire($raw);
        $cpnr = is_array($wire['CreatePassengerNameRecordRQ'] ?? null) ? $wire['CreatePassengerNameRecordRQ'] : [];

        $sumWireOnly = $builder->summarizeTraditionalPnrWirePostBody($wire);
        $this->assertFalse($sumWireOnly['wire_offer_snapshot_present'] ?? true);
        $this->assertTrue($sumWireOnly['wire_segment_sell_context_all_have_marketing_airline'] ?? false);
        $this->assertTrue($sumWireOnly['wire_segment_sell_context_all_have_flight_number'] ?? false);
        $this->assertTrue($sumWireOnly['wire_segment_sell_context_all_have_res_book_desig_code'] ?? false);
        $this->assertTrue($sumWireOnly['wire_segment_sell_context_all_have_departure_datetime'] ?? false);
        $this->assertTrue($sumWireOnly['wire_segment_sell_context_all_have_origin_destination'] ?? false);
        $this->assertTrue($sumWireOnly['wire_segment_sell_context_all_required_present'] ?? false);
        $this->assertGreaterThanOrEqual(1, (int) ($sumWireOnly['wire_segment_sell_context_count'] ?? 0));
        $this->assertSame((int) ($sumWireOnly['wire_segment_count'] ?? 0), (int) ($sumWireOnly['wire_segment_sell_context_count'] ?? -1));

        $metaFresh = array_merge(is_array($booking->meta) ? $booking->meta : [], [
            'sabre_search_completed_at' => now()->subMinutes(10)->toIso8601String(),
        ]);
        $sumMeta = $builder->summarizeTraditionalPnrWirePostBody($wire, $metaFresh);
        $this->assertTrue($sumMeta['wire_offer_snapshot_present'] ?? false);
        $this->assertNotNull($sumMeta['wire_offer_snapshot_age_minutes'] ?? null);
        $this->assertSame('captured_under_30m', (string) ($sumMeta['wire_offer_snapshot_age_bucket'] ?? ''));

        $snapExpired = array_merge($snapshot, ['expires_at' => now()->subHours(2)->toIso8601String()]);
        $sumExpired = $builder->summarizeTraditionalPnrWirePostBody($wire, [
            'normalized_offer_snapshot' => $snapExpired,
        ]);
        $this->assertNotNull($sumExpired['wire_offer_snapshot_age_minutes'] ?? null);
        $this->assertStringStartsWith('expired_', (string) ($sumExpired['wire_offer_snapshot_age_bucket'] ?? ''));

        $metaBrand = [
            'normalized_offer_snapshot' => array_merge($snapshot, [
                'brand_program_keys' => ['tier' => 'redacted-in-real-row'],
                'raw_payload' => ['sabre_shop_identifiers' => ['itinerary_id' => 'secret']],
            ]),
        ];
        $sumBrand = $builder->summarizeTraditionalPnrWirePostBody($wire, $metaBrand);
        $this->assertTrue($sumBrand['wire_offer_has_raw_sabre_identifiers'] ?? false);
        $this->assertTrue($sumBrand['wire_offer_has_brand_candidates'] ?? false);
        $this->assertContains('brand_program_keys', $sumBrand['wire_brand_candidate_keys_sanitized'] ?? []);

        $enc = json_encode($sumMeta, JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString((string) $p->first_name, $enc);
        $this->assertStringNotContainsString((string) $c->email, $enc);
        $this->assertStringNotContainsString((string) $p->passport_number, $enc);

        $diff = SabreTraditionalCpnrIatiWireStructureDiagnostic::analyze($cpnr);
        $this->assertArrayHasKey('b60_post_b59_residual_hypotheses', $diff);
        $this->assertNotEmpty($diff['b60_post_b59_residual_hypotheses']);
    }

    public function test_b61_traditional_cpnr_airbook_retry_redisplay_gated_off_absent_on_wire(): void
    {
        config([
            'suppliers.sabre.ticketing_enabled' => false,
            'suppliers.sabre.traditional_cpnr_airbook_retry_redisplay' => false,
        ]);
        $booking = $this->seedWireTestBooking();
        $booking->load(['passengers', 'contact']);
        $snapshot = is_array($booking->meta['normalized_offer_snapshot'] ?? null)
            ? $booking->meta['normalized_offer_snapshot'] : [];
        $this->assertNotSame([], $snapshot);
        $p = $booking->passengers->first();
        $c = $booking->contact;
        $this->assertNotNull($p);
        $this->assertNotNull($c);
        $expiry = $p->passport_expiry_date;
        $expiryStr = $expiry instanceof \DateTimeInterface ? $expiry->format('Y-m-d') : (string) $expiry;
        $passengerData = [
            'passengers' => [[
                'type' => 'ADT',
                'first_name' => (string) $p->first_name,
                'last_name' => (string) $p->last_name,
                'passport_number' => (string) $p->passport_number,
                'passport_issuing_country' => (string) $p->passport_issuing_country,
                'passport_expiry_date' => $expiryStr,
                'nationality' => (string) $p->nationality,
                'document_type' => (string) $p->document_type,
            ]],
            'contact' => [
                'email' => (string) $c->email,
                'phone' => (string) $c->phone,
            ],
        ];
        $builder = app(SabreBookingPayloadBuilder::class);
        $draft = $builder->buildInternalDraft($snapshot, $passengerData);
        $this->assertTrue($draft['_valid'] ?? false);
        $apiDraft = $draft;
        unset($apiDraft['_valid']);
        $wire = $builder->stripOtaInternalKeysFromBookingWire(
            $builder->buildTraditionalPnrCreatePassengerNameRecordV1Wire($apiDraft, [])
        );
        $air = is_array(data_get($wire, 'CreatePassengerNameRecordRQ.AirBook')) ? data_get($wire, 'CreatePassengerNameRecordRQ.AirBook') : [];
        $this->assertArrayNotHasKey('RetryRebook', $air);
        $this->assertArrayNotHasKey('RedisplayReservation', $air);
        $sum = $builder->summarizeTraditionalPnrWirePostBody($wire);
        $this->assertFalse($sum['wire_airbook_retry_redisplay_enabled'] ?? true);
        $this->assertFalse($sum['wire_airbook_has_retry_rebook'] ?? true);
        $this->assertFalse($sum['wire_airbook_has_redisplay_reservation'] ?? true);
        $this->assertFalse($sum['wire_airbook_retry_rebook_num_attempts_present'] ?? true);
        $this->assertFalse($sum['wire_airbook_retry_rebook_wait_interval_present'] ?? true);
        $this->assertFalse($sum['wire_airbook_redisplay_num_attempts_present'] ?? true);
        $this->assertFalse($sum['wire_airbook_redisplay_wait_interval_present'] ?? true);
        $this->assertSame('missing', $sum['wire_airbook_retry_rebook_num_attempts_type'] ?? '');
        $this->assertSame('missing', $sum['wire_airbook_retry_rebook_wait_interval_type'] ?? '');
        $this->assertSame('missing', $sum['wire_airbook_redisplay_num_attempts_type'] ?? '');
        $this->assertSame('missing', $sum['wire_airbook_redisplay_wait_interval_type'] ?? '');
        $this->assertFalse($sum['wire_airbook_retry_rebook_has_option'] ?? true);
        $this->assertSame('missing', $sum['wire_airbook_retry_rebook_option_type'] ?? '');
        $this->assertTrue($sum['wire_airbook_retry_rebook_contract_valid'] ?? false);
        $this->assertTrue($sum['wire_airbook_retry_redisplay_numeric_contract_valid'] ?? false);
        $this->assertTrue($sum['wire_traditional_pnr_contract_valid'] ?? false);
    }

    public function test_b61_traditional_cpnr_airbook_retry_redisplay_gated_on_present_and_contract_valid(): void
    {
        config([
            'suppliers.sabre.ticketing_enabled' => false,
            'suppliers.sabre.traditional_cpnr_airbook_retry_redisplay' => true,
        ]);
        $booking = $this->seedWireTestBooking();
        $booking->load(['passengers', 'contact']);
        $snapshot = is_array($booking->meta['normalized_offer_snapshot'] ?? null)
            ? $booking->meta['normalized_offer_snapshot'] : [];
        $this->assertNotSame([], $snapshot);
        $p = $booking->passengers->first();
        $c = $booking->contact;
        $this->assertNotNull($p);
        $this->assertNotNull($c);
        $expiry = $p->passport_expiry_date;
        $expiryStr = $expiry instanceof \DateTimeInterface ? $expiry->format('Y-m-d') : (string) $expiry;
        $passengerData = [
            'passengers' => [[
                'type' => 'ADT',
                'first_name' => (string) $p->first_name,
                'last_name' => (string) $p->last_name,
                'passport_number' => (string) $p->passport_number,
                'passport_issuing_country' => (string) $p->passport_issuing_country,
                'passport_expiry_date' => $expiryStr,
                'nationality' => (string) $p->nationality,
                'document_type' => (string) $p->document_type,
            ]],
            'contact' => [
                'email' => (string) $c->email,
                'phone' => (string) $c->phone,
            ],
        ];
        $builder = app(SabreBookingPayloadBuilder::class);
        $draft = $builder->buildInternalDraft($snapshot, $passengerData);
        $this->assertTrue($draft['_valid'] ?? false);
        $apiDraft = $draft;
        unset($apiDraft['_valid']);
        $wire = $builder->stripOtaInternalKeysFromBookingWire(
            $builder->buildTraditionalPnrCreatePassengerNameRecordV1Wire($apiDraft, [])
        );
        $cpnr = is_array($wire['CreatePassengerNameRecordRQ'] ?? null) ? $wire['CreatePassengerNameRecordRQ'] : [];
        $air = is_array($cpnr['AirBook'] ?? null) ? $cpnr['AirBook'] : [];
        $this->assertArrayHasKey('RetryRebook', $air);
        $this->assertArrayHasKey('RedisplayReservation', $air);
        $rr = is_array($air['RetryRebook'] ?? null) ? $air['RetryRebook'] : [];
        $rd = is_array($air['RedisplayReservation'] ?? null) ? $air['RedisplayReservation'] : [];
        $this->assertArrayHasKey('Option', $rr);
        $this->assertTrue($rr['Option'] ?? null);
        $this->assertIsBool($rr['Option'] ?? null);
        $this->assertSame(3, $rr['NumAttempts'] ?? null);
        $this->assertSame(1000, $rr['WaitInterval'] ?? null);
        $this->assertSame(3, $rd['NumAttempts'] ?? null);
        $this->assertSame(1000, $rd['WaitInterval'] ?? null);
        $this->assertIsInt($rr['NumAttempts'] ?? null);
        $this->assertIsInt($rr['WaitInterval'] ?? null);
        $this->assertIsInt($rd['NumAttempts'] ?? null);
        $this->assertIsInt($rd['WaitInterval'] ?? null);
        $sum = $builder->summarizeTraditionalPnrWirePostBody($wire);
        $this->assertTrue($sum['wire_airbook_retry_redisplay_enabled'] ?? false);
        $this->assertTrue($sum['wire_airbook_has_retry_rebook'] ?? false);
        $this->assertTrue($sum['wire_airbook_has_redisplay_reservation'] ?? false);
        $this->assertTrue($sum['wire_airbook_retry_rebook_num_attempts_present'] ?? false);
        $this->assertTrue($sum['wire_airbook_retry_rebook_wait_interval_present'] ?? false);
        $this->assertTrue($sum['wire_airbook_redisplay_num_attempts_present'] ?? false);
        $this->assertTrue($sum['wire_airbook_redisplay_wait_interval_present'] ?? false);
        $this->assertTrue($sum['wire_airbook_retry_rebook_has_option'] ?? false);
        $this->assertSame('boolean', $sum['wire_airbook_retry_rebook_option_type'] ?? '');
        $this->assertTrue($sum['wire_airbook_retry_rebook_contract_valid'] ?? false);
        $this->assertSame('integer', $sum['wire_airbook_retry_rebook_num_attempts_type'] ?? '');
        $this->assertSame('integer', $sum['wire_airbook_retry_rebook_wait_interval_type'] ?? '');
        $this->assertSame('integer', $sum['wire_airbook_redisplay_num_attempts_type'] ?? '');
        $this->assertSame('integer', $sum['wire_airbook_redisplay_wait_interval_type'] ?? '');
        $this->assertTrue($sum['wire_airbook_retry_redisplay_numeric_contract_valid'] ?? false);
        $this->assertTrue($sum['wire_traditional_pnr_contract_valid'] ?? false);
        $diff = SabreTraditionalCpnrIatiWireStructureDiagnostic::analyze($cpnr);
        $this->assertTrue($diff['b61_airbook_retry_redisplay_config_on'] ?? false);
        $this->assertTrue($diff['b61_airbook_retry_redisplay_ota_experiment_satisfied'] ?? false);
        $this->assertTrue($diff['b61_key_paths_only_in_iati_template_airbook_retry_redisplay_suppressed'] ?? false);
        foreach ($diff['key_paths_only_in_iati_template'] ?? [] as $p) {
            $this->assertFalse(str_starts_with((string) $p, 'AirBook.RetryRebook'));
            $this->assertFalse(str_starts_with((string) $p, 'AirBook.RedisplayReservation'));
        }
        $svc = app(SabreBookingService::class);
        $preview = $svc->previewTripOrdersWireJsonForInspectCommand(
            $booking,
            SabreBookingPayloadBuilder::TRADITIONAL_PNR_CREATE_PASSENGER_NAME_RECORD_V1
        );
        $this->assertArrayNotHasKey('error', $preview);
        $this->assertTrue($preview['wire_airbook_has_retry_rebook'] ?? false);
        $this->assertTrue($preview['wire_airbook_has_redisplay_reservation'] ?? false);
        $this->assertTrue($preview['wire_airbook_retry_redisplay_numeric_contract_valid'] ?? false);
        $this->assertTrue($preview['wire_airbook_retry_rebook_contract_valid'] ?? false);
        $this->assertFalse($preview['wire_ticketing_enabled'] ?? true);
        $m = new \ReflectionMethod(SabreBookingService::class, 'traditionalPnrWireInspectPreviewMatchesContract');
        $m->setAccessible(true);
        $this->assertTrue($m->invoke($svc, $preview));
    }

    public function test_b62_summarize_envelope_maps_traditional_cpnr_wire(): void
    {
        config([
            'suppliers.sabre.ticketing_enabled' => false,
            'suppliers.sabre.traditional_cpnr_airbook_retry_redisplay' => false,
        ]);
        $booking = $this->seedWireTestBooking();
        $booking->load(['passengers', 'contact']);
        $snapshot = is_array($booking->meta['normalized_offer_snapshot'] ?? null)
            ? $booking->meta['normalized_offer_snapshot'] : [];
        $p = $booking->passengers->first();
        $c = $booking->contact;
        $this->assertNotNull($p);
        $this->assertNotNull($c);
        $expiry = $p->passport_expiry_date;
        $expiryStr = $expiry instanceof \DateTimeInterface ? $expiry->format('Y-m-d') : (string) $expiry;
        $passengerData = [
            'passengers' => [[
                'type' => 'ADT',
                'first_name' => (string) $p->first_name,
                'last_name' => (string) $p->last_name,
                'passport_number' => (string) $p->passport_number,
                'passport_issuing_country' => (string) $p->passport_issuing_country,
                'passport_expiry_date' => $expiryStr,
                'nationality' => (string) $p->nationality,
                'document_type' => (string) $p->document_type,
            ]],
            'contact' => [
                'email' => (string) $c->email,
                'phone' => (string) $c->phone,
            ],
        ];
        $builder = app(SabreBookingPayloadBuilder::class);
        $draft = $builder->buildInternalDraft($snapshot, $passengerData);
        $this->assertTrue($draft['_valid'] ?? false);
        $apiDraft = $draft;
        unset($apiDraft['_valid']);
        $envelope = $builder->buildTraditionalPnrCreatePassengerNameRecordV1Wire($apiDraft, []);
        $diag = $builder->summarizeEnvelopeForDiagnostics($envelope);
        $this->assertSame(SabreBookingPayloadBuilder::TRADITIONAL_PNR_CREATE_PASSENGER_NAME_RECORD_V1, $diag['payload_schema'] ?? null);
        $this->assertSame('rest_json_passenger_records_cpnr', $diag['booking_transport'] ?? null);
        $this->assertTrue($diag['wire_traditional_pnr_contract_valid'] ?? false);
        $this->assertTrue($diag['wire_has_create_passenger_name_record_rq'] ?? false);
    }

    public function test_b62_passenger_records_schema_env_alias_normalizes_to_cpnr(): void
    {
        config(['suppliers.sabre.booking_path' => '/v2.5.0/passenger/records?mode=create']);
        config(['suppliers.sabre.booking_schema' => 'passenger_records_create_pnr']);
        $svc = app(SabreBookingService::class);
        $this->assertSame('create_passenger_name_record', $svc->effectiveSabreBookingSchema());
    }

    public function test_b75_segment_sell_diagnostics_two_leg_route_continuity_and_no_pii(): void
    {
        $booking = $this->seedWireTestBookingTwoSegment();
        $cid = (int) data_get($booking->meta, 'supplier_connection_id');
        config([
            'suppliers.sabre.booking_enabled' => true,
            'suppliers.sabre.ticketing_enabled' => false,
            'suppliers.sabre.booking_path' => '/v2.5.0/passenger/records?mode=create',
            'suppliers.sabre.booking_schema' => 'create_passenger_name_record',
        ]);
        foreach ([
            ['/v2.4.0/passenger/records?mode=create', 200, ['0411'], ['NOOP']],
            ['/v2.5.0/passenger/records?mode=create', 200, ['0411'], ['NOOP']],
        ] as $row) {
            SupplierBookingAttempt::query()->create([
                'agency_id' => $booking->agency_id,
                'booking_id' => $booking->id,
                'supplier_connection_id' => $cid,
                'provider' => SupplierProvider::Sabre->value,
                'action' => 'compare_booking_endpoint',
                'status' => 'ok',
                'safe_summary' => [
                    'endpoint_path' => $row[0],
                    'http_status' => $row[1],
                    'response_error_codes' => $row[2],
                    'response_error_messages' => $row[3],
                ],
                'attempted_at' => now(),
                'completed_at' => now(),
            ]);
        }
        SupplierBookingAttempt::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'supplier_connection_id' => $cid,
            'provider' => SupplierProvider::Sabre->value,
            'action' => 'create_booking',
            'status' => 'needs_review',
            'safe_summary' => [
                'http_status' => 200,
                'application_results_status' => 'Incomplete',
                'response_error_codes' => ['0411'],
                'response_error_messages' => ['EnhancedAirBookRQ: FLIGHT NOOP'],
                'host_warning_sabre_codes' => ['ERR.SP.PROVIDER_ERROR'],
            ],
            'attempted_at' => now(),
            'completed_at' => now(),
        ]);

        $exit = Artisan::call('sabre:inspect-booking-payload', [
            '--booking' => (string) $booking->id,
            '--segment-sell-diagnostics' => true,
            '--note' => 'parity_check_manual',
        ]);
        $this->assertSame(0, $exit);
        $out = Artisan::output();
        $this->assertStringContainsString('segment_sell_diagnostics_json=', $out);
        $this->assertStringContainsString('"segment_count":2', $out);
        $this->assertStringContainsString('"KHI"', $out);
        $this->assertStringContainsString('"route_continuity":true', $out);
        $this->assertStringContainsString('"fare_basis_snapshot":"VOW1"', $out);
        $this->assertStringContainsString('"fare_basis_snapshot":"UOWSKPK"', $out);
        $this->assertStringContainsString('parity_check_manual', $out);
        $this->assertStringContainsString('v24_and_v25_same_http_status_and_truncated_error_signals_in_compare_attempts', $out);
        foreach (['wire-b23-test@example.com', 'WireTest', '+92300', 'XT8888888', 'Bearer'] as $leak) {
            $this->assertStringNotContainsString($leak, $out, 'segment diagnostics must not leak: '.$leak);
        }
    }

    public function test_b78_fare_context_diagnostics_reflects_snapshot_fare_and_carrier(): void
    {
        $booking = $this->seedWireTestBooking();
        config([
            'suppliers.sabre.booking_enabled' => true,
            'suppliers.sabre.ticketing_enabled' => false,
            'suppliers.sabre.booking_path' => '/v2.5.0/passenger/records?mode=create',
            'suppliers.sabre.booking_schema' => 'create_passenger_name_record',
        ]);
        $meta = $booking->meta;
        $snap = $meta['normalized_offer_snapshot'];
        $snap['brand_diag'] = ['brandCode' => 'LIGHT'];
        $meta['normalized_offer_snapshot'] = $snap;
        $booking->meta = $meta;
        $booking->save();

        $exit = Artisan::call('sabre:inspect-booking-payload', [
            '--booking' => (string) $booking->id,
            '--fare-context-diagnostics' => true,
        ]);
        $this->assertSame(0, $exit);
        $out = Artisan::output();
        $this->assertStringContainsString('fare_context_diagnostics_json=', $out);
        $pos = strpos($out, 'fare_context_diagnostics_json=');
        $this->assertNotFalse($pos);
        $json = json_decode(
            trim(substr($out, $pos + strlen('fare_context_diagnostics_json='))),
            true,
            512,
            JSON_THROW_ON_ERROR
        );
        $this->assertSame($booking->id, $json['booking_id']);
        $this->assertSame(1, $json['segment_count']);
        $this->assertTrue($json['validating_carrier_present']);
        $this->assertSame('EK', $json['validating_carrier_sanitized']);
        $this->assertTrue($json['fare_basis_present_per_segment'][0]);
        $this->assertContains('KLITE1', $json['fare_basis_values_sanitized']);
        $this->assertTrue($json['airprice_optional_qualifiers_present']);
        $this->assertContains('PricingQualifiers', $json['current_airprice_qualifier_keys']);
        $this->assertContains('ADT', $json['passenger_type_codes_sanitized']);
        $this->assertTrue($json['brand_candidates_present']);
        $this->assertContains('LIGHT', $json['brand_codes_sanitized']);
        foreach (['wire-b23-test@example.com', 'WireTest', '+92300', 'XT8888888', 'Bearer'] as $leak) {
            $this->assertStringNotContainsString($leak, $out, 'fare context diagnostics must not leak: '.$leak);
        }
    }

    public function test_b78_fare_context_diagnostics_missing_flags_and_no_fares_attempt_line(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $sabreConn = SupplierConnection::query()
            ->where('agency_id', $agency->id)
            ->where('provider', 'sabre')
            ->firstOrFail();
        $depart = now()->addDays(14)->toDateString();
        $snapshot = [
            'offer_id' => 'wire-b78-sparse',
            'supplier_offer_id' => 'wire-b78-sparse',
            'supplier_provider' => 'sabre',
            'supplier_connection_id' => $sabreConn->id,
            'airline_code' => 'QR',
            'segments' => [
                [
                    'origin' => 'DOH',
                    'destination' => 'LHR',
                    'departure_at' => $depart.'T08:00:00Z',
                    'arrival_at' => $depart.'T14:00:00Z',
                    'carrier' => 'QR',
                    'flight_number' => '5',
                    'booking_class' => 'B',
                ],
            ],
            'fare_breakdown' => [
                'supplier_total' => 100000,
                'currency' => 'PKR',
                'base_fare' => 80000,
                'taxes' => 20000,
                'passenger_counts' => ['adults' => 1, 'children' => 0, 'infants' => 0],
            ],
            'raw_payload' => [
                'sabre_shop_context' => [
                    'fare_basis_codes' => [],
                    'fare_component_refs' => [],
                    'fare_component_desc_refs' => [],
                    'pricing_information_ref' => '',
                    'pricing_information_id' => '',
                ],
                'sabre_shop_identifiers' => [],
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
                    'origin' => 'DOH',
                    'destination' => 'LHR',
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
            'first_name' => 'Diag',
            'last_name' => 'Only',
        ]);
        BookingContact::query()->create([
            'booking_id' => $booking->id,
            'email' => 'b78-sparse@example.com',
            'phone' => '+923001111111',
            'country' => 'Pakistan',
            'address_line' => null,
            'meta' => [],
        ]);
        BookingFareBreakdown::query()->create([
            'booking_id' => $booking->id,
            'base_fare' => 80000,
            'taxes' => 20000,
            'fees' => 0,
            'markup' => 0,
            'discount' => 0,
            'total' => 100000,
            'currency' => 'PKR',
            'breakdown' => [],
        ]);

        SupplierBookingAttempt::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'supplier_connection_id' => $sabreConn->id,
            'provider' => SupplierProvider::Sabre->value,
            'action' => 'create_booking',
            'status' => 'failed',
            'safe_summary' => [
                'http_status' => 200,
                'response_error_messages' => ['EnhancedAirBookRQ: *NO FARES/RBD/CARRIER'],
            ],
            'attempted_at' => now(),
            'completed_at' => now(),
        ]);

        config([
            'suppliers.sabre.booking_enabled' => true,
            'suppliers.sabre.ticketing_enabled' => false,
            'suppliers.sabre.booking_path' => '/v2.5.0/passenger/records?mode=create',
            'suppliers.sabre.booking_schema' => 'create_passenger_name_record',
        ]);

        $exit = Artisan::call('sabre:inspect-booking-payload', [
            '--booking' => (string) $booking->id,
            '--fare-context-diagnostics' => true,
        ]);
        $this->assertSame(0, $exit);
        $out = Artisan::output();
        $this->assertStringContainsString('fare_context_diagnostics_json=', $out);
        $pos = strpos($out, 'fare_context_diagnostics_json=');
        $this->assertNotFalse($pos);
        $json = json_decode(
            trim(substr($out, $pos + strlen('fare_context_diagnostics_json='))),
            true,
            512,
            JSON_THROW_ON_ERROR
        );
        $this->assertFalse($json['validating_carrier_present']);
        $this->assertFalse($json['fare_basis_present_per_segment'][0]);
        $this->assertFalse($json['pricing_information_present']);
        $this->assertFalse($json['fare_components_present']);
        $this->assertContains('missing_validating_carrier', $json['missing_context_flags']);
        $this->assertContains('missing_fare_basis_all_segments', $json['missing_context_flags']);
        $this->assertContains('missing_pricing_information', $json['missing_context_flags']);
        $this->assertContains('missing_fare_components', $json['missing_context_flags']);
        $this->assertStringContainsString('NO FARES', (string) $json['last_supplier_attempt_error']);
        foreach (['b78-sparse@example.com', 'Diag', '+923001111111', 'Bearer'] as $leak) {
            $this->assertStringNotContainsString($leak, $out, 'fare context diagnostics must not leak: '.$leak);
        }
    }

    public function test_b79_airprice_validating_carrier_compare_wire_carries_qualifier_and_safe_diagnostic_flags(): void
    {
        $booking = $this->seedWireTestBooking();
        config(['suppliers.sabre.ticketing_enabled' => false]);
        $booking->load(['passengers', 'contact']);
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $snapshot = is_array($meta['normalized_offer_snapshot'] ?? null) ? $meta['normalized_offer_snapshot'] : [];
        $snapshot['validating_carrier'] = 'QR';
        $meta['normalized_offer_snapshot'] = $snapshot;
        $booking->meta = $meta;
        $booking->save();

        $p = $booking->passengers->first();
        $this->assertNotNull($p);
        $c = $booking->contact;
        $this->assertNotNull($c);
        $expiry = $p->passport_expiry_date;
        $expiryStr = $expiry instanceof \DateTimeInterface ? $expiry->format('Y-m-d') : (string) $expiry;
        $passengerData = [
            'passengers' => [[
                'type' => 'ADT',
                'first_name' => (string) $p->first_name,
                'last_name' => (string) $p->last_name,
                'passport_number' => (string) $p->passport_number,
                'passport_issuing_country' => (string) $p->passport_issuing_country,
                'passport_expiry_date' => $expiryStr,
                'nationality' => (string) $p->nationality,
                'document_type' => (string) $p->document_type,
            ]],
            'contact' => [
                'email' => (string) $c->email,
                'phone' => (string) $c->phone,
            ],
        ];
        $builder = app(SabreBookingPayloadBuilder::class);
        $draft = $builder->buildInternalDraft($snapshot, $passengerData);
        $this->assertTrue($draft['_valid'] ?? false);
        $this->assertSame('QR', $draft['validating_carrier'] ?? null);
        $apiDraft = $draft;
        unset($apiDraft['_valid']);

        $raw = $builder->buildTraditionalPnrCreatePassengerNameRecordV1AirpriceValidatingCarrierCompareWire($apiDraft, []);
        $this->assertSame(
            SabreBookingPayloadBuilder::TRADITIONAL_PNR_CREATE_PASSENGER_NAME_RECORD_V1_AIRPRICE_VALIDATING_CARRIER_COMPARE_V1,
            $raw['_ota_payload_schema'] ?? null
        );
        $wire = $builder->stripOtaInternalKeysFromBookingWire($raw);
        $vcNode = data_get($wire, 'CreatePassengerNameRecordRQ.AirPrice.0.PriceRequestInformation.OptionalQualifiers.PricingQualifiers.ValidatingCarrier');
        $this->assertSame(['Code' => 'QR'], $vcNode);
        $pt = data_get($wire, 'CreatePassengerNameRecordRQ.AirPrice.0.PriceRequestInformation.OptionalQualifiers.PricingQualifiers.PassengerType');
        $this->assertIsArray($pt);
        $this->assertGreaterThanOrEqual(1, count($pt));
        $cpnr = is_array($wire['CreatePassengerNameRecordRQ'] ?? null) ? $wire['CreatePassengerNameRecordRQ'] : [];
        $airBook = is_array($cpnr['AirBook'] ?? null) ? $cpnr['AirBook'] : [];
        $this->assertArrayNotHasKey('AirPrice', $airBook);

        $sum = $builder->summarizeTraditionalPnrWirePostBody($wire);
        $this->assertTrue((bool) ($sum['wire_airprice_has_validating_carrier'] ?? false));
        $this->assertSame(['QR'], $sum['wire_airprice_validating_carriers_sanitized'] ?? []);
        $this->assertNull($sum['wire_airprice_validating_carrier_invalid_pointer'] ?? null);
        $this->assertTrue($sum['wire_traditional_pnr_contract_valid'] ?? false);

        $badWire = $wire;
        data_set($badWire, 'CreatePassengerNameRecordRQ.AirPrice.0.PriceRequestInformation.OptionalQualifiers.PricingQualifiers.ValidatingCarrier', ['Code' => 'NOTAIRLINE']);
        $sumBad = $builder->summarizeTraditionalPnrWirePostBody($badWire);
        $this->assertContains('air_price_validating_carrier_wire_shape_invalid', $sumBad['wire_invalid_traditional_pnr_contract_keys'] ?? []);
        $this->assertSame(
            'CreatePassengerNameRecordRQ.AirPrice.0.PriceRequestInformation.OptionalQualifiers.PricingQualifiers.ValidatingCarrier',
            $sumBad['wire_airprice_validating_carrier_invalid_pointer'] ?? null
        );

        $svc = app(SabreBookingService::class);
        $preview = $svc->previewTripOrdersWireJsonForInspectCommand(
            $booking,
            SabreBookingPayloadBuilder::TRADITIONAL_PNR_CREATE_PASSENGER_NAME_RECORD_V1_AIRPRICE_VALIDATING_CARRIER_COMPARE_V1
        );
        $this->assertArrayNotHasKey('error', $preview);
        $this->assertSame(
            SabreBookingPayloadBuilder::TRADITIONAL_PNR_CREATE_PASSENGER_NAME_RECORD_V1_AIRPRICE_VALIDATING_CARRIER_COMPARE_V1,
            $preview['payload_style'] ?? null
        );
        $this->assertTrue((bool) ($preview['wire_airprice_has_validating_carrier'] ?? false));
        $this->assertSame(['QR'], $preview['wire_airprice_validating_carriers_sanitized'] ?? []);
        $this->assertFalse($preview['wire_ticketing_enabled'] ?? true);
        $wireJson = json_encode($preview['redacted_wire_request_body'] ?? [], JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString((string) $p->first_name, $wireJson);
        $this->assertStringNotContainsString('+92300', $wireJson);
    }

    public function test_b79_airprice_validating_carrier_compare_omits_qualifier_when_offer_has_no_validating_carrier(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $sabreConn = SupplierConnection::query()
            ->where('agency_id', $agency->id)
            ->where('provider', 'sabre')
            ->firstOrFail();
        $depart = now()->addDays(14)->toDateString();
        $snapshot = [
            'offer_id' => 'wire-b79-no-vc',
            'supplier_offer_id' => 'wire-b79-no-vc',
            'supplier_provider' => 'sabre',
            'supplier_connection_id' => $sabreConn->id,
            'airline_code' => 'QR',
            'segments' => [[
                'origin' => 'DOH',
                'destination' => 'LHR',
                'departure_at' => $depart.'T08:00:00Z',
                'arrival_at' => $depart.'T14:00:00Z',
                'carrier' => 'QR',
                'flight_number' => '5',
                'booking_class' => 'B',
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
                'normalized_offer_snapshot' => $snapshot,
                'search_criteria' => [
                    'origin' => 'DOH',
                    'destination' => 'LHR',
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
            'first_name' => 'NoVc',
            'last_name' => 'Traveler',
        ]);
        BookingContact::query()->create([
            'booking_id' => $booking->id,
            'email' => 'b79-no-vc@example.com',
            'phone' => '+923009990000',
            'country' => 'Pakistan',
            'address_line' => null,
            'meta' => [],
        ]);
        BookingFareBreakdown::query()->create([
            'booking_id' => $booking->id,
            'base_fare' => 80000,
            'taxes' => 20000,
            'fees' => 0,
            'markup' => 0,
            'discount' => 0,
            'total' => 100000,
            'currency' => 'PKR',
            'breakdown' => [],
        ]);

        config(['suppliers.sabre.ticketing_enabled' => false]);
        $booking->load(['passengers', 'contact']);
        $snapUse = $snapshot;
        $p = $booking->passengers->first();
        $this->assertNotNull($p);
        $c = $booking->contact;
        $this->assertNotNull($c);
        $passengerData = [
            'passengers' => [[
                'type' => 'ADT',
                'first_name' => (string) $p->first_name,
                'last_name' => (string) $p->last_name,
            ]],
            'contact' => [
                'email' => (string) $c->email,
                'phone' => (string) $c->phone,
            ],
        ];
        $builder = app(SabreBookingPayloadBuilder::class);
        $draft = $builder->buildInternalDraft($snapUse, $passengerData);
        $this->assertTrue($draft['_valid'] ?? false);
        $this->assertSame('', $draft['validating_carrier'] ?? 'non-empty');
        $apiDraft = $draft;
        unset($apiDraft['_valid']);
        $raw = $builder->buildTraditionalPnrCreatePassengerNameRecordV1AirpriceValidatingCarrierCompareWire($apiDraft, []);
        $wire = $builder->stripOtaInternalKeysFromBookingWire($raw);
        $pq = data_get($wire, 'CreatePassengerNameRecordRQ.AirPrice.0.PriceRequestInformation.OptionalQualifiers.PricingQualifiers');
        $this->assertIsArray($pq);
        $this->assertArrayNotHasKey('ValidatingCarrier', $pq);
        $sum = $builder->summarizeTraditionalPnrWirePostBody($wire);
        $this->assertFalse((bool) ($sum['wire_airprice_has_validating_carrier'] ?? true));
        $this->assertSame([], $sum['wire_airprice_validating_carriers_sanitized'] ?? []);
    }

    public function test_d2c_traditional_cpnr_airprice_validating_carrier_gated_off_omits_qualifier_on_live_wire(): void
    {
        config([
            'suppliers.sabre.ticketing_enabled' => false,
            'suppliers.sabre.traditional_cpnr_airprice_validating_carrier' => false,
        ]);
        $booking = $this->seedWireTestBooking();
        $booking->load(['passengers', 'contact']);
        $builder = app(SabreBookingPayloadBuilder::class);
        $apiDraft = $this->wireTestApiDraftFromBooking($booking);
        $raw = $builder->buildTraditionalPnrCreatePassengerNameRecordV1Wire($apiDraft, []);
        $wire = $builder->stripOtaInternalKeysFromBookingWire($raw);
        $pq = data_get($wire, 'CreatePassengerNameRecordRQ.AirPrice.0.PriceRequestInformation.OptionalQualifiers.PricingQualifiers');
        $this->assertIsArray($pq);
        $this->assertArrayNotHasKey('ValidatingCarrier', $pq);
        $pt = data_get($wire, 'CreatePassengerNameRecordRQ.AirPrice.0.PriceRequestInformation.OptionalQualifiers.PricingQualifiers.PassengerType');
        $this->assertIsArray($pt);
        $this->assertGreaterThanOrEqual(1, count($pt));
        $sum = $builder->summarizeTraditionalPnrWirePostBody($wire);
        $this->assertFalse((bool) ($sum['wire_airprice_has_validating_carrier'] ?? true));
    }

    public function test_d2c_traditional_cpnr_airprice_validating_carrier_gated_on_carries_qualifier_and_preserves_sell_schema(): void
    {
        config([
            'suppliers.sabre.ticketing_enabled' => false,
            'suppliers.sabre.traditional_cpnr_airprice_validating_carrier' => true,
        ]);
        $booking = $this->seedWireTestBooking();
        $booking->load(['passengers', 'contact']);
        $builder = app(SabreBookingPayloadBuilder::class);
        $apiDraft = $this->wireTestApiDraftFromBooking($booking);
        $this->assertSame('EK', $apiDraft['validating_carrier'] ?? null);
        $raw = $builder->buildTraditionalPnrCreatePassengerNameRecordV1Wire($apiDraft, []);
        $this->assertSame(
            SabreBookingPayloadBuilder::TRADITIONAL_PNR_CREATE_PASSENGER_NAME_RECORD_V1,
            $raw['_ota_payload_schema'] ?? null
        );
        $wire = $builder->stripOtaInternalKeysFromBookingWire($raw);
        $vcNode = data_get($wire, 'CreatePassengerNameRecordRQ.AirPrice.0.PriceRequestInformation.OptionalQualifiers.PricingQualifiers.ValidatingCarrier');
        $this->assertSame(['Code' => 'EK'], $vcNode);
        $pt = data_get($wire, 'CreatePassengerNameRecordRQ.AirPrice.0.PriceRequestInformation.OptionalQualifiers.PricingQualifiers.PassengerType');
        $this->assertIsArray($pt);
        $this->assertGreaterThanOrEqual(1, count($pt));
        $fs = data_get($wire, 'CreatePassengerNameRecordRQ.AirBook.OriginDestinationInformation.FlightSegment');
        $fsRows = is_array($fs) ? (array_is_list($fs) ? $fs : [$fs]) : [];
        $this->assertNotSame([], $fsRows);
        foreach ($fsRows as $seg) {
            if (! is_array($seg)) {
                continue;
            }
            $this->assertArrayHasKey('ResBookDesigCode', $seg);
            $this->assertArrayNotHasKey('FareBasisCode', $seg);
        }
        $sum = $builder->summarizeTraditionalPnrWirePostBody($wire);
        $this->assertTrue((bool) ($sum['wire_airprice_has_validating_carrier'] ?? false));
        $this->assertSame(['EK'], $sum['wire_airprice_validating_carriers_sanitized'] ?? []);
        $this->assertTrue($sum['wire_traditional_pnr_contract_valid'] ?? false);
    }

    public function test_d2c_traditional_cpnr_airprice_validating_carrier_gated_on_omits_invalid_or_missing_vc(): void
    {
        config([
            'suppliers.sabre.ticketing_enabled' => false,
            'suppliers.sabre.traditional_cpnr_airprice_validating_carrier' => true,
        ]);
        $this->seed(OtaFoundationSeeder::class);
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $sabreConn = SupplierConnection::query()
            ->where('agency_id', $agency->id)
            ->where('provider', 'sabre')
            ->firstOrFail();
        $depart = now()->addDays(14)->toDateString();
        $snapshot = [
            'offer_id' => 'wire-d2c-no-vc',
            'supplier_offer_id' => 'wire-d2c-no-vc',
            'supplier_provider' => 'sabre',
            'supplier_connection_id' => $sabreConn->id,
            'airline_code' => 'QR',
            'segments' => [[
                'origin' => 'DOH',
                'destination' => 'LHR',
                'departure_at' => $depart.'T08:00:00Z',
                'arrival_at' => $depart.'T14:00:00Z',
                'carrier' => 'QR',
                'flight_number' => '5',
                'booking_class' => 'B',
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
                'normalized_offer_snapshot' => $snapshot,
                'search_criteria' => [
                    'origin' => 'DOH',
                    'destination' => 'LHR',
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
            'first_name' => 'NoVc',
            'last_name' => 'Traveler',
        ]);
        BookingContact::query()->create([
            'booking_id' => $booking->id,
            'email' => 'd2c-no-vc@example.com',
            'phone' => '+923009990001',
            'country' => 'Pakistan',
            'address_line' => null,
            'meta' => [],
        ]);
        BookingFareBreakdown::query()->create([
            'booking_id' => $booking->id,
            'base_fare' => 80000,
            'taxes' => 20000,
            'fees' => 0,
            'markup' => 0,
            'discount' => 0,
            'total' => 100000,
            'currency' => 'PKR',
            'breakdown' => [],
        ]);

        $booking->load(['passengers', 'contact']);
        $builder = app(SabreBookingPayloadBuilder::class);
        $apiDraft = $this->wireTestApiDraftFromBooking($booking, $snapshot);
        $this->assertSame('', $apiDraft['validating_carrier'] ?? 'non-empty');
        $raw = $builder->buildTraditionalPnrCreatePassengerNameRecordV1Wire($apiDraft, []);
        $wire = $builder->stripOtaInternalKeysFromBookingWire($raw);
        $pq = data_get($wire, 'CreatePassengerNameRecordRQ.AirPrice.0.PriceRequestInformation.OptionalQualifiers.PricingQualifiers');
        $this->assertIsArray($pq);
        $this->assertArrayNotHasKey('ValidatingCarrier', $pq);
        $pt = data_get($wire, 'CreatePassengerNameRecordRQ.AirPrice.0.PriceRequestInformation.OptionalQualifiers.PricingQualifiers.PassengerType');
        $this->assertIsArray($pt);
        $this->assertGreaterThanOrEqual(1, count($pt));
        $sum = $builder->summarizeTraditionalPnrWirePostBody($wire);
        $this->assertFalse((bool) ($sum['wire_airprice_has_validating_carrier'] ?? true));

        $apiDraftInvalid = $apiDraft;
        $apiDraftInvalid['validating_carrier'] = 'NOTAIRLINE';
        $rawInvalid = $builder->buildTraditionalPnrCreatePassengerNameRecordV1Wire($apiDraftInvalid, []);
        $wireInvalid = $builder->stripOtaInternalKeysFromBookingWire($rawInvalid);
        $pqInvalid = data_get($wireInvalid, 'CreatePassengerNameRecordRQ.AirPrice.0.PriceRequestInformation.OptionalQualifiers.PricingQualifiers');
        $this->assertIsArray($pqInvalid);
        $this->assertArrayNotHasKey('ValidatingCarrier', $pqInvalid);
    }

    /**
     * @return array<string, mixed>
     */
    private function wireTestApiDraftFromBooking(Booking $booking, ?array $snapshotOverride = null): array
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $snapshot = $snapshotOverride ?? (is_array($meta['normalized_offer_snapshot'] ?? null) ? $meta['normalized_offer_snapshot'] : []);
        $p = $booking->passengers->first();
        $this->assertNotNull($p);
        $c = $booking->contact;
        $this->assertNotNull($c);
        $expiry = $p->passport_expiry_date;
        $expiryStr = $expiry instanceof \DateTimeInterface ? $expiry->format('Y-m-d') : (string) $expiry;
        $passengerData = [
            'passengers' => [[
                'type' => 'ADT',
                'first_name' => (string) $p->first_name,
                'last_name' => (string) $p->last_name,
                'passport_number' => (string) $p->passport_number,
                'passport_issuing_country' => (string) $p->passport_issuing_country,
                'passport_expiry_date' => $expiryStr,
                'nationality' => (string) $p->nationality,
                'document_type' => (string) $p->document_type,
            ]],
            'contact' => [
                'email' => (string) $c->email,
                'phone' => (string) $c->phone,
            ],
        ];
        $builder = app(SabreBookingPayloadBuilder::class);
        $draft = $builder->buildInternalDraft($snapshot, $passengerData);
        $this->assertTrue($draft['_valid'] ?? false);
        $apiDraft = $draft;
        unset($apiDraft['_valid']);

        return $apiDraft;
    }
}
