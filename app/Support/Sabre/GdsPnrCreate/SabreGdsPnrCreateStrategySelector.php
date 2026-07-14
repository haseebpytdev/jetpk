<?php

namespace App\Support\Sabre\GdsPnrCreate;

use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Models\SupplierBookingAttempt;
use App\Support\Bookings\SabreCertifiedRouteSelector;
use App\Support\Bookings\SabrePnrCertificationSupport;
use App\Support\Sabre\GdsPnrCreate\SabreGdsOneWayTripShapeClassifier;
use App\Support\Sabre\GdsPnrCreate\SabreGdsReturnTripClassifier;
use App\Support\Sabre\GdsPnrCreate\SabreGdsMixedCarrierCertificationGate;
use App\Support\Sabre\GdsPnrCreate\SabreGdsMixedCarrierFareBasisPayloadPreflight;
use App\Support\Sabre\Scenario\SabreGdsLiveScenarioPlanCandidateDiagnostics;

/**
 * Selects exactly one Sabre GDS PNR create strategy for automatic live booking (no multi-strategy live testing).
 * Scenario runner uses {@see selectForScenarioRunner()} with repaired completion context and operator approval.
 */
final class SabreGdsPnrCreateStrategySelector
{
    public const REASON_KNOWN_GOOD = 'known_good_strategy_evidence';

    public const REASON_CERTIFIED_ROUTE_MATRIX = 'certified_connection_route_matrix';

    public const V25_NOT_SELECTED_AUTOMATIC_DISABLED = 'automatic_public_pnr_disabled_admin_fallback_only';

    public const V25_NOT_SELECTED_LOWER_PRIORITY = 'lower_priority_than_selected_strategy';

    public const V25_NOT_SELECTED_PREVIOUS_FORMAT_FAILURE = 'previous_enhanced_airbook_format_failure';

    public const TRADITIONAL_NOT_SELECTED_MIXED_SUCCESS = 'mixed_success_enhanced_airbook_format_failure';

    public const TRADITIONAL_NOT_SELECTED_LOWER_PRIORITY = 'lower_priority_than_selected_strategy';

    public const REASON_HIGHEST_CONFIDENCE = 'highest_confidence_eligible';

    public const REASON_NO_ELIGIBLE = 'supplier_no_eligible_create_strategy';

    public const REASON_PREVIOUS_FORMAT_FAILURE_BLOCKS_AUTO = 'previous_enhanced_airbook_format_blocks_auto_retry';

    public const REASON_SCENARIO_RUNNER_OPERATOR_APPROVED = 'scenario_runner_operator_approved';

    public const REASON_SCENARIO_RUNNER_EXPLICIT_STRATEGY = 'scenario_runner_explicit_strategy';

    public const REASON_SCENARIO_RUNNER_PK_DIRECT_IATI = 'scenario_runner_pk_direct_iati_matrix';

    public const REASON_SCENARIO_RUNNER_RETURN_SAME_CARRIER_IATI = 'scenario_runner_return_same_carrier_iati_matrix';

    public const REASON_SCENARIO_RUNNER_ONE_WAY_MULTISTOP_SAME_CARRIER_IATI = 'scenario_runner_one_way_multistop_same_carrier_iati_matrix';

    public const REASON_SCENARIO_RUNNER_MIXED_CARRIER_IATI = 'scenario_runner_mixed_carrier_iati_matrix';

    public const SAFE_REASON_UNEXPECTED_STRATEGY_PRIORITY = 'unexpected_strategy_priority';

    public const EXCLUSION_AUTOMATIC_NOT_ALLOWED = 'automatic_not_allowed';

    public const EXCLUSION_PREVIOUS_FORMAT_FAILURE = 'previous_enhanced_airbook_format_failure';

    public const EXCLUSION_MIXED_SUCCESS_FORMAT_FAILURE = 'mixed_success_enhanced_airbook_format_failure';

    public const EXCLUSION_CONTEXT_NOT_READY = 'context_not_ready';

    public const EXCLUSION_REQUIRED_FIELDS_MISSING = 'required_fields_missing';

    public const EXCLUSION_PUBLIC_AUTO_NOT_READY = 'public_auto_pnr_attempt_not_ready';

    public function __construct(
        protected SabreGdsPnrCreateStrategyRegistry $registry,
        protected SabreGdsPnrCreateStrategyDigest $digestBuilder,
        protected SabreGdsPnrCreateStrategyEvidenceRecorder $evidenceRecorder,
        protected SabreGdsPnrCreateStrategyResultClassifier $resultClassifier,
        protected SabrePnrCertificationSupport $certificationSupport,
        protected SabreCertifiedRouteSelector $routeSelector,
        protected SabreConnectingBrandedFarePublicAutoCertification $publicAutoCertification,
        protected SabreGdsAutoPnrContextCompletionService $contextCompletion,
    ) {}

