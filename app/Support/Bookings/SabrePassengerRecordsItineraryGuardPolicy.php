<?php

namespace App\Support\Bookings;

use App\Models\Booking;
use App\Support\Sabre\GdsPnrCreate\SabreGdsAutoPnrContextCompletionService;
use App\Support\Sabre\GdsPnrCreate\SabreGdsOneWayTripShapeClassifier;
use App\Support\Sabre\GdsPnrCreate\SabreGdsPnrCreateStrategyRegistry;

/**
 * B64+: Scoped Passenger Records pre-live itinerary guard policy (no PII, no raw Sabre payload).
 */
final class SabrePassengerRecordsItineraryGuardPolicy
{
    public const ERROR_CODE = SabrePassengerRecordsItineraryGuardDigest::ERROR_CODE;

    public const BYPASS_REASON = 'scenario_runner_certified_multistop_same_carrier_iati_v2_4';

    /**
     * @param  array<string, mixed>  $options
     * @param  array{guard_trigger: string, segment_order_corrected: bool}|null  $guardSlice
     * @param  array<string, mixed>  $diagFlags
     * @return array<string, mixed>
     */
    public function resolve(
        ?Booking $booking,
        array $offer,
        array $options,
        ?array $guardSlice,
        int $segCount,
        array $diagFlags,
        bool $ticketingEnabled,
        string $endpointPath,
    ): array {
        $fields = $this->eligibilityFields($booking, $offer, $options, $guardSlice, $segCount, $diagFlags, $ticketingEnabled, $endpointPath);

        if ($guardSlice === null) {
            return array_merge($fields, [
                'guard_trigger' => false,
                'guard_bypassed' => false,
                'bypass_allowed' => false,
                'bypass_block_reasons' => [],
            ]);
        }

        $evaluation = $this->evaluateScenarioRunnerMultistopBypass($fields, $guardSlice);

        if ($evaluation['allowed']) {
            return array_merge($fields, [
                'guard_trigger' => (string) ($guardSlice['guard_trigger'] ?? ''),
                'guard_bypassed' => true,
                'guard_bypass_reason' => self::BYPASS_REASON,
                'bypass_allowed' => true,
                'bypass_block_reasons' => [],
            ]);
        }

        return array_merge($fields, [
            'guard_trigger' => (string) ($guardSlice['guard_trigger'] ?? ''),
            'guard_reason_code' => self::ERROR_CODE,
            'segment_order_corrected' => ($guardSlice['segment_order_corrected'] ?? false) === true,
            'guard_bypassed' => false,
            'bypass_allowed' => false,
            'bypass_block_reasons' => $evaluation['reasons'],
            'pnr_attempted' => false,
            'public_auto_pnr_attempted' => false,
        ]);
    }

