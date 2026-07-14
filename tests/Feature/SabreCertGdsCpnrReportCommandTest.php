<?php

namespace Tests\Feature;

use App\Console\Commands\SabreCertGdsCpnrReportCommand;
use App\Enums\SupplierProvider;
use App\Models\Agency;
use App\Models\SupplierConnection;
use App\Services\Suppliers\Sabre\SabreBookingPayloadBuilder;
use App\Services\Suppliers\Sabre\SabreInspectGate;
use App\Support\Bookings\SabrePnrCertificationSupport;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SabreCertGdsCpnrReportCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Config::set('app.env', 'testing');
        Config::set('suppliers.sabre.cert_entitlement_matrix_enabled', false);
        parent::tearDown();
    }

    public function test_command_registered(): void
    {
        Artisan::call('list');
        $this->assertStringContainsString('sabre:cert-gds-cpnr-report', Artisan::output());
    }

    public function test_blocked_in_production_without_flag(): void
    {
        Config::set('app.env', 'production');
        Config::set('suppliers.sabre.cert_entitlement_matrix_enabled', false);
        $conn = $this->seedSabreConnection('https://api.cert.platform.sabre.com');

        $exit = Artisan::call('sabre:cert-gds-cpnr-report', [
            '--connection' => (string) $conn->id,
        ]);

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('sabre_cert_entitlement_matrix_disabled', Artisan::output());
    }

    public function test_blocked_on_live_platform_host(): void
    {
        Config::set('app.env', 'testing');
        $conn = $this->seedSabreConnection('https://api.platform.sabre.com');

        $exit = Artisan::call('sabre:cert-gds-cpnr-report', [
            '--connection' => (string) $conn->id,
        ]);

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('blocks api.platform.sabre.com', Artisan::output());
        $this->assertFalse(SabreInspectGate::isCertSabreHost('https://api.platform.sabre.com'));
    }

    public function test_ow_direct_shop_and_cpnr_preview_success(): void
    {
        Config::set('app.env', 'testing');
        Config::set('suppliers.sabre.booking_mode', 'pnr_only');
        Config::set('suppliers.sabre.booking_schema', 'create_passenger_name_record');
        Config::set('suppliers.sabre.ticketing_enabled', false);
        Config::set('suppliers.sabre.pnr_only_waive_mandatory_revalidation', true);

        $shopFixture = $this->shopFixtureWithBookingCode('Y');
        Http::fake([
            '*/v2/auth/token*' => Http::response(['access_token' => 'fake-token-for-tests-only', 'expires_in' => 1800], 200),
            '*v4/offers/shop*' => Http::response($shopFixture, 200),
        ]);
        $conn = $this->seedSabreConnection();

        $exit = Artisan::call('sabre:cert-gds-cpnr-report', [
            '--connection' => (string) $conn->id,
            '--from' => 'LHE',
            '--to' => 'DXB',
            '--date' => '2026-08-15',
            '--scenario' => 'ow_direct',
            '--carrier' => 'EK',
            '--json' => true,
        ]);

        $this->assertSame(0, $exit);
        $payload = $this->decodeReportOutput(Artisan::output());
        $this->assertSame('cert_gds_cpnr_v1', $payload['report_version']);
        $this->assertSame('ow_direct', $payload['scenario']);
        $this->assertGreaterThanOrEqual(1, $payload['eligible_offer_count']);
        $this->assertSame('EK', $payload['selected_offer']['validating_carrier'] ?? null);
        $this->assertFalse($payload['cpnr_config']['send_enabled']);
        $this->assertFalse($payload['cpnr_config']['ticketing_enabled']);
        $this->assertSame(
            '/v2.5.0/passenger/records?mode=create',
            $payload['cpnr_config']['endpoint'],
        );
        $this->assertSame(
            SabreBookingPayloadBuilder::TRADITIONAL_PNR_CREATE_PASSENGER_NAME_RECORD_V1,
            $payload['cpnr_config']['payload_style'],
        );
        $this->assertTrue($payload['payload_summary']['has_create_passenger_name_record_rq']);
        $this->assertTrue($payload['payload_summary']['has_air_book']);
        $this->assertTrue($payload['payload_summary']['has_booking_classes']);
        $this->assertGreaterThanOrEqual(1, $payload['payload_summary']['flight_segment_count']);
        $this->assertArrayHasKey('safe_wire_preview', $payload);
        $this->assertArrayHasKey('CreatePassengerNameRecordRQ', $payload['safe_wire_preview']);
        $this->assertTrue($payload['readiness']['wire_contract_valid']);
        $this->assertTrue($payload['readiness']['cpnr_preview_ready']);
        $this->assertSame('cpnr_wire_preview_ready', $payload['reason_code']);
        $this->assertSame('waived_for_pnr_only', $payload['cpnr_config']['revalidation_required']);
        $this->assertArrayHasKey('pricing_diagnostics', $payload);
        $this->assertArrayHasKey('style_comparison', $payload);
        $this->assertArrayHasKey('entries', $payload['style_comparison']);
        $this->assertArrayHasKey(SabreBookingPayloadBuilder::TRADITIONAL_PNR_CREATE_PASSENGER_NAME_RECORD_V1, $payload['style_comparison']['entries']);
        $this->assertArrayHasKey(SabreBookingPayloadBuilder::IATI_LIKE_CPNR_V2_4_GDS, $payload['style_comparison']['entries']);
        $this->assertArrayHasKey('has_air_price', $payload['pricing_diagnostics']);
        $this->assertArrayHasKey('air_price_node_count', $payload['pricing_diagnostics']);
        $this->assertArrayHasKey('has_price_request_information', $payload['pricing_diagnostics']);
        $this->assertFalse($payload['pricing_diagnostics']['has_ticketing']);
        $this->assertAirpriceWireDiagnosticsFlagFalse($payload);
        $previewAirPrice = data_get($payload, 'safe_wire_preview.CreatePassengerNameRecordRQ.AirPrice');
        $this->assertSame([], $previewAirPrice);
    }

    public function test_airprice_wire_diagnostics_flag_true_includes_validating_carrier(): void
    {
        Config::set('app.env', 'testing');
        Config::set('suppliers.sabre.booking_mode', 'pnr_only');
        Config::set('suppliers.sabre.booking_schema', 'create_passenger_name_record');
        Config::set('suppliers.sabre.ticketing_enabled', false);
        Config::set('suppliers.sabre.traditional_cpnr_airprice_validating_carrier', true);

        $shopFixture = $this->shopFixtureWithBookingCode('Y');
        Http::fake([
            '*/v2/auth/token*' => Http::response(['access_token' => 'fake-token-for-tests-only', 'expires_in' => 1800], 200),
            '*v4/offers/shop*' => Http::response($shopFixture, 200),
        ]);
        $conn = $this->seedSabreConnection();

        $exit = Artisan::call('sabre:cert-gds-cpnr-report', [
            '--connection' => (string) $conn->id,
            '--from' => 'LHE',
            '--to' => 'DXB',
            '--date' => '2026-08-15',
            '--scenario' => 'ow_direct',
            '--carrier' => 'EK',
            '--json' => true,
        ]);

        $this->assertSame(0, $exit);
        $payload = $this->decodeReportOutput(Artisan::output());
        $diag = $payload['pricing_diagnostics'];
        $this->assertGreaterThanOrEqual(1, $diag['wire_airprice_node_count']);
        $this->assertTrue($diag['wire_airprice_has_validating_carrier']);
        $this->assertContains('EK', $diag['wire_airprice_validating_carriers_sanitized']);
        $this->assertTrue($diag['has_air_price']);
        $this->assertTrue($diag['wire_airprice_has_passenger_type']);
        $this->assertGreaterThanOrEqual(1, $diag['wire_airprice_passenger_type_count']);
        $this->assertTrue($diag['wire_root_air_price_retain_present']);
        $this->assertSame([], data_get($payload, 'safe_wire_preview.CreatePassengerNameRecordRQ.AirPrice'));
        $this->assertAirpriceDiagnosticsDoNotExposePii($payload);
    }

    public function test_airprice_wire_diagnostics_do_not_expose_pii(): void
    {
        Config::set('app.env', 'testing');
        Config::set('suppliers.sabre.booking_mode', 'pnr_only');
        Config::set('suppliers.sabre.booking_schema', 'create_passenger_name_record');
        Config::set('suppliers.sabre.ticketing_enabled', false);

        $shopFixture = $this->shopFixtureWithBookingCode('Y');
        Http::fake([
            '*/v2/auth/token*' => Http::response(['access_token' => 'fake-token-for-tests-only', 'expires_in' => 1800], 200),
            '*v4/offers/shop*' => Http::response($shopFixture, 200),
        ]);
        $conn = $this->seedSabreConnection();

        Artisan::call('sabre:cert-gds-cpnr-report', [
            '--connection' => (string) $conn->id,
            '--from' => 'LHE',
            '--to' => 'DXB',
            '--date' => '2026-08-15',
            '--scenario' => 'ow_direct',
            '--carrier' => 'EK',
            '--json' => true,
        ]);

        $payload = $this->decodeReportOutput(Artisan::output());
        $this->assertAirpriceDiagnosticsDoNotExposePii($payload);
    }

    public function test_iati_style_uses_v24_endpoint(): void
    {
        Config::set('app.env', 'testing');
        $shopFixture = $this->shopFixtureWithBookingCode('Y');
        Http::fake([
            '*/v2/auth/token*' => Http::response(['access_token' => 'fake-token-for-tests-only', 'expires_in' => 1800], 200),
            '*v4/offers/shop*' => Http::response($shopFixture, 200),
        ]);
        $conn = $this->seedSabreConnection();

        Artisan::call('sabre:cert-gds-cpnr-report', [
            '--connection' => (string) $conn->id,
            '--from' => 'LHE',
            '--to' => 'DXB',
            '--date' => '2026-08-15',
            '--scenario' => 'ow_direct',
            '--carrier' => 'EK',
            '--style' => SabreBookingPayloadBuilder::IATI_LIKE_CPNR_V2_4_GDS,
            '--json' => true,
        ]);

        $payload = $this->decodeReportOutput(Artisan::output());
        $this->assertSame(SabreBookingPayloadBuilder::IATI_LIKE_CPNR_V2_4_GDS, $payload['cpnr_config']['payload_style']);
        $this->assertStringContainsString('/v2.4.0/passenger/records', $payload['cpnr_config']['endpoint']);
    }

    public function test_no_eligible_offers_returns_selection_error(): void
    {
        Config::set('app.env', 'testing');
        $shopFixture = json_decode(
            file_get_contents(base_path('tests/Fixtures/sabre_bfm_v4_grouped_refs_response.json')),
            true,
        );
        Http::fake([
            '*/v2/auth/token*' => Http::response(['access_token' => 'fake-token-for-tests-only', 'expires_in' => 1800], 200),
            '*v4/offers/shop*' => Http::response($shopFixture, 200),
        ]);
        $conn = $this->seedSabreConnection();

        $exit = Artisan::call('sabre:cert-gds-cpnr-report', [
            '--connection' => (string) $conn->id,
            '--from' => 'LHE',
            '--to' => 'DXB',
            '--date' => '2026-08-15',
            '--scenario' => 'ow_direct',
            '--carrier' => 'ZZ',
            '--json' => true,
        ]);

        $this->assertSame(1, $exit);
        $payload = $this->decodeReportOutput(Artisan::output());
        $this->assertSame('no_eligible_gds_offer', $payload['selection_error']);
        $this->assertSame(0, $payload['eligible_offer_count']);
    }

    public function test_send_refused_without_confirm_yes(): void
    {
        Config::set('app.env', 'testing');
        Config::set('suppliers.sabre.ticketing_enabled', false);
        $conn = $this->seedSabreConnection();

        $exit = Artisan::call('sabre:cert-gds-cpnr-report', [
            '--connection' => (string) $conn->id,
            '--send' => true,
        ]);

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('--confirm-cert-pnr-send=YES', Artisan::output());
    }

    public function test_send_refused_when_ticketing_enabled(): void
    {
        Config::set('app.env', 'testing');
        Config::set('suppliers.sabre.ticketing_enabled', true);
        $conn = $this->seedSabreConnection();

        $exit = Artisan::call('sabre:cert-gds-cpnr-report', [
            '--connection' => (string) $conn->id,
            '--send' => true,
            '--confirm-cert-pnr-send' => 'YES',
        ]);

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('ticketing_enabled', Artisan::output());
    }

    public function test_send_blocked_for_non_default_endpoint(): void
    {
        Config::set('app.env', 'testing');
        Config::set('suppliers.sabre.booking_mode', 'pnr_only');
        Config::set('suppliers.sabre.ticketing_enabled', false);
        $shopFixture = $this->shopFixtureWithBookingCode('Y');
        Http::fake([
            '*/v2/auth/token*' => Http::response(['access_token' => 'fake-token-for-tests-only', 'expires_in' => 1800], 200),
            '*v4/offers/shop*' => Http::response($shopFixture, 200),
        ]);
        $conn = $this->seedSabreConnection();

        $exit = Artisan::call('sabre:cert-gds-cpnr-report', [
            '--connection' => (string) $conn->id,
            '--from' => 'LHE',
            '--to' => 'DXB',
            '--date' => '2026-08-15',
            '--scenario' => 'ow_direct',
            '--carrier' => 'EK',
            '--endpoint' => '/v2.4.0/passenger/records?mode=create',
            '--send' => true,
            '--confirm-cert-pnr-send' => 'YES',
            '--json' => true,
        ]);

        $this->assertSame(1, $exit);
        $payload = $this->decodeReportOutput(Artisan::output());
        $this->assertSame('cert_gds_cpnr_send_v1', $payload['report_version']);
        $this->assertFalse($payload['send_result']['attempted']);
        $this->assertSame('send_requires_certified_style_endpoint_pair', $payload['send_result']['block_reason']);
    }

    public function test_send_blocked_for_unlisted_compare_style(): void
    {
        Config::set('app.env', 'testing');
        Config::set('suppliers.sabre.booking_mode', 'pnr_only');
        Config::set('suppliers.sabre.ticketing_enabled', false);
        $shopFixture = $this->shopFixtureWithBookingCode('Y');
        Http::fake([
            '*/v2/auth/token*' => Http::response(['access_token' => 'fake-token-for-tests-only', 'expires_in' => 1800], 200),
            '*v4/offers/shop*' => Http::response($shopFixture, 200),
        ]);
        $conn = $this->seedSabreConnection();

        $exit = Artisan::call('sabre:cert-gds-cpnr-report', [
            '--connection' => (string) $conn->id,
            '--from' => 'LHE',
            '--to' => 'DXB',
            '--date' => '2026-08-15',
            '--scenario' => 'ow_direct',
            '--carrier' => 'EK',
            '--style' => SabreBookingPayloadBuilder::TRADITIONAL_PNR_CREATE_PASSENGER_NAME_RECORD_V1_AIRPRICE_VALIDATING_CARRIER_COMPARE_V1,
            '--send' => true,
            '--confirm-cert-pnr-send' => 'YES',
            '--json' => true,
        ]);

        $this->assertSame(1, $exit);
        $payload = $this->decodeReportOutput(Artisan::output());
        $this->assertSame('send_requires_certified_style_endpoint_pair', $payload['send_result']['block_reason']);
    }

    public function test_send_allows_iati_v24_style_endpoint_pair(): void
    {
        Config::set('app.env', 'testing');
        Config::set('suppliers.sabre.booking_mode', 'pnr_only');
        Config::set('suppliers.sabre.booking_schema', 'create_passenger_name_record');
        Config::set('suppliers.sabre.ticketing_enabled', false);
        $shopFixture = $this->shopFixtureWithBookingCode('Y');
        Http::fake([
            '*/v2/auth/token*' => Http::response(['access_token' => 'fake-token-for-tests-only', 'expires_in' => 1800], 200),
            '*v4/offers/shop*' => Http::response($shopFixture, 200),
            '*passenger/records*' => Http::response([
                'CreatePassengerNameRecordRS' => [
                    'ApplicationResults' => ['status' => 'Complete'],
                    'ItineraryRef' => ['ID' => 'IATIPNR1'],
                ],
            ], 200),
        ]);
        $conn = $this->seedSabreConnection();

        $exit = Artisan::call('sabre:cert-gds-cpnr-report', [
            '--connection' => (string) $conn->id,
            '--from' => 'LHE',
            '--to' => 'DXB',
            '--date' => '2026-08-15',
            '--scenario' => 'ow_direct',
            '--carrier' => 'EK',
            '--style' => SabreBookingPayloadBuilder::IATI_LIKE_CPNR_V2_4_GDS,
            '--endpoint' => SabreBookingPayloadBuilder::PASSENGER_RECORDS_V24_CREATE_PATH,
            '--send' => true,
            '--confirm-cert-pnr-send' => 'YES',
            '--json' => true,
        ]);

        $this->assertSame(0, $exit);
        $payload = $this->decodeReportOutput(Artisan::output());
        $this->assertTrue($payload['cpnr_config']['send_enabled']);
        $this->assertSame(SabreBookingPayloadBuilder::IATI_LIKE_CPNR_V2_4_GDS, $payload['cpnr_config']['payload_style']);
        $this->assertSame(SabreBookingPayloadBuilder::PASSENGER_RECORDS_V24_CREATE_PATH, $payload['cpnr_config']['endpoint']);
        $this->assertTrue($payload['send_result']['attempted']);
        $this->assertTrue($payload['send_result']['pnr_created']);
        $this->assertSame('IATIPNR1', $payload['send_result']['pnr']);
        $this->assertFalse($payload['send_result']['ticketing_attempted']);
        $this->assertFalse($payload['send_result']['cancel_attempted']);
    }

    public function test_send_blocked_when_auto_pnr_pricing_context_not_ready(): void
    {
        $evaluation = $this->evaluateSendGates([
            'distribution_channel' => 'gds',
            'cpnr_eligible' => true,
            'auto_pnr_pricing_context_ready' => false,
            'segment_count' => 1,
        ], 'ow_direct', true, SabreBookingPayloadBuilder::TRADITIONAL_PNR_CREATE_PASSENGER_NAME_RECORD_V1, SabreCertGdsCpnrReportCommand::EXPECTED_FIRST_SEND_ENDPOINT);

        $this->assertSame('send_requires_auto_pnr_pricing_context_ready', $evaluation['block_reason']);
    }

    public function test_send_blocked_for_non_pk_two_segment_connecting(): void
    {
        $evaluation = $this->evaluateSendGates([
            'distribution_channel' => 'gds',
            'cpnr_eligible' => true,
            'auto_pnr_pricing_context_ready' => true,
            'segment_count' => 2,
            'validating_carrier' => 'EK',
            'marketing_carriers' => ['EK'],
            'carrier_chain' => 'EK',
            'connecting_carrier_profile' => 'same_carrier',
        ], 'ow_connecting', true, SabreBookingPayloadBuilder::IATI_LIKE_CPNR_V2_4_GDS, SabreBookingPayloadBuilder::PASSENGER_RECORDS_V24_CREATE_PATH);

        $this->assertSame('send_blocks_non_pk_same_carrier_connecting', $evaluation['block_reason']);
        $this->assertFalse($evaluation['send_gate_summary']['send_scenario_allowed']);
        $this->assertFalse($evaluation['send_gate_summary']['carrier_chain_valid']);
    }

    public function test_send_blocked_for_pk_two_segment_with_traditional_v25(): void
    {
        $evaluation = $this->evaluateSendGates($this->pkSameCarrierTwoSegmentRow(), 'ow_connecting', true, SabreBookingPayloadBuilder::TRADITIONAL_PNR_CREATE_PASSENGER_NAME_RECORD_V1, SabreCertGdsCpnrReportCommand::EXPECTED_FIRST_SEND_ENDPOINT);

        $this->assertSame('send_pk_two_segment_requires_iati_v24_only', $evaluation['block_reason']);
        $this->assertSame('pk_same_carrier_two_segment', $evaluation['send_gate_summary']['send_scenario_type']);
        $this->assertTrue($evaluation['send_gate_summary']['carrier_chain_valid']);
        $this->assertTrue($evaluation['send_gate_summary']['segment_count_allowed']);
    }

    public function test_send_blocked_for_mixed_carrier_two_segment(): void
    {
        $evaluation = $this->evaluateSendGates([
            'distribution_channel' => 'gds',
            'cpnr_eligible' => true,
            'auto_pnr_pricing_context_ready' => true,
            'segment_count' => 2,
            'validating_carrier' => 'PK',
            'marketing_carriers' => ['PK', 'EK'],
            'carrier_chain' => 'PK+EK',
            'connecting_carrier_profile' => 'mixed_carrier',
        ], 'ow_connecting', true, SabreBookingPayloadBuilder::IATI_LIKE_CPNR_V2_4_GDS, SabreBookingPayloadBuilder::PASSENGER_RECORDS_V24_CREATE_PATH);

        $this->assertSame('send_blocks_mixed_carrier_connecting', $evaluation['block_reason']);
        $this->assertTrue($evaluation['send_gate_summary']['mixed_carrier_detected']);
    }

    public function test_send_blocked_for_segment_count_above_two(): void
    {
        $evaluation = $this->evaluateSendGates([
            'distribution_channel' => 'gds',
            'cpnr_eligible' => true,
            'auto_pnr_pricing_context_ready' => true,
            'segment_count' => 3,
            'validating_carrier' => 'PK',
            'marketing_carriers' => ['PK'],
            'carrier_chain' => 'PK',
        ], 'ow_connecting', true, SabreBookingPayloadBuilder::IATI_LIKE_CPNR_V2_4_GDS, SabreBookingPayloadBuilder::PASSENGER_RECORDS_V24_CREATE_PATH);

        $this->assertSame('send_blocks_segment_count_above_two', $evaluation['block_reason']);
        $this->assertFalse($evaluation['send_gate_summary']['segment_count_allowed']);
    }

    public function test_send_allows_pk_same_carrier_two_segment_iati_v24(): void
    {
        Config::set('app.env', 'testing');
        Config::set('suppliers.sabre.booking_mode', 'pnr_only');
        Config::set('suppliers.sabre.booking_schema', 'create_passenger_name_record');
        Config::set('suppliers.sabre.ticketing_enabled', false);
        $shopFixture = $this->shopFixturePkConnectingLheJed();
        Http::fake([
            '*/v2/auth/token*' => Http::response(['access_token' => 'fake-token-for-tests-only', 'expires_in' => 1800], 200),
            '*v4/offers/shop*' => Http::response($shopFixture, 200),
            '*passenger/records*' => Http::response([
                'CreatePassengerNameRecordRS' => [
                    'ApplicationResults' => ['status' => 'Complete'],
                    'ItineraryRef' => ['ID' => 'PK2PNR01'],
                ],
            ], 200),
        ]);
        $conn = $this->seedSabreConnection();

        $exit = Artisan::call('sabre:cert-gds-cpnr-report', [
            '--connection' => (string) $conn->id,
            '--from' => 'LHE',
            '--to' => 'JED',
            '--date' => '2026-08-15',
            '--scenario' => 'ow_connecting',
            '--carrier' => 'PK',
            '--style' => SabreBookingPayloadBuilder::IATI_LIKE_CPNR_V2_4_GDS,
            '--endpoint' => SabreBookingPayloadBuilder::PASSENGER_RECORDS_V24_CREATE_PATH,
            '--send' => true,
            '--confirm-cert-pnr-send' => 'YES',
            '--json' => true,
        ]);

        $this->assertSame(0, $exit);
        $payload = $this->decodeReportOutput(Artisan::output());
        $this->assertTrue($payload['send_gate_summary']['send_scenario_allowed']);
        $this->assertSame('pk_same_carrier_two_segment', $payload['send_gate_summary']['send_scenario_type']);
        $this->assertTrue($payload['send_gate_summary']['carrier_chain_valid']);
        $this->assertTrue($payload['send_gate_summary']['segment_count_allowed']);
        $this->assertFalse($payload['send_gate_summary']['mixed_carrier_detected']);
        $this->assertTrue($payload['send_result']['attempted']);
        $this->assertTrue($payload['send_result']['pnr_created']);
        $this->assertSame('PK2PNR01', $payload['send_result']['pnr']);
        $this->assertFalse($payload['send_result']['ticketing_attempted']);
        $this->assertFalse($payload['send_result']['cancel_attempted']);
    }

    public function test_preview_includes_send_gate_summary_for_pk_connecting(): void
    {
        Config::set('app.env', 'testing');
        $shopFixture = $this->shopFixturePkConnectingLheJed();
        Http::fake([
            '*/v2/auth/token*' => Http::response(['access_token' => 'fake-token-for-tests-only', 'expires_in' => 1800], 200),
            '*v4/offers/shop*' => Http::response($shopFixture, 200),
        ]);
        $conn = $this->seedSabreConnection();

        Artisan::call('sabre:cert-gds-cpnr-report', [
            '--connection' => (string) $conn->id,
            '--from' => 'LHE',
            '--to' => 'JED',
            '--date' => '2026-08-15',
            '--scenario' => 'ow_connecting',
            '--carrier' => 'PK',
            '--style' => SabreBookingPayloadBuilder::IATI_LIKE_CPNR_V2_4_GDS,
            '--endpoint' => SabreBookingPayloadBuilder::PASSENGER_RECORDS_V24_CREATE_PATH,
            '--json' => true,
        ]);

        $payload = $this->decodeReportOutput(Artisan::output());
        $this->assertArrayHasKey('send_gate_summary', $payload);
        $this->assertTrue($payload['send_gate_summary']['send_scenario_allowed']);
        $this->assertSame('pk_same_carrier_two_segment', $payload['send_gate_summary']['send_scenario_type']);
        $this->assertSame(2, $payload['selected_offer']['segment_count']);
    }

    public function test_send_blocked_for_gf_two_segment_connecting(): void
    {
        $evaluation = $this->evaluateSendGates([
            'distribution_channel' => 'gds',
            'cpnr_eligible' => true,
            'auto_pnr_pricing_context_ready' => true,
            'segment_count' => 2,
            'validating_carrier' => 'GF',
            'marketing_carriers' => ['GF', 'GF'],
            'carrier_chain' => 'GF',
            'connecting_carrier_profile' => 'same_carrier',
        ], 'ow_connecting', true, SabreBookingPayloadBuilder::IATI_LIKE_CPNR_V2_4_GDS, SabreBookingPayloadBuilder::PASSENGER_RECORDS_V24_CREATE_PATH);

        $this->assertSame('send_blocks_non_pk_same_carrier_connecting', $evaluation['block_reason']);
        $this->assertFalse($evaluation['send_gate_summary']['send_scenario_allowed']);
        $this->assertFalse($evaluation['send_gate_summary']['carrier_chain_valid']);
    }

    public function test_send_allows_qr_same_carrier_two_segment_iati_v24(): void
    {
        Config::set('app.env', 'testing');
        Config::set('suppliers.sabre.booking_mode', 'pnr_only');
        Config::set('suppliers.sabre.booking_schema', 'create_passenger_name_record');
        Config::set('suppliers.sabre.ticketing_enabled', false);
        Http::fake([
            '*/v2/auth/token*' => Http::response(['access_token' => 'fake-token-for-tests-only', 'expires_in' => 1800], 200),
            '*v4/offers/shop*' => Http::response($this->shopFixtureQrConnectingLheJed(), 200),
            '*passenger/records*' => Http::response([
                'CreatePassengerNameRecordRS' => [
                    'ApplicationResults' => ['status' => 'Complete'],
                    'ItineraryRef' => ['ID' => 'QR2PNR01'],
                ],
            ], 200),
        ]);
        $conn = $this->seedSabreConnection();

        $exit = Artisan::call('sabre:cert-gds-cpnr-report', [
            '--connection' => (string) $conn->id,
            '--from' => 'LHE',
            '--to' => 'JED',
            '--date' => '2026-08-15',
            '--scenario' => 'ow_connecting',
            '--carrier' => 'QR',
            '--style' => SabreBookingPayloadBuilder::IATI_LIKE_CPNR_V2_4_GDS,
            '--endpoint' => SabreBookingPayloadBuilder::PASSENGER_RECORDS_V24_CREATE_PATH,
            '--send' => true,
            '--confirm-cert-pnr-send' => 'YES',
            '--json' => true,
        ]);

        $this->assertSame(0, $exit);
        $payload = $this->decodeReportOutput(Artisan::output());
        $this->assertTrue($payload['send_gate_summary']['send_scenario_allowed']);
        $this->assertSame('qr_same_carrier_two_segment', $payload['send_gate_summary']['send_scenario_type']);
        $this->assertTrue($payload['send_gate_summary']['carrier_chain_valid']);
        $this->assertTrue($payload['send_gate_summary']['segment_count_allowed']);
        $this->assertFalse($payload['send_gate_summary']['mixed_carrier_detected']);
        $this->assertTrue($payload['send_result']['attempted']);
        $this->assertTrue($payload['send_result']['pnr_created']);
        $this->assertSame('QR2PNR01', $payload['send_result']['pnr']);
        $this->assertFalse($payload['send_result']['ticketing_attempted']);
        $this->assertFalse($payload['send_result']['cancel_attempted']);
    }

    public function test_preview_includes_send_gate_summary_for_qr_connecting(): void
    {
        Config::set('app.env', 'testing');
        Http::fake([
            '*/v2/auth/token*' => Http::response(['access_token' => 'fake-token-for-tests-only', 'expires_in' => 1800], 200),
            '*v4/offers/shop*' => Http::response($this->shopFixtureQrConnectingLheJed(), 200),
        ]);
        $conn = $this->seedSabreConnection();

        Artisan::call('sabre:cert-gds-cpnr-report', [
            '--connection' => (string) $conn->id,
            '--from' => 'LHE',
            '--to' => 'JED',
            '--date' => '2026-08-15',
            '--scenario' => 'ow_connecting',
            '--carrier' => 'QR',
            '--style' => SabreBookingPayloadBuilder::IATI_LIKE_CPNR_V2_4_GDS,
            '--endpoint' => SabreBookingPayloadBuilder::PASSENGER_RECORDS_V24_CREATE_PATH,
            '--json' => true,
        ]);

        $payload = $this->decodeReportOutput(Artisan::output());
        $this->assertArrayHasKey('send_gate_summary', $payload);
        $this->assertTrue($payload['send_gate_summary']['send_scenario_allowed']);
        $this->assertSame('qr_same_carrier_two_segment', $payload['send_gate_summary']['send_scenario_type']);
        $this->assertSame(2, $payload['selected_offer']['segment_count']);
        $this->assertSame('QR', $payload['selected_offer']['validating_carrier'] ?? null);
    }

    public function test_send_blocked_for_qr_two_segment_with_traditional_v25(): void
    {
        $evaluation = $this->evaluateSendGates($this->qrSameCarrierTwoSegmentRow(), 'ow_connecting', true, SabreBookingPayloadBuilder::TRADITIONAL_PNR_CREATE_PASSENGER_NAME_RECORD_V1, SabreCertGdsCpnrReportCommand::EXPECTED_FIRST_SEND_ENDPOINT);

        $this->assertSame('send_qr_two_segment_requires_iati_v24_only', $evaluation['block_reason']);
        $this->assertSame('qr_same_carrier_two_segment', $evaluation['send_gate_summary']['send_scenario_type']);
        $this->assertTrue($evaluation['send_gate_summary']['carrier_chain_valid']);
        $this->assertTrue($evaluation['send_gate_summary']['segment_count_allowed']);
    }

    public function test_preview_allow_nn_diagnostic_omits_nn_for_qr_two_segment(): void
    {
        Config::set('app.env', 'testing');
        Config::set('suppliers.sabre.booking_mode', 'pnr_only');
        Config::set('suppliers.sabre.ticketing_enabled', false);
        Config::set('suppliers.sabre.cancel_enabled', true);
        Http::fake([
            '*/v2/auth/token*' => Http::response(['access_token' => 'fake-token-for-tests-only', 'expires_in' => 1800], 200),
            '*v4/offers/shop*' => Http::response($this->shopFixtureQrConnectingLheJed(), 200),
        ]);
        $conn = $this->seedSabreConnection();

        $exit = Artisan::call('sabre:cert-gds-cpnr-report', [
            '--connection' => (string) $conn->id,
            '--from' => 'LHE',
            '--to' => 'JED',
            '--date' => '2026-08-15',
            '--scenario' => 'ow_connecting',
            '--carrier' => 'QR',
            '--style' => SabreBookingPayloadBuilder::IATI_LIKE_CPNR_V2_4_GDS,
            '--endpoint' => SabreBookingPayloadBuilder::PASSENGER_RECORDS_V24_CREATE_PATH,
            '--allow-nn-cert-diagnostic' => 'YES',
            '--json' => true,
        ]);

        $this->assertSame(0, $exit);
        $payload = $this->decodeReportOutput(Artisan::output());
        $this->assertTrue($payload['allow_nn_cert_diagnostic']);
        $this->assertTrue($payload['wire_halt_on_status_nn_omitted']);
        $this->assertNotContains('NN', $payload['wire_halt_on_status_codes_sanitized']);
        $this->assertNotContains('WN', $payload['wire_halt_on_status_codes_sanitized']);
        $this->assertFalse($payload['cpnr_config']['cancel_enabled']);
        $this->assertArrayHasKey('final_wire_fingerprint', $payload);
        $this->assertFalse($payload['final_wire_fingerprint']['final_wire_contains_nn_halt']);
        $this->assertFalse($payload['final_wire_fingerprint']['final_wire_contains_wn_halt']);
        $this->assertTrue($payload['preview_final_wire_fingerprint_match']);
    }

    public function test_send_allow_nn_diagnostic_allowed_for_qr_two_segment(): void
    {
        Config::set('app.env', 'testing');
        Config::set('suppliers.sabre.booking_mode', 'pnr_only');
        Config::set('suppliers.sabre.booking_schema', 'create_passenger_name_record');
        Config::set('suppliers.sabre.ticketing_enabled', false);
        Config::set('suppliers.sabre.cancel_enabled', true);
        Http::fake([
            '*/v2/auth/token*' => Http::response(['access_token' => 'fake-token-for-tests-only', 'expires_in' => 1800], 200),
            '*v4/offers/shop*' => Http::response($this->shopFixtureQrConnectingLheJed(), 200),
            '*passenger/records*' => Http::response([
                'CreatePassengerNameRecordRS' => [
                    'ApplicationResults' => ['status' => 'Complete'],
                    'ItineraryRef' => ['ID' => 'QR2PNR02'],
                ],
            ], 200),
        ]);
        $conn = $this->seedSabreConnection();

        $exit = Artisan::call('sabre:cert-gds-cpnr-report', [
            '--connection' => (string) $conn->id,
            '--from' => 'LHE',
            '--to' => 'JED',
            '--date' => '2026-08-15',
            '--scenario' => 'ow_connecting',
            '--carrier' => 'QR',
            '--style' => SabreBookingPayloadBuilder::IATI_LIKE_CPNR_V2_4_GDS,
            '--endpoint' => SabreBookingPayloadBuilder::PASSENGER_RECORDS_V24_CREATE_PATH,
            '--allow-nn-cert-diagnostic' => 'YES',
            '--send' => true,
            '--confirm-cert-pnr-send' => 'YES',
            '--json' => true,
        ]);

        $this->assertSame(0, $exit);
        $payload = $this->decodeReportOutput(Artisan::output());
        $this->assertTrue($payload['allow_nn_cert_diagnostic']);
        $this->assertTrue($payload['wire_halt_on_status_nn_omitted']);
        $this->assertFalse($payload['final_wire_fingerprint']['final_wire_contains_nn_halt']);
        $this->assertTrue($payload['preview_final_wire_fingerprint_match']);
        $this->assertFalse($payload['cpnr_config']['cancel_enabled']);
        $this->assertTrue($payload['send_result']['attempted']);
        $this->assertTrue($payload['send_result']['pnr_created']);
        $this->assertFalse($payload['send_result']['ticketing_attempted']);
        $this->assertFalse($payload['send_result']['cancel_attempted']);
    }

    public function test_allow_nn_diagnostic_blocked_for_gf_two_segment(): void
    {
        Config::set('app.env', 'testing');
        Config::set('suppliers.sabre.ticketing_enabled', false);
        Config::set('suppliers.sabre.cancel_enabled', false);
        Http::fake([
            '*/v2/auth/token*' => Http::response(['access_token' => 'fake-token-for-tests-only', 'expires_in' => 1800], 200),
            '*v4/offers/shop*' => Http::response($this->shopFixtureGfConnectingLheJed(), 200),
        ]);
        $conn = $this->seedSabreConnection();

        $exit = Artisan::call('sabre:cert-gds-cpnr-report', [
            '--connection' => (string) $conn->id,
            '--from' => 'LHE',
            '--to' => 'JED',
            '--date' => '2026-08-15',
            '--scenario' => 'ow_connecting',
            '--carrier' => 'GF',
            '--style' => SabreBookingPayloadBuilder::IATI_LIKE_CPNR_V2_4_GDS,
            '--endpoint' => SabreBookingPayloadBuilder::PASSENGER_RECORDS_V24_CREATE_PATH,
            '--allow-nn-cert-diagnostic' => 'YES',
            '--json' => true,
        ]);

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('allow_nn_cert_diagnostic_requires_pk_or_qr_same_carrier', Artisan::output());
    }

    public function test_send_blocked_for_non_gds_distribution_channel(): void
    {
        $evaluation = $this->evaluateSendGates([
            'distribution_channel' => 'ndc',
            'cpnr_eligible' => false,
            'auto_pnr_pricing_context_ready' => true,
            'segment_count' => 1,
        ], 'ow_direct', true, SabreBookingPayloadBuilder::TRADITIONAL_PNR_CREATE_PASSENGER_NAME_RECORD_V1, SabreCertGdsCpnrReportCommand::EXPECTED_FIRST_SEND_ENDPOINT);

        $this->assertSame('send_requires_gds_distribution_channel', $evaluation['block_reason']);
    }

    public function test_send_succeeds_when_all_gates_pass(): void
    {
        Config::set('app.env', 'testing');
        Config::set('suppliers.sabre.booking_mode', 'pnr_only');
        Config::set('suppliers.sabre.booking_schema', 'create_passenger_name_record');
        Config::set('suppliers.sabre.ticketing_enabled', false);
        $shopFixture = $this->shopFixtureWithBookingCode('Y');
        Http::fake([
            '*/v2/auth/token*' => Http::response(['access_token' => 'fake-token-for-tests-only', 'expires_in' => 1800], 200),
            '*v4/offers/shop*' => Http::response($shopFixture, 200),
            '*passenger/records*' => Http::response([
                'CreatePassengerNameRecordRS' => [
                    'ApplicationResults' => ['status' => 'Complete'],
                    'ItineraryRef' => ['ID' => 'CERTPNR1'],
                ],
            ], 200),
        ]);
        $conn = $this->seedSabreConnection();

        $exit = Artisan::call('sabre:cert-gds-cpnr-report', [
            '--connection' => (string) $conn->id,
            '--from' => 'LHE',
            '--to' => 'DXB',
            '--date' => '2026-08-15',
            '--scenario' => 'ow_direct',
            '--carrier' => 'EK',
            '--send' => true,
            '--confirm-cert-pnr-send' => 'YES',
            '--json' => true,
        ]);

        $this->assertSame(0, $exit);
        $payload = $this->decodeReportOutput(Artisan::output());
        $this->assertSame('cert_gds_cpnr_send_v1', $payload['report_version']);
        $this->assertTrue($payload['cpnr_config']['send_enabled']);
        $this->assertFalse($payload['cpnr_config']['ticketing_enabled']);
        $this->assertFalse($payload['cpnr_config']['cancel_enabled']);
        $this->assertTrue($payload['send_result']['attempted']);
        $this->assertSame(200, $payload['send_result']['http_status']);
        $this->assertTrue($payload['send_result']['success']);
        $this->assertTrue($payload['send_result']['pnr_created']);
        $this->assertSame('CERTPNR1', $payload['send_result']['pnr']);
        $this->assertFalse($payload['send_result']['ticketing_attempted']);
        $this->assertFalse($payload['send_result']['cancel_attempted']);
        $this->assertSame('success_hk', $payload['send_result']['host_status_classification']);
        $this->assertSame('cert_cpnr_send_pnr_created', $payload['reason_code']);
    }

    public function test_send_output_does_not_leak_secrets(): void
    {
        Config::set('app.env', 'testing');
        Config::set('suppliers.sabre.booking_mode', 'pnr_only');
        Config::set('suppliers.sabre.ticketing_enabled', false);
        $shopFixture = $this->shopFixtureWithBookingCode('Y');
        Http::fake([
            '*/v2/auth/token*' => Http::response(['access_token' => 'SEND_SECRET_TOKEN_VALUE', 'expires_in' => 1800], 200),
            '*v4/offers/shop*' => Http::response($shopFixture, 200),
            '*passenger/records*' => Http::response([
                'CreatePassengerNameRecordRS' => [
                    'ApplicationResults' => ['status' => 'Complete'],
                    'ItineraryRef' => ['ID' => 'ABCDEF'],
                ],
            ], 200),
        ]);
        $conn = $this->seedSabreConnection();
        $conn->credentials = [
            'client_id' => 'send_ci_user',
            'client_secret' => 'send_ci_super_secret',
            'pcc' => 'TEST',
            'password' => 'send_ci_password',
        ];
        $conn->save();
        Cache::flush();

        Artisan::call('sabre:cert-gds-cpnr-report', [
            '--connection' => (string) $conn->id,
            '--from' => 'LHE',
            '--to' => 'DXB',
            '--date' => '2026-08-15',
            '--scenario' => 'ow_direct',
            '--send' => true,
            '--confirm-cert-pnr-send' => 'YES',
            '--json' => true,
        ]);

        $encoded = json_encode($this->decodeReportOutput(Artisan::output()));
        $lower = strtolower((string) $encoded);
        $this->assertStringNotContainsString('send_secret_token_value', $lower);
        $this->assertStringNotContainsString('send_ci_super_secret', $lower);
        $this->assertStringNotContainsString('send_ci_password', $lower);
        $this->assertStringNotContainsString('bearer ', $lower);
        $this->assertStringNotContainsString('access_token', $lower);
        $this->assertStringNotContainsString('cert-probe@example.invalid', $lower);
    }

    public function test_preview_allow_nn_diagnostic_omits_nn_for_pk_two_segment(): void
    {
        Config::set('app.env', 'testing');
        Config::set('suppliers.sabre.booking_mode', 'pnr_only');
        Config::set('suppliers.sabre.ticketing_enabled', false);
        Config::set('suppliers.sabre.cancel_enabled', true);
        Http::fake([
            '*/v2/auth/token*' => Http::response(['access_token' => 'fake-token-for-tests-only', 'expires_in' => 1800], 200),
            '*v4/offers/shop*' => Http::response($this->shopFixturePkConnectingLheJed(), 200),
        ]);
        $conn = $this->seedSabreConnection();

        $exit = Artisan::call('sabre:cert-gds-cpnr-report', [
            '--connection' => (string) $conn->id,
            '--from' => 'LHE',
            '--to' => 'JED',
            '--date' => '2026-08-15',
            '--scenario' => 'ow_connecting',
            '--carrier' => 'PK',
            '--style' => SabreBookingPayloadBuilder::IATI_LIKE_CPNR_V2_4_GDS,
            '--endpoint' => SabreBookingPayloadBuilder::PASSENGER_RECORDS_V24_CREATE_PATH,
            '--allow-nn-cert-diagnostic' => 'YES',
            '--json' => true,
        ]);

        $this->assertSame(0, $exit);
        $payload = $this->decodeReportOutput(Artisan::output());
        $this->assertTrue($payload['allow_nn_cert_diagnostic']);
        $this->assertTrue($payload['wire_halt_on_status_nn_omitted']);
        $this->assertNotContains('NN', $payload['wire_halt_on_status_codes_sanitized']);
        $this->assertNotContains('WN', $payload['wire_halt_on_status_codes_sanitized']);
        $this->assertFalse($payload['cpnr_config']['cancel_enabled']);
        $this->assertArrayHasKey('final_wire_fingerprint', $payload);
        $this->assertFalse($payload['final_wire_fingerprint']['final_wire_contains_nn_halt']);
        $this->assertFalse($payload['final_wire_fingerprint']['final_wire_contains_wn_halt']);
        $this->assertContains('NN', $payload['final_wire_fingerprint']['final_wire_flight_segment_statuses']);
        $this->assertTrue($payload['preview_final_wire_fingerprint_match']);
    }

    public function test_preview_includes_final_wire_fingerprint_and_output_is_safe(): void
    {
        Config::set('app.env', 'testing');
        Config::set('suppliers.sabre.booking_mode', 'pnr_only');
        Config::set('suppliers.sabre.ticketing_enabled', false);
        Config::set('suppliers.sabre.cancel_enabled', false);
        Http::fake([
            '*/v2/auth/token*' => Http::response(['access_token' => 'fake-token-for-tests-only', 'expires_in' => 1800], 200),
            '*v4/offers/shop*' => Http::response($this->shopFixtureWithBookingCode('Y'), 200),
        ]);
        $conn = $this->seedSabreConnection();

        $exit = Artisan::call('sabre:cert-gds-cpnr-report', [
            '--connection' => (string) $conn->id,
            '--from' => 'LHE',
            '--to' => 'DXB',
            '--date' => '2026-08-15',
            '--scenario' => 'ow_direct',
            '--json' => true,
        ]);

        $this->assertSame(0, $exit);
        $payload = $this->decodeReportOutput(Artisan::output());
        $this->assertArrayHasKey('final_wire_fingerprint', $payload);
        $this->assertTrue($payload['final_wire_fingerprint']['final_wire_contains_nn_halt']);
        $this->assertTrue($payload['preview_final_wire_fingerprint_match']);
        app(SabrePnrCertificationSupport::class)->assertOutputSafe($payload);
    }

    public function test_send_allow_nn_diagnostic_allowed_when_global_cancel_enabled(): void
    {
        Config::set('app.env', 'testing');
        Config::set('suppliers.sabre.booking_mode', 'pnr_only');
        Config::set('suppliers.sabre.booking_schema', 'create_passenger_name_record');
        Config::set('suppliers.sabre.ticketing_enabled', false);
        Config::set('suppliers.sabre.cancel_enabled', true);
        Http::fake([
            '*/v2/auth/token*' => Http::response(['access_token' => 'fake-token-for-tests-only', 'expires_in' => 1800], 200),
            '*v4/offers/shop*' => Http::response($this->shopFixturePkConnectingLheJed(), 200),
            '*passenger/records*' => Http::response([
                'CreatePassengerNameRecordRS' => [
                    'ApplicationResults' => ['status' => 'Complete'],
                    'ItineraryRef' => ['ID' => 'PK2PNR02'],
                ],
            ], 200),
        ]);
        $conn = $this->seedSabreConnection();

        $exit = Artisan::call('sabre:cert-gds-cpnr-report', [
            '--connection' => (string) $conn->id,
            '--from' => 'LHE',
            '--to' => 'JED',
            '--date' => '2026-08-15',
            '--scenario' => 'ow_connecting',
            '--carrier' => 'PK',
            '--style' => SabreBookingPayloadBuilder::IATI_LIKE_CPNR_V2_4_GDS,
            '--endpoint' => SabreBookingPayloadBuilder::PASSENGER_RECORDS_V24_CREATE_PATH,
            '--allow-nn-cert-diagnostic' => 'YES',
            '--send' => true,
            '--confirm-cert-pnr-send' => 'YES',
            '--json' => true,
        ]);

        $this->assertSame(0, $exit);
        $payload = $this->decodeReportOutput(Artisan::output());
        $this->assertTrue($payload['allow_nn_cert_diagnostic']);
        $this->assertTrue($payload['wire_halt_on_status_nn_omitted']);
        $this->assertFalse($payload['final_wire_fingerprint']['final_wire_contains_nn_halt']);
        $this->assertTrue($payload['preview_final_wire_fingerprint_match']);
        $this->assertFalse($payload['cpnr_config']['cancel_enabled']);
        $this->assertTrue($payload['send_result']['attempted']);
        $this->assertTrue($payload['send_result']['pnr_created']);
        $this->assertFalse($payload['send_result']['cancel_attempted']);
    }

    public function test_allow_nn_diagnostic_blocked_for_non_iati_style(): void
    {
        Config::set('app.env', 'testing');
        Config::set('suppliers.sabre.ticketing_enabled', false);
        Config::set('suppliers.sabre.cancel_enabled', false);
        Http::fake([
            '*/v2/auth/token*' => Http::response(['access_token' => 'fake-token-for-tests-only', 'expires_in' => 1800], 200),
            '*v4/offers/shop*' => Http::response($this->shopFixtureWithBookingCode('Y'), 200),
        ]);
        $conn = $this->seedSabreConnection();

        $exit = Artisan::call('sabre:cert-gds-cpnr-report', [
            '--connection' => (string) $conn->id,
            '--from' => 'LHE',
            '--to' => 'DXB',
            '--date' => '2026-08-15',
            '--scenario' => 'ow_direct',
            '--carrier' => 'EK',
            '--allow-nn-cert-diagnostic' => 'YES',
            '--json' => true,
        ]);

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('allow_nn_cert_diagnostic_requires_iati_v24_style', Artisan::output());
    }

    public function test_allow_nn_diagnostic_blocked_for_non_v24_endpoint(): void
    {
        Config::set('app.env', 'testing');
        Config::set('suppliers.sabre.ticketing_enabled', false);
        Config::set('suppliers.sabre.cancel_enabled', false);
        Http::fake([
            '*/v2/auth/token*' => Http::response(['access_token' => 'fake-token-for-tests-only', 'expires_in' => 1800], 200),
            '*v4/offers/shop*' => Http::response($this->shopFixturePkConnectingLheJed(), 200),
        ]);
        $conn = $this->seedSabreConnection();

        $exit = Artisan::call('sabre:cert-gds-cpnr-report', [
            '--connection' => (string) $conn->id,
            '--from' => 'LHE',
            '--to' => 'JED',
            '--date' => '2026-08-15',
            '--scenario' => 'ow_connecting',
            '--carrier' => 'PK',
            '--style' => SabreBookingPayloadBuilder::IATI_LIKE_CPNR_V2_4_GDS,
            '--endpoint' => SabreCertGdsCpnrReportCommand::EXPECTED_FIRST_SEND_ENDPOINT,
            '--allow-nn-cert-diagnostic' => 'YES',
            '--json' => true,
        ]);

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('allow_nn_cert_diagnostic_requires_v24_create_endpoint', Artisan::output());
    }

    public function test_allow_nn_diagnostic_blocked_on_production_host(): void
    {
        Config::set('app.env', 'testing');
        $conn = $this->seedSabreConnection('https://api.platform.sabre.com');

        $exit = Artisan::call('sabre:cert-gds-cpnr-report', [
            '--connection' => (string) $conn->id,
            '--allow-nn-cert-diagnostic' => 'YES',
            '--json' => true,
        ]);

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('blocks api.platform.sabre.com', Artisan::output());
    }

    public function test_send_nn_halt_classified_as_pending_not_uc(): void
    {
        Config::set('app.env', 'testing');
        Config::set('suppliers.sabre.booking_mode', 'pnr_only');
        Config::set('suppliers.sabre.ticketing_enabled', false);
        Config::set('suppliers.sabre.cancel_enabled', false);
        Http::fake([
            '*/v2/auth/token*' => Http::response(['access_token' => 'fake-token-for-tests-only', 'expires_in' => 1800], 200),
            '*v4/offers/shop*' => Http::response($this->shopFixturePkConnectingLheJed(), 200),
            '*passenger/records*' => Http::response([
                'CreatePassengerNameRecordRS' => [
                    'ApplicationResults' => [
                        'status' => 'Incomplete',
                        'Error' => [
                            [
                                'SystemSpecificResults' => [
                                    [
                                        'Message' => [
                                            ['content' => 'WARN.SP.HALT_ON_STATUS_RECEIVED'],
                                            ['content' => 'Flight PK301 returned status code NN'],
                                            ['content' => 'Flight PK741 returned status code NN'],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);
        $conn = $this->seedSabreConnection();

        $exit = Artisan::call('sabre:cert-gds-cpnr-report', [
            '--connection' => (string) $conn->id,
            '--from' => 'LHE',
            '--to' => 'JED',
            '--date' => '2026-08-15',
            '--scenario' => 'ow_connecting',
            '--carrier' => 'PK',
            '--style' => SabreBookingPayloadBuilder::IATI_LIKE_CPNR_V2_4_GDS,
            '--endpoint' => SabreBookingPayloadBuilder::PASSENGER_RECORDS_V24_CREATE_PATH,
            '--send' => true,
            '--confirm-cert-pnr-send' => 'YES',
            '--json' => true,
        ]);

        $this->assertSame(1, $exit);
        $payload = $this->decodeReportOutput(Artisan::output());
        $this->assertTrue($payload['send_result']['attempted']);
        $this->assertFalse($payload['send_result']['pnr_created']);
        $this->assertSame('host_sell_pending_nn', $payload['send_result']['host_status_classification']);
        $this->assertSame('cert_cpnr_send_host_pending_nn', $payload['reason_code']);
        $this->assertFalse($payload['send_result']['ticketing_attempted']);
        $this->assertFalse($payload['send_result']['cancel_attempted']);
    }

    public function test_output_does_not_leak_secrets(): void
    {
        Config::set('app.env', 'testing');
        $shopFixture = json_decode(
            file_get_contents(base_path('tests/Fixtures/sabre_bfm_v4_grouped_refs_response.json')),
            true,
        );
        Http::fake([
            '*/v2/auth/token*' => Http::response(['access_token' => 'CPNR_SECRET_TOKEN_VALUE', 'expires_in' => 1800], 200),
            '*v4/offers/shop*' => Http::response($shopFixture, 200),
        ]);
        $conn = $this->seedSabreConnection();
        $conn->credentials = [
            'client_id' => 'cpnr_ci_user',
            'client_secret' => 'cpnr_ci_super_secret',
            'pcc' => 'TEST',
            'password' => 'cpnr_ci_password',
        ];
        $conn->save();
        Cache::flush();

        Artisan::call('sabre:cert-gds-cpnr-report', [
            '--connection' => (string) $conn->id,
            '--from' => 'LHE',
            '--to' => 'DXB',
            '--date' => '2026-08-15',
            '--scenario' => 'ow_direct',
            '--json' => true,
        ]);

        $encoded = json_encode($this->decodeReportOutput(Artisan::output()));
        $lower = strtolower((string) $encoded);
        $this->assertStringNotContainsString('cpnr_secret_token_value', $lower);
        $this->assertStringNotContainsString('cpnr_ci_super_secret', $lower);
        $this->assertStringNotContainsString('cpnr_ci_password', $lower);
        $this->assertStringNotContainsString('bearer ', $lower);
        $this->assertStringNotContainsString('access_token', $lower);
        $this->assertStringNotContainsString('cert-probe@example.invalid', $lower);
    }

    /**
     * @return array<string, mixed>
     */
    /**
     * @param  array<string, mixed>  $row
     * @return array{block_reason: ?string, send_gate_summary: array<string, mixed>}
     */
    protected function evaluateSendGates(
        array $row,
        string $scenario,
        bool $wireContractValid,
        string $style,
        string $endpoint,
    ): array {
        $command = new SabreCertGdsCpnrReportCommand;
        $method = new \ReflectionMethod(SabreCertGdsCpnrReportCommand::class, 'evaluateSendGates');
        $method->setAccessible(true);

        return $method->invoke($command, $row, $scenario, $wireContractValid, $style, $endpoint);
    }

    /**
     * @return array<string, mixed>
     */
    protected function pkSameCarrierTwoSegmentRow(): array
    {
        return [
            'distribution_channel' => 'gds',
            'cpnr_eligible' => true,
            'auto_pnr_pricing_context_ready' => true,
            'segment_count' => 2,
            'validating_carrier' => 'PK',
            'marketing_carriers' => ['PK', 'PK'],
            'carrier_chain' => 'PK',
            'connecting_carrier_profile' => 'same_carrier',
            'route' => 'LHE-KHI-JED',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function qrSameCarrierTwoSegmentRow(): array
    {
        return [
            'distribution_channel' => 'gds',
            'cpnr_eligible' => true,
            'auto_pnr_pricing_context_ready' => true,
            'segment_count' => 2,
            'validating_carrier' => 'QR',
            'marketing_carriers' => ['QR', 'QR'],
            'carrier_chain' => 'QR',
            'connecting_carrier_profile' => 'same_carrier',
            'route' => 'LHE-DOH-JED',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function shopFixturePkConnectingLheJed(): array
    {
        return [
            'groupedItineraryResponse' => [
                'version' => '6',
                'scheduleDescs' => [
                    [
                        'ref' => 1,
                        'departure' => ['airport' => 'LHE', 'time' => '2026-08-15T08:00:00'],
                        'arrival' => ['airport' => 'KHI', 'time' => '2026-08-15T09:30:00'],
                        'elapsedTime' => 90,
                        'carrier' => ['marketing' => 'PK', 'marketingFlightNumber' => '301'],
                    ],
                    [
                        'ref' => 2,
                        'departure' => ['airport' => 'KHI', 'time' => '2026-08-15T11:00:00'],
                        'arrival' => ['airport' => 'JED', 'time' => '2026-08-15T14:00:00'],
                        'elapsedTime' => 180,
                        'carrier' => ['marketing' => 'PK', 'marketingFlightNumber' => '741'],
                    ],
                ],
                'legDescs' => [
                    [
                        'ref' => 1,
                        'elapsedTime' => 360,
                        'schedules' => [['ref' => 1], ['ref' => 2]],
                    ],
                ],
                'fareComponentDescs' => [
                    ['ref' => 9, 'fareBasisCode' => 'VOW1'],
                    ['ref' => 10, 'fareBasisCode' => 'VOWSKPK'],
                ],
                'itineraryGroups' => [
                    [
                        'itineraries' => [
                            [
                                'id' => 'itin-pk-lhe-jed',
                                'pricingSource' => 'ADVJR1',
                                'legs' => [['ref' => 1]],
                                'pricingInformation' => [
                                    [
                                        'pricingSubsource' => 'HPIS',
                                        'fare' => [
                                            'validatingCarrierCode' => 'PK',
                                            'totalFare' => [
                                                'currency' => 'PKR',
                                                'totalPrice' => 99714,
                                                'baseFareAmount' => 80000,
                                                'totalTaxAmount' => 19714,
                                            ],
                                            'passengerInfoList' => [
                                                [
                                                    'passengerInfo' => [
                                                        'passengerType' => 'ADT',
                                                        'fareComponents' => [
                                                            [
                                                                'ref' => 9,
                                                                'segments' => [
                                                                    ['segment' => ['bookingCode' => 'V', 'cabinCode' => 'Y', 'fareBasisCode' => 'VOW1']],
                                                                    ['segment' => ['bookingCode' => 'V', 'cabinCode' => 'Y', 'fareBasisCode' => 'VOWSKPK']],
                                                                ],
                                                            ],
                                                        ],
                                                    ],
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function shopFixtureGfConnectingLheJed(): array
    {
        return [
            'groupedItineraryResponse' => [
                'version' => '6',
                'scheduleDescs' => [
                    [
                        'ref' => 1,
                        'departure' => ['airport' => 'LHE', 'time' => '2026-08-15T08:00:00'],
                        'arrival' => ['airport' => 'BAH', 'time' => '2026-08-15T10:00:00'],
                        'elapsedTime' => 120,
                        'carrier' => ['marketing' => 'GF', 'marketingFlightNumber' => '761'],
                    ],
                    [
                        'ref' => 2,
                        'departure' => ['airport' => 'BAH', 'time' => '2026-08-15T11:30:00'],
                        'arrival' => ['airport' => 'JED', 'time' => '2026-08-15T13:30:00'],
                        'elapsedTime' => 120,
                        'carrier' => ['marketing' => 'GF', 'marketingFlightNumber' => '181'],
                    ],
                ],
                'legDescs' => [
                    [
                        'ref' => 1,
                        'elapsedTime' => 330,
                        'schedules' => [['ref' => 1], ['ref' => 2]],
                    ],
                ],
                'fareComponentDescs' => [
                    ['ref' => 9, 'fareBasisCode' => 'WDLIT3PK'],
                    ['ref' => 10, 'fareBasisCode' => 'WDLIT3PK'],
                ],
                'itineraryGroups' => [
                    [
                        'itineraries' => [
                            [
                                'id' => 'itin-gf-lhe-jed',
                                'pricingSource' => 'ADVJR1',
                                'legs' => [['ref' => 1]],
                                'pricingInformation' => [
                                    [
                                        'pricingSubsource' => 'HPIS',
                                        'fare' => [
                                            'validatingCarrierCode' => 'GF',
                                            'totalFare' => [
                                                'currency' => 'PKR',
                                                'totalPrice' => 87493,
                                                'baseFareAmount' => 69000,
                                                'totalTaxAmount' => 18493,
                                            ],
                                            'passengerInfoList' => [
                                                [
                                                    'passengerInfo' => [
                                                        'passengerType' => 'ADT',
                                                        'fareComponents' => [
                                                            [
                                                                'ref' => 9,
                                                                'segments' => [
                                                                    ['segment' => ['bookingCode' => 'W', 'cabinCode' => 'Y', 'fareBasisCode' => 'WDLIT3PK']],
                                                                    ['segment' => ['bookingCode' => 'W', 'cabinCode' => 'Y', 'fareBasisCode' => 'WDLIT3PK']],
                                                                ],
                                                            ],
                                                        ],
                                                    ],
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function shopFixtureQrConnectingLheJed(): array
    {
        return [
            'groupedItineraryResponse' => [
                'version' => '6',
                'scheduleDescs' => [
                    [
                        'ref' => 1,
                        'departure' => ['airport' => 'LHE', 'time' => '2026-08-15T08:00:00'],
                        'arrival' => ['airport' => 'DOH', 'time' => '2026-08-15T10:30:00'],
                        'elapsedTime' => 150,
                        'carrier' => ['marketing' => 'QR', 'marketingFlightNumber' => '601'],
                    ],
                    [
                        'ref' => 2,
                        'departure' => ['airport' => 'DOH', 'time' => '2026-08-15T12:00:00'],
                        'arrival' => ['airport' => 'JED', 'time' => '2026-08-15T14:30:00'],
                        'elapsedTime' => 150,
                        'carrier' => ['marketing' => 'QR', 'marketingFlightNumber' => '1185'],
                    ],
                ],
                'legDescs' => [
                    [
                        'ref' => 1,
                        'elapsedTime' => 390,
                        'schedules' => [['ref' => 1], ['ref' => 2]],
                    ],
                ],
                'fareComponentDescs' => [
                    ['ref' => 9, 'fareBasisCode' => 'OJPKP1RI'],
                    ['ref' => 10, 'fareBasisCode' => 'OJPKP1RI'],
                ],
                'itineraryGroups' => [
                    [
                        'itineraries' => [
                            [
                                'id' => 'itin-qr-lhe-jed',
                                'pricingSource' => 'ADVJR1',
                                'legs' => [['ref' => 1]],
                                'pricingInformation' => [
                                    [
                                        'pricingSubsource' => 'HPIS',
                                        'fare' => [
                                            'validatingCarrierCode' => 'QR',
                                            'totalFare' => [
                                                'currency' => 'PKR',
                                                'totalPrice' => 88584,
                                                'baseFareAmount' => 70000,
                                                'totalTaxAmount' => 18584,
                                            ],
                                            'passengerInfoList' => [
                                                [
                                                    'passengerInfo' => [
                                                        'passengerType' => 'ADT',
                                                        'fareComponents' => [
                                                            [
                                                                'ref' => 9,
                                                                'segments' => [
                                                                    ['segment' => ['bookingCode' => 'O', 'cabinCode' => 'Y', 'fareBasisCode' => 'OJPKP1RI']],
                                                                    ['segment' => ['bookingCode' => 'O', 'cabinCode' => 'Y', 'fareBasisCode' => 'OJPKP1RI']],
                                                                ],
                                                            ],
                                                        ],
                                                    ],
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    protected function shopFixtureWithBookingCode(string $bookingCode = 'Y'): array
    {
        $shopFixture = json_decode(
            file_get_contents(base_path('tests/Fixtures/sabre_bfm_v4_grouped_refs_response.json')),
            true,
        );
        data_set(
            $shopFixture,
            'groupedItineraryResponse.itineraryGroups.0.itineraries.0.pricingInformation.0.fare.passengerInfoList.0.passengerInfo.fareComponents.0.segments.0.segment.bookingCode',
            $bookingCode,
        );

        return $shopFixture;
    }

    protected function seedSabreConnection(string $baseUrl = 'https://api.cert.platform.sabre.com'): SupplierConnection
    {
        $this->seed(OtaFoundationSeeder::class);
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $conn = SupplierConnection::query()
            ->where('agency_id', $agency->id)
            ->where('provider', SupplierProvider::Sabre)
            ->firstOrFail();
        $conn->base_url = $baseUrl;
        $conn->credentials = ['client_id' => 'cpnr_ci', 'client_secret' => 'cpnr_cs', 'pcc' => 'TEST'];
        $conn->save();
        Cache::flush();

        return $conn;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function assertAirpriceWireDiagnosticsFlagFalse(array $payload): void
    {
        $diag = $payload['pricing_diagnostics'];
        $this->assertGreaterThanOrEqual(1, $diag['wire_airprice_node_count']);
        $this->assertFalse($diag['wire_airprice_has_validating_carrier']);
        $this->assertSame([], $diag['wire_airprice_validating_carriers_sanitized']);
        $this->assertTrue($diag['has_air_price']);
        $this->assertTrue($diag['wire_airprice_has_passenger_type']);
        $this->assertGreaterThanOrEqual(1, $diag['wire_airprice_passenger_type_count']);
        $this->assertContains('ADT', $diag['wire_airprice_passenger_type_codes_sanitized']);
        $this->assertTrue($diag['wire_root_air_price_retain_present']);
        $this->assertAirpriceDiagnosticsDoNotExposePii($payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function assertAirpriceDiagnosticsDoNotExposePii(array $payload): void
    {
        $diagJson = json_encode($payload['pricing_diagnostics'] ?? [], JSON_UNESCAPED_UNICODE);
        $this->assertIsString($diagJson);
        $forbidden = [
            'Cert',
            'Probe',
            'cert-probe@example.invalid',
            '+920000000000',
            'cpnr_ci',
            'cpnr_cs',
            'fake-token-for-tests-only',
            'PriceRequestInformation',
            'OptionalQualifiers',
            'PricingQualifiers',
        ];
        foreach ($forbidden as $needle) {
            $this->assertStringNotContainsString($needle, $diagJson, 'pricing_diagnostics leaked: '.$needle);
        }
        $this->assertArrayNotHasKey('AirPrice', $payload['pricing_diagnostics']);
    }

    /**
     * @return array<string, mixed>
     */
    protected function decodeReportOutput(string $output): array
    {
        if (! preg_match('/cert_gds_cpnr_report_json=(.+)/s', trim($output), $matches)) {
            $this->fail('Expected cert_gds_cpnr_report_json= line in output: '.$output);
        }
        $decoded = json_decode($matches[1], true);
        $this->assertIsArray($decoded);

        return $decoded;
    }
}
