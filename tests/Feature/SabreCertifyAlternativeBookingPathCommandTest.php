<?php

namespace Tests\Feature;

use App\Enums\SupplierProvider;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\SupplierConnection;
use App\Services\Suppliers\Sabre\SabreBookingPayloadBuilder;
use App\Services\Suppliers\Sabre\SabreBookingService;
use App\Services\Suppliers\Sabre\SabreInspectGate;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Mockery;
use Tests\TestCase;

class SabreCertifyAlternativeBookingPathCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('app.env', 'testing');
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_dry_run_emits_audit_and_matrix_without_send(): void
    {
        $booking = $this->sabreBooking();
        $mock = Mockery::mock(SabreBookingService::class);
        $mock->shouldReceive('alternativeBookingPathAuditForCommand')
            ->once()
            ->andReturn([
                'booking_id' => $booking->id,
                'configured_booking_path' => '/v2.5.0/passenger/records?mode=create',
                'trip_orders_configured' => false,
                'agency_phone_config_present' => true,
                'pricing_context_ready' => true,
                'offer_refresh_acceptance_required' => false,
                'readiness' => ['segment_count' => 2],
                'iati_cpnr_structure_diff' => ['paths_only_in_ota_wire_count' => 1],
                'recommended_next_action' => 'test',
            ]);
        $mock->shouldReceive('compareBookingEndpointsForCommand')
            ->once()
            ->with(Mockery::type(Booking::class), false, false, null, null, 'p5')
            ->andReturn([[
                'endpoint_path' => '/v1/trip/orders/createBooking',
                'payload_style' => 'trip_orders_flight_details_sabre_v1',
                'status' => 'inspect_only',
            ]]);
        $this->app->instance(SabreBookingService::class, $mock);

        $exit = Artisan::call('sabre:certify-alternative-booking-path', ['--booking' => (string) $booking->id]);
        $out = Artisan::output();

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('P5 alternative booking path audit', $out);
        $this->assertStringContainsString('configured_booking_path=', $out);
        $this->assertStringContainsString('inspect_only', $out);
    }

    public function test_send_requires_endpoint_and_style(): void
    {
        $booking = $this->sabreBooking();

        $exit = Artisan::call('sabre:certify-alternative-booking-path', [
            '--booking' => (string) $booking->id,
            '--send' => true,
        ]);

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('--send requires explicit', Artisan::output());
    }

    public function test_blocked_outside_local_testing(): void
    {
        Config::set('app.env', 'production');
        $this->assertFalse(SabreInspectGate::allowed());

        $exit = Artisan::call('sabre:certify-alternative-booking-path', ['--booking' => '1']);

        $this->assertSame(1, $exit);
    }

    public function test_v2_style_puts_flight_details_under_create_booking_wrapper_with_payload_summary(): void
    {
        config([
            'suppliers.sabre.agency_phone' => '+19997770001',
            'suppliers.sabre.agency_phone_country_code' => 'US',
        ]);
        $builder = app(SabreBookingPayloadBuilder::class);
        $offer = [
            'id' => 'p5-v2',
            'supplier_connection_id' => 1,
            'supplier_offer_id' => 'so-p5-v2',
            'airline_code' => 'EK',
            'validating_carrier' => 'EK',
            'segments' => [
                [
                    'origin' => 'LHE',
                    'destination' => 'KHI',
                    'departure_at' => '2026-06-01T08:00:00Z',
                    'arrival_at' => '2026-06-01T10:00:00Z',
                    'carrier' => 'PK',
                    'flight_number' => '301',
                    'booking_class' => 'Y',
                    'fare_basis_code' => 'YOWPK',
                ],
                [
                    'origin' => 'KHI',
                    'destination' => 'DXB',
                    'departure_at' => '2026-06-01T14:00:00Z',
                    'arrival_at' => '2026-06-01T16:00:00Z',
                    'carrier' => 'EK',
                    'flight_number' => '601',
                    'booking_class' => 'K',
                    'fare_basis_code' => 'KLITE1',
                ],
            ],
            'fare_breakdown' => [
                'supplier_total' => 200,
                'currency' => 'USD',
                'base_fare' => 160,
                'taxes' => 40,
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
                'passport_number' => 'AB1234567',
                'passport_issuing_country' => 'PK',
                'passport_expiry_date' => '2030-05-05',
                'nationality' => 'PK',
                'document_type' => 'passport',
            ]],
            'contact' => ['email' => 'p5-v2@example.com', 'phone' => '+1000000000'],
        ];
        $draft = $builder->buildInternalDraft($offer, $passengerData);
        unset($draft['_valid']);
        $env = $builder->buildTripOrdersCreateBookingEnvelope($draft, [], 'trip_orders_create_booking_root_flight_details_v2');
        $wire = $builder->tripOrdersFinalWirePostBodyFromEnvelope($env);
        $this->assertArrayHasKey('flightDetails', $wire);
        $this->assertArrayNotHasKey('createBooking', $wire);
        $this->assertArrayHasKey('contactInfo', $wire);
        $this->assertArrayNotHasKey('contact', $wire);
        $fdSeg = is_array($wire['flightDetails']['segments'][0] ?? null) ? $wire['flightDetails']['segments'][0] : [];
        $this->assertArrayHasKey('departureDateTime', $fdSeg);
        $t0 = is_array($wire['travelers'][0] ?? null) ? $wire['travelers'][0] : [];
        $this->assertArrayHasKey('passengerCode', $t0);
        $summary = $builder->summarizeTripOrdersCertificationPayloadSummary($env);
        $this->assertTrue($summary['has_flightDetails']);
        $this->assertFalse($summary['has_flightOffer']);
        $this->assertSame(2, $summary['segment_count']);
        $this->assertSame(1, $summary['traveler_count']);
        $this->assertTrue($summary['has_contactInfo']);
        $this->assertContains('flightDetails', $summary['root_keys']);
        $diag = $builder->summarizeTripOrdersWirePostBodyForEnvelope($env);
        $this->assertTrue($diag['wire_contract_valid']);
    }

    public function test_v2_agency_phone_flat_shape_uses_phone_number_keys_without_raw_phone_in_summary(): void
    {
        config([
            'suppliers.sabre.agency_phone' => '+19997770001',
            'suppliers.sabre.agency_name' => 'Test Agency',
        ]);
        $builder = app(SabreBookingPayloadBuilder::class);
        $draft = $this->minimalV2Draft($builder);
        $env = $builder->buildTripOrdersCreateBookingEnvelope($draft, [], 'trip_orders_root_flight_details_v2_agency_phone_flat');
        $wire = $builder->tripOrdersFinalWirePostBodyFromEnvelope($env);
        $aci = is_array($wire['agencyContactInfo'] ?? null) ? $wire['agencyContactInfo'] : [];
        $this->assertArrayHasKey('phoneNumber', $aci);
        $summary = $builder->summarizeTripOrdersCertificationPayloadSummary($env);
        $this->assertTrue($summary['has_agency_phone_value']);
        $this->assertStringContainsString('agencyContactInfo.phoneNumber', (string) $summary['agency_phone_shape']);
        $this->assertTrue($summary['agency_name_present']);
        $encoded = json_encode($summary);
        $this->assertIsString($encoded);
        $this->assertStringNotContainsString('+19997770001', $encoded);
    }

    public function test_v2_no_agency_contact_omits_agency_contact_info_block(): void
    {
        config(['suppliers.sabre.agency_phone' => '+19997770001']);
        $builder = app(SabreBookingPayloadBuilder::class);
        $draft = $this->minimalV2Draft($builder);
        $env = $builder->buildTripOrdersCreateBookingEnvelope($draft, [], 'trip_orders_root_flight_details_v2_no_agency_contact');
        $wire = $builder->tripOrdersFinalWirePostBodyFromEnvelope($env);
        $this->assertArrayNotHasKey('agencyContactInfo', $wire);
        $summary = $builder->summarizeTripOrdersCertificationPayloadSummary($env);
        $this->assertFalse($summary['has_agency_phone_value']);
        $this->assertSame('none', $summary['agency_phone_shape']);
    }

    /**
     * @return array<string, mixed>
     */
    protected function minimalV2Draft(SabreBookingPayloadBuilder $builder): array
    {
        $offer = [
            'id' => 'p5-v2-mini',
            'supplier_connection_id' => 1,
            'supplier_offer_id' => 'so-mini',
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
                'passenger_counts' => ['adults' => 1],
            ],
        ];
        $passengerData = [
            'passengers' => [['passenger_type' => 'adult', 'first_name' => 'A', 'last_name' => 'B', 'gender' => 'M', 'date_of_birth' => '1990-01-01']],
            'contact' => ['email' => 'mini@example.com', 'phone' => '+1000000000'],
        ];
        $draft = $builder->buildInternalDraft($offer, $passengerData);
        unset($draft['_valid']);

        return $draft;
    }

    protected function sabreBooking(): Booking
    {
        $this->seed(OtaFoundationSeeder::class);
        $agency = Agency::query()->firstOrFail();
        $conn = SupplierConnection::query()->create([
            'agency_id' => $agency->id,
            'provider' => SupplierProvider::Sabre,
            'label' => 'Test Sabre',
            'base_url' => 'https://example.sabre.test',
            'status' => 'active',
            'credentials' => ['client_id' => 'x', 'client_secret' => 'y'],
        ]);

        return Booking::query()->create([
            'agency_id' => $agency->id,
            'supplier' => SupplierProvider::Sabre->value,
            'status' => 'draft',
            'meta' => [
                'supplier_provider' => SupplierProvider::Sabre->value,
                'supplier_connection_id' => $conn->id,
                'normalized_offer_snapshot' => [
                    'segments' => [
                        ['origin' => 'LHE', 'destination' => 'KHI', 'carrier' => 'PK'],
                        ['origin' => 'KHI', 'destination' => 'DXB', 'carrier' => 'EK'],
                    ],
                ],
            ],
        ]);
    }
}
