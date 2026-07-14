<?php

namespace App\Support\Sabre\GdsPnrCreate;

use App\Models\Booking;
use App\Services\Suppliers\Sabre\Booking\SabreBookingPayloadBuilder;
use App\Services\Suppliers\Sabre\Booking\SabreBookingService;
use App\Services\Suppliers\Sabre\Gds\SabreFlightSearchNormalizer;
use App\Support\Bookings\SabreCertifiedRouteSelector;
use App\Support\Bookings\SabrePnrCertificationSupport;
use App\Support\Sabre\SabrePassengerRecordsPayloadDigest;
use App\Support\Security\SensitiveDataRedactor;

/**
 * Payload-level mixed-carrier preflight before live IATI v2.4 Passenger Records create (no HTTP, no raw payload / PII).
 */
final class SabreGdsMixedCarrierFareBasisPayloadPreflight
{
    public const META_PREFLIGHT_PROOF_KEY = 'mixed_carrier_preflight_proof';

    public const PAYLOAD_PREFLIGHT_STATUS_PASS = 'pass';

    public const PAYLOAD_PREFLIGHT_STATUS_BLOCKED = 'blocked';

    public const REASON_PAYLOAD_MAPPING_INCOMPLETE = 'mixed_carrier_fare_basis_payload_mapping_incomplete';

    public const REASON_PAYLOAD_MAPPING_UNAVAILABLE = 'mixed_carrier_fare_basis_payload_mapping_unavailable';

    public const REASON_FARE_COMPONENT_CARRIER_MAPPING_UNAVAILABLE = 'mixed_carrier_fare_component_carrier_mapping_unavailable';

    public const REASON_V24_COMMANDPRICING_SCHEMA_INVALID = 'mixed_carrier_v24_commandpricing_schema_invalid';

    public const REASON_V24_COMMANDPRICING_SEGMENTSELECT_PAIRING_MISSING = 'mixed_carrier_v24_commandpricing_segmentselect_pairing_missing';

    public const REASON_V24_BRAND_SEGMENTSELECT_PAIRING_MISSING = 'mixed_carrier_v24_brand_segmentselect_pairing_missing';

    public const REASON_V24_BRAND_RPH_SCHEMA_INVALID = 'mixed_carrier_v24_brand_rph_schema_invalid';

