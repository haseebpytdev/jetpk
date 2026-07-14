<?php

namespace App\Support\Sabre\Scenario;

use App\Models\SupplierConnection;
use App\Services\Suppliers\Sabre\SabreStoredPricingContextDigest;
use App\Support\Bookings\SabrePnrCertificationSupport;
use App\Support\Sabre\GdsPnrCreate\SabreGdsAutoPnrContextCompletionService;
use App\Support\Sabre\GdsPnrCreate\SabreGdsMixedCarrierCertificationGate;
use App\Support\Sabre\GdsPnrCreate\SabreGdsMixedCarrierFareBasisPayloadPreflight;
use App\Support\Sabre\GdsPnrCreate\SabreGdsOneWayTripShapeClassifier;
use App\Support\Sabre\GdsPnrCreate\SabreGdsPnrCreateStrategyRegistry;
use App\Support\Sabre\GdsPnrCreate\SabreGdsReturnTripClassifier;

/**
 * Read-only plan-mode candidate diagnostics for Sabre GDS scenario runner (no live PNR, no PII).
 */
final class SabreGdsLiveScenarioPlanCandidateDiagnostics
{
    public const BLOCK_MIXED_CARRIER_NOT_CERTIFIED = 'mixed_carrier_not_certified';

    public const BLOCK_MIXED_CARRIER_PLAN_ONLY_READY = 'mixed_carrier_plan_only_ready_for_certified_live_retry';

    public const PAYLOAD_PREFLIGHT_STATUS_PASS = 'pass';

    public const PAYLOAD_PREFLIGHT_STATUS_BLOCKED = 'blocked';

    public const BLOCK_ADVANCED_ITINERARY_PLAN_ONLY = SabreGdsOneWayTripShapeClassifier::ADVANCED_ITINERARY_PLAN_ONLY_BLOCK_REASON;

    public function __construct(
        protected SabreStoredPricingContextDigest $pricingDigest,
        protected SabreGdsOneWayTripShapeClassifier $oneWayClassifier,
        protected SabreGdsReturnTripClassifier $returnClassifier,
        protected SabreGdsMixedCarrierFareBasisPayloadPreflight $mixedCarrierPayloadPreflight,
        protected SabreGdsAutoPnrContextCompletionService $contextCompletion,
        protected SabreGdsLiveScenarioRunnerBookingFactory $bookingFactory,
        protected SabrePnrCertificationSupport $certificationSupport,
    ) {}

