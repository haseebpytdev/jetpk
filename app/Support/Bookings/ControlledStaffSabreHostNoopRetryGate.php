<?php

namespace App\Support\Bookings;

use App\Models\Booking;
use App\Models\SupplierBookingAttempt;
use App\Services\Booking\BookingOperationalPrecheckService;

/**
 * E3C: Shared admin/staff gate for controlled host NOOP diagnostic PNR retry execution.
 */
final class ControlledStaffSabreHostNoopRetryGate
{
    public function __construct(
        protected SabrePnrCertificationSupport $sabrePnrCertificationSupport,
        protected BookingOperationalPrecheckService $operationalPrecheckService,
    ) {}

    /**
     * @param  'public_checkout'|'admin'|'staff'|'system'|'manual'  $source
     */
    public function allows(
        Booking $booking,
        ?SupplierBookingAttempt $meaningfulAttempt,
        bool $allowControlledStaffPnr,
        string $source,
    ): bool {
        if ($meaningfulAttempt === null || ! $allowControlledStaffPnr || ! in_array($source, ['admin', 'staff'], true)) {
            return false;
        }

        $meta = is_array($booking->meta) ? $booking->meta : [];
        $provider = strtolower(trim((string) ($meta['supplier_provider'] ?? $booking->supplier ?? '')));
        if ($provider !== 'sabre') {
            return false;
        }

        if ((bool) config('suppliers.sabre.ticketing_enabled', false)) {
            return false;
        }

        if (trim((string) ($booking->pnr ?? '')) !== ''
            || trim((string) ($booking->supplier_reference ?? '')) !== '') {
            return false;
        }

        $booking->loadMissing('supplierBookings');
        $hasSupplierBookingRecord = $booking->supplierBookings->contains(
            fn ($item) => in_array((string) $item->status, ['created', 'pending_ticketing', 'ticketed'], true),
        );
        if ($hasSupplierBookingRecord) {
            return false;
        }

        $safeSummary = is_array($meaningfulAttempt->safe_summary) ? $meaningfulAttempt->safe_summary : [];
        $errorCode = (string) ($meaningfulAttempt->error_code ?? '');
        if (! SabrePnrFailureClassifier::isControlledStaffHostNoopDiagnosticRetryable($errorCode, $safeSummary)) {
            return false;
        }

        $diag = $this->sabrePnrCertificationSupport->buildMultiSegmentPnrReadinessDiagnostics($booking);
        if (($diag['admin_pnr_live_action_allowed'] ?? false) !== true) {
            return false;
        }

        return $this->controlledStaffSafeRefreshOrOfferFreshnessReady($booking, $meta);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    protected function controlledStaffSafeRefreshOrOfferFreshnessReady(Booking $booking, array $meta): bool
    {
        $booking->loadMissing(['passengers', 'contact']);

        if ($booking->passengers->isEmpty() || $booking->contact === null) {
            return false;
        }

        $hasContact = filled($booking->contact->email) || filled($booking->contact->phone);
        if (! $hasContact) {
            return false;
        }

        if ($this->operationalPrecheckService->validatePassengerReadiness($booking) !== []) {
            return false;
        }

        $hasValidationSnapshot = isset($meta['validated_offer_snapshot']) || isset($meta['normalized_offer_snapshot']);
        $validationStatus = (string) ($meta['offer_validation_status'] ?? '');
        $offerIsValid = in_array($validationStatus, ['valid', 'validated', 'ok', 'pass', 'fresh'], true)
            || ($validationStatus === '' && $hasValidationSnapshot);

        if ($offerIsValid && $hasValidationSnapshot) {
            return true;
        }

        return (app(SabreSafeRefreshContext::class)->assess($meta)['safe_refresh_context_complete'] ?? false) === true;
    }
}
