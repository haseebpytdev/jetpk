<?php

namespace App\Support\Bookings;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\SupplierBooking;

/**
 * F9D/F9E: Narrow controlled-command defer override after explicit F9C manual-review approval
 * and F9E fare-change acceptance when applicable.
 *
 * Does not clear historical defer meta; does not bypass pricing/revalidation/duplicate PNR gates.
 */
final class SabreControlledPnrApprovalOverrideGate
{
    public function __construct(
        protected SabreControlledPnrFareChangeAcceptance $fareChangeAcceptance,
    ) {}

    /**
     * @param  array<string, mixed>  $controlledOperationContext
     */
    public function allowsDeferOverride(
        Booking $booking,
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

        $meta = is_array($booking->meta) ? $booking->meta : [];
        if (! $this->isApprovedMeta($meta)) {
            return false;
        }

        if ($this->fareChangeAcceptance->fareChangeGateActive($meta)
            && ! $this->fareChangeAcceptance->isAccepted($meta)) {
            return false;
        }

        if (($meta['defer_supplier_booking_to_manual_review'] ?? false) !== true) {
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

        if (($readiness['has_existing_pnr'] ?? false) === true
            || ($readiness['is_ticketed'] ?? false) === true
            || ($readiness['is_cancelled'] ?? false) === true) {
            return false;
        }

        $booking->loadMissing(['supplierBookings', 'tickets']);
        if ($this->detectExistingPnr($booking)) {
            return false;
        }

        if ($booking->status === BookingStatus::Cancelled) {
            return false;
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    protected function isApprovedMeta(array $meta): bool
    {
        $record = $meta[SabreControlledPnrManualReviewApproval::META_KEY] ?? null;
        if (! is_array($record)) {
            return false;
        }

        return ($record['approved'] ?? false) === true
            && (string) ($record['approved_for'] ?? '') === SabreControlledPnrManualReviewApproval::APPROVED_FOR_CONTROLLED_PNR_CREATE;
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
}
