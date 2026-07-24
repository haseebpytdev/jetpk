<?php

namespace App\Support\Sabre\Scenario;

use App\Enums\BookingCommunicationEvent;
use App\Enums\BookingStatus;
use App\Enums\SupplierProvider;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\CommunicationLog;
use App\Models\SupplierBooking;
use App\Models\SupplierBookingAttempt;
use App\Models\SupplierConnection;
use App\Services\Suppliers\Sabre\Booking\SabreBookingService;
use App\Services\Suppliers\Sabre\Cancel\SabreGdsCancelService;
use App\Services\Suppliers\Sabre\Cancel\SabreGdsCancellationReconciliationService;
use App\Services\Suppliers\Sabre\PnrRetrieve\SabrePnrItinerarySyncService;
use App\Services\Communication\BookingCommunicationService;
use App\Support\Bookings\SabrePnrCertificationSupport;
use App\Support\Bookings\SupplierBookingAttemptGuard;
use App\Support\Sabre\GdsPnrCreate\SabreGdsAutoPnrContextCompletionService;
use App\Support\Sabre\GdsPnrCreate\SabreGdsMixedCarrierCertificationGate;
use App\Support\Sabre\GdsPnrCreate\SabreGdsPnrCreateStrategyDigest;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Orchestrates Sabre GDS live scenario runs (plan/book/retrieve/cancel) with safe summaries only.
 */
final class SabreGdsLiveScenarioRunner
{
    public const CONFIRM_PHRASE = 'LIVE-SABRE-GDS-SCENARIO-RUNNER';

    public const PRODUCTION_OPS_APPROVAL_PHRASE = 'APPROVE-LIVE-SABRE-GDS-SCENARIO-RUNNER';

    public const MIXED_CARRIER_CERTIFICATION_APPROVAL_PHRASE = SabreGdsMixedCarrierCertificationGate::APPROVAL_PHRASE;

    public const CANCEL_APPROVAL_PHRASE = 'CANCEL-UNTICKETED-SABRE-GDS-TEST-PNRS';

