<?php

namespace App\Support\Bookings;

use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Services\Suppliers\Sabre\SabrePnrRetrieveProbe;
use App\Services\Suppliers\Sabre\SabreStoredPricingContextDigest;
use App\Support\Sabre\GdsPnrCreate\SabreGdsOneWayTripShapeClassifier;
use App\Support\Sabre\GdsPnrCreate\SabreGdsReturnTripClassifier;
use App\Support\Suppliers\SabrePassengerRecordsMultiSegmentSellVerifier;
use Carbon\Carbon;

/**
 * C1: Trip-type matrix, safe readiness, and PNR expiry extraction for certification CLI.
 */
final class SabrePnrCertificationSupport
{
    public const ACTION_CERTIFICATION = 'create_pnr_certification';

    public const META_EXPIRES_AT = 'supplier_pnr_expires_at';

    public const META_EXPIRY_SOURCE = 'supplier_pnr_expiry_source';

    public const META_EXPIRY_SYNCED_AT = 'supplier_pnr_expiry_synced_at';

    /** P2c: Compact revalidation linkage applied only for certification PNR attempts (not public checkout). */
    public const META_CERTIFICATION_REVALIDATE_LINKAGE = 'sabre_certification_revalidate_linkage';

    public const META_CERTIFICATION_REVALIDATE_AT = 'sabre_certification_revalidate_at';

    public function __construct(
        protected SabrePnrRetrieveProbe $retrieveProbe,
        protected SabreStoredPricingContextDigest $pricingContextDigest,
    ) {}

    public function detectTripType(Booking $booking): string
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $criteria = is_array($meta['search_criteria'] ?? null) ? $meta['search_criteria'] : [];
        $readiness = $this->buildReadiness($booking);
        $returnClassifier = app(SabreGdsReturnTripClassifier::class);
        $returnDetected = $returnClassifier->detectTripType($booking, $readiness);
        if (in_array($returnDetected, [
            SabreGdsReturnTripClassifier::TRIP_RETURN_SAME_CARRIER,
            SabreGdsReturnTripClassifier::TRIP_RETURN_MIXED_CARRIER,
        ], true)) {
            return $returnDetected;
        }

        $oneWayClassifier = app(SabreGdsOneWayTripShapeClassifier::class);
        $oneWayDetected = $oneWayClassifier->detectTripType($booking, $readiness);
        if (in_array($oneWayDetected, SabreGdsOneWayTripShapeClassifier::knownOneWayTripTypes(), true)) {
            return $oneWayDetected;
        }

        $tripType = strtolower(trim((string) ($criteria['trip_type'] ?? '')));

        if ($tripType === 'multi_city') {
            return 'multi_city';
        }

        $criteriaSegments = $criteria['segments'] ?? null;
        if (is_array($criteriaSegments) && $criteriaSegments !== []) {
            return 'multi_city';
        }

        if (in_array($tripType, ['round_trip', 'return'], true)) {
            return 'round_trip';
        }

        if ($tripType === '' && $this->journeyGroupCount($meta) > 1) {
            return 'round_trip';
        }

        $segmentCount = $this->segmentCountFromBooking($booking);
        if ($tripType === 'one_way' || ($tripType === '' && $segmentCount > 0)) {
            return $segmentCount <= 1 ? 'one_way_direct' : 'one_way_connecting';
        }