    public function __construct(
        protected SabreGdsMixedCarrierCertificationGate $certificationGate,
        protected SabreBookingService $bookingService,
        protected SabreBookingPayloadBuilder $payloadBuilder,
        protected SabrePassengerRecordsPayloadDigest $payloadDigest,
        protected SabrePnrCertificationSupport $certificationSupport,
        protected SabreCertifiedRouteSelector $routeSelector,
        protected SabreGdsAutoPnrContextCompletionService $contextCompletion,
        protected SabreFlightSearchNormalizer $flightSearchNormalizer,
    ) {}

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function evaluate(Booking $booking, array $context = []): array
    {
        $completion = is_array($context['completion'] ?? null)
            ? $context['completion']
            : $this->contextCompletion->completeForBooking($booking);
        $tripType = trim((string) ($context['trip_type'] ?? $this->certificationSupport->detectTripType($booking)));
        $selectedStrategy = trim((string) ($context['selected_strategy'] ?? ''));
        $endpointPath = trim((string) ($context['endpoint_path'] ?? SabreGdsMixedCarrierCertificationGate::ENDPOINT_IATI_CREATE));

        $gate = $this->certificationGate->evaluate($booking, array_merge($context, [
            'completion' => $completion,
            'trip_type' => $tripType,
            'selected_strategy' => $selectedStrategy,
            'endpoint_path' => $endpointPath,
        ]));

        $readiness = is_array($context['readiness'] ?? null)
            ? $context['readiness']
            : $this->certificationSupport->buildReadiness($booking);
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $handoff = is_array($meta['sabre_booking_context'] ?? null) ? $meta['sabre_booking_context'] : [];
        $routeSelection = $this->routeSelector->selectForBooking($booking);
        $segmentCount = (int) ($gate['segment_count'] ?? $readiness['segment_count'] ?? 0);
        $stops = max(0, $segmentCount - 1);
        $carriers = is_array($readiness['carrier_chain'] ?? null) ? array_values($readiness['carrier_chain']) : [];
        $marketingCarriers = $this->extractMarketingCarriers($booking, $readiness);
        $operatingCarriers = $this->extractOperatingCarriers($booking);

        $bookingClassCount = (int) ($gate['booking_classes_by_segment_count'] ?? 0);
        $fareBasisCount = (int) ($gate['fare_basis_codes_by_segment_count'] ?? 0);
        $cabinCount = (int) ($gate['cabin_by_segment_count'] ?? 0);

        $payloadReadiness = $this->assessPayloadReadiness($booking, $completion, $selectedStrategy);
        $hasFareBasis = ($payloadReadiness['has_fare_basis'] ?? false) === true;
        $hasBookingClass = ($payloadReadiness['has_booking_class'] ?? false) === true;
        $hasValidatingCarrier = ($payloadReadiness['has_validating_carrier'] ?? false) === true;
        $rbdCarrierMappingComplete = ($payloadReadiness['rbd_carrier_mapping_complete'] ?? false) === true;
        $mixedFareCarrierMappingComplete = ($payloadReadiness['mixed_fare_carrier_mapping_complete'] ?? false) === true;
        $noFaresRbdCarrierPreflightRisk = ($payloadReadiness['no_fares_rbd_carrier_preflight_risk'] ?? true) === true;
        $fareComponentCount = (int) ($payloadReadiness['fare_component_count'] ?? 0);
        $fareComponentCarrierCount = (int) ($payloadReadiness['fare_component_carrier_count'] ?? 0);
        $mappingUnavailable = ($payloadReadiness['mapping_unavailable'] ?? false) === true
            || ($payloadReadiness['fare_component_mapping_unavailable'] ?? false) === true;

        $blockReasons = is_array($gate['block_reasons'] ?? null) ? $gate['block_reasons'] : [];
        if (! $hasFareBasis) {
            $blockReasons[] = 'payload_has_fare_basis_false';
        }
        if (! $hasBookingClass) {
            $blockReasons[] = 'payload_has_booking_class_false';
        }
        if (! $hasValidatingCarrier) {
            $blockReasons[] = 'payload_has_validating_carrier_false';
        }
        if (! $rbdCarrierMappingComplete) {
            $blockReasons[] = 'rbd_carrier_mapping_incomplete';
        }
        if (! $mixedFareCarrierMappingComplete) {
            $blockReasons[] = 'mixed_fare_carrier_mapping_incomplete';
        }
        if (($payloadReadiness['mixed_mapping_comparison_result'] ?? null) !== 'match'
            && ($gate['mixed_carrier_detected'] ?? false) === true) {
            $blockReasons[] = 'mixed_carrier_mapping_comparison_not_match';
        }
        if ($noFaresRbdCarrierPreflightRisk) {
            $blockReasons[] = 'no_fares_rbd_carrier_preflight_risk';
        }
        if ($mappingUnavailable) {
            $blockReasons[] = in_array('fare_component_carrier_mapping_unavailable', $payloadReadiness['mixed_mapping_missing_reasons'] ?? [], true)
                ? self::REASON_FARE_COMPONENT_CARRIER_MAPPING_UNAVAILABLE
                : self::REASON_PAYLOAD_MAPPING_UNAVAILABLE;
        }
        if (($payloadReadiness['command_pricing_schema_valid'] ?? true) !== true
            && ($gate['mixed_carrier_detected'] ?? false) === true) {
            $blockReasons[] = self::REASON_V24_COMMANDPRICING_SCHEMA_INVALID;
        }
        if (($payloadReadiness['command_pricing_segmentselect_pairing_complete'] ?? true) !== true
            && ($gate['mixed_carrier_detected'] ?? false) === true) {
            $blockReasons[] = self::REASON_V24_COMMANDPRICING_SEGMENTSELECT_PAIRING_MISSING;
        }
        if (($payloadReadiness['brand_segmentselect_pairing_required'] ?? false) === true
            && ($payloadReadiness['brand_segmentselect_pairing_complete'] ?? true) !== true
            && ($payloadReadiness['brand_omitted_for_mixed_v24_segmentselect'] ?? false) !== true
            && ($gate['mixed_carrier_detected'] ?? false) === true) {
            $blockReasons[] = self::REASON_V24_BRAND_SEGMENTSELECT_PAIRING_MISSING;
        }
        if (($payloadReadiness['brand_schema_valid'] ?? true) !== true
            && ($payloadReadiness['brand_rph_schema_valid'] ?? true) === true
            && ($payloadReadiness['brand_present'] ?? false) === true
            && ($gate['mixed_carrier_detected'] ?? false) === true) {
            $blockReasons[] = self::REASON_V24_BRAND_SEGMENTSELECT_PAIRING_MISSING;
        }
        if (($payloadReadiness['brand_rph_schema_valid'] ?? true) !== true
            && ($payloadReadiness['brand_present'] ?? false) === true
            && ($gate['mixed_carrier_detected'] ?? false) === true) {
            $blockReasons[] = self::REASON_V24_BRAND_RPH_SCHEMA_INVALID;
        }
        if ($selectedStrategy !== '' && $selectedStrategy !== SabreGdsPnrCreateStrategyRegistry::STRATEGY_IATI_LIKE_CPNR_V2_4_GDS) {
            $blockReasons[] = SabreGdsMixedCarrierCertificationGate::REASON_STRATEGY_NOT_IATI;
        }
        if ($endpointPath !== SabreGdsMixedCarrierCertificationGate::ENDPOINT_IATI_CREATE) {
            $blockReasons[] = 'endpoint_not_iati_v2_4_create';
        }

        $blockReasons = array_values(array_unique($blockReasons));
        $gateAllowed = ($gate['allowed'] ?? false) === true;
        $allowed = $gateAllowed && $blockReasons === [];

        $primaryBlock = null;
        if (! $allowed) {
            if (($payloadReadiness['command_pricing_schema_valid'] ?? true) !== true
                && ($gate['mixed_carrier_detected'] ?? false) === true) {
                $primaryBlock = self::REASON_V24_COMMANDPRICING_SCHEMA_INVALID;
            } elseif (($payloadReadiness['command_pricing_segmentselect_pairing_complete'] ?? true) !== true
                && ($gate['mixed_carrier_detected'] ?? false) === true) {
                $primaryBlock = self::REASON_V24_COMMANDPRICING_SEGMENTSELECT_PAIRING_MISSING;
            } elseif (($payloadReadiness['brand_rph_schema_valid'] ?? true) !== true
                && ($payloadReadiness['brand_present'] ?? false) === true
                && ($gate['mixed_carrier_detected'] ?? false) === true) {
                $primaryBlock = self::REASON_V24_BRAND_RPH_SCHEMA_INVALID;
            } elseif (
                (
                    (($payloadReadiness['brand_segmentselect_pairing_required'] ?? false) === true
                        && ($payloadReadiness['brand_segmentselect_pairing_complete'] ?? true) !== true
                        && ($payloadReadiness['brand_omitted_for_mixed_v24_segmentselect'] ?? false) !== true)
                    || (($payloadReadiness['brand_schema_valid'] ?? true) !== true
                        && ($payloadReadiness['brand_rph_schema_valid'] ?? true) === true
                        && ($payloadReadiness['brand_present'] ?? false) === true)
                )
                && ($gate['mixed_carrier_detected'] ?? false) === true
            ) {
                $primaryBlock = self::REASON_V24_BRAND_SEGMENTSELECT_PAIRING_MISSING;
            } elseif ($mappingUnavailable) {
                $primaryBlock = in_array('fare_component_carrier_mapping_unavailable', $payloadReadiness['mixed_mapping_missing_reasons'] ?? [], true)
                    ? self::REASON_FARE_COMPONENT_CARRIER_MAPPING_UNAVAILABLE
                    : self::REASON_PAYLOAD_MAPPING_UNAVAILABLE;
            } elseif (! $hasFareBasis || ! $rbdCarrierMappingComplete || ! $mixedFareCarrierMappingComplete || $noFaresRbdCarrierPreflightRisk) {
                $primaryBlock = self::REASON_PAYLOAD_MAPPING_INCOMPLETE;
            } else {
                $primaryBlock = $gate['block_reason'] ?? ($blockReasons[0] ?? self::REASON_PAYLOAD_MAPPING_INCOMPLETE);
            }
        }

        $strategyForSummary = $allowed
            ? SabreGdsPnrCreateStrategyRegistry::STRATEGY_IATI_LIKE_CPNR_V2_4_GDS
            : null;

        return SensitiveDataRedactor::redact([
            'allowed' => $allowed,
            'block_reason' => $primaryBlock,
            'block_reasons' => $blockReasons,
            'error_code' => $primaryBlock,
            'mixed_carrier_detected' => ($gate['mixed_carrier_detected'] ?? false) === true,
            'marketing_carriers' => $marketingCarriers !== [] ? $marketingCarriers : null,
            'operating_carriers' => $operatingCarriers !== [] ? $operatingCarriers : null,
            'carrier_chain' => $carriers !== [] ? $carriers : null,
            'carrier_chain_count' => count($carriers) > 0 ? count($carriers) : null,
            'validating_carrier' => strtoupper(trim((string) ($gate['validating_carrier'] ?? $readiness['validating_carrier'] ?? ''))) ?: null,
            'mixed_carrier_certification_approved' => ($gate['mixed_carrier_certification_approved'] ?? false) === true,
            'mixed_carrier_certification_scope' => SabreGdsMixedCarrierCertificationGate::SCOPE,
            'trip_type' => $tripType,
            'category' => (string) ($routeSelection['category'] ?? SabreCertifiedRouteSelector::CATEGORY_UNKNOWN),
            'route_shape' => (string) ($routeSelection['route_shape'] ?? ''),
            'selected_strategy' => $strategyForSummary,
            'pnr_strategy_used' => null,
            'payload_schema' => $strategyForSummary,
            'endpoint_path' => SabreGdsMixedCarrierCertificationGate::ENDPOINT_IATI_CREATE,
            'segment_count' => $segmentCount,
            'stops' => $stops,
            'segment_rows_count' => (int) ($gate['segment_rows_count'] ?? $segmentCount),
            'schedule_refs_count' => (int) ($gate['schedule_refs_count'] ?? count(is_array($handoff['schedule_refs'] ?? null) ? $handoff['schedule_refs'] : [])),
            'leg_refs_count' => count(is_array($handoff['leg_refs'] ?? null) ? $handoff['leg_refs'] : []),
            'booking_classes_by_segment_count' => $bookingClassCount,
            'fare_basis_codes_by_segment_count' => $fareBasisCount,
            'cabin_by_segment_count' => $cabinCount,
            'has_fare_basis' => $hasFareBasis,
            'has_booking_class' => $hasBookingClass,
            'has_validating_carrier' => $hasValidatingCarrier,
            'fare_component_count' => $fareComponentCount > 0 ? $fareComponentCount : null,
            'fare_component_carrier_count' => $fareComponentCarrierCount > 0 ? $fareComponentCarrierCount : null,
            'rbd_carrier_mapping_complete' => $rbdCarrierMappingComplete,
            'mixed_fare_carrier_mapping_complete' => $mixedFareCarrierMappingComplete,
            'no_fares_rbd_carrier_preflight_risk' => $noFaresRbdCarrierPreflightRisk,
            'live_call_attempted' => false,
            'pnr_attempted' => false,
            'ticketing_attempted' => false,
            'airticket_attempted' => false,
            'automatic_allowed' => $allowed,
            'payload_wire_build_valid' => ($payloadReadiness['wire_build_valid'] ?? false) === true,
            'payload_command_pricing_segment_count' => (int) ($payloadReadiness['command_pricing_segment_count'] ?? 0),
            'wire_command_pricing_count' => (int) ($payloadReadiness['wire_command_pricing_count'] ?? 0) ?: null,
            'wire_fare_basis_count' => (int) ($payloadReadiness['wire_fare_basis_count'] ?? 0) ?: null,
            'wire_segment_count' => (int) ($payloadReadiness['wire_segment_count'] ?? 0) ?: null,
            'fare_component_carriers' => $payloadReadiness['fare_component_carriers'] ?? null,
            'segment_marketing_carrier_count' => ($payloadReadiness['segment_marketing_carrier_count'] ?? null) !== null
                ? (int) $payloadReadiness['segment_marketing_carrier_count']
                : null,
            'segment_marketing_carriers' => $payloadReadiness['segment_marketing_carriers'] ?? null,
            'command_pricing_carrier_count' => ($payloadReadiness['command_pricing_carrier_count'] ?? null) !== null
                ? (int) $payloadReadiness['command_pricing_carrier_count']
                : null,
            'command_pricing_carriers' => $payloadReadiness['command_pricing_carriers'] ?? null,
            'command_pricing_fare_basis_count' => ($payloadReadiness['command_pricing_fare_basis_count'] ?? null) !== null
                ? (int) $payloadReadiness['command_pricing_fare_basis_count']
                : null,
            'command_pricing_rbd_count' => ($payloadReadiness['command_pricing_rbd_count'] ?? null) !== null
                ? (int) $payloadReadiness['command_pricing_rbd_count']
                : null,
            'command_pricing_segment_ref_count' => ($payloadReadiness['command_pricing_segment_ref_count'] ?? null) !== null
                ? (int) $payloadReadiness['command_pricing_segment_ref_count']
                : null,
            'mixed_mapping_missing_reasons' => $payloadReadiness['mixed_mapping_missing_reasons'] ?? null,
            'mixed_mapping_expected_carriers' => $payloadReadiness['mixed_mapping_expected_carriers'] ?? null,
            'mixed_mapping_actual_carriers' => $payloadReadiness['mixed_mapping_actual_carriers'] ?? null,
            'mixed_mapping_comparison_result' => $payloadReadiness['mixed_mapping_comparison_result'] ?? null,
            'command_pricing_schema_valid' => ($payloadReadiness['command_pricing_schema_valid'] ?? null) === true ? true : (($payloadReadiness['command_pricing_schema_valid'] ?? null) === false ? false : null),
            'command_pricing_rejected_keys' => $payloadReadiness['command_pricing_rejected_keys'] ?? null,
            'command_pricing_allowed_shape' => $payloadReadiness['command_pricing_allowed_shape'] ?? null,
            'command_pricing_wire_keys' => $payloadReadiness['command_pricing_wire_keys'] ?? null,
            'segment_select_present' => ($payloadReadiness['segment_select_present'] ?? false) === true,
            'segment_select_rph_count' => ($payloadReadiness['segment_select_rph_count'] ?? null) !== null
                ? (int) $payloadReadiness['segment_select_rph_count']
                : null,
            'segment_select_rph_values' => $payloadReadiness['segment_select_rph_values'] ?? null,
            'command_pricing_rph_values' => $payloadReadiness['command_pricing_rph_values'] ?? null,
            'command_pricing_segmentselect_pairing_complete' => ($payloadReadiness['command_pricing_segmentselect_pairing_complete'] ?? null) === true
                ? true
                : (($payloadReadiness['command_pricing_segmentselect_pairing_complete'] ?? null) === false ? false : null),
            'command_pricing_segmentselect_missing_rph' => $payloadReadiness['command_pricing_segmentselect_missing_rph'] ?? null,
            'brand_present' => ($payloadReadiness['brand_present'] ?? null) === true ? true : (($payloadReadiness['brand_present'] ?? null) === false ? false : null),
            'brand_code' => $payloadReadiness['brand_code'] ?? null,
            'brand_rph_present' => ($payloadReadiness['brand_rph_present'] ?? null) === true ? true : (($payloadReadiness['brand_rph_present'] ?? null) === false ? false : null),
            'brand_rph_type' => $payloadReadiness['brand_rph_type'] ?? null,
            'brand_rph_values' => $payloadReadiness['brand_rph_values'] ?? null,
            'brand_rph_values_raw' => $payloadReadiness['brand_rph_values_raw'] ?? null,
            'brand_rph_values_normalized' => $payloadReadiness['brand_rph_values_normalized'] ?? null,
            'brand_rph_schema_valid' => ($payloadReadiness['brand_rph_schema_valid'] ?? null) === true ? true : (($payloadReadiness['brand_rph_schema_valid'] ?? null) === false ? false : null),
            'brand_segmentselect_pairing_required' => ($payloadReadiness['brand_segmentselect_pairing_required'] ?? null) === true
                ? true
                : (($payloadReadiness['brand_segmentselect_pairing_required'] ?? null) === false ? false : null),
            'brand_segmentselect_pairing_complete' => ($payloadReadiness['brand_segmentselect_pairing_complete'] ?? null) === true
                ? true
                : (($payloadReadiness['brand_segmentselect_pairing_complete'] ?? null) === false ? false : null),
            'brand_segmentselect_pairing_values_match_normalized' => ($payloadReadiness['brand_segmentselect_pairing_values_match_normalized'] ?? null) === true
                ? true
                : (($payloadReadiness['brand_segmentselect_pairing_values_match_normalized'] ?? null) === false ? false : null),
            'brand_segmentselect_missing_rph' => $payloadReadiness['brand_segmentselect_missing_rph'] ?? null,
            'brand_wire_keys' => $payloadReadiness['brand_wire_keys'] ?? null,
            'brand_wire_shape' => $payloadReadiness['brand_wire_shape'] ?? null,
            'brand_schema_valid' => ($payloadReadiness['brand_schema_valid'] ?? null) === true ? true : (($payloadReadiness['brand_schema_valid'] ?? null) === false ? false : null),
            'brand_schema_rejected_pointer' => $payloadReadiness['brand_schema_rejected_pointer'] ?? null,
            'brand_schema_rejected_message' => $payloadReadiness['brand_schema_rejected_message'] ?? null,
            'brand_omitted_for_mixed_v24_segmentselect' => ($payloadReadiness['brand_omitted_for_mixed_v24_segmentselect'] ?? null) === true ? true : (($payloadReadiness['brand_omitted_for_mixed_v24_segmentselect'] ?? null) === false ? false : null),
            'brand_omission_reason' => $payloadReadiness['brand_omission_reason'] ?? null,
            'payload_preflight_status' => $allowed ? self::PAYLOAD_PREFLIGHT_STATUS_PASS : self::PAYLOAD_PREFLIGHT_STATUS_BLOCKED,
        ]);
    }