    /**
     * @param  array<string, mixed>  $snap
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>  $scenario
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function diagnose(array $snap, array $row, array $scenario = [], array $options = []): array
    {
        $readiness = $this->pricingDigest->assessReadiness($snap);
        $segments = is_array($snap['segments'] ?? null) ? array_values(array_filter($snap['segments'], 'is_array')) : [];
        $segmentCount = (int) ($row['segment_count'] ?? count($segments));
        $stops = max(0, $segmentCount - 1);
        $marketing = is_array($row['marketing_carriers'] ?? null)
            ? $row['marketing_carriers']
            : (is_array($snap['marketing_carrier_chain'] ?? null) ? $snap['marketing_carrier_chain'] : []);
        $marketing = array_values(array_filter(array_map(
            static fn ($c): string => strtoupper(trim((string) $c)),
            $marketing,
        ), static fn (string $c): bool => $c !== ''));
        $operating = $this->extractOperatingCarriers($segments);
        $sameCarrier = ($row['same_carrier'] ?? null) === true
            || count(array_unique($marketing)) <= 1;
        $mixedCarrier = ($row['mixed_carrier'] ?? null) === true
            || ($segmentCount >= 2 && ! $sameCarrier);
        $carrierChain = trim((string) ($row['carrier_chain'] ?? ''));
        if ($carrierChain === '' && $marketing !== []) {
            $carrierChain = implode('+', $marketing);
        }
        $carrierChainCount = $carrierChain !== ''
            ? count(array_filter(explode('+', $carrierChain)))
            : count(array_unique($marketing));

        $tripType = strtolower(trim((string) ($scenario['trip_type'] ?? 'one_way')));
        $shape = $tripType === 'return'
            ? $this->returnClassifier->classifyFromNormalizedOffer($snap, $readiness)
            : $this->oneWayClassifier->classifyFromNormalizedOffer($snap, $readiness);

        $detectedTripType = trim((string) ($shape['trip_type'] ?? $shape['trip_type_detected'] ?? 'unknown'));
        $contextReady = ($readiness['auto_pnr_pricing_context_ready'] ?? false) === true;
        $publicAutoReady = $contextReady;
        $contextCompletionStatus = $contextReady ? 'ready' : 'incomplete';
        $mixedCertApproved = ($options['mixed_carrier_certification_approved'] ?? false) === true;

        $bookingClasses = is_array($row['booking_classes_by_segment'] ?? null) ? $row['booking_classes_by_segment'] : [];
        $fareBasis = is_array($row['fare_basis_codes_by_segment'] ?? null) ? $row['fare_basis_codes_by_segment'] : [];
        $cabins = is_array($row['cabin_by_segment'] ?? null) ? $row['cabin_by_segment'] : [];
        $handoff = is_array($snap['sabre_booking_context'] ?? null) ? $snap['sabre_booking_context'] : [];
        if ($cabins === [] && is_array($handoff['cabin_by_segment'] ?? null)) {
            $cabins = $handoff['cabin_by_segment'];
        }
        $scheduleRefs = is_array($handoff['schedule_refs'] ?? null) ? $handoff['schedule_refs'] : [];
        $legRefs = is_array($handoff['leg_refs'] ?? null) ? $handoff['leg_refs'] : [];

        $strategyPlan = $this->resolvePlanStrategyExpectation($detectedTripType, $mixedCarrier, $publicAutoReady, $mixedCertApproved);
        $interlineBlocked = $mixedCarrier || in_array($detectedTripType, [
            SabreGdsOneWayTripShapeClassifier::TRIP_ONE_WAY_SINGLE_CONNECTION_MIXED_CARRIER,
            SabreGdsOneWayTripShapeClassifier::TRIP_ONE_WAY_MULTISTOP_MIXED_CARRIER,
            SabreGdsReturnTripClassifier::TRIP_RETURN_MIXED_CARRIER,
        ], true);

        $diagnostics = [
            'route' => $row['route'] ?? null,
            'segment_count' => $segmentCount,
            'stops' => $stops,
            'marketing_carriers' => $marketing,
            'operating_carriers' => $operating,
            'validating_carrier' => strtoupper(trim((string) ($row['validating_carrier'] ?? ''))) ?: null,
            'carrier_chain' => $carrierChain !== '' ? $carrierChain : null,
            'carrier_chain_count' => $carrierChainCount,
            'same_carrier' => $sameCarrier,
            'mixed_carrier' => $mixedCarrier,
            'mixed_carrier_detected' => $mixedCarrier,
            'brand_code' => strtoupper(trim((string) ($row['brand_code'] ?? ''))) ?: null,
            'fare_basis_display' => $this->fareBasisDisplay($fareBasis),
            'booking_classes_by_segment_count' => count($bookingClasses),
            'fare_basis_codes_by_segment_count' => count($fareBasis),
            'cabin_by_segment_count' => count($cabins),
            'segment_rows_count' => (int) ($shape['segment_rows_count'] ?? $segmentCount),
            'schedule_refs_count' => (int) ($shape['schedule_refs_count'] ?? count($scheduleRefs)),
            'leg_refs_count' => (int) ($shape['leg_refs_count'] ?? count($legRefs)),
            'context_completion_status' => $contextCompletionStatus,
            'public_auto_pnr_attempt_ready' => $publicAutoReady,
            'trip_type' => $detectedTripType !== '' ? $detectedTripType : 'unknown',
            'category' => $shape['category'] ?? null,
            'route_shape' => $shape['route_shape'] ?? null,
            'selected_strategy' => $strategyPlan['selected_strategy'],
            'selected_strategy_expected' => $strategyPlan['selected_strategy_expected'],
            'automatic_allowed' => $strategyPlan['automatic_allowed'],
            'block_reason' => $strategyPlan['block_reason'],
            'automatic_block_reason' => $strategyPlan['automatic_block_reason'],
            'interline_or_mixed_blocked' => $interlineBlocked,
            'advanced_itinerary_plan_only' => ($shape['advanced_itinerary_plan_only'] ?? false) === true,
            'ticketing_attempted' => false,
            'airticket_attempted' => false,
            'pnr_attempted' => false,
            'live_call_attempted' => false,
        ];

        if ($this->shouldRunMixedCarrierPayloadPreflight(
            $mixedCarrier,
            $carrierChainCount,
            $segmentCount,
            $stops,
            $mixedCertApproved,
            $options,
        )) {
            return array_merge($diagnostics, $this->runMixedCarrierPayloadPreflight($snap, $row, $scenario, $options));
        }

        if ($mixedCarrier && $mixedCertApproved) {
            $sizeBlock = $this->mixedCarrierSizeBlockReason($segmentCount, $stops);
            if ($sizeBlock !== null) {
                $diagnostics['block_reason'] = $sizeBlock;
                $diagnostics['automatic_block_reason'] = $sizeBlock;
            }
        }

        return $diagnostics;
    }

    /**
     * @param  list<array<string, mixed>>  $segments
     * @return list<string>
     */
    protected function extractOperatingCarriers(array $segments): array
    {
        $carriers = [];
        foreach ($segments as $seg) {
            if (! is_array($seg)) {
                continue;
            }
            $op = strtoupper(trim((string) (
                $seg['operating_carrier'] ?? $seg['operating_airline'] ?? $seg['operating_airline_code'] ?? ''
            )));
            if ($op !== '') {
                $carriers[] = $op;
            }
        }

        return array_values(array_unique($carriers));
    }