    /**
     * @return array{
     *     selected_strategy: string|null,
     *     selection_reason: string,
     *     eligible_strategies: list<string>,
     *     blocked_strategies: list<string>,
     *     fallback_available: bool,
     *     manual_review: bool,
     *     reason_code: string
     * }
     */
    public function selectForBooking(Booking $booking): array
    {
        $booking->loadMissing(['passengers', 'contact']);
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $readiness = $this->certificationSupport->buildReadiness($booking);
        $tripType = $this->certificationSupport->detectTripType($booking);
        $routeSelection = $this->routeSelector->selectForBooking($booking);
        $category = (string) ($routeSelection['category'] ?? SabreCertifiedRouteSelector::CATEGORY_UNKNOWN);
        $segmentCount = (int) ($readiness['segment_count'] ?? 0);
        $validatingCarrier = strtoupper(trim((string) ($readiness['validating_carrier'] ?? '')));
        $connId = (int) ($meta['supplier_connection_id'] ?? 0);

        $previousFailedStrategy = $this->resolvePreviousFailedStrategy($booking);
        $previousFormatFailure = $this->previousEnhancedAirBookFormatFailure($booking, $previousFailedStrategy);

        $completion = $this->resolveContextCompletion($booking);
        $publicAutoCert = $this->publicAutoCertification->assess($booking);
        if (($completion['public_auto_pnr_attempt_ready'] ?? false) !== true) {
            $candidateDigests = $this->digestBuilder->buildCandidateDigests($booking);
            $blockReason = trim((string) ($completion['public_auto_pnr_block_reason'] ?? ''));
            if ($blockReason === '') {
                $blockReason = SabreGdsAutoPnrContextCompletionService::REASON_CONTEXT_COMPLETION_FAILED;
            }

            return array_merge($this->blockedPublicAutoSelection($candidateDigests, $publicAutoCert), [
                'public_auto_certified' => false,
                'public_auto_pnr_certified' => false,
                'public_auto_block_reason' => $blockReason,
                'connecting_brand_context' => $publicAutoCert,
                'auto_pnr_context_completion' => $completion,
                'public_auto_pnr_attempt_ready' => false,
            ]);
        }

        $publicAutoCert['public_auto_certified'] = true;
        $publicAutoCert['public_auto_pnr_certified'] = true;
        $publicAutoCert['public_auto_block_reason'] = null;
        $publicAutoCert['connecting_brand_context_complete'] = true;

        $candidateDigests = $this->digestBuilder->buildCandidateDigests($booking);
        $eligible = [];
        $blocked = [];

        foreach ($candidateDigests as $candidate) {
            $code = (string) ($candidate['strategy_code'] ?? '');
            if ($code === '') {
                continue;
            }
            $automaticAllowed = ($candidate['automatic_allowed'] ?? false) === true;
            $contextReady = ($candidate['context_ready'] ?? false) === true;
            if (! $contextReady || ! $automaticAllowed) {
                $blocked[] = $code;

                continue;
            }
            if ($previousFormatFailure && $code === $previousFailedStrategy) {
                $blocked[] = $code;

                continue;
            }
            if ($connId > 0 && $validatingCarrier !== ''
                && $this->evidenceRecorder->hasMixedSuccessFormatFailureEvidence(
                    $connId,
                    $validatingCarrier,
                    $category,
                    $tripType,
                    $segmentCount,
                    $code,
                )) {
                $blocked[] = $code;

                continue;
            }
            $eligible[] = $code;
        }

        if ($eligible === []) {
            $fallbackAvailable = $this->hasAdminFallbackCandidate($candidateDigests, $previousFailedStrategy);

            return array_merge([
                'selected_strategy' => null,
                'selection_reason' => self::REASON_NO_ELIGIBLE,
                'eligible_strategies' => [],
                'blocked_strategies' => $blocked,
                'fallback_available' => $fallbackAvailable,
                'manual_review' => true,
                'reason_code' => self::REASON_NO_ELIGIBLE,
                'public_auto_certified' => true,
                'public_auto_pnr_certified' => true,
                'public_auto_block_reason' => null,
                'public_auto_pnr_attempt_ready' => true,
            ], [
                'connecting_brand_context' => $publicAutoCert,
                'auto_pnr_context_completion' => $completion,
            ]);
        }

        $knownGood = $connId > 0 && $validatingCarrier !== ''
            ? $this->evidenceRecorder->findBestKnownGood(
                $connId,
                $validatingCarrier,
                $category,
                $tripType,
                $segmentCount,
                $eligible,
            )
            : null;
        if ($knownGood !== null && in_array($knownGood->strategy_code, $eligible, true)) {
            return $this->selectionResult(
                $knownGood->strategy_code,
                self::REASON_KNOWN_GOOD,
                $eligible,
                $blocked,
                $previousFormatFailure,
                $knownGood,
                $previousFailedStrategy,
                $connId,
                $validatingCarrier,
                $category,
                $tripType,
                $segmentCount,
                $publicAutoCert,
                $completion,
            );
        }

        $matrixDefault = $this->certifiedRouteMatrixDefault($category, $eligible);
        if ($matrixDefault !== null) {
            return $this->selectionResult(
                $matrixDefault,
                self::REASON_CERTIFIED_ROUTE_MATRIX,
                $eligible,
                $blocked,
                $previousFormatFailure,
                null,
                $previousFailedStrategy,
                $connId,
                $validatingCarrier,
                $category,
                $tripType,
                $segmentCount,
                $publicAutoCert,
                $completion,
            );
        }

        $selected = $this->highestConfidenceEligible($eligible);

        return $this->selectionResult(
            $selected,
            self::REASON_HIGHEST_CONFIDENCE,
            $eligible,
            $blocked,
            $previousFormatFailure,
            null,
            $previousFailedStrategy,
            $connId,
            $validatingCarrier,
            $category,
            $tripType,
            $segmentCount,
            $publicAutoCert,
            $completion,
        );
    }