    /**
     * @param  array<string, mixed>  $options
     * @param  array{guard_trigger: string, segment_order_corrected: bool}|null  $guardSlice
     * @param  array<string, mixed>  $diagFlags
     * @return array<string, mixed>
     */
    public function eligibilityFields(
        ?Booking $booking,
        array $offer,
        array $options,
        ?array $guardSlice,
        int $segCount,
        array $diagFlags,
        bool $ticketingEnabled,
        string $endpointPath,
    ): array {
        $strategySelection = is_array($options['gds_strategy_selection'] ?? null)
            ? $options['gds_strategy_selection']
            : [];
        $completion = is_array($options['auto_pnr_context_completion'] ?? null)
            ? $options['auto_pnr_context_completion']
            : [];
        $selectedStrategy = trim((string) (
            $options['gds_pnr_strategy_code']
            ?? $strategySelection['selected_strategy']
            ?? ''
        ));
        $payloadSchema = trim((string) (
            $diagFlags['payload_schema']
            ?? $diagFlags['payload_style']
            ?? $selectedStrategy
        ));

        $meta = $booking !== null && is_array($booking->meta) ? $booking->meta : [];
        if ($meta !== []) {
            $storedCompletion = is_array($meta[SabreGdsAutoPnrContextCompletionService::META_KEY] ?? null)
                ? $meta[SabreGdsAutoPnrContextCompletionService::META_KEY]
                : [];
            if ($storedCompletion !== []) {
                $completion = array_merge($storedCompletion, $completion);
            }
        }

        $handoff = is_array($meta['sabre_booking_context'] ?? null) ? $meta['sabre_booking_context'] : [];
        $selected = is_array($meta['selected_fare_family_option'] ?? null) ? $meta['selected_fare_family_option'] : [];
        $readiness = $booking !== null
            ? app(SabrePnrCertificationSupport::class)->buildReadiness($booking)
            : [];
        $classified = $booking !== null
            ? app(SabreGdsOneWayTripShapeClassifier::class)->classify($booking, $readiness)
            : [];
        $routeSelection = $booking !== null
            ? app(SabreCertifiedRouteSelector::class)->selectForBooking($booking)
            : [];

        $carriers = is_array($readiness['carrier_chain'] ?? null) ? $readiness['carrier_chain'] : [];
        $legRefs = is_array($handoff['leg_refs'] ?? null) ? array_values($handoff['leg_refs']) : [];
        $scheduleRefs = is_array($handoff['schedule_refs'] ?? null) ? array_values($handoff['schedule_refs']) : [];
        $segmentRows = (int) ($classified['segment_rows_count'] ?? $classified['segment_count'] ?? $segCount);
        $segmentSliceCount = max(
            (int) ($handoff['segment_slice_count'] ?? 0),
            (int) ($selected['segment_slice_count'] ?? 0),
            count($scheduleRefs),
        );
        $completionStatus = trim((string) ($completion['auto_pnr_context_completion_status'] ?? ''));
        $publicAutoReady = ($completion['public_auto_pnr_attempt_ready'] ?? false) === true;
        $handoffReady = ($handoff['ready_for_booking_payload'] ?? false) === true;
        $scenarioRunnerActive = $this->isScenarioRunnerApproved($options);
        $scenarioLiveApproved = ($options['operator_approved_live_pnr_create'] ?? false) === true;
        $overrideApplied = ($strategySelection['scenario_runner_override_applied'] ?? false) === true
            || ($scenarioRunnerActive
                && $scenarioLiveApproved
                && $publicAutoReady
                && in_array($completionStatus, [
                    SabreGdsAutoPnrContextCompletionService::STATUS_COMPLETE,
                    SabreGdsAutoPnrContextCompletionService::STATUS_REPAIRED,
                ], true));

        $bookingClassCount = $this->perSegmentCount(
            $completion,
            $handoff,
            $selected,
            'booking_classes_by_segment_count',
            'booking_classes_by_segment',
            'completed_booking_classes_by_segment',
            $segCount,
            $scheduleRefs,
        );
        $fareBasisCount = $this->perSegmentCount(
            $completion,
            $handoff,
            $selected,
            'fare_basis_codes_by_segment_count',
            'fare_basis_codes_by_segment',
            'completed_fare_basis_codes_by_segment',
            $segCount,
            $scheduleRefs,
        );
        $cabinCount = $this->perSegmentCount(
            $completion,
            $handoff,
            $selected,
            'cabin_by_segment_count',
            'cabin_by_segment',
            'completed_cabin_by_segment',
            $segCount,
            $scheduleRefs,
        );

        $concreteSell = $this->computeConcreteSellContextComplete(
            $segCount,
            $segmentRows,
            count($scheduleRefs),
            $segmentSliceCount,
            $bookingClassCount,
            $fareBasisCount,
            $cabinCount,
            $publicAutoReady,
            $completionStatus,
            $handoffReady,
            $this->resolveStrategyContextReady($options, $strategySelection, $selectedStrategy),
        );

        return [
            'selected_strategy' => $selectedStrategy !== '' ? $selectedStrategy : null,
            'pnr_strategy_used' => $selectedStrategy !== '' ? $selectedStrategy : null,
            'payload_schema' => $payloadSchema !== '' ? $payloadSchema : null,
            'endpoint_path' => $endpointPath !== '' ? $endpointPath : null,
            'trip_type' => (string) ($classified['trip_type'] ?? ''),
            'category' => (string) ($routeSelection['category'] ?? $classified['category'] ?? ''),
            'route_shape' => (string) ($classified['route_shape'] ?? ''),
            'segment_count' => $segCount,
            'stops' => max(0, $segCount - 1),
            'carrier_chain_count' => count($carriers),
            'validating_carrier_present' => trim((string) ($readiness['validating_carrier'] ?? '')) !== '',
            'multistop_shape_valid' => ($classified['multistop_shape_valid'] ?? false) === true,
            'multistop_same_carrier' => ($classified['multistop_same_carrier'] ?? false) === true,
            'multistop_route_continuity_valid' => ($classified['multistop_route_continuity_valid'] ?? false) === true,
            'multistop_chronology_valid' => ($classified['multistop_chronology_valid'] ?? false) === true,
            'segment_sell_context_valid' => ($classified['segment_sell_context_valid'] ?? null),
            'selection_safe' => ($classified['selection_safe'] ?? null),
            'segment_order_corrected' => ($guardSlice['segment_order_corrected'] ?? self::offerSegmentOrderCorrected($offer)) === true,
            'leg_refs_count' => count($legRefs),
            'schedule_refs_count' => count($scheduleRefs),
            'segment_rows_count' => $segmentRows,
            'segment_slice_count' => $segmentSliceCount,
            'booking_classes_by_segment_count' => $bookingClassCount,
            'fare_basis_codes_by_segment_count' => $fareBasisCount,
            'cabin_by_segment_count' => $cabinCount,
            'auto_pnr_context_completion_status' => $completionStatus !== '' ? $completionStatus : null,
            'public_auto_pnr_attempt_ready' => $publicAutoReady,
            'sabre_booking_context_ready_for_booking_payload' => $handoffReady,
            'strategy_context_ready' => $this->resolveStrategyContextReady($options, $strategySelection, $selectedStrategy),
            'concrete_sell_context_complete' => $concreteSell['complete'],
            'concrete_sell_context_source' => $concreteSell['source'],
            'scenario_runner_active' => $scenarioRunnerActive,
            'scenario_live_pnr_create_approved' => $scenarioLiveApproved,
            'scenario_runner_override_applied' => $overrideApplied,
            'ticketing_enabled' => $this->resolveOperationalTicketingEnabled($ticketingEnabled),
        ];
    }

