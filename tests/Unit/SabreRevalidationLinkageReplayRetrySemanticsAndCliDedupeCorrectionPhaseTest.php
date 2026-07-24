<?php

namespace Tests\Unit;

use App\Support\Sabre\Revalidation\SabreGdsRevalidationSanitizedOutcomeContract;
use App\Support\Sabre\Scenario\SabreGdsLiveRevalidationOnlyProbe;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SabreRevalidationLinkageReplayRetrySemanticsAndCliDedupeCorrectionPhaseTest extends TestCase
{
    use RefreshDatabase;

    private const LINKAGE_FIXTURE = 'tests/fixtures/sabre/revalidation/http-200-informational-warning-31-candidates-linkage.json';

    public function test_successful_fixture_replay_emits_retry_safe_false(): void
    {
        Artisan::call('sabre:gds-scenario-revalidation-diagnostic', [
            '--linkage-fixture' => base_path(self::LINKAGE_FIXTURE),
        ]);

        $output = Artisan::output();
        $this->assertStringContainsString('revalidation_success=true', $output);
        $this->assertStringContainsString('retry_safe=false', $output);
        $this->assertStringNotContainsString('retry_safe=true', $output);
    }

    public function test_successful_fixture_replay_emits_retry_idempotency_safe_false(): void
    {
        Artisan::call('sabre:gds-scenario-revalidation-diagnostic', [
            '--linkage-fixture' => base_path(self::LINKAGE_FIXTURE),
        ]);

        $output = Artisan::output();
        $this->assertStringContainsString('retry_idempotency_safe=false', $output);
        $this->assertStringNotContainsString('retry_idempotency_safe=true', $output);
    }

    public function test_local_fixture_idempotency_is_not_confused_with_supplier_retry_eligibility(): void
    {
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

        $this->assertFalse($outcome['supplier_call_attempted']);
        $this->assertTrue($outcome['success']);
        $this->assertFalse($outcome['retry_safe']);
        $this->assertFalse($outcome['retry_idempotency_safe']);
    }

    public function test_failed_pre_call_outcome_retains_retry_eligible_semantics(): void
    {
        $outcome = SabreGdsRevalidationSanitizedOutcomeContract::wrap([
            'success' => false,
            'http_status' => null,
            'reason_code' => 'sabre_revalidation_gatekeeper_failed',
            'revalidation_failure_class' => 'gatekeeper_failed',
        ], false, false);

        $this->assertFalse($outcome['success']);
        $this->assertFalse($outcome['supplier_call_attempted']);
        $this->assertTrue($outcome['retry_safe']);
        $this->assertTrue($outcome['retry_idempotency_safe']);
    }

    public function test_failed_supplier_outcome_with_non_retryable_failure_class_is_fail_closed(): void
    {
        $outcome = SabreGdsRevalidationSanitizedOutcomeContract::wrap([
            'success' => false,
            'http_status' => 200,
            'reason_code' => 'sabre_revalidation_failed',
            'revalidation_failure_class' => 'unusable_linkage',
        ], true, true);

        $this->assertFalse($outcome['retry_safe']);
        $this->assertFalse($outcome['retry_idempotency_safe']);
    }

    public function test_linkage_fixture_replay_prints_each_top_level_field_exactly_once(): void
    {
        Artisan::call('sabre:gds-scenario-revalidation-diagnostic', [
            '--linkage-fixture' => base_path(self::LINKAGE_FIXTURE),
        ]);

        $output = Artisan::output();
        foreach ([
            'mode',
            'supplier_call_attempted',
            'supplier_response_received',
            'revalidation_attempted',
            'supplier_revalidation_call_count',
            'db_mutation_detected',
            'blocking_application_error_present',
            'blocking_application_warning_present',
            'informational_warning_present',
            'response_candidate_count',
            'structurally_eligible_candidate_count',
            'unique_usable_linkage_match_count',
            'fixture_response_present',
            'fixture_response_analyzed',
            'replay_performed',
            'retry_safe',
            'retry_idempotency_safe',
        ] as $field) {
            preg_match_all('/^'.preg_quote($field, '/').'=/m', $output, $matches);
            $this->assertCount(1, $matches[0], $field.' should appear exactly once at top level');
        }
    }

    public function test_linkage_fixture_replay_preserves_success_and_candidate_counts(): void
    {
        Artisan::call('sabre:gds-scenario-revalidation-diagnostic', [
            '--linkage-fixture' => base_path(self::LINKAGE_FIXTURE),
        ]);

        $output = Artisan::output();
        $this->assertStringContainsString('mode=linkage_fixture_replay', $output);
        $this->assertStringContainsString('revalidation_success=true', $output);
        $this->assertStringContainsString('reason_code=sabre_revalidation_ok', $output);
        $this->assertStringContainsString('response_candidate_count=31', $output);
        $this->assertStringContainsString('structurally_eligible_candidate_count=2', $output);
        $this->assertStringContainsString('unique_usable_linkage_match_count=1', $output);
        $this->assertStringContainsString('selected_response_candidate_ordinal=2', $output);
    }

    public function test_stored_probe_inspection_remains_summary_only(): void
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
        $this->assertStringContainsString('supplier_revalidation_call_count=1', $output);
        $this->assertStringContainsString('revalidation_success=false', $output);
        $this->assertStringContainsString('note=stored_probe_artifact_summary_only_not_replayable', $output);
        $this->assertStringContainsString('hint=use --linkage-fixture', $output);
        $this->assertStringNotContainsString('mode=linkage_fixture_replay', $output);
    }

    public function test_replay_commands_do_not_mutate_database(): void
    {
        $bookingCountBefore = \App\Models\Booking::query()->count();
        $attemptCountBefore = \App\Models\SupplierBookingAttempt::query()->count();

        Artisan::call('sabre:gds-scenario-revalidation-diagnostic', [
            '--linkage-fixture' => base_path(self::LINKAGE_FIXTURE),
        ]);

        $this->assertSame($bookingCountBefore, \App\Models\Booking::query()->count());
        $this->assertSame($attemptCountBefore, \App\Models\SupplierBookingAttempt::query()->count());
        $this->assertStringContainsString('db_mutation_detected=false', Artisan::output());
    }
}
