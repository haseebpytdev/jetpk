<?php

namespace App\Support\Bookings;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\SupplierBooking;
use App\Models\SupplierBookingAttempt;

/**
 * F9F: One-shot controlled retry allowance after F9E fare-change acceptance when preflight
 * would otherwise block with supplier_booking_retry_not_allowed on a prior fare-acceptance outcome.
 *
 * Applies only to sabre:controlled-create-pnr with exact confirm phrase. Does not weaken
 * general retry protection, public checkout, admin generic retry, or ticketing/cancellation paths.
 */
final class SabreControlledPnrRetryAllowanceGate
{
    public const META_KEY = 'controlled_supplier_retry_allowance';

    public const REASON_ACCEPTED_FARE_CHANGE_RETRY = 'accepted_fare_change_retry';

    public const USED_BY_CONTROLLED_PNR_COMMAND = 'controlled_pnr_command';

    public const USED_FOR_CONTROLLED_PNR_CREATE_AFTER_FARE_ACCEPTANCE = 'controlled_pnr_create_after_fare_acceptance';

    public const PREVIOUS_BLOCKER_RETRY_NOT_ALLOWED = 'supplier_booking_retry_not_allowed';

    public function __construct(
        protected SabreControlledPnrFareChangeAcceptance $fareChangeAcceptance,
        protected SabreSafeRefreshContext $safeRefreshContext,
    ) {}

    public function reasonCode(): string
    {
        return self::REASON_ACCEPTED_FARE_CHANGE_RETRY;
    }

    /**
     * @param  array<string, mixed>  $controlledOperationContext
     */
    public function allows(
        Booking $booking,
        SupplierBookingAttempt $meaningfulAttempt,
        string $attemptSource,
        bool $allowControlledStaffPnr,
        array $controlledOperationContext,
    ): bool {
        if ($attemptSource !== 'controlled_pnr_command' || ! $allowControlledStaffPnr) {
            return false;
        }

        if (($controlledOperationContext['controlled_pnr_create'] ?? false) !== true) {
            return false;
        }

        if (($controlledOperationContext['controlled_manual_review_approved'] ?? false) !== true) {
            return false;
        }

        $expectedConfirm = 'CREATE-PNR-FOR-BOOKING-'.$booking->id;
        if ((string) ($controlledOperationContext['controlled_approval_confirm_phrase'] ?? '') !== $expectedConfirm) {
            return false;
        }

        if ((bool) config('suppliers.sabre.ticketing_enabled', false)
            || (bool) config('suppliers.sabre.cancel_enabled', false)) {
            return false;
        }

        $booking->loadMissing(['supplierBookings', 'tickets']);
        $meta = is_array($booking->meta) ? $booking->meta : [];

        if ($this->retryAllowanceAlreadyUsed($meta)) {
            return false;
        }

        $provider = strtolower(trim((string) ($meta['supplier_provider'] ?? $booking->supplier ?? '')));
        if ($provider !== 'sabre' || (int) ($meta['supplier_connection_id'] ?? 0) <= 0) {
            return false;
        }

        if ($this->detectExistingPnr($booking)) {
            return false;
        }

        if ($booking->status === BookingStatus::Cancelled || $this->isTicketed($booking)) {
            return false;
        }

        if (! $this->isManualReviewApproved($meta)) {
            return false;
        }

        if (! $this->fareChangeAcceptance->isAccepted($meta)) {
            return false;
        }

        if (($meta[SabreOfferRefreshAcceptance::META_ACCEPTED] ?? false) !== true) {
            return false;
        }

        if (($meta[SabreOfferRefreshAcceptance::META_PRICE_CHANGED] ?? false) !== true
            && ($meta[SabreOfferRefreshAcceptance::META_REQUIRES_CONFIRMATION] ?? false) !== true) {
            return false;
        }

        if (! $this->hasRequiredControlledContext($meta)) {
            return false;
        }

        $readiness = is_array($controlledOperationContext['readiness_snapshot'] ?? null)
            ? $controlledOperationContext['readiness_snapshot']
            : [];

        if (($readiness['eligible'] ?? false) !== true
            || ($readiness['can_attempt_supplier_pnr'] ?? false) !== true
            || ($readiness['live_supplier_call_allowed'] ?? false) !== true
            || ($readiness['has_usable_controlled_pnr_context'] ?? false) !== true) {
            return false;
        }

        $blockers = is_array($readiness['blockers'] ?? null) ? $readiness['blockers'] : [];
        if ($blockers !== []) {
            return false;
        }

        if (($readiness['has_existing_pnr'] ?? false) === true
            || ($readiness['is_ticketed'] ?? false) === true
            || ($readiness['is_cancelled'] ?? false) === true) {
            return false;
        }

        return $this->priorOutcomeCompatibleWithFareAcceptanceRetry($meaningfulAttempt, $meta);
    }

