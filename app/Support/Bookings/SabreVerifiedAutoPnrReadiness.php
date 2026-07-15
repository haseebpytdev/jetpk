<?php

namespace App\Support\Bookings;

use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Services\Suppliers\Sabre\SabreBookingService;

/**
 * E5D/E5E/E5F: Evaluator for verified-lane public same-carrier connecting auto-PNR.
 * Readiness only — live Sabre create is invoked from {@see SabreBookingService::runPublicReviewDryRun()}
 * when {@see self::canAttemptLivePublicAutoPnr()} passes and feature flag is on.
 */
final class SabreVerifiedAutoPnrReadiness
{
    public const MODE_DRY_RUN_ONLY = 'dry_run_only';

    public const MODE_LIVE_ELIGIBLE = 'live_eligible';

    public const REASON_ELIGIBLE_DRY_RUN = 'eligible_dry_run';

    public const REASON_ELIGIBLE_LIVE = 'eligible_live';

    public const REASON_FEATURE_FLAG_DISABLED = 'feature_flag_disabled';

    public const REASON_PNR_ALREADY_EXISTS = 'pnr_already_exists';

    public const REASON_SUPPLIER_BOOKING_EXISTS = 'supplier_booking_exists';

    public const REASON_HOST_NOOP_BLOCKED = 'host_noop_blocked';

    public const REASON_UNKNOWN_CONTROLLED_ONLY = 'unknown_controlled_only';

    public const REASON_TICKETING_ENABLED = 'ticketing_enabled';

    /** @deprecated E5E — use {@see REASON_FEATURE_FLAG_DISABLED} or {@see REASON_ELIGIBLE_LIVE} */
    public const REASON_PUBLIC_AUTO_PNR_ENABLED = 'public_auto_pnr_enabled';

    public const REASON_NOT_SABRE = 'not_sabre';

    public const REASON_NOT_ONE_WAY = 'not_one_way';

    public const REASON_NOT_SAME_CARRIER = 'not_same_carrier';

    public const REASON_SEGMENT_COUNT_NOT_TWO = 'segment_count_not_two';

    public const REASON_SAFE_REFRESH_INCOMPLETE = 'safe_refresh_incomplete';

    public const REASON_CERTIFICATION_NOT_VERIFIED = 'certification_not_verified';

    public const REASON_INSUFFICIENT_FARE_RBD_EVIDENCE = 'insufficient_fare_rbd_evidence';

    public const REASON_INSUFFICIENT_FLIGHT_DATE_SELLABILITY_EVIDENCE = 'insufficient_flight_date_sellability_evidence';

    public const REASON_PRIOR_FARE_RBD_FAILURE = 'prior_fare_rbd_failure';

    public const REASON_FARE_RBD_CARRIER_NOT_SELLABLE = 'fare_rbd_carrier_not_sellable';

    public const VERIFIED_AUTO_PNR_TERMINAL_FAILURE_REASON = 'fare_rbd_carrier_not_sellable';

