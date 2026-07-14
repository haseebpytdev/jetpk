<?php

namespace App\Services\Suppliers\Sabre\Cancel;

use App\Enums\BookingStatus;
use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Models\SupplierBookingAttempt;
use App\Support\Bookings\SabreGdsPnrCancellationStateResolver;
use App\Support\Bookings\SupplierBookingAttemptGuard;
use App\Support\Platform\PlatformModuleEnforcer;

/**
 * Sabre GDS unticketed PNR cancel eligibility from stored booking state only (no supplier HTTP).
 */
final class SabreGdsCancelReadiness
{
    public const META_KEY = 'sabre_gds_cancel';

    public const ACTION_CANCEL_SABRE_PNR = 'cancel_sabre_pnr';

    public const ACTION_CANCELLATION_PENDING = 'cancellation_pending';

    public const ACTION_CANCELLED = 'cancelled';

    public const ACTION_MANUAL_TICKETED_REQUIRED = 'manual_ticketed_required';

    public const ACTION_NOT_ELIGIBLE = 'not_eligible';

    public function __construct(
        private readonly PlatformModuleEnforcer $platformModuleEnforcer,
        private readonly SupplierBookingAttemptGuard $attemptGuard,
        private readonly SabreGdsPnrCancellationStateResolver $pnrCancellationStateResolver,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function evaluate(Booking $booking): array
    {
        $booking->loadMissing(['tickets', 'cancellationRequests', 'supplierBookingAttempts']);

        $meta = is_array($booking->meta) ? $booking->meta : [];
        $provider = strtolower(trim((string) ($meta['supplier_provider'] ?? $booking->supplier ?? '')));
        $distributionChannel = $this->platformModuleEnforcer->distributionChannelFromBookingMeta($meta);
        $isNdcChannel = $this->platformModuleEnforcer->isSabreNdcDistributionChannel($distributionChannel);
        $cancelMeta = is_array($meta[self::META_KEY] ?? null) ? $meta[self::META_KEY] : [];
        $outcomeMeta = is_array($meta['sabre_cancel_outcome'] ?? null) ? $meta['sabre_cancel_outcome'] : [];

        $blockers = [];
        if ($provider !== SupplierProvider::Sabre->value) {
            $blockers[] = 'supplier_not_sabre';
        }
        if ($isNdcChannel) {
            $blockers[] = 'sabre_ndc_channel_not_gds_cancel';
        }
        if (! $this->platformModuleEnforcer->effectiveModuleEnabled('sabre_gds')) {
            $blockers[] = 'sabre_gds_module_disabled';
        }

        $pnr = trim((string) ($booking->pnr ?? $booking->supplier_reference ?? ''));
        if ($pnr === '') {
            $blockers[] = 'missing_pnr';
        }

        $ticketed = $this->isTicketed($booking, $meta);
        $cancelled = $this->isCancelled($booking, $cancelMeta);
        $inProgress = $this->isCancellationInProgress($booking, $cancelMeta);
        $adminGateEnabled = (bool) config('suppliers.sabre.admin_cancel_live_call_enabled', false);

        $actionState = self::ACTION_NOT_ELIGIBLE;
        $actionLabel = 'Not eligible';
        $adminMessage = 'Sabre GDS cancellation is not available for this booking.';
        $customerMessage = 'Cancellation must be handled by our team.';
        $canExecute = false;

        if ($cancelled) {
            $actionState = self::ACTION_CANCELLED;
            $actionLabel = 'PNR released/cancelled';
            $adminMessage = 'Sabre/GDS PNR has been released/cancelled. Handle refund/credit manually or close the booking.';
            $customerMessage = 'Your reservation with the airline has been cancelled. Contact our team for refund or credit assistance.';
        } elseif ($inProgress) {
            $actionState = self::ACTION_CANCELLATION_PENDING;
            $actionLabel = 'Cancellation pending';
            $adminMessage = 'Sabre cancellation is already in progress. Do not submit another cancel request.';
            $customerMessage = 'Your cancellation is being processed. We will update you shortly.';
        } elseif ($ticketed) {
            $actionState = self::ACTION_MANUAL_TICKETED_REQUIRED;
            $actionLabel = 'Manual action required for ticketed booking';
            $adminMessage = 'Ticketed Sabre GDS bookings cannot use Trip Orders cancelBooking. Use void/refund or manual airline cancellation.';
            $customerMessage = 'Your ticketed booking requires manual cancellation review by our team.';
        } elseif ($blockers === [] && $adminGateEnabled) {
            $actionState = self::ACTION_CANCEL_SABRE_PNR;
            $actionLabel = 'Release PNR';
            $adminMessage = 'Unticketed Sabre GDS PNR can be released/cancelled via Trip Orders cancelBooking after getBooking pre-check.';
            $customerMessage = 'Our team can cancel your unticketed reservation with the airline.';
            $canExecute = true;
        } elseif (! $adminGateEnabled) {
            $blockers[] = 'admin_cancel_live_gate_disabled';
            $adminMessage = 'Sabre GDS live cancellation is disabled for admin/staff. Enable SABRE_ADMIN_CANCEL_LIVE_CALL_ENABLED when ready.';
        }

        return [
            'eligible_provider' => $provider === SupplierProvider::Sabre->value && ! $isNdcChannel,
            'action_state' => $actionState,
            'action_label' => $actionLabel,
            'admin_message' => $adminMessage,
            'customer_message' => $customerMessage,
            'can_execute' => $canExecute,
            'ticketed' => $ticketed,
            'cancelled' => $cancelled,
            'in_progress' => $inProgress,
            'blockers' => $blockers,
            'stored_status' => is_string($cancelMeta['status'] ?? null) ? (string) $cancelMeta['status'] : null,
            'stored_classification' => is_string($cancelMeta['classification'] ?? $outcomeMeta['classification'] ?? null)
                ? (string) ($cancelMeta['classification'] ?? $outcomeMeta['classification'])
                : null,
            'stored_segment_statuses' => $this->normalizeSegmentStatuses($cancelMeta['airline_segment_statuses'] ?? null),
            'post_cancel_segment_count' => isset($cancelMeta['post_cancel_segment_count']) && is_numeric($cancelMeta['post_cancel_segment_count'])
                ? (int) $cancelMeta['post_cancel_segment_count']
                : (isset($outcomeMeta['post_cancel_segment_count']) && is_numeric($outcomeMeta['post_cancel_segment_count'])
                    ? (int) $outcomeMeta['post_cancel_segment_count']
                    : null),
            'admin_live_gate_enabled' => $adminGateEnabled,
        ];
    }

    /**
     * @param  array<string, mixed>  $cancelMeta
     */
    public function isCancelled(Booking $booking, array $cancelMeta = []): bool
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        if ($cancelMeta !== []) {
            $meta = array_merge($meta, [self::META_KEY => $cancelMeta]);
        }

        return $this->pnrCancellationStateResolver->isPnrCancelledOrReleased($booking, $meta);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public function isTicketed(Booking $booking, array $meta = []): bool
    {
        if ($booking->tickets()->exists() || $booking->status === BookingStatus::Ticketed) {
            return true;
        }
        if ($booking->ticketed_at !== null) {
            return true;
        }
        if (in_array((string) ($booking->ticketing_status ?? ''), ['ticketed', 'issued'], true)) {
            return true;
        }

        $sync = is_array($meta['pnr_itinerary_sync'] ?? null) ? $meta['pnr_itinerary_sync'] : [];

        return ($sync['is_ticketed'] ?? null) === true
            || ($sync['ticket_numbers_present'] ?? false) === true;
    }

    /**
     * @param  array<string, mixed>  $cancelMeta
     */
    public function isCancellationInProgress(Booking $booking, array $cancelMeta = []): bool
    {
        if (($cancelMeta['status'] ?? '') === 'in_progress') {
            return true;
        }

        $active = $this->attemptGuard->resolveActiveAttempt(
            $booking,
            SupplierProvider::Sabre->value,
            'cancel_booking',
        );

        return $active instanceof SupplierBookingAttempt;
    }

    /**
     * @return list<string>
     */
    protected function normalizeSegmentStatuses(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }

        $statuses = [];
        foreach ($raw as $status) {
            if (! is_scalar($status)) {
                continue;
            }
            $normalized = strtoupper(trim((string) $status));
            if ($normalized !== '') {
                $statuses[] = $normalized;
            }
        }

        return array_values(array_unique($statuses));
    }
}
