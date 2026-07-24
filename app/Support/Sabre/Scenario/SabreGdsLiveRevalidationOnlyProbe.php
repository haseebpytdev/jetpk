<?php

namespace App\Support\Sabre\Scenario;

use App\Enums\SupplierProvider;
use App\Models\SupplierConnection;
use App\Services\Suppliers\Sabre\Booking\SabreBookingService;
use App\Services\Suppliers\Sabre\Gds\SabreGdsRevalidationService;
use App\Services\Suppliers\Sabre\Gds\SabreRevalidationPayloadBuilder;
use App\Support\Bookings\SabrePnrCertificationSupport;
use App\Support\Sabre\Revalidation\SabreGdsRevalidationCanonicalSignatureRuntimePropagation;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Controlled Sabre GDS revalidation-only probe: search → exact-offer linkage → one revalidation call → stop.
 * Never creates bookings, PNRs, tickets, cancellations, or communications.
 */
final class SabreGdsLiveRevalidationOnlyProbe
{
    public const CONFIRM_PRODUCTION_PHRASE = 'APPROVE-LIVE-SABRE-GDS-REVALIDATION-ONLY-PROBE';

    public const CONFIRM_REVALIDATION_PHRASE = 'LIVE-SABRE-GDS-REVALIDATION-ONLY-PROBE';

    public const ARTIFACT_DIRECTORY = 'sabre-gds-revalidation-probes';

