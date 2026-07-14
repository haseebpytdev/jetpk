<?php

namespace App\Support\Sabre\GdsPnrCreate;

use App\Models\Booking;
use App\Support\Bookings\SabreCertifiedRouteSelector;
use App\Support\Bookings\SabrePnrCertificationSupport;
use App\Support\Sabre\Scenario\SabreGdsLiveScenarioPlanCandidateDiagnostics;

/**
 * Scenario-runner-only mixed-carrier GDS PNR certification gate (max 2 stops / 3 segments, IATI v2.4 only).
 */
final class SabreGdsMixedCarrierCertificationGate
{
    public const APPROVAL_PHRASE = 'APPROVE-MIXED-CARRIER-GDS-PNR';

    public const SCOPE = 'max_2_stops';

    public const MAX_STOPS = 2;

    public const MAX_SEGMENTS = 3;

    public const REASON_APPROVAL_MISSING = 'mixed_carrier_certification_approval_missing';

    public const REASON_TOO_MANY_STOPS = 'advanced_mixed_too_many_stops';

    public const REASON_TOO_MANY_SEGMENTS = 'mixed_carrier_segment_count_exceeds_certified_max';

    public const REASON_NOT_MIXED = 'mixed_carrier_not_detected';

    public const REASON_STRATEGY_NOT_IATI = 'mixed_carrier_requires_iati_v2_4';

    public const REASON_TICKETING_ENABLED = 'ticketing_enabled_blocks_mixed_certification';

    public const ENDPOINT_IATI_CREATE = SabreCertifiedRouteSelector::ENDPOINT_PASSENGER_RECORDS_V24_CREATE;

    public function __construct(
        protected SabrePnrCertificationSupport $certificationSupport,
    ) {}

    /**
     * @return list<string>
     */
    public static function mixedCarrierTripTypes(): array
    {
        return [
            SabreGdsOneWayTripShapeClassifier::TRIP_ONE_WAY_SINGLE_CONNECTION_MIXED_CARRIER,
            SabreGdsOneWayTripShapeClassifier::TRIP_ONE_WAY_MULTISTOP_MIXED_CARRIER,
            SabreGdsReturnTripClassifier::TRIP_RETURN_MIXED_CARRIER,
        ];
    }

