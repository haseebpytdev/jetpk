<?php

namespace App\Support\Bookings;

use App\Enums\BookingStatus;
use App\Models\Booking;

/**
 * F9E: Explicit operator fare-change acceptance for controlled Sabre PNR retry (meta only; no supplier HTTP).
 */
final class SabreControlledPnrFareChangeAcceptance
{
    public const META_KEY = 'controlled_pnr_fare_change_acceptance';

    public const ACCEPTANCE_SOURCE_ARTISAN = 'artisan';

    public const ACCEPTED_FOR_CONTROLLED_PNR_CREATE_RETRY = 'controlled_pnr_create_retry';

    public const WARNING_CONTROLLED_FARE_CHANGE_ACCEPTED = 'controlled_fare_change_accepted';

    public function __construct(
        protected SabrePnrCertificationSupport $certificationSupport,
        protected SabreSafeRefreshContext $safeRefreshContext,
    ) {}

    public function isAccepted(array $meta): bool
    {
        $record = $this->extractRecord($meta);

        return ($record['accepted'] ?? false) === true
            && (string) ($record['accepted_for'] ?? '') === self::ACCEPTED_FOR_CONTROLLED_PNR_CREATE_RETRY;
    }

    public function fareChangeGateActive(array $meta): bool
    {
        $refreshStatus = strtolower(trim((string) ($meta['offer_refresh_status'] ?? '')));
        if ($refreshStatus !== 'refreshed') {
            return false;
        }

        if (($meta[SabreOfferRefreshAcceptance::META_REQUIRES_CONFIRMATION] ?? false) === true
            && ($meta[SabreOfferRefreshAcceptance::META_ACCEPTED] ?? false) !== true) {
            return true;
        }

        if (($meta[SabreOfferRefreshAcceptance::META_PRICE_CHANGED] ?? false) === true
            && ($meta[SabreOfferRefreshAcceptance::META_ACCEPTED] ?? false) !== true) {
            return true;
        }

        if (($meta['requires_price_change_confirmation'] ?? false) === true
            && ($meta[SabreOfferRefreshAcceptance::META_ACCEPTED] ?? false) !== true) {
            return true;
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>|null
     */
    public function extractRecord(array $meta): ?array
    {
        $record = $meta[self::META_KEY] ?? null;
        if (! is_array($record)) {
            return null;
        }

        return $record;
    }

    /**
     * @return array{
     *     eligible: bool,
     *     blockers: list<string>,
     *     controlled_pnr_manual_review_approved: bool,
     *     fare_change_gate_active: bool,
     *     safe_refresh_context_complete: bool,
     *     pricing_snapshot_present: bool,
     *     certified_route_selection_present: bool,
     * }
     */
    public function evaluateAcceptanceEligibility(Booking $booking): array
    {
        $booking->loadMissing(['passengers', 'contact', 'supplierBookings', 'tickets']);
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $blockers = [];

        if (! $this->certificationSupport->isSabreBooking($booking)) {
            $blockers[] = 'not_sabre_booking';
        }

        if ((int) ($meta['supplier_connection_id'] ?? 0) <= 0) {
            $blockers[] = 'missing_supplier_connection';
        }

        if ($this->detectExistingPnr($booking)) {
            $blockers[] = 'existing_pnr_present';
        }

        if ($booking->status === BookingStatus::Cancelled) {
            $blockers[] = 'cancelled_booking_blocked';
        }

        if ($this->isTicketed($booking)) {
            $blockers[] = 'ticketed_booking_blocked';
        }

        $manualReviewApproved = $this->isManualReviewApproved($meta);
        if (! $manualReviewApproved) {
            $blockers[] = 'controlled_pnr_manual_review_not_approved';
        }

        if (! $this->fareChangeGateActive($meta)) {
            $blockers[] = 'fare_change_gate_not_active';
        }

        $refreshStatus = strtolower(trim((string) ($meta['offer_refresh_status'] ?? '')));
        if ($refreshStatus !== 'refreshed') {
            $blockers[] = 'offer_refresh_status_not_refreshed';
        }

        $validatedSnapshot = is_array($meta['validated_offer_snapshot'] ?? null) ? $meta['validated_offer_snapshot'] : [];
        if ($validatedSnapshot === []) {
            $blockers[] = 'validated_offer_snapshot_missing';
        }

        $pricingSnapshot = is_array($meta['pricing_snapshot'] ?? null) ? $meta['pricing_snapshot'] : [];
        if ($pricingSnapshot === []) {
            $blockers[] = 'pricing_snapshot_missing';
        }

        $safeRefreshAssess = $this->safeRefreshContext->assess($meta);
        $safeRefreshComplete = ($safeRefreshAssess['safe_refresh_context_complete'] ?? false) === true;
        if (! $safeRefreshComplete) {
            $blockers[] = 'safe_refresh_context_incomplete';
        }

        $certifiedRoute = is_array($meta['certified_route_selection'] ?? null) ? $meta['certified_route_selection'] : [];
        $certifiedRoutePresent = $this->isCertifiedRouteSelectionValid($certifiedRoute);
        if (! $certifiedRoutePresent) {
            $blockers[] = 'certified_route_selection_missing';
        }

        if ($this->isAccepted($meta)) {
            $blockers[] = 'controlled_pnr_fare_change_already_accepted';
        }

        $blockers = array_values(array_unique($blockers));

        return [
            'eligible' => $blockers === [],
            'blockers' => $blockers,
            'controlled_pnr_manual_review_approved' => $manualReviewApproved,
            'fare_change_gate_active' => $this->fareChangeGateActive($meta),
            'safe_refresh_context_complete' => $safeRefreshComplete,
            'pricing_snapshot_present' => $pricingSnapshot !== [],
            'certified_route_selection_present' => $certifiedRoutePresent,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function buildAcceptanceRecord(Booking $booking, string $reason, string $acceptedBy): array
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $pricingSnapshot = is_array($meta['pricing_snapshot'] ?? null) ? $meta['pricing_snapshot'] : [];
        $validatedSnapshot = is_array($meta['validated_offer_snapshot'] ?? null) ? $meta['validated_offer_snapshot'] : [];

        return [
            'accepted' => true,
            'accepted_at' => now()->toIso8601String(),
            'accepted_by' => $this->sanitizeOperatorLabel($acceptedBy),
            'acceptance_source' => self::ACCEPTANCE_SOURCE_ARTISAN,
            'acceptance_reason' => $this->sanitizeReason($reason),
            'acceptance_booking_reference' => (string) ($booking->reference_code ?? ''),
            'accepted_for' => self::ACCEPTED_FOR_CONTROLLED_PNR_CREATE_RETRY,
            'accepted_offer_refresh_refreshed_at' => (string) ($meta['offer_refresh_refreshed_at'] ?? ''),
            'accepted_offer_refresh_reason' => (string) ($meta['offer_refresh_reason'] ?? ''),
            'accepted_supplier_total' => $this->safeNumericTotal($pricingSnapshot['supplier_total'] ?? $meta['supplier_total'] ?? null),
            'accepted_supplier_currency' => $this->safeCurrency(
                (string) ($pricingSnapshot['supplier_currency'] ?? $meta['supplier_currency'] ?? '')
            ),
            'accepted_pricing_currency' => $this->safeCurrency(
                (string) ($pricingSnapshot['pricing_currency'] ?? $pricingSnapshot['currency'] ?? $booking->currency ?? '')
            ),
            'accepted_pricing_snapshot_fingerprint' => self::fingerprintPricingSnapshot($pricingSnapshot),
            'accepted_validated_offer_fingerprint' => self::fingerprintValidatedOffer($validatedSnapshot),
        ];
    }

    /**
     * @param  array<string, mixed>  $pricingSnapshot
     */
    public static function fingerprintPricingSnapshot(array $pricingSnapshot): string
    {
        $canonical = [
            'base_fare' => self::roundNumeric($pricingSnapshot['base_fare'] ?? null),
            'taxes' => self::roundNumeric($pricingSnapshot['taxes'] ?? null),
            'supplier_total' => self::roundNumeric($pricingSnapshot['supplier_total'] ?? null),
            'supplier_currency' => strtoupper(trim((string) ($pricingSnapshot['supplier_currency'] ?? ''))),
            'pricing_currency' => strtoupper(trim((string) ($pricingSnapshot['pricing_currency'] ?? $pricingSnapshot['currency'] ?? ''))),
            'final_total' => self::roundNumeric($pricingSnapshot['final_total'] ?? null),
        ];
        ksort($canonical);

        return substr(hash('sha256', json_encode($canonical, JSON_THROW_ON_ERROR)), 0, 32);
    }

    /**
     * @param  array<string, mixed>  $validatedSnapshot
     */
    public static function fingerprintValidatedOffer(array $validatedSnapshot): string
    {
        $segments = array_values(is_array($validatedSnapshot['segments'] ?? null) ? $validatedSnapshot['segments'] : []);
        $segmentFingerprints = [];
        foreach ($segments as $segment) {
            if (! is_array($segment)) {
                continue;
            }
            $segmentFingerprints[] = implode('|', [
                strtoupper(trim((string) ($segment['origin'] ?? ''))),
                strtoupper(trim((string) ($segment['destination'] ?? ''))),
                strtoupper(trim((string) ($segment['carrier'] ?? $segment['airline_code'] ?? ''))),
                trim((string) ($segment['flight_number'] ?? '')),
                strtoupper(trim((string) ($segment['booking_class'] ?? ''))),
                trim((string) ($segment['fare_basis_code'] ?? '')),
            ]);
        }

        $canonical = [
            'validating_carrier' => strtoupper(trim((string) ($validatedSnapshot['validating_carrier'] ?? ''))),
            'origin' => strtoupper(trim((string) ($validatedSnapshot['origin'] ?? ''))),
            'destination' => strtoupper(trim((string) ($validatedSnapshot['destination'] ?? ''))),
            'segment_fingerprints' => $segmentFingerprints,
            'segment_count' => count($segmentFingerprints),
        ];
        ksort($canonical);

        return substr(hash('sha256', json_encode($canonical, JSON_THROW_ON_ERROR)), 0, 32);
    }

    public function sanitizeOperatorLabel(string $label): string
    {
        $clean = preg_replace('/[^\p{L}\p{N}\s@._\-]/u', '', trim($label)) ?? '';
        $clean = trim(preg_replace('/\s+/', ' ', $clean) ?? '');

        if ($clean === '') {
            return 'operator';
        }

        return mb_substr($clean, 0, 80);
    }

    public function sanitizeReason(string $reason): string
    {
        $clean = preg_replace('/[^\p{L}\p{N}\s.,;:!?\-]/u', '', trim($reason)) ?? '';
        $clean = trim(preg_replace('/\s+/', ' ', $clean) ?? '');

        if ($clean === '') {
            return 'controlled_pnr_fare_change';
        }

        return mb_substr($clean, 0, 200);
    }

    protected function safeNumericTotal(mixed $value): ?float
    {
        if (! is_numeric($value)) {
            return null;
        }

        return round((float) $value, 2);
    }

    protected function safeCurrency(string $currency): string
    {
        $clean = strtoupper(substr(trim($currency), 0, 8));

        return $clean !== '' ? $clean : 'PKR';
    }

    protected static function roundNumeric(mixed $value): ?float
    {
        if (! is_numeric($value)) {
            return null;
        }

        return round((float) $value, 2);
    }

    protected function isManualReviewApproved(array $meta): bool
    {
        $record = $meta[SabreControlledPnrManualReviewApproval::META_KEY] ?? null;
        if (! is_array($record)) {
            return false;
        }

        return ($record['approved'] ?? false) === true
            && (string) ($record['approved_for'] ?? '') === SabreControlledPnrManualReviewApproval::APPROVED_FOR_CONTROLLED_PNR_CREATE;
    }

    /**
     * @param  array<string, mixed>  $route
     */
    protected function isCertifiedRouteSelectionValid(array $route): bool
    {
        if ($route === []) {
            return false;
        }

        $status = (string) ($route['route_status'] ?? '');
        if (! in_array($status, [
            SabreCertifiedRouteSelector::STATUS_CONTROLLED_CERTIFIED,
            SabreCertifiedRouteSelector::STATUS_CERTIFIED,
        ], true)) {
            return false;
        }

        return trim((string) ($route['endpoint_path'] ?? '')) !== ''
            && trim((string) ($route['payload_style'] ?? '')) !== '';
    }

    protected function detectExistingPnr(Booking $booking): bool
    {
        $booking->loadMissing(['supplierBookings']);

        if (trim((string) ($booking->pnr ?? '')) !== '') {
            return true;
        }

        if (trim((string) ($booking->supplier_reference ?? '')) !== '') {
            return true;
        }

        if (trim((string) ($booking->supplier_api_booking_id ?? '')) !== '') {
            return true;
        }

        return $booking->supplierBookings->contains(
            fn ($item) => in_array((string) $item->status, ['created', 'pending_ticketing', 'ticketed'], true),
        );
    }

    protected function isTicketed(Booking $booking): bool
    {
        if ($booking->status === BookingStatus::Ticketed) {
            return true;
        }

        return $booking->supplierBookings->contains(
            fn ($item) => (string) $item->status === 'ticketed',
        ) || $booking->tickets->isNotEmpty();
    }
}
