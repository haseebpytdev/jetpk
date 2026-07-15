<?php

namespace App\Support\Bookings;

use App\Enums\BookingStatus;
use App\Models\Booking;

/**
 * F9C: Explicit operator approval marker for controlled Sabre PNR burn-in (meta only; no supplier HTTP).
 */
final class SabreControlledPnrManualReviewApproval
{
    public const META_KEY = 'controlled_pnr_manual_review';

    public const APPROVAL_SOURCE_ARTISAN = 'artisan';

    public const APPROVED_FOR_CONTROLLED_PNR_CREATE = 'controlled_pnr_create';

    public function __construct(
        protected SabrePnrCertificationSupport $certificationSupport,
        protected SabreControlledPnrContextDigest $contextDigest,
    ) {}

    public function isApproved(array $meta): bool
    {
        $record = $this->extractRecord($meta);

        return ($record['approved'] ?? false) === true
            && (string) ($record['approved_for'] ?? '') === self::APPROVED_FOR_CONTROLLED_PNR_CREATE;
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
     *     has_usable_controlled_pnr_context: bool,
     *     safe_refresh_context_complete: bool,
     *     pricing_snapshot_present: bool,
     *     certified_route_selection_present: bool,
     * }
     */
    public function evaluateApprovalEligibility(Booking $booking): array
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

        $digest = $this->certificationSupport->isSabreBooking($booking)
            ? $this->contextDigest->classify($booking)
            : [];

        $hasUsableContext = ($digest['has_usable_controlled_pnr_context'] ?? false) === true;
        $safeRefreshComplete = ($digest['safe_refresh_context_complete'] ?? false) === true;
        $pricingSnapshotPresent = ($digest['pricing_snapshot_present'] ?? false) === true;
        $certifiedRoute = $this->contextDigest->extractCertifiedRouteSelection($meta);
        $certifiedRoutePresent = $this->contextDigest->isCertifiedRouteSelectionValid($certifiedRoute);

        if (! $hasUsableContext) {
            $blockers[] = 'controlled_context_unusable';
        }
        if (! $safeRefreshComplete) {
            $blockers[] = 'safe_refresh_context_incomplete';
        }
        if (! $pricingSnapshotPresent) {
            $blockers[] = 'pricing_snapshot_missing';
        }
        if (! $certifiedRoutePresent) {
            $blockers[] = 'certified_route_selection_missing';
        }

        if ($this->isApproved($meta)) {
            $blockers[] = 'controlled_pnr_manual_review_already_approved';
        }

        $blockers = array_values(array_unique($blockers));

        return [
            'eligible' => $blockers === [],
            'blockers' => $blockers,
            'has_usable_controlled_pnr_context' => $hasUsableContext,
            'safe_refresh_context_complete' => $safeRefreshComplete,
            'pricing_snapshot_present' => $pricingSnapshotPresent,
            'certified_route_selection_present' => $certifiedRoutePresent,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function buildApprovalRecord(Booking $booking, string $reason, string $approvedBy): array
    {
        return [
            'approved' => true,
            'approved_at' => now()->toIso8601String(),
            'approved_by' => $this->sanitizeOperatorLabel($approvedBy),
            'approval_source' => self::APPROVAL_SOURCE_ARTISAN,
            'approval_reason' => $this->sanitizeReason($reason),
            'approval_booking_reference' => (string) ($booking->reference_code ?? ''),
            'approved_for' => self::APPROVED_FOR_CONTROLLED_PNR_CREATE,
        ];
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
            return 'controlled_pnr_burn_in';
        }

        return mb_substr($clean, 0, 200);
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