    /**
     * @return list<string>
     */
    public function attemptProofKeys(): array
    {
        return [
            'mixed_mapping_comparison_result',
            'command_pricing_schema_valid',
            'command_pricing_allowed_shape',
            'command_pricing_rejected_keys',
            'payload_preflight_status',
            'command_pricing_segmentselect_pairing_complete',
            'segment_select_rph_values',
            'command_pricing_rph_values',
            'brand_present',
            'brand_code',
            'brand_rph_present',
            'brand_rph_type',
            'brand_rph_values',
            'brand_rph_values_normalized',
            'brand_rph_schema_valid',
            'brand_segmentselect_pairing_required',
            'brand_segmentselect_pairing_complete',
            'brand_segmentselect_pairing_values_match_normalized',
            'brand_schema_valid',
            'brand_omitted_for_mixed_v24_segmentselect',
            'mixed_fare_carrier_mapping_complete',
            'no_fares_rbd_carrier_preflight_risk',
            'segment_marketing_carriers',
            'command_pricing_carriers',
            'selected_payload_style',
        ];
    }

    /**
     * Safe mixed-carrier preflight proof for attempt safe_summary (no live-call flags).
     *
     * @param  array<string, mixed>  $evaluation
     * @return array<string, mixed>
     */
    public function attemptProofSlice(array $evaluation): array
    {
        $slice = array_intersect_key($evaluation, array_flip($this->attemptProofKeys()));
        if (! isset($slice['selected_payload_style'])) {
            $style = trim((string) ($evaluation['selected_strategy'] ?? $evaluation['payload_schema'] ?? ''));
            if ($style !== '') {
                $slice['selected_payload_style'] = $style;
            }
        }

        return $slice;
    }