    public function __construct(
        private readonly SabreGdsLiveScenarioOfferCatalog $offerCatalog,
        private readonly SabreGdsLiveScenarioPresetResolver $presetResolver,
        private readonly SabreGdsLiveScenarioExactOfferEvidence $exactOfferEvidence,
        private readonly SabreGdsLiveScenarioRevalidationOutcomeMapper $outcomeMapper,
        private readonly SabreGdsScenarioCorrelationRegistry $correlationRegistry,
        private readonly SabreGdsLiveScenarioRunnerPassengerLoader $passengerLoader,
        private readonly SabreBookingService $sabreBookingService,
        private readonly SabreGdsRevalidationService $revalidationService,
        private readonly SabreRevalidationPayloadBuilder $payloadBuilder,
        private readonly SabreGdsRevalidationProbeCallCounter $callCounter,
        private readonly SabreGdsRevalidationProbeDbSnapshot $dbSnapshot,
        private readonly SabrePnrCertificationSupport $certificationSupport,
    ) {}

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function run(array $options): array
    {
        $this->callCounter->reset();
        $runId = (string) Str::uuid();
        $mode = ($options['send'] ?? false) === true ? 'send' : 'plan';
        $dbBefore = $this->dbSnapshot->capture();

        $connectionId = (int) ($options['connection_id'] ?? 0);
        $connection = SupplierConnection::query()
            ->where('id', $connectionId)
            ->where('provider', SupplierProvider::Sabre->value)
            ->first();

        if ($connection === null) {
            return $this->finalizeArtifact($runId, $mode, [
                'run_id' => $runId,
                'mode' => 'revalidation-only',
                'probe_mode' => $mode,
                'error' => 'connection_not_found',
            ], $options, $dbBefore);
        }

        $departureDate = trim((string) ($options['departure_date'] ?? ''));
        if ($departureDate === '') {
            return $this->finalizeArtifact($runId, $mode, [
                'run_id' => $runId,
                'mode' => 'revalidation-only',
                'probe_mode' => $mode,
                'error' => 'departure_date_required',
            ], $options, $dbBefore);
        }

        $passengerPath = trim((string) ($options['passenger_json'] ?? ''));
        if ($passengerPath === '') {
            return $this->finalizeArtifact($runId, $mode, [
                'run_id' => $runId,
                'mode' => 'revalidation-only',
                'probe_mode' => $mode,
                'error' => 'passenger_json_required',
                'supplier_revalidation_call_count' => 0,
            ], $options, $dbBefore);
        }

        try {
            $passengerBundle = $this->passengerLoader->loadFromPath($passengerPath);
        } catch (\InvalidArgumentException) {
            return $this->finalizeArtifact($runId, $mode, [
                'run_id' => $runId,
                'mode' => 'revalidation-only',
                'probe_mode' => $mode,
                'error' => 'passenger_json_invalid',
                'supplier_revalidation_call_count' => 0,
            ], $options, $dbBefore);
        }

        $preset = is_string($options['preset'] ?? null) ? strtolower(trim((string) $options['preset'])) : null;
        $scenarios = $this->presetResolver->resolve(
            $preset,
            (string) ($options['origin'] ?? 'LHE'),
            (string) ($options['destination'] ?? 'JED'),
            $departureDate,
            null,
            'one_way',
            null,
            'ANY',
            'lowest',
        );
        $scenario = $scenarios[0] ?? null;
        if ($scenario === null) {
            return $this->finalizeArtifact($runId, $mode, [
                'run_id' => $runId,
                'mode' => 'revalidation-only',
                'probe_mode' => $mode,
                'error' => 'scenario_unresolved',
            ], $options, $dbBefore);
        }

        $search = $this->offerCatalog->search($connection, $scenario, []);
        $searchCorrelationId = $this->correlationRegistry->searchCorrelationId();
        if (($search['shop_error'] ?? null) !== null) {
            return $this->finalizeArtifact($runId, $mode, [
                'run_id' => $runId,
                'mode' => 'revalidation-only',
                'probe_mode' => $mode,
                'connection_id' => $connectionId,
                'search_correlation_id' => $searchCorrelationId,
                'scenario' => $this->scenarioLabel($scenario),
                'route' => ($scenario['origin'] ?? '').'-'.($scenario['destination'] ?? ''),
                'departure_date' => $departureDate,
                'shop_http_status' => $search['shop_http_status'] ?? 0,
                'error' => $search['shop_error'],
                'supplier_call_planned' => false,
                'booking_planned' => false,
                'pnr_planned' => false,
                'cancellation_planned' => false,
                'ticketing_planned' => false,
            ], $options, $dbBefore);
        }

        $candidateIndex = max(0, (int) ($options['candidate_index'] ?? 0));
        $pick = $this->offerCatalog->pickCandidateByIndex($search['eligible'], $candidateIndex);
        if (($pick['selection_error'] ?? null) !== null || ($pick['candidate'] ?? null) === null) {
            return $this->finalizeArtifact($runId, $mode, [
                'run_id' => $runId,
                'mode' => 'revalidation-only',
                'probe_mode' => $mode,
                'connection_id' => $connectionId,
                'search_correlation_id' => $searchCorrelationId,
                'scenario' => $this->scenarioLabel($scenario),
                'route' => ($scenario['origin'] ?? '').'-'.($scenario['destination'] ?? ''),
                'departure_date' => $departureDate,
                'eligible_offer_count' => count($search['eligible']),
                'selected_candidate_index' => $candidateIndex,
                'error' => $pick['selection_error'] ?? 'selection_failed',
                'supplier_call_planned' => $mode === 'send',
                'booking_planned' => false,
                'pnr_planned' => false,
                'cancellation_planned' => false,
                'ticketing_planned' => false,
            ], $options, $dbBefore);
        }

        /** @var array{row: array<string, mixed>, snap: array<string, mixed>} $candidate */
        $candidate = $pick['candidate'];
        $row = is_array($candidate['row'] ?? null) ? $candidate['row'] : [];
        $offerSnap = is_array($candidate['snap'] ?? null) ? $candidate['snap'] : [];
        $offerSnap['supplier_provider'] = SupplierProvider::Sabre->value;
        $offerSnap['supplier_connection_id'] = $connection->id;
        $selectedFareFamilyOption = is_array($pick['selected_fare_family_option'] ?? null)
            ? $pick['selected_fare_family_option']
            : null;

        $linkage = $this->exactOfferEvidence->buildLinkageContext(
            $connection,
            $offerSnap,
            $row,
            $selectedFareFamilyOption,
            is_string($search['shop_captured_at'] ?? null) ? $search['shop_captured_at'] : null,
        );

        $payloadStyle = $this->resolvePayloadStyle($options);
        $endpointPath = $this->resolveEndpointPath($options);

        $planBase = [
            'run_id' => $runId,
            'mode' => 'revalidation-only',
            'probe_mode' => $mode,
            'connection_id' => $connectionId,
            'search_correlation_id' => $searchCorrelationId,
            'scenario' => $this->scenarioLabel($scenario),
            'preset' => $scenario['preset'] ?? $preset,
            'route' => ($scenario['origin'] ?? '').'-'.($scenario['destination'] ?? ''),
            'departure_date' => $departureDate,
            'eligible_offer_count' => count($search['eligible']),
            'selected_candidate_index' => $candidateIndex,
            'carrier' => $row['validating_carrier'] ?? $row['carrier'] ?? $scenario['carrier'] ?? null,
            'segment_count' => (int) ($row['segment_count'] ?? 0),
            'selected_total' => $linkage['selected_total'] ?? null,
            'selected_currency' => $linkage['currency'] ?? null,
            'selected_offer_fingerprint' => $linkage['safe_offer_fingerprint'] ?? null,
            'selected_segment_signature_hash' => $linkage['segment_signature'] ?? null,
            'selected_source_identifier_hash' => $linkage['source_identifier_hash'] ?? null,
            'revalidation_linkage_ready' => ($linkage['revalidation_linkage_ready'] ?? false) === true,
            'revalidation_linkage_missing_components' => $linkage['revalidation_linkage_missing_components'] ?? [],
            'payload_style' => $payloadStyle,
            'endpoint_path' => $endpointPath,
            'supplier_call_planned' => $mode === 'send',
            'booking_planned' => false,
            'pnr_planned' => false,
            'cancellation_planned' => false,
            'ticketing_planned' => false,
            'ticketing_attempted' => false,
            'airticket_attempted' => false,
            'pnr_attempted' => false,
            'booking_created' => false,
        ];

        if ($mode === 'plan') {
            $draftContext = $this->buildRevalidationDraftContext(
                $connection,
                $offerSnap,
                $row,
                $linkage,
                $selectedFareFamilyOption,
                is_string($search['shop_captured_at'] ?? null) ? $search['shop_captured_at'] : null,
                $passengerBundle,
                $payloadStyle,
                $endpointPath,
            );

            return $this->finalizeArtifact($runId, $mode, array_merge($planBase, [
                'supplier_revalidation_call_count' => 0,
                'revalidation_attempted' => false,
            ], $this->planSchemaFieldsFromDraftContext($draftContext)), $options, $dbBefore);
        }

        $draftContext = $this->buildRevalidationDraftContext(
            $connection,
            $offerSnap,
            $row,
            $linkage,
            $selectedFareFamilyOption,
            is_string($search['shop_captured_at'] ?? null) ? $search['shop_captured_at'] : null,
            $passengerBundle,
            $payloadStyle,
            $endpointPath,
        );

        if (($draftContext['error'] ?? null) !== null) {
            return $this->finalizeArtifact($runId, $mode, array_merge($planBase, [
                'supplier_revalidation_call_count' => 0,
            ], $this->persistableDraftContext($draftContext)), $options, $dbBefore);
        }

        $apiDraft = is_array($draftContext['api_draft'] ?? null) ? $draftContext['api_draft'] : [];
        $structuralDigest = is_array($draftContext['payload_structural_digest'] ?? null)
            ? $draftContext['payload_structural_digest']
            : [];
        $revalidationCorrelationId = $this->correlationRegistry->startRevalidationCorrelation();

        $this->callCounter->recordCall();
        $outcome = $this->revalidationService->revalidateDraft(
            $apiDraft,
            $connection,
            $payloadStyle,
            null,
            $revalidationCorrelationId,
            $endpointPath,
            [
                'run_id' => $runId,
                'search_correlation_id' => $searchCorrelationId,
                'supplier_revalidation_call_count' => 1,
            ],
        );

        $mappingContext = array_filter([
            'selected_total' => (float) ($linkage['selected_total'] ?? 0),
            'selected_currency' => $linkage['currency'] ?? null,
            'selected_offer_fingerprint' => $linkage['safe_offer_fingerprint'] ?? null,
            'revalidation_linkage_ready' => true,
            'offer_source' => $linkage['offer_source'] ?? null,
            'shop_captured_at' => $search['shop_captured_at'] ?? null,
            'selected_segment_signature_hash' => $linkage['segment_signature'] ?? null,
            'selected_source_identifier_hash' => $linkage['source_identifier_hash'] ?? null,
            'selected_route' => ($scenario['origin'] ?? '').'-'.($scenario['destination'] ?? ''),
            'selected_segment_count' => (int) ($row['segment_count'] ?? 0),
            'scenario_search_correlation_id' => $searchCorrelationId,
            'revalidation_correlation_id' => $revalidationCorrelationId,
        ], static fn ($value) => $value !== null);

        $evidence = $this->outcomeMapper->mapToScenarioEvidence($outcome, $mappingContext);

        return $this->finalizeArtifact($runId, $mode, array_merge(
            $planBase,
            $this->planSchemaFieldsFromDraftContext($draftContext),
            [
                'revalidation_correlation_id' => $revalidationCorrelationId,
                'payload_structural_digest' => $structuralDigest,
                'supplier_revalidation_call_count' => $this->callCounter->count(),
                'ambiguous_outcome_stopped' => $this->isAmbiguousOutcome($outcome),
            ],
            $this->outcomeMapper->extractScenarioResultFields($evidence),
            $this->richOutcomeSlice($outcome, $evidence),
        ), $options, $dbBefore);
    }

