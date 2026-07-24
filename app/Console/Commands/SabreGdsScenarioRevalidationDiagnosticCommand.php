<?php

namespace App\Console\Commands;

use App\Support\Sabre\Revalidation\SabreGdsRevalidationApplicationMessageDiagnostics;
use App\Support\Sabre\Revalidation\SabreGdsRevalidationCanonicalSignatureRuntimePropagation;
use App\Support\Sabre\Revalidation\SabreGdsRevalidationLinkageAggregateContract;
use App\Support\Sabre\Revalidation\SabreGdsRevalidationResponseCandidateLinker;
use App\Support\Sabre\Revalidation\SabreGdsRevalidationSanitizedOutcomeContract;
use App\Support\Sabre\Revalidation\SabreGdsRevalidationHttpSupplierErrorSanitizer;
use App\Support\Sabre\Scenario\SabreGdsLiveRevalidationOnlyProbe;
use App\Support\Sabre\Scenario\SabreGdsLiveScenarioRevalidationOutcomeMapper;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

/**
 * Read-only replay/inspection of Sabre GDS scenario revalidation outcome mapping (no supplier HTTP, no Booking/PNR).
 */
class SabreGdsScenarioRevalidationDiagnosticCommand extends Command
{
    protected $signature = 'sabre:gds-scenario-revalidation-diagnostic
                            {--fixture= : Path to sanitized outcome JSON fixture}
                            {--sanitizer-fixture= : Path to representative HTTP error JSON for read-only sanitization replay}
                            {--linkage-fixture= : Path to sanitized groupedItineraryResponse structure fixture for read-only linkage replay}
                            {--run-id= : Inspect stored scenario run JSON by run_id}
                            {--probe-run-id= : Inspect stored revalidation-only probe artifact by run_id}
                            {--stored-signature-diagnostics= : Read-only canonical signature diagnostics from a stored scenario/probe artifact run_id}
                            {--context-json= : Optional JSON context for mapper (selected_total, route, draft segments, etc.)}';

    protected $description = '[read-only] Replay or inspect Sabre GDS scenario revalidation outcome mapping';