    public function __construct(
        protected SabreGdsLiveScenarioOfferCatalog $offerCatalog,
        protected SabreGdsLiveScenarioRunnerBookingFactory $bookingFactory,
        protected SabreGdsLiveScenarioRunnerPassengerLoader $passengerLoader,
        protected SabreGdsLiveScenarioPresetResolver $presetResolver,
        protected SabreGdsLiveScenarioRunnerPnrExecutor $pnrExecutor,
        protected SabreGdsLiveScenarioMulticityInputLoader $multicityInputLoader,
        protected SabreGdsLiveScenarioMulticityShopService $multicityShop,
        protected SabrePnrItinerarySyncService $pnrSync,
        protected SabreGdsCancelService $cancelService,
        protected SabreGdsCancellationReconciliationService $cancellationReconciliation,
        protected SabreGdsLiveScenarioRevalidationGate $revalidationGate,
        protected SabreGdsLiveScenarioRevalidationOutcomeMapper $revalidationOutcomeMapper,
        protected BookingCommunicationService $communicationService,
        protected SabrePnrCertificationSupport $certificationSupport,
        protected SupplierBookingAttemptGuard $attemptGuard,
    ) {}

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function run(array $options): array
    {
        $runId = (string) Str::uuid();
        $mode = strtolower(trim((string) ($options['mode'] ?? 'plan')));
        $maxBookings = max(1, (int) ($options['max_bookings'] ?? 1));
        $connectionId = (int) ($options['connection_id'] ?? 0);
        $farePick = trim((string) ($options['fare_pick'] ?? 'lowest'));
        $cancelApproval = trim((string) ($options['cancel_approval'] ?? ''));
        $operatorApproved = ($options['operator_approved'] ?? false) === true;

        $connection = SupplierConnection::query()
            ->where('id', $connectionId)
            ->where('provider', SupplierProvider::Sabre->value)
            ->first();
        if ($connection === null) {
            return $this->finalizeRun($runId, [
                'run_id' => $runId,
                'mode' => $mode,
                'error' => 'connection_not_found',
            ]);
        }

        $preset = is_string($options['preset'] ?? null) ? strtolower(trim((string) $options['preset'])) : null;
        if ($preset === 'multicity') {
            return $this->runMulticityPreset($runId, $connection, $connectionId, $mode, $options);
        }

        $passengerPath = trim((string) ($options['passenger_json'] ?? ''));
        $passengerBundle = null;
        if ($mode !== 'plan') {
            if ($passengerPath === '') {
                return $this->finalizeRun($runId, [
                    'run_id' => $runId,
                    'mode' => $mode,
                    'error' => 'passenger_json_required',
                ]);
            }
            try {
                $passengerBundle = $this->passengerLoader->loadFromPath($passengerPath);
            } catch (\InvalidArgumentException) {
                return $this->finalizeRun($runId, [
                    'run_id' => $runId,
                    'mode' => $mode,
                    'error' => 'passenger_json_invalid',
                    'reason_code' => SabreGdsLiveScenarioRunnerPassengerLoader::REASON_VALIDATION_FAILED,
                ]);
            }
        }

        if ($mode === 'book-retrieve-and-cancel' && $cancelApproval !== self::CANCEL_APPROVAL_PHRASE) {
            return $this->finalizeRun($runId, [
                'run_id' => $runId,
                'mode' => $mode,
                'error' => 'cancel_approval_required',
                'cancellation_attempted' => false,
            ]);
        }

        $scenarios = $this->presetResolver->resolve(
            $options['preset'] ?? null,
            (string) ($options['origin'] ?? 'LHE'),
            (string) ($options['destination'] ?? 'DXB'),
            (string) ($options['departure_date'] ?? ''),
            $options['return_date'] ?? null,
            (string) ($options['trip_type'] ?? 'one_way'),
            $options['carrier'] ?? null,
            (string) ($options['stops'] ?? 'ANY'),
            $farePick,
        );

        $discoveryFilters = $this->resolveDiscoveryFilters($options);
        $planCandidateLimit = max(1, (int) ($options['plan_only_candidates'] ?? 10));
        $mixedCertApproved = ($options['mixed_carrier_certification_approved'] ?? false) === true;

        $scenarioResults = [];
        $bookingsCreated = 0;

        foreach ($scenarios as $scenario) {
            if ($mode !== 'plan' && $bookingsCreated >= $maxBookings) {
                break;
            }
            if ($mode === 'plan' && ($options['preset'] ?? null) === 'all-basic' && count($scenarioResults) >= $maxBookings) {
                break;
            }

            if ($mode !== 'plan' && ($scenario['plan_only'] ?? false) === true) {
                $scenarioResults[] = [
                    'scenario' => $this->safeScenarioLabel($scenario),
                    'error' => 'advanced_itinerary_plan_only_preset',
                    'booking_created' => false,
                    'pnr_attempted' => false,
                ];

                continue;
            }

            if ($mode !== 'plan'
                && ($scenario['mixed_carrier_preset'] ?? false) === true
                && ! $mixedCertApproved) {
                $scenarioResults[] = [
                    'scenario' => $this->safeScenarioLabel($scenario),
                    'error' => SabreGdsMixedCarrierCertificationGate::REASON_APPROVAL_MISSING,
                    'booking_created' => false,
                    'pnr_attempted' => false,
                    'mixed_carrier_certification_approved' => false,
                ];

                continue;
            }

            if (($scenario['trip_type'] ?? '') === 'return' && empty($scenario['return_date'])) {
                $scenarioResults[] = [
                    'scenario' => $scenario,
                    'error' => 'return_date_required',
                ];

                continue;
            }

            $search = $this->offerCatalog->search($connection, $scenario, $discoveryFilters);
            if (($search['shop_error'] ?? null) !== null) {
                $scenarioResults[] = [
                    'scenario' => $this->safeScenarioLabel($scenario),
                    'shop_http_status' => $search['shop_http_status'] ?? 0,
                    'error' => $search['shop_error'],
                ];

                continue;
            }

            if ($mode === 'plan') {
                $pick = $this->offerCatalog->pickCandidate($search['eligible'], $farePick);
                $candidates = $this->offerCatalog->buildPlanSummaries(
                    $search['eligible'],
                    $scenario,
                    $planCandidateLimit,
                    [
                        'mixed_carrier_certification_approved' => $mixedCertApproved,
                        'connection' => $connection,
                        'shop_captured_at' => $search['shop_captured_at'] ?? null,
                    ],
                );
                /** @var array{row: array<string, mixed>, snap: array<string, mixed>}|null $pickedCandidate */
                $pickedCandidate = is_array($pick['candidate'] ?? null) ? $pick['candidate'] : null;
                $selectedFareFamilyOption = is_array($pick['selected_fare_family_option'] ?? null)
                    ? $pick['selected_fare_family_option']
                    : null;
                $selectedIndex = $pickedCandidate !== null
                    ? $this->offerCatalog->findSelectedCandidateIndex(
                        $search['eligible'],
                        $pickedCandidate,
                        $connection,
                        $selectedFareFamilyOption,
                    )
                    : null;
                $selectedSummary = $selectedIndex !== null ? ($candidates[$selectedIndex] ?? null) : null;
                $selectedEvidence = $pickedCandidate !== null
                    ? app(SabreGdsLiveScenarioExactOfferEvidence::class)->buildPlanCandidateEvidence(
                        $connection,
                        is_array($pickedCandidate['snap'] ?? null) ? $pickedCandidate['snap'] : [],
                        is_array($pickedCandidate['row'] ?? null) ? $pickedCandidate['row'] : [],
                        $selectedFareFamilyOption,
                        is_string($search['shop_captured_at'] ?? null) ? $search['shop_captured_at'] : null,
                    )
                    : [];

                $scenarioResults[] = array_merge([
                    'scenario' => $this->safeScenarioLabel($scenario),
                    'shop_http_status' => $search['shop_http_status'],
                    'shop_captured_at' => $search['shop_captured_at'] ?? null,
                    'normalized_offer_count' => $search['normalized_offer_count'],
                    'eligible_offer_count' => count($search['eligible']),
                    'candidates' => $candidates,
                    'selected_candidate_index' => $selectedIndex,
                    'selected_candidate' => $selectedSummary,
                    'selected_total' => $selectedEvidence['selected_total'] ?? null,
                    'selected_currency' => $selectedEvidence['currency'] ?? null,
                    'selected_offer_fingerprint' => $selectedEvidence['safe_offer_fingerprint'] ?? null,
                    'offer_identifier_present' => ($selectedEvidence['offer_identifier_present'] ?? false) === true,
                    'source_identifier_hash_present' => ($selectedEvidence['source_identifier_hash_present'] ?? false) === true,
                    'source_identifier_hash_length' => $selectedEvidence['source_identifier_hash_length'] ?? 0,
                    'segment_signature_present' => ($selectedEvidence['segment_signature_present'] ?? false) === true,
                    'segment_signature_length' => $selectedEvidence['segment_signature_length'] ?? 0,
                    'revalidation_linkage_ready' => ($selectedEvidence['revalidation_linkage_ready'] ?? false) === true,
                    'revalidation_linkage_missing_components' => $selectedEvidence['revalidation_linkage_missing_components'] ?? [],
                    'booking_created' => false,
                    'pnr_attempted' => false,
                ]);

                continue;
            }

            $candidateIndex = array_key_exists('candidate_index', $options)
                ? (int) $options['candidate_index']
                : null;
            $pick = $candidateIndex !== null
                ? $this->offerCatalog->pickCandidateByIndex($search['eligible'], $candidateIndex)
                : $this->offerCatalog->pickCandidate($search['eligible'], $farePick);
            if (($pick['selection_error'] ?? null) !== null || ($pick['candidate'] ?? null) === null) {
                $scenarioResults[] = [
                    'scenario' => $this->safeScenarioLabel($scenario),
                    'shop_http_status' => $search['shop_http_status'],
                    'error' => $pick['selection_error'] ?? 'selection_failed',
                    'booking_created' => false,
                    'pnr_attempted' => false,
                ];

                continue;
            }

            /** @var array{row: array<string, mixed>, snap: array<string, mixed>} $candidate */
            $candidate = $pick['candidate'];
            $row = is_array($candidate['row'] ?? null) ? $candidate['row'] : [];
            $segmentCount = (int) ($row['segment_count'] ?? 0);
            $stops = max(0, $segmentCount - 1);
            $isMixedCandidate = ($row['mixed_carrier'] ?? false) === true
                || (($scenario['mixed_carrier_preset'] ?? false) === true);
            if ($isMixedCandidate && ($stops > SabreGdsMixedCarrierCertificationGate::MAX_STOPS
                || $segmentCount > SabreGdsMixedCarrierCertificationGate::MAX_SEGMENTS)) {
                $scenarioResults[] = [
                    'scenario' => $this->safeScenarioLabel($scenario),
                    'shop_http_status' => $search['shop_http_status'],
                    'error' => SabreGdsMixedCarrierCertificationGate::REASON_TOO_MANY_STOPS,
                    'booking_created' => false,
                    'pnr_attempted' => false,
                    'segment_count' => $segmentCount,
                    'stops' => $stops,
                ];

                continue;
            }

            $offerSnap = is_array($candidate['snap'] ?? null) ? $candidate['snap'] : [];
            $offerSnap['supplier_provider'] = SupplierProvider::Sabre->value;
            $offerSnap['supplier_connection_id'] = $connection->id;
            $selectedFareFamilyOption = is_array($pick['selected_fare_family_option'] ?? null)
                ? $pick['selected_fare_family_option']
                : null;
            $exactOfferEvidence = app(SabreGdsLiveScenarioExactOfferEvidence::class);
            $continuityEvidence = $exactOfferEvidence->buildLinkageContext(
                $connection,
                $offerSnap,
                $row,
                $selectedFareFamilyOption,
                is_string($search['shop_captured_at'] ?? null) ? $search['shop_captured_at'] : null,
            );
            $expectedFingerprint = (string) ($continuityEvidence['safe_offer_fingerprint'] ?? '');
            if (($continuityEvidence['revalidation_linkage_ready'] ?? false) !== true) {
                $scenarioResults[] = array_merge([
                    'scenario' => $this->safeScenarioLabel($scenario),
                    'shop_http_status' => $search['shop_http_status'],
                    'error' => SabreGdsLiveScenarioExactOfferEvidence::REASON_EXACT_OFFER_LINKAGE_UNAVAILABLE,
                    'safe_reason_code' => SabreGdsLiveScenarioExactOfferEvidence::REASON_EXACT_OFFER_LINKAGE_UNAVAILABLE,
                    'booking_created' => false,
                    'pnr_attempted' => false,
                    'selected_total' => $continuityEvidence['selected_total'] ?? null,
                    'selected_currency' => $continuityEvidence['currency'] ?? null,
                    'selected_offer_fingerprint' => $expectedFingerprint !== '' ? $expectedFingerprint : null,
                    'offer_identifier_present' => ($continuityEvidence['offer_identifier_present'] ?? false) === true,
                    'source_identifier_hash_present' => ($continuityEvidence['source_identifier_hash_present'] ?? false) === true,
                    'segment_signature_present' => ($continuityEvidence['segment_signature_present'] ?? false) === true,
                    'revalidation_linkage_ready' => false,
                    'revalidation_linkage_missing_components' => $continuityEvidence['revalidation_linkage_missing_components'] ?? [],
                ]);

                continue;
            }

            $continuityMismatch = $exactOfferEvidence->assertContinuityMatch(
                $continuityEvidence,
                $connection,
                $offerSnap,
                $row,
                $selectedFareFamilyOption,
                is_string($search['shop_captured_at'] ?? null) ? $search['shop_captured_at'] : null,
            );
            if ($continuityMismatch !== null) {
                $scenarioResults[] = [
                    'scenario' => $this->safeScenarioLabel($scenario),
                    'shop_http_status' => $search['shop_http_status'],
                    'error' => $continuityMismatch,
                    'safe_reason_code' => $continuityMismatch,
                    'booking_created' => false,
                    'pnr_attempted' => false,
                    'selected_total' => $continuityEvidence['selected_total'] ?? null,
                    'selected_currency' => $continuityEvidence['currency'] ?? null,
                    'selected_offer_fingerprint' => $expectedFingerprint,
                    'revalidation_linkage_ready' => true,
                ];

                continue;
            }

            $gate = app(SabreBookingService::class)->validateNormalizedSabreOffer($offerSnap);
            if (! $gate->success) {
                $scenarioResults[] = [
                    'scenario' => $this->safeScenarioLabel($scenario),
                    'error' => 'offer_gate_failed',
                    'booking_created' => false,
                    'pnr_attempted' => false,
                ];

                continue;
            }

            $selectedTotal = (float) ($continuityEvidence['selected_total']
                ?? $pick['selected_fare_family_option']['displayed_price']
                ?? $pick['selected_fare_family_option']['price_total']
                ?? $row['total_fare']
                ?? 0);
            $selectedCurrency = is_string($continuityEvidence['currency'] ?? null)
                ? (string) $continuityEvidence['currency']
                : null;

            $revalidationEvidence = $this->revalidationGate->revalidateSelectedOffer(
                $connection,
                $offerSnap,
                $passengerBundle,
                $selectedTotal,
                null,
                [
                    'expected_fingerprint' => $expectedFingerprint,
                    'expected_source_identifier_hash' => $continuityEvidence['source_identifier_hash'] ?? null,
                    'expected_segment_signature' => $continuityEvidence['segment_signature'] ?? null,
                    'selected_currency' => $selectedCurrency,
                    'shop_captured_at' => $search['shop_captured_at'] ?? null,
                    'offer_source' => $continuityEvidence['offer_source'] ?? null,
                    'revalidation_linkage_ready' => ($continuityEvidence['revalidation_linkage_ready'] ?? false) === true,
                    'continuity_evidence' => $continuityEvidence,
                    'continuity_row' => $row,
                    'selected_fare_family_option' => $selectedFareFamilyOption,
                ],
            );
            if (! $this->revalidationProceeds($revalidationEvidence, $options)) {
                $scenarioResults[] = array_merge([
                    'scenario' => $this->safeScenarioLabel($scenario),
                    'booking_created' => false,
                    'pnr_attempted' => false,
                ], $this->revalidationOutcomeFields($revalidationEvidence, $selectedCurrency, $expectedFingerprint, $continuityEvidence));

                continue;
            }

            $authoritativeContext = null;
            $preCreateDiagnostics = [];
            $lifecycleDedicated = ($options['lifecycle_dedicated'] ?? false) === true;
            if ($lifecycleDedicated && $passengerBundle !== null) {
                $handoff = app(SabreGdsQrUnticketedBookAndRetrieveRevalidationHandoff::class);
                $authoritativeContext = $handoff->buildAuthoritativeContext(
                    $connection,
                    $offerSnap,
                    $revalidationEvidence,
                    $continuityEvidence,
                    $passengerBundle,
                    is_string($options['lifecycle_run_id'] ?? null) ? (string) $options['lifecycle_run_id'] : null,
                );
                $preCreateDiagnostics = $handoff->validateFinalOffer(
                    $authoritativeContext,
                    $revalidationEvidence,
                    $passengerBundle,
                );
                if (($preCreateDiagnostics['final_offer_validation_success'] ?? false) !== true) {
                    $scenarioResults[] = array_merge([
                        'scenario' => $this->safeScenarioLabel($scenario),
                        'booking_created' => false,
                        'pnr_attempted' => false,
                        'live_call_attempted' => false,
                        'safe_reason_code' => (string) ($preCreateDiagnostics['final_offer_validation_reason_code'] ?? 'final_offer_validation_failed'),
                        'error' => (string) ($preCreateDiagnostics['final_offer_validation_reason_code'] ?? 'final_offer_validation_failed'),
                    ], $preCreateDiagnostics, $this->revalidationOutcomeFields($revalidationEvidence, $selectedCurrency, $expectedFingerprint, $continuityEvidence));

                    continue;
                }
            }

            $booking = $this->bookingFactory->create(
                $connection,
                $scenario,
                $passengerBundle,
                $candidate,
                is_array($pick['selected_fare_family_option'] ?? null) ? $pick['selected_fare_family_option'] : null,
                is_string($pick['fare_option_key'] ?? null) ? $pick['fare_option_key'] : null,
                $authoritativeContext,
            );
            $this->revalidationGate->persistOnBooking($booking, $revalidationEvidence);
            if ($preCreateDiagnostics !== []) {
                $meta = is_array($booking->meta) ? $booking->meta : [];
                $meta['qr_unticketed_pre_create_diagnostics'] = $preCreateDiagnostics;
                $meta['pre_create_gate_complete'] = true;
                $meta['booking_row_created_at_stage'] = 'post_pre_create_gate';
                $booking->forceFill(['meta' => $meta])->save();
            }
            $bookingsCreated++;

            $guard = $this->attemptGuard->assertRetryAllowed($booking, SupplierProvider::Sabre->value);
            if (($guard['blocked'] ?? false) === true) {
                $scenarioResults[] = $this->buildBookingResultSlice(
                    $runId,
                    $scenario,
                    $booking,
                    [
                        'success' => false,
                        'reason_code' => SabreGdsLiveScenarioRunnerPnrExecutor::REASON_DUPLICATE_LOCK_FAILED,
                        'error_code' => SabreGdsLiveScenarioRunnerPnrExecutor::REASON_DUPLICATE_LOCK_FAILED,
                        'live_call_attempted' => false,
                        'pnr_attempted' => false,
                    ],
                    $pick,
                    $candidate,
                    [
                        'error' => (string) ($guard['reason_code'] ?? SabreGdsLiveScenarioRunnerPnrExecutor::REASON_DUPLICATE_LOCK_FAILED),
                    ],
                );

                continue;
            }

            $pnrResult = $this->pnrExecutor->execute($booking->fresh([
                'passengers', 'contact', 'fareBreakdown', 'supplierBookings', 'supplierBookingAttempts',
            ]), $operatorApproved, [
                'strategy' => strtolower(trim((string) ($options['strategy'] ?? 'auto'))) ?: 'auto',
                'mixed_carrier_certification_approved' => $mixedCertApproved,
                'lifecycle_dedicated' => $lifecycleDedicated,
                'lifecycle_run_id' => is_string($options['lifecycle_run_id'] ?? null) ? (string) $options['lifecycle_run_id'] : null,
                'authoritative_revalidated_context' => $authoritativeContext?->toArray(),
                'skip_redundant_revalidation' => $authoritativeContext !== null,
            ]);

            $booking->refresh();
            $pnr = trim((string) ($pnrResult['pnr'] ?? $booking->pnr ?? ''));
            if ($pnr !== '' && ($pnrResult['live_call_attempted'] ?? false) === true) {
                $this->communicationService->sendSupplierBookingCreated($booking->fresh());
            }

            $retrieveResult = null;
            $retrieveResult2 = null;
            $cancelResult = null;
            $reconcileResult = null;
            $reconcileResult2 = null;
            $cancellationAttempted = false;
            $segmentCountBeforeCancel = null;
            $supplierBookingCreatedCommsAfterCreate = null;
            $supplierBookingCreatedCommsAfterSecondRetrieve = null;

            if ($pnr !== '' && in_array($mode, ['book-and-retrieve', 'book-retrieve-and-cancel'], true)) {
                $denyLocators = array_map(
                    static fn (string $code): string => strtoupper(trim($code)),
                    is_array($options['deny_locators'] ?? null) ? $options['deny_locators'] : [],
                );
                if ($denyLocators !== [] && in_array(strtoupper($pnr), $denyLocators, true)) {
                    $retrieveResult = [
                        'success' => false,
                        'reason_code' => 'denylisted_locator',
                    ];
                } else {
                    $retrieveResult = $this->pnrSync->sync($booking->fresh(), false);
                }
                $booking->refresh();
                $supplierBookingCreatedCommsAfterCreate = $this->countSupplierBookingCreatedComms($booking);

                if (($options['single_retrieve_only'] ?? false) !== true) {
                    $retrieveResult2 = $this->pnrSync->sync($booking->fresh(), false);
                    $booking->refresh();
                    $supplierBookingCreatedCommsAfterSecondRetrieve = $this->countSupplierBookingCreatedComms($booking);
                }
            }

            if ($mode === 'book-retrieve-and-cancel'
                && ($options['lifecycle_dedicated'] ?? false) !== true
                && $pnr !== ''
                && $cancelApproval === self::CANCEL_APPROVAL_PHRASE) {
                $segmentCountBeforeCancel = $this->resolveActiveSegmentCount($booking);
                $cancellationAttempted = true;
                $cancelResult = $this->cancelService->cancelForBooking($booking->fresh(), true, [
                    'source' => 'sabre_gds_live_scenario_runner',
                    'run_id' => $runId,
                ]);
                $booking->refresh();
                if (($cancelResult['success'] ?? false) === true) {
                    $reconcileResult = $this->cancellationReconciliation->reconcileFromStoredEvidence($booking->fresh(), [
                        'source' => 'sabre_gds_live_scenario_runner',
                        'run_id' => $runId,
                    ]);
                    $booking->refresh();
                    $reconcileResult2 = $this->cancellationReconciliation->reconcileFromStoredEvidence($booking->fresh(), [
                        'source' => 'sabre_gds_live_scenario_runner',
                        'run_id' => $runId,
                        'phase' => 'idempotency_proof',
                    ]);
                    $booking->refresh();
                }
            }

            $scenarioResults[] = $this->buildBookingResultSlice(
                $runId,
                $scenario,
                $booking,
                $pnrResult,
                $pick,
                $candidate,
                [
                    'revalidation_attempted' => ($revalidationEvidence['revalidation_attempted'] ?? false) === true,
                    'revalidation_success' => ($revalidationEvidence['revalidation_success'] ?? false) === true,
                    'revalidated_total' => $revalidationEvidence['revalidated_total'] ?? null,
                    'fare_changed' => ($revalidationEvidence['fare_changed'] ?? false) === true,
                    'revalidation_at' => $revalidationEvidence['revalidation_at'] ?? null,
                    'selected_currency' => $selectedCurrency,
                    'revalidated_currency' => $revalidationEvidence['revalidated_currency'] ?? null,
                    'selected_offer_fingerprint' => $expectedFingerprint,
                    'revalidation_linkage_ready' => ($revalidationEvidence['revalidation_linkage_ready'] ?? $continuityEvidence['revalidation_linkage_ready'] ?? false) === true,
                    'offer_identifiers' => $this->sanitizeOfferIdentifiers($offerSnap),
                    'retrieve_attempted' => $retrieveResult !== null,
                    'retrieve_success' => $this->isRetrieveSuccessful($retrieveResult),
                    'retrieve_attempt_2' => $retrieveResult2 !== null,
                    'retrieve_success_2' => $this->isRetrieveSuccessful($retrieveResult2),
                    'supplier_booking_created_comm_count_after_create' => $supplierBookingCreatedCommsAfterCreate,
                    'supplier_booking_created_comm_count_after_second_retrieve' => $supplierBookingCreatedCommsAfterSecondRetrieve,
                    'cancellation_attempted' => $cancellationAttempted,
                    'cancellation_success' => ($cancelResult['success'] ?? false) === true,
                    'cancellation_reason_code' => $cancelResult['reason_code'] ?? null,
                    'cancellation_classification' => data_get($cancelResult, 'post_cancel_verification.classification')
                        ?? data_get($cancelResult, 'sabre_gds_cancel.classification')
                        ?? ($cancelResult['classification'] ?? null),
                    'segment_count_before_cancel' => $segmentCountBeforeCancel,
                    'segment_count_after_cancel' => data_get($cancelResult, 'sabre_gds_cancel.post_cancel_segment_count')
                        ?? data_get($cancelResult, 'post_cancel_sync.post_cancel_segment_count'),
                    'reconciliation_success' => ($reconcileResult['success'] ?? false) === true,
                    'reconciliation_already_reconciled_on_second_run' => ($reconcileResult2['already_reconciled'] ?? false) === true,
                    'closure_verification' => $this->buildClosureVerification(
                        $booking,
                        $cancelResult,
                        $reconcileResult,
                        $reconcileResult2,
                    ),
                ],
            );
        }

        $summary = [
            'run_id' => $runId,
            'mode' => $mode,
            'connection_id' => $connectionId,
            'max_bookings' => $maxBookings,
            'bookings_created' => $bookingsCreated,
            'ticketing_attempted' => false,
            'airticket_attempted' => false,
            'scenario_results' => $scenarioResults,
        ];

        return $this->finalizeRun($runId, $summary);
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    protected function runMulticityPreset(
        string $runId,
        SupplierConnection $connection,
        int $connectionId,
        string $mode,
        array $options,
    ): array {
        $multicityJson = trim((string) ($options['multicity_json'] ?? ''));
        if ($multicityJson === '') {
            return $this->finalizeRun($runId, [
                'run_id' => $runId,
                'mode' => $mode,
                'preset' => 'multicity',
                'error' => 'multicity_json_required',
            ]);
        }

        try {
            $multicityInput = $this->multicityInputLoader->load($multicityJson);
        } catch (\InvalidArgumentException $e) {
            return $this->finalizeRun($runId, [
                'run_id' => $runId,
                'mode' => $mode,
                'preset' => 'multicity',
                'error' => $e->getMessage(),
                'pnr_attempted' => false,
            ]);
        }

        if ($mode !== 'plan') {
            return $this->finalizeRun($runId, [
                'run_id' => $runId,
                'mode' => $mode,
                'preset' => 'multicity',
                'connection_id' => $connectionId,
                'multicity_slice_count' => count($multicityInput['slices'] ?? []),
                'scenario_results' => [[
                    'scenario' => 'multicity',
                    'error' => 'multicity_plan_only_not_certified',
                    'block_reason' => 'multicity_plan_only_not_certified',
                    'booking_created' => false,
                    'pnr_attempted' => false,
                    'ticketing_attempted' => false,
                    'airticket_attempted' => false,
                    'cancellation_attempted' => false,
                    'automatic_booking_allowed' => false,
                ]],
                'bookings_created' => 0,
                'ticketing_attempted' => false,
                'airticket_attempted' => false,
            ]);
        }

        $planCandidateLimit = max(1, (int) ($options['plan_only_candidates'] ?? 10));
        $search = $this->multicityShop->search($connection, $multicityInput, $planCandidateLimit, [
            'include_mixed_carrier_results' => ($options['include_mixed_carrier_results'] ?? false) === true,
        ]);
        $diagnostics = is_array($search['diagnostics'] ?? null) ? $search['diagnostics'] : [];

        $scenarioResult = array_merge([
            'scenario' => 'multicity',
            'shop_http_status' => $search['shop_http_status'] ?? 0,
            'eligible_offer_count' => (int) ($search['eligible_offer_count'] ?? 0),
            'candidate_count' => (int) ($search['candidate_count'] ?? 0),
            'candidates' => is_array($search['candidates'] ?? null) ? $search['candidates'] : [],
            'booking_created' => false,
            'pnr_attempted' => false,
            'ticketing_attempted' => false,
            'airticket_attempted' => false,
            'cancellation_attempted' => false,
            'automatic_booking_allowed' => false,
        ], $diagnostics);

        if (($search['shop_error'] ?? null) !== null) {
            $scenarioResult['error'] = $search['shop_error'];
        }

        return $this->finalizeRun($runId, [
            'run_id' => $runId,
            'mode' => $mode,
            'preset' => 'multicity',
            'connection_id' => $connectionId,
            'multicity_slice_count' => count($multicityInput['slices'] ?? []),
            'bookings_created' => 0,
            'ticketing_attempted' => false,
            'airticket_attempted' => false,
            'scenario_results' => [$scenarioResult],
        ]);
    }

    /**
     * @param  array<string, mixed>  $scenario
     * @param  array<string, mixed>|null  $pnrResult
     * @param  array<string, mixed>  $pick
     * @param  array{row: array<string, mixed>, snap: array<string, mixed>}  $candidate
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    protected function buildBookingResultSlice(
        string $runId,
        array $scenario,
        Booking $booking,
        ?array $pnrResult,
        array $pick,
        array $candidate,
        array $extra,
    ): array {
        $row = is_array($candidate['row'] ?? null) ? $candidate['row'] : [];
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $completion = is_array($meta[SabreGdsAutoPnrContextCompletionService::META_KEY] ?? null)
            ? $meta[SabreGdsAutoPnrContextCompletionService::META_KEY]
            : (is_array($pnrResult['auto_pnr_context_completion'] ?? null) ? $pnrResult['auto_pnr_context_completion'] : []);
        $checkout = is_array($meta['sabre_checkout_outcome'] ?? null) ? $meta['sabre_checkout_outcome'] : [];
        $attempt = SupplierBookingAttempt::query()
            ->where('booking_id', $booking->id)
            ->orderByDesc('id')
            ->first();
        $attemptSafe = is_array($attempt?->safe_summary) ? $attempt->safe_summary : [];

        $hostFamily = data_get($attemptSafe, 'host_error_family')
            ?? data_get($attemptSafe, 'safe_host_error_family')
            ?? data_get($meta, 'sabre_checkout_outcome.sabre_host_classification.host_error_family');

        $liveCallAttempted = ($pnrResult['live_call_attempted'] ?? false) === true
            || ($attemptSafe['live_call_attempted'] ?? false) === true
            || ($checkout['live_call_attempted'] ?? false) === true;
        $pnrAttempted = ($pnrResult['pnr_attempted'] ?? $pnrResult['public_auto_pnr_attempted'] ?? false) === true
            || ($attemptSafe['pnr_attempted'] ?? false) === true
            || ($checkout['pnr_attempted'] ?? false) === true
            || $liveCallAttempted;

        $safeReasonCode = is_scalar($attemptSafe['safe_reason_code'] ?? null)
            ? (string) $attemptSafe['safe_reason_code']
            : (is_scalar($pnrResult['reason_code'] ?? null)
                ? (string) $pnrResult['reason_code']
                : (is_scalar($pnrResult['error_code'] ?? null) ? (string) $pnrResult['error_code'] : ''));

        $mixedPreflightKeys = [
            'mixed_mapping_comparison_result',
            'command_pricing_schema_valid',
            'command_pricing_allowed_shape',
            'command_pricing_rejected_keys',
            'payload_preflight_status',
            'mixed_fare_carrier_mapping_complete',
            'no_fares_rbd_carrier_preflight_risk',
            'segment_marketing_carriers',
            'command_pricing_carriers',
            'command_pricing_segmentselect_pairing_complete',
            'segment_select_rph_values',
            'command_pricing_rph_values',
            'selected_payload_style',
        ];
        $mixedPreflightSlice = array_intersect_key(
            array_merge(
                is_array($meta['mixed_carrier_preflight_proof'] ?? null) ? $meta['mixed_carrier_preflight_proof'] : [],
                $attemptSafe,
                is_array($pnrResult) ? $pnrResult : [],
            ),
            array_flip($mixedPreflightKeys),
        );

        $fareBasis = $row['fare_basis_codes_by_segment'] ?? [];
        $fareBasisDisplay = is_array($fareBasis) && $fareBasis !== []
            ? implode('/', array_map(static fn ($v): string => (string) $v, $fareBasis))
            : null;

        $segmentCount = (int) ($row['segment_count'] ?? 0);

        return [
            'run_id' => $runId,
            'scenario' => $this->safeScenarioLabel($scenario),
            'booking_id' => $booking->id,
            'booking_reference' => (string) ($booking->booking_reference ?? ''),
            'pnr' => trim((string) ($booking->pnr ?? $pnrResult['pnr'] ?? '')) ?: null,
            'supplier_reference' => trim((string) ($booking->supplier_reference ?? '')) ?: null,
            'selected_carrier' => $row['validating_carrier'] ?? null,
            'route' => $row['route'] ?? null,
            'trip_type' => $scenario['trip_type'] ?? null,
            'stops' => max(0, $segmentCount - 1),
            'segment_count' => $segmentCount,
            'brand_code' => $pick['brand_code'] ?? null,
            'fare_basis_display' => $fareBasisDisplay,
            'selected_total' => $booking->selected_fare_total,
            'freshness_satisfied' => data_get($meta, 'sabre_offer_freshness.satisfied'),
            'freshness_source' => data_get($meta, 'sabre_offer_freshness.source'),
            'auto_pnr_context_completion_status' => $completion['auto_pnr_context_completion_status']
                ?? $checkout['auto_pnr_context_completion_status']
                ?? $attemptSafe['auto_pnr_context_completion_status']
                ?? null,
            'completion_sources_used' => $completion['completion_sources_used'] ?? [],
            'public_auto_pnr_attempt_ready' => ($completion['public_auto_pnr_attempt_ready'] ?? $checkout['public_auto_pnr_attempt_ready'] ?? null),
            'scenario_live_pnr_create_approved' => ($pnrResult['scenario_live_pnr_create_approved'] ?? null),
            'scenario_runner_override_applied' => ($pnrResult['scenario_runner_override_applied']
                ?? data_get($pnrResult, 'gds_strategy_selection.scenario_runner_override_applied')),
            'selected_strategy' => $pnrResult['selected_strategy'] ?? data_get($meta, 'sabre_checkout_outcome.pnr_strategy_selected'),
            'pnr_strategy_used' => $pnrResult['pnr_strategy_used'] ?? $pnrResult['payload_schema'] ?? data_get($meta, 'sabre_checkout_outcome.pnr_strategy_used'),
            'payload_schema' => $pnrResult['payload_schema'] ?? null,
            'live_call_attempted' => $liveCallAttempted,
            'pnr_attempted' => $pnrAttempted,
            'attempt_id' => $attempt?->id,
            'attempt_status' => $attempt?->status ?? data_get($checkout, 'status'),
            'http_status' => $attempt?->http_status ?? data_get($checkout, 'http_status'),
            'sabre_application_status' => data_get($attemptSafe, 'safe_application_status')
                ?? data_get($attemptSafe, 'sabre_application_status')
                ?? data_get($pnrResult, 'sabre_application_status'),
            'safe_host_error_family' => $hostFamily,
            'safe_reason_code' => $safeReasonCode,
            'retry_policy' => $attemptSafe['retry_policy'] ?? data_get($meta, 'sabre_checkout_outcome.sabre_host_classification.retry_policy'),
            'recommended_admin_action' => $attemptSafe['recommended_admin_action'] ?? $attemptSafe['admin_summary'] ?? null,
            ...$mixedPreflightSlice,
            'ticketing_attempted' => false,
            'airticket_attempted' => false,
            'booking_created' => true,
            ...$extra,
        ];
    }

    /**
     * @param  array<string, mixed>  $revalidationEvidence
     * @param  array<string, mixed>  $continuityEvidence
     * @return array<string, mixed>
     */
    protected function revalidationOutcomeFields(
        array $revalidationEvidence,
        ?string $selectedCurrency,
        string $expectedFingerprint,
        array $continuityEvidence,
    ): array {
        return array_merge(
            $this->revalidationOutcomeMapper->extractScenarioResultFields($revalidationEvidence),
            array_filter([
                'selected_currency' => $selectedCurrency,
                'selected_offer_fingerprint' => $expectedFingerprint !== '' ? $expectedFingerprint : null,
                'revalidation_linkage_ready' => ($continuityEvidence['revalidation_linkage_ready'] ?? $revalidationEvidence['revalidation_linkage_ready'] ?? false) === true,
            ], static fn ($value) => $value !== null),
        );
    }

    /**
     * @param  array<string, mixed>  $scenario
     */
    protected function safeScenarioLabel(array $scenario): string
    {
        $preset = $scenario['preset'] ?? null;
        if (is_string($preset) && $preset !== '') {
            return $preset;
        }

        $parts = [
            $scenario['origin'] ?? '',
            $scenario['destination'] ?? '',
            $scenario['scenario_key'] ?? '',
            $scenario['carrier'] ?? 'ANY',
            'stops='.($scenario['stops'] ?? 'ANY'),
        ];

        return implode('/', array_filter($parts, static fn ($p): bool => trim((string) $p) !== ''));
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    protected function resolveDiscoveryFilters(array $options): array
    {
        $filters = [];

        foreach (['min_stops', 'max_stops', 'min_segments', 'max_segments'] as $key) {
            if (isset($options[$key]) && is_numeric($options[$key])) {
                $filters[$key] = (int) $options[$key];
            }
        }

        foreach (['same_carrier', 'mixed_carrier'] as $key) {
            if (! array_key_exists($key, $options)) {
                continue;
            }
            $parsed = $this->parseNullableBoolean($options[$key]);
            if ($parsed !== null) {
                $filters[$key] = $parsed;
            }
        }

        $carrierChain = trim((string) ($options['carrier_chain'] ?? ''));
        if ($carrierChain !== '') {
            $filters['carrier_chain'] = strtoupper($carrierChain);
        }

        $validatingCarrier = trim((string) ($options['validating_carrier'] ?? ''));
        if ($validatingCarrier !== '') {
            $filters['validating_carrier'] = strtoupper($validatingCarrier);
        }

        return $filters;
    }

    protected function parseNullableBoolean(mixed $value): ?bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (! is_string($value) && ! is_int($value)) {
            return null;
        }
        $normalized = strtolower(trim((string) $value));
        if (in_array($normalized, ['1', 'true', 'yes', 'on'], true)) {
            return true;
        }
        if (in_array($normalized, ['0', 'false', 'no', 'off'], true)) {
            return false;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>|null  $retrieveResult
     */
    protected function isRetrieveSuccessful(?array $retrieveResult): bool
    {
        if ($retrieveResult === null) {
            return false;
        }

        return ($retrieveResult['synced'] ?? false) === true
            || ($retrieveResult['success'] ?? false) === true;
    }

    /**
     * @param  array<string, mixed>  $offerSnap
     * @return array<string, mixed>
     */
    protected function sanitizeOfferIdentifiers(array $offerSnap): array
    {
        return array_filter([
            'supplier_offer_id' => is_scalar($offerSnap['supplier_offer_id'] ?? null)
                ? (string) $offerSnap['supplier_offer_id']
                : null,
            'offer_id' => is_scalar($offerSnap['offer_id'] ?? null)
                ? (string) $offerSnap['offer_id']
                : null,
            'validating_carrier' => is_scalar($offerSnap['validating_carrier'] ?? null)
                ? (string) $offerSnap['validating_carrier']
                : null,
            'distribution_channel' => is_scalar($offerSnap['distribution_channel'] ?? null)
                ? (string) $offerSnap['distribution_channel']
                : null,
            'supplier_connection_id' => isset($offerSnap['supplier_connection_id']) && is_numeric($offerSnap['supplier_connection_id'])
                ? (int) $offerSnap['supplier_connection_id']
                : null,
        ], static fn ($value) => $value !== null && $value !== '');
    }

    protected function countSupplierBookingCreatedComms(Booking $booking): int
    {
        return CommunicationLog::query()
            ->where('booking_id', $booking->id)
            ->where('event', BookingCommunicationEvent::SupplierBookingCreated->value)
            ->count();
    }

    protected function resolveActiveSegmentCount(Booking $booking): ?int
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $snapshot = is_array($meta['pnr_itinerary_snapshot'] ?? null) ? $meta['pnr_itinerary_snapshot'] : [];
        $segments = is_array($snapshot['segments'] ?? null) ? $snapshot['segments'] : [];
        if ($segments !== []) {
            return count($segments);
        }

        return isset($snapshot['segment_count']) && is_numeric($snapshot['segment_count'])
            ? (int) $snapshot['segment_count']
            : null;
    }

    protected function resolveIsTicketed(Booking $booking): bool
    {
        if ($booking->tickets()->exists()) {
            return true;
        }

        $meta = is_array($booking->meta) ? $booking->meta : [];
        $syncSidecar = is_array($meta['pnr_itinerary_sync'] ?? null) ? $meta['pnr_itinerary_sync'] : [];

        return ($syncSidecar['is_ticketed'] ?? false) === true;
    }

    /**
     * @param  array<string, mixed>|null  $cancelResult
     * @param  array<string, mixed>|null  $reconcileResult
     * @param  array<string, mixed>|null  $reconcileResult2
     * @return array<string, mixed>
     */
    protected function buildClosureVerification(
        Booking $booking,
        ?array $cancelResult,
        ?array $reconcileResult,
        ?array $reconcileResult2,
    ): array {
        $booking->refresh();
        $booking->loadMissing(['tickets']);

        $supplierBooking = SupplierBooking::query()
            ->where('booking_id', $booking->id)
            ->where('provider', SupplierProvider::Sabre->value)
            ->first();

        $createAttempts = SupplierBookingAttempt::query()
            ->where('booking_id', $booking->id)
            ->where('provider', SupplierProvider::Sabre->value)
            ->where('action', 'create_pnr')
            ->count();
        $cancelAttempts = SupplierBookingAttempt::query()
            ->where('booking_id', $booking->id)
            ->where('provider', SupplierProvider::Sabre->value)
            ->where('action', 'cancel_booking')
            ->whereIn('status', ['success', 'attempted', 'failed', 'in_progress'])
            ->count();
        $ticketAttempts = SupplierBookingAttempt::query()
            ->where('booking_id', $booking->id)
            ->where('provider', SupplierProvider::Sabre->value)
            ->where('action', 'ticket')
            ->count();

        $supplierCreatedComms = CommunicationLog::query()
            ->where('booking_id', $booking->id)
            ->where('event', BookingCommunicationEvent::SupplierBookingCreated->value)
            ->get(['status']);
        $cancellationComms = CommunicationLog::query()
            ->where('booking_id', $booking->id)
            ->whereIn('event', [
                BookingCommunicationEvent::BookingCancelled->value,
                'booking_cancelled',
            ])
            ->get(['status']);

        $reconciliationAuditCount = AuditLog::query()
            ->where('auditable_type', Booking::class)
            ->where('auditable_id', $booking->id)
            ->where('action', SabreGdsCancellationReconciliationService::AUDIT_ACTION)
            ->count();
        $statusLogCount = $booking->statusLogs()
            ->where('to_status', BookingStatus::Cancelled->value)
            ->where('note', 'Sabre GDS cancellation reconciled from stored supplier evidence')
            ->count();

        return [
            'booking_status' => (string) ($booking->status->value ?? $booking->status),
            'supplier_booking_status' => (string) ($booking->supplier_booking_status ?? ''),
            'cancelled_at_populated' => $booking->cancelled_at !== null,
            'supplier_booking_row_status' => (string) ($supplierBooking?->status ?? ''),
            'pnr_preserved' => trim((string) ($booking->pnr ?? '')) !== '',
            'supplier_reference_preserved' => trim((string) ($booking->supplier_reference ?? '')) !== '',
            'is_ticketed' => $this->resolveIsTicketed($booking),
            'ticketing_attempt_count' => $ticketAttempts,
            'create_pnr_attempt_count' => $createAttempts,
            'cancel_booking_attempt_count' => $cancelAttempts,
            'supplier_booking_created_comm_count' => $supplierCreatedComms->count(),
            'supplier_booking_created_comm_statuses' => $supplierCreatedComms->pluck('status')->values()->all(),
            'cancellation_comm_count' => $cancellationComms->count(),
            'cancellation_comm_statuses' => $cancellationComms->pluck('status')->values()->all(),
            'communication_logs_queued_count' => CommunicationLog::query()
                ->where('booking_id', $booking->id)
                ->where('status', 'queued')
                ->count(),
            'reconciliation_audit_count' => $reconciliationAuditCount,
            'cancellation_status_log_count' => $statusLogCount,
            'reconciliation_success' => ($reconcileResult['success'] ?? false) === true,
            'reconciliation_already_reconciled_on_second_run' => ($reconcileResult2['already_reconciled'] ?? false) === true,
            'cancellation_classification' => data_get($cancelResult, 'post_cancel_verification.classification')
                ?? data_get($cancelResult, 'sabre_gds_cancel.classification')
                ?? ($cancelResult['classification'] ?? null),
        ];
    }

    /**
     * @param  array<string, mixed>  $revalidationEvidence
     * @param  array<string, mixed>  $options
     */
    protected function revalidationProceeds(array $revalidationEvidence, array $options): bool
    {
        if (($options['lifecycle_dedicated'] ?? false) === true) {
            return app(SabreGdsQrUnticketedBookAndRetrieveRevalidationHandoff::class)
                ->allowsPnrCreate($revalidationEvidence);
        }

        return $this->revalidationGate->shouldProceed($revalidationEvidence);
    }

    /**
     * @param  array<string, mixed>  $summary
     * @return array<string, mixed>
     */
    protected function finalizeRun(string $runId, array $summary): array
    {
        $summary['run_id'] = $runId;
        $summary['ticketing_attempted'] = false;
        $summary['airticket_attempted'] = false;

        try {
            $this->certificationSupport->assertOutputSafe($summary);
        } catch (\Throwable) {
            $summary = [
                'run_id' => $runId,
                'error' => 'output_safety_check_failed',
            ];
        }

        $relativePath = 'sabre-gds-scenario-runs/'.$runId.'.json';
        Storage::disk('local')->put($relativePath, json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $absolutePath = Storage::disk('local')->path($relativePath);
        @chmod($absolutePath, 0600);
        $summary['output_json_path'] = $absolutePath;

        return $summary;
    }
}
