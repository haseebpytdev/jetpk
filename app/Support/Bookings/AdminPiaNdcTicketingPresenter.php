<?php

namespace App\Support\Bookings;

use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Services\Bookings\PiaNdcEticketDeliveryService;
use App\Services\Suppliers\PiaNdc\PiaNdcOrderOperationPreflight;
use App\Services\Suppliers\PiaNdc\PiaNdcTicketingService;
use App\Services\Suppliers\PiaNdc\PiaNdcTicketPreviewService;
use App\Services\Suppliers\PiaNdc\PiaNdcVoidTicketService;
use App\Services\Suppliers\TicketingService;

/**
 * Admin booking detail: PIA NDC ticketing / void / e-ticket actions (R12S, PIA-NDC-OPS1).
 */
final class AdminPiaNdcTicketingPresenter
{
    public function __construct(
        private readonly PiaNdcOrderOperationPreflight $preflight,
        private readonly PiaNdcVoidTicketService $voidTicketService,
        private readonly PiaNdcEticketDeliveryService $eticketDeliveryService,
        private readonly TicketingService $ticketingService,
        private readonly AdminBookingSupplierActionGate $actionGate,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function panel(Booking $booking, bool $genericTicketingEligible): array
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $provider = strtolower(trim((string) ($meta['supplier_provider'] ?? $booking->supplier ?? '')));
        if ($provider !== SupplierProvider::PiaNdc->value) {
            return ['show' => false];
        }

        $booking->loadMissing(['tickets', 'contact', 'passengers', 'latestSupplierBooking']);
        $context = is_array($meta['pia_ndc_context'] ?? null) ? $meta['pia_ndc_context'] : [];
        $interpreted = strtolower(trim((string) ($context['interpreted_status'] ?? '')));
        $orderStatus = strtoupper(trim((string) ($context['order_status'] ?? '')));
        $released = ($context['option_pnr_released'] ?? false) === true
            || in_array($interpreted, [
                PiaNdcBookingStatusInterpreter::STATUS_RELEASED,
                PiaNdcBookingStatusInterpreter::STATUS_NO_ACTIVE_SEGMENTS,
            ], true)
            || in_array($orderStatus, ['CLOSED', 'CANCELLED', 'CANCELED'], true);

        $voided = PiaNdcVoidLocalReconciliation::isVoided($booking);
        $voidRequiresReview = PiaNdcVoidLocalReconciliation::requiresVoidReview($booking);
        $ticketed = ! $voided && ! $voidRequiresReview && (
            $booking->tickets->contains(fn ($ticket): bool => strtolower(trim((string) $ticket->status)) !== 'voided')
            || in_array((string) ($booking->ticketing_status ?? ''), ['ticketed', 'issued'], true)
            || $this->preflight->duplicateTicketGuard($booking)
        );
        $manual = $this->actionGate->piaNdcManualTicketing($booking, $genericTicketingEligible);
        $strictEligible = $genericTicketingEligible
            && $this->ticketingService->isBookingEligibleForTicketing($booking)
            && $manual['hard_block_reason'] === null;

        $canPreview = $manual['can_manual_preview'];
        $canIssue = $manual['can_manual_issue'];
        $canVoid = $this->voidTicketService->canVoidBooking($booking);
        $canResendEticket = $this->eticketDeliveryService->canResend($booking);

        $ticketDocInfos = is_array($context['ticket_doc_infos'] ?? null) ? $context['ticket_doc_infos'] : [];
        $couponStatuses = [];
        foreach ($ticketDocInfos as $doc) {
            foreach ((array) ($doc['coupon_status_codes'] ?? []) as $code) {
                if (is_scalar($code) && trim((string) $code) !== '') {
                    $couponStatuses[] = strtoupper(trim((string) $code));
                }
            }
        }

        $voidMeta = is_array($meta[PiaNdcOperationAuditRecorder::META_VOID_TICKET] ?? null)
            ? $meta[PiaNdcOperationAuditRecorder::META_VOID_TICKET]
            : (is_array($meta[PiaNdcVoidLocalReconciliation::META_LAST_VOID_RESPONSE] ?? null)
                ? $meta[PiaNdcVoidLocalReconciliation::META_LAST_VOID_RESPONSE]
                : []);

        return [
            'show' => true,
            'title' => 'PIA NDC ticketing',
            'can_preview' => $canPreview,
            'can_issue' => $canIssue,
            'can_void' => $canVoid,
            'can_resend_eticket' => $canResendEticket,
            'admin_override_allowed' => $manual['admin_override_allowed'],
            'requires_admin_confirm' => $manual['requires_admin_confirm'],
            'warnings' => $manual['warnings'],
            'itinerary_synced' => $manual['itinerary_synced'],
            'selected_fare_present' => $manual['selected_fare_present'],
            'strict_ticketing_eligible' => $strictEligible,
            'preview_confirm_phrase' => PiaNdcTicketPreviewService::PREVIEW_CONFIRM_PHRASE,
            'issue_confirm_phrase' => PiaNdcTicketingService::ISSUE_CONFIRM_PHRASE,
            'void_confirm_phrase' => PiaNdcVoidTicketService::VOID_CONFIRM_PHRASE,
            'resend_confirm_phrase' => PiaNdcEticketDeliveryService::RESEND_CONFIRM_PHRASE,
            'preview_blocked_reason' => $canPreview ? null : ($manual['hard_block_reason'] ?? 'Ticket preview is not available for this booking.'),
            'issue_blocked_reason' => $canIssue ? null : ($manual['hard_block_reason'] ?? 'Issue ticket is not available for this booking.'),
            'void_blocked_reason' => $canVoid ? null : $this->voidTicketService->voidBlockedReason($booking),
            'resend_blocked_reason' => $canResendEticket ? null : $this->eticketDeliveryService->resendBlockedReason($booking),
            'ticket_numbers' => is_array($context['ticket_numbers'] ?? null)
                ? implode(', ', array_map('strval', $context['ticket_numbers']))
                : ($booking->tickets->pluck('ticket_number')->filter()->implode(', ') ?: '—'),
            'coupon_statuses' => $couponStatuses !== [] ? implode(', ', array_values(array_unique($couponStatuses))) : '—',
            'airline_locator' => trim((string) ($context['airline_locator'] ?? '')) !== '' ? (string) $context['airline_locator'] : null,
            'payment_required_by' => $booking->payment_required_by?->format('d M Y H:i') ?? null,
            'ticketing_status' => (string) ($context['ticketing_status'] ?? $booking->ticketing_status ?? '—'),
            'void_status' => $voided
                ? 'voided'
                : ($voidRequiresReview ? 'requires_review' : (string) ($context['void_status'] ?? '—')),
            'latest_void_attempt_status' => (string) ($voidMeta['status'] ?? '—'),
            'latest_void_attempt_at' => (string) ($voidMeta['completed_at'] ?? $voidMeta['attempted_at'] ?? '—'),
            'latest_void_supplier_summary' => trim((string) ($voidMeta['operation'] ?? '')) !== ''
                ? trim((string) ($voidMeta['operation'] ?? '')).' / '.(string) ($voidMeta['void_status'] ?? $voidMeta['status'] ?? '—')
                : '—',
            'void_requires_review' => $voidRequiresReview,
            'released' => $released,
            'ticketed' => $ticketed,
            'voided' => $voided,
        ];
    }
}
