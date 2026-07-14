<?php

namespace App\Support\Sabre\GdsPnrCreate;

use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Models\SupplierBookingAttempt;
use App\Services\Suppliers\Sabre\Booking\SabreBookingPayloadBuilder;
use App\Services\Suppliers\Sabre\Booking\SabreBookingService;
use App\Support\Bookings\SabreCertifiedRouteSelector;
use App\Support\Bookings\SabrePnrCertificationSupport;
use App\Support\Sabre\Scenario\SabreGdsLiveScenarioPlanCandidateDiagnostics;
use App\Support\Sabre\GdsPnrCreate\SabreGdsMixedCarrierCertificationGate;
use App\Support\Sabre\GdsPnrCreate\SabreGdsMixedCarrierFareBasisPayloadPreflight;

/**
 * Read-only digest builder for all candidate Sabre GDS PNR create strategies (no live HTTP, no raw payload / PII).
 */
final class SabreGdsPnrCreateStrategyDigest
{
    /** @var list<string> */
    private const FORBIDDEN_OUTPUT_KEYS = [
        'pcc',
        'token',
        'authorization',
        'credentials',
        'password',
        'passport',
        'telephone',
        'email',
        'personname',
        'givenname',
        'surname',
        'createpassengernamerecordrq',
    ];