    /**
     * @param  list<mixed>  $fareBasis
     */
    protected function fareBasisDisplay(array $fareBasis): ?string
    {
        if ($fareBasis === []) {
            return null;
        }

        return implode('/', array_map(static fn ($v): string => (string) $v, $fareBasis));
    }

    /**
     * @param  array<string, mixed>  $options
     */
    protected function shouldRunMixedCarrierPayloadPreflight(
        bool $mixedCarrier,
        int $carrierChainCount,
        int $segmentCount,
        int $stops,
        bool $mixedCertApproved,
        array $options,
    ): bool {
        if (! $mixedCarrier || ! $mixedCertApproved) {
            return false;
        }
        if ($carrierChainCount <= 1) {
            return false;
        }
        if ($segmentCount > SabreGdsMixedCarrierCertificationGate::MAX_SEGMENTS
            || $stops > SabreGdsMixedCarrierCertificationGate::MAX_STOPS) {
            return false;
        }

        return ($options['connection'] ?? null) instanceof SupplierConnection;
    }

    /**
     * @param  array<string, mixed>  $snap
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>  $scenario
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    protected function runMixedCarrierPayloadPreflight(
        array $snap,
        array $row,
        array $scenario,
        array $options,
    ): array {
        /** @var SupplierConnection $connection */
        $connection = $options['connection'];
        $booking = $this->bookingFactory->buildPlanPreflightBooking($connection, $scenario, $snap, $row);
        $completion = $this->contextCompletion->completeForBooking($booking);
        $tripType = $this->certificationSupport->detectTripType($booking);

        $preflight = $this->mixedCarrierPayloadPreflight->evaluate($booking, [
            'completion' => $completion,
            'trip_type' => $tripType,
            'mixed_carrier_certification_approved' => true,
            'scenario_runner_override_applied' => true,
            'plan_mode_payload_preflight' => true,
            'selected_strategy' => SabreGdsPnrCreateStrategyRegistry::STRATEGY_IATI_LIKE_CPNR_V2_4_GDS,
        ]);