    /**
     * @param  array<string, mixed>  $offerSnap
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>  $linkage
     * @param  array<string, mixed>|null  $selectedFareFamilyOption
     * @param  array{
     *     passenger: array<string, mixed>,
     *     contact: array<string, mixed>
     * }  $passengerBundle
     * @return array<string, mixed>
     */
    protected function buildRevalidationDraftContext(
        SupplierConnection $connection,
        array $offerSnap,
        array $row,
        array $linkage,
        ?array $selectedFareFamilyOption,
        ?string $shopCapturedAt,
        array $passengerBundle,
        string $payloadStyle,
        string $endpointPath,
    ): array {
        if (($linkage['revalidation_linkage_ready'] ?? false) !== true) {
            $blocked = $this->outcomeMapper->mapBlockedEvidence([
                'block_reason' => SabreGdsLiveScenarioExactOfferEvidence::REASON_EXACT_OFFER_LINKAGE_UNAVAILABLE,
                'attempted' => false,
                'selected_total' => $linkage['selected_total'] ?? null,
                'selected_currency' => $linkage['currency'] ?? null,
                'selected_offer_fingerprint' => $linkage['safe_offer_fingerprint'] ?? null,
                'revalidation_linkage_ready' => false,
            ]);

            return array_merge([
                'error' => SabreGdsLiveScenarioExactOfferEvidence::REASON_EXACT_OFFER_LINKAGE_UNAVAILABLE,
                'revalidation_attempted' => false,
            ], $this->outcomeMapper->extractScenarioResultFields($blocked));
        }

        $continuityMismatch = $this->exactOfferEvidence->assertContinuityMatch(
            $linkage,
            $connection,
            $offerSnap,
            $row,
            $selectedFareFamilyOption,
            $shopCapturedAt,
        );
        if ($continuityMismatch !== null) {
            $blocked = $this->outcomeMapper->mapBlockedEvidence([
                'block_reason' => $continuityMismatch,
                'attempted' => false,
                'selected_total' => $linkage['selected_total'] ?? null,
                'selected_currency' => $linkage['currency'] ?? null,
                'selected_offer_fingerprint' => $linkage['safe_offer_fingerprint'] ?? null,
            ]);

            return array_merge([
                'error' => $continuityMismatch,
                'revalidation_attempted' => false,
            ], $this->outcomeMapper->extractScenarioResultFields($blocked));
        }

        $gate = $this->sabreBookingService->validateNormalizedSabreOffer($offerSnap);
        if (! $gate->success) {
            return [
                'error' => 'offer_gate_failed',
                'revalidation_attempted' => false,
            ];
        }

        $draft = $this->sabreBookingService->prepareBookingPayload($offerSnap, [
            'passengers' => $this->passengersFromBundle($passengerBundle),
        ]);
        if (($draft['_valid'] ?? false) !== true) {
            $blocked = $this->outcomeMapper->mapBlockedEvidence([
                'block_reason' => SabreGdsLiveScenarioRevalidationGate::REASON_DRAFT_INVALID,
                'attempted' => true,
                'selected_total' => $linkage['selected_total'] ?? null,
                'selected_currency' => $linkage['currency'] ?? null,
                'reason_code' => (string) ($draft['code'] ?? 'draft_invalid'),
            ]);

            return array_merge([
                'error' => SabreGdsLiveScenarioRevalidationGate::REASON_DRAFT_INVALID,
                'revalidation_attempted' => true,
                'payload_structural_digest' => $this->buildStructuralDigest($draft, $payloadStyle, $endpointPath),
            ], $this->outcomeMapper->extractScenarioResultFields($blocked));
        }

        $apiDraft = $draft;
        unset($apiDraft['_valid']);
        $apiDraft = app(SabreGdsRevalidationCanonicalSignatureRuntimePropagation::class)->attachRuntimeToDraft(
            $apiDraft,
            $connection,
            $offerSnap,
            $row,
            $selectedFareFamilyOption,
            $linkage,
        );

        return [
            'api_draft' => $apiDraft,
            'payload_structural_digest' => $this->buildStructuralDigest($apiDraft, $payloadStyle, $endpointPath),
            'revalidation_attempted' => false,
        ];
    }