    /**
     * Operator-approved scenario runner: prefer repaired completion context over stale display-option readiness.
     *
     * @param  array<string, mixed>  $options  strategy: auto|iati_like_cpnr_v2_4_gds|...
     * @return array<string, mixed>
     */
    public function selectForScenarioRunner(Booking $booking, array $options = []): array
    {
        $booking->loadMissing(['passengers', 'contact']);
        $completion = $this->resolveContextCompletion($booking);
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $handoff = is_array($meta['sabre_booking_context'] ?? null) ? $meta['sabre_booking_context'] : [];
        $selectedFare = is_array($meta['selected_fare_family_option'] ?? null) ? $meta['selected_fare_family_option'] : [];
        $sabreBookingContextReady = ($handoff['ready_for_booking_payload'] ?? false) === true;
        $selectedFareOptionReady = ($selectedFare['ready_for_booking_payload'] ?? false) === true;
        $scenarioOverrideApplied = $this->resolveScenarioRunnerOverrideApplied($completion, $sabreBookingContextReady);

        $strategyOption = strtolower(trim((string) ($options['strategy'] ?? 'auto')));
        if ($strategyOption === '') {
            $strategyOption = 'auto';
        }
        $mixedCertApproved = ($options['mixed_carrier_certification_approved'] ?? false) === true;

        if (($completion['public_auto_pnr_attempt_ready'] ?? false) !== true) {
            $candidateDigests = $this->digestBuilder->buildCandidateDigests($booking, null, [
                'scenario_runner' => true,
                'context_completion' => $completion,
                'mixed_carrier_certification_approved' => $mixedCertApproved,
            ]);

            return array_merge($this->blockedPublicAutoSelection($candidateDigests, []), [
                'public_auto_certified' => false,
                'public_auto_pnr_certified' => false,
                'public_auto_block_reason' => $completion['public_auto_pnr_block_reason']
                    ?? SabreGdsAutoPnrContextCompletionService::REASON_CONTEXT_COMPLETION_FAILED,
                'connecting_brand_context' => [],
                'auto_pnr_context_completion' => $completion,
                'public_auto_pnr_attempt_ready' => false,
                'scenario_runner_override_applied' => false,
                'strategy_option' => $strategyOption,
                'candidate_exclusion_diagnostics' => $this->buildScenarioRunnerExclusionDiagnostics(
                    $candidateDigests,
                    $completion,
                    $selectedFareOptionReady,
                    $sabreBookingContextReady,
                    false,
                ),
            ]);
        }

        $readiness = $this->certificationSupport->buildReadiness($booking);
        $tripType = $this->certificationSupport->detectTripType($booking);
        $routeSelection = $this->routeSelector->selectForBooking($booking);
        $category = (string) ($routeSelection['category'] ?? SabreCertifiedRouteSelector::CATEGORY_UNKNOWN);
        $segmentCount = (int) ($readiness['segment_count'] ?? 0);
        $validatingCarrier = strtoupper(trim((string) ($readiness['validating_carrier'] ?? '')));
        $connId = (int) ($meta['supplier_connection_id'] ?? 0);
        $previousFailedStrategy = $this->resolvePreviousFailedStrategy($booking);
        $previousFormatFailure = $this->previousEnhancedAirBookFormatFailure($booking, $previousFailedStrategy);
        $publicAutoCert = $this->publicAutoCertification->assess($booking);
        $publicAutoCert['connecting_brand_context_complete'] = true;
        $publicAutoCert['public_auto_certified'] = true;
        $publicAutoCert['public_auto_pnr_certified'] = true;
        $publicAutoCert['public_auto_block_reason'] = null;

        $candidateDigests = $this->digestBuilder->buildCandidateDigests($booking, null, [
            'scenario_runner' => true,
            'context_completion' => $completion,
            'mixed_carrier_certification_approved' => $mixedCertApproved,
        ]);

        if ($this->mixedCarrierGate()->isMixedCarrierTripType($tripType) && ! $mixedCertApproved) {
            return array_merge([
                'selected_strategy' => null,
                'selection_reason' => self::REASON_NO_ELIGIBLE,
                'eligible_strategies' => [],
                'blocked_strategies' => array_map(
                    static fn (array $row): string => (string) ($row['strategy_code'] ?? ''),
                    $candidateDigests,
                ),
                'fallback_available' => false,
                'manual_review' => true,
                'reason_code' => self::REASON_NO_ELIGIBLE,
                'public_auto_certified' => true,
                'public_auto_pnr_certified' => true,
                'public_auto_block_reason' => null,
                'public_auto_pnr_attempt_ready' => true,
                'scenario_runner_override_applied' => $scenarioOverrideApplied,
                'strategy_option' => $strategyOption,
                'mixed_carrier_certification_approved' => false,
            ], [
                'connecting_brand_context' => $publicAutoCert,
                'auto_pnr_context_completion' => $completion,
            ], $this->tripShapeSelectionDiagnostics($booking, $tripType, $candidateDigests, $mixedCertApproved));
        }

        if ($strategyOption !== 'auto') {
            return $this->selectExplicitScenarioRunnerStrategy(
                $booking,
                $strategyOption,
                $candidateDigests,
                $completion,
                $publicAutoCert,
                $scenarioOverrideApplied,
                $selectedFareOptionReady,
                $sabreBookingContextReady,
            );
        }

        $eligible = [];
        $blocked = [];

        foreach ($candidateDigests as $candidate) {
            $code = (string) ($candidate['strategy_code'] ?? '');
            if ($code === '') {
                continue;
            }
            if (! $this->scenarioRunnerAutoCandidateEligible($candidate, $completion)) {
                $blocked[] = $code;

                continue;
            }
            if ($previousFormatFailure && $code === $previousFailedStrategy) {
                $blocked[] = $code;

                continue;
            }
            $eligible[] = $code;
        }

        if ($mixedCertApproved && $this->mixedCarrierGate()->isMixedCarrierTripType($tripType)) {
            $eligible = array_values(array_filter(
                $eligible,
                static fn (string $code): bool => $code === SabreGdsPnrCreateStrategyRegistry::STRATEGY_IATI_LIKE_CPNR_V2_4_GDS,
            ));
            if ($eligible !== [] && ! $this->mixedCarrierPayloadPreflightAllowsLive($booking, $completion, $tripType, [
                'scenario_runner_override_applied' => $scenarioOverrideApplied,
            ])) {
                $eligible = [];
                if (! in_array(SabreGdsPnrCreateStrategyRegistry::STRATEGY_IATI_LIKE_CPNR_V2_4_GDS, $blocked, true)) {
                    $blocked[] = SabreGdsPnrCreateStrategyRegistry::STRATEGY_IATI_LIKE_CPNR_V2_4_GDS;
                }
            }
        }

        if ($eligible === []) {
            return array_merge([
                'selected_strategy' => null,
                'selection_reason' => self::REASON_NO_ELIGIBLE,
                'eligible_strategies' => [],
                'blocked_strategies' => $blocked,
                'fallback_available' => $this->hasAdminFallbackCandidate($candidateDigests, $previousFailedStrategy),
                'manual_review' => true,
                'reason_code' => self::REASON_NO_ELIGIBLE,
                'public_auto_certified' => true,
                'public_auto_pnr_certified' => true,
                'public_auto_block_reason' => null,
                'public_auto_pnr_attempt_ready' => true,
                'scenario_runner_override_applied' => $scenarioOverrideApplied,
                'strategy_option' => $strategyOption,
                'candidate_exclusion_diagnostics' => $this->buildScenarioRunnerExclusionDiagnostics(
                    $candidateDigests,
                    $completion,
                    $selectedFareOptionReady,
                    $sabreBookingContextReady,
                    $scenarioOverrideApplied,
                    $blocked,
                    $previousFailedStrategy,
                    $previousFormatFailure,
                    true,
                ),
            ], [
                'connecting_brand_context' => $publicAutoCert,
                'auto_pnr_context_completion' => $completion,
            ], $this->tripShapeSelectionDiagnostics($booking, $tripType, $candidateDigests, $mixedCertApproved));
        }

        $knownGood = $connId > 0 && $validatingCarrier !== ''
            ? $this->evidenceRecorder->findBestKnownGood(
                $connId,
                $validatingCarrier,
                $category,
                $tripType,
                $segmentCount,
                $eligible,
            )
            : null;
        if ($knownGood !== null && in_array($knownGood->strategy_code, $eligible, true)) {
            $result = $this->selectionResult(
                $knownGood->strategy_code,
                self::REASON_KNOWN_GOOD,
                $eligible,
                $blocked,
                $previousFormatFailure,
                $knownGood,
                $previousFailedStrategy,
                $connId,
                $validatingCarrier,
                $category,
                $tripType,
                $segmentCount,
                $publicAutoCert,
                $completion,
                $scenarioOverrideApplied,
            );

            return $this->finalizeScenarioRunnerAutoSelection($result, $candidateDigests, $completion, $strategyOption);
        }

        $matrixDefault = $this->scenarioRunnerRouteMatrixDefault($category, $eligible);
        if ($matrixDefault !== null) {
            $reason = match (true) {
                $matrixDefault === SabreGdsPnrCreateStrategyRegistry::STRATEGY_IATI_LIKE_CPNR_V2_4_GDS
                    && $category === SabreCertifiedRouteSelector::CATEGORY_ONE_WAY_DIRECT_SAME_CARRIER
                    => self::REASON_SCENARIO_RUNNER_PK_DIRECT_IATI,
                $matrixDefault === SabreGdsPnrCreateStrategyRegistry::STRATEGY_IATI_LIKE_CPNR_V2_4_GDS
                    && $category === SabreCertifiedRouteSelector::CATEGORY_RETURN
                    => self::REASON_SCENARIO_RUNNER_RETURN_SAME_CARRIER_IATI,
                $matrixDefault === SabreGdsPnrCreateStrategyRegistry::STRATEGY_IATI_LIKE_CPNR_V2_4_GDS
                    && $category === SabreCertifiedRouteSelector::CATEGORY_ONE_WAY_MULTISTOP_SAME_CARRIER_GDS
                    => self::REASON_SCENARIO_RUNNER_ONE_WAY_MULTISTOP_SAME_CARRIER_IATI,
                $matrixDefault === SabreGdsPnrCreateStrategyRegistry::STRATEGY_IATI_LIKE_CPNR_V2_4_GDS
                    && $category === SabreCertifiedRouteSelector::CATEGORY_MIXED_INTERLINE
                    => self::REASON_SCENARIO_RUNNER_MIXED_CARRIER_IATI,
                default => self::REASON_CERTIFIED_ROUTE_MATRIX,
            };
            $result = $this->selectionResult(
                $matrixDefault,
                $reason,
                $eligible,
                $blocked,
                $previousFormatFailure,
                null,
                $previousFailedStrategy,
                $connId,
                $validatingCarrier,
                $category,
                $tripType,
                $segmentCount,
                $publicAutoCert,
                $completion,
                $scenarioOverrideApplied,
            );

            return $this->finalizeScenarioRunnerAutoSelection($result, $candidateDigests, $completion, $strategyOption);
        }

        $selected = $this->highestConfidenceEligible($eligible);
        $result = $this->selectionResult(
            $selected,
            self::REASON_HIGHEST_CONFIDENCE,
            $eligible,
            $blocked,
            $previousFormatFailure,
            null,
            $previousFailedStrategy,
            $connId,
            $validatingCarrier,
            $category,
            $tripType,
            $segmentCount,
            $publicAutoCert,
            $completion,
            $scenarioOverrideApplied,
        );

        return $this->finalizeScenarioRunnerAutoSelection($result, $candidateDigests, $completion, $strategyOption);
    }

