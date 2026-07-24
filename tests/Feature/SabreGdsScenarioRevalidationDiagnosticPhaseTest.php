<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\SupplierBooking;
use App\Models\SupplierBookingAttempt;
use App\Support\Sabre\Scenario\SabreGdsLiveScenarioRevalidationOutcomeMapper;
use App\Support\Sabre\Scenario\SabreGdsLiveScenarioRunner;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Sabre\AlwaysSuccessfulScenarioRevalidationGate;
use Tests\TestCase;

class SabreGdsScenarioRevalidationDiagnosticPhaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_diagnostic_command_replays_sanitizer_fixture_without_supplier_http(): void
    {
        $exit = Artisan::call('sabre:gds-scenario-revalidation-diagnostic', [
            '--sanitizer-fixture' => 'representative',
        ]);

        $this->assertSame(0, $exit);
        $output = Artisan::output();
        $this->assertStringContainsString('mode=sanitizer_fixture_replay', $output);
        $this->assertStringContainsString('supplier_error_code=ERR.2SG.INVALID.REQUEST', $output);
        $this->assertStringContainsString('automatic_retry_allowed=false', $output);
        $this->assertStringContainsString('same_payload_retry_recommended=false', $output);
        $this->assertStringContainsString('revalidation_reason_code=scenario_revalidation_request_validation_failed', $output);
    }

    public function test_diagnostic_command_replays_fixture_without_supplier_http(): void
    {
        $fixture = [
            'outcome' => [
                'success' => false,
                'http_status' => 500,
                'reason_code' => 'sabre_revalidation_failed',
                'revalidation_attempted' => true,
                'supplier_call_attempted' => true,
                'supplier_response_received' => true,
                'revalidation_failure_class' => 'http_rejected',
                'payload_style' => 'iati_like_bfm_revalidate_v1',
                'endpoint_path' => '/v4/shop/flights/revalidate',
                'response_structure' => [
                    'top_level_keys' => 'errors',
                    'key_paths' => '',
                    'empty_body' => 'false',
                    'json_valid' => 'true',
                    'candidate_fields' => '',
                    'candidate_count' => '0',
                ],
            ],
            'context' => [
                'selected_total' => 520.83,
                'selected_currency' => 'USD',
            ],
        ];

        $path = storage_path('app/testing-revalidation-fixture.json');
        file_put_contents($path, json_encode($fixture, JSON_UNESCAPED_SLASHES));

        $exit = Artisan::call('sabre:gds-scenario-revalidation-diagnostic', [
            '--fixture' => $path,
        ]);

        $this->assertSame(0, $exit);
        $output = Artisan::output();
        $this->assertStringContainsString('revalidation_reason_code=scenario_revalidation_http_rejected', $output);
        $this->assertStringContainsString('supplier_call_attempted=true', $output);
        $this->assertStringContainsString('selected_total=520.83', $output);

        @unlink($path);
    }

    public function test_scenario_json_preserves_safe_diagnostics_slice(): void
    {
        $mapper = new SabreGdsLiveScenarioRevalidationOutcomeMapper;
        $evidence = $mapper->mapToScenarioEvidence([
            'success' => false,
            'http_status' => 200,
            'reason_code' => 'sabre_revalidation_empty_or_unusable_response',
            'revalidation_attempted' => true,
            'supplier_call_attempted' => true,
            'supplier_response_received' => true,
            'revalidation_failure_class' => 'unusable_linkage',
            'payload_style' => 'iati_like_bfm_revalidate_v1',
            'endpoint_path' => '/v4/shop/flights/revalidate',
            'linkage_digest' => [
                'per_segment_fare_basis_complete' => true,
                'has_revalidated_fare' => false,
                'has_revalidated_currency' => false,
            ],
            'response_structure' => [
                'top_level_keys' => 'groupedItineraryResponse',
                'key_paths' => '',
                'empty_body' => 'false',
                'json_valid' => 'true',
                'candidate_fields' => '',
                'candidate_count' => '1',
            ],
            'fare_comparison' => [
                'stored_total' => 520.83,
                'stored_currency' => 'USD',
                'fresh_total' => null,
                'fresh_currency' => null,
                'mismatches' => [],
            ],
        ], [
            'selected_total' => 520.83,
            'selected_currency' => 'USD',
        ]);

        $slice = $mapper->extractScenarioResultFields($evidence);

        $this->assertSame('scenario_revalidation_fare_linkage_missing', $slice['revalidation_reason_code']);
        $this->assertTrue($slice['supplier_call_attempted']);
        $this->assertTrue($slice['supplier_response_received']);
        $this->assertArrayHasKey('revalidation_diagnostics', $slice);
        $this->assertArrayHasKey('response_structure_summary', $slice);
    }

    public function test_book_mode_with_stub_gate_does_not_mutate_booking_supplier_entities(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $this->app->instance(
            \App\Support\Sabre\Scenario\SabreGdsLiveScenarioRevalidationGate::class,
            new AlwaysSuccessfulScenarioRevalidationGate,
        );

        $bookingCountBefore = Booking::query()->count();
        $attemptCountBefore = SupplierBookingAttempt::query()->count();
        $supplierBookingCountBefore = SupplierBooking::query()->count();

        config([
            'suppliers.sabre.booking_enabled' => false,
            'suppliers.sabre.booking_live_call_enabled' => false,
        ]);

        $runner = app(SabreGdsLiveScenarioRunner::class);
        $this->assertNotNull($runner);

        $this->assertSame($bookingCountBefore, Booking::query()->count());
        $this->assertSame($attemptCountBefore, SupplierBookingAttempt::query()->count());
        $this->assertSame($supplierBookingCountBefore, SupplierBooking::query()->count());
    }

    public function test_inspect_stored_run_read_only(): void
    {
        Storage::fake('local');
        $runId = '952d8cfe-793f-48d2-a535-ca923a67311e';
        Storage::disk('local')->put('sabre-gds-scenario-runs/'.$runId.'.json', json_encode([
            'run_id' => $runId,
            'mode' => 'book',
            'scenario_results' => [[
                'scenario' => 'pk-direct',
                'error' => 'scenario_revalidation_failed',
                'revalidation_attempted' => true,
                'revalidation_success' => false,
                'freshness_satisfied' => false,
                'selected_total' => 520.83,
                'selected_currency' => 'USD',
            ]],
        ], JSON_UNESCAPED_SLASHES));

        $bookingCountBefore = \App\Models\Booking::query()->count();
        $attemptCountBefore = \App\Models\SupplierBookingAttempt::query()->count();

        $exit = Artisan::call('sabre:gds-scenario-revalidation-diagnostic', [
            '--run-id' => $runId,
        ]);

        $this->assertSame(0, $exit);
        $output = Artisan::output();
        $this->assertStringContainsString('run_id='.$runId, $output);
        $this->assertStringContainsString('note=legacy_run_replayed_with_sparse_outcome', $output);
        $this->assertStringContainsString('revalidation_attempted=true', $output);
        $this->assertStringContainsString('revalidation_success=false', $output);
        $this->assertStringContainsString('revalidation_reason_code=scenario_revalidation_failed', $output);
        $this->assertStringNotContainsString('supplier_call_attempted=', $output);
        $this->assertStringNotContainsString('supplier_response_received=', $output);
        $this->assertStringNotContainsString('response_structure_summary=', $output);
        $this->assertSame($bookingCountBefore, \App\Models\Booking::query()->count());
        $this->assertSame($attemptCountBefore, \App\Models\SupplierBookingAttempt::query()->count());
    }
}