        return 'unknown';
    }

    /**
     * @return array<string, mixed>
     */
    public function buildReadiness(Booking $booking): array
    {
        $booking->loadMissing(['passengers', 'contact', 'fareBreakdown']);
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $snapshot = $this->resolveSnapshot($booking);
        $segments = array_values(is_array($snapshot['segments'] ?? null) ? $snapshot['segments'] : []);

        $routeChain = [];
        $carrierChain = [];
        $rbdList = [];
        $fareBasisList = [];

        foreach ($segments as $seg) {
            if (! is_array($seg)) {
                continue;
            }
            $origin = strtoupper(trim((string) ($seg['origin'] ?? '')));
            $dest = strtoupper(trim((string) ($seg['destination'] ?? '')));
            if ($origin !== '') {
                $routeChain[] = $origin;
            }
            if ($dest !== '') {
                $routeChain[] = $dest;
            }
            $carrier = strtoupper(trim((string) ($seg['carrier'] ?? $seg['airline_code'] ?? $seg['marketing_carrier'] ?? '')));
            if ($carrier !== '') {
                $carrierChain[] = $carrier;
            }
            $rbd = strtoupper(trim((string) (
                $seg['booking_class'] ?? $seg['class_of_service'] ?? $seg['rbd'] ?? $seg['resBookDesigCode'] ?? ''
            )));
            if ($rbd !== '') {
                $rbdList[] = $rbd;
            }
            $fb = strtoupper(trim((string) ($seg['fare_basis_code'] ?? $seg['fareBasisCode'] ?? '')));
            if ($fb !== '') {
                $fareBasisList[] = $fb;
            }
        }

        $routeChain = $this->dedupeAdjacentAirports($routeChain);
        $validatingCarrier = strtoupper(trim((string) (
            $snapshot['validating_carrier']
            ?? $snapshot['validating_airline']
            ?? $meta['validating_carrier']
            ?? ''
        )));
        $hasCodeshareSegment = false;
        foreach ($segments as $seg) {
            if (! is_array($seg)) {
                continue;
            }
            $marketing = strtoupper(trim((string) ($seg['carrier'] ?? $seg['airline_code'] ?? $seg['marketing_carrier'] ?? '')));
            $operating = strtoupper(trim((string) ($seg['operating_carrier'] ?? $seg['operating_airline'] ?? '')));
            if ($marketing !== '' && $operating !== '' && $marketing !== $operating) {
                $hasCodeshareSegment = true;
                break;
            }
        }
        $firstMarketing = '';
        foreach ($carrierChain as $c) {
            if ($c !== '') {
                $firstMarketing = $c;
                break;
            }
        }
        $validatingCarrierMismatch = $validatingCarrier !== ''
            && $firstMarketing !== ''
            && $validatingCarrier !== $firstMarketing;

        $hasFareTotal = $booking->fareBreakdown !== null
            || (isset($snapshot['total']) && is_numeric($snapshot['total']))
            || trim((string) ($booking->total_amount ?? '')) !== '';

        $contact = $booking->contact;

        return [
            'segment_count' => count($segments),
            'route_chain' => $routeChain,
            'carrier_chain' => array_values(array_unique($carrierChain)),
            'rbd_list' => array_values(array_unique($rbdList)),
            'fare_basis_list' => array_values(array_unique($fareBasisList)),
            'validating_carrier' => $validatingCarrier !== '' ? $validatingCarrier : null,
            'has_passenger' => $booking->passengers->isNotEmpty(),
            'has_contact' => $contact !== null
                && (trim((string) $contact->email) !== '' || trim((string) $contact->phone) !== ''),
            'has_fare_total' => $hasFareTotal,
            'complex_itinerary' => ComplexItineraryPolicy::isComplex($booking),
            'has_codeshare_segment' => $hasCodeshareSegment,
            'validating_carrier_mismatch' => $validatingCarrierMismatch,
        ];
    }

    /**
     * P2c: Certification-only — whether to run revalidation before Passenger Records (public checkout unchanged).
     *
     * @param  array<string, mixed>  $pricingReadiness  {@see SabreStoredPricingContextDigest::assessReadiness()}
     * @param  array<string, mixed>  $readiness  {@see self::buildReadiness()}
     * @return array{required: bool, reasons: list<string>, exempt: bool, exempt_reason: ?string}
     */
    public function certificationRevalidatePolicy(array $pricingReadiness, array $readiness): array
    {
        if ($this->isSimpleOneWaySameCarrierForCertification($readiness)) {
            return [
                'required' => false,
                'reasons' => [],
                'exempt' => true,
                'exempt_reason' => 'simple_one_way_same_carrier',
            ];
        }

        $reasons = [];
        if ((int) ($readiness['segment_count'] ?? 0) > 1) {
            $reasons[] = 'multi_segment';
        }
        if (($readiness['validating_carrier_mismatch'] ?? false) === true) {
            $reasons[] = 'validating_carrier_mismatch';
        }
        if (($readiness['has_codeshare_segment'] ?? false) === true) {
            $reasons[] = 'codeshare_segment';
        }
        if (($pricingReadiness['auto_pnr_pricing_context_ready'] ?? false) !== true) {
            $reasons[] = 'missing_pricing_context';
        }

        return [
            'required' => $reasons !== [],
            'reasons' => $reasons,
            'exempt' => false,
            'exempt_reason' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $readiness
     */
    /**
     * Sprint 11A/12A: Admin-safe multi-segment / mixed-carrier PNR readiness (snapshot only; no live Sabre, no PII).
     *
     * @return array<string, mixed>
     */
    public function buildMultiSegmentPnrReadinessDiagnostics(Booking $booking): array
    {
        if (! $this->isSabreBooking($booking)) {
            return ['multi_segment_candidate' => false];
        }

        $booking->loadMissing(['passengers']);
        $readiness = $this->buildReadiness($booking);
        $tripType = $this->detectTripType($booking);
        $routeSelection = app(SabreCertifiedRouteSelector::class)->selectForBooking($booking);
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $snapshot = $this->firstSnapshotFromMeta($meta);
        $segments = array_values(is_array($snapshot['segments'] ?? null) ? $snapshot['segments'] : []);
        $segCount = (int) ($readiness['segment_count'] ?? count($segments));
        $carriers = is_array($readiness['carrier_chain'] ?? null) ? $readiness['carrier_chain'] : [];
        $mixedCarrier = count($carriers) > 1;
        $marketingBySegment = [];
        $operatingBySegment = [];
        $operatingMissing = 0;
        foreach ($segments as $seg) {
            if (! is_array($seg)) {
                continue;
            }
            $mkt = strtoupper(trim((string) ($seg['carrier'] ?? $seg['airline_code'] ?? $seg['marketing_carrier'] ?? '')));
            $op = strtoupper(trim((string) ($seg['operating_carrier'] ?? $seg['operating_airline'] ?? $seg['operating_airline_code'] ?? '')));
            $marketingBySegment[] = $mkt !== '' ? $mkt : '—';
            $operatingBySegment[] = $op !== '' ? $op : '—';
            if ($mkt !== '' && $op === '') {
                $operatingMissing++;
            }
        }
        $handoff = $this->sabreBookingContextFromSnapshot($snapshot, $meta);
        $wireCoverage = $this->segmentWireBookingCoverage($segments, $handoff);
        $rbdComplete = $segCount > 0 && (int) ($wireCoverage['rbd_present_count'] ?? 0) >= $segCount;
        $fareBasisComplete = $segCount > 0 && (int) ($wireCoverage['fare_basis_present_count'] ?? 0) >= $segCount;
        $segmentContextComplete = $this->segmentSellContextCompleteFromSnapshot($segments);
        $pricingSnapshot = $snapshot !== [] ? $this->enrichSnapshotForPricingAssessment($snapshot, $meta) : [];
        $pricingReadiness = $pricingSnapshot !== [] ? $this->pricingContextDigest->assessReadiness($pricingSnapshot) : [];
        $b65 = SabrePassengerRecordsMultiSegmentSellVerifier::evaluate($snapshot, $segments);
        $proposedCategory = $this->proposeMultiSegmentCertificationCategory($tripType, $readiness, $mixedCarrier, $segCount);
        $codesharePresent = ($readiness['has_codeshare_segment'] ?? false) === true;
        $connectingCandidate = $segCount === 2
            && ! $mixedCarrier
            && ! $codesharePresent
            && ($readiness['validating_carrier_mismatch'] ?? false) !== true
            && in_array($proposedCategory, [
                'one_way_connecting_same_carrier_gds',
                SabreCertifiedRouteSelector::CATEGORY_ONE_WAY_CONNECTING_SAME_CARRIER_GDS,
            ], true);
        $connectingEnabled = SabreCertifiedRouteSelector::isConnectingSameCarrierGdsEnabled();
        $connectingPublicCheckout = SabreCertifiedRouteSelector::isConnectingSameCarrierPublicCheckoutEnabled();

        $wireBlockers = [];
        if (! $rbdComplete) {
            $wireBlockers[] = 'rbd_incomplete';
        }
        if (! $fareBasisComplete) {
            $wireBlockers[] = 'fare_basis_incomplete';
        }
        if (! $segmentContextComplete) {
            $wireBlockers[] = 'segment_context_incomplete';
        }
        if (trim((string) ($readiness['validating_carrier'] ?? '')) === '') {
            $wireBlockers[] = 'missing_validating_carrier';
        }

        $policyBlockers = [];
        if (ComplexItineraryPolicy::isComplex($booking)) {
            $policyBlockers[] = 'complex_itinerary';
        }
        if ($tripType === 'round_trip') {
            $policyBlockers[] = 'round_trip';
        }
        if ($tripType === 'multi_city') {
            $policyBlockers[] = 'multi_city';
        }
        $routeStatus = (string) ($routeSelection['route_status'] ?? '');
        $routeCategory = (string) ($routeSelection['category'] ?? '');
        if (($routeSelection['live_booking_allowed'] ?? false) !== true) {
            $policyBlockers[] = 'certified_route_'.$routeCategory;
            if ($routeStatus !== '') {
                $policyBlockers[] = 'certified_route_status_'.$routeStatus;
            }
        }
        $controlledPnrCertificationStatus = (string) (
            $routeSelection['controlled_pnr_certification_status']
            ?? SabreCertifiedRouteSelector::CONTROLLED_PNR_UNKNOWN_CONTROLLED_ONLY
        );
        if ($controlledPnrCertificationStatus === SabreCertifiedRouteSelector::CONTROLLED_PNR_HOST_NOOP_BLOCKED) {
            $policyBlockers[] = 'controlled_pnr_host_noop_blocked';
        }
        if ($segCount >= 2 && ! SabrePassengerRecordsMultiSegmentSellVerifier::isMultiSegmentPassengerRecordsEvaluationEnabled($segCount)) {
            $policyBlockers[] = 'multi_segment_config_disabled';
        }
        if ($connectingCandidate && ! $connectingEnabled) {
            $policyBlockers[] = 'connecting_same_carrier_gds_disabled';
        }
        if ($segCount >= 2 && (bool) config('suppliers.sabre.passenger_records_block_risky_itinerary_live', true)) {
            if (($b65['passenger_records_multi_segment_eligible'] ?? false) !== true) {
                $policyBlockers[] = 'passenger_records_risky_itinerary_guard';
            }
        }
        if (($readiness['validating_carrier_mismatch'] ?? false) === true) {
            $policyBlockers[] = 'validating_carrier_mismatch';
        }
        if (($readiness['has_codeshare_segment'] ?? false) === true) {
            $policyBlockers[] = 'codeshare_segment';
        }
        if ($snapshot !== [] && ($pricingReadiness['auto_pnr_pricing_context_ready'] ?? false) !== true) {
            $policyBlockers[] = 'pricing_context_incomplete';
        }
        if ($segCount >= 2 && ($b65['passenger_records_multi_segment_eligible'] ?? false) !== true) {
            foreach (is_array($b65['passenger_records_multi_segment_validation_failed_reasons'] ?? null)
                ? $b65['passenger_records_multi_segment_validation_failed_reasons']
                : [] as $reason) {
                $policyBlockers[] = 'b65_'.$reason;
            }
        }

        $blockers = array_values(array_unique(array_merge($wireBlockers, $policyBlockers)));

        $payloadReady = $segCount >= 2
            && $rbdComplete
            && $fareBasisComplete
            && $segmentContextComplete
            && trim((string) ($readiness['validating_carrier'] ?? '')) !== '';

        $iatiLikeMultiReady = $payloadReady && $wireBlockers === [];
        $iatiLikeConnectingReady = $connectingCandidate
            && $connectingEnabled
            && $iatiLikeMultiReady
            && ($b65['passenger_records_multi_segment_eligible'] ?? false) === true
            && in_array($routeStatus, [
                SabreCertifiedRouteSelector::STATUS_CERTIFIED,
                SabreCertifiedRouteSelector::STATUS_CONTROLLED_CERTIFIED,
            ], true);
        $routeAllowsAdminRetry = $connectingCandidate
            && $connectingEnabled
            && ($routeSelection['admin_staff_pnr_retry_allowed'] ?? false) === true;
        $pricingContextReady = ($pricingReadiness['auto_pnr_pricing_context_ready'] ?? false) === true;
        $adminStaffPnrReadinessPassed = $routeAllowsAdminRetry
            && $iatiLikeMultiReady
            && $pricingContextReady;
        $adminPnrLiveActionAllowed = $adminStaffPnrReadinessPassed && $iatiLikeConnectingReady;
        $contextRefreshAvailable = $connectingCandidate
            && $connectingEnabled
            && $routeAllowsAdminRetry
            && ! $pricingContextReady;

        $pricingMissing = is_array($pricingReadiness['missing_pricing_context_fields'] ?? null)
            ? $pricingReadiness['missing_pricing_context_fields']
            : [];
        $shopLinkage = $this->buildShopLinkageDiagnostics($pricingSnapshot !== [] ? $pricingSnapshot : $snapshot, $meta);
        $bfmPolicy = $pricingSnapshot !== [] ? $this->pricingContextDigest->assessBfmV4LinkagePolicy($pricingSnapshot) : [];

        $validatingCarrierPresent = trim((string) ($readiness['validating_carrier'] ?? '')) !== '';
        $mixedCarrierCandidate = $segCount === 2 && $mixedCarrier && $tripType === 'one_way_connecting';
        $interlineCandidate = $mixedCarrierCandidate && ! $codesharePresent;
        $proposedMixedCategory = $mixedCarrierCandidate ? 'one_way_connecting_mixed_carrier_gds' : null;
        $mixedCarrierReadinessBlockers = $mixedCarrierCandidate
            ? array_values(array_unique(array_slice($blockers, 0, 24)))
            : [];

        return [
            'multi_segment_candidate' => $segCount >= 2,
            'connecting_same_carrier_candidate' => $connectingCandidate,
            'connecting_same_carrier_enabled' => $connectingEnabled,
            'connecting_same_carrier_public_checkout_enabled' => $connectingPublicCheckout,
            'segment_count' => $segCount,
            'carrier_chain' => implode('→', $carriers),
            'validating_carrier' => (string) ($readiness['validating_carrier'] ?? ''),
            'mixed_carrier' => $mixedCarrier,
            'mixed_carrier_candidate' => $mixedCarrierCandidate,
            'marketing_carriers_by_segment' => implode('→', $marketingBySegment),
            'operating_carriers_by_segment' => implode('→', $operatingBySegment),
            'interline_candidate' => $interlineCandidate,
            'validating_carrier_present' => $validatingCarrierPresent,
            'mixed_carrier_readiness_blockers' => $mixedCarrierReadinessBlockers,
            'proposed_mixed_carrier_category' => $proposedMixedCategory,
            'mixed_carrier_public_checkout_enabled' => false,
            'mixed_carrier_admin_enabled' => false,
            'mixed_carrier_next_step' => $mixedCarrierCandidate ? 'inspection_only' : null,
            'codeshare_present' => $codesharePresent,
            'operating_carrier_missing_count' => $operatingMissing,
            'rbd_complete' => $rbdComplete,
            'fare_basis_complete' => $fareBasisComplete,
            'segment_context_complete' => $segmentContextComplete,
            'iati_like_multi_segment_ready' => $iatiLikeMultiReady,
            'iati_like_connecting_ready' => $iatiLikeConnectingReady,
            'proposed_certification_category' => $proposedCategory,
            'certified_route_category' => $routeCategory,
            'certified_route_status' => $routeStatus,
            'controlled_pnr_certification_status' => $controlledPnrCertificationStatus,
            'controlled_pnr_certification_key' => $routeSelection['controlled_pnr_certification_key'] ?? null,
            'controlled_pnr_verified_booking_id' => $routeSelection['controlled_pnr_verified_booking_id'] ?? null,
            'controlled_pnr_verified_pnr_present' => ($routeSelection['controlled_pnr_verified_pnr_present'] ?? false) === true,
            'controlled_pnr_airline_locator_present' => ($routeSelection['controlled_pnr_airline_locator_present'] ?? false) === true,
            'controlled_pnr_ticketing_enabled' => ($routeSelection['controlled_pnr_ticketing_enabled'] ?? false) === true,
            'controlled_pnr_carrier_chain' => (string) ($routeSelection['controlled_pnr_carrier_chain'] ?? ''),
            'controlled_pnr_origin' => (string) ($routeSelection['controlled_pnr_origin'] ?? ''),
            'controlled_pnr_connection_airport' => (string) ($routeSelection['controlled_pnr_connection_airport'] ?? ''),
            'controlled_pnr_destination' => (string) ($routeSelection['controlled_pnr_destination'] ?? ''),
            'trip_type_detected' => $tripType,
            'passenger_records_multi_segment_enabled' => (bool) ($b65['passenger_records_multi_segment_enabled'] ?? false),
            'passenger_records_multi_segment_eligible' => (bool) ($b65['passenger_records_multi_segment_eligible'] ?? false),
            'admin_staff_pnr_retry_allowed' => $adminStaffPnrReadinessPassed,
            'admin_staff_pnr_readiness_passed' => $adminStaffPnrReadinessPassed,
            'admin_staff_pnr_retry_route_allowed' => $routeAllowsAdminRetry,
            'admin_pnr_live_action_allowed' => $adminPnrLiveActionAllowed,
            'context_refresh_available' => $contextRefreshAvailable,
            'rbd_present_count' => (int) ($wireCoverage['rbd_present_count'] ?? 0),
            'rbd_total_segments' => (int) ($wireCoverage['rbd_total_segments'] ?? $segCount),
            'rbd_source' => (string) ($wireCoverage['rbd_source'] ?? ''),
            'fare_basis_present_count' => (int) ($wireCoverage['fare_basis_present_count'] ?? 0),
            'fare_basis_missing_count' => (int) ($wireCoverage['fare_basis_missing_count'] ?? 0),
            'fare_basis_source' => (string) ($wireCoverage['fare_basis_source'] ?? ''),
            'pricing_context_ready' => $pricingContextReady,
            'pricing_context_missing_fields' => array_values(array_slice($pricingMissing, 0, 12)),
            'pricing_context_policy' => (string) ($pricingReadiness['pricing_context_policy'] ?? $bfmPolicy['pricing_context_policy_used'] ?? ''),
            'bfm_itinerary_reference_present' => ($pricingReadiness['bfm_itinerary_reference_present'] ?? false) === true,
            'bfm_pricing_information_index_present' => ($pricingReadiness['bfm_pricing_information_index_present'] ?? false) === true,
            'bfm_pricing_information_index' => $pricingReadiness['bfm_pricing_information_index'] ?? null,
            'formal_offer_reference_required' => ($pricingReadiness['formal_offer_reference_required'] ?? true) === true ? 'yes' : 'no',
            'formal_pricing_information_ref_required' => ($pricingReadiness['formal_pricing_information_ref_required'] ?? true) === true ? 'yes' : 'no',
            'shop_identifiers_present' => ($shopLinkage['shop_identifiers_present'] ?? false) === true,
            'pricing_information_ref_present' => ($shopLinkage['pricing_information_ref_present'] ?? false) === true,
            'offer_reference_present' => ($shopLinkage['offer_reference_present'] ?? false) === true,
            'itinerary_reference_present' => ($shopLinkage['itinerary_reference_present'] ?? false) === true,
            'bfm_pricing_information_index_present_diag' => ($shopLinkage['bfm_pricing_information_index_present'] ?? false) === true,
            'pricing_linkage_source' => (string) ($shopLinkage['pricing_linkage_source'] ?? 'missing'),
            'context_can_be_rebuilt' => ($shopLinkage['context_can_be_rebuilt'] ?? false) === true,
            'priced_itinerary_sequence_present' => ($bfmPolicy['priced_itinerary_sequence_present'] ?? false) === true,
            'air_pricing_info_index_present' => ($bfmPolicy['air_pricing_info_index_present'] ?? false) === true,
            'offer_reference_unavailable_in_bfm_v4' => ($bfmPolicy['offer_reference_unavailable_in_bfm_v4'] ?? false) === true,
            'pricing_context_policy_used' => (string) ($pricingReadiness['pricing_context_policy'] ?? $bfmPolicy['pricing_context_policy_used'] ?? ''),
            'bfm_index_linkage_sufficient' => ($bfmPolicy['bfm_index_linkage_sufficient'] ?? false) === true,
            're_shop_required' => ($bfmPolicy['re_shop_required'] ?? false) === true,
            'controlled_certification_required' => $connectingCandidate && $connectingEnabled && ! $connectingPublicCheckout,
            'multi_segment_blocker_reasons' => array_values(array_unique(array_slice($blockers, 0, 24))),
            'blocker_reasons' => array_values(array_unique(array_slice($blockers, 0, 24))),
        ];
    }

    /**
     * E1B: Admin/staff controlled same-carrier connecting PNR may bypass historical
     * {@code meta.defer_supplier_booking_to_manual_review} without clearing it.
     *
     * @param  'public_checkout'|'admin'|'staff'|'system'|'manual'  $source
     */
    public function allowsControlledStaffPnrBypassDeferManualReview(
        Booking $booking,
        string $source,
        bool $allowControlledStaffPnr,
    ): bool {
        if (! $allowControlledStaffPnr) {
            return false;
        }

        if (! in_array($source, ['admin', 'staff'], true)) {
            return false;
        }

        if (! $this->isSabreBooking($booking)) {
            return false;
        }

        if ((bool) config('suppliers.sabre.ticketing_enabled', false)) {
            return false;
        }

        if (! SabreCertifiedRouteSelector::isConnectingSameCarrierGdsEnabled()) {
            return false;
        }

        $diag = $this->buildMultiSegmentPnrReadinessDiagnostics($booking);

        if (($diag['admin_pnr_live_action_allowed'] ?? false) !== true) {
            return false;
        }

        if (($diag['connecting_same_carrier_candidate'] ?? false) !== true) {
            return false;
        }

        $category = (string) ($diag['certified_route_category'] ?? $diag['proposed_certification_category'] ?? '');

        return $category === SabreCertifiedRouteSelector::CATEGORY_ONE_WAY_CONNECTING_SAME_CARRIER_GDS;
    }

    /**
     * Sprint 11D: Rebuild Sabre pricing linkage on stored snapshots only (no PNR, no ticketing, no live Sabre).
     *
     * @return array{
     *     success: bool,
     *     status: string,
     *     message: string,
     *     applied_fields: list<string>,
     *     missing_fields: list<string>,
     *     pricing_context_ready: bool
     * }
     */
    public function prepareSabrePricingContext(Booking $booking): array
    {
        if (! $this->isSabreBooking($booking)) {
            return [
                'success' => false,
                'status' => 'skipped',
                'message' => 'Supplier is not Sabre.',
                'applied_fields' => [],
                'missing_fields' => [],
                'pricing_context_ready' => false,
            ];
        }

        $diag = $this->buildMultiSegmentPnrReadinessDiagnostics($booking);
        if (($diag['connecting_same_carrier_candidate'] ?? false) !== true) {
            return [
                'success' => false,
                'status' => 'skipped',
                'message' => 'Pricing context preparation is only available for same-carrier 2-segment controlled certification bookings.',
                'applied_fields' => [],
                'missing_fields' => [],
                'pricing_context_ready' => false,
            ];
        }

        if (($diag['admin_staff_pnr_retry_route_allowed'] ?? false) !== true) {
            return [
                'success' => false,
                'status' => 'skipped',
                'message' => 'Controlled certification route is not enabled for this booking.',
                'applied_fields' => [],
                'missing_fields' => [],
                'pricing_context_ready' => false,
            ];
        }

        $meta = is_array($booking->meta) ? $booking->meta : [];
        $snapshot = $this->enrichSnapshotForPricingAssessment($this->firstSnapshotFromMeta($meta), $meta);
        if ($snapshot === []) {
            return [
                'success' => false,
                'status' => 'failed',
                'message' => 'No offer snapshot is stored on this booking.',
                'applied_fields' => [],
                'missing_fields' => [],
                'pricing_context_ready' => false,
            ];
        }

        $rebuild = $this->pricingContextDigest->rebuildSnapshotPricingLinkage($snapshot);
        $refreshedAt = now()->toIso8601String();
        $after = is_array($rebuild['readiness_after'] ?? null) ? $rebuild['readiness_after'] : [];
        $missing = is_array($after['missing_pricing_context_fields'] ?? null)
            ? array_values($after['missing_pricing_context_fields'])
            : [];
        $applied = is_array($rebuild['applied_fields'] ?? null) ? array_values($rebuild['applied_fields']) : [];
        $ready = ($after['auto_pnr_pricing_context_ready'] ?? false) === true;

        /** @var array<string, mixed> $updatedSnapshot */
        $updatedSnapshot = is_array($rebuild['snapshot'] ?? null) ? $rebuild['snapshot'] : $snapshot;
        foreach (['normalized_offer_snapshot', 'validated_offer_snapshot', 'flight_offer_snapshot'] as $key) {
            if (array_key_exists($key, $meta)) {
                $meta[$key] = $updatedSnapshot;
            }
        }
        if (! array_key_exists('normalized_offer_snapshot', $meta) && ! array_key_exists('validated_offer_snapshot', $meta)) {
            $meta['normalized_offer_snapshot'] = $updatedSnapshot;
        }

        $meta['sabre_pricing_context_refresh'] = [
            'status' => $ready ? 'complete' : ($applied !== [] ? 'partial' : 'incomplete'),
            'refreshed_at' => $refreshedAt,
            'applied_fields' => array_values(array_slice($applied, 0, 12)),
            'missing_fields' => array_values(array_slice($missing, 0, 12)),
            'pricing_context_ready' => $ready,
        ];
        $booking->meta = $meta;
        $booking->save();

        $message = $ready
            ? 'Sabre pricing context is ready for controlled PNR.'
            : ($applied !== []
                ? 'Pricing context partially rebuilt; still missing: '.implode(', ', array_slice($missing, 0, 6)).'.'
                : 'Could not rebuild pricing context from stored shop identifiers — missing: '.implode(', ', array_slice($missing, 0, 6)).'.');

        return [
            'success' => $ready,
            'status' => $ready ? 'complete' : ($applied !== [] ? 'partial' : 'incomplete'),
            'message' => $message,
            'applied_fields' => $applied,
            'missing_fields' => $missing,
            'pricing_context_ready' => $ready,
        ];
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    protected function sabreBookingContextFromSnapshot(array $snapshot, array $meta): array
    {
        $raw = is_array($snapshot['raw_payload'] ?? null) ? $snapshot['raw_payload'] : [];
        $fromRaw = is_array($raw['sabre_booking_context'] ?? null) ? $raw['sabre_booking_context'] : [];
        if ($fromRaw !== []) {
            return $fromRaw;
        }

        return is_array($meta['sabre_booking_context'] ?? null) ? $meta['sabre_booking_context'] : [];
    }

    /**
     * Sprint 11E: Safe shop/pricing linkage presence for admin diagnostics (no raw Sabre JSON / credentials).
     *
     * @param  array<string, mixed>  $snapshot
     * @param  array<string, mixed>  $meta
     * @return array{
     *     shop_identifiers_present: bool,
     *     pricing_information_ref_present: bool,
     *     offer_reference_present: bool,
     *     itinerary_reference_present: bool,
     *     pricing_linkage_source: string,
     *     context_can_be_rebuilt: bool
     * }
     */
    protected function buildShopLinkageDiagnostics(array $snapshot, array $meta): array
    {
        if ($snapshot === []) {
            return [
                'shop_identifiers_present' => false,
                'pricing_information_ref_present' => false,
                'offer_reference_present' => false,
                'itinerary_reference_present' => false,
                'pricing_linkage_source' => 'missing',
                'context_can_be_rebuilt' => false,
            ];
        }

        $raw = is_array($snapshot['raw_payload'] ?? null) ? $snapshot['raw_payload'] : [];
        $ids = is_array($raw['sabre_shop_identifiers'] ?? null) ? $raw['sabre_shop_identifiers'] : [];
        $ctx = is_array($raw['sabre_shop_context'] ?? null) ? $raw['sabre_shop_context'] : [];
        $handoff = $this->sabreBookingContextFromSnapshot($snapshot, $meta);

        $readiness = $this->pricingContextDigest->assessReadiness($snapshot);
        $bfmIndexPresent = ($readiness['bfm_pricing_information_index_present'] ?? false) === true;

        $piPresent = trim((string) ($raw['pricing_information_ref'] ?? '')) !== ''
            || trim((string) ($ctx['pricing_information_ref'] ?? '')) !== ''
            || trim((string) ($handoff['pricing_information_ref'] ?? '')) !== '';
        $offerPresent = trim((string) ($raw['offer_reference'] ?? '')) !== ''
            || trim((string) ($ctx['offer_ref'] ?? '')) !== ''
            || trim((string) ($ctx['offer_id'] ?? '')) !== ''
            || trim((string) ($handoff['offer_reference'] ?? '')) !== '';
        $itinPresent = trim((string) ($raw['itinerary_reference'] ?? '')) !== ''
            || trim((string) ($ctx['itinerary_ref'] ?? '')) !== ''
            || trim((string) ($ids['itinerary_id'] ?? '')) !== ''
            || trim((string) ($handoff['itinerary_reference'] ?? '')) !== '';

        $source = 'missing';
        if ($piPresent || $offerPresent) {
            if ($ids !== []) {
                $source = 'search_cache_identifiers';
            } elseif ($ctx !== []) {
                $source = 'sabre_shop_context';
            } elseif ($handoff !== []) {
                $source = 'sabre_booking_context';
            } elseif (trim((string) ($raw['pricing_information_ref'] ?? $raw['offer_reference'] ?? '')) !== '') {
                $source = 'validated_snapshot';
            }
        } elseif ($bfmIndexPresent && $itinPresent) {
            if ($ctx !== []) {
                $source = 'sabre_shop_context';
            } elseif ($handoff !== []) {
                $source = 'sabre_booking_context';
            }
        } elseif ($itinPresent && $ids !== []) {
            $source = 'search_cache_identifiers';
        }

        $rebuildProbe = $this->pricingContextDigest->rebuildSnapshotPricingLinkage($snapshot);
        $afterRebuild = is_array($rebuildProbe['readiness_after'] ?? null) ? $rebuildProbe['readiness_after'] : [];

        return [
            'shop_identifiers_present' => $ids !== [],
            'pricing_information_ref_present' => $piPresent,
            'offer_reference_present' => $offerPresent,
            'itinerary_reference_present' => $itinPresent,
            'bfm_pricing_information_index_present' => $bfmIndexPresent,
            'pricing_linkage_source' => $source,
            'context_can_be_rebuilt' => ($afterRebuild['auto_pnr_pricing_context_ready'] ?? false) === true,
        ];
    }

    /**
     * Per-segment RBD / fare basis coverage (counts duplicates; does not use unique lists).
     *
     * @param  list<array<string, mixed>>  $segments
     * @param  array<string, mixed>  $handoff
     * @return array{
     *     rbd_present_count: int,
     *     rbd_total_segments: int,
     *     rbd_missing_count: int,
     *     rbd_source: string,
     *     fare_basis_present_count: int,
     *     fare_basis_missing_count: int,
     *     fare_basis_source: string
     * }
     */
    protected function segmentWireBookingCoverage(array $segments, array $handoff): array
    {
        $bookingBySeg = is_array($handoff['booking_classes_by_segment'] ?? null) ? $handoff['booking_classes_by_segment'] : [];
        $fareBasisBySeg = is_array($handoff['fare_basis_codes_by_segment'] ?? null) ? $handoff['fare_basis_codes_by_segment'] : [];
        $rbdPresent = 0;
        $fbPresent = 0;
        $rbdFromSegment = 0;
        $rbdFromHandoff = 0;
        $fbFromSegment = 0;
        $fbFromHandoff = 0;
        $total = 0;
        foreach ($segments as $i => $seg) {
            if (! is_array($seg)) {
                continue;
            }
            $total++;
            $rbd = strtoupper(trim((string) (
                $seg['booking_class'] ?? $seg['class_of_service'] ?? $seg['rbd'] ?? $seg['resBookDesigCode'] ?? ''
            )));
            if ($rbd === '' && isset($bookingBySeg[$i]) && trim((string) $bookingBySeg[$i]) !== '') {
                $rbd = strtoupper(trim((string) $bookingBySeg[$i]));
                $rbdFromHandoff++;
            } elseif ($rbd !== '') {
                $rbdFromSegment++;
            }
            if ($rbd !== '') {
                $rbdPresent++;
            }
            $fb = strtoupper(trim((string) ($seg['fare_basis_code'] ?? $seg['fareBasisCode'] ?? '')));
            if ($fb === '' && isset($fareBasisBySeg[$i]) && trim((string) $fareBasisBySeg[$i]) !== '') {
                $fb = strtoupper(trim((string) $fareBasisBySeg[$i]));
                $fbFromHandoff++;
            } elseif ($fb !== '') {
                $fbFromSegment++;
            }
            if ($fb !== '') {
                $fbPresent++;
            }
        }

        return [
            'rbd_present_count' => $rbdPresent,
            'rbd_total_segments' => $total,
            'rbd_missing_count' => max(0, $total - $rbdPresent),
            'rbd_source' => $this->wireFieldSourceLabel($rbdFromSegment, $rbdFromHandoff),
            'fare_basis_present_count' => $fbPresent,
            'fare_basis_missing_count' => max(0, $total - $fbPresent),
            'fare_basis_source' => $this->wireFieldSourceLabel($fbFromSegment, $fbFromHandoff),
        ];
    }

    protected function wireFieldSourceLabel(int $fromSegment, int $fromHandoff): string
    {
        if ($fromSegment > 0 && $fromHandoff > 0) {
            return 'segment_and_handoff';
        }
        if ($fromHandoff > 0) {
            return 'sabre_booking_context';
        }
        if ($fromSegment > 0) {
            return 'segment_snapshot';
        }

        return 'missing';
    }

    /**
     * @param  list<array<string, mixed>>  $segments
     */
    protected function segmentSellContextCompleteFromSnapshot(array $segments): bool
    {
        if ($segments === []) {
            return false;
        }
        foreach ($segments as $seg) {
            if (! is_array($seg)) {
                return false;
            }
            $origin = strtoupper(trim((string) ($seg['origin'] ?? '')));
            $dest = strtoupper(trim((string) ($seg['destination'] ?? '')));
            $dep = trim((string) ($seg['departure_at'] ?? $seg['depart_at'] ?? ''));
            $arr = trim((string) ($seg['arrival_at'] ?? $seg['arrive_at'] ?? ''));
            $carrier = strtoupper(trim((string) ($seg['airline_code'] ?? $seg['carrier'] ?? $seg['marketing_carrier'] ?? '')));
            $flight = trim((string) ($seg['flight_number'] ?? $seg['flight_no'] ?? ''));
            if ($origin === '' || $dest === '' || $dep === '' || $arr === '' || $carrier === '' || $flight === '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $readiness
     */
    protected function proposeMultiSegmentCertificationCategory(
        string $tripType,
        array $readiness,
        bool $mixedCarrier,
        int $segCount,
    ): string {
        if ($tripType === 'round_trip') {
            return SabreCertifiedRouteSelector::CATEGORY_RETURN;
        }
        if ($tripType === 'multi_city') {
            return SabreCertifiedRouteSelector::CATEGORY_MULTI_CITY;
        }
        if ($segCount <= 1) {
            return SabreCertifiedRouteSelector::CATEGORY_ONE_WAY_DIRECT_SAME_CARRIER;
        }
        if ($mixedCarrier || ($readiness['validating_carrier_mismatch'] ?? false) === true) {
            if ($segCount <= 2) {
                return 'one_way_connecting_mixed_carrier_gds';
            }

            return 'one_way_multi_segment_mixed_carrier_gds';
        }
        if ($segCount === 2) {
            return 'one_way_connecting_same_carrier_gds';
        }

        return 'one_way_multi_segment_same_carrier_gds';
    }

    public function isSimpleOneWaySameCarrierForCertification(array $readiness): bool
    {
        if ((int) ($readiness['segment_count'] ?? 0) > 1) {
            return false;
        }
        if (($readiness['has_codeshare_segment'] ?? false) === true) {
            return false;
        }
        if (($readiness['validating_carrier_mismatch'] ?? false) === true) {
            return false;
        }
        $carriers = is_array($readiness['carrier_chain'] ?? null) ? $readiness['carrier_chain'] : [];
        $vc = strtoupper(trim((string) ($readiness['validating_carrier'] ?? '')));
        $first = strtoupper(trim((string) ($carriers[0] ?? '')));

        return $vc !== '' && $first !== '' && $vc === $first;
    }

    /**
     * @param  array<string, mixed>  $payloadInspect
     */
    public function wireContractValidFromInspect(array $payloadInspect): bool
    {
        if (isset($payloadInspect['wire_contract_valid'])) {
            return (bool) $payloadInspect['wire_contract_valid'];
        }

        if (isset($payloadInspect['wire_traditional_pnr_contract_valid'])) {
            return (bool) $payloadInspect['wire_traditional_pnr_contract_valid'];
        }

        return (bool) ($payloadInspect['validation_ok'] ?? false);
    }

    /**
     * @param  array<string, mixed>  $createResult
     * @return array{stored: bool, expires_at: ?string, source: ?string}
     */
    public function persistExpiryFromCreateResult(Booking $booking, array $createResult): array
    {
        $parsed = $this->extractExpiryFromCreateResult($createResult);
        if ($parsed['iso'] === null) {
            return ['stored' => false, 'expires_at' => null, 'source' => null];
        }

        return $this->writeExpiryMeta($booking, $parsed['iso'], $parsed['source']);
    }

    /**
     * Optional retrieve probe when PNR exists but create response had no expiry.
     *
     * @return array{stored: bool, expires_at: ?string, source: ?string}
     */
    public function tryPersistExpiryFromRetrieveProbe(Booking $booking): array
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $pnr = trim((string) ($booking->pnr ?? ''));
        if ($pnr === '') {
            return ['stored' => false, 'expires_at' => null, 'source' => null];
        }

        $probe = $this->retrieveProbe->probe($booking, true, null, 'pnr', false, false, false);
        if (isset($probe['error'])) {
            return ['stored' => false, 'expires_at' => null, 'source' => null];
        }

        $iso = $this->extractExpiryFromProbeDigest($probe);
        if ($iso === null) {
            $iso = $this->extractExpiryFromOfferSnapshot($meta);
            if ($iso !== null) {
                return $this->writeExpiryMeta($booking, $iso, 'offer_snapshot');
            }

            return ['stored' => false, 'expires_at' => null, 'source' => null];
        }

        return $this->writeExpiryMeta($booking, $iso, 'retrieve_probe');
    }

    /**
     * @param  array<string, mixed>  $createResult
     * @return array{iso: ?string, source: ?string}
     */
    public function extractExpiryFromCreateResult(array $createResult): array
    {
        foreach ([
            'supplier_pnr_expires_at',
            'pnr_expires_at',
            'ticketing_time_limit',
            'time_limit_iso',
        ] as $key) {
            $iso = $this->parseExpiryScalar($createResult[$key] ?? null);
            if ($iso !== null) {
                return ['iso' => $iso, 'source' => 'create_response'];
            }
        }

        $diag = is_array($createResult['booking_diagnostics'] ?? null)
            ? $createResult['booking_diagnostics']
            : [];
        foreach (['ticketing_time_limit', 'time_limit_iso', 'pnr_ticketing_time_limit'] as $key) {
            $iso = $this->parseExpiryScalar($diag[$key] ?? null);
            if ($iso !== null) {
                return ['iso' => $iso, 'source' => 'create_response'];
            }
        }

        return ['iso' => null, 'source' => null];
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    protected function extractExpiryFromOfferSnapshot(array $meta): ?string
    {
        $snapshot = $this->firstSnapshotFromMeta($meta);
        if ($snapshot === []) {
            return null;
        }

        foreach (['time_limit_iso', 'offer_expires_at', 'expires_at'] as $key) {
            $iso = $this->parseExpiryScalar($snapshot[$key] ?? null);
            if ($iso !== null) {
                return $iso;
            }
        }

        $raw = is_array($snapshot['raw_payload'] ?? null) ? $snapshot['raw_payload'] : [];
        $tl = $raw['payment_requirements']['payment_required_by'] ?? null;

        return $this->parseExpiryScalar($tl);
    }

    /**
     * @param  array<string, mixed>  $probe
     */
    protected function extractExpiryFromProbeDigest(array $probe): ?string
    {
        foreach ([
            'ticketing_time_limit',
            'time_limit',
            'last_ticket_date',
            'pnr_expires_at',
        ] as $key) {
            $iso = $this->parseExpiryScalar($probe[$key] ?? null);
            if ($iso !== null) {
                return $iso;
            }
        }

        return null;
    }

    /**
     * @return array{stored: bool, expires_at: ?string, source: ?string}
     */
    protected function writeExpiryMeta(Booking $booking, string $iso, string $source): array
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $meta[self::META_EXPIRES_AT] = $iso;
        $meta[self::META_EXPIRY_SOURCE] = $source;
        $meta[self::META_EXPIRY_SYNCED_AT] = now()->toIso8601String();
        $booking->meta = $meta;
        $booking->save();

        return ['stored' => true, 'expires_at' => $iso, 'source' => $source];
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    protected function journeyGroupCount(array $meta): int
    {
        foreach (['validated_offer_snapshot', 'normalized_offer_snapshot', 'flight_offer_snapshot'] as $key) {
            $offer = $meta[$key] ?? null;
            if (! is_array($offer)) {
                continue;
            }
            $journeys = $offer['journeys_display'] ?? null;
            if (is_array($journeys) && $journeys !== []) {
                return count($journeys);
            }
        }

        $journeys = $meta['journeys_display'] ?? null;

        return is_array($journeys) ? count($journeys) : 0;
    }

    protected function segmentCountFromBooking(Booking $booking): int
    {
        $snapshot = $this->resolveSnapshot($booking);
        $segments = $snapshot['segments'] ?? null;

        return is_array($segments) ? count($segments) : 0;
    }

    /**
     * @return array<string, mixed>
     */
    protected function resolveSnapshot(Booking $booking): array
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];

        return $this->firstSnapshotFromMeta($meta);
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    protected function firstSnapshotFromMeta(array $meta): array
    {
        foreach (['normalized_offer_snapshot', 'validated_offer_snapshot', 'flight_offer_snapshot'] as $key) {
            $snap = $meta[$key] ?? null;
            if (is_array($snap) && $snap !== []) {
                return $snap;
            }
        }

        return [];
    }

    /**
     * Sprint 11G: Merge meta + alternate snapshot BFM/GDS handoff into the primary snapshot before pricing readiness/rebuild.
     *
     * @param  array<string, mixed>  $snapshot
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    protected function enrichSnapshotForPricingAssessment(array $snapshot, array $meta): array
    {
        if ($snapshot === []) {
            return [];
        }

        $raw = is_array($snapshot['raw_payload'] ?? null) ? $snapshot['raw_payload'] : [];
        $ctx = is_array($raw['sabre_shop_context'] ?? null) ? $raw['sabre_shop_context'] : [];
        $handoff = is_array($raw['sabre_booking_context'] ?? null) ? $raw['sabre_booking_context'] : [];

        foreach ([
            $this->sabreBookingContextFromSnapshot($snapshot, $meta),
            $this->sabreBookingContextFromAlternateSnapshots($meta),
        ] as $layer) {
            if ($layer === []) {
                continue;
            }
            $handoff = $this->mergeSabreBookingContextLayers($handoff, $layer);
        }

        foreach ([
            'distribution_channel',
            'shop_endpoint_path',
            'itinerary_reference',
            'pricing_information_index',
            'validating_carrier',
        ] as $scalarKey) {
            if ($this->scalarLinkagePresent($raw, $scalarKey)) {
                continue;
            }
            if (array_key_exists($scalarKey, $handoff) && is_scalar($handoff[$scalarKey]) && trim((string) $handoff[$scalarKey]) !== '') {
                $raw[$scalarKey] = $handoff[$scalarKey];
            }
        }

        if ($handoff !== []) {
            $raw['sabre_booking_context'] = $handoff;
            foreach ([
                'distribution_channel' => 'distribution_channel',
                'shop_endpoint_path' => 'shop_endpoint_path',
                'itinerary_ref' => 'itinerary_reference',
                'pricing_information_index' => 'pricing_information_index',
                'validating_carrier' => 'validating_carrier',
                'booking_classes_by_segment' => 'booking_classes_by_segment',
                'fare_basis_codes_by_segment' => 'fare_basis_codes_by_segment',
                'segment_slice_count' => 'segment_slice_count',
                'leg_refs' => 'leg_refs',
                'schedule_refs' => 'schedule_refs',
            ] as $ctxKey => $handoffKey) {
                if ($this->contextLayerHasValue($ctx, $ctxKey)) {
                    continue;
                }
                if ($this->contextLayerHasValue($handoff, $handoffKey)) {
                    $ctx[$ctxKey] = $handoff[$handoffKey];
                }
            }
            if (! $this->contextLayerHasValue($ctx, 'itinerary_ref')
                && $this->contextLayerHasValue($handoff, 'itinerary_reference')) {
                $ctx['itinerary_ref'] = $handoff['itinerary_reference'];
            }
            $raw['sabre_shop_context'] = $ctx;
        }

        $snapshot['raw_payload'] = $raw;

        return $snapshot;
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    protected function sabreBookingContextFromAlternateSnapshots(array $meta): array
    {
        $merged = [];
        foreach (['validated_offer_snapshot', 'flight_offer_snapshot', 'normalized_offer_snapshot'] as $key) {
            $snap = $meta[$key] ?? null;
            if (! is_array($snap)) {
                continue;
            }
            $raw = is_array($snap['raw_payload'] ?? null) ? $snap['raw_payload'] : [];
            $layer = is_array($raw['sabre_booking_context'] ?? null) ? $raw['sabre_booking_context'] : [];
            if ($layer === []) {
                continue;
            }
            $merged = $this->mergeSabreBookingContextLayers($merged, $layer);
        }

        return $merged;
    }

    /**
     * @param  array<string, mixed>  $base
     * @param  array<string, mixed>  $overlay
     * @return array<string, mixed>
     */
    protected function mergeSabreBookingContextLayers(array $base, array $overlay): array
    {
        foreach ($overlay as $key => $value) {
            if (! is_string($key)) {
                continue;
            }
            if ($this->contextLayerHasValue($base, $key)) {
                continue;
            }
            if ($value === null || $value === '' || $value === []) {
                continue;
            }
            $base[$key] = $value;
        }

        return $base;
    }

    /**
     * @param  array<string, mixed>  $layer
     */
    protected function contextLayerHasValue(array $layer, string $key): bool
    {
        if (! array_key_exists($key, $layer)) {
            return false;
        }
        $value = $layer[$key];
        if (is_array($value)) {
            return $value !== [];
        }
        if (is_numeric($value)) {
            return true;
        }

        return trim((string) $value) !== '';
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    protected function scalarLinkagePresent(array $raw, string $key): bool
    {
        if (! array_key_exists($key, $raw)) {
            return false;
        }
        $value = $raw[$key];
        if (is_numeric($value)) {
            return true;
        }

        return trim((string) $value) !== '';
    }

    /**
     * @param  list<string>  $chain
     * @return list<string>
     */
    protected function dedupeAdjacentAirports(array $chain): array
    {
        $out = [];
        foreach ($chain as $code) {
            if ($out !== [] && end($out) === $code) {
                continue;
            }
            $out[] = $code;
        }

        return $out;
    }

    protected function parseExpiryScalar(mixed $value): ?string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return null;
        }
        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
            $raw .= 'T23:59:59Z';
        }
        try {
            return Carbon::parse($raw)->toIso8601String();
        } catch (\Throwable) {
            return null;
        }
    }

    public function isSabreBooking(Booking $booking): bool
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $provider = strtolower(trim((string) ($meta['supplier_provider'] ?? $booking->supplier ?? '')));

        return $provider === SupplierProvider::Sabre->value;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function assertOutputSafe(array $payload): void
    {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
        if (! is_string($json)) {
            return;
        }
        $lower = strtolower($json);
        $lower = $this->scrubSafeAuthorizationDiagnostics($lower);
        $forbidden = [
            'passport', 'passport_number', 'date_of_birth', 'birthdate', 'birth_date',
            'bearer ', 'access_token', 'client_secret', 'client_id',
            'request_payload', 'response_payload', 'redacted_wire_request_body',
        ];
        foreach ($forbidden as $needle) {
            if (str_contains($lower, $needle)) {
                throw new \RuntimeException('Certification output failed safety check: '.$needle);
            }
        }
        if ($this->containsUnsafeAuthorizationLeak($lower)) {
            throw new \RuntimeException('Certification output failed safety check: authorization');
        }
    }

    protected function scrubSafeAuthorizationDiagnostics(string $lower): string
    {
        $scrubbed = $lower;
        $patterns = [
            '/\bnot_authorized\b/',
            '/\bnot authorized\b/',
            '/http_401_not_authorized\b/',
            '/\bauthorization failure\b/',
            '/\bauthorization_failure\b/',
            '/err\.[a-z0-9_.]*not_authorized[a-z0-9_.]*/',
            '/[a-z0-9_.]*not_authorized[a-z0-9_.]*/',
        ];
        foreach ($patterns as $pattern) {
            $replaced = preg_replace($pattern, '[safe_auth_diag]', $scrubbed);
            if (is_string($replaced)) {
                $scrubbed = $replaced;
            }
        }

        return $scrubbed;
    }

    protected function containsUnsafeAuthorizationLeak(string $lower): bool
    {
        if (! str_contains($lower, 'authorization')) {
            return false;
        }

        $dangerous = [
            '/authorization\s*:\s*(basic|bearer)\s+\S+/',
            '/\bauthorization[_-]?header\b/',
            '/["\']authorization["\']\s*:\s*["\'](?!\[redacted\]|\[safe_auth_diag\])/',
            '/\bauthorization\s*=\s*bearer\b/',
        ];
        foreach ($dangerous as $pattern) {
            if (preg_match($pattern, $lower) === 1) {
                return true;
            }
        }

        return false;
    }
}
