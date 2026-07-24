<?php

namespace Tests\Feature;

use App\Enums\SupplierConnectionStatus;
use App\Enums\SupplierProvider;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\CommunicationLog;
use App\Models\SupplierBooking;
use App\Models\SupplierBookingAttempt;
use App\Models\SupplierConnection;
use App\Support\Sabre\Scenario\SabreGdsLiveRevalidationOnlyProbe;
use App\Support\Sabre\Scenario\SabreGdsLiveScenarioRevalidationOutcomeMapper;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SabreGdsLiveRevalidationOnlyProbeTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Config::set('app.env', 'testing');
        parent::tearDown();
    }

    public function test_command_registered(): void
    {
        Artisan::call('list');
        $this->assertStringContainsString('sabre:gds-live-revalidation-only-probe', Artisan::output());
    }

    public function test_plan_mode_performs_search_only_and_no_revalidation_http(): void
    {
        $this->configureProbeSabre();
        Storage::fake('local');
        $revalidateCalls = 0;
        Http::fake(function (Request $request) use (&$revalidateCalls) {
            $url = strtolower($request->url());
            if (str_contains($url, 'revalidate')) {
                $revalidateCalls++;
            }
            $tokenPath = strtolower((string) config('suppliers.sabre.token_path', '/v2/auth/token'));
            if (str_contains($url, $tokenPath)) {
                return Http::response(['access_token' => 'fake-token-for-tests-only', 'expires_in' => 1800], 200);
            }
            if (str_contains($url, 'v4/offers/shop')) {
                return Http::response($this->shopFixtureWithBookingCode('Y'), 200);
            }

            return Http::response([], 404);
        });

        $conn = $this->seedSabreConnection();
        $before = $this->dbCounts();

        $exit = Artisan::call('sabre:gds-live-revalidation-only-probe', $this->probeArgs([
            '--connection' => (string) $conn->id,
            '--plan' => true,
            '--passenger-json' => $this->writePassengerFixture(),
        ]));

        $output = Artisan::output();
        $this->assertSame(0, $exit, $output);
        $this->assertSame(0, $revalidateCalls);
        $this->assertStringContainsString('probe_mode=plan', $output);
        $this->assertStringContainsString('supplier_call_planned=false', $output);
        $this->assertStringContainsString('supplier_revalidation_call_count=0', $output);
        $this->assertStringContainsString('db_mutation_detected=false', $output);
        $this->assertStringContainsString('revalidation_linkage_ready=', $output);
        $this->assertStringContainsString('selected_offer_fingerprint=', $output);
        $this->assertStringNotContainsString('Haseeb', $output);
        $this->assertDbUnchanged($before);

        $files = Storage::disk('local')->allFiles('sabre-gds-revalidation-probes');
        $this->assertCount(1, $files);
        $artifact = json_decode((string) Storage::disk('local')->get($files[0]), true);
        $this->assertIsArray($artifact);
        $this->assertSame(0, $artifact['supplier_revalidation_call_count']);
        $this->assertFalse($artifact['db_mutation_detected']);
        $this->assertArrayHasKey('db_snapshot_before', $artifact);
        $this->assertArrayHasKey('db_snapshot_after', $artifact);
        $this->assertArrayHasKey('selected_candidate_index', $artifact);
        $this->assertArrayHasKey('revalidation_linkage_missing_components', $artifact);
        $digest = $artifact['payload_structural_digest'];
        $this->assertIsArray($digest);
        foreach ([
            'payload_style',
            'endpoint_path',
            'payload_schema_valid',
            'payload_schema_reason_code',
            'root_version_present',
            'root_version_type_valid',
            'root_child_keys',
            'requestor_id_present',
            'requestor_id_type_valid',
            'requestor_id_non_empty',
            'pos_child_keys',
            'source_child_keys',
            'requestor_id_child_keys',
            'requestor_identity_source_present',
            'pseudo_city_code_present',
            'pseudo_city_code_type_valid',
            'pseudo_city_code_non_empty',
            'pseudo_city_code_source_present',
            'origin_destination_child_keys',
            'contains_invalid_direct_flight_segment',
            'airline_marketing_type_valid',
            'airline_operating_type_valid',
            'contains_unsupported_segment_number',
            'contains_unsupported_resbookdesigcode',
            'contains_unsupported_fare_basis_code',
            'contains_unsupported_cabin_code',
            'contains_unsupported_single_branded_fare',
            'unsupported_branded_fare_indicator_keys',
            'branded_fare_indicator_child_keys',
            'branded_fare_context_present',
            'booking_class_context_present',
            'cabin_context_present',
            'fare_basis_context_present',
            'unsupported_flight_child_keys',
            'flight_child_keys',
            'airline_child_keys',
            'origin_destination_count',
            'segment_count',
            'segment_routes',
            'booking_classes_complete',
            'fare_basis_complete',
            'pricing_context_present',
            'has_reconstructed_pricing_context',
            'itinerary_indexes_present',
            'leg_references_present',
            'schedule_references_present',
            'fare_component_references_present',
            'selected_itinerary_context_present',
            'payload_freeze_fingerprint',
        ] as $digestKey) {
            $this->assertArrayHasKey($digestKey, $digest, 'Missing digest key: '.$digestKey);
        }
        $this->assertArrayHasKey('payload_schema_valid', $artifact);
        $this->assertArrayHasKey('contains_invalid_direct_flight_segment', $artifact);
        $this->assertArrayHasKey('airline_marketing_type_valid', $artifact);
        $this->assertTrue($artifact['payload_schema_valid']);
        $this->assertTrue($artifact['root_version_present']);
        $this->assertTrue($artifact['root_version_type_valid']);
        $this->assertContains('Version', $artifact['root_child_keys']);
        $this->assertTrue($artifact['requestor_id_present']);
        $this->assertTrue($artifact['requestor_id_type_valid']);
        $this->assertTrue($artifact['requestor_id_non_empty']);
        $this->assertContains('ID', $artifact['requestor_id_child_keys']);
        $this->assertTrue($artifact['requestor_identity_source_present']);
        $this->assertTrue($artifact['pseudo_city_code_present']);
        $this->assertTrue($artifact['pseudo_city_code_type_valid']);
        $this->assertTrue($artifact['pseudo_city_code_non_empty']);
        $this->assertTrue($artifact['pseudo_city_code_source_present']);
        $this->assertContains('PseudoCityCode', $artifact['source_child_keys']);
        $this->assertFalse($artifact['contains_invalid_direct_flight_segment']);
        $this->assertTrue($artifact['airline_marketing_type_valid']);
        $this->assertFalse($artifact['contains_unsupported_segment_number']);
        $this->assertFalse($artifact['contains_unsupported_resbookdesigcode']);
        $this->assertFalse($artifact['contains_unsupported_fare_basis_code']);
        $this->assertFalse($artifact['contains_unsupported_cabin_code']);
        $this->assertFalse($artifact['contains_unsupported_single_branded_fare']);
        $this->assertSame([], $artifact['unsupported_branded_fare_indicator_keys']);
        $this->assertSame([], $artifact['unsupported_flight_child_keys']);
        $this->assertTrue($artifact['booking_class_context_present']);
        $this->assertTrue($artifact['fare_basis_context_present']);
        $this->assertStringNotContainsString('Haseeb', json_encode($artifact));
        $this->assertStringNotContainsString('request_payload', json_encode($artifact));
        $this->assertStringNotContainsString('api_draft', json_encode($artifact));
    }

    public function test_plan_mode_invalid_passenger_json_makes_no_revalidation_call(): void
    {
        $this->configureProbeSabre();
        Storage::fake('local');
        $revalidateCalls = 0;
        Http::fake(function (Request $request) use (&$revalidateCalls) {
            $url = strtolower($request->url());
            if (str_contains($url, 'revalidate')) {
                $revalidateCalls++;
            }
            $tokenPath = strtolower((string) config('suppliers.sabre.token_path', '/v2/auth/token'));
            if (str_contains($url, $tokenPath)) {
                return Http::response(['access_token' => 'fake-token-for-tests-only', 'expires_in' => 1800], 200);
            }
            if (str_contains($url, 'v4/offers/shop')) {
                return Http::response($this->shopFixtureWithBookingCode('Y'), 200);
            }

            return Http::response([], 404);
        });

        $conn = $this->seedSabreConnection();
        $invalidPath = storage_path('app/testing-revalidation-probe-passenger-invalid.json');
        file_put_contents($invalidPath, json_encode(['given_name' => 'OnlyOneField']));

        $exit = Artisan::call('sabre:gds-live-revalidation-only-probe', $this->probeArgs([
            '--connection' => (string) $conn->id,
            '--plan' => true,
            '--passenger-json' => $invalidPath,
        ]));

        $this->assertSame(1, $exit);
        $this->assertSame(0, $revalidateCalls);
        $this->assertStringContainsString('passenger_json_invalid', Artisan::output());

        $files = Storage::disk('local')->allFiles('sabre-gds-revalidation-probes');
        $this->assertCount(1, $files);
        $artifact = json_decode((string) Storage::disk('local')->get($files[0]), true);
        $this->assertSame(0, $artifact['supplier_revalidation_call_count']);
        $this->assertFalse($artifact['db_mutation_detected']);
    }

    public function test_plan_mode_missing_passenger_json_fails_before_revalidation(): void
    {
        $this->configureProbeSabre();
        Storage::fake('local');
        $revalidateCalls = 0;
        Http::fake(function (Request $request) use (&$revalidateCalls) {
            $url = strtolower($request->url());
            if (str_contains($url, 'revalidate')) {
                $revalidateCalls++;
            }

            return Http::response([], 404);
        });

        $conn = $this->seedSabreConnection();

        $exit = Artisan::call('sabre:gds-live-revalidation-only-probe', $this->probeArgs([
            '--connection' => (string) $conn->id,
            '--plan' => true,
        ]));

        $this->assertSame(1, $exit);
        $this->assertSame(0, $revalidateCalls);
        $this->assertStringContainsString('passenger_json_required', Artisan::output());
    }

    public function test_send_mode_performs_exactly_one_revalidation_call(): void
    {
        $this->configureProbeSabre();
        Storage::fake('local');
        $revalidateCalls = 0;
        Http::fake(function (Request $request) use (&$revalidateCalls) {
            $url = strtolower($request->url());
            if (str_contains($url, 'revalidate')) {
                $revalidateCalls++;

                return Http::response($this->revalidateFailureFixture('unusable_linkage'), 200);
            }
            $tokenPath = strtolower((string) config('suppliers.sabre.token_path', '/v2/auth/token'));
            if (str_contains($url, $tokenPath)) {
                return Http::response(['access_token' => 'fake-token-for-tests-only', 'expires_in' => 1800], 200);
            }
            if (str_contains($url, 'v4/offers/shop')) {
                return Http::response($this->shopFixtureWithBookingCode('Y'), 200);
            }

            return Http::response([], 404);
        });

        $conn = $this->seedSabreConnection();
        $passengerPath = $this->writePassengerFixture();
        $before = $this->dbCounts();

        $exit = Artisan::call('sabre:gds-live-revalidation-only-probe', $this->probeArgs([
            '--connection' => (string) $conn->id,
            '--send' => true,
            '--confirm-revalidation' => SabreGdsLiveRevalidationOnlyProbe::CONFIRM_REVALIDATION_PHRASE,
            '--passenger-json' => $passengerPath,
        ]));

        $output = Artisan::output();
        $this->assertSame(1, $revalidateCalls);
        $this->assertStringContainsString('supplier_revalidation_call_count=1', $output);
        $this->assertStringContainsString('revalidation_attempted=true', $output);
        $this->assertStringContainsString('revalidation_reason_code=', $output);
        $this->assertDbUnchanged($before);

        $files = Storage::disk('local')->allFiles('sabre-gds-revalidation-probes');
        $this->assertCount(1, $files);
        $artifact = json_decode((string) Storage::disk('local')->get($files[0]), true);
        $this->assertIsArray($artifact);
        $this->assertSame('revalidation-only', $artifact['mode']);
        $this->assertArrayHasKey('payload_structural_digest', $artifact);
        $digest = $artifact['payload_structural_digest'];
        $this->assertIsArray($digest);
        $this->assertArrayHasKey('payload_schema_valid', $artifact);
        $this->assertArrayHasKey('contains_invalid_direct_flight_segment', $artifact);
        $this->assertArrayHasKey('airline_marketing_type_valid', $artifact);
        $this->assertTrue($artifact['payload_schema_valid']);
        $this->assertTrue($artifact['root_version_present']);
        $this->assertTrue($artifact['root_version_type_valid']);
        $this->assertContains('Version', $artifact['root_child_keys']);
        $this->assertTrue($artifact['requestor_id_present']);
        $this->assertTrue($artifact['requestor_id_type_valid']);
        $this->assertTrue($artifact['requestor_id_non_empty']);
        $this->assertContains('ID', $artifact['requestor_id_child_keys']);
        $this->assertTrue($artifact['requestor_identity_source_present']);
        $this->assertTrue($artifact['pseudo_city_code_present']);
        $this->assertTrue($artifact['pseudo_city_code_type_valid']);
        $this->assertTrue($artifact['pseudo_city_code_non_empty']);
        $this->assertTrue($artifact['pseudo_city_code_source_present']);
        $this->assertContains('PseudoCityCode', $artifact['source_child_keys']);
        $this->assertFalse($artifact['contains_invalid_direct_flight_segment']);
        $this->assertTrue($artifact['airline_marketing_type_valid']);
        $this->assertFalse($artifact['contains_unsupported_segment_number']);
        $this->assertFalse($artifact['contains_unsupported_resbookdesigcode']);
        $this->assertFalse($artifact['contains_unsupported_fare_basis_code']);
        $this->assertFalse($artifact['contains_unsupported_cabin_code']);
        $this->assertFalse($artifact['contains_unsupported_single_branded_fare']);
        $this->assertSame([], $artifact['unsupported_branded_fare_indicator_keys']);
        $this->assertSame([], $artifact['unsupported_flight_child_keys']);
        $this->assertTrue($artifact['booking_class_context_present']);
        $this->assertTrue($artifact['fare_basis_context_present']);
        $this->assertTrue($digest['payload_schema_valid']);
        $this->assertTrue($digest['airline_marketing_type_valid']);
        $this->assertFalse($digest['contains_unsupported_segment_number']);
        $this->assertArrayHasKey('search_correlation_id', $artifact);
        $this->assertSame(1, $artifact['supplier_revalidation_call_count']);
        $this->assertFalse($artifact['db_mutation_detected']);
    }

    public function test_send_requires_confirm_revalidation_phrase(): void
    {
        $this->configureProbeSabre();
        $conn = $this->seedSabreConnection();

        $exit = Artisan::call('sabre:gds-live-revalidation-only-probe', $this->probeArgs([
            '--connection' => (string) $conn->id,
            '--send' => true,
            '--passenger-json' => $this->writePassengerFixture(),
        ]));

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('--confirm-revalidation=', Artisan::output());
    }

    public function test_production_requires_confirm_production_phrase(): void
    {
        Config::set('app.env', 'production');
        Config::set('suppliers.sabre.ticketing_enabled', false);
        $conn = $this->seedSabreConnection();

        $exit = Artisan::call('sabre:gds-live-revalidation-only-probe', $this->probeArgs([
            '--connection' => (string) $conn->id,
            '--plan' => true,
        ]));

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('--confirm-production=', Artisan::output());
    }

    public function test_ticketing_enabled_blocks_probe(): void
    {
        Config::set('app.env', 'testing');
        Config::set('suppliers.sabre.ticketing_enabled', true);
        $conn = $this->seedSabreConnection();

        $exit = Artisan::call('sabre:gds-live-revalidation-only-probe', $this->probeArgs([
            '--connection' => (string) $conn->id,
            '--plan' => true,
        ]));

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('ticketing_enabled must remain false', Artisan::output());
    }

    public function test_http_500_does_not_retry(): void
    {
        $this->configureProbeSabre();
        $revalidateCalls = 0;
        Http::fake(function (Request $request) use (&$revalidateCalls) {
            $url = strtolower($request->url());
            if (str_contains($url, 'revalidate')) {
                $revalidateCalls++;

                return Http::response(['error' => 'server'], 500);
            }
            $tokenPath = strtolower((string) config('suppliers.sabre.token_path', '/v2/auth/token'));
            if (str_contains($url, $tokenPath)) {
                return Http::response(['access_token' => 'fake-token-for-tests-only', 'expires_in' => 1800], 200);
            }
            if (str_contains($url, 'v4/offers/shop')) {
                return Http::response($this->shopFixtureWithBookingCode('Y'), 200);
            }

            return Http::response([], 404);
        });

        $conn = $this->seedSabreConnection();
        Artisan::call('sabre:gds-live-revalidation-only-probe', $this->probeArgs([
            '--connection' => (string) $conn->id,
            '--send' => true,
            '--confirm-revalidation' => SabreGdsLiveRevalidationOnlyProbe::CONFIRM_REVALIDATION_PHRASE,
            '--passenger-json' => $this->writePassengerFixture(),
        ]));

        $this->assertSame(1, $revalidateCalls);
        $this->assertStringContainsString(
            'revalidation_reason_code='.SabreGdsLiveScenarioRevalidationOutcomeMapper::REASON_HTTP_REJECTED,
            Artisan::output(),
        );
    }

    public function test_timeout_does_not_retry(): void
    {
        $this->configureProbeSabre();
        $revalidateCalls = 0;
        Http::fake(function (Request $request) use (&$revalidateCalls) {
            $url = strtolower($request->url());
            if (str_contains($url, 'revalidate')) {
                $revalidateCalls++;
                throw new \Illuminate\Http\Client\ConnectionException('cURL error 28: Operation timed out');
            }
            $tokenPath = strtolower((string) config('suppliers.sabre.token_path', '/v2/auth/token'));
            if (str_contains($url, $tokenPath)) {
                return Http::response(['access_token' => 'fake-token-for-tests-only', 'expires_in' => 1800], 200);
            }
            if (str_contains($url, 'v4/offers/shop')) {
                return Http::response($this->shopFixtureWithBookingCode('Y'), 200);
            }

            return Http::response([], 404);
        });

        $conn = $this->seedSabreConnection();
        Artisan::call('sabre:gds-live-revalidation-only-probe', $this->probeArgs([
            '--connection' => (string) $conn->id,
            '--send' => true,
            '--confirm-revalidation' => SabreGdsLiveRevalidationOnlyProbe::CONFIRM_REVALIDATION_PHRASE,
            '--passenger-json' => $this->writePassengerFixture(),
        ]));

        $this->assertSame(1, $revalidateCalls);
        $this->assertStringContainsString(
            'revalidation_reason_code='.SabreGdsLiveScenarioRevalidationOutcomeMapper::REASON_TIMEOUT,
            Artisan::output(),
        );
    }

    public function test_grouped_itinerary_error_classification(): void
    {
        $this->configureProbeSabre();
        Http::fake(function (Request $request) {
            $url = strtolower($request->url());
            if (str_contains($url, 'revalidate')) {
                return Http::response($this->revalidateFailureFixture('mip_5053'), 200);
            }
            $tokenPath = strtolower((string) config('suppliers.sabre.token_path', '/v2/auth/token'));
            if (str_contains($url, $tokenPath)) {
                return Http::response(['access_token' => 'fake-token-for-tests-only', 'expires_in' => 1800], 200);
            }
            if (str_contains($url, 'v4/offers/shop')) {
                return Http::response($this->shopFixtureWithBookingCode('Y'), 200);
            }

            return Http::response([], 404);
        });

        $conn = $this->seedSabreConnection();
        Artisan::call('sabre:gds-live-revalidation-only-probe', $this->probeArgs([
            '--connection' => (string) $conn->id,
            '--send' => true,
            '--confirm-revalidation' => SabreGdsLiveRevalidationOnlyProbe::CONFIRM_REVALIDATION_PHRASE,
            '--passenger-json' => $this->writePassengerFixture(),
        ]));

        $this->assertStringContainsString(
            'revalidation_reason_code='.SabreGdsLiveScenarioRevalidationOutcomeMapper::REASON_GROUPED_ITINERARY_ERROR,
            Artisan::output(),
        );
    }

    public function test_missing_usable_linkage_classification(): void
    {
        $this->configureProbeSabre();
        Http::fake(function (Request $request) {
            $url = strtolower($request->url());
            if (str_contains($url, 'revalidate')) {
                return Http::response($this->revalidateFailureFixture('unusable_linkage'), 200);
            }
            $tokenPath = strtolower((string) config('suppliers.sabre.token_path', '/v2/auth/token'));
            if (str_contains($url, $tokenPath)) {
                return Http::response(['access_token' => 'fake-token-for-tests-only', 'expires_in' => 1800], 200);
            }
            if (str_contains($url, 'v4/offers/shop')) {
                return Http::response($this->shopFixtureWithBookingCode('Y'), 200);
            }

            return Http::response([], 404);
        });

        $conn = $this->seedSabreConnection();
        Artisan::call('sabre:gds-live-revalidation-only-probe', $this->probeArgs([
            '--connection' => (string) $conn->id,
            '--send' => true,
            '--confirm-revalidation' => SabreGdsLiveRevalidationOnlyProbe::CONFIRM_REVALIDATION_PHRASE,
            '--passenger-json' => $this->writePassengerFixture(),
        ]));

        $output = Artisan::output();
        $this->assertStringContainsString('revalidation_success=false', $output);
        $this->assertStringContainsString('supplier_response_received=true', $output);
        $this->assertStringContainsString('revalidation_reason_code=', $output);
    }

    public function test_success_with_valid_linkage(): void
    {
        $this->configureProbeSabre();
        Http::fake(function (Request $request) {
            $url = strtolower($request->url());
            if (str_contains($url, 'revalidate')) {
                return Http::response($this->revalidateSuccessFixture(450.5, 'USD'), 200);
            }
            $tokenPath = strtolower((string) config('suppliers.sabre.token_path', '/v2/auth/token'));
            if (str_contains($url, $tokenPath)) {
                return Http::response(['access_token' => 'fake-token-for-tests-only', 'expires_in' => 1800], 200);
            }
            if (str_contains($url, 'v4/offers/shop')) {
                return Http::response($this->shopFixtureWithBookingCode('Y'), 200);
            }

            return Http::response([], 404);
        });

        $conn = $this->seedSabreConnection();
        Artisan::call('sabre:gds-live-revalidation-only-probe', $this->probeArgs([
            '--connection' => (string) $conn->id,
            '--send' => true,
            '--confirm-revalidation' => SabreGdsLiveRevalidationOnlyProbe::CONFIRM_REVALIDATION_PHRASE,
            '--passenger-json' => $this->writePassengerFixture(),
        ]));

        $output = Artisan::output();
        $this->assertStringContainsString('revalidation_success=true', $output);
        $this->assertStringContainsString('freshness_satisfied=true', $output);
    }

    public function test_artifact_excludes_pii_and_raw_tokens(): void
    {
        $this->configureProbeSabre();
        Storage::fake('local');
        Http::fake(function (Request $request) {
            $url = strtolower($request->url());
            if (str_contains($url, 'revalidate')) {
                return Http::response($this->revalidateFailureFixture('unusable_linkage'), 200);
            }
            $tokenPath = strtolower((string) config('suppliers.sabre.token_path', '/v2/auth/token'));
            if (str_contains($url, $tokenPath)) {
                return Http::response(['access_token' => 'fake-token-for-tests-only', 'expires_in' => 1800], 200);
            }
            if (str_contains($url, 'v4/offers/shop')) {
                return Http::response($this->shopFixtureWithBookingCode('Y'), 200);
            }

            return Http::response([], 404);
        });

        $conn = $this->seedSabreConnection();
        Artisan::call('sabre:gds-live-revalidation-only-probe', $this->probeArgs([
            '--connection' => (string) $conn->id,
            '--send' => true,
            '--confirm-revalidation' => SabreGdsLiveRevalidationOnlyProbe::CONFIRM_REVALIDATION_PHRASE,
            '--passenger-json' => $this->writePassengerFixture(),
        ]));

        $files = Storage::disk('local')->allFiles('sabre-gds-revalidation-probes');
        $json = (string) Storage::disk('local')->get($files[0]);
        $this->assertStringNotContainsString('Haseeb', $json);
        $this->assertStringNotContainsString('EX1345432', $json);
        $this->assertStringNotContainsString('myworkhaseeb@gmail.com', $json);
        $this->assertStringNotContainsString('fake-token-for-tests-only', $json);
        $this->assertStringNotContainsString('"access_token"', $json);
    }

    public function test_no_passenger_records_or_ticketing_endpoints_called(): void
    {
        $this->configureProbeSabre();
        Http::fake(function (Request $request) {
            $url = strtolower($request->url());
            $this->assertStringNotContainsString('passenger/records', $url);
            $this->assertStringNotContainsString('airticket', $url);
            $this->assertStringNotContainsString('void', $url);
            $this->assertStringNotContainsString('refund', $url);
            $tokenPath = strtolower((string) config('suppliers.sabre.token_path', '/v2/auth/token'));
            if (str_contains($url, $tokenPath)) {
                return Http::response(['access_token' => 'fake-token-for-tests-only', 'expires_in' => 1800], 200);
            }
            if (str_contains($url, 'v4/offers/shop')) {
                return Http::response($this->shopFixtureWithBookingCode('Y'), 200);
            }
            if (str_contains($url, 'revalidate')) {
                return Http::response($this->revalidateFailureFixture('unusable_linkage'), 200);
            }

            return Http::response([], 404);
        });

        $conn = $this->seedSabreConnection();
        $before = $this->dbCounts();
        Artisan::call('sabre:gds-live-revalidation-only-probe', $this->probeArgs([
            '--connection' => (string) $conn->id,
            '--send' => true,
            '--confirm-revalidation' => SabreGdsLiveRevalidationOnlyProbe::CONFIRM_REVALIDATION_PHRASE,
            '--passenger-json' => $this->writePassengerFixture(),
        ]));
        $this->assertDbUnchanged($before);
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    protected function probeArgs(array $extra = []): array
    {
        return array_merge([
            '--departure-date' => '2026-08-15',
            '--origin' => 'LHE',
            '--destination' => 'DXB',
        ], $extra);
    }

    protected function configureProbeSabre(): void
    {
        Config::set('app.env', 'testing');
        Config::set('suppliers.sabre.booking_enabled', true);
        Config::set('suppliers.sabre.booking_live_call_enabled', true);
        Config::set('suppliers.sabre.revalidate_before_booking', true);
        Config::set('suppliers.sabre.revalidate_path', '/v4/shop/flights/revalidate');
        Config::set('suppliers.sabre.revalidate_payload_style', 'bfm_revalidate_v1');
        Config::set('suppliers.sabre.ticketing_enabled', false);
        Config::set('suppliers.sabre.booking_mode', 'pnr_only');
    }

    /**
     * @return array<string, int>
     */
    protected function dbCounts(): array
    {
        return [
            'bookings' => Booking::query()->count(),
            'supplier_bookings' => SupplierBooking::query()->count(),
            'supplier_booking_attempts' => SupplierBookingAttempt::query()->count(),
            'communication_logs' => CommunicationLog::query()->count(),
        ];
    }

    /**
     * @param  array<string, int>  $before
     */
    protected function assertDbUnchanged(array $before): void
    {
        $this->assertSame($before['bookings'], Booking::query()->count());
        $this->assertSame($before['supplier_bookings'], SupplierBooking::query()->count());
        $this->assertSame($before['supplier_booking_attempts'], SupplierBookingAttempt::query()->count());
        $this->assertSame($before['communication_logs'], CommunicationLog::query()->count());
    }

    protected function seedSabreConnection(): SupplierConnection
    {
        $this->seed(OtaFoundationSeeder::class);
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $conn = SupplierConnection::query()
            ->where('agency_id', $agency->id)
            ->where('provider', SupplierProvider::Sabre)
            ->firstOrFail();
        $conn->base_url = 'https://api.cert.platform.sabre.com';
        $conn->is_active = true;
        $conn->status = SupplierConnectionStatus::Active;
        $conn->credentials = ['client_id' => 'probe_ci', 'client_secret' => 'probe_cs', 'pcc' => 'TEST'];
        $conn->save();
        Cache::flush();

        return $conn;
    }

    protected function writePassengerFixture(): string
    {
        $path = storage_path('app/testing-revalidation-probe-passenger.json');
        file_put_contents($path, json_encode([
            'title' => 'MR',
            'given_name' => 'Haseeb',
            'surname' => 'Asif',
            'gender' => 'M',
            'dob' => '1997-10-10',
            'nationality' => 'PK',
            'country' => 'PK',
            'passport_number' => 'EX1345432',
            'passport_issue_date' => '2020-10-10',
            'passport_expiry_date' => '2030-10-10',
            'phone' => '+92387656789',
            'email' => 'myworkhaseeb@gmail.com',
        ], JSON_PRETTY_PRINT));

        return $path;
    }

    /**
     * @return array<string, mixed>
     */
    protected function shopFixtureWithBookingCode(string $bookingCode = 'Y'): array
    {
        $shopFixture = json_decode(
            file_get_contents(base_path('tests/Fixtures/sabre_bfm_v4_grouped_refs_response.json')),
            true,
        );
        $this->assertIsArray($shopFixture);
        data_set($shopFixture, 'groupedItineraryResponse.itineraryGroups.0.itineraries.0.pricingInformation.0.fare.bookingCode', $bookingCode);
        data_set($shopFixture, 'groupedItineraryResponse.itineraryGroups.0.itineraries.0.pricingInformation.0.fare.passengerInfoList.0.passengerInfo.fareComponents.0.segments.0.segment.bookingCode', $bookingCode);

        return $shopFixture;
    }

    /**
     * @return array<string, mixed>
     */
    protected function revalidateSuccessFixture(float $total, string $currency = 'USD'): array
    {
        $fixture = $this->shopFixtureWithBookingCode('Y');
        data_set($fixture, 'groupedItineraryResponse.itineraryGroups.0.itineraries.0.pricingInformation.0.fare.totalFare.totalPrice', $total);
        data_set($fixture, 'groupedItineraryResponse.itineraryGroups.0.itineraries.0.pricingInformation.0.fare.totalFare.currencyCode', $currency);

        return $fixture;
    }

    /**
     * @return array<string, mixed>
     */
    protected function revalidateFailureFixture(string $failureClass): array
    {
        if ($failureClass === 'mip_5053') {
            return [
                'groupedItineraryResponse' => [
                    'messages' => [['severity' => 'Error', 'type' => 'MIP', 'code' => 'MIP5053', 'text' => 'Offer unavailable']],
                    'statistics' => ['itineraryCount' => 0],
                ],
            ];
        }

        if ($failureClass === 'unusable_linkage') {
            return [
                'groupedItineraryResponse' => [
                    'messages' => [],
                    'statistics' => ['itineraryCount' => 1],
                    'itineraryGroups' => [[
                        'itineraries' => [[
                            'pricingInformation' => [[
                                'fare' => [
                                    'validatingCarrierCode' => 'QR',
                                    'totalFare' => ['totalPrice' => null, 'currency' => null],
                                ],
                            ]],
                        ]],
                    ]],
                ],
            ];
        }

        return ['groupedItineraryResponse' => ['messages' => [], 'statistics' => ['itineraryCount' => 0]]];
    }
}