    /**
     * @param  list<array<string, mixed>>  $candidateDigests
     * @param  array<string, mixed>  $publicAutoCert
     * @return array<string, mixed>
     */
    protected function selectExplicitScenarioRunnerStrategy(
        Booking $booking,
        string $strategyOption,
        array $candidateDigests,
        array $completion,
        array $publicAutoCert,
        bool $scenarioOverrideApplied,
        bool $selectedFareOptionReady,
        bool $sabreBookingContextReady,
    ): array {
        if (! $this->registry->isSupported($strategyOption)) {
            return array_merge([
                'selected_strategy' => null,
                'selection_reason' => self::REASON_NO_ELIGIBLE,
                'eligible_strategies' => [],
                'blocked_strategies' => [$strategyOption],
                'fallback_available' => false,
                'manual_review' => true,
                'reason_code' => self::REASON_NO_ELIGIBLE,
                'public_auto_pnr_attempt_ready' => true,
                'scenario_runner_override_applied' => $scenarioOverrideApplied,
                'strategy_option' => $strategyOption,
            ], [
                'connecting_brand_context' => $publicAutoCert,
                'auto_pnr_context_completion' => $completion,
            ]);
        }

        $candidate = null;
        foreach ($candidateDigests as $row) {
            if ((string) ($row['strategy_code'] ?? '') === $strategyOption) {
                $candidate = $row;
                break;
            }
        }

        $explicitEligible = $candidate !== null
            && ($candidate['context_ready'] ?? false) === true
            && ($candidate['required_fields_present'] ?? false) === true
            && (
                ($candidate['automatic_allowed'] ?? false) === true
                || ($candidate['admin_confirmed_fallback_allowed'] ?? false) === true
            )
            && ($completion['public_auto_pnr_attempt_ready'] ?? false) === true;

        if (! $explicitEligible) {
            return array_merge([
                'selected_strategy' => null,
                'selection_reason' => self::REASON_NO_ELIGIBLE,
                'eligible_strategies' => [],
                'blocked_strategies' => [$strategyOption],
                'fallback_available' => false,
                'manual_review' => true,
                'reason_code' => self::REASON_NO_ELIGIBLE,
                'public_auto_pnr_attempt_ready' => true,
                'scenario_runner_override_applied' => $scenarioOverrideApplied,
                'strategy_option' => $strategyOption,
                'candidate_exclusion_diagnostics' => $this->buildScenarioRunnerExclusionDiagnostics(
                    $candidateDigests,
                    $completion,
                    $selectedFareOptionReady,
                    $sabreBookingContextReady,
                    $scenarioOverrideApplied,
                    [$strategyOption],
                ),
            ], [
                'connecting_brand_context' => $publicAutoCert,
                'auto_pnr_context_completion' => $completion,
            ]);
        }

        return array_merge($this->selectionResult(
            $strategyOption,
            self::REASON_SCENARIO_RUNNER_EXPLICIT_STRATEGY,
            [$strategyOption],
            [],
            false,
            null,
            null,
            0,
            '',
            '',
            '',
            0,
            $publicAutoCert,
            $completion,
            $scenarioOverrideApplied,
        ), [
            'strategy_option' => $strategyOption,
            'reason_code' => self::REASON_SCENARIO_RUNNER_EXPLICIT_STRATEGY,
        ]);
    }