    public function __construct(
        protected SabreGdsPnrCreateStrategyRegistry $registry,
        protected SabreBookingPayloadBuilder $payloadBuilder,
        protected SabreBookingService $bookingService,
        protected SabrePnrCertificationSupport $certificationSupport,
        protected SabreCertifiedRouteSelector $routeSelector,
        protected SabreGdsPnrCreateStrategyResultClassifier $resultClassifier,
        protected SabreConnectingBrandedFarePublicAutoCertification $publicAutoCertification,
        protected SabreGdsAutoPnrContextCompletionService $contextCompletion,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function buildBookingSummary(Booking $booking): array
    {
        $booking->loadMissing(['passengers', 'contact', 'fareBreakdown']);
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $readiness = $this->certificationSupport->buildReadiness($booking);
        $tripType = $this->certificationSupport->detectTripType($booking);
        $shapeDiagnostics = $this->isReturnTripShape($tripType)
            ? app(SabreGdsReturnTripClassifier::class)->diagnose($booking, $readiness)
            : app(SabreGdsOneWayTripShapeClassifier::class)->classify($booking, $readiness);
        if (! $this->isReturnTripShape($tripType)) {
            $tripType = trim((string) ($shapeDiagnostics['trip_type'] ?? $tripType));
        }
        $shapeDiagnosticKeys = $this->isReturnTripShape($tripType)
            ? [
                'trip_type_detected',
                'return_route_continuity_valid',
                'return_chronology_valid',
                'return_same_carrier',
                'return_origin_destination_pattern',
                'return_shape_valid',
            ]
            : [
                'trip_type',
                'trip_type_detected',
                'category',
                'route_shape',
                'selection_safe',
                'multistop_route_continuity_valid',
                'multistop_chronology_valid',
                'multistop_same_carrier',
                'multistop_origin_destination_pattern',
                'multistop_shape_valid',
                'segment_sell_context_valid',
                'stops',
                'leg_refs_count',
                'schedule_refs_count',
                'segment_rows_count',
            ];
        $carriers = is_array($readiness['carrier_chain'] ?? null) ? $readiness['carrier_chain'] : [];
        $selected = is_array($meta['selected_fare_family_option'] ?? null) ? $meta['selected_fare_family_option'] : [];
        $handoff = is_array($meta['sabre_booking_context'] ?? null) ? $meta['sabre_booking_context'] : [];

        $integritySummary = $this->bookingService->inspectGdsPnrPayloadIntegrityForCommand($booking);
        $attemptContext = $this->resolvePreviousAttemptContext($booking);
        $publicAutoCert = $this->publicAutoCertification->assess($booking);
        $contextCompletion = $this->contextCompletion->completeForBooking($booking);

        $carriers = is_array($readiness['carrier_chain'] ?? null) ? $readiness['carrier_chain'] : [];
        $mixedCarrierDetected = count($carriers) > 1 || ($shapeDiagnostics['mixed_carrier'] ?? false) === true;
        $interlineBlocked = in_array($tripType, [
            SabreGdsOneWayTripShapeClassifier::TRIP_ONE_WAY_SINGLE_CONNECTION_MIXED_CARRIER,
            SabreGdsOneWayTripShapeClassifier::TRIP_ONE_WAY_MULTISTOP_MIXED_CARRIER,
            SabreGdsReturnTripClassifier::TRIP_RETURN_MIXED_CARRIER,
        ], true);

        return array_merge([
            'booking_id' => $booking->id,
            'booking_reference' => (string) ($booking->booking_reference ?? ''),
            'connection_sticky' => (bool) ($integritySummary['connection_sticky'] ?? false),
            'selected_brand_context_consistent' => (bool) ($integritySummary['selected_brand_context_consistent'] ?? false),
            'trip_type' => $tripType,
            'segment_count' => (int) ($readiness['segment_count'] ?? 0),
            'carrier_chain' => $carriers,
            'validating_carrier' => strtoupper(trim((string) ($readiness['validating_carrier'] ?? ''))) ?: null,
            'selected_brand_code' => strtoupper(trim((string) ($selected['brand_code'] ?? ''))) ?: null,
            'selected_fare_basis' => $this->firstFareBasis($selected) ?: $this->firstFareBasis($handoff),
            'selected_fare_basis_display' => $publicAutoCert['selected_fare_basis_display'] ?? null,
            'selected_baggage' => trim((string) ($selected['baggage_summary'] ?? $selected['baggage'] ?? '')) ?: null,
            'selected_total' => $this->resolveSelectedTotal($booking, $meta, $selected),
            'previous_attempt_failed' => $attemptContext['previous_attempt_failed'],
            'previous_failed_strategy' => $attemptContext['previous_failed_strategy'],
            'previous_host_error_family' => $attemptContext['previous_host_error_family'],
            'safe_retry_requires_admin_confirmation' => $attemptContext['safe_retry_requires_admin_confirmation'],
        ], array_intersect_key($publicAutoCert, array_flip([
            'booking_classes_by_segment_count',
            'fare_basis_codes_by_segment_count',
            'per_segment_booking_class_complete',
            'per_segment_fare_basis_complete',
            'per_segment_cabin_complete',
            'connecting_brand_context_complete',
            'public_auto_certified',
            'public_auto_pnr_certified',
            'public_auto_block_reason',
        ])), array_intersect_key($contextCompletion, array_flip([
            'auto_pnr_context_completion_attempted',
            'auto_pnr_context_completion_status',
            'completion_sources_used',
            'booking_classes_by_segment_count',
            'fare_basis_codes_by_segment_count',
            'cabin_by_segment_count',
            'per_segment_booking_class_complete',
            'per_segment_fare_basis_complete',
            'expanded_single_fare_component_to_all_segments',
            'exact_refresh_attempted',
            'exact_refresh_result',
            'public_auto_pnr_attempt_ready',
        ])), [
            'completed_booking_classes_by_segment_count' => (int) ($contextCompletion['booking_classes_by_segment_count'] ?? 0),
            'completed_fare_basis_codes_by_segment_count' => (int) ($contextCompletion['fare_basis_codes_by_segment_count'] ?? 0),
            'public_auto_pnr_block_reason' => ($contextCompletion['public_auto_pnr_attempt_ready'] ?? false) === true
                ? null
                : ($contextCompletion['public_auto_pnr_block_reason'] ?? null),
            'mixed_carrier_detected' => $mixedCarrierDetected,
            'carrier_chain_count' => count($carriers),
            'interline_or_mixed_blocked' => $interlineBlocked,
            'automatic_block_reason' => $interlineBlocked
                ? SabreGdsLiveScenarioPlanCandidateDiagnostics::BLOCK_MIXED_CARRIER_NOT_CERTIFIED
                : (in_array($tripType, [
                    SabreGdsOneWayTripShapeClassifier::TRIP_ONE_WAY_THREE_STOP_SAME_CARRIER,
                    SabreGdsOneWayTripShapeClassifier::TRIP_ONE_WAY_FOUR_STOP_SAME_CARRIER,
                ], true)
                    ? SabreGdsOneWayTripShapeClassifier::ADVANCED_ITINERARY_PLAN_ONLY_BLOCK_REASON
                    : null),
        ], array_intersect_key($shapeDiagnostics, array_flip($shapeDiagnosticKeys)));
    }

    protected function isReturnTripShape(string $tripType): bool
    {
        return in_array($tripType, [
            SabreGdsReturnTripClassifier::TRIP_RETURN_SAME_CARRIER,
            SabreGdsReturnTripClassifier::TRIP_RETURN_MIXED_CARRIER,
            'round_trip',
        ], true);
    }

    /**
     * @param  array<string, mixed>|null  $selection
     * @param  array<string, mixed>  $options
     * @return list<array<string, mixed>>
     */
    public function buildCandidateDigests(Booking $booking, ?array $selection = null, array $options = []): array
    {
        $booking->loadMissing(['passengers', 'contact']);
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $scenarioRunner = ($options['scenario_runner'] ?? false) === true;
        $mixedCertApproved = ($options['mixed_carrier_certification_approved'] ?? false) === true;
        $contextCompletion = is_array($options['context_completion'] ?? null)
            ? $options['context_completion']
            : $this->contextCompletion->completeForBooking($booking);
        $metaOverlay = $scenarioRunner
            ? $this->contextCompletion->scenarioRunnerStrategyMetaOverlay($booking, $contextCompletion)
            : null;
        $wireContext = $this->bookingService->buildGdsPnrStrategyWireContext($booking, $metaOverlay);
        if (($wireContext['valid'] ?? false) !== true) {
            return array_map(
                fn (string $code): array => $this->emptyCandidateDigest($code, 'wire_context_invalid', $selection),
                $this->registry->supportedCodes(),
            );
        }

        $snapshot = is_array($wireContext['snapshot'] ?? null) ? $wireContext['snapshot'] : [];
        $apiDraft = is_array($wireContext['api_draft'] ?? null) ? $wireContext['api_draft'] : [];
        $hints = is_array($wireContext['hints'] ?? null) ? $wireContext['hints'] : [];
        $meta = is_array($wireContext['meta'] ?? null) ? $wireContext['meta'] : ($metaOverlay ?? $meta);
        $routeSelection = $this->routeSelector->selectForBooking($booking);
        $category = (string) ($routeSelection['category'] ?? SabreCertifiedRouteSelector::CATEGORY_UNKNOWN);
        $readiness = $this->certificationSupport->buildReadiness($booking);
        $tripType = $this->certificationSupport->detectTripType($booking);
        $segmentCount = max(0, (int) ($readiness['segment_count'] ?? 0));
        $handoff = is_array($meta['sabre_booking_context'] ?? null) ? $meta['sabre_booking_context'] : [];
        $selected = is_array($meta['selected_fare_family_option'] ?? null) ? $meta['selected_fare_family_option'] : [];
        $publicAutoCert = $this->publicAutoCertification->assess($booking);
        if ($scenarioRunner && ($contextCompletion['public_auto_pnr_attempt_ready'] ?? false) === true) {
            $publicAutoCert['connecting_brand_context_complete'] = true;
            $publicAutoCert['public_auto_certified'] = true;
            $publicAutoCert['public_auto_pnr_certified'] = true;
            $publicAutoCert['public_auto_block_reason'] = null;
        }
        $segmentContext = $this->resolvedSegmentContext($meta, $selected, $handoff, $segmentCount, $contextCompletion);
        $bookingClasses = $segmentContext['booking_classes_by_segment'];
        $fareBasisCodes = $segmentContext['fare_basis_codes_by_segment'];
        $cabins = $segmentContext['cabin_by_segment'];

        $digests = [];
        foreach ($this->registry->supportedCodes() as $strategyCode) {
            $definition = $this->registry->get($strategyCode);
            $wireStyle = $this->registry->wireStyleForStrategy($strategyCode);
            $rawWire = $this->payloadBuilder->buildPassengerRecordsCpnrWireForStyle($apiDraft, $hints, $wireStyle);
            $wire = $this->payloadBuilder->stripOtaInternalKeysFromBookingWire($rawWire);
            $envelopeDiag = $this->payloadBuilder->summarizeEnvelopeForDiagnostics($rawWire);
            $tradDiag = $this->payloadBuilder->summarizeTraditionalPnrWirePostBody(
                $wire,
                $meta,
                (string) ($definition['payload_schema'] ?? $strategyCode),
            );
            $haltCodes = $this->payloadBuilder->extractHaltOnStatusCodesFromCpnr(
                is_array($wire['CreatePassengerNameRecordRQ'] ?? null) ? $wire['CreatePassengerNameRecordRQ'] : [],
            );
            $snapshotSegs = array_values(is_array($snapshot['segments'] ?? null) ? $snapshot['segments'] : []);
            $segSell = $this->payloadBuilder->traditionalPnrAirBookSegmentSellDiagnostics($wire, $snapshotSegs);
            $sellRows = is_array($segSell['segments'] ?? null) ? $segSell['segments'] : [];
            $blankCount = 0;
            $completeCount = 0;
            foreach ($sellRows as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $origin = trim((string) ($row['origin'] ?? ''));
                $dest = trim((string) ($row['destination'] ?? ''));
                $mkt = trim((string) ($row['marketing_airline'] ?? ''));
                $fn = trim((string) ($row['flight_number'] ?? ''));
                if ($origin === '' || $dest === '' || $mkt === '' || $fn === '') {
                    $blankCount++;
                } else {
                    $completeCount++;
                }
            }
            $expectedSegs = max(count($snapshotSegs), count($sellRows));

            $missingFields = $this->missingRequiredFields($definition, $meta, $handoff, $segmentCount, $segmentContext);
            $contextReady = $missingFields === [] && $this->patternSupported($definition, $category, $tripType);
            $isMixedTrip = in_array($tripType, SabreGdsMixedCarrierCertificationGate::mixedCarrierTripTypes(), true);
            if (! $mixedCertApproved || ! $scenarioRunner) {
                if ($tripType === SabreGdsReturnTripClassifier::TRIP_RETURN_MIXED_CARRIER) {
                    $contextReady = false;
                    if (! in_array('return_mixed_carrier_not_automatic', $missingFields, true)) {
                        $missingFields[] = 'return_mixed_carrier_not_automatic';
                    }
                }
                if (in_array($tripType, [
                    SabreGdsOneWayTripShapeClassifier::TRIP_ONE_WAY_SINGLE_CONNECTION_MIXED_CARRIER,
                    SabreGdsOneWayTripShapeClassifier::TRIP_ONE_WAY_MULTISTOP_MIXED_CARRIER,
                ], true)) {
                    $contextReady = false;
                    if (! in_array('one_way_mixed_carrier_not_automatic', $missingFields, true)) {
                        $missingFields[] = 'one_way_mixed_carrier_not_automatic';
                    }
                }
            } elseif ($isMixedTrip && $strategyCode !== SabreGdsPnrCreateStrategyRegistry::STRATEGY_IATI_LIKE_CPNR_V2_4_GDS) {
                $contextReady = false;
                if (! in_array(SabreGdsMixedCarrierCertificationGate::REASON_STRATEGY_NOT_IATI, $missingFields, true)) {
                    $missingFields[] = SabreGdsMixedCarrierCertificationGate::REASON_STRATEGY_NOT_IATI;
                }
            }
            if (in_array($tripType, [
                SabreGdsOneWayTripShapeClassifier::TRIP_ONE_WAY_THREE_STOP_SAME_CARRIER,
                SabreGdsOneWayTripShapeClassifier::TRIP_ONE_WAY_FOUR_STOP_SAME_CARRIER,
            ], true)) {
                $contextReady = false;
                if (! in_array(SabreGdsOneWayTripShapeClassifier::ADVANCED_ITINERARY_PLAN_ONLY_BLOCK_REASON, $missingFields, true)) {
                    $missingFields[] = SabreGdsOneWayTripShapeClassifier::ADVANCED_ITINERARY_PLAN_ONLY_BLOCK_REASON;
                }
            }
            if (($contextCompletion['public_auto_pnr_attempt_ready'] ?? false) !== true) {
                $contextReady = false;
                if (! in_array('connecting_brand_context_incomplete', $missingFields, true)) {
                    $missingFields[] = 'connecting_brand_context_incomplete';
                }
            } elseif (($publicAutoCert['connecting_brand_context_complete'] ?? false) !== true
                && ($contextCompletion['connecting_brand_context_complete'] ?? false) !== true) {
                $contextReady = false;
                if (! in_array('connecting_brand_context_incomplete', $missingFields, true)) {
                    $missingFields[] = 'connecting_brand_context_incomplete';
                }
            }
            if ($strategyCode === SabreGdsPnrCreateStrategyRegistry::STRATEGY_MINIMAL_AIRBOOK_AIRPRICE_ENDTRANSACTION_GDS) {
                $contextReady = $contextReady
                    && ($envelopeDiag['wire_has_air_book'] ?? false) === true
                    && ($envelopeDiag['wire_has_air_price'] ?? $envelopeDiag['has_end_transaction'] ?? false) === true
                    && ($envelopeDiag['wire_post_processing_has_end_transaction'] ?? $envelopeDiag['has_end_transaction'] ?? false) === true;
            }

            $fareBasisPresent = (bool) ($envelopeDiag['has_fare_basis'] ?? false)
                || (bool) ($tradDiag['fare_basis_present'] ?? false)
                || (bool) ($tradDiag['wire_flight_segment_has_fare_basis_code'] ?? false)
                || (bool) ($tradDiag['wire_airprice_has_fare_basis'] ?? false)
                || (int) ($tradDiag['wire_fare_basis_count'] ?? 0) > 0;
            if ($isMixedTrip && $mixedCertApproved && $scenarioRunner
                && $strategyCode === SabreGdsPnrCreateStrategyRegistry::STRATEGY_IATI_LIKE_CPNR_V2_4_GDS
                && ! $fareBasisPresent) {
                $contextReady = false;
                if (! in_array(SabreGdsMixedCarrierFareBasisPayloadPreflight::REASON_PAYLOAD_MAPPING_INCOMPLETE, $missingFields, true)) {
                    $missingFields[] = SabreGdsMixedCarrierFareBasisPayloadPreflight::REASON_PAYLOAD_MAPPING_INCOMPLETE;
                }
            }

            $validatingCarrierPresent = (bool) ($envelopeDiag['has_validating_carrier'] ?? false)
                || (bool) ($tradDiag['validating_carrier_present'] ?? false)
                || (bool) ($tradDiag['wire_airprice_has_validating_carrier'] ?? false);
            $manualTicketingMarkerPresent = (bool) ($tradDiag['manual_ticketing_marker_present'] ?? false)
                || (bool) ($tradDiag['wire_traditional_manual_ticketing_marker_present'] ?? false);

            $selectedBySelector = is_array($selection)
                && (string) ($selection['selected_strategy'] ?? '') === $strategyCode;
            $selectionReason = $selectedBySelector ? (string) ($selection['selection_reason'] ?? '') : '';

            $digests[] = $this->sanitizeDigest([
                'strategy_code' => $strategyCode,
                'endpoint_path' => $this->registry->endpointPathForStrategy($strategyCode),
                'payload_schema' => (string) ($definition['payload_schema'] ?? $strategyCode),
                'automatic_allowed' => (bool) ($definition['automatic_allowed'] ?? false),
                'admin_confirmed_fallback_allowed' => (bool) ($definition['admin_confirmed_fallback_allowed'] ?? true),
                'context_ready' => $contextReady,
                'required_fields_present' => $missingFields === [],
                'missing_fields' => $missingFields,
                'segment_rows_complete' => $expectedSegs > 0 && $completeCount >= $expectedSegs && $blankCount === 0,
                'blank_segment_rows_present' => $blankCount > 0,
                'fare_basis_present' => $fareBasisPresent,
                'validating_carrier_present' => $validatingCarrierPresent,
                'booking_class_present' => (bool) ($envelopeDiag['has_booking_class'] ?? $tradDiag['wire_flight_segment_has_res_book_desig_code'] ?? false),
                'air_book_present' => (bool) ($envelopeDiag['wire_has_air_book'] ?? $tradDiag['wire_has_air_book'] ?? false),
                'air_price_present' => (bool) ($envelopeDiag['wire_has_air_price'] ?? $tradDiag['wire_has_air_price'] ?? false),
                'end_transaction_present' => (bool) ($envelopeDiag['wire_post_processing_has_end_transaction'] ?? $envelopeDiag['has_end_transaction'] ?? false),
                'received_from_present' => (bool) ($envelopeDiag['wire_has_received_from'] ?? $tradDiag['wire_has_received_from'] ?? false),
                'ticketing_time_limit_present' => $manualTicketingMarkerPresent
                    || (bool) ($tradDiag['ticketing_time_limit_present'] ?? false),
                'manual_ticketing_marker_present' => $manualTicketingMarkerPresent,
                'halt_on_status_policy' => $haltCodes,
                'nn_not_self_blocking' => ! in_array('NN', $haltCodes, true),
                'estimated_duplicate_risk' => (string) ($definition['duplicate_risk_level'] ?? 'medium'),
                'selected_by_selector' => $selectedBySelector,
                'selection_reason' => $selectionReason !== '' ? $selectionReason : null,
            ]);
        }

        return $digests;
    }

    /**
     * @return array<string, mixed>
     */
    public function buildFullDigest(Booking $booking, ?array $selection = null): array
    {
        return [
            'summary' => $this->buildBookingSummary($booking),
            'candidates' => $this->buildCandidateDigests($booking, $selection),
            'selection' => $selection,
        ];
    }

    /**
     * @param  array<string, mixed>  $definition
     * @param  array<string, mixed>  $meta
     * @param  array<string, mixed>  $handoff
     * @param  array{
     *     booking_classes_by_segment: list<string>,
     *     fare_basis_codes_by_segment: list<string>,
     *     cabin_by_segment: list<string>
     * }  $segmentContext
     * @return list<string>
     */
    protected function missingRequiredFields(array $definition, array $meta, array $handoff, int $segmentCount, array $segmentContext): array
    {
        $selected = is_array($meta['selected_fare_family_option'] ?? null) ? $meta['selected_fare_family_option'] : [];
        $bookingClasses = $segmentContext['booking_classes_by_segment'];
        $fareBasisCodes = $segmentContext['fare_basis_codes_by_segment'];
        $cabins = $segmentContext['cabin_by_segment'];

        $missing = [];
        foreach ($definition['required_context_fields'] ?? [] as $field) {
            if (! is_string($field)) {
                continue;
            }
            $present = match ($field) {
                'supplier_connection_id' => (int) ($meta['supplier_connection_id'] ?? 0) > 0,
                'validating_carrier' => trim((string) ($handoff['validating_carrier'] ?? $meta['validating_carrier'] ?? '')) !== '',
                'segments' => is_array($meta['normalized_offer_snapshot']['segments'] ?? null)
                    && count($meta['normalized_offer_snapshot']['segments']) > 0,
                'sabre_booking_context' => $handoff !== [],
                default => true,
            };
            if (! $present) {
                $missing[] = $field;
            }
        }

        foreach ($definition['required_selected_fare_fields'] ?? [] as $field) {
            if (! is_string($field)) {
                continue;
            }
            $present = match ($field) {
                'brand_code' => trim((string) ($handoff['selected_brand_code'] ?? $handoff['brand_code'] ?? $selected['brand_code'] ?? '')) !== '',
                'fare_basis_codes_by_segment' => $this->publicAutoCertification->perSegmentStringListComplete($fareBasisCodes, max(1, $segmentCount)),
                'booking_classes_by_segment' => $this->publicAutoCertification->perSegmentStringListComplete($bookingClasses, max(1, $segmentCount)),
                default => true,
            };
            if (! $present) {
                $missing[] = $field;
            }
        }

        if ($segmentCount >= 2 && ! $this->publicAutoCertification->perSegmentStringListComplete($cabins, $segmentCount)) {
            $missing[] = 'cabin_by_segment';
        }

        return array_values(array_unique($missing));
    }

    /**
     * @param  array<string, mixed>  $meta
     * @param  array<string, mixed>  $selected
     * @param  array<string, mixed>  $handoff
     * @param  array<string, mixed>  $contextCompletion
     * @return array{
     *     booking_classes_by_segment: list<string>,
     *     fare_basis_codes_by_segment: list<string>,
     *     cabin_by_segment: list<string>
     * }
     */
    protected function resolvedSegmentContext(
        array $meta,
        array $selected,
        array $handoff,
        int $segmentCount,
        array $contextCompletion,
    ): array {
        if (($contextCompletion['public_auto_pnr_attempt_ready'] ?? false) === true) {
            return [
                'booking_classes_by_segment' => is_array($contextCompletion['completed_booking_classes_by_segment'] ?? null)
                    ? $contextCompletion['completed_booking_classes_by_segment']
                    : [],
                'fare_basis_codes_by_segment' => is_array($contextCompletion['completed_fare_basis_codes_by_segment'] ?? null)
                    ? $contextCompletion['completed_fare_basis_codes_by_segment']
                    : [],
                'cabin_by_segment' => is_array($contextCompletion['completed_cabin_by_segment'] ?? null)
                    ? $contextCompletion['completed_cabin_by_segment']
                    : [],
            ];
        }

        return $this->publicAutoCertification->resolveMergedSegmentContext($selected, $handoff, $meta, $segmentCount);
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    protected function patternSupported(array $definition, string $category, string $tripType = ''): bool
    {
        $strategyCode = trim((string) ($definition['strategy_code'] ?? ''));

        return $strategyCode !== ''
            ? $this->registry->patternSupported($strategyCode, $category, $tripType)
            : false;
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    protected function offerSnapshotFromMeta(array $meta): array
    {
        if (is_array($meta['normalized_offer_snapshot'] ?? null)) {
            return $meta['normalized_offer_snapshot'];
        }
        if (is_array($meta['validated_offer_snapshot'] ?? null)) {
            return $meta['validated_offer_snapshot'];
        }

        return is_array($meta['flight_offer_snapshot'] ?? null) ? $meta['flight_offer_snapshot'] : [];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    protected function firstFareBasis(array $row): string
    {
        $list = is_array($row['fare_basis_codes_by_segment'] ?? null) ? $row['fare_basis_codes_by_segment'] : [];
        if ($list !== [] && trim((string) $list[0]) !== '') {
            return strtoupper(trim((string) $list[0]));
        }

        return strtoupper(trim((string) ($row['fare_basis'] ?? '')));
    }

    /**
     * @param  array<string, mixed>  $meta
     * @param  array<string, mixed>  $selected
     */
    protected function resolveSelectedTotal(Booking $booking, array $meta, array $selected): ?float
    {
        if (isset($selected['displayed_price']) && is_numeric($selected['displayed_price'])) {
            return (float) $selected['displayed_price'];
        }
        if ((float) ($booking->selected_fare_total ?? 0) > 0) {
            return (float) $booking->selected_fare_total;
        }
        if (isset($meta['selected_fare_total']) && is_numeric($meta['selected_fare_total'])) {
            return (float) $meta['selected_fare_total'];
        }

        return null;
    }

    /**
     * @return array{
     *     previous_attempt_failed: bool,
     *     previous_failed_strategy: string|null,
     *     previous_host_error_family: string|null,
     *     safe_retry_requires_admin_confirmation: bool
     * }
     */
    protected function resolvePreviousAttemptContext(Booking $booking): array
    {
        $attempt = SupplierBookingAttempt::query()
            ->where('booking_id', $booking->id)
            ->where('provider', SupplierProvider::Sabre->value)
            ->orderByDesc('id')
            ->first();

        if ($attempt === null) {
            return [
                'previous_attempt_failed' => false,
                'previous_failed_strategy' => null,
                'previous_host_error_family' => null,
                'safe_retry_requires_admin_confirmation' => false,
            ];
        }

        $status = strtolower((string) $attempt->status);
        $failed = in_array($status, ['failed', 'manual_review', 'needs_review'], true);
        $safe = is_array($attempt->safe_summary) ? $attempt->safe_summary : [];
        $strategy = trim((string) ($safe['payload_schema'] ?? $safe['payload_style'] ?? ''));
        $hostFamily = trim((string) ($safe['host_error_family'] ?? ''));
        $messages = is_array($safe['response_error_messages'] ?? null) ? $safe['response_error_messages'] : [];
        $classification = $this->resultClassifier->classify(array_merge($safe, [
            'response_error_messages' => $messages,
        ]));
        if ($classification['host_error_family'] !== null) {
            $hostFamily = (string) $classification['host_error_family'];
            $failed = true;
        }

        return [
            'previous_attempt_failed' => $failed,
            'previous_failed_strategy' => $strategy !== '' ? $strategy : null,
            'previous_host_error_family' => $hostFamily !== '' ? $hostFamily : null,
            'safe_retry_requires_admin_confirmation' => $failed,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $selection
     * @return array<string, mixed>
     */
    protected function emptyCandidateDigest(string $strategyCode, string $reason, ?array $selection): array
    {
        $definition = $this->registry->get($strategyCode);

        return $this->sanitizeDigest([
            'strategy_code' => $strategyCode,
            'endpoint_path' => $this->registry->endpointPathForStrategy($strategyCode),
            'payload_schema' => (string) ($definition['payload_schema'] ?? $strategyCode),
            'automatic_allowed' => (bool) ($definition['automatic_allowed'] ?? false),
            'admin_confirmed_fallback_allowed' => (bool) ($definition['admin_confirmed_fallback_allowed'] ?? true),
            'context_ready' => false,
            'required_fields_present' => false,
            'missing_fields' => [$reason],
            'segment_rows_complete' => false,
            'blank_segment_rows_present' => false,
            'fare_basis_present' => false,
            'validating_carrier_present' => false,
            'booking_class_present' => false,
            'air_book_present' => false,
            'air_price_present' => false,
            'end_transaction_present' => false,
            'received_from_present' => false,
            'ticketing_time_limit_present' => false,
            'halt_on_status_policy' => [],
            'nn_not_self_blocking' => true,
            'estimated_duplicate_risk' => (string) ($definition['duplicate_risk_level'] ?? 'medium'),
            'selected_by_selector' => is_array($selection) && (string) ($selection['selected_strategy'] ?? '') === $strategyCode,
            'selection_reason' => null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $digest
     * @return array<string, mixed>
     */
    protected function sanitizeDigest(array $digest): array
    {
        $json = json_encode($digest);
        if (! is_string($json)) {
            return $digest;
        }
        $lower = strtolower($json);
        foreach (self::FORBIDDEN_OUTPUT_KEYS as $forbidden) {
            if (str_contains($lower, $forbidden)) {
                throw new \RuntimeException('Strategy digest leaked forbidden key: '.$forbidden);
            }
        }

        return $digest;
    }
}