    /**
     * @return list<string>
     */
    public function safeSummaryKeys(): array
    {
        return [
            'mixed_carrier_detected',
            'marketing_carriers',
            'operating_carriers',
            'carrier_chain',
            'carrier_chain_count',
            'validating_carrier',
            'mixed_carrier_certification_approved',
            'mixed_carrier_certification_scope',
            'trip_type',
            'category',
            'route_shape',
            'selected_strategy',
            'pnr_strategy_used',
            'payload_schema',
            'endpoint_path',
            'segment_count',
            'stops',
            'segment_rows_count',
            'schedule_refs_count',
            'leg_refs_count',
            'booking_classes_by_segment_count',
            'fare_basis_codes_by_segment_count',
            'cabin_by_segment_count',
            'has_fare_basis',
            'has_booking_class',
            'has_validating_carrier',
            'fare_component_count',
            'fare_component_carrier_count',
            'fare_component_carriers',
            'segment_marketing_carrier_count',
            'segment_marketing_carriers',
            'command_pricing_carrier_count',
            'command_pricing_carriers',
            'command_pricing_fare_basis_count',
            'command_pricing_rbd_count',
            'command_pricing_segment_ref_count',
            'mixed_mapping_missing_reasons',
            'mixed_mapping_expected_carriers',
            'mixed_mapping_actual_carriers',
            'mixed_mapping_comparison_result',
            'command_pricing_schema_valid',
            'command_pricing_rejected_keys',
            'command_pricing_allowed_shape',
            'command_pricing_wire_keys',
            'segment_select_present',
            'segment_select_rph_count',
            'segment_select_rph_values',
            'command_pricing_rph_values',
            'command_pricing_segmentselect_pairing_complete',
            'command_pricing_segmentselect_missing_rph',
            'brand_present',
            'brand_code',
            'brand_rph_present',
            'brand_rph_type',
            'brand_rph_values',
            'brand_rph_values_raw',
            'brand_rph_values_normalized',
            'brand_rph_schema_valid',
            'brand_segmentselect_pairing_required',
            'brand_segmentselect_pairing_complete',
            'brand_segmentselect_pairing_values_match_normalized',
            'brand_segmentselect_missing_rph',
            'brand_wire_keys',
            'brand_wire_shape',
            'brand_schema_valid',
            'brand_schema_rejected_pointer',
            'brand_schema_rejected_message',
            'brand_omitted_for_mixed_v24_segmentselect',
            'brand_omission_reason',
            'payload_preflight_status',
            'wire_command_pricing_count',
            'wire_fare_basis_count',
            'wire_segment_count',
            'rbd_carrier_mapping_complete',
            'mixed_fare_carrier_mapping_complete',
            'no_fares_rbd_carrier_preflight_risk',
            'live_call_attempted',
            'pnr_attempted',
            'ticketing_attempted',
            'airticket_attempted',
            'block_reason',
            'block_reasons',
            'error_code',
            'automatic_allowed',
        ];
    }