    public function recordUsage(Booking $booking, SupplierBookingAttempt $meaningfulAttempt): void
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        if ($this->retryAllowanceAlreadyUsed($meta)) {
            return;
        }

        $meta[self::META_KEY] = [
            'used' => true,
            'used_at' => now()->toIso8601String(),
            'used_by' => self::USED_BY_CONTROLLED_PNR_COMMAND,
            'used_for' => self::USED_FOR_CONTROLLED_PNR_CREATE_AFTER_FARE_ACCEPTANCE,
            'booking_reference' => (string) ($booking->reference_code ?? ''),
            'previous_blocker' => self::PREVIOUS_BLOCKER_RETRY_NOT_ALLOWED,
            'prior_meaningful_error_code' => strtolower(trim((string) ($meaningfulAttempt->error_code ?? ''))),
            'required_acceptance_key' => SabreControlledPnrFareChangeAcceptance::META_KEY,
        ];

        $booking->forceFill(['meta' => $meta])->save();
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public function retryAllowanceAlreadyUsed(array $meta): bool
    {
        $record = $meta[self::META_KEY] ?? null;
        if (! is_array($record)) {
            return false;
        }

        return ($record['used'] ?? false) === true;
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    protected function hasRequiredControlledContext(array $meta): bool
    {
        $pricingSnapshot = is_array($meta['pricing_snapshot'] ?? null) ? $meta['pricing_snapshot'] : [];
        if ($pricingSnapshot === []) {
            return false;
        }

        $validatedSnapshot = is_array($meta['validated_offer_snapshot'] ?? null) ? $meta['validated_offer_snapshot'] : [];
        if ($validatedSnapshot === []) {
            return false;
        }

        $safeRefreshAssess = $this->safeRefreshContext->assess($meta);
        if (($safeRefreshAssess['safe_refresh_context_complete'] ?? false) !== true) {
            return false;
        }

        $certifiedRoute = is_array($meta['certified_route_selection'] ?? null) ? $meta['certified_route_selection'] : [];
        if (! $this->isCertifiedRouteSelectionValid($certifiedRoute)) {
            return false;
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    protected function priorOutcomeCompatibleWithFareAcceptanceRetry(
        SupplierBookingAttempt $attempt,
        array $meta,
    ): bool {
        $errorCode = strtolower(trim((string) ($attempt->error_code ?? '')));
        $status = strtolower(trim((string) ($attempt->status ?? '')));
        $safeSummary = is_array($attempt->safe_summary) ? $attempt->safe_summary : [];
        $summaryReasonCode = strtolower(trim((string) ($safeSummary['reason_code'] ?? '')));

        if ($errorCode === SabreOfferRefreshAcceptance::ERROR_CODE_REQUIRES_ACCEPTANCE) {
            return true;
        }

        if (in_array($status, ['manual_review', 'needs_review'], true)) {
            if ($errorCode === SabreOfferRefreshAcceptance::ERROR_CODE_REQUIRES_ACCEPTANCE
                || $summaryReasonCode === SabreOfferRefreshAcceptance::ERROR_CODE_REQUIRES_ACCEPTANCE
                || ($safeSummary['offer_refresh_requires_acceptance'] ?? false) === true) {
                return true;
            }
        }

        if ($errorCode === 'defer_supplier_booking_to_manual_review'
            && ($meta['defer_supplier_booking_to_manual_review'] ?? false) === true
            && (string) ($meta['supplier_pnr_deferred_reason'] ?? '') === SabreCertifiedRouteSelector::DEFER_REASON) {
            return true;
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $meta
     */
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
            fn (SupplierBooking $item) => in_array((string) $item->status, ['created', 'pending_ticketing', 'ticketed'], true),
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