    /**
     * PNR-only scenario-runner lane requires ticketing OFF; block bypass only when ticketing is ON.
     */
    protected function resolveOperationalTicketingEnabled(bool $ticketingEnabledParam): bool
    {
        if (config()->has('suppliers.sabre.ticketing_enabled')) {
            return (bool) config('suppliers.sabre.ticketing_enabled', false);
        }

        return $ticketingEnabledParam;
    }

    protected function isTicketingEnabledForBypass(array $fields): bool
    {
        return $this->normalizeStrictBoolean($fields['ticketing_enabled'] ?? false);
    }

    protected function normalizeStrictBoolean(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value) || is_float($value)) {
            return (int) $value === 1;
        }
        if (is_string($value)) {
            return in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'on'], true);
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $fields
     * @param  array{guard_trigger: string, segment_order_corrected: bool}  $guardSlice
     * @return array{allowed: bool, reasons: list<string>}
     */
    protected function evaluateScenarioRunnerMultistopBypass(array $fields, array $guardSlice): array
    {
        $reasons = [];

        if (($fields['scenario_runner_active'] ?? false) !== true) {
            $reasons[] = 'scenario_runner_not_active';
        }
        if (($fields['scenario_live_pnr_create_approved'] ?? false) !== true) {
            $reasons[] = 'scenario_live_pnr_create_not_approved';
        }
        if (($fields['scenario_runner_override_applied'] ?? false) !== true) {
            $reasons[] = 'scenario_runner_override_not_applied';
        }
        if (($fields['segment_order_corrected'] ?? false) === true) {
            $reasons[] = 'segment_order_corrected';
        }
        if ($this->isTicketingEnabledForBypass($fields)) {
            $reasons[] = 'ticketing_enabled';
        }

        $strategy = (string) ($fields['selected_strategy'] ?? '');
        if ($strategy !== SabreGdsPnrCreateStrategyRegistry::STRATEGY_IATI_LIKE_CPNR_V2_4_GDS) {
            $reasons[] = 'selected_strategy_not_iati_v2_4';
        }
        if ((string) ($fields['payload_schema'] ?? '') !== SabreGdsPnrCreateStrategyRegistry::STRATEGY_IATI_LIKE_CPNR_V2_4_GDS) {
            $reasons[] = 'payload_schema_not_iati_v2_4';
        }
        if ((string) ($fields['endpoint_path'] ?? '') !== SabreCertifiedRouteSelector::ENDPOINT_PASSENGER_RECORDS_V24_CREATE) {
            $reasons[] = 'endpoint_path_not_v2_4_create';
        }
        if ((string) ($fields['trip_type'] ?? '') !== SabreGdsOneWayTripShapeClassifier::TRIP_ONE_WAY_MULTISTOP_SAME_CARRIER) {
            $reasons[] = 'trip_type_not_multistop_same_carrier';
        }
        if ((string) ($fields['category'] ?? '') !== SabreCertifiedRouteSelector::CATEGORY_ONE_WAY_MULTISTOP_SAME_CARRIER_GDS) {
            $reasons[] = 'category_not_multistop_same_carrier_gds';
        }
        if ((string) ($fields['route_shape'] ?? '') !== SabreGdsOneWayTripShapeClassifier::ROUTE_SHAPE_ONE_WAY_MULTISTOP_SAME_CARRIER) {
            $reasons[] = 'route_shape_not_multistop_same_carrier';
        }

        $segCount = (int) ($fields['segment_count'] ?? 0);
        if ($segCount < 3 || (int) ($fields['stops'] ?? 0) < 2) {
            $reasons[] = 'segment_count_or_stops_insufficient';
        }
        if ((int) ($fields['carrier_chain_count'] ?? 0) !== 1) {
            $reasons[] = 'carrier_chain_not_single';
        }
        if (($fields['validating_carrier_present'] ?? false) !== true) {
            $reasons[] = 'validating_carrier_missing';
        }

        foreach ([
            'multistop_shape_valid',
            'multistop_same_carrier',
            'multistop_route_continuity_valid',
            'multistop_chronology_valid',
        ] as $flag) {
            if (($fields[$flag] ?? false) !== true) {
                $reasons[] = $flag.'_false';
            }
        }

        if (($fields['concrete_sell_context_complete'] ?? false) !== true) {
            $reasons[] = 'concrete_sell_context_incomplete';
        }

        if (($fields['strategy_context_ready'] ?? null) === false) {
            $reasons[] = 'strategy_context_not_ready';
        }

        if (trim((string) ($guardSlice['guard_trigger'] ?? '')) === '') {
            $reasons[] = 'guard_trigger_missing';
        }

        return [
            'allowed' => $reasons === [],
            'reasons' => $reasons,
        ];
    }

    /**
     * @param  list<mixed>  $scheduleRefs
     * @return array{complete: bool, source: ?string}
     */
    protected function computeConcreteSellContextComplete(
        int $segCount,
        int $segmentRows,
        int $scheduleRefCount,
        int $segmentSliceCount,
        int $bookingClassCount,
        int $fareBasisCount,
        int $cabinCount,
        bool $publicAutoReady,
        string $completionStatus,
        bool $handoffReady,
        ?bool $strategyContextReady,
    ): array {
        if ($segCount < 3) {
            return ['complete' => false, 'source' => null];
        }

        $segmentEvidence = $segmentRows === $segCount
            || $scheduleRefCount === $segCount
            || $segmentSliceCount === $segCount;
        $arraysComplete = $bookingClassCount === $segCount
            && $fareBasisCount === $segCount
            && $cabinCount === $segCount;
        $completionOk = in_array($completionStatus, [
            SabreGdsAutoPnrContextCompletionService::STATUS_COMPLETE,
            SabreGdsAutoPnrContextCompletionService::STATUS_REPAIRED,
        ], true);
        $strategyOk = $strategyContextReady !== false;

        $complete = $segmentEvidence
            && $arraysComplete
            && $publicAutoReady
            && $completionOk
            && $strategyOk;

        if (! $complete) {
            return ['complete' => false, 'source' => null];
        }

        $source = match (true) {
            $handoffReady && $segmentRows === $segCount => 'handoff_ready_segment_rows',
            $scheduleRefCount === $segCount => 'schedule_refs_per_segment_arrays',
            $segmentSliceCount === $segCount => 'segment_slice_per_segment_arrays',
            default => 'segment_rows_per_segment_arrays',
        };

        return ['complete' => true, 'source' => $source];
    }

    /**
     * @param  array<string, mixed>  $completion
     * @param  array<string, mixed>  $handoff
     * @param  array<string, mixed>  $selected
     * @param  list<mixed>  $scheduleRefs
     */
    protected function perSegmentCount(
        array $completion,
        array $handoff,
        array $selected,
        string $countField,
        string $arrayField,
        string $completedArrayField,
        int $segCount,
        array $scheduleRefs,
    ): int {
        $explicit = (int) ($completion[$countField] ?? 0);
        if ($segCount > 0 && $explicit >= $segCount) {
            return $explicit;
        }

        foreach ([
            is_array($completion[$completedArrayField] ?? null) ? count($completion[$completedArrayField]) : 0,
            is_array($handoff[$arrayField] ?? null) ? count($handoff[$arrayField]) : 0,
            is_array($selected[$arrayField] ?? null) ? count($selected[$arrayField]) : 0,
        ] as $count) {
            if ($segCount > 0 && $count >= $segCount) {
                return $count;
            }
        }

        if ($segCount >= 3 && count($scheduleRefs) >= $segCount) {
            foreach ([$handoff[$arrayField] ?? [], $selected[$arrayField] ?? []] as $values) {
                if (is_array($values) && count($values) === 1 && trim((string) ($values[0] ?? '')) !== '') {
                    return $segCount;
                }
            }
        }

        return max($explicit, 0);
    }

    /**
     * @param  array<string, mixed>  $options
     * @param  array<string, mixed>  $strategySelection
     */
    protected function resolveStrategyContextReady(array $options, array $strategySelection, string $selectedStrategy): ?bool
    {
        if (array_key_exists('gds_strategy_context_ready', $options)) {
            return ($options['gds_strategy_context_ready'] ?? false) === true ? true : false;
        }

        $candidates = is_array($strategySelection['candidate_exclusion_diagnostics'] ?? null)
            ? $strategySelection['candidate_exclusion_diagnostics']
            : [];
        foreach ($candidates as $candidate) {
            if (! is_array($candidate)) {
                continue;
            }
            if ((string) ($candidate['strategy_code'] ?? '') !== $selectedStrategy) {
                continue;
            }

            return ($candidate['context_ready'] ?? false) === true ? true : false;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $options
     */
    protected function isScenarioRunnerApproved(array $options): bool
    {
        return ($options['mode'] ?? '') === 'scenario_runner'
            && ($options['operator_approved_live_pnr_create'] ?? false) === true;
    }

    /**
     * @param  array<string, mixed>  $offer
     */
    protected static function offerSegmentOrderCorrected(array $offer): bool
    {
        return data_get($offer, 'raw_payload.sabre_segment_order.segment_order_corrected') === true;
    }

    /**
     * @param  array<string, mixed>  $guardDecision
     * @return array<string, mixed>
     */
    public function safeSummarySlice(array $guardDecision): array
    {
        $keys = [
            'guard_trigger',
            'guard_reason_code',
            'guard_bypassed',
            'guard_bypass_reason',
            'bypass_allowed',
            'bypass_block_reasons',
            'concrete_sell_context_complete',
            'concrete_sell_context_source',
            'selected_strategy',
            'pnr_strategy_used',
            'payload_schema',
            'endpoint_path',
            'trip_type',
            'category',
            'route_shape',
            'segment_count',
            'stops',
            'carrier_chain_count',
            'validating_carrier_present',
            'multistop_shape_valid',
            'multistop_same_carrier',
            'multistop_route_continuity_valid',
            'multistop_chronology_valid',
            'segment_sell_context_valid',
            'selection_safe',
            'segment_order_corrected',
            'leg_refs_count',
            'schedule_refs_count',
            'segment_rows_count',
            'segment_slice_count',
            'booking_classes_by_segment_count',
            'fare_basis_codes_by_segment_count',
            'cabin_by_segment_count',
            'auto_pnr_context_completion_status',
            'public_auto_pnr_attempt_ready',
            'scenario_live_pnr_create_approved',
            'scenario_runner_override_applied',
            'strategy_context_ready',
            'ticketing_enabled',
            'pnr_attempted',
            'public_auto_pnr_attempted',
        ];

        $slice = array_intersect_key($guardDecision, array_flip($keys));
        if (isset($slice['bypass_block_reasons']) && is_array($slice['bypass_block_reasons']) && $slice['bypass_block_reasons'] === []) {
            unset($slice['bypass_block_reasons']);
        }

        return array_filter(
            $slice,
            static fn ($value) => $value !== null && $value !== '',
        );
    }
}
