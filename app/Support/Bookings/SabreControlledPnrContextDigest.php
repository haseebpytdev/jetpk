<?php

namespace App\Support\Bookings;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Services\Booking\BookingOperationalPrecheckService;
use App\Services\Suppliers\Sabre\Booking\SabreBookingService;
use App\Support\FlightSearch\SabreOfferFreshness;

/**
 * F9B: Production-safe controlled Sabre PNR context digest/classifier (read-only; no HTTP, no DB mutation).
 *
 * Classifies whether legacy controlled-certified checkout context is usable for explicit admin/command PNR.
 */
final class SabreControlledPnrContextDigest
{
    public const REASON_USABLE = 'usable_controlled_pnr_context';

    public const REASON_NOT_SABRE = 'not_sabre_booking';

    public const REASON_MISSING_CONNECTION = 'missing_supplier_connection';

    public const REASON_EXISTING_PNR = 'existing_pnr_present';

    public const REASON_TICKETED = 'ticketed_booking_blocked';

    public const REASON_CANCELLED = 'cancelled_booking_blocked';

    public const REASON_MISSING_PASSENGERS = 'missing_passengers';

    public const REASON_MISSING_PASSENGER_FIELDS = 'missing_required_passenger_fields';

    public const REASON_MISSING_CONTACT = 'missing_contact';

    public const REASON_MISSING_OFFER_SNAPSHOT = 'missing_offer_snapshot';

    public const REASON_MISSING_PRICING_SNAPSHOT = 'missing_pricing_snapshot';

    public const REASON_MISSING_SAFE_REFRESH = 'missing_safe_refresh_context';

    public const REASON_INCOMPLETE_SAFE_REFRESH = 'incomplete_safe_refresh_context';

    public const REASON_PAYLOAD_NOT_READY = 'payload_not_ready';

    public const REASON_MISSING_CERTIFIED_ROUTE = 'missing_certified_route_selection';

    public const REASON_MISSING_LEGACY_REVALIDATION = 'missing_legacy_revalidation_signal';

    public const BLOCKER_REVALIDATION_EXPIRED = 'revalidation_expired';

    public const BLOCKER_STALE_PRICING = 'stale_pricing';

    public const BLOCKER_OFFER_REFRESH_CONFIRMATION = 'offer_refresh_customer_confirmation_required';

    public const BLOCKER_PRICE_CHANGE_CONFIRMATION = 'price_change_confirmation_required';

