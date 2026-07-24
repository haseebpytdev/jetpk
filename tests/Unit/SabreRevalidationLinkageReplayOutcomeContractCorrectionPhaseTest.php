<?php

namespace Tests\Unit;

use App\Support\Sabre\Revalidation\SabreGdsRevalidationSanitizedOutcomeContract;
use App\Support\Sabre\Scenario\SabreGdsLiveRevalidationOnlyProbe;
use App\Support\Sabre\Scenario\SabreGdsLiveScenarioRevalidationOutcomeMapper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SabreRevalidationLinkageReplayOutcomeContractCorrectionPhaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_linkage_fixture_replay_reports_zero_supplier_activity(): void
    {
        $exit = Artisan::call('sabre:gds-scenario-revalidation-diagnostic', [
            '--linkage-fixture' => base_path('tests/fixtures/sabre/revalidation/http-200-informational-warning-31-candidates-linkage.json'),
        ]);

        $this->assertSame(0, $exit);
        $output = Artisan::output();
        $this->assertStringContainsString('mode=linkage_fixture_replay', $output);
        $this->assertStringContainsString('supplier_call_attempted=false', $output);
        $this->assertStringContainsString('supplier_response_received=false', $output);
        $this->assertStringContainsString('revalidation_attempted=false', $output);
        $this->assertStringContainsString('supplier_revalidation_call_count=0', $output);
        $this->assertStringContainsString('db_mutation_detected=false', $output);
        $this->assertStringContainsString('fixture_response_present=true', $output);
        $this->assertStringContainsString('fixture_response_analyzed=true', $output);
        $this->assertStringContainsString('replay_performed=true', $output);
    }

    public function test_successful_replay_emits_success_compatible_reason_code(): void
    {
        Artisan::call('sabre:gds-scenario-revalidation-diagnostic', [
            '--linkage-fixture' => base_path('tests/fixtures/sabre/revalidation/http-200-informational-warning-31-candidates-linkage.json'),
        ]);

        $output = Artisan::output();
        $this->assertStringContainsString('revalidation_success=true', $output);
        $this->assertStringContainsString('reason_code=sabre_revalidation_ok', $output);
        $this->assertStringContainsString(
            'revalidation_reason_code='.SabreGdsLiveScenarioRevalidationOutcomeMapper::REASON_SUCCESS,
            $output,
        );
        $this->assertStringNotContainsString('revalidation_reason_code=scenario_revalidation_failed', $output);
    }

    public function test_candidate_counts_remain_consistent_between_top_level_and_nested_fields(): void
    {
        Artisan::call('sabre:gds-scenario-revalidation-diagnostic', [
            '--linkage-fixture' => base_path('tests/fixtures/sabre/revalidation/http-200-informational-warning-31-candidates-linkage.json'),
        ]);

        $output = Artisan::output();
        $this->assertStringContainsString('response_candidate_count=31', $output);
        $this->assertStringContainsString('structurally_eligible_candidate_count=2', $output);
        $this->assertStringContainsString('"candidate_count":31', $output);
        $this->assertStringContainsString('"response_candidate_count":31', $output);
        $this->assertStringContainsString('"structurally_eligible_candidate_count":2', $output);
    }

    public function test_mapper_success_reason_for_replay_outcome(): void
    {
        $mapper = new SabreGdsLiveScenarioRevalidationOutcomeMapper;
        $outcome = SabreGdsRevalidationSanitizedOutcomeContract::wrap([
            'success' => true,
            'http_status' => 200,
            'reason_code' => 'sabre_revalidation_ok',
            'revalidation_attempted' => false,
            'response_structure' => [
                'top_level_keys' => 'groupedItineraryResponse',
                'key_paths' => '',
                'empty_body' => 'false',
                'json_valid' => 'true',
                'candidate_fields' => 'totalFare',
                'candidate_count' => '31',
            ],
            'response_linkage_diagnostics' => [
                'response_candidate_count' => 31,
                'structurally_eligible_candidate_count' => 2,
                'unique_usable_linkage_match_count' => 1,
                'usable_fare_linkage' => true,
                'pricing_complete' => true,
            ],
            'replay_performed' => true,
        ], false, false);

        $this->assertSame(
            SabreGdsLiveScenarioRevalidationOutcomeMapper::REASON_SUCCESS,
            $mapper->classifyScenarioReasonCode($outcome),
        );
        $this->assertFalse($outcome['revalidation_attempted']);
        $this->assertFalse($outcome['supplier_call_attempted']);
    }

    public function test_probe_run_lookup_reads_revalidation_probe_directory(): void
    {
        Storage::fake('local');
        $runId = '25997adc-680d-430a-a047-d8fb64cf9dad';
        Storage::disk('local')->put(
            SabreGdsLiveRevalidationOnlyProbe::ARTIFACT_DIRECTORY.'/'.$runId.'.json',
            json_encode([
                'run_id' => $runId,
                'mode' => 'revalidation-only',
                'probe_mode' => 'send',
                'supplier_revalidation_call_count' => 1,
                'db_mutation_detected' => false,
                'revalidation_success' => false,
                'revalidation_reason_code' => 'scenario_revalidation_supplier_application_error',
                'response_candidate_count' => 31,
            ], JSON_UNESCAPED_SLASHES),
        );

        $exit = Artisan::call('sabre:gds-scenario-revalidation-diagnostic', [
            '--probe-run-id' => $runId,
        ]);

        $this->assertSame(0, $exit);
        $output = Artisan::output();
        $this->assertStringContainsString('artifact_location='.SabreGdsLiveRevalidationOnlyProbe::ARTIFACT_DIRECTORY.'/'.$runId.'.json', $output);
        $this->assertStringContainsString('note=stored_probe_artifact_summary_only_not_replayable', $output);
        $this->assertStringContainsString('response_candidate_count=31', $output);
    }

    public function test_run_id_searches_scenario_runs_then_probe_directory(): void
    {
        Storage::fake('local');
        $runId = '228d1e73-06ba-4e30-8567-bbeec7e40700';
        Storage::disk('local')->put(
            SabreGdsLiveRevalidationOnlyProbe::ARTIFACT_DIRECTORY.'/'.$runId.'.json',
            json_encode([
                'run_id' => $runId,
                'mode' => 'revalidation-only',
                'probe_mode' => 'plan',
                'supplier_revalidation_call_count' => 0,
                'payload_schema_valid' => true,
                'revalidation_linkage_ready' => true,
            ], JSON_UNESCAPED_SLASHES),
        );

        $exit = Artisan::call('sabre:gds-scenario-revalidation-diagnostic', [
            '--run-id' => $runId,
        ]);

        $this->assertSame(0, $exit);
        $output = Artisan::output();
        $this->assertStringContainsString('artifact_location='.SabreGdsLiveRevalidationOnlyProbe::ARTIFACT_DIRECTORY.'/'.$runId.'.json', $output);
    }

    public function test_linkage_fixture_replay_preserves_linkage_evidence_contract(): void
    {
        Artisan::call('sabre:gds-scenario-revalidation-diagnostic', [
            '--linkage-fixture' => base_path('tests/fixtures/sabre/revalidation/http-200-informational-warning-31-candidates-linkage.json'),
        ]);

        $output = Artisan::output();
        foreach ([
            'blocking_application_error_present=false',
            'blocking_application_warning_present=false',
            'informational_warning_present=true',
            'application_error_count=0',
            'application_warning_count=1',
            'exact_segment_signature_match_count=2',
            'exact_itinerary_match_count=1',
            'pricing_compatible_match_count=2',
            'fare_basis_compatible_match_count=1',
            'booking_class_compatible_match_count=1',
            'unique_usable_linkage_match_count=1',
            'ambiguous_linkage_match_count=0',
            'selected_response_candidate_ordinal=2',
            'pricing_complete=true',
            'usable_fare_linkage=true',
            'freshness_satisfied=true',
            'retry_safe=false',
            'retry_idempotency_safe=false',
        ] as $needle) {
            $this->assertStringContainsString($needle, $output, 'Missing '.$needle);
        }
    }

    public function test_linkage_fixture_replay_output_contains_no_sensitive_identifiers(): void
    {
        Artisan::call('sabre:gds-scenario-revalidation-diagnostic', [
            '--linkage-fixture' => base_path('tests/fixtures/sabre/revalidation/http-200-informational-warning-31-candidates-linkage.json'),
        ]);

        $output = strtolower(Artisan::output());
        foreach (['requestorid', 'pcc', 'password', 'bearer', 'authorization', 'groupeditineraryresponse":{'] as $needle) {
            $this->assertStringNotContainsString($needle, $output, 'Sensitive marker leaked: '.$needle);
        }
    }

    public function test_scenario_run_lookup_still_reads_scenario_runs_directory(): void
    {
        Storage::fake('local');
        $runId = '952d8cfe-793f-48d2-a535-ca923a67311e';
        Storage::disk('local')->put('sabre-gds-scenario-runs/'.$runId.'.json', json_encode([
            'run_id' => $runId,
            'mode' => 'book',
            'scenario_results' => [[
                'scenario' => 'pk-direct',
                'revalidation_attempted' => true,
                'revalidation_success' => false,
                'revalidation_reason_code' => 'scenario_revalidation_failed',
            ]],
        ], JSON_UNESCAPED_SLASHES));

        $exit = Artisan::call('sabre:gds-scenario-revalidation-diagnostic', [
            '--run-id' => $runId,
        ]);

        $this->assertSame(0, $exit);
        $output = Artisan::output();
        $this->assertStringContainsString('artifact_location=sabre-gds-scenario-runs/'.$runId.'.json', $output);
        $this->assertStringContainsString('revalidation_reason_code=scenario_revalidation_failed', $output);
    }
}
