<?php

namespace App\Support\Bookings;

use App\Models\Booking;
use App\Models\SupplierBookingAttempt;

/**
 * BF7-J-OPS-FIX3: One-shot admin/staff operational retry after prior NN HaltOnStatus failure
 * when FIX2 allow-NN strategy is now enabled (omit NN/WN from HaltOnStatus on next live create).
 */
final class SabreOperationalAllowNnStrategyChangedRetryGate
{
    public const RETRY_POLICY = 'operational_allow_nn_strategy_changed';

    public function __construct(
        protected SabreOperationalPnrReadiness $operationalPnrReadiness,
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

        if (! $this->operationalPnrReadiness->wouldAttemptPnr($booking)) {
            return false;
        }

        if (strtolower(trim((string) $meaningfulAttempt->error_code)) !== 'sabre_booking_application_error') {
            return false;
        }

        if (! SabreCpnrOperationalAllowNnPolicy::isConfigEnabled()) {
            return false;
        }

        $safeSummary = is_array($meaningfulAttempt->safe_summary) ? $meaningfulAttempt->safe_summary : [];
        if (! SabrePnrFailureClassifier::safeSummaryIndicatesPriorNnHaltOnStatusFailure($safeSummary)) {
            return false;
        }

        if (($safeSummary['create_halt_on_status_nn_omitted'] ?? $safeSummary['halt_on_status_nn_omitted'] ?? false) === true) {
            return false;
        }

        if ($this->hasExhaustedStrategyChangedRetry($booking)) {
            return false;
        }

        return true;
    }

    /**
     * @return array{retry_policy: string, prior_error_code: string, prior_halt_on_status_nn: true}
     */
    public function buildRetryPolicyAuditSlice(SupplierBookingAttempt $priorAttempt): array
    {
        return [
            'retry_policy' => self::RETRY_POLICY,
            'prior_error_code' => strtolower(trim((string) $priorAttempt->error_code)) !== ''
                ? (string) $priorAttempt->error_code
                : 'sabre_booking_application_error',
            'prior_halt_on_status_nn' => true,
        ];
    }

    protected function hasExhaustedStrategyChangedRetry(Booking $booking): bool
    {
        $booking->loadMissing('supplierBookingAttempts');

        foreach ($booking->supplierBookingAttempts as $attempt) {
            if (SupplierBookingAttemptResolution::isRetryBlockedWrapperAttempt($attempt)) {
                continue;
            }
            if (strtolower((string) $attempt->action) !== 'create_pnr') {
                continue;
            }
            if (! in_array(strtolower((string) $attempt->status), ['failed', 'needs_review', 'manual_review'], true)) {
                continue;
            }

            $summary = is_array($attempt->safe_summary) ? $attempt->safe_summary : [];
            if (($summary['create_halt_on_status_nn_omitted'] ?? $summary['halt_on_status_nn_omitted'] ?? false) !== true) {
                continue;
            }

            return true;
        }

        return false;
    }
}