    /**
     * @param  array<string, mixed>  $evaluation
     * @return array<string, mixed>
     */
    public function safeSummarySlice(array $evaluation): array
    {
        return array_intersect_key($evaluation, array_flip($this->safeSummaryKeys()));
    }

    /**
     * @param  array<string, mixed>  $completion
     * @return array<string, mixed>
     */
    protected function assessPayloadReadiness(Booking $booking, array $completion, string $selectedStrategy): array
    {
        $strategyCode = $selectedStrategy !== ''
            ? $selectedStrategy
            : SabreGdsPnrCreateStrategyRegistry::STRATEGY_IATI_LIKE_CPNR_V2_4_GDS;

        $metaOverlay = $this->contextCompletion->scenarioRunnerStrategyMetaOverlay($booking, $completion);
        $wireContext = $this->bookingService->buildGdsPnrStrategyWireContext($booking, $metaOverlay);
        if (($wireContext['valid'] ?? false) !== true) {
            return [
                'wire_build_valid' => false,
                'mapping_unavailable' => true,
                'has_fare_basis' => false,
                'has_booking_class' => false,
                'has_validating_carrier' => false,
                'rbd_carrier_mapping_complete' => false,
                'mixed_fare_carrier_mapping_complete' => false,
                'no_fares_rbd_carrier_preflight_risk' => true,
                'fare_component_count' => 0,
                'fare_component_carrier_count' => 0,
                'command_pricing_segment_count' => 0,
                'wire_command_pricing_count' => 0,
                'wire_fare_basis_count' => 0,
                'wire_segment_count' => 0,
            ];
        }

        $apiDraft = is_array($wireContext['api_draft'] ?? null) ? $wireContext['api_draft'] : [];
        $hints = is_array($wireContext['hints'] ?? null) ? $wireContext['hints'] : [];
        $meta = is_array($wireContext['meta'] ?? null) ? $wireContext['meta'] : [];
        $snapshot = is_array($wireContext['snapshot'] ?? null) ? $wireContext['snapshot'] : [];
        $wireStyle = SabreGdsPnrCreateStrategyRegistry::STRATEGY_IATI_LIKE_CPNR_V2_4_GDS;
        $rawWire = $this->payloadBuilder->buildPassengerRecordsCpnrWireForStyle($apiDraft, $hints, $wireStyle);
        $wire = $this->payloadBuilder->stripOtaInternalKeysFromBookingWire($rawWire);
        $tradDiag = $this->payloadBuilder->summarizeTraditionalPnrWirePostBody(
            $wire,
            $meta,
            $strategyCode,
        );

        $segmentCount = max(0, (int) ($tradDiag['wire_segment_count'] ?? count($snapshot['segments'] ?? [])));
        $handoff = is_array($meta['sabre_booking_context'] ?? null) ? $meta['sabre_booking_context'] : [];
        $fareBasisBySeg = is_array($handoff['fare_basis_codes_by_segment'] ?? null)
            ? array_values($handoff['fare_basis_codes_by_segment'])
            : (is_array($completion['completed_fare_basis_codes_by_segment'] ?? null)
                ? array_values($completion['completed_fare_basis_codes_by_segment'])
                : []);
        $bookingClassBySeg = is_array($handoff['booking_classes_by_segment'] ?? null)
            ? array_values($handoff['booking_classes_by_segment'])
            : (is_array($completion['completed_booking_classes_by_segment'] ?? null)
                ? array_values($completion['completed_booking_classes_by_segment'])
                : []);

        $mappingUnavailable = $segmentCount > 0 && (
            count(array_filter($fareBasisBySeg, static fn ($v): bool => trim((string) $v) !== '')) < $segmentCount
            || count(array_filter($bookingClassBySeg, static fn ($v): bool => trim((string) $v) !== '')) < $segmentCount
        );

        $snapshotSegs = array_values(is_array($snapshot['segments'] ?? null) ? $snapshot['segments'] : []);
        $apiDraftSegs = array_values(is_array($apiDraft['segments'] ?? null) ? $apiDraft['segments'] : []);
        $marketingCarrierChain = is_array($snapshot['marketing_carrier_chain'] ?? null)
            ? array_values($snapshot['marketing_carrier_chain'])
            : [];
        $segSell = $this->payloadBuilder->traditionalPnrAirBookSegmentSellDiagnostics($wire, $snapshotSegs);
        $sellRows = is_array($segSell['segments'] ?? null) ? $segSell['segments'] : [];
        $commandPricingCount = $this->countCommandPricingFareBasisRows($wire);

        $hasFareBasis = $segmentCount > 0 && $commandPricingCount >= $segmentCount;
        $hasBookingClass = (bool) ($tradDiag['wire_flight_segment_has_res_book_desig_code'] ?? false);
        $hasValidatingCarrier = (bool) ($tradDiag['validating_carrier_present'] ?? false)
            || (bool) ($tradDiag['wire_airprice_has_validating_carrier'] ?? false);

        $digestContext = [
            'endpoint_path' => SabreGdsMixedCarrierCertificationGate::ENDPOINT_IATI_CREATE,
            'payload_schema' => $strategyCode,
            'payload_style' => $wireStyle,
            'api_draft' => $apiDraft,
            'booking_meta' => $meta,
            'validating_carrier' => $handoff['validating_carrier'] ?? $apiDraft['validating_carrier'] ?? null,
            'selected_context_segments' => $snapshotSegs,
            'passenger_count' => count(is_array($apiDraft['passengers'] ?? null) ? $apiDraft['passengers'] : []),
        ];
        $digest = $this->payloadDigest->digest($wire, $digestContext);
        $digestSummary = $this->payloadDigest->commandSummaryFromDigest($digest);

        $rbdComplete = $segmentCount > 0
            && count(array_filter($bookingClassBySeg, static fn ($v): bool => trim((string) $v) !== '')) >= $segmentCount
            && $this->sellRowsHaveCompleteRbd($sellRows, $segmentCount)
            && $this->sellRowsPreserveCarrierChain($sellRows, $snapshotSegs);

        $uniqueFareBasis = array_values(array_unique(array_filter(array_map(
            static fn ($v): string => strtoupper(trim((string) $v)),
            $fareBasisBySeg,
        ), static fn (string $v): bool => $v !== '')));
        $uniqueCarriers = array_values(array_unique(array_filter(
            $this->payloadBuilder->resolveMixedCarrierMappingExpectedCarriers(
                $snapshotSegs,
                $apiDraftSegs,
                [],
                $marketingCarrierChain,
            )['expected_carriers'] ?? [],
            static fn (string $v): bool => $v !== '',
        )));
        $isMixedCarrierTrip = count($uniqueCarriers) > 1;

        $fareComponentRows = $this->flightSearchNormalizer->fareComponentBookingSegmentRowsFromOfferSnapshot($snapshot, $meta);
        $airbookSellCarriers = array_values(array_map(
            static fn (array $row): string => strtoupper(trim((string) ($row['marketing_airline'] ?? ''))),
            $sellRows,
        ));
        $airbookSellRbds = array_values(array_map(
            static fn (array $row): string => strtoupper(trim((string) ($row['res_book_desig_code'] ?? ''))),
            $sellRows,
        ));
        $mappingDiag = $this->payloadBuilder->summarizeIatiMixedCarrierCommandPricingMapping(
            $wire,
            $snapshotSegs,
            $fareComponentRows,
            [
                'api_draft_segments' => $apiDraftSegs,
                'marketing_carrier_chain' => $marketingCarrierChain,
                'airbook_sell_carriers' => $airbookSellCarriers,
                'airbook_sell_rbds' => $airbookSellRbds,
            ],
        );

        $fareComponentCount = (int) ($mappingDiag['fare_component_count'] ?? max(count($uniqueFareBasis), $commandPricingCount));
        $fareComponentCarrierCount = (int) ($mappingDiag['fare_component_carrier_count'] ?? count($uniqueCarriers));
        $commandPricingSchemaValid = ($mappingDiag['command_pricing_schema_valid'] ?? false) === true;
        $pairingComplete = ($mappingDiag['command_pricing_segmentselect_pairing_complete'] ?? false) === true;

        $resolvedBrandCode = $this->payloadBuilder->resolveBrandCodeFromInternalDraftForInspect($apiDraft);
        if ($resolvedBrandCode === null) {
            $handoffBrand = strtoupper(trim((string) ($handoff['selected_brand_code'] ?? $handoff['brand_code'] ?? '')));
            if ($handoffBrand !== '' && preg_match('/^[A-Z0-9]{2,16}$/', $handoffBrand) === 1) {
                $resolvedBrandCode = $handoffBrand;
            }
        }
        $brandDiag = $this->payloadBuilder->inspectIatiV24BrandSegmentSelectPairing($wire, $resolvedBrandCode);
        $brandSchemaValid = ($brandDiag['brand_schema_valid'] ?? false) === true;
        $brandRphSchemaValid = ($brandDiag['brand_rph_schema_valid'] ?? false) === true;
        $brandPairingComplete = ($brandDiag['brand_segmentselect_pairing_complete'] ?? false) === true;
        $brandPairingValuesMatch = ($brandDiag['brand_segmentselect_pairing_values_match_normalized'] ?? false) === true;
        $brandOmitted = ($brandDiag['brand_omitted_for_mixed_v24_segmentselect'] ?? false) === true;
        $brandSegmentSafe = $brandOmitted
            || (
                $brandSchemaValid
                && $brandRphSchemaValid
                && (
                    ($brandDiag['brand_segmentselect_pairing_required'] ?? false) !== true
                    || ($brandPairingComplete && $brandPairingValuesMatch)
                )
            );

        $mappingComparisonMatch = ($mappingDiag['mixed_mapping_comparison_result'] ?? null) === 'match';
        $mixedFareComplete = ! $isMixedCarrierTrip || (
            ($mappingDiag['mixed_fare_carrier_mapping_complete'] ?? false) === true
            && $mappingComparisonMatch
            && $commandPricingSchemaValid
            && $pairingComplete
            && $brandSegmentSafe
        );
        $fareComponentMappingUnavailable = $isMixedCarrierTrip
            && ($mappingDiag['mapping_unavailable'] ?? false) === true;

        $hardReasons = is_array($digest['hard_no_fares_rbd_carrier_risk_reasons'] ?? null)
            ? array_values($digest['hard_no_fares_rbd_carrier_risk_reasons'])
            : [];
        $fareRbdCarrierHardReasons = array_values(array_intersect($hardReasons, [
            'missing_rbd',
            'missing_marketing_carrier',
            'missing_operating_carrier',
            'missing_flight_number',
            'rbd_context_payload_mismatch',
            'carrier_context_payload_mismatch',
            'airprice_missing_validating_carrier',
            'validating_carrier_mismatch',
        ]));
        $noFaresRisk = ! $mappingUnavailable && ! $fareComponentMappingUnavailable && (
            ! $hasFareBasis
            || ! $rbdComplete
            || ($isMixedCarrierTrip && ! $mixedFareComplete)
            || ($isMixedCarrierTrip && ! $commandPricingSchemaValid)
            || ($isMixedCarrierTrip && ! $pairingComplete)
            || ($isMixedCarrierTrip && ! $brandSegmentSafe)
            || $fareRbdCarrierHardReasons !== []
        );

        return [
            'wire_build_valid' => true,
            'mapping_unavailable' => $mappingUnavailable,
            'fare_component_mapping_unavailable' => $fareComponentMappingUnavailable,
            'has_fare_basis' => $hasFareBasis,
            'has_booking_class' => $hasBookingClass,
            'has_validating_carrier' => $hasValidatingCarrier,
            'rbd_carrier_mapping_complete' => $rbdComplete,
            'mixed_fare_carrier_mapping_complete' => $mixedFareComplete,
            'no_fares_rbd_carrier_preflight_risk' => $noFaresRisk,
            'fare_component_count' => $fareComponentCount,
            'fare_component_carrier_count' => $fareComponentCarrierCount,
            'command_pricing_segment_count' => $commandPricingCount,
            'wire_command_pricing_count' => $commandPricingCount,
            'wire_fare_basis_count' => (int) ($tradDiag['wire_fare_basis_count'] ?? $commandPricingCount),
            'wire_segment_count' => $segmentCount,
            'fare_component_carriers' => $mappingDiag['fare_component_carriers'] ?? null,
            'segment_marketing_carrier_count' => $mappingDiag['segment_marketing_carrier_count'] ?? count($snapshotSegs),
            'segment_marketing_carriers' => $mappingDiag['segment_marketing_carriers'] ?? null,
            'command_pricing_carrier_count' => $mappingDiag['command_pricing_carrier_count'] ?? null,
            'command_pricing_carriers' => $mappingDiag['command_pricing_carriers'] ?? null,
            'command_pricing_fare_basis_count' => $mappingDiag['command_pricing_fare_basis_count'] ?? null,
            'command_pricing_rbd_count' => $mappingDiag['command_pricing_rbd_count'] ?? null,
            'command_pricing_segment_ref_count' => $mappingDiag['command_pricing_segment_ref_count'] ?? null,
            'mixed_mapping_missing_reasons' => $mappingDiag['mixed_mapping_missing_reasons'] ?? null,
            'mixed_mapping_expected_carriers' => $mappingDiag['mixed_mapping_expected_carriers'] ?? null,
            'mixed_mapping_actual_carriers' => $mappingDiag['mixed_mapping_actual_carriers'] ?? null,
            'mixed_mapping_comparison_result' => $mappingDiag['mixed_mapping_comparison_result'] ?? null,
            'command_pricing_schema_valid' => $commandPricingSchemaValid,
            'command_pricing_rejected_keys' => $mappingDiag['command_pricing_rejected_keys'] ?? null,
            'command_pricing_allowed_shape' => $mappingDiag['command_pricing_allowed_shape'] ?? null,
            'command_pricing_wire_keys' => $mappingDiag['command_pricing_wire_keys'] ?? null,
            'segment_select_present' => ($mappingDiag['segment_select_present'] ?? false) === true,
            'segment_select_rph_count' => (int) ($mappingDiag['segment_select_rph_count'] ?? 0),
            'segment_select_rph_values' => $mappingDiag['segment_select_rph_values'] ?? null,
            'command_pricing_rph_values' => $mappingDiag['command_pricing_rph_values'] ?? null,
            'command_pricing_segmentselect_pairing_complete' => $pairingComplete,
            'command_pricing_segmentselect_missing_rph' => $mappingDiag['command_pricing_segmentselect_missing_rph'] ?? null,
            'brand_present' => ($brandDiag['brand_present'] ?? false) === true,
            'brand_code' => $brandDiag['brand_code'] ?? null,
            'brand_rph_present' => ($brandDiag['brand_rph_present'] ?? false) === true,
            'brand_rph_type' => $brandDiag['brand_rph_type'] ?? null,
            'brand_rph_values' => $brandDiag['brand_rph_values'] ?? null,
            'brand_rph_values_raw' => $brandDiag['brand_rph_values_raw'] ?? null,
            'brand_rph_values_normalized' => $brandDiag['brand_rph_values_normalized'] ?? null,
            'brand_rph_schema_valid' => $brandRphSchemaValid,
            'brand_segmentselect_pairing_required' => ($brandDiag['brand_segmentselect_pairing_required'] ?? false) === true,
            'brand_segmentselect_pairing_complete' => $brandPairingComplete,
            'brand_segmentselect_pairing_values_match_normalized' => $brandPairingValuesMatch,
            'brand_segmentselect_missing_rph' => $brandDiag['brand_segmentselect_missing_rph'] ?? null,
            'brand_wire_keys' => $brandDiag['brand_wire_keys'] ?? null,
            'brand_wire_shape' => $brandDiag['brand_wire_shape'] ?? null,
            'brand_schema_valid' => $brandSchemaValid,
            'brand_schema_rejected_pointer' => $brandDiag['brand_schema_rejected_pointer'] ?? null,
            'brand_schema_rejected_message' => $brandDiag['brand_schema_rejected_message'] ?? null,
            'brand_omitted_for_mixed_v24_segmentselect' => $brandOmitted,
            'brand_omission_reason' => $brandDiag['brand_omission_reason'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $readiness
     * @return list<string>
     */
    protected function extractMarketingCarriers(Booking $booking, array $readiness): array
    {
        $carriers = is_array($readiness['carrier_chain'] ?? null) ? $readiness['carrier_chain'] : [];
        if ($carriers !== []) {
            return array_values(array_unique(array_map(
                static fn ($c): string => strtoupper(trim((string) $c)),
                $carriers,
            )));
        }

        $meta = is_array($booking->meta) ? $booking->meta : [];
        $snapshot = is_array($meta['normalized_offer_snapshot'] ?? null) ? $meta['normalized_offer_snapshot'] : [];
        $segments = is_array($snapshot['segments'] ?? null) ? $snapshot['segments'] : [];
        $out = [];
        foreach ($segments as $seg) {
            if (! is_array($seg)) {
                continue;
            }
            $c = strtoupper(trim((string) ($seg['marketing_carrier'] ?? $seg['carrier'] ?? '')));
            if ($c !== '') {
                $out[] = $c;
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * @return list<string>
     */
    protected function extractOperatingCarriers(Booking $booking): array
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $snapshot = is_array($meta['normalized_offer_snapshot'] ?? null) ? $meta['normalized_offer_snapshot'] : [];
        $segments = is_array($snapshot['segments'] ?? null) ? $snapshot['segments'] : [];
        $out = [];
        foreach ($segments as $seg) {
            if (! is_array($seg)) {
                continue;
            }
            $c = strtoupper(trim((string) ($seg['operating_carrier'] ?? $seg['marketing_carrier'] ?? $seg['carrier'] ?? '')));
            if ($c !== '') {
                $out[] = $c;
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * @param  list<array<string, mixed>>  $sellRows
     */
    protected function sellRowsHaveCompleteRbd(array $sellRows, int $segmentCount): bool
    {
        if ($segmentCount <= 0 || count($sellRows) < $segmentCount) {
            return false;
        }
        for ($i = 0; $i < $segmentCount; $i++) {
            $rbd = strtoupper(trim((string) ($sellRows[$i]['res_book_desig_code'] ?? '')));
            if ($rbd === '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  list<array<string, mixed>>  $sellRows
     * @param  list<array<string, mixed>>  $contextSegs
     */
    protected function sellRowsPreserveCarrierChain(array $sellRows, array $contextSegs): bool
    {
        if ($contextSegs === [] || count($sellRows) !== count($contextSegs)) {
            return $contextSegs === [] && $sellRows === [];
        }
        foreach ($contextSegs as $i => $ctx) {
            if (! is_array($ctx)) {
                return false;
            }
            $ctxCarrier = strtoupper(trim((string) ($ctx['marketing_carrier'] ?? $ctx['carrier'] ?? '')));
            $wireCarrier = strtoupper(trim((string) ($sellRows[$i]['marketing_airline'] ?? '')));
            if ($ctxCarrier !== '' && $wireCarrier !== '' && $ctxCarrier !== $wireCarrier) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $wire
     */
    protected function countCommandPricingFareBasisRows(array $wire): int
    {
        $cpnr = is_array($wire['CreatePassengerNameRecordRQ'] ?? null)
            ? $wire['CreatePassengerNameRecordRQ']
            : $wire;
        $ap = is_array($cpnr['AirPrice'] ?? null) ? $cpnr['AirPrice'] : [];
        $first = is_array($ap[0] ?? null) ? $ap[0] : [];
        $pq = data_get($first, 'PriceRequestInformation.OptionalQualifiers.PricingQualifiers', []);
        if (! is_array($pq)) {
            return 0;
        }
        $commandPricing = $pq['CommandPricing'] ?? null;
        if (! is_array($commandPricing)) {
            return 0;
        }
        $rows = array_is_list($commandPricing) ? $commandPricing : [$commandPricing];
        $count = 0;
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $fb = $row['FareBasis'] ?? null;
            $code = is_array($fb) ? trim((string) ($fb['Code'] ?? '')) : trim((string) $fb);
            if ($code !== '') {
                $count++;
            }
        }

        return $count;
    }
}