    public function __construct(
        protected SabrePnrCertificationSupport $certificationSupport,
        protected SabreCertifiedRouteSelector $routeSelector,
        protected SabreSafeRefreshContext $safeRefreshContext,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function evaluate(Booking $booking): array
    {
        $booking->loadMissing(['supplierBookings']);
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $provider = strtolower(trim((string) ($meta['supplier_provider'] ?? $booking->supplier ?? '')));
        $providerIsSabre = $provider === SupplierProvider::Sabre->value;

        $pnrPresent = trim((string) ($booking->pnr ?? '')) !== ''
            || trim((string) ($booking->supplier_reference ?? '')) !== '';
        $supplierBookingPresent = $booking->supplierBookings->contains(
            fn ($item) => in_array((string) $item->status, ['created', 'pending_ticketing', 'ticketed'], true),
        );

        $ticketingEnabled = (bool) config('suppliers.sabre.ticketing_enabled', false);
        $publicAutoPnrEnabled = self::isPublicVerifiedAutoPnrEnabled();

        $tripType = $providerIsSabre ? $this->certificationSupport->detectTripType($booking) : 'unknown';
        $readiness = $providerIsSabre ? $this->certificationSupport->buildReadiness($booking) : [];
        $segmentCount = (int) ($readiness['segment_count'] ?? 0);
        $carriers = is_array($readiness['carrier_chain'] ?? null) ? $readiness['carrier_chain'] : [];
        $sameCarrier = count($carriers) === 1 && $segmentCount >= 2;
        $carrierChain = implode('→', $carriers);

        $routeChain = is_array($readiness['route_chain'] ?? null) ? $readiness['route_chain'] : [];
        $origin = (string) ($routeChain[0] ?? '');
        $destination = $routeChain !== [] ? (string) end($routeChain) : '';
        $connectionAirports = $this->connectionAirportsFromRouteChain($routeChain);

        $safeRefreshAssess = $this->safeRefreshContext->assess($meta);
        $safeRefreshPresent = ($safeRefreshAssess['safe_refresh_context_present'] ?? false) === true;
        $safeRefreshComplete = ($safeRefreshAssess['safe_refresh_context_complete'] ?? false) === true;

        $selection = $providerIsSabre ? $this->routeSelector->selectForBooking($booking) : [];
        $certStatus = (string) (
            $selection['controlled_pnr_certification_status']
            ?? SabreCertifiedRouteSelector::CONTROLLED_PNR_UNKNOWN_CONTROLLED_ONLY
        );
        $certLabel = $this->certificationLabel($certStatus);

        $supplierConnectionIdPresent = trim((string) ($meta['supplier_connection_id'] ?? '')) !== '';

        $base = [
            'eligible' => false,
            'mode' => self::MODE_DRY_RUN_ONLY,
            'reason_code' => '',
            'reason_message' => '',
            'trip_type' => $tripType,
            'supplier_connection_id_present' => $supplierConnectionIdPresent,
            'provider_is_sabre' => $providerIsSabre,
            'same_carrier' => $sameCarrier,
            'carrier_chain' => $carrierChain,
            'origin' => $origin,
            'connection_airports' => $connectionAirports,
            'destination' => $destination,
            'segment_count' => $segmentCount,
            'safe_refresh_context_present' => $safeRefreshPresent,
            'safe_refresh_context_complete' => $safeRefreshComplete,
            'controlled_pnr_certification_status' => $certStatus,
            'controlled_pnr_certification_label' => $certLabel,
            'ticketing_enabled' => $ticketingEnabled,
            'pnr_present' => $pnrPresent,
            'supplier_booking_present' => $supplierBookingPresent,
            'public_auto_pnr_currently_enabled' => $publicAutoPnrEnabled,
        ];

        if (! $providerIsSabre) {
            return $this->ineligible($base, self::REASON_NOT_SABRE, 'Supplier is not Sabre.');
        }

        if ($pnrPresent) {
            return $this->ineligible($base, self::REASON_PNR_ALREADY_EXISTS, 'Supplier PNR or reference already exists.');
        }

        if ($supplierBookingPresent) {
            return $this->ineligible($base, self::REASON_SUPPLIER_BOOKING_EXISTS, 'Supplier booking record already exists.');
        }

        if ($certStatus === SabreCertifiedRouteSelector::CONTROLLED_PNR_HOST_NOOP_BLOCKED) {
            return $this->ineligible($base, self::REASON_HOST_NOOP_BLOCKED, 'Host rejected this itinerary — do not retry same route.');
        }

        if ($ticketingEnabled) {
            return $this->ineligible($base, self::REASON_TICKETING_ENABLED, 'Sabre ticketing is enabled — verified auto-PNR readiness requires ticketing disabled.');
        }

        if ($tripType !== 'one_way_connecting') {
            return $this->ineligible($base, self::REASON_NOT_ONE_WAY, 'Trip type is not one-way connecting.');
        }

        if ($segmentCount !== 2) {
            return $this->ineligible($base, self::REASON_SEGMENT_COUNT_NOT_TWO, 'Segment count must be 2 for verified-lane auto-PNR readiness.');
        }

        if (! $sameCarrier) {
            return $this->ineligible($base, self::REASON_NOT_SAME_CARRIER, 'Itinerary is not same-carrier connecting.');
        }

        if ($certStatus !== SabreCertifiedRouteSelector::CONTROLLED_PNR_VERIFIED) {
            $reason = $certStatus === SabreCertifiedRouteSelector::CONTROLLED_PNR_UNKNOWN_CONTROLLED_ONLY
                ? self::REASON_UNKNOWN_CONTROLLED_ONLY
                : self::REASON_CERTIFICATION_NOT_VERIFIED;

            return $this->ineligible(
                $base,
                $reason,
                $certStatus === SabreCertifiedRouteSelector::CONTROLLED_PNR_UNKNOWN_CONTROLLED_ONLY
                    ? 'Same-carrier connecting route is unknown controlled-only — not verified for auto-PNR.'
                    : 'Controlled PNR certification is not verified for this route.',
            );
        }

        if (! $safeRefreshPresent || ! $safeRefreshComplete) {
            return $this->ineligible($base, self::REASON_SAFE_REFRESH_INCOMPLETE, 'Safe refresh context is missing or incomplete.');
        }

        $meta = is_array($booking->meta) ? $booking->meta : [];
        if (($meta['verified_multiseg_auto_pnr_result'] ?? '') === 'failed'
            && ($meta['verified_multiseg_auto_pnr_reason_code'] ?? '') === self::VERIFIED_AUTO_PNR_TERMINAL_FAILURE_REASON) {
            return $this->ineligibleWithEvidence(
                $base,
                self::REASON_PRIOR_FARE_RBD_FAILURE,
                'Prior verified public auto-PNR failed for this offer — terminal fare/RBD/carrier rejection.',
                (string) ($meta['verified_auto_pnr_evidence_fingerprint'] ?? ''),
                SabreCertifiedRouteSelector::EVIDENCE_STATUS_EXACT_FAILED,
            );
        }

        $evidenceAssess = $this->routeSelector->assessVerifiedPublicAutoPnrEvidence($booking);
        $evidenceStatus = (string) ($evidenceAssess['status'] ?? '');
        $evidenceFingerprint = (string) ($evidenceAssess['evidence_fingerprint'] ?? '');

        if ($evidenceStatus === SabreCertifiedRouteSelector::EVIDENCE_STATUS_EXACT_FAILED) {
            return $this->ineligibleWithEvidence(
                $base,
                self::REASON_FARE_RBD_CARRIER_NOT_SELLABLE,
                (string) ($evidenceAssess['reason_message'] ?? 'Static failed evidence — fare/RBD/carrier not sellable.'),
                $evidenceFingerprint,
                $evidenceStatus,
                $evidenceAssess,
            );
        }

        if ($evidenceStatus !== SabreCertifiedRouteSelector::EVIDENCE_STATUS_EXACT_SUCCESS) {
            $insufficientReason = (string) ($evidenceAssess['reason_code'] ?? '');
            if ($insufficientReason === '') {
                $insufficientReason = self::REASON_INSUFFICIENT_FLIGHT_DATE_SELLABILITY_EVIDENCE;
            }

            return $this->ineligibleWithEvidence(
                $base,
                $insufficientReason,
                (string) ($evidenceAssess['reason_message'] ?? 'Insufficient flight/date sellability evidence for verified public auto-PNR.'),
                $evidenceFingerprint,
                $evidenceStatus,
                $evidenceAssess,
            );
        }

        if (! $publicAutoPnrEnabled) {
            return array_merge($base, [
                'eligible' => false,
                'mode' => self::MODE_DRY_RUN_ONLY,
                'reason_code' => self::REASON_FEATURE_FLAG_DISABLED,
                'reason_message' => 'Verified-lane public auto-PNR feature flag is disabled — deferring to manual review.',
            ]);
        }

        return array_merge($base, [
            'eligible' => true,
            'mode' => self::MODE_LIVE_ELIGIBLE,
            'reason_code' => self::REASON_ELIGIBLE_LIVE,
            'reason_message' => 'Eligible for verified-lane public auto-PNR when live Sabre booking is enabled.',
            'public_auto_pnr_evidence_status' => $evidenceStatus,
            'public_auto_pnr_evidence_fingerprint' => $evidenceFingerprint,
        ]);
    }

    public function canAttemptLivePublicAutoPnr(Booking $booking): bool
    {
        $readiness = $this->evaluate($booking);

        return ($readiness['eligible'] ?? false) === true
            && ($readiness['mode'] ?? '') === self::MODE_LIVE_ELIGIBLE
            && self::isPublicVerifiedAutoPnrEnabled();
    }

    /**
     * Persist safe verified auto-PNR checkout meta (no raw Sabre payloads or PII).
     *
     * @param  array<string, mixed>  $readiness
     */
    public function persistCheckoutMeta(
        Booking $booking,
        array $readiness,
        bool $attempted,
        ?string $result = null,
        ?string $attemptReasonCode = null,
    ): void {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $flagEnabled = self::isPublicVerifiedAutoPnrEnabled();
        $mode = (string) ($readiness['mode'] ?? self::MODE_DRY_RUN_ONLY);
        $readinessKey = ($flagEnabled && $mode === self::MODE_LIVE_ELIGIBLE)
            ? 'verified_auto_pnr_readiness'
            : 'verified_auto_pnr_readiness_dry_run';

        $meta[$readinessKey] = $readiness;
        if ($readinessKey === 'verified_auto_pnr_readiness') {
            unset($meta['verified_auto_pnr_readiness_dry_run']);
        }

        $meta['verified_multiseg_auto_pnr_enabled'] = $flagEnabled;
        $meta['verified_multiseg_auto_pnr_attempted'] = $attempted;
        if ($result !== null) {
            $meta['verified_multiseg_auto_pnr_result'] = $result;
        }

        $reasonCode = $attemptReasonCode ?? (string) ($readiness['reason_code'] ?? '');
        if ($result === 'failed' && $attemptReasonCode !== null && $attemptReasonCode !== '') {
            $reasonCode = $attemptReasonCode;
        }
        $meta['verified_multiseg_auto_pnr_reason_code'] = $reasonCode;

        $fingerprint = (string) ($readiness['public_auto_pnr_evidence_fingerprint'] ?? '');
        if ($fingerprint !== '') {
            $meta['verified_auto_pnr_evidence_fingerprint'] = $fingerprint;
        }

        $meta['controlled_pnr_certification_status'] = (string) ($readiness['controlled_pnr_certification_status'] ?? '');
        $meta['controlled_pnr_certification_label'] = (string) ($readiness['controlled_pnr_certification_label'] ?? '');

        $booking->forceFill(['meta' => $meta])->save();
    }

    public static function isPublicVerifiedAutoPnrEnabled(): bool
    {
        return (bool) config('suppliers.sabre.verified_multiseg_auto_pnr_enabled', false);
    }

    /**
     * @param  list<string>  $routeChain
     * @return list<string>
     */
    protected function connectionAirportsFromRouteChain(array $routeChain): array
    {
        if (count($routeChain) < 3) {
            return [];
        }

        $connections = array_slice($routeChain, 1, -1);

        return array_values(array_unique(array_filter($connections, static fn (string $code) => $code !== '')));
    }

    protected function certificationLabel(string $status): string
    {
        return match ($status) {
            SabreCertifiedRouteSelector::CONTROLLED_PNR_VERIFIED => 'Verified controlled PNR-capable',
            SabreCertifiedRouteSelector::CONTROLLED_PNR_HOST_NOOP_BLOCKED => 'Host rejected / do not retry same itinerary',
            default => 'Unknown controlled-only',
        };
    }

    /**
     * @param  array<string, mixed>  $base
     * @return array<string, mixed>
     */
    protected function ineligible(array $base, string $reasonCode, string $reasonMessage): array
    {
        return array_merge($base, [
            'eligible' => false,
            'reason_code' => $reasonCode,
            'reason_message' => $reasonMessage,
        ]);
    }

    /**
     * @param  array<string, mixed>  $base
     * @param  array<string, mixed>  $evidenceAssess
     * @return array<string, mixed>
     */
    protected function ineligibleWithEvidence(
        array $base,
        string $reasonCode,
        string $reasonMessage,
        string $fingerprint,
        string $evidenceStatus,
        array $evidenceAssess = [],
    ): array {
        return array_merge($base, [
            'eligible' => false,
            'reason_code' => $reasonCode,
            'reason_message' => $reasonMessage,
            'public_auto_pnr_evidence_status' => $evidenceStatus,
            'public_auto_pnr_evidence_fingerprint' => $fingerprint,
            'public_auto_pnr_booking_classes' => $evidenceAssess['booking_classes'] ?? [],
            'public_auto_pnr_flight_numbers' => $evidenceAssess['flight_numbers'] ?? [],
            'public_auto_pnr_fare_basis_codes' => $evidenceAssess['fare_basis_codes'] ?? [],
        ]);
    }
}