    public function isMixedCarrierTripType(string $tripType): bool
    {
        return in_array(trim($tripType), self::mixedCarrierTripTypes(), true);
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function evaluate(Booking $booking, array $context = []): array
    {
        $readiness = is_array($context['readiness'] ?? null)
            ? $context['readiness']
            : $this->certificationSupport->buildReadiness($booking);
        $tripType = trim((string) ($context['trip_type'] ?? $this->certificationSupport->detectTripType($booking)));
        $completion = is_array($context['completion'] ?? null) ? $context['completion'] : [];
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $handoff = is_array($meta['sabre_booking_context'] ?? null) ? $meta['sabre_booking_context'] : [];

        $segmentCount = (int) ($context['segment_count'] ?? $readiness['segment_count'] ?? 0);
        $stops = max(0, $segmentCount - 1);
        $carriers = is_array($readiness['carrier_chain'] ?? null) ? array_values($readiness['carrier_chain']) : [];
        $carrierChainCount = count($carriers);
        $mixedDetected = $carrierChainCount > 1
            || ($readiness['mixed_carrier'] ?? false) === true
            || $this->isMixedCarrierTripType($tripType);

        $blockReasons = [];
        $operatorApproved = ($context['scenario_live_pnr_create_approved'] ?? false) === true;
        $planModePreflight = ($context['plan_mode_payload_preflight'] ?? false) === true;
        $mixedApproved = ($context['mixed_carrier_certification_approved'] ?? false) === true;
        $overrideApplied = ($context['scenario_runner_override_applied'] ?? false) === true;

        if (! $operatorApproved && ! $planModePreflight) {
            $blockReasons[] = 'scenario_live_pnr_create_not_approved';
        }
        if (! $mixedApproved) {
            $blockReasons[] = self::REASON_APPROVAL_MISSING;
        }
        if (! $overrideApplied) {
            $blockReasons[] = 'scenario_runner_override_not_applied';
        }
        if (! $mixedDetected) {
            $blockReasons[] = self::REASON_NOT_MIXED;
        }
        if ($stops > self::MAX_STOPS) {
            $blockReasons[] = self::REASON_TOO_MANY_STOPS;
        }
        if ($segmentCount > self::MAX_SEGMENTS) {
            $blockReasons[] = self::REASON_TOO_MANY_SEGMENTS;
        }
        if ($this->ticketingEnabled()) {
            $blockReasons[] = self::REASON_TICKETING_ENABLED;
        }
        if (trim((string) ($readiness['validating_carrier'] ?? $handoff['validating_carrier'] ?? '')) === '') {
            $blockReasons[] = 'missing_validating_carrier';
        }
        if ($carrierChainCount <= 1 && $mixedDetected) {
            $blockReasons[] = 'carrier_chain_count_not_mixed';
        }

        $completionStatus = trim((string) ($completion['auto_pnr_context_completion_status'] ?? ''));
        if (! in_array($completionStatus, [
            SabreGdsAutoPnrContextCompletionService::STATUS_COMPLETE,
            SabreGdsAutoPnrContextCompletionService::STATUS_REPAIRED,
        ], true)) {
            $blockReasons[] = 'context_completion_not_ready';
        }
        if (($completion['public_auto_pnr_attempt_ready'] ?? false) !== true) {
            $blockReasons[] = 'public_auto_pnr_attempt_not_ready';
        }

        $segmentRowsCount = (int) ($context['segment_rows_count'] ?? $segmentCount);
        $scheduleRefs = is_array($handoff['schedule_refs'] ?? null) ? $handoff['schedule_refs'] : [];
        $scheduleRefsCount = (int) ($context['schedule_refs_count'] ?? count($scheduleRefs));
        $bookingClassCount = (int) ($completion['booking_classes_by_segment_count']
            ?? count(is_array($handoff['booking_classes_by_segment'] ?? null) ? $handoff['booking_classes_by_segment'] : []));
        $fareBasisCount = (int) ($completion['fare_basis_codes_by_segment_count']
            ?? count(is_array($handoff['fare_basis_codes_by_segment'] ?? null) ? $handoff['fare_basis_codes_by_segment'] : []));
        $cabinCount = (int) ($completion['cabin_by_segment_count']
            ?? count(is_array($handoff['cabin_by_segment'] ?? null) ? $handoff['cabin_by_segment'] : []));

        if ($segmentCount > 0 && $segmentRowsCount !== $segmentCount) {
            $blockReasons[] = 'segment_rows_count_mismatch';
        }
        if ($segmentCount > 0 && $scheduleRefsCount > 0 && $scheduleRefsCount !== $segmentCount) {
            $blockReasons[] = 'schedule_refs_count_mismatch';
        }
        if ($segmentCount > 0 && $bookingClassCount !== $segmentCount) {
            $blockReasons[] = 'booking_classes_by_segment_incomplete';
        }
        if ($segmentCount > 0 && $fareBasisCount !== $segmentCount) {
            $blockReasons[] = 'fare_basis_codes_by_segment_incomplete';
        }
        if ($segmentCount >= 3 && $cabinCount !== $segmentCount) {
            $blockReasons[] = 'cabin_by_segment_incomplete';
        }

        $segmentOrderCorrected = ($context['segment_order_corrected'] ?? false) === true;
        if ($segmentOrderCorrected) {
            $blockReasons[] = 'segment_order_corrected';
        }

        $selectedStrategy = trim((string) ($context['selected_strategy'] ?? ''));
        if ($selectedStrategy !== '' && $selectedStrategy !== SabreGdsPnrCreateStrategyRegistry::STRATEGY_IATI_LIKE_CPNR_V2_4_GDS) {
            $blockReasons[] = self::REASON_STRATEGY_NOT_IATI;
        }

        $endpointPath = trim((string) ($context['endpoint_path'] ?? self::ENDPOINT_IATI_CREATE));
        if ($endpointPath !== '' && $endpointPath !== self::ENDPOINT_IATI_CREATE) {
            $blockReasons[] = 'endpoint_not_iati_v2_4_create';
        }

        $blockReasons = array_values(array_unique($blockReasons));
        $allowed = $blockReasons === [];

        return [
            'allowed' => $allowed,
            'block_reasons' => $blockReasons,
            'block_reason' => $allowed ? null : ($blockReasons[0] ?? SabreGdsLiveScenarioPlanCandidateDiagnostics::BLOCK_MIXED_CARRIER_NOT_CERTIFIED),
            'mixed_carrier_detected' => $mixedDetected,
            'carrier_chain' => $carriers,
            'carrier_chain_count' => $carrierChainCount > 0 ? $carrierChainCount : null,
            'validating_carrier' => strtoupper(trim((string) ($readiness['validating_carrier'] ?? $handoff['validating_carrier'] ?? ''))) ?: null,
            'segment_count' => $segmentCount,
            'stops' => $stops,
            'segment_rows_count' => $segmentRowsCount,
            'schedule_refs_count' => $scheduleRefsCount,
            'booking_classes_by_segment_count' => $bookingClassCount,
            'fare_basis_codes_by_segment_count' => $fareBasisCount,
            'cabin_by_segment_count' => $cabinCount,
            'mixed_carrier_certification_approved' => $mixedApproved,
            'mixed_carrier_certification_scope' => self::SCOPE,
            'scenario_live_pnr_create_approved' => $operatorApproved,
            'scenario_runner_override_applied' => $overrideApplied,
            'interline_or_mixed_blocked' => ! $allowed,
            'automatic_block_reason' => $allowed
                ? null
                : ($mixedApproved ? ($blockReasons[0] ?? null) : SabreGdsLiveScenarioPlanCandidateDiagnostics::BLOCK_MIXED_CARRIER_NOT_CERTIFIED),
            'selected_strategy_expected' => $allowed || $mixedApproved
                ? SabreGdsPnrCreateStrategyRegistry::STRATEGY_IATI_LIKE_CPNR_V2_4_GDS
                : null,
            'payload_schema_expected' => SabreGdsPnrCreateStrategyRegistry::STRATEGY_IATI_LIKE_CPNR_V2_4_GDS,
            'endpoint_path_expected' => self::ENDPOINT_IATI_CREATE,
            'ticketing_attempted' => false,
            'airticket_attempted' => false,
            'trip_type' => $tripType,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function safeSummarySlice(array $evaluation): array
    {
        return array_intersect_key($evaluation, array_flip([
            'mixed_carrier_detected',
            'carrier_chain',
            'carrier_chain_count',
            'validating_carrier',
            'mixed_carrier_certification_approved',
            'mixed_carrier_certification_scope',
            'scenario_live_pnr_create_approved',
            'scenario_runner_override_applied',
            'selected_strategy_expected',
            'payload_schema_expected',
            'endpoint_path_expected',
            'segment_count',
            'stops',
            'block_reason',
            'block_reasons',
            'interline_or_mixed_blocked',
            'automatic_block_reason',
            'ticketing_attempted',
            'airticket_attempted',
        ]));
    }

    protected function ticketingEnabled(): bool
    {
        if (config('suppliers.sabre.ticketing_enabled', false) === true) {
            return true;
        }
        if (config('suppliers.sabre.public_ticketing_enabled', false) === true) {
            return true;
        }
        if (config('suppliers.sabre.checkout_auto_ticketing_enabled', false) === true) {
            return true;
        }

        return false;
    }
}
