<?php

namespace App\Support\Bookings;

use App\Models\Booking;

/**
 * E5G: Safe verified-lane public auto-PNR candidate discovery (no live Sabre calls, no raw payloads).
 */
final class SabreVerifiedAutoPnrCandidateDiscovery
{
    public const ACTION_AUTO_PNR_CANDIDATE = 'auto_pnr_candidate';

    public const ACTION_MANUAL_REVIEW = 'manual_review';

    public const ACTION_FRESH_SEARCH_REQUIRED = 'fresh_search_required';

    public const ACTION_BLOCKED_SAME_OFFER = 'blocked_same_offer';

    public function __construct(
        protected SabreCertifiedRouteSelector $routeSelector,
        protected SabreVerifiedAutoPnrReadiness $readiness,
        protected SabrePnrCertificationSupport $certificationSupport,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function diagnose(Booking $booking): array
    {
        $booking->loadMissing(['supplierBookings', 'latestSupplierBookingAttempt']);

        $tripType = $this->certificationSupport->detectTripType($booking);
        $selection = $this->routeSelector->selectForBooking($booking);
        $certStatus = (string) (
            $selection['controlled_pnr_certification_status']
            ?? SabreCertifiedRouteSelector::CONTROLLED_PNR_UNKNOWN_CONTROLLED_ONLY
        );

        $evidenceAssess = $this->routeSelector->assessVerifiedPublicAutoPnrEvidence($booking);
        $evidenceStatus = $this->resolveEvidenceStatus($certStatus, $evidenceAssess, $tripType, $booking);
        $evidenceReasonCode = $this->resolveEvidenceReasonCode($evidenceStatus, $evidenceAssess);

        $readiness = $this->readiness->evaluate($booking);
        $pnr = trim((string) ($booking->pnr ?? ''));
        $supplierRef = trim((string) ($booking->supplier_reference ?? ''));
        $pnrPresent = $pnr !== '' || $supplierRef !== '';

        return [
            'booking_id' => $booking->id,
            'booking_reference' => (string) ($booking->booking_reference ?? ''),
            'pnr_status' => $pnrPresent ? 'present' : 'absent',
            'pnr' => $pnrPresent ? $this->maskLocator($pnr !== '' ? $pnr : $supplierRef) : null,
            'segment_summary' => $this->buildSegmentSummary($booking),
            'evidence_status' => $evidenceStatus,
            'evidence_reason_code' => $evidenceReasonCode,
            'matched_success_booking_id' => $evidenceAssess['matched_success_booking_id'] ?? null,
            'matched_failed_booking_id' => $evidenceAssess['matched_failed_booking_id'] ?? null,
            'payload_strategy' => $evidenceAssess['payload_strategy'] ?? null,
            'public_auto_pnr_allowed_now' => $this->readiness->canAttemptLivePublicAutoPnr($booking),
            'readiness_reason_code' => (string) ($readiness['reason_code'] ?? ''),
            'recommended_action' => $this->resolveRecommendedAction(
                $evidenceStatus,
                $readiness,
                $pnrPresent,
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $evidenceAssess
     */
    protected function resolveEvidenceStatus(
        string $certStatus,
        array $evidenceAssess,
        string $tripType,
        Booking $booking,
    ): string {
        if ($certStatus === SabreCertifiedRouteSelector::CONTROLLED_PNR_HOST_NOOP_BLOCKED) {
            return SabreCertifiedRouteSelector::EVIDENCE_STATUS_HOST_NOOP_BLOCKED;
        }

        $assessedStatus = (string) ($evidenceAssess['status'] ?? '');
        if (in_array($assessedStatus, [
            SabreCertifiedRouteSelector::EVIDENCE_STATUS_EXACT_SUCCESS,
            SabreCertifiedRouteSelector::EVIDENCE_STATUS_EXACT_FAILED,
        ], true)) {
            return $assessedStatus;
        }

        if ($certStatus === SabreCertifiedRouteSelector::CONTROLLED_PNR_UNKNOWN_CONTROLLED_ONLY
            && $this->isSameCarrierConnectingCandidate($booking, $tripType)) {
            return SabreCertifiedRouteSelector::EVIDENCE_STATUS_UNKNOWN_CONTROLLED_ONLY;
        }

        if ($assessedStatus !== '') {
            return $assessedStatus;
        }

        return SabreCertifiedRouteSelector::EVIDENCE_STATUS_INSUFFICIENT_FLIGHT_DATE;
    }

    /**
     * @param  array<string, mixed>  $evidenceAssess
     */
    protected function resolveEvidenceReasonCode(string $evidenceStatus, array $evidenceAssess): string
    {
        if ($evidenceStatus === SabreCertifiedRouteSelector::EVIDENCE_STATUS_HOST_NOOP_BLOCKED) {
            return SabreVerifiedAutoPnrReadiness::REASON_HOST_NOOP_BLOCKED;
        }

        if ($evidenceStatus === SabreCertifiedRouteSelector::EVIDENCE_STATUS_UNKNOWN_CONTROLLED_ONLY) {
            return SabreVerifiedAutoPnrReadiness::REASON_UNKNOWN_CONTROLLED_ONLY;
        }

        $reasonCode = trim((string) ($evidenceAssess['reason_code'] ?? ''));
        if ($reasonCode !== '') {
            return $reasonCode;
        }

        if ($evidenceStatus === SabreCertifiedRouteSelector::EVIDENCE_STATUS_EXACT_SUCCESS) {
            return '';
        }

        return SabreCertifiedRouteSelector::REASON_INSUFFICIENT_FLIGHT_DATE_SELLABILITY_EVIDENCE;
    }

    /**
     * @param  array<string, mixed>  $readiness
     */
    protected function resolveRecommendedAction(
        string $evidenceStatus,
        array $readiness,
        bool $pnrPresent,
    ): string {
        if (in_array($evidenceStatus, [
            SabreCertifiedRouteSelector::EVIDENCE_STATUS_HOST_NOOP_BLOCKED,
            SabreCertifiedRouteSelector::EVIDENCE_STATUS_EXACT_FAILED,
        ], true)) {
            return self::ACTION_BLOCKED_SAME_OFFER;
        }

        if ($pnrPresent) {
            return self::ACTION_MANUAL_REVIEW;
        }

        $readinessReason = (string) ($readiness['reason_code'] ?? '');

        if ($evidenceStatus === SabreCertifiedRouteSelector::EVIDENCE_STATUS_EXACT_SUCCESS
            && in_array($readinessReason, [
                SabreVerifiedAutoPnrReadiness::REASON_FEATURE_FLAG_DISABLED,
                SabreVerifiedAutoPnrReadiness::REASON_ELIGIBLE_LIVE,
            ], true)) {
            return self::ACTION_AUTO_PNR_CANDIDATE;
        }

        if ($evidenceStatus === SabreCertifiedRouteSelector::EVIDENCE_STATUS_INSUFFICIENT_FLIGHT_DATE) {
            return self::ACTION_FRESH_SEARCH_REQUIRED;
        }

        if ($evidenceStatus === SabreCertifiedRouteSelector::EVIDENCE_STATUS_UNKNOWN_CONTROLLED_ONLY) {
            return self::ACTION_MANUAL_REVIEW;
        }

        return self::ACTION_MANUAL_REVIEW;
    }

    protected function isSameCarrierConnectingCandidate(Booking $booking, string $tripType): bool
    {
        if ($tripType !== 'one_way_connecting') {
            return false;
        }

        $readiness = $this->certificationSupport->buildReadiness($booking);
        $segmentCount = (int) ($readiness['segment_count'] ?? 0);
        $carriers = is_array($readiness['carrier_chain'] ?? null) ? $readiness['carrier_chain'] : [];

        return $segmentCount === 2 && count($carriers) === 1;
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function buildSegmentSummary(Booking $booking): array
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $snapshot = [];
        foreach (['normalized_offer_snapshot', 'validated_offer_snapshot', 'flight_offer_snapshot'] as $key) {
            if (is_array($meta[$key] ?? null) && $meta[$key] !== []) {
                $snapshot = $meta[$key];
                break;
            }
        }

        $segments = array_values(is_array($snapshot['segments'] ?? null) ? $snapshot['segments'] : []);
        $summary = [];

        foreach ($segments as $seg) {
            if (! is_array($seg)) {
                continue;
            }
            $carrier = strtoupper(trim((string) ($seg['carrier'] ?? $seg['airline_code'] ?? $seg['marketing_carrier'] ?? '')));
            $flight = trim((string) ($seg['flight_number'] ?? $seg['flight_no'] ?? ''));
            $departure = trim((string) ($seg['departure_at'] ?? $seg['depart_at'] ?? ''));
            $summary[] = [
                'carrier' => $carrier,
                'flight_number' => $flight,
                'origin' => strtoupper(trim((string) ($seg['origin'] ?? ''))),
                'destination' => strtoupper(trim((string) ($seg['destination'] ?? ''))),
                'booking_class' => strtoupper(trim((string) (
                    $seg['booking_class'] ?? $seg['class_of_service'] ?? $seg['rbd'] ?? ''
                ))),
                'fare_basis' => strtoupper(trim((string) ($seg['fare_basis_code'] ?? $seg['fareBasisCode'] ?? ''))),
                'departure_at' => $departure !== '' ? substr($departure, 0, 19) : null,
            ];
        }

        return $summary;
    }

    protected function maskLocator(string $locator): string
    {
        $locator = strtoupper(trim($locator));
        if (strlen($locator) <= 3) {
            return $locator;
        }

        return substr($locator, 0, 2).str_repeat('*', max(0, strlen($locator) - 3)).substr($locator, -1);
    }
}