        return $this->mapPayloadPreflightToPlanDiagnostics($preflight);
    }

    /**
     * @param  array<string, mixed>  $preflight
     * @return array<string, mixed>
     */
    protected function mapPayloadPreflightToPlanDiagnostics(array $preflight): array
    {
        $allowed = ($preflight['allowed'] ?? false) === true;
        $comparisonMatch = ($preflight['mixed_mapping_comparison_result'] ?? null) === 'match';
        $mappingComplete = ($preflight['mixed_fare_carrier_mapping_complete'] ?? false) === true;
        $schemaValid = ($preflight['command_pricing_schema_valid'] ?? null) === true;
        $pairingComplete = ($preflight['command_pricing_segmentselect_pairing_complete'] ?? null) === true;
        $brandSchemaValid = ($preflight['brand_schema_valid'] ?? null) !== false;
        $brandRphSchemaValid = ($preflight['brand_rph_schema_valid'] ?? null) !== false;
        $brandOmitted = ($preflight['brand_omitted_for_mixed_v24_segmentselect'] ?? false) === true;
        $brandPairingComplete = ($preflight['brand_segmentselect_pairing_complete'] ?? null) === true;
        $brandPairingRequired = ($preflight['brand_segmentselect_pairing_required'] ?? false) === true;
        $brandPairingValuesMatch = ($preflight['brand_segmentselect_pairing_values_match_normalized'] ?? null) === true;
        $brandSegmentSafe = $brandOmitted
            || ($brandSchemaValid && $brandRphSchemaValid && (! $brandPairingRequired || ($brandPairingComplete && $brandPairingValuesMatch)));
        $noFaresRiskClear = ($preflight['no_fares_rbd_carrier_preflight_risk'] ?? null) === false;
        $preflightPass = $allowed && $comparisonMatch && $mappingComplete && $schemaValid && $pairingComplete && $brandSegmentSafe && $noFaresRiskClear;
        $payloadBlockReason = trim((string) ($preflight['block_reason'] ?? ''));
        $wireCommandPricingCount = (int) ($preflight['wire_command_pricing_count']
            ?? $preflight['payload_command_pricing_segment_count']
            ?? 0);

        $common = [
            'has_fare_basis' => ($preflight['has_fare_basis'] ?? null) === true ? true : (($preflight['has_fare_basis'] ?? null) === false ? false : null),
            'has_booking_class' => ($preflight['has_booking_class'] ?? null) === true ? true : (($preflight['has_booking_class'] ?? null) === false ? false : null),
            'has_validating_carrier' => ($preflight['has_validating_carrier'] ?? null) === true ? true : (($preflight['has_validating_carrier'] ?? null) === false ? false : null),
            'rbd_carrier_mapping_complete' => ($preflight['rbd_carrier_mapping_complete'] ?? null) === true ? true : (($preflight['rbd_carrier_mapping_complete'] ?? null) === false ? false : null),
            'mixed_fare_carrier_mapping_complete' => ($preflight['mixed_fare_carrier_mapping_complete'] ?? null) === true ? true : (($preflight['mixed_fare_carrier_mapping_complete'] ?? null) === false ? false : null),
            'no_fares_rbd_carrier_preflight_risk' => ($preflight['no_fares_rbd_carrier_preflight_risk'] ?? null) === true ? true : (($preflight['no_fares_rbd_carrier_preflight_risk'] ?? null) === false ? false : null),
            'fare_component_count' => ($preflight['fare_component_count'] ?? null) !== null ? (int) $preflight['fare_component_count'] : null,
            'fare_component_carrier_count' => ($preflight['fare_component_carrier_count'] ?? null) !== null ? (int) $preflight['fare_component_carrier_count'] : null,
            'fare_component_carriers' => is_array($preflight['fare_component_carriers'] ?? null) ? $preflight['fare_component_carriers'] : null,
            'segment_marketing_carrier_count' => ($preflight['segment_marketing_carrier_count'] ?? null) !== null
                ? (int) $preflight['segment_marketing_carrier_count']
                : null,
            'segment_marketing_carriers' => is_array($preflight['segment_marketing_carriers'] ?? null) ? $preflight['segment_marketing_carriers'] : null,
            'command_pricing_carrier_count' => ($preflight['command_pricing_carrier_count'] ?? null) !== null
                ? (int) $preflight['command_pricing_carrier_count']
                : null,
            'command_pricing_carriers' => is_array($preflight['command_pricing_carriers'] ?? null) ? $preflight['command_pricing_carriers'] : null,
            'command_pricing_fare_basis_count' => ($preflight['command_pricing_fare_basis_count'] ?? null) !== null
                ? (int) $preflight['command_pricing_fare_basis_count']
                : null,
            'command_pricing_rbd_count' => ($preflight['command_pricing_rbd_count'] ?? null) !== null
                ? (int) $preflight['command_pricing_rbd_count']
                : null,
            'command_pricing_segment_ref_count' => ($preflight['command_pricing_segment_ref_count'] ?? null) !== null
                ? (int) $preflight['command_pricing_segment_ref_count']
                : null,
            'mixed_mapping_missing_reasons' => is_array($preflight['mixed_mapping_missing_reasons'] ?? null) ? $preflight['mixed_mapping_missing_reasons'] : null,
            'mixed_mapping_expected_carriers' => is_array($preflight['mixed_mapping_expected_carriers'] ?? null) ? $preflight['mixed_mapping_expected_carriers'] : null,
            'mixed_mapping_actual_carriers' => is_array($preflight['mixed_mapping_actual_carriers'] ?? null) ? $preflight['mixed_mapping_actual_carriers'] : null,
            'mixed_mapping_comparison_result' => is_string($preflight['mixed_mapping_comparison_result'] ?? null)
                ? $preflight['mixed_mapping_comparison_result']
                : null,
            'command_pricing_schema_valid' => ($preflight['command_pricing_schema_valid'] ?? null) === true ? true : (($preflight['command_pricing_schema_valid'] ?? null) === false ? false : null),
            'command_pricing_rejected_keys' => is_array($preflight['command_pricing_rejected_keys'] ?? null) ? $preflight['command_pricing_rejected_keys'] : null,
            'command_pricing_allowed_shape' => is_string($preflight['command_pricing_allowed_shape'] ?? null)
                ? $preflight['command_pricing_allowed_shape']
                : null,
            'command_pricing_wire_keys' => is_array($preflight['command_pricing_wire_keys'] ?? null) ? $preflight['command_pricing_wire_keys'] : null,
            'segment_select_present' => ($preflight['segment_select_present'] ?? null) === true ? true : (($preflight['segment_select_present'] ?? null) === false ? false : null),
            'segment_select_rph_count' => ($preflight['segment_select_rph_count'] ?? null) !== null
                ? (int) $preflight['segment_select_rph_count']
                : null,
            'segment_select_rph_values' => is_array($preflight['segment_select_rph_values'] ?? null) ? $preflight['segment_select_rph_values'] : null,
            'command_pricing_rph_values' => is_array($preflight['command_pricing_rph_values'] ?? null) ? $preflight['command_pricing_rph_values'] : null,
            'command_pricing_segmentselect_pairing_complete' => ($preflight['command_pricing_segmentselect_pairing_complete'] ?? null) === true
                ? true
                : (($preflight['command_pricing_segmentselect_pairing_complete'] ?? null) === false ? false : null),
            'command_pricing_segmentselect_missing_rph' => is_array($preflight['command_pricing_segmentselect_missing_rph'] ?? null)
                ? $preflight['command_pricing_segmentselect_missing_rph']
                : null,
            'brand_present' => ($preflight['brand_present'] ?? null) === true ? true : (($preflight['brand_present'] ?? null) === false ? false : null),
            'brand_code' => is_string($preflight['brand_code'] ?? null) ? $preflight['brand_code'] : null,
            'brand_rph_present' => ($preflight['brand_rph_present'] ?? null) === true ? true : (($preflight['brand_rph_present'] ?? null) === false ? false : null),
            'brand_rph_type' => is_string($preflight['brand_rph_type'] ?? null) ? $preflight['brand_rph_type'] : null,
            'brand_rph_values' => is_array($preflight['brand_rph_values'] ?? null) ? $preflight['brand_rph_values'] : null,
            'brand_rph_values_raw' => is_array($preflight['brand_rph_values_raw'] ?? null) ? $preflight['brand_rph_values_raw'] : null,
            'brand_rph_values_normalized' => is_array($preflight['brand_rph_values_normalized'] ?? null) ? $preflight['brand_rph_values_normalized'] : null,
            'brand_rph_schema_valid' => ($preflight['brand_rph_schema_valid'] ?? null) === true
                ? true
                : (($preflight['brand_rph_schema_valid'] ?? null) === false ? false : null),
            'brand_segmentselect_pairing_required' => ($preflight['brand_segmentselect_pairing_required'] ?? null) === true
                ? true
                : (($preflight['brand_segmentselect_pairing_required'] ?? null) === false ? false : null),
            'brand_segmentselect_pairing_complete' => ($preflight['brand_segmentselect_pairing_complete'] ?? null) === true
                ? true
                : (($preflight['brand_segmentselect_pairing_complete'] ?? null) === false ? false : null),
            'brand_segmentselect_pairing_values_match_normalized' => ($preflight['brand_segmentselect_pairing_values_match_normalized'] ?? null) === true
                ? true
                : (($preflight['brand_segmentselect_pairing_values_match_normalized'] ?? null) === false ? false : null),
            'brand_segmentselect_missing_rph' => is_array($preflight['brand_segmentselect_missing_rph'] ?? null)
                ? $preflight['brand_segmentselect_missing_rph']
                : null,
            'brand_schema_valid' => ($preflight['brand_schema_valid'] ?? null) === true
                ? true
                : (($preflight['brand_schema_valid'] ?? null) === false ? false : null),
            'brand_schema_rejected_pointer' => is_string($preflight['brand_schema_rejected_pointer'] ?? null)
                ? $preflight['brand_schema_rejected_pointer']
                : null,
            'brand_schema_rejected_message' => is_string($preflight['brand_schema_rejected_message'] ?? null)
                ? $preflight['brand_schema_rejected_message']
                : null,
            'brand_wire_shape' => is_string($preflight['brand_wire_shape'] ?? null) ? $preflight['brand_wire_shape'] : null,
            'brand_omitted_for_mixed_v24_segmentselect' => ($preflight['brand_omitted_for_mixed_v24_segmentselect'] ?? null) === true
                ? true
                : (($preflight['brand_omitted_for_mixed_v24_segmentselect'] ?? null) === false ? false : null),
            'brand_omission_reason' => is_string($preflight['brand_omission_reason'] ?? null)
                ? $preflight['brand_omission_reason']
                : null,
            'wire_command_pricing_count' => $wireCommandPricingCount > 0 ? $wireCommandPricingCount : null,
            'wire_fare_basis_count' => (int) ($preflight['wire_fare_basis_count'] ?? 0) ?: null,
            'wire_segment_count' => (int) ($preflight['wire_segment_count'] ?? 0) ?: null,
            'live_call_attempted' => false,
            'pnr_attempted' => false,
            'ticketing_attempted' => false,
            'airticket_attempted' => false,
            'selected_strategy' => null,
            'automatic_allowed' => false,
        ];

        if ($preflightPass) {
            return array_merge($common, [
                'payload_preflight_status' => self::PAYLOAD_PREFLIGHT_STATUS_PASS,
                'payload_preflight_block_reason' => null,
                'has_fare_basis' => true,
                'has_booking_class' => true,
                'has_validating_carrier' => true,
                'rbd_carrier_mapping_complete' => true,
                'mixed_fare_carrier_mapping_complete' => true,
                'no_fares_rbd_carrier_preflight_risk' => false,
                'selected_strategy_expected' => SabreGdsPnrCreateStrategyRegistry::STRATEGY_IATI_LIKE_CPNR_V2_4_GDS,
                'block_reason' => self::BLOCK_MIXED_CARRIER_PLAN_ONLY_READY,
                'automatic_block_reason' => self::BLOCK_MIXED_CARRIER_PLAN_ONLY_READY,
            ]);
        }

        $blockReason = $payloadBlockReason !== ''
            ? $payloadBlockReason
            : (! $schemaValid
                ? SabreGdsMixedCarrierFareBasisPayloadPreflight::REASON_V24_COMMANDPRICING_SCHEMA_INVALID
                : (! $pairingComplete
                    ? SabreGdsMixedCarrierFareBasisPayloadPreflight::REASON_V24_COMMANDPRICING_SEGMENTSELECT_PAIRING_MISSING
                    : (! $brandRphSchemaValid
                        ? SabreGdsMixedCarrierFareBasisPayloadPreflight::REASON_V24_BRAND_RPH_SCHEMA_INVALID
                        : (! $brandSegmentSafe
                            ? SabreGdsMixedCarrierFareBasisPayloadPreflight::REASON_V24_BRAND_SEGMENTSELECT_PAIRING_MISSING
                            : (! $comparisonMatch
                                ? SabreGdsMixedCarrierFareBasisPayloadPreflight::REASON_FARE_COMPONENT_CARRIER_MAPPING_UNAVAILABLE
                                : SabreGdsMixedCarrierFareBasisPayloadPreflight::REASON_PAYLOAD_MAPPING_INCOMPLETE)))));

        return array_merge($common, [
            'payload_preflight_status' => self::PAYLOAD_PREFLIGHT_STATUS_BLOCKED,
            'payload_preflight_block_reason' => $blockReason,
            'selected_strategy_expected' => null,
            'block_reason' => $blockReason,
            'automatic_block_reason' => $blockReason,
        ]);
    }

    protected function mixedCarrierSizeBlockReason(int $segmentCount, int $stops): ?string
    {
        if ($segmentCount > SabreGdsMixedCarrierCertificationGate::MAX_SEGMENTS) {
            return SabreGdsMixedCarrierCertificationGate::REASON_TOO_MANY_SEGMENTS;
        }
        if ($stops > SabreGdsMixedCarrierCertificationGate::MAX_STOPS) {
            return SabreGdsMixedCarrierCertificationGate::REASON_TOO_MANY_STOPS;
        }

        return null;
    }

    /**
     * @return array{
     *     selected_strategy: string|null,
     *     selected_strategy_expected: string|null,
     *     automatic_allowed: bool,
     *     block_reason: string|null,
     *     automatic_block_reason: string|null
     * }
     */
    protected function resolvePlanStrategyExpectation(
        string $tripType,
        bool $mixedCarrier,
        bool $publicAutoReady,
        bool $mixedCertApproved = false,
    ): array {
        if ($mixedCarrier || in_array($tripType, [
            SabreGdsOneWayTripShapeClassifier::TRIP_ONE_WAY_SINGLE_CONNECTION_MIXED_CARRIER,
            SabreGdsOneWayTripShapeClassifier::TRIP_ONE_WAY_MULTISTOP_MIXED_CARRIER,
            SabreGdsReturnTripClassifier::TRIP_RETURN_MIXED_CARRIER,
        ], true)) {
            if ($mixedCertApproved) {
                return [
                    'selected_strategy' => null,
                    'selected_strategy_expected' => null,
                    'automatic_allowed' => false,
                    'block_reason' => null,
                    'automatic_block_reason' => null,
                ];
            }

            return [
                'selected_strategy' => null,
                'selected_strategy_expected' => null,
                'automatic_allowed' => false,
                'block_reason' => self::BLOCK_MIXED_CARRIER_NOT_CERTIFIED,
                'automatic_block_reason' => self::BLOCK_MIXED_CARRIER_NOT_CERTIFIED,
            ];
        }

        if (in_array($tripType, [
            SabreGdsOneWayTripShapeClassifier::TRIP_ONE_WAY_THREE_STOP_SAME_CARRIER,
            SabreGdsOneWayTripShapeClassifier::TRIP_ONE_WAY_FOUR_STOP_SAME_CARRIER,
        ], true)) {
            return [
                'selected_strategy' => SabreGdsPnrCreateStrategyRegistry::STRATEGY_IATI_LIKE_CPNR_V2_4_GDS,
                'selected_strategy_expected' => SabreGdsPnrCreateStrategyRegistry::STRATEGY_IATI_LIKE_CPNR_V2_4_GDS,
                'automatic_allowed' => false,
                'block_reason' => self::BLOCK_ADVANCED_ITINERARY_PLAN_ONLY,
                'automatic_block_reason' => self::BLOCK_ADVANCED_ITINERARY_PLAN_ONLY,
            ];
        }

        $iatiEligible = $publicAutoReady && in_array($tripType, [
            SabreGdsOneWayTripShapeClassifier::TRIP_ONE_WAY_SINGLE_CONNECTION_SAME_CARRIER,
            SabreGdsOneWayTripShapeClassifier::TRIP_ONE_WAY_MULTISTOP_SAME_CARRIER,
            SabreGdsReturnTripClassifier::TRIP_RETURN_SAME_CARRIER,
            'one_way_direct',
            'one_way_connecting',
        ], true);

        return [
            'selected_strategy' => $iatiEligible
                ? SabreGdsPnrCreateStrategyRegistry::STRATEGY_IATI_LIKE_CPNR_V2_4_GDS
                : null,
            'selected_strategy_expected' => $iatiEligible
                ? SabreGdsPnrCreateStrategyRegistry::STRATEGY_IATI_LIKE_CPNR_V2_4_GDS
                : null,
            'automatic_allowed' => $iatiEligible,
            'block_reason' => $iatiEligible ? null : 'context_not_ready_or_shape_unknown',
            'automatic_block_reason' => $iatiEligible ? null : 'context_not_ready_or_shape_unknown',
        ];
    }
}
