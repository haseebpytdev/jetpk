<?php

namespace Tests\Unit\Support\Bookings;

use App\Enums\SupplierProvider;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\SupplierConnection;
use App\Services\Suppliers\Sabre\Booking\SabreBookingPayloadBuilder;
use App\Support\Bookings\SabreCpnrOperationalAllowNnPolicy;
use App\Support\Bookings\SabrePnrFailureClassifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class SabreCpnrOperationalAllowNnPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function gfConnectingDraft(): array
    {
        return [
            'segments' => [
                ['carrier' => 'GF', 'origin' => 'LHE', 'destination' => 'BAH', 'flight_number' => '765'],
                ['carrier' => 'GF', 'origin' => 'BAH', 'destination' => 'JED', 'flight_number' => '500'],
            ],
            'supplier_connection_id' => 1,
        ];
    }

    protected function seedCertConnection(): SupplierConnection
    {
        $agency = Agency::factory()->create();

        return SupplierConnection::factory()->create([
            'agency_id' => $agency->id,
            'provider' => SupplierProvider::Sabre,
            'credentials' => ['base_url' => 'https://api.cert.platform.sabre.com'],
        ]);
    }

    public function test_policy_omits_nn_when_config_and_gf_two_segment_cert_gates_pass(): void
    {
        Config::set('suppliers.sabre.cpnr_allow_nn_halt_on_status_cert_operational', true);
        Config::set('suppliers.sabre.ticketing_enabled', false);

        $connection = $this->seedCertConnection();
        $draft = $this->gfConnectingDraft();
        $draft['supplier_connection_id'] = $connection->id;

        $decision = app(SabreCpnrOperationalAllowNnPolicy::class)->evaluate(
            $draft,
            SabreBookingPayloadBuilder::IATI_LIKE_CPNR_V2_4_GDS,
            SabreBookingPayloadBuilder::PASSENGER_RECORDS_V24_CREATE_PATH,
            $connection,
        );

        $this->assertTrue($decision['should_omit_nn_wn']);
        $this->assertTrue($decision['allow_nn_cert_operational']);
        $this->assertTrue($decision['halt_on_status_nn_omitted']);
        $this->assertSame(SabreCpnrOperationalAllowNnPolicy::POLICY_CERT_OPERATIONAL_OMIT_NN_WN, $decision['halt_on_status_policy']);
    }

    public function test_policy_blocks_when_config_disabled(): void
    {
        Config::set('suppliers.sabre.cpnr_allow_nn_halt_on_status_cert_operational', false);
        $connection = $this->seedCertConnection();

        $decision = app(SabreCpnrOperationalAllowNnPolicy::class)->evaluate(
            $this->gfConnectingDraft(),
            SabreBookingPayloadBuilder::IATI_LIKE_CPNR_V2_4_GDS,
            SabreBookingPayloadBuilder::PASSENGER_RECORDS_V24_CREATE_PATH,
            $connection,
        );

        $this->assertFalse($decision['should_omit_nn_wn']);
        $this->assertSame('config_disabled', $decision['block_reason']);
    }

    public function test_policy_blocks_mixed_carrier_two_segment(): void
    {
        Config::set('suppliers.sabre.cpnr_allow_nn_halt_on_status_cert_operational', true);
        Config::set('suppliers.sabre.ticketing_enabled', false);
        $connection = $this->seedCertConnection();

        $draft = [
            'segments' => [
                ['carrier' => 'GF', 'origin' => 'LHE', 'destination' => 'BAH'],
                ['carrier' => 'EK', 'origin' => 'BAH', 'destination' => 'JED'],
            ],
        ];

        $decision = app(SabreCpnrOperationalAllowNnPolicy::class)->evaluate(
            $draft,
            SabreBookingPayloadBuilder::IATI_LIKE_CPNR_V2_4_GDS,
            SabreBookingPayloadBuilder::PASSENGER_RECORDS_V24_CREATE_PATH,
            $connection,
        );

        $this->assertFalse($decision['should_omit_nn_wn']);
        $this->assertSame('blocks_mixed_carrier', $decision['block_reason']);
    }

    public function test_policy_blocks_when_pnr_already_exists(): void
    {
        Config::set('suppliers.sabre.cpnr_allow_nn_halt_on_status_cert_operational', true);
        Config::set('suppliers.sabre.ticketing_enabled', false);
        $connection = $this->seedCertConnection();
        $booking = Booking::factory()->create([
            'agency_id' => $connection->agency_id,
            'pnr' => 'ABCDEF',
        ]);

        $decision = app(SabreCpnrOperationalAllowNnPolicy::class)->evaluate(
            $this->gfConnectingDraft(),
            SabreBookingPayloadBuilder::IATI_LIKE_CPNR_V2_4_GDS,
            SabreBookingPayloadBuilder::PASSENGER_RECORDS_V24_CREATE_PATH,
            $connection,
            $booking,
        );

        $this->assertFalse($decision['should_omit_nn_wn']);
        $this->assertSame('pnr_already_exists', $decision['block_reason']);
    }

    public function test_gf_draft_wire_omits_nn_when_operational_flag_set(): void
    {
        $builder = app(SabreBookingPayloadBuilder::class);
        $draft = [
            '_valid' => true,
            'supplier_connection_id' => 1,
            '_sabre_pseudo_city_code' => 'AB12',
            'validating_carrier' => 'GF',
            'segments' => [
                [
                    'origin' => 'LHE',
                    'destination' => 'BAH',
                    'carrier' => 'GF',
                    'flight_number' => '765',
                    'departure_at' => '2026-08-01T08:00:00',
                    'arrival_at' => '2026-08-01T14:00:00',
                    'booking_class' => 'W',
                ],
                [
                    'origin' => 'BAH',
                    'destination' => 'JED',
                    'carrier' => 'GF',
                    'flight_number' => '500',
                    'departure_at' => '2026-08-01T16:00:00',
                    'arrival_at' => '2026-08-01T20:00:00',
                    'booking_class' => 'W',
                ],
            ],
            'passengers' => [
                ['type' => 'ADT', 'first_name' => 'Test', 'last_name' => 'Traveler', 'gender' => 'MALE', 'date_of_birth' => '1990-01-15'],
            ],
            'contact' => ['email' => 'booker@example.com', 'phone' => '3001234567'],
            '_requires_passport_doc' => false,
            '_sabre_booking_context' => [],
            '_ota_cert_allow_nn_diagnostic' => true,
        ];

        $wire = $builder->buildIatiLikeCpnrV24GdsWire($draft);
        $codes = $builder->extractHaltOnStatusCodesFromCpnr($wire['CreatePassengerNameRecordRQ'] ?? []);

        $this->assertNotContains('NN', $codes);
        $this->assertNotContains('WN', $codes);
    }

    public function test_classifier_allows_operational_retry_when_config_enabled(): void
    {
        Config::set('suppliers.sabre.cpnr_allow_nn_halt_on_status_cert_operational', true);

        $result = SabrePnrFailureClassifier::classify('sabre_booking_application_error', [
            'airline_segment_status' => 'NN',
            'halt_on_status_received' => true,
            'probable_issue' => 'airline_segment_status_nn_halt',
        ]);

        $this->assertTrue($result['retry_allowed']);
        $this->assertSame(SabrePnrFailureClassifier::NEXT_ACTION_OPERATIONAL_ALLOW_NN_RETRY, $result['next_action']);
    }

    public function test_classifier_detects_nn_halt_from_response_messages_only(): void
    {
        $this->assertTrue(SabrePnrFailureClassifier::safeSummaryIndicatesPriorNnHaltOnStatusFailure([
            'response_error_messages' => [
                'WARN.SP.HALT_ON_STATUS_RECEIVED',
                'Flight GF767 returned status code NN',
            ],
        ]));
    }
}