    /**
     * @param  array<string, mixed>  $options
     * @param  array<string, int>  $dbBefore
     * @param  array<string, mixed>  $summary
     * @return array<string, mixed>
     */
    protected function finalizeArtifact(
        string $runId,
        string $mode,
        array $summary,
        array $options,
        array $dbBefore,
    ): array {
        $summary['run_id'] = $runId;
        $summary['mode'] = 'revalidation-only';
        $summary['probe_mode'] = $mode;
        $summary['ticketing_attempted'] = false;
        $summary['airticket_attempted'] = false;
        $summary['pnr_attempted'] = false;
        $summary['booking_created'] = false;

        $dbAfter = $this->dbSnapshot->capture();
        $mutationKey = $this->dbSnapshot->assertUnchanged($dbBefore, $dbAfter);
        $summary['db_snapshot_before'] = $dbBefore;
        $summary['db_snapshot_after'] = $dbAfter;
        $summary['db_mutation_detected'] = $mutationKey === null ? false : $mutationKey;
        if ($mutationKey !== null) {
            $summary['error'] = $summary['error'] ?? 'db_mutation_detected';
            $summary['db_mutation_field'] = $mutationKey;
        }

        if (! array_key_exists('supplier_revalidation_call_count', $summary)) {
            $summary['supplier_revalidation_call_count'] = $this->callCounter->count();
        }

        try {
            $this->certificationSupport->assertOutputSafe($summary);
        } catch (\Throwable) {
            $summary = [
                'run_id' => $runId,
                'mode' => 'revalidation-only',
                'probe_mode' => $mode,
                'error' => 'output_safety_check_failed',
            ];
        }

        $relativePath = self::ARTIFACT_DIRECTORY.'/'.$runId.'.json';
        $customOutput = trim((string) ($options['output'] ?? ''));
        if ($customOutput !== '') {
            $absolutePath = $customOutput;
            $dir = dirname($absolutePath);
            if (! is_dir($dir)) {
                mkdir($dir, 0700, true);
            }
            file_put_contents($absolutePath, json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            @chmod($absolutePath, 0600);
            $summary['output_json_path'] = $absolutePath;
        } else {
            Storage::disk('local')->put($relativePath, json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            $absolutePath = Storage::disk('local')->path($relativePath);
            @chmod($absolutePath, 0600);
            $summary['output_json_path'] = $absolutePath;
        }

        return $summary;
    }

    /**
     * @param  array<string, mixed>  $draftContext
     * @return array<string, mixed>
     */
    protected function planSchemaFieldsFromDraftContext(array $draftContext): array
    {
        $persistable = $this->persistableDraftContext($draftContext);
        $digest = is_array($draftContext['payload_structural_digest'] ?? null)
            ? $draftContext['payload_structural_digest']
            : [];

        foreach ([
            'payload_schema_valid',
            'payload_schema_reason_code',
            'root_version_present',
            'root_version_type_valid',
            'root_child_keys',
            'root_target_present',
            'root_target_type_valid',
            'requestor_id_present',
            'requestor_id_type_valid',
            'requestor_id_non_empty',
            'pos_child_keys',
            'source_child_keys',
            'requestor_id_child_keys',
            'requestor_id_child_types',
            'requestor_identity_source_present',
            'requestor_identity_source_location',
            'pseudo_city_code_present',
            'pseudo_city_code_type_valid',
            'pseudo_city_code_non_empty',
            'pseudo_city_code_source_present',
            'pseudo_city_code_source_location',
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
            'branded_fare_indicator_child_types',
            'branded_fare_context_present',
            'branded_fare_context_location',
            'unsupported_flight_child_keys',
            'pricing_context_present',
            'fare_component_references_present',
            'booking_class_context_present',
            'booking_class_context_location',
            'cabin_context_present',
            'cabin_context_location',
            'fare_basis_context_present',
            'fare_basis_context_location',
            'invalid_schema_paths',
            'invalid_schema_type_count',
            'flight_child_keys',
            'airline_child_keys',
        ] as $key) {
            if (array_key_exists($key, $digest)) {
                $persistable[$key] = $digest[$key];
            }
        }

        if ($digest !== []) {
            $persistable['payload_structural_digest'] = $digest;
        }

        return $persistable;
    }

    /**
     * @param  array<string, mixed>  $draftContext
     * @return array<string, mixed>
     */
    protected function persistableDraftContext(array $draftContext): array
    {
        unset($draftContext['api_draft']);

        return $draftContext;
    }

    /**
     * @param  array<string, mixed>  $apiDraft
     * @return array<string, mixed>
     */
    protected function buildStructuralDigest(array $apiDraft, string $payloadStyle, string $endpointPath): array
    {
        $payload = $this->payloadBuilder->buildPayload($apiDraft, $payloadStyle);
        $coverage = $this->payloadBuilder->normalizedPayloadCoverageSummary($payload);
        $safe = $this->payloadBuilder->safePayloadSummary($payload);
        $schema = $this->payloadBuilder->evaluateRevalidationPayloadSchema($payload, $endpointPath);
        $odis = is_array(data_get($payload, 'OTA_AirLowFareSearchRQ.OriginDestinationInformation'))
            ? data_get($payload, 'OTA_AirLowFareSearchRQ.OriginDestinationInformation')
            : [];

        return array_filter([
            'payload_style' => $payloadStyle,
            'endpoint_path' => $endpointPath,
            'payload_schema_valid' => ($schema['revalidation_payload_schema_valid'] ?? true) === true,
            'payload_schema_reason_code' => $schema['payload_schema_reason_code'] ?? null,
            'root_version_present' => ($schema['root_version_present'] ?? false) === true,
            'root_version_type_valid' => ($schema['root_version_type_valid'] ?? false) === true,
            'root_child_keys' => $schema['root_child_keys'] ?? [],
            'root_target_present' => ($schema['root_target_present'] ?? false) === true,
            'root_target_type_valid' => ($schema['root_target_type_valid'] ?? true) === true,
            'requestor_id_present' => ($schema['requestor_id_present'] ?? false) === true,
            'requestor_id_type_valid' => ($schema['requestor_id_type_valid'] ?? false) === true,
            'requestor_id_non_empty' => ($schema['requestor_id_non_empty'] ?? false) === true,
            'pos_child_keys' => $schema['pos_child_keys'] ?? [],
            'source_child_keys' => $schema['source_child_keys'] ?? [],
            'requestor_id_child_keys' => $schema['requestor_id_child_keys'] ?? [],
            'requestor_id_child_types' => $schema['requestor_id_child_types'] ?? [],
            'requestor_identity_source_present' => ($schema['requestor_identity_source_present'] ?? false) === true,
            'requestor_identity_source_location' => $schema['requestor_identity_source_location'] ?? null,
            'pseudo_city_code_present' => ($schema['pseudo_city_code_present'] ?? false) === true,
            'pseudo_city_code_type_valid' => ($schema['pseudo_city_code_type_valid'] ?? false) === true,
            'pseudo_city_code_non_empty' => ($schema['pseudo_city_code_non_empty'] ?? false) === true,
            'pseudo_city_code_source_present' => ($schema['pseudo_city_code_source_present'] ?? false) === true,
            'pseudo_city_code_source_location' => $schema['pseudo_city_code_source_location'] ?? null,
            'origin_destination_child_keys' => $schema['origin_destination_child_keys'] ?? [],
            'contains_invalid_direct_flight_segment' => ($schema['contains_invalid_direct_flight_segment'] ?? false) === true,
            'airline_marketing_type_valid' => ($schema['airline_marketing_type_valid'] ?? true) === true,
            'airline_operating_type_valid' => ($schema['airline_operating_type_valid'] ?? true) === true,
            'contains_unsupported_segment_number' => ($schema['contains_unsupported_segment_number'] ?? false) === true,
            'contains_unsupported_resbookdesigcode' => ($schema['contains_unsupported_resbookdesigcode'] ?? false) === true,
            'contains_unsupported_fare_basis_code' => ($schema['contains_unsupported_fare_basis_code'] ?? false) === true,
            'contains_unsupported_cabin_code' => ($schema['contains_unsupported_cabin_code'] ?? false) === true,
            'contains_unsupported_single_branded_fare' => ($schema['contains_unsupported_single_branded_fare'] ?? false) === true,
            'unsupported_branded_fare_indicator_keys' => $schema['unsupported_branded_fare_indicator_keys'] ?? [],
            'branded_fare_indicator_child_keys' => $schema['branded_fare_indicator_child_keys'] ?? [],
            'branded_fare_indicator_child_types' => $schema['branded_fare_indicator_child_types'] ?? [],
            'branded_fare_context_present' => ($schema['branded_fare_context_present'] ?? false) === true,
            'branded_fare_context_location' => $schema['branded_fare_context_location'] ?? null,
            'unsupported_flight_child_keys' => $schema['unsupported_flight_child_keys'] ?? [],
            'pricing_context_present' => ($schema['pricing_context_present'] ?? false) === true
                || ($coverage['pricing_context_present'] ?? false) === true,
            'fare_component_references_present' => ($schema['fare_component_references_present'] ?? false) === true
                || ($safe['has_fare_component_refs'] ?? false) === true,
            'booking_class_context_present' => ($schema['booking_class_context_present'] ?? false) === true
                || ($safe['has_booking_class'] ?? false) === true,
            'booking_class_context_location' => $schema['booking_class_context_location'] ?? null,
            'cabin_context_present' => ($schema['cabin_context_present'] ?? false) === true,
            'cabin_context_location' => $schema['cabin_context_location'] ?? null,
            'fare_basis_context_present' => ($schema['fare_basis_context_present'] ?? false) === true
                || ($safe['has_fare_basis'] ?? false) === true,
            'fare_basis_context_location' => $schema['fare_basis_context_location'] ?? null,
            'invalid_schema_paths' => $schema['invalid_schema_paths'] ?? [],
            'invalid_schema_type_count' => (int) ($schema['invalid_schema_type_count'] ?? 0),
            'flight_child_keys' => $schema['flight_child_keys'] ?? [],
            'airline_child_keys' => $schema['airline_child_keys'] ?? [],
            'origin_destination_count' => count($odis),
            'segment_count' => (int) ($coverage['segment_count'] ?? $safe['segment_count'] ?? 0),
            'segment_routes' => array_slice((array) ($safe['segment_routes'] ?? []), 0, 8),
            'booking_classes_complete' => ($safe['has_booking_class'] ?? false) === true,
            'fare_basis_complete' => ($safe['has_fare_basis'] ?? false) === true,
            'has_reconstructed_pricing_context' => ($safe['has_reconstructed_pricing_context'] ?? false) === true,
            'itinerary_indexes_present' => ($safe['has_itinerary_reference'] ?? false) === true,
            'leg_references_present' => ($safe['has_leg_refs'] ?? false) === true,
            'schedule_references_present' => ($safe['has_schedule_refs'] ?? false) === true,
            'selected_itinerary_context_present' => ($coverage['selected_offer_context_present'] ?? false) === true,
            'payload_freeze_fingerprint' => $this->payloadBuilder->revalidationPayloadFreezeFingerprint($payload, $apiDraft),
            'payload_coverage_digest' => $this->payloadCoverageDigest($coverage, $safe),
        ], static function ($value, string $key): bool {
            if (in_array($key, [
                'payload_schema_reason_code',
                'invalid_schema_type_count',
                'unsupported_flight_child_keys',
                'unsupported_branded_fare_indicator_keys',
                'branded_fare_indicator_child_keys',
                'branded_fare_indicator_child_types',
                'branded_fare_context_location',
                'root_child_keys',
                'pos_child_keys',
                'source_child_keys',
                'requestor_id_child_keys',
                'requestor_id_child_types',
                'requestor_identity_source_location',
                'pseudo_city_code_source_location',
                'booking_class_context_location',
                'fare_basis_context_location',
            ], true)) {
                return true;
            }

            return $value !== null && $value !== [] && $value !== '';
        }, ARRAY_FILTER_USE_BOTH);
    }

    /**
     * @param  array<string, mixed>  $coverage
     * @param  array<string, mixed>  $safe
     */
    protected function payloadCoverageDigest(array $coverage, array $safe): string
    {
        $tags = [];
        foreach ([
            'pos' => ($coverage['has_pos'] ?? false) === true,
            'pcc' => ($coverage['has_pcc'] ?? false) === true,
            'odi' => ($coverage['has_origin_destination_information'] ?? false) === true,
            'sel_offer' => ($coverage['selected_offer_context_present'] ?? false) === true,
            'pricing_ctx' => ($coverage['pricing_context_present'] ?? false) === true,
            'itin_ref' => ($safe['has_itinerary_reference'] ?? false) === true,
            'leg_refs' => ($safe['has_leg_refs'] ?? false) === true,
            'sched_refs' => ($safe['has_schedule_refs'] ?? false) === true,
            'booking_class' => ($safe['has_booking_class'] ?? false) === true,
            'fare_basis' => ($safe['has_fare_basis'] ?? false) === true,
            'recon_pricing' => ($safe['has_reconstructed_pricing_context'] ?? false) === true,
        ] as $tag => $present) {
            if ($present) {
                $tags[] = $tag;
            }
        }

        return $tags !== [] ? implode('+', $tags) : 'minimal';
    }

    /**
     * @param  array<string, mixed>  $outcome
     * @param  array<string, mixed>  $evidence
     * @return array<string, mixed>
     */
    protected function richOutcomeSlice(array $outcome, array $evidence): array
    {
        return array_filter([
            'operation' => $outcome['operation'] ?? null,
            'safe_error_code' => $outcome['safe_error_code'] ?? $outcome['reason_code'] ?? null,
            'http_status' => $outcome['http_status'] ?? null,
            'duration_ms' => $outcome['duration_ms'] ?? null,
            'transport_error_category' => $outcome['transport_error_category'] ?? null,
            'exception_class_category' => $outcome['exception_class_category'] ?? null,
            'response_json_valid' => array_key_exists('response_json_valid', $outcome) ? (bool) $outcome['response_json_valid'] : null,
            'response_empty' => array_key_exists('response_empty', $outcome) ? (bool) $outcome['response_empty'] : null,
            'response_top_level_keys' => $outcome['response_top_level_keys'] ?? null,
            'response_candidate_count' => $outcome['response_candidate_count'] ?? null,
            'grouped_itinerary_errors_present' => ($outcome['grouped_itinerary_errors_present'] ?? false) === true ? true : null,
            'application_errors_present' => ($outcome['application_errors_present'] ?? false) === true ? true : null,
            'application_warnings_present' => ($outcome['application_warnings_present'] ?? false) === true ? true : null,
            'blocking_application_error_present' => ($outcome['blocking_application_error_present'] ?? false) === true ? true : null,
            'blocking_application_warning_present' => ($outcome['blocking_application_warning_present'] ?? false) === true ? true : null,
            'informational_warning_present' => ($outcome['informational_warning_present'] ?? false) === true ? true : null,
            'application_error_count' => $outcome['application_error_count'] ?? null,
            'application_warning_count' => $outcome['application_warning_count'] ?? null,
            'application_message_categories' => $outcome['application_message_categories'] ?? null,
            'response_candidate_count' => $outcome['response_candidate_count'] ?? data_get($outcome, 'response_linkage_diagnostics.response_candidate_count'),
            'structurally_eligible_candidate_count' => data_get($outcome, 'response_linkage_diagnostics.structurally_eligible_candidate_count'),
            'exact_segment_signature_match_count' => data_get($outcome, 'response_linkage_diagnostics.exact_segment_signature_match_count'),
            'exact_itinerary_match_count' => data_get($outcome, 'response_linkage_diagnostics.exact_itinerary_match_count'),
            'pricing_compatible_match_count' => data_get($outcome, 'response_linkage_diagnostics.pricing_compatible_match_count'),
            'fare_basis_compatible_match_count' => data_get($outcome, 'response_linkage_diagnostics.fare_basis_compatible_match_count'),
            'booking_class_compatible_match_count' => data_get($outcome, 'response_linkage_diagnostics.booking_class_compatible_match_count'),
            'unique_usable_linkage_match_count' => data_get($outcome, 'response_linkage_diagnostics.unique_usable_linkage_match_count'),
            'ambiguous_linkage_match_count' => data_get($outcome, 'response_linkage_diagnostics.ambiguous_linkage_match_count'),
            'linkage_failure_reason_code' => $outcome['linkage_failure_reason_code'] ?? data_get($outcome, 'response_linkage_diagnostics.linkage_failure_reason_code'),
            'linkage_missing_components' => data_get($outcome, 'response_linkage_diagnostics.linkage_missing_components'),
            'linkage_conflicting_components' => data_get($outcome, 'response_linkage_diagnostics.linkage_conflicting_components'),
            'selected_response_candidate_ordinal' => $outcome['selected_response_candidate_ordinal'] ?? data_get($outcome, 'response_linkage_diagnostics.selected_response_candidate_ordinal'),
            'pricing_complete' => array_key_exists('pricing_complete', $outcome) ? (bool) $outcome['pricing_complete'] : null,
            'payload_freeze_fingerprint' => $outcome['revalidation_freeze_fingerprint'] ?? null,
            'fare_basis_complete' => array_key_exists('fare_basis_complete', $outcome) ? (bool) $outcome['fare_basis_complete'] : null,
            'usable_fare_linkage' => array_key_exists('usable_fare_linkage', $outcome) ? (bool) $outcome['usable_fare_linkage'] : null,
            'offer_unavailable' => ($outcome['offer_unavailable'] ?? false) === true ? true : null,
            'revalidated_total' => $evidence['revalidated_total'] ?? null,
            'revalidated_currency' => $evidence['revalidated_currency'] ?? null,
            'mismatches' => data_get($outcome, 'fare_comparison.mismatches'),
            'response_structure_summary' => $evidence['response_structure_summary'] ?? null,
            'supplier_error_type' => $outcome['supplier_error_type'] ?? null,
            'supplier_error_code' => $outcome['supplier_error_code'] ?? null,
            'supplier_error_message_safe' => $outcome['supplier_error_message_safe'] ?? null,
            'supplier_additional_messages_summary' => $outcome['supplier_additional_messages_summary'] ?? null,
            'supplier_additional_message_codes' => $outcome['supplier_additional_message_codes'] ?? null,
            'supplier_validation_paths' => $outcome['supplier_validation_paths'] ?? null,
            'supplier_error_count' => $outcome['supplier_error_count'] ?? null,
            'supplier_warning_count' => $outcome['supplier_warning_count'] ?? null,
            'automatic_retry_allowed' => array_key_exists('automatic_retry_allowed', $outcome) ? (bool) $outcome['automatic_retry_allowed'] : null,
            'same_payload_retry_recommended' => array_key_exists('same_payload_retry_recommended', $outcome) ? (bool) $outcome['same_payload_retry_recommended'] : null,
            'retry_idempotency_safe' => array_key_exists('retry_idempotency_safe', $outcome) ? (bool) $outcome['retry_idempotency_safe'] : null,
        ], static fn ($value) => $value !== null);
    }

    /**
     * @param  array<string, mixed>  $outcome
     */
    protected function isAmbiguousOutcome(array $outcome): bool
    {
        if (($outcome['supplier_call_attempted'] ?? false) !== true) {
            return false;
        }

        $http = $outcome['http_status'] ?? null;
        if ($http === null) {
            return true;
        }

        if (($outcome['response_json_valid'] ?? true) === false) {
            return true;
        }

        if (($outcome['success'] ?? false) === true && ($outcome['usable_fare_linkage'] ?? false) !== true) {
            return true;
        }

        return false;
    }

    /**
     * @param  array{
     *     passenger: array<string, mixed>,
     *     contact: array<string, mixed>
     * }  $passengerBundle
     * @return list<array<string, mixed>>
     */
    protected function passengersFromBundle(array $passengerBundle): array
    {
        $passenger = is_array($passengerBundle['passenger'] ?? null) ? $passengerBundle['passenger'] : [];

        return [[
            'type' => (string) ($passenger['passenger_type'] ?? 'adult'),
            'first_name' => (string) ($passenger['first_name'] ?? ''),
            'last_name' => (string) ($passenger['last_name'] ?? ''),
        ]];
    }

    /**
     * @param  array<string, mixed>  $options
     */
    protected function resolvePayloadStyle(array $options): string
    {
        $override = trim((string) ($options['payload_style'] ?? ''));

        return $override !== ''
            ? $override
            : trim((string) config('suppliers.sabre.revalidate_payload_style', 'bfm_revalidate_v1'));
    }

    /**
     * @param  array<string, mixed>  $options
     */
    protected function resolveEndpointPath(array $options): string
    {
        $override = trim((string) ($options['endpoint_path'] ?? ''));

        return $override !== ''
            ? $override
            : trim((string) config('suppliers.sabre.revalidate_path', '/v4/shop/flights/revalidate'));
    }

    /**
     * @param  array<string, mixed>  $scenario
     */
    protected function scenarioLabel(array $scenario): string
    {
        $preset = $scenario['preset'] ?? null;
        if (is_string($preset) && $preset !== '') {
            return $preset;
        }

        return implode('/', array_filter([
            $scenario['origin'] ?? '',
            $scenario['destination'] ?? '',
            $scenario['scenario_key'] ?? '',
        ]));
    }
}