    public function handle(
        SabreGdsLiveScenarioRevalidationOutcomeMapper $mapper,
        SabreGdsRevalidationHttpSupplierErrorSanitizer $sanitizer,
    ): int {
        $fixturePath = trim((string) ($this->option('fixture') ?? ''));
        $sanitizerFixturePath = trim((string) ($this->option('sanitizer-fixture') ?? ''));
        $linkageFixturePath = trim((string) ($this->option('linkage-fixture') ?? ''));
        $runId = trim((string) ($this->option('run-id') ?? ''));
        $probeRunId = trim((string) ($this->option('probe-run-id') ?? ''));
        $storedSignatureRunId = trim((string) ($this->option('stored-signature-diagnostics') ?? ''));

        if ($fixturePath === '' && $sanitizerFixturePath === '' && $linkageFixturePath === '' && $runId === '' && $probeRunId === '' && $storedSignatureRunId === '') {
            $this->components->error('Provide --fixture=path, --sanitizer-fixture=path, --linkage-fixture=path, --run-id=uuid, --probe-run-id=uuid, or --stored-signature-diagnostics=uuid.');

            return self::FAILURE;
        }

        $context = $this->parseContextJson();
        if ($storedSignatureRunId !== '') {
            return $this->inspectStoredSignatureDiagnostics($storedSignatureRunId);
        }
        if ($linkageFixturePath !== '') {
            return $this->replayLinkageFixture($mapper, $linkageFixturePath, $context);
        }
        if ($probeRunId !== '') {
            return $this->inspectStoredProbeRun($mapper, $probeRunId, $context);
        }
        if ($sanitizerFixturePath !== '') {
            return $this->replaySanitizerFixture($mapper, $sanitizer, $sanitizerFixturePath, $context);
        }
        if ($fixturePath !== '') {
            return $this->replayFixture($mapper, $fixturePath, $context);
        }

        return $this->inspectStoredRun($mapper, $runId, $context);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    protected function replaySanitizerFixture(
        SabreGdsLiveScenarioRevalidationOutcomeMapper $mapper,
        SabreGdsRevalidationHttpSupplierErrorSanitizer $sanitizer,
        string $fixturePath,
        array $context,
    ): int {
        $resolvedPath = $this->resolveSanitizerFixturePath($fixturePath);
        if (! File::isFile($resolvedPath)) {
            $this->components->error('Sanitizer fixture not found: '.$resolvedPath);

            return self::FAILURE;
        }

        $decoded = json_decode((string) File::get($resolvedPath), true);
        if (! is_array($decoded)) {
            $this->components->error('Sanitizer fixture must be a JSON object.');

            return self::FAILURE;
        }

        $httpStatus = (int) ($decoded['http_status'] ?? 400);
        $body = is_array($decoded['body'] ?? null) ? $decoded['body'] : $decoded;
        $errorDigest = is_array($decoded['error_digest'] ?? null) ? $decoded['error_digest'] : [];
        $supplier = $sanitizer->extract($httpStatus, $body, null, $errorDigest);

        $this->line('mode=sanitizer_fixture_replay');
        $this->line('http_status='.$httpStatus);
        foreach ([
            'supplier_error_type',
            'supplier_error_code',
            'supplier_error_message_safe',
            'supplier_additional_messages_summary',
            'supplier_http_failure_classification',
            'automatic_retry_allowed',
            'same_payload_retry_recommended',
        ] as $key) {
            if (! array_key_exists($key, $supplier)) {
                continue;
            }
            $value = $supplier[$key];
            if (is_bool($value)) {
                $this->line($key.'='.($value ? 'true' : 'false'));
            } elseif (is_scalar($value)) {
                $this->line($key.'='.(string) $value);
            }
        }

        if (isset($supplier['supplier_additional_message_codes']) && is_array($supplier['supplier_additional_message_codes'])) {
            $this->line('supplier_additional_message_codes='.json_encode($supplier['supplier_additional_message_codes'], JSON_UNESCAPED_SLASHES));
        }
        if (isset($supplier['supplier_validation_paths']) && is_array($supplier['supplier_validation_paths'])) {
            $this->line('supplier_validation_paths='.json_encode($supplier['supplier_validation_paths'], JSON_UNESCAPED_SLASHES));
        }

        $outcome = SabreGdsRevalidationSanitizedOutcomeContract::wrap(array_merge([
            'success' => false,
            'http_status' => $httpStatus,
            'reason_code' => 'sabre_revalidation_failed',
            'revalidation_failure_class' => 'http_rejected',
            'payload_style' => (string) ($decoded['payload_style'] ?? 'iati_like_bfm_revalidate_v1'),
            'endpoint_path' => (string) ($decoded['endpoint_path'] ?? '/v4/shop/flights/revalidate'),
            'response_structure' => is_array($decoded['response_structure'] ?? null) ? $decoded['response_structure'] : [
                'top_level_keys' => implode(',', array_keys($body)),
                'key_paths' => '',
                'empty_body' => 'false',
                'json_valid' => 'true',
                'candidate_fields' => '',
                'candidate_count' => '0',
            ],
        ], $supplier), true, true);

        $evidence = $mapper->mapToScenarioEvidence($outcome, array_merge([
            'selected_total' => 520.83,
            'selected_currency' => 'USD',
        ], $context));
        $this->line('--- mapped_scenario_evidence ---');
        $this->printEvidence($evidence);

        return self::SUCCESS;
    }

    protected function resolveSanitizerFixturePath(string $fixturePath): string
    {
        if (in_array(strtolower($fixturePath), ['representative', 'http-400-representative'], true)) {
            return base_path('tests/fixtures/sabre/revalidation/http-400-supplier-error-representative.json');
        }

        return $fixturePath;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    protected function replayLinkageFixture(
        SabreGdsLiveScenarioRevalidationOutcomeMapper $mapper,
        string $fixturePath,
        array $context,
    ): int {
        if (! File::isFile($fixturePath)) {
            $this->components->error('Linkage fixture not found: '.$fixturePath);

            return self::FAILURE;
        }

        $decoded = json_decode((string) File::get($fixturePath), true);
        if (! is_array($decoded)) {
            $this->components->error('Linkage fixture must be a JSON object.');

            return self::FAILURE;
        }

        $response = is_array($decoded['response'] ?? null) ? $decoded['response'] : $decoded;
        $draft = is_array($decoded['api_draft'] ?? null) ? $decoded['api_draft'] : $context;
        $responseStructure = is_array($decoded['response_structure'] ?? null) ? $decoded['response_structure'] : [
            'top_level_keys' => 'groupedItineraryResponse',
            'key_paths' => '',
            'empty_body' => 'false',
            'json_valid' => 'true',
            'candidate_fields' => 'totalFare',
            'candidate_count' => '0',
        ];
        $declaredCandidateCount = $this->resolveDeclaredCandidateCount($responseStructure);
        $selectedContext = app(SabreGdsRevalidationResponseCandidateLinker::class)->buildSelectedContextFromDraft($draft);
        $applicationDiagnostics = app(SabreGdsRevalidationApplicationMessageDiagnostics::class)->analyze($response);
        $linkageAnalysis = app(SabreGdsRevalidationResponseCandidateLinker::class)->analyze(
            $response,
            $selectedContext,
            $declaredCandidateCount,
        );
        $canonicalNormalization = app(SabreGdsRevalidationCanonicalSignatureRuntimePropagation::class)->postResponseDiagnostics(
            $draft,
            $selectedContext,
            $linkageAnalysis,
            $response,
        );
        $linkageAnalysis['canonical_linkage_normalization'] = $canonicalNormalization;
        $aggregates = app(SabreGdsRevalidationLinkageAggregateContract::class)->normalize(
            $linkageAnalysis,
            $canonicalNormalization,
        );
        $linkageAnalysis = array_merge($linkageAnalysis, array_filter([
            'selected_fare_basis_complete' => $aggregates['selected_fare_basis_complete'],
            'draft_fare_basis_complete' => $aggregates['draft_fare_basis_complete'],
            'candidate_fare_basis_complete' => $aggregates['candidate_fare_basis_complete'],
            'overall_fare_basis_complete' => $aggregates['overall_fare_basis_complete'],
            'fare_basis_complete' => $aggregates['fare_basis_complete'],
            'pricing_complete' => $aggregates['pricing_complete'],
            'usable_fare_linkage' => $aggregates['usable_fare_linkage'],
            'linkage_aggregate_derivation' => array_filter([
                'aggregate_derivation_inputs' => $aggregates['aggregate_derivation_inputs'],
                'aggregate_derivation_predicate' => $aggregates['aggregate_derivation_predicate'],
                'aggregate_derivation_source' => $aggregates['aggregate_derivation_source'],
            ], static fn ($value) => $value !== null && $value !== []),
        ], static fn ($value) => $value !== null));
        $replaySuccess = ($aggregates['usable_fare_linkage'] ?? false) === true
            && ! app(SabreGdsRevalidationApplicationMessageDiagnostics::class)->hasBlockingMessages($applicationDiagnostics);

        $outcome = SabreGdsRevalidationSanitizedOutcomeContract::wrap([
            'success' => $replaySuccess,
            'http_status' => 200,
            'reason_code' => $replaySuccess ? 'sabre_revalidation_ok' : 'sabre_revalidation_failed',
            'revalidation_attempted' => false,
            'payload_style' => (string) ($decoded['payload_style'] ?? 'bfm_revalidate_v1'),
            'endpoint_path' => (string) ($decoded['endpoint_path'] ?? '/v4/shop/flights/revalidate'),
            'response_structure' => $responseStructure,
            'application_message_diagnostics' => app(SabreGdsRevalidationApplicationMessageDiagnostics::class)->safeDigestSlice($applicationDiagnostics),
            'response_linkage_diagnostics' => $linkageAnalysis,
            'canonical_linkage_normalization' => $canonicalNormalization,
            'fixture_response_present' => true,
            'fixture_response_analyzed' => true,
            'replay_performed' => true,
            'supplier_revalidation_call_count' => 0,
            'db_mutation_detected' => false,
            'linkage' => $replaySuccess
                ? app(SabreGdsRevalidationResponseCandidateLinker::class)->extractLinkageForSelectedCandidate($response, $linkageAnalysis)
                : [],
        ], false, false);

        $evidence = $mapper->mapToScenarioEvidence($outcome, array_merge([
            'selected_total' => $selectedContext['selected_total'] ?? null,
            'selected_currency' => $selectedContext['selected_currency'] ?? null,
        ], $context));
        $evidence['mode'] = 'linkage_fixture_replay';
        $this->printEvidence($evidence);

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    protected function replayFixture(
        SabreGdsLiveScenarioRevalidationOutcomeMapper $mapper,
        string $fixturePath,
        array $context,
    ): int {
        if (! File::isFile($fixturePath)) {
            $this->components->error('Fixture not found: '.$fixturePath);

            return self::FAILURE;
        }

        $decoded = json_decode((string) File::get($fixturePath), true);
        if (! is_array($decoded)) {
            $this->components->error('Fixture must be a JSON object.');

            return self::FAILURE;
        }

        $outcome = is_array($decoded['outcome'] ?? null) ? $decoded['outcome'] : $decoded;
        $fixtureContext = is_array($decoded['context'] ?? null) ? $decoded['context'] : [];
        $evidence = $mapper->mapToScenarioEvidence($outcome, array_merge($fixtureContext, $context));
        $this->printEvidence($evidence);

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    protected function inspectStoredRun(
        SabreGdsLiveScenarioRevalidationOutcomeMapper $mapper,
        string $runId,
        array $context,
    ): int {
        $located = $this->locateStoredArtifact($runId, [
            'sabre-gds-scenario-runs',
            SabreGdsLiveRevalidationOnlyProbe::ARTIFACT_DIRECTORY,
        ]);
        if ($located === null) {
            $this->components->error('Stored run not found. Searched sabre-gds-scenario-runs/'.$runId.'.json and '.SabreGdsLiveRevalidationOnlyProbe::ARTIFACT_DIRECTORY.'/'.$runId.'.json');
            $this->line('hint=use --probe-run-id for revalidation-only probe artifacts under '.SabreGdsLiveRevalidationOnlyProbe::ARTIFACT_DIRECTORY);

            return self::FAILURE;
        }

        $run = json_decode((string) Storage::disk('local')->get($located['relative_path']), true);
        if (! is_array($run)) {
            $this->components->error('Stored run JSON is invalid.');

            return self::FAILURE;
        }

        $this->line('run_id='.$runId);
        $this->line('artifact_location='.$located['relative_path']);
        $this->line('mode='.(string) ($run['mode'] ?? ''));
        if ($located['kind'] === 'probe') {
            return $this->printStoredProbeArtifact($mapper, $run, $context);
        }

        $results = is_array($run['scenario_results'] ?? null) ? $run['scenario_results'] : [];
        if ($results === []) {
            $this->components->warn('No scenario_results in stored run.');

            return self::SUCCESS;
        }

        foreach ($results as $index => $result) {
            if (! is_array($result)) {
                continue;
            }
            $this->line('--- scenario_result_index='.$index.' scenario='.(string) ($result['scenario'] ?? ''));
            if (isset($result['revalidation_diagnostics']) || isset($result['revalidation_reason_code'])) {
                $this->printEvidence($result);

                continue;
            }

            $syntheticOutcome = [
                'success' => ($result['revalidation_success'] ?? false) === true,
                'revalidation_attempted' => ($result['revalidation_attempted'] ?? false) === true,
                'reason_code' => $result['reason_code'] ?? $result['error'] ?? null,
            ];
            $evidence = $mapper->mapToScenarioEvidence($syntheticOutcome, array_merge([
                'selected_total' => $result['selected_total'] ?? null,
                'selected_currency' => $result['selected_currency'] ?? null,
                'selected_offer_fingerprint' => $result['selected_offer_fingerprint'] ?? null,
                'pre_block_reason' => $result['error'] ?? null,
            ], $context));
            $this->line('note=legacy_run_replayed_with_sparse_outcome');
            $this->printEvidence($evidence);
        }

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    protected function inspectStoredProbeRun(
        SabreGdsLiveScenarioRevalidationOutcomeMapper $mapper,
        string $runId,
        array $context,
    ): int {
        $located = $this->locateStoredArtifact($runId, [SabreGdsLiveRevalidationOnlyProbe::ARTIFACT_DIRECTORY]);
        if ($located === null) {
            $this->components->error('Stored probe artifact not found: '.SabreGdsLiveRevalidationOnlyProbe::ARTIFACT_DIRECTORY.'/'.$runId.'.json');

            return self::FAILURE;
        }

        $run = json_decode((string) Storage::disk('local')->get($located['relative_path']), true);
        if (! is_array($run)) {
            $this->components->error('Stored probe artifact JSON is invalid.');

            return self::FAILURE;
        }

        $this->line('run_id='.$runId);
        $this->line('artifact_location='.$located['relative_path']);
        $this->line('mode=probe_artifact_inspect');

        return $this->printStoredProbeArtifact($mapper, $run, $context);
    }

    /**
     * @param  array<string, mixed>  $artifact
     * @param  array<string, mixed>  $context
     */
    protected function printStoredProbeArtifact(
        SabreGdsLiveScenarioRevalidationOutcomeMapper $mapper,
        array $artifact,
        array $context,
    ): int {
        $this->line('supplier_revalidation_call_count='.(string) ((int) ($artifact['supplier_revalidation_call_count'] ?? 0)));
        $this->line('db_mutation_detected='.(($artifact['db_mutation_detected'] ?? false) === true ? 'true' : 'false'));

        if ($this->probeArtifactIsReplayable($artifact)) {
            $this->line('note=stored_probe_artifact_replayable_with_sanitized_evidence');
            $this->printEvidence($artifact);

            return self::SUCCESS;
        }

        $this->line('note=stored_probe_artifact_summary_only_not_replayable');
        $this->line('hint=use --linkage-fixture with a sanitized groupedItineraryResponse structure for read-only linkage replay');
        foreach ([
            'revalidation_reason_code',
            'revalidation_success',
            'reason_code',
            'response_candidate_count',
            'structurally_eligible_candidate_count',
            'unique_usable_linkage_match_count',
            'selected_response_candidate_ordinal',
            'usable_fare_linkage',
            'pricing_complete',
            'payload_schema_valid',
            'revalidation_linkage_ready',
        ] as $key) {
            if (! array_key_exists($key, $artifact)) {
                continue;
            }
            $value = $artifact[$key];
            if (is_bool($value)) {
                $this->line($key.'='.($value ? 'true' : 'false'));
            } elseif (is_scalar($value)) {
                $this->line($key.'='.(string) $value);
            }
        }

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $artifact
     */
    protected function probeArtifactIsReplayable(array $artifact): bool
    {
        if (isset($artifact['response']) && is_array($artifact['response'])) {
            return true;
        }

        $diagnostics = $artifact['revalidation_diagnostics'] ?? null;
        $summary = $artifact['response_structure_summary'] ?? null;
        if (! is_array($diagnostics) || ! is_array($summary)) {
            return false;
        }

        $hasCandidateStructure = ($summary['candidate_fields_present'] ?? false) === true
            || (($summary['top_level_keys'] ?? []) !== []);

        return $hasCandidateStructure
            && (
                isset($diagnostics['structurally_eligible_candidate_count'])
                || isset($diagnostics['selected_response_candidate_ordinal'])
            );
    }

    /**
     * @param  list<string>  $directories
     * @return array{relative_path: string, kind: string}|null
     */
    protected function locateStoredArtifact(string $runId, array $directories): ?array
    {
        foreach ($directories as $directory) {
            $relativePath = rtrim($directory, '/').'/'.$runId.'.json';
            if (! Storage::disk('local')->exists($relativePath)) {
                continue;
            }

            return [
                'relative_path' => $relativePath,
                'kind' => $directory === SabreGdsLiveRevalidationOnlyProbe::ARTIFACT_DIRECTORY ? 'probe' : 'scenario',
            ];
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $responseStructure
     */
    protected function resolveDeclaredCandidateCount(array $responseStructure): int
    {
        $raw = $responseStructure['candidate_count'] ?? 0;

        return is_numeric($raw) ? (int) $raw : 0;
    }

    /**
     * @return array<string, mixed>
     */
    protected function parseContextJson(): array
    {
        $raw = trim((string) ($this->option('context-json') ?? ''));
        if ($raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    protected function inspectStoredSignatureDiagnostics(string $runId): int
    {
        $located = $this->locateStoredArtifact($runId, [
            'sabre-gds-scenario-runs',
            SabreGdsLiveRevalidationOnlyProbe::ARTIFACT_DIRECTORY,
        ]);
        if ($located === null) {
            $this->components->error('Stored run not found for signature diagnostics: '.$runId);

            return self::FAILURE;
        }

        $run = json_decode((string) Storage::disk('local')->get($located['relative_path']), true);
        if (! is_array($run)) {
            $this->components->error('Stored run JSON is invalid.');

            return self::FAILURE;
        }

        $diagnostics = app(SabreGdsRevalidationCanonicalSignatureRuntimePropagation::class)
            ->extractStoredArtifactSignatureDiagnostics($run);
        $this->line('mode=stored_signature_diagnostics_summary');
        $this->line('run_id='.$runId);
        $this->line('artifact_location='.$located['relative_path']);
        $this->line('supplier_call_attempted=false');
        $this->line('replay_performed=false');
        $this->line('db_mutation_detected=false');
        if ($diagnostics === []) {
            $this->components->warn('No canonical linkage normalization diagnostics persisted on this artifact (legacy run or pre-propagation build).');

            return self::SUCCESS;
        }

        $this->line('canonical_signature_diagnostics='.json_encode($diagnostics, JSON_UNESCAPED_SLASHES));

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $evidence
     */
    protected function printEvidence(array $evidence): void
    {
        foreach ([
            'mode',
            'revalidation_reason_code',
            'revalidation_failure_category',
            'revalidation_http_status',
            'revalidation_endpoint_path',
            'supplier_call_attempted',
            'supplier_response_received',
            'revalidation_style',
            'revalidation_attempted',
            'revalidation_success',
            'freshness_satisfied',
            'selected_total',
            'selected_currency',
            'revalidated_total',
            'revalidated_currency',
            'fare_changed',
            'retry_safe',
            'revalidation_correlation_id',
            'selected_route',
            'selected_segment_count',
            'block_reason',
            'reason_code',
            'error',
            'supplier_error_type',
            'supplier_error_code',
            'supplier_error_message_safe',
            'supplier_additional_messages_summary',
            'automatic_retry_allowed',
            'same_payload_retry_recommended',
            'retry_idempotency_safe',
            'blocking_application_error_present',
            'blocking_application_warning_present',
            'informational_warning_present',
            'application_error_count',
            'application_warning_count',
            'response_candidate_count',
            'structurally_eligible_candidate_count',
            'exact_segment_signature_match_count',
            'exact_itinerary_match_count',
            'pricing_compatible_match_count',
            'fare_basis_compatible_match_count',
            'booking_class_compatible_match_count',
            'unique_usable_linkage_match_count',
            'ambiguous_linkage_match_count',
            'usable_fare_linkage',
            'linkage_failure_reason_code',
            'selected_response_candidate_ordinal',
            'pricing_complete',
            'fare_basis_complete',
            'fixture_response_present',
            'fixture_response_analyzed',
            'replay_performed',
            'supplier_revalidation_call_count',
            'db_mutation_detected',
            'canonical_signature_version',
            'selected_segment_signature_digest',
            'draft_segment_signature_digest',
            'selected_draft_signature_equal',
            'fare_basis_applicability_match_count',
            'booking_class_compatibility_count',
        ] as $key) {
            if (! array_key_exists($key, $evidence)) {
                continue;
            }
            $value = $evidence[$key];
            if (is_bool($value)) {
                $this->line($key.'='.($value ? 'true' : 'false'));
            } elseif (is_scalar($value)) {
                $this->line($key.'='.(string) $value);
            }
        }

        if (isset($evidence['response_structure_summary']) && is_array($evidence['response_structure_summary'])) {
            $this->line('response_structure_summary='.json_encode($evidence['response_structure_summary'], JSON_UNESCAPED_SLASHES));
        }
        if (isset($evidence['canonical_linkage_normalization']) && is_array($evidence['canonical_linkage_normalization'])) {
            $this->line('canonical_linkage_normalization='.json_encode($evidence['canonical_linkage_normalization'], JSON_UNESCAPED_SLASHES));
        }
        if (isset($evidence['revalidation_diagnostics']) && is_array($evidence['revalidation_diagnostics'])) {
            $this->line('revalidation_diagnostics='.json_encode($evidence['revalidation_diagnostics'], JSON_UNESCAPED_SLASHES));
        }
        if (isset($evidence['supplier_additional_message_codes']) && is_array($evidence['supplier_additional_message_codes'])) {
            $this->line('supplier_additional_message_codes='.json_encode($evidence['supplier_additional_message_codes'], JSON_UNESCAPED_SLASHES));
        }
        if (isset($evidence['supplier_validation_paths']) && is_array($evidence['supplier_validation_paths'])) {
            $this->line('supplier_validation_paths='.json_encode($evidence['supplier_validation_paths'], JSON_UNESCAPED_SLASHES));
        }
    }
}