    /**
     * @param  array<string, mixed>  $result
     * @param  list<array<string, mixed>>  $candidateDigests
     * @param  array<string, mixed>  $completion
     * @return array<string, mixed>
     */
    protected function finalizeScenarioRunnerAutoSelection(
        array $result,
        array $candidateDigests,
        array $completion,
        string $strategyOption,
    ): array {
        $result['strategy_option'] = $strategyOption;
        $unexpected = $this->detectUnexpectedScenarioRunnerAutoPriority($result, $candidateDigests, $completion);
        if ($unexpected !== null) {
            $result['unexpected_strategy_priority'] = $unexpected;
            $result['safe_reason_code'] = self::SAFE_REASON_UNEXPECTED_STRATEGY_PRIORITY;
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $selection
     * @param  list<array<string, mixed>>  $candidateDigests
     * @param  array<string, mixed>  $completion
     * @return array<string, mixed>|null
     */
    protected function detectUnexpectedScenarioRunnerAutoPriority(
        array $selection,
        array $candidateDigests,
        array $completion,
    ): ?array {
        $selected = trim((string) ($selection['selected_strategy'] ?? ''));
        if ($selected !== SabreGdsPnrCreateStrategyRegistry::STRATEGY_PASSENGER_RECORDS_V2_5_GDS) {
            return null;
        }

        $iatiCode = SabreGdsPnrCreateStrategyRegistry::STRATEGY_IATI_LIKE_CPNR_V2_4_GDS;
        $iatiCandidate = null;
        foreach ($candidateDigests as $candidate) {
            if ((string) ($candidate['strategy_code'] ?? '') === $iatiCode) {
                $iatiCandidate = $candidate;
                break;
            }
        }
        if ($iatiCandidate === null || ! $this->scenarioRunnerAutoCandidateEligible($iatiCandidate, $completion)) {
            return null;
        }

        return [
            'safe_reason_code' => self::SAFE_REASON_UNEXPECTED_STRATEGY_PRIORITY,
            'expected_strategy' => $iatiCode,
            'actual_strategy' => $selected,
            'scenario_runner_override_applied' => ($selection['scenario_runner_override_applied'] ?? false) === true,
        ];
    }

    /**
     * @param  array<string, mixed>  $completion
     */
    protected function resolveScenarioRunnerOverrideApplied(array $completion, bool $sabreBookingContextReady): bool
    {
        if (($completion['public_auto_pnr_attempt_ready'] ?? false) !== true) {
            return false;
        }
        $status = trim((string) ($completion['auto_pnr_context_completion_status'] ?? ''));

        return in_array($status, [
            SabreGdsAutoPnrContextCompletionService::STATUS_COMPLETE,
            SabreGdsAutoPnrContextCompletionService::STATUS_REPAIRED,
        ], true) || $sabreBookingContextReady;
    }

    /**
     * @param  list<string>  $eligible
     */
    protected function scenarioRunnerRouteMatrixDefault(string $category, array $eligible): ?string
    {
        if ($category === SabreCertifiedRouteSelector::CATEGORY_MIXED_INTERLINE) {
            if (in_array(SabreGdsPnrCreateStrategyRegistry::STRATEGY_IATI_LIKE_CPNR_V2_4_GDS, $eligible, true)) {
                return SabreGdsPnrCreateStrategyRegistry::STRATEGY_IATI_LIKE_CPNR_V2_4_GDS;
            }

            return null;
        }

        if ($category === SabreCertifiedRouteSelector::CATEGORY_RETURN
            || $category === SabreCertifiedRouteSelector::CATEGORY_ONE_WAY_MULTISTOP_SAME_CARRIER_GDS) {
            foreach ([
                SabreGdsPnrCreateStrategyRegistry::STRATEGY_IATI_LIKE_CPNR_V2_4_GDS,
                SabreGdsPnrCreateStrategyRegistry::STRATEGY_TRADITIONAL_PNR_CREATE_V1,
            ] as $code) {
                if (in_array($code, $eligible, true)) {
                    return $code;
                }
            }
        }

        return $this->certifiedRouteMatrixDefault($category, $eligible);
    }

    /**
     * @param  list<array<string, mixed>>  $candidateDigests
     * @return array<string, mixed>
     */
    protected function tripShapeSelectionDiagnostics(
        Booking $booking,
        string $tripType,
        array $candidateDigests,
        bool $mixedCertApproved = false,
    ): array {
        $readiness = $this->certificationSupport->buildReadiness($booking);
        $shapeDiag = $this->isReturnTripType($tripType)
            ? app(SabreGdsReturnTripClassifier::class)->diagnose($booking, $readiness)
            : app(SabreGdsOneWayTripShapeClassifier::class)->classify($booking, $readiness);

        $iati = null;
        foreach ($candidateDigests as $candidate) {
            if ((string) ($candidate['strategy_code'] ?? '') === SabreGdsPnrCreateStrategyRegistry::STRATEGY_IATI_LIKE_CPNR_V2_4_GDS) {
                $iati = $candidate;
                break;
            }
        }

        $expected = match (true) {
            in_array($tripType, [
                SabreGdsReturnTripClassifier::TRIP_RETURN_SAME_CARRIER,
                'round_trip',
            ], true) => SabreGdsPnrCreateStrategyRegistry::STRATEGY_IATI_LIKE_CPNR_V2_4_GDS,
            $tripType === SabreGdsOneWayTripShapeClassifier::TRIP_ONE_WAY_MULTISTOP_SAME_CARRIER,
            $tripType === SabreGdsOneWayTripShapeClassifier::TRIP_ONE_WAY_THREE_STOP_SAME_CARRIER,
            $tripType === SabreGdsOneWayTripShapeClassifier::TRIP_ONE_WAY_FOUR_STOP_SAME_CARRIER
                => SabreGdsPnrCreateStrategyRegistry::STRATEGY_IATI_LIKE_CPNR_V2_4_GDS,
            default => null,
        };

        $blockReason = null;
        $automaticBlockReason = null;
        $carriers = is_array($readiness['carrier_chain'] ?? null) ? $readiness['carrier_chain'] : [];
        $mixedCarrierDetected = count($carriers) > 1
            || ($shapeDiag['mixed_carrier'] ?? false) === true
            || in_array($tripType, [
                SabreGdsOneWayTripShapeClassifier::TRIP_ONE_WAY_SINGLE_CONNECTION_MIXED_CARRIER,
                SabreGdsOneWayTripShapeClassifier::TRIP_ONE_WAY_MULTISTOP_MIXED_CARRIER,
                SabreGdsReturnTripClassifier::TRIP_RETURN_MIXED_CARRIER,
            ], true);
        $interlineOrMixedBlocked = in_array($tripType, [
            SabreGdsOneWayTripShapeClassifier::TRIP_ONE_WAY_SINGLE_CONNECTION_MIXED_CARRIER,
            SabreGdsOneWayTripShapeClassifier::TRIP_ONE_WAY_MULTISTOP_MIXED_CARRIER,
            SabreGdsReturnTripClassifier::TRIP_RETURN_MIXED_CARRIER,
        ], true) && ! $mixedCertApproved;

        if ($iati !== null && ($iati['context_ready'] ?? false) !== true) {
            $missing = is_array($iati['missing_fields'] ?? null) ? $iati['missing_fields'] : [];
            $blockReason = $missing !== [] ? implode(',', array_slice($missing, 0, 6)) : 'context_not_ready';
            $automaticBlockReason = $interlineOrMixedBlocked
                ? SabreGdsLiveScenarioPlanCandidateDiagnostics::BLOCK_MIXED_CARRIER_NOT_CERTIFIED
                : $blockReason;
        } elseif (in_array($tripType, [
            SabreGdsOneWayTripShapeClassifier::TRIP_ONE_WAY_SINGLE_CONNECTION_MIXED_CARRIER,
            SabreGdsOneWayTripShapeClassifier::TRIP_ONE_WAY_MULTISTOP_MIXED_CARRIER,
        ], true)) {
            if ($mixedCertApproved) {
                $expected = SabreGdsPnrCreateStrategyRegistry::STRATEGY_IATI_LIKE_CPNR_V2_4_GDS;
                $interlineOrMixedBlocked = false;
            } else {
                $blockReason = 'one_way_mixed_carrier_not_automatic';
                $automaticBlockReason = SabreGdsLiveScenarioPlanCandidateDiagnostics::BLOCK_MIXED_CARRIER_NOT_CERTIFIED;
            }
        } elseif ($tripType === SabreGdsReturnTripClassifier::TRIP_RETURN_MIXED_CARRIER) {
            if ($mixedCertApproved) {
                $expected = SabreGdsPnrCreateStrategyRegistry::STRATEGY_IATI_LIKE_CPNR_V2_4_GDS;
                $interlineOrMixedBlocked = false;
            } else {
                $blockReason = 'return_mixed_carrier_not_automatic';
                $automaticBlockReason = SabreGdsLiveScenarioPlanCandidateDiagnostics::BLOCK_MIXED_CARRIER_NOT_CERTIFIED;
            }
        } elseif (in_array($tripType, [
            SabreGdsOneWayTripShapeClassifier::TRIP_ONE_WAY_THREE_STOP_SAME_CARRIER,
            SabreGdsOneWayTripShapeClassifier::TRIP_ONE_WAY_FOUR_STOP_SAME_CARRIER,
        ], true)) {
            $blockReason = SabreGdsOneWayTripShapeClassifier::ADVANCED_ITINERARY_PLAN_ONLY_BLOCK_REASON;
            $automaticBlockReason = SabreGdsOneWayTripShapeClassifier::ADVANCED_ITINERARY_PLAN_ONLY_BLOCK_REASON;
            $expected = SabreGdsPnrCreateStrategyRegistry::STRATEGY_IATI_LIKE_CPNR_V2_4_GDS;
        } elseif (($shapeDiag['trip_type_detected'] ?? '') === 'unknown') {
            $blockReason = (string) ($shapeDiag['segment_sell_block_reason'] ?? 'trip_shape_unknown');
            $automaticBlockReason = $blockReason;
        }

        if ($interlineOrMixedBlocked) {
            $expected = null;
        }

        return array_merge($shapeDiag, array_filter([
            'context_ready_block_reason' => $blockReason,
            'selected_strategy_expected' => $expected,
            'selected_strategy_actual' => null,
            'mixed_carrier_detected' => $mixedCarrierDetected,
            'carrier_chain_count' => count($carriers) > 0 ? count($carriers) : null,
            'interline_or_mixed_blocked' => $interlineOrMixedBlocked,
            'automatic_block_reason' => $automaticBlockReason,
        ], static fn ($v) => $v !== null && $v !== ''));
    }

    protected function isReturnTripType(string $tripType): bool
    {
        return in_array($tripType, [
            SabreGdsReturnTripClassifier::TRIP_RETURN_SAME_CARRIER,
            SabreGdsReturnTripClassifier::TRIP_RETURN_MIXED_CARRIER,
            'round_trip',
        ], true);
    }

    /**
     * @param  list<array<string, mixed>>  $candidateDigests
     * @return array<string, mixed>
     * @deprecated Use {@see tripShapeSelectionDiagnostics()}
     */
    protected function returnTripSelectionDiagnostics(
        Booking $booking,
        string $tripType,
        array $candidateDigests,
    ): array {
        return $this->tripShapeSelectionDiagnostics($booking, $tripType, $candidateDigests);
    }

    /**
     * @param  array<string, mixed>  $candidate
     * @param  array<string, mixed>  $completion
     */
    protected function scenarioRunnerAutoCandidateEligible(array $candidate, array $completion): bool
    {
        if (($completion['public_auto_pnr_attempt_ready'] ?? false) !== true) {
            return false;
        }

        if (($candidate['context_ready'] ?? false) !== true) {
            return false;
        }

        if (($candidate['required_fields_present'] ?? false) !== true) {
            return false;
        }

        return ($candidate['automatic_allowed'] ?? false) === true;
    }

    /**
     * @param  array<string, mixed>  $candidate
     * @param  array<string, mixed>  $completion
     */
    protected function scenarioRunnerCandidateEligible(array $candidate, array $completion): bool
    {
        if (($completion['public_auto_pnr_attempt_ready'] ?? false) !== true) {
            return false;
        }

        if (($candidate['context_ready'] ?? false) !== true) {
            return false;
        }

        if (($candidate['required_fields_present'] ?? false) !== true) {
            return false;
        }

        $automaticAllowed = ($candidate['automatic_allowed'] ?? false) === true;
        $adminFallbackAllowed = ($candidate['admin_confirmed_fallback_allowed'] ?? false) === true;

        return $automaticAllowed || $adminFallbackAllowed;
    }

    /**
     * @param  list<array<string, mixed>>  $candidateDigests
     * @param  array<string, mixed>  $completion
     * @param  list<string>  $blocked
     * @return list<array<string, mixed>>
     */
    protected function buildScenarioRunnerExclusionDiagnostics(
        array $candidateDigests,
        array $completion,
        bool $selectedFareOptionReady,
        bool $sabreBookingContextReady,
        bool $scenarioRunnerOverrideApplied,
        array $blocked = [],
        ?string $previousFailedStrategy = null,
        bool $previousFormatFailure = false,
        bool $autoModeOnly = false,
    ): array {
        $out = [];
        foreach ($candidateDigests as $candidate) {
            $code = (string) ($candidate['strategy_code'] ?? '');
            if ($code === '') {
                continue;
            }

            $eligibleFn = $autoModeOnly
                ? fn (array $c, array $comp): bool => $this->scenarioRunnerAutoCandidateEligible($c, $comp)
                : fn (array $c, array $comp): bool => $this->scenarioRunnerCandidateEligible($c, $comp);

            $excludedBy = null;
            $exclusionReason = null;
            if (! $eligibleFn($candidate, $completion)) {
                if (($completion['public_auto_pnr_attempt_ready'] ?? false) !== true) {
                    $excludedBy = 'completion';
                    $exclusionReason = self::EXCLUSION_PUBLIC_AUTO_NOT_READY;
                } elseif (($candidate['required_fields_present'] ?? false) !== true) {
                    $excludedBy = 'required_fields';
                    $exclusionReason = self::EXCLUSION_REQUIRED_FIELDS_MISSING;
                } elseif (($candidate['context_ready'] ?? false) !== true) {
                    $excludedBy = 'context';
                    $exclusionReason = self::EXCLUSION_CONTEXT_NOT_READY;
                } else {
                    $excludedBy = 'automatic_policy';
                    $exclusionReason = $autoModeOnly && ($candidate['automatic_allowed'] ?? false) !== true
                        ? self::EXCLUSION_AUTOMATIC_NOT_ALLOWED
                        : self::EXCLUSION_AUTOMATIC_NOT_ALLOWED;
                }
            } elseif ($previousFormatFailure && $code === $previousFailedStrategy) {
                $excludedBy = 'previous_attempt';
                $exclusionReason = self::EXCLUSION_PREVIOUS_FORMAT_FAILURE;
            } elseif (in_array($code, $blocked, true)) {
                $excludedBy = 'evidence';
                $exclusionReason = self::EXCLUSION_MIXED_SUCCESS_FORMAT_FAILURE;
            }

            $out[] = [
                'strategy_code' => $code,
                'context_ready' => ($candidate['context_ready'] ?? false) === true,
                'required_fields_present' => ($candidate['required_fields_present'] ?? false) === true,
                'automatic_allowed' => ($candidate['automatic_allowed'] ?? false) === true,
                'admin_confirmed_fallback_allowed' => ($candidate['admin_confirmed_fallback_allowed'] ?? false) === true,
                'public_auto_pnr_attempt_ready' => ($completion['public_auto_pnr_attempt_ready'] ?? false) === true,
                'excluded_by' => $excludedBy,
                'exclusion_reason' => $exclusionReason,
                'selected_fare_option_ready' => $selectedFareOptionReady,
                'sabre_booking_context_ready' => $sabreBookingContextReady,
            'scenario_runner_override_applied' => $scenarioRunnerOverrideApplied,
            'strategy_option' => null,
        ];
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $candidateDigests
     * @param  array<string, mixed>  $publicAutoCert
     * @return array<string, mixed>
     */
    protected function blockedPublicAutoSelection(array $candidateDigests, array $publicAutoCert): array
    {
        $blocked = [];
        foreach ($candidateDigests as $candidate) {
            $code = (string) ($candidate['strategy_code'] ?? '');
            if ($code !== '' && ($candidate['automatic_allowed'] ?? false) === true) {
                $blocked[] = $code;
            }
        }

        return [
            'selected_strategy' => null,
            'selection_reason' => self::REASON_NO_ELIGIBLE,
            'eligible_strategies' => [],
            'blocked_strategies' => $blocked,
            'fallback_available' => $this->adminFallbackAllowed($candidateDigests),
            'manual_review' => true,
            'reason_code' => self::REASON_NO_ELIGIBLE,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $candidateDigests
     */
    protected function adminFallbackAllowed(array $candidateDigests): bool
    {
        foreach ($candidateDigests as $candidate) {
            if (($candidate['admin_confirmed_fallback_allowed'] ?? false) === true) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $eligible
     * @param  list<string>  $blocked
     * @return array<string, mixed>
     */
    protected function selectionResult(
        string $selected,
        string $reason,
        array $eligible,
        array $blocked,
        bool $previousFormatFailure,
        ?\App\Models\SabreGdsPnrCreateStrategyEvidence $knownGood = null,
        ?string $previousFailedStrategy = null,
        int $connId = 0,
        string $validatingCarrier = '',
        string $category = '',
        string $tripType = '',
        int $segmentCount = 0,
        ?array $publicAutoCert = null,
        ?array $completion = null,
        bool $scenarioRunnerOverrideApplied = false,
    ): array {
        $v25Code = SabreGdsPnrCreateStrategyRegistry::STRATEGY_PASSENGER_RECORDS_V2_5_GDS;
        $traditionalCode = SabreGdsPnrCreateStrategyRegistry::STRATEGY_TRADITIONAL_PNR_CREATE_V1;

        return [
            'selected_strategy' => $selected,
            'selection_reason' => $reason,
            'eligible_strategies' => $eligible,
            'blocked_strategies' => $blocked,
            'fallback_available' => $this->fallbackAvailableForSelection($blocked, $previousFormatFailure),
            'manual_review' => false,
            'public_auto_certified' => true,
            'public_auto_pnr_certified' => true,
            'public_auto_block_reason' => null,
            'reason_code' => $previousFormatFailure && ! in_array($selected, $eligible, true)
                ? self::REASON_PREVIOUS_FORMAT_FAILURE_BLOCKS_AUTO
                : $reason,
            'scenario_runner_override_applied' => $scenarioRunnerOverrideApplied,
            'known_good_strategy_evidence' => $knownGood !== null ? [
                'strategy_code' => (string) $knownGood->strategy_code,
                'success_count' => (int) $knownGood->success_count,
                'last_success_booking_id' => $knownGood->last_success_booking_id,
                'last_success_at' => $knownGood->last_success_at?->toIso8601String(),
                'validating_carrier' => (string) $knownGood->validating_carrier,
                'route_pattern' => (string) $knownGood->route_pattern,
            ] : null,
            'passenger_records_v2_5_gds_not_selected_reason' => $selected !== $v25Code
                ? $this->resolveV25NotSelectedReason($v25Code, $eligible, $blocked, $previousFailedStrategy, $previousFormatFailure)
                : null,
            'traditional_not_selected_reason' => $selected !== $traditionalCode
                ? $this->resolveTraditionalNotSelectedReason(
                    $traditionalCode,
                    $eligible,
                    $blocked,
                    $connId,
                    $validatingCarrier,
                    $category,
                    $tripType,
                    $segmentCount,
                )
                : null,
            'connecting_brand_context' => $publicAutoCert,
            'auto_pnr_context_completion' => $completion ?? [],
            'public_auto_pnr_attempt_ready' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function resolveContextCompletion(Booking $booking): array
    {
        return $this->contextCompletion->completeForBooking($booking);
    }

    /**
     * @param  list<string>  $eligible
     * @param  list<string>  $blocked
     */
    protected function fallbackAvailableForSelection(array $blocked, bool $previousFormatFailure): bool
    {
        return in_array(
            SabreGdsPnrCreateStrategyRegistry::STRATEGY_PASSENGER_RECORDS_V2_5_GDS,
            $blocked,
            true,
        ) || in_array(
            SabreGdsPnrCreateStrategyRegistry::STRATEGY_TRADITIONAL_PNR_CREATE_V1,
            $blocked,
            true,
        ) || $previousFormatFailure;
    }

    /**
     * @param  list<string>  $eligible
     * @param  list<string>  $blocked
     */
    protected function resolveTraditionalNotSelectedReason(
        string $traditionalCode,
        array $eligible,
        array $blocked,
        int $connId,
        string $validatingCarrier,
        string $category,
        string $tripType,
        int $segmentCount,
    ): ?string {
        if ($connId > 0 && $validatingCarrier !== ''
            && $this->evidenceRecorder->hasMixedSuccessFormatFailureEvidence(
                $connId,
                $validatingCarrier,
                $category,
                $tripType,
                $segmentCount,
                $traditionalCode,
            )) {
            return self::TRADITIONAL_NOT_SELECTED_MIXED_SUCCESS;
        }

        if (in_array($traditionalCode, $blocked, true)) {
            return self::TRADITIONAL_NOT_SELECTED_MIXED_SUCCESS;
        }

        if (in_array($traditionalCode, $eligible, true)) {
            return self::TRADITIONAL_NOT_SELECTED_LOWER_PRIORITY;
        }

        return null;
    }

    /**
     * @param  list<string>  $eligible
     * @param  list<string>  $blocked
     */
    protected function resolveV25NotSelectedReason(
        string $v25Code,
        array $eligible,
        array $blocked,
        ?string $previousFailedStrategy,
        bool $previousFormatFailure,
    ): ?string {
        if (in_array($v25Code, $eligible, true)) {
            return self::V25_NOT_SELECTED_LOWER_PRIORITY;
        }

        if ($previousFormatFailure && $previousFailedStrategy === $v25Code) {
            return self::V25_NOT_SELECTED_PREVIOUS_FORMAT_FAILURE;
        }

        if (in_array($v25Code, $blocked, true)) {
            $definition = $this->registry->get($v25Code);

            return (($definition['automatic_allowed'] ?? false) !== true)
                ? self::V25_NOT_SELECTED_AUTOMATIC_DISABLED
                : self::V25_NOT_SELECTED_LOWER_PRIORITY;
        }

        return null;
    }

    /**
     * @param  list<string>  $eligible
     */
    protected function certifiedRouteMatrixDefault(string $category, array $eligible): ?string
    {
        $preference = match ($category) {
            SabreCertifiedRouteSelector::CATEGORY_ONE_WAY_DIRECT_SAME_CARRIER => [
                SabreGdsPnrCreateStrategyRegistry::STRATEGY_IATI_LIKE_CPNR_V2_4_GDS,
                SabreGdsPnrCreateStrategyRegistry::STRATEGY_TRADITIONAL_PNR_CREATE_V1,
            ],
            SabreCertifiedRouteSelector::CATEGORY_ONE_WAY_CONNECTING_SAME_CARRIER_GDS => [
                SabreGdsPnrCreateStrategyRegistry::STRATEGY_IATI_LIKE_CPNR_V2_4_GDS,
                SabreGdsPnrCreateStrategyRegistry::STRATEGY_TRADITIONAL_PNR_CREATE_V1,
            ],
            SabreCertifiedRouteSelector::CATEGORY_ONE_WAY_MULTISTOP_SAME_CARRIER_GDS => [
                SabreGdsPnrCreateStrategyRegistry::STRATEGY_IATI_LIKE_CPNR_V2_4_GDS,
                SabreGdsPnrCreateStrategyRegistry::STRATEGY_TRADITIONAL_PNR_CREATE_V1,
            ],
            SabreCertifiedRouteSelector::CATEGORY_RETURN,
            SabreCertifiedRouteSelector::CATEGORY_MULTI_CITY => [
                SabreGdsPnrCreateStrategyRegistry::STRATEGY_TRADITIONAL_PNR_CREATE_V1,
            ],
            default => [
                SabreGdsPnrCreateStrategyRegistry::STRATEGY_TRADITIONAL_PNR_CREATE_V1,
            ],
        };

        foreach ($preference as $code) {
            if (in_array($code, $eligible, true)) {
                return $code;
            }
        }

        return $eligible[0] ?? null;
    }

    /**
     * @param  list<string>  $eligible
     */
    protected function highestConfidenceEligible(array $eligible): string
    {
        $rank = [
            SabreGdsPnrCreateStrategyRegistry::STRATEGY_IATI_LIKE_CPNR_V2_4_GDS => 45,
            SabreGdsPnrCreateStrategyRegistry::STRATEGY_TRADITIONAL_PNR_CREATE_V1 => 35,
            SabreGdsPnrCreateStrategyRegistry::STRATEGY_PASSENGER_RECORDS_V2_5_GDS => 30,
            SabreGdsPnrCreateStrategyRegistry::STRATEGY_MINIMAL_AIRBOOK_AIRPRICE_ENDTRANSACTION_GDS => 10,
        ];
        usort($eligible, static fn (string $a, string $b): int => ($rank[$b] ?? 0) <=> ($rank[$a] ?? 0));

        return $eligible[0];
    }

    /**
     * @param  list<array<string, mixed>>  $candidateDigests
     */
    protected function hasAdminFallbackCandidate(array $candidateDigests, ?string $previousFailedStrategy): bool
    {
        foreach ($candidateDigests as $candidate) {
            $code = (string) ($candidate['strategy_code'] ?? '');
            if ($code === '' || $code === $previousFailedStrategy) {
                continue;
            }
            if (($candidate['admin_confirmed_fallback_allowed'] ?? false) === true
                && ($candidate['context_ready'] ?? false) === true) {
                return true;
            }
        }

        return false;
    }

    protected function resolvePreviousFailedStrategy(Booking $booking): ?string
    {
        $attempt = SupplierBookingAttempt::query()
            ->where('booking_id', $booking->id)
            ->where('provider', SupplierProvider::Sabre->value)
            ->orderByDesc('id')
            ->first();
        if ($attempt === null) {
            return null;
        }
        $safe = is_array($attempt->safe_summary) ? $attempt->safe_summary : [];
        $strategy = trim((string) ($safe['payload_schema'] ?? $safe['payload_style'] ?? ''));

        return $strategy !== '' ? $strategy : null;
    }

    protected function previousEnhancedAirBookFormatFailure(Booking $booking, ?string $previousFailedStrategy): bool
    {
        $attempt = SupplierBookingAttempt::query()
            ->where('booking_id', $booking->id)
            ->where('provider', SupplierProvider::Sabre->value)
            ->orderByDesc('id')
            ->first();
        if ($attempt === null) {
            return false;
        }
        $safe = is_array($attempt->safe_summary) ? $attempt->safe_summary : [];

        return $this->resultClassifier->isEnhancedAirBookFormatError($safe);
    }

    protected function mixedCarrierGate(): SabreGdsMixedCarrierCertificationGate
    {
        return app(SabreGdsMixedCarrierCertificationGate::class);
    }

    /**
     * @param  array<string, mixed>  $completion
     * @param  array<string, mixed>  $context
     */
    protected function mixedCarrierPayloadPreflightAllowsLive(Booking $booking, array $completion, string $tripType, array $context = []): bool
    {
        $preflight = app(SabreGdsMixedCarrierFareBasisPayloadPreflight::class)->evaluate($booking, array_merge($context, [
            'completion' => $completion,
            'trip_type' => $tripType,
            'selected_strategy' => SabreGdsPnrCreateStrategyRegistry::STRATEGY_IATI_LIKE_CPNR_V2_4_GDS,
            'mixed_carrier_certification_approved' => true,
            'scenario_live_pnr_create_approved' => true,
        ]));

        return ($preflight['allowed'] ?? false) === true;
    }
}