    public function __construct(
        protected SabrePnrCertificationSupport $certificationSupport,
        protected SabreBookingService $sabreBookingService,
        protected BookingOperationalPrecheckService $operationalPrecheck,
        protected SabreSafeRefreshContext $safeRefreshContext,
        protected SabreOfferFreshness $offerFreshness,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function classify(Booking $booking): array
    {
        $booking->loadMissing(['passengers', 'contact', 'supplierBookings', 'tickets']);

        $meta = is_array($booking->meta) ? $booking->meta : [];
        $isSabre = $this->certificationSupport->isSabreBooking($booking);
        $connectionId = $this->resolveSupplierConnectionId($booking, $meta);
        $hasExistingPnr = $this->detectExistingPnr($booking);
        $isTicketed = $this->isTicketed($booking);
        $isCancelled = $booking->status === BookingStatus::Cancelled;

        $pricingReadiness = $isSabre
            ? $this->sabreBookingService->assessAutoPnrPricingContextReadinessForBooking($booking)
            : [];
        $handoff = is_array($meta['sabre_booking_context'] ?? null) ? $meta['sabre_booking_context'] : [];
        $hasStrongLinkage = ($pricingReadiness['has_revalidation_linkage_complete'] ?? false) === true;
        if (array_key_exists('has_revalidation_linkage', $handoff) && ($handoff['has_revalidation_linkage'] ?? null) === false) {
            $hasStrongLinkage = false;
        }
        $hasLegacySignal = $this->hasLegacySuccessRevalidationSignal($meta);
        $hasPayloadReady = $this->hasPayloadReadyContext($meta);
        $safeRefreshAssess = $this->safeRefreshContext->assess($meta);
        $hasSafeRefresh = ($safeRefreshAssess['safe_refresh_context_present'] ?? false) === true
            && ($safeRefreshAssess['safe_refresh_context_complete'] ?? false) === true;
        $certifiedRoute = $this->extractCertifiedRouteSelection($meta);
        $hasCertifiedRoute = $this->isCertifiedRouteSelectionValid($certifiedRoute);

        $snapshot = SabreOfferRefreshAcceptance::authoritativeOfferSnapshot($meta);
        $hasOfferSnapshot = $snapshot !== [];
        $pricingSnapshot = is_array($meta['pricing_snapshot'] ?? null) ? $meta['pricing_snapshot'] : [];
        $hasPricingSnapshot = $pricingSnapshot !== [];

        $passengerCount = $booking->passengers->count();
        $hasPassengers = $passengerCount > 0;
        $hasContact = $booking->contact !== null
            && trim((string) ($booking->contact->email ?? '')) !== '';

        $freshnessBlockers = $this->freshnessBlockers($meta);
        $contextBlockers = [];
        $contextWarnings = [];

        if (! $isSabre) {
            $contextBlockers[] = self::REASON_NOT_SABRE;
        }
        if ($connectionId <= 0) {
            $contextBlockers[] = self::REASON_MISSING_CONNECTION;
        }
        if ($hasExistingPnr) {
            $contextBlockers[] = self::REASON_EXISTING_PNR;
        }
        if ($isTicketed) {
            $contextBlockers[] = self::REASON_TICKETED;
        }
        if ($isCancelled) {
            $contextBlockers[] = self::REASON_CANCELLED;
        }
        if (! $hasPassengers) {
            $contextBlockers[] = self::REASON_MISSING_PASSENGERS;
        } elseif ($isSabre && $this->operationalPrecheck->validatePassengerReadiness($booking) !== []) {
            $contextBlockers[] = self::REASON_MISSING_PASSENGER_FIELDS;
        }
        if (! $hasContact) {
            $contextBlockers[] = self::REASON_MISSING_CONTACT;
        }
        if (! $hasOfferSnapshot) {
            $contextBlockers[] = self::REASON_MISSING_OFFER_SNAPSHOT;
        }
        if (! $hasPricingSnapshot) {
            $contextBlockers[] = self::REASON_MISSING_PRICING_SNAPSHOT;
        }
        if (! ($safeRefreshAssess['safe_refresh_context_present'] ?? false)) {
            $contextBlockers[] = self::REASON_MISSING_SAFE_REFRESH;
        } elseif (! ($safeRefreshAssess['safe_refresh_context_complete'] ?? false)) {
            $contextBlockers[] = self::REASON_INCOMPLETE_SAFE_REFRESH;
        }
        if (! $hasPayloadReady) {
            $contextBlockers[] = self::REASON_PAYLOAD_NOT_READY;
        }
        if (! $hasCertifiedRoute) {
            $contextBlockers[] = self::REASON_MISSING_CERTIFIED_ROUTE;
        }
        if (! $hasLegacySignal) {
            $contextBlockers[] = self::REASON_MISSING_LEGACY_REVALIDATION;
        }

        foreach ($freshnessBlockers as $blocker) {
            $contextBlockers[] = $blocker;
        }

        $contextBlockers = array_values(array_unique($contextBlockers));
        $hasUsable = $contextBlockers === [];

        if ($hasUsable && ! $hasStrongLinkage) {
            $contextWarnings[] = 'controlled_certified_context_used';
            if ($hasLegacySignal) {
                $contextWarnings[] = 'legacy_revalidation_signal_used';
            }
        }

        if ($this->hasControlledFareChangeAcceptance($meta)) {
            $contextWarnings[] = SabreControlledPnrFareChangeAcceptance::WARNING_CONTROLLED_FARE_CHANGE_ACCEPTED;
        }

        $checkoutOutcome = is_array($meta['sabre_checkout_outcome'] ?? null) ? $meta['sabre_checkout_outcome'] : [];
        $segments = array_values(is_array($snapshot['segments'] ?? null) ? $snapshot['segments'] : []);
        $carrierChain = $this->carrierChainFromSegments($segments, $handoff, $safeRefreshAssess);

        return [
            'has_strong_revalidation_linkage' => $hasStrongLinkage,
            'has_legacy_success_revalidation_signal' => $hasLegacySignal,
            'has_payload_ready_context' => $hasPayloadReady,
            'has_safe_refresh_context' => $hasSafeRefresh,
            'has_certified_route_selection' => $hasCertifiedRoute,
            'has_usable_controlled_pnr_context' => $hasUsable,
            'controlled_pnr_context_reason_code' => $hasUsable
                ? self::REASON_USABLE
                : ($contextBlockers[0] ?? 'blocked_ineligible'),
            'context_warnings' => array_values(array_unique($contextWarnings)),
            'context_blockers' => $contextBlockers,
            'booking_id' => $booking->id,
            'booking_reference' => (string) ($booking->reference_code ?? ''),
            'booking_status' => (string) ($booking->status?->value ?? $booking->status ?? ''),
            'payment_status' => (string) ($booking->payment_status ?? ''),
            'supplier_provider' => (string) ($meta['supplier_provider'] ?? $booking->supplier ?? ''),
            'supplier_connection_id' => $connectionId > 0 ? $connectionId : null,
            'pnr_present' => trim((string) ($booking->pnr ?? '')) !== '',
            'supplier_reference_present' => trim((string) ($booking->supplier_reference ?? '')) !== '',
            'selected_offer_revalidation_status' => (string) ($meta['selected_offer_revalidation_status'] ?? ''),
            'revalidation_status' => (string) ($meta['revalidation_status'] ?? $meta['sabre_revalidation_status'] ?? ''),
            'selected_offer_last_revalidated_at' => (string) ($meta['selected_offer_last_revalidated_at'] ?? ''),
            'last_revalidated_at' => (string) ($meta['last_revalidated_at'] ?? ''),
            'offer_refresh_status' => (string) ($meta['offer_refresh_status'] ?? ''),
            'offer_refresh_reason' => (string) ($meta['offer_refresh_reason'] ?? ''),
            'sabre_booking_context_has_revalidation_linkage' => ($handoff['has_revalidation_linkage'] ?? false) === true,
            'sabre_booking_context_ready_for_booking_payload' => ($handoff['ready_for_booking_payload'] ?? false) === true,
            'sabre_booking_context_validating_carrier' => strtoupper(trim((string) (
                $handoff['validating_carrier'] ?? $snapshot['validating_carrier'] ?? ''
            ))) ?: null,
            'sabre_booking_context_brand_code' => trim((string) ($handoff['brand_code'] ?? $handoff['brandCode'] ?? '')) ?: null,
            'segment_count' => count($segments) > 0
                ? count($segments)
                : (int) (($safeRefreshAssess['safe_refresh_context_present'] ?? false)
                    ? ($this->safeRefreshContext->fromMeta($meta)['segment_count'] ?? 0)
                    : 0),
            'carrier_chain' => $carrierChain,
            'certified_route_selection_category' => (string) ($certifiedRoute['category'] ?? ''),
            'certified_route_selection_route_status' => (string) ($certifiedRoute['route_status'] ?? ''),
            'certified_route_selection_endpoint_path' => (string) ($certifiedRoute['endpoint_path'] ?? ''),
            'certified_route_selection_payload_style' => (string) ($certifiedRoute['payload_style'] ?? ''),
            'sabre_checkout_outcome_status' => (string) ($checkoutOutcome['status'] ?? ''),
            'sabre_checkout_outcome_live_call_attempted' => ($checkoutOutcome['live_call_attempted'] ?? false) === true,
            'sabre_checkout_outcome_error_code' => (string) ($checkoutOutcome['error_code'] ?? ''),
            'safe_refresh_context_present' => ($safeRefreshAssess['safe_refresh_context_present'] ?? false) === true,
            'safe_refresh_context_complete' => ($safeRefreshAssess['safe_refresh_context_complete'] ?? false) === true,
            'normalized_offer_snapshot_present' => is_array($meta['normalized_offer_snapshot'] ?? null) && $meta['normalized_offer_snapshot'] !== [],
            'validated_offer_snapshot_present' => is_array($meta['validated_offer_snapshot'] ?? null) && $meta['validated_offer_snapshot'] !== [],
            'pricing_snapshot_present' => $hasPricingSnapshot,
            'raw_payload_present' => is_array($snapshot['raw_payload'] ?? null) && $snapshot['raw_payload'] !== [],
            'pricing_snapshot_currency_present' => trim((string) ($pricingSnapshot['currency'] ?? $pricingSnapshot['supplier_currency'] ?? '')) !== '',
            'pricing_snapshot_converted_present' => isset($pricingSnapshot['converted_total']) || isset($pricingSnapshot['customer_total']),
            'controlled_context_classification' => $hasUsable ? 'usable' : 'blocked',
            'controlled_context_reason_code' => $hasUsable
                ? self::REASON_USABLE
                : ($contextBlockers[0] ?? 'blocked_ineligible'),
            'controlled_context_warnings' => array_values(array_unique($contextWarnings)),
            'controlled_context_blockers' => $contextBlockers,
        ];
    }

    /**
     * Explicit freshness/confirmation blockers only — never infer stale from null offer_expires_at.
     *
     * @param  array<string, mixed>  $meta
     * @return list<string>
     */
    public function freshnessBlockers(array $meta): array
    {
        $blockers = [];
        $freshness = is_array($meta['offer_freshness'] ?? null) ? $meta['offer_freshness'] : [];

        $refreshStatus = strtolower(trim((string) ($meta['offer_refresh_status'] ?? '')));
        if ($refreshStatus === 'stale' || ($meta['offer_stale'] ?? false) === true) {
            $blockers[] = self::BLOCKER_STALE_PRICING;
        }

        if (($freshness['high_risk_cached_offer'] ?? false) === true) {
            $blockers[] = self::BLOCKER_STALE_PRICING;
        }

        $revalidationStatus = strtolower(trim((string) (
            $freshness['revalidation_status'] ?? $meta['revalidation_status'] ?? ''
        )));
        if ($revalidationStatus === 'expired') {
            $blockers[] = self::BLOCKER_REVALIDATION_EXPIRED;
        }

        $expiresAt = $this->offerFreshness->parseTimestamp(
            is_string($freshness['revalidation_expires_at'] ?? null) ? (string) $freshness['revalidation_expires_at'] : null
        );
        if ($expiresAt !== null && $expiresAt->isPast()) {
            $blockers[] = self::BLOCKER_REVALIDATION_EXPIRED;
        }

        $freshnessForCheck = array_merge($freshness, [
            'last_revalidated_at' => $meta['last_revalidated_at'] ?? $freshness['last_revalidated_at'] ?? null,
            'revalidation_status' => $meta['revalidation_status'] ?? $freshness['revalidation_status'] ?? null,
        ]);
        if (($freshness['requires_revalidation_before_checkout'] ?? false) === true
            && ! $this->offerFreshness->hasValidRecentRevalidation($freshnessForCheck)) {
            $blockers[] = self::BLOCKER_REVALIDATION_EXPIRED;
        }

        if (SabreOfferRefreshAcceptance::requiresAcceptanceFromMeta($meta)) {
            $blockers[] = self::BLOCKER_OFFER_REFRESH_CONFIRMATION;
        }

        if (($meta['requires_price_change_confirmation'] ?? false) === true
            && ($meta[SabreOfferRefreshAcceptance::META_ACCEPTED] ?? false) !== true) {
            $blockers[] = self::BLOCKER_PRICE_CHANGE_CONFIRMATION;
        } elseif (($meta[SabreOfferRefreshAcceptance::META_PRICE_CHANGED] ?? false) === true
            && ($meta[SabreOfferRefreshAcceptance::META_ACCEPTED] ?? false) !== true) {
            $blockers[] = self::BLOCKER_PRICE_CHANGE_CONFIRMATION;
        }

        return array_values(array_unique($blockers));
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    protected function hasControlledFareChangeAcceptance(array $meta): bool
    {
        $record = $meta[SabreControlledPnrFareChangeAcceptance::META_KEY] ?? null;
        if (! is_array($record)) {
            return false;
        }

        return ($record['accepted'] ?? false) === true
            && (string) ($record['accepted_for'] ?? '') === SabreControlledPnrFareChangeAcceptance::ACCEPTED_FOR_CONTROLLED_PNR_CREATE_RETRY;
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public function hasLegacySuccessRevalidationSignal(array $meta): bool
    {
        $selectedStatus = strtolower(trim((string) ($meta['selected_offer_revalidation_status'] ?? '')));
        $status = strtolower(trim((string) ($meta['revalidation_status'] ?? $meta['sabre_revalidation_status'] ?? '')));
        $hasSuccess = $selectedStatus === 'success' || $status === 'success';
        if (! $hasSuccess) {
            return false;
        }

        $lastAt = trim((string) ($meta['last_revalidated_at'] ?? ''));
        $selectedLastAt = trim((string) ($meta['selected_offer_last_revalidated_at'] ?? ''));

        return $lastAt !== '' || $selectedLastAt !== '';
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public function hasPayloadReadyContext(array $meta): bool
    {
        $handoff = is_array($meta['sabre_booking_context'] ?? null) ? $meta['sabre_booking_context'] : [];

        return ($handoff['ready_for_booking_payload'] ?? false) === true;
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    public function extractCertifiedRouteSelection(array $meta): array
    {
        $stored = is_array($meta['certified_route_selection'] ?? null) ? $meta['certified_route_selection'] : [];

        return $stored;
    }

    /**
     * @param  array<string, mixed>  $route
     */
    public function isCertifiedRouteSelectionValid(array $route): bool
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

    /**
     * @param  array<string, mixed>  $meta
     */
    protected function resolveSupplierConnectionId(Booking $booking, array $meta): int
    {
        $fromMeta = (int) ($meta['supplier_connection_id'] ?? 0);
        if ($fromMeta > 0) {
            return $fromMeta;
        }

        return (int) ($booking->supplier_connection_id ?? 0);
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

    /**
     * @param  list<array<string, mixed>>  $segments
     * @param  array<string, mixed>  $handoff
     * @param  array<string, mixed>  $safeRefreshAssess
     * @return list<string>
     */
    public function detectExistingPnr(Booking $booking): bool
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

    /**
     * @param  list<array<string, mixed>>  $segments
     * @param  array<string, mixed>  $handoff
     * @param  array<string, mixed>  $safeRefreshAssess
     * @return list<string>
     */
    protected function carrierChainFromSegments(array $segments, array $handoff, array $safeRefreshAssess): array
    {
        if (is_array($handoff['carrier_chain'] ?? null) && $handoff['carrier_chain'] !== []) {
            return array_values(array_map('strval', $handoff['carrier_chain']));
        }

        $chain = [];
        foreach ($segments as $seg) {
            if (! is_array($seg)) {
                continue;
            }
            $carrier = strtoupper(trim((string) ($seg['carrier'] ?? $seg['airline_code'] ?? '')));
            if ($carrier !== '' && ! in_array($carrier, $chain, true)) {
                $chain[] = $carrier;
            }
        }

        return $chain;
    }
}
