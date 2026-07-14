<?php

namespace App\Services\Booking;

use App\Enums\BookingDocumentType;
use App\Enums\BookingPaymentStatus;
use App\Models\Booking;
use App\Support\Bookings\AdminBookingSupplierActions;

class BookingActionStateService
{
    public function __construct(
        protected AdminBookingSupplierActions $adminBookingSupplierActions,
    ) {}

    /**
     * @return array{
     *   next_action:string,
     *   enabled_actions:array<int, string>,
     *   disabled_actions:array<int, string>,
     *   disabled_reasons:array<string, string>,
     *   workflow_step_statuses:array<string, string>
     * }
     */
    public function build(Booking $booking, bool $genericSupplierEligible = false, bool $genericTicketingEligible = false): array
    {
        $supplierActions = $this->adminBookingSupplierActions->build(
            $booking,
            $genericSupplierEligible,
            $genericTicketingEligible,
        );

        $booking->loadMissing([
            'contact',
            'payments',
            'documents',
            'tickets',
            'supplierBookings',
            'bookingNotes',
        ]);

        $hasContact = $booking->contact !== null
            && (filled($booking->contact->email) || filled($booking->contact->phone));
        $paymentVerified = (string) ($booking->payment_status ?? '') === 'paid'
            || $booking->payments->contains(fn ($p) => (string) $p->status->value === BookingPaymentStatus::Verified->value);
        $paymentPendingReview = $booking->payments->contains(
            fn ($p) => in_array((string) $p->status->value, [BookingPaymentStatus::Submitted->value, BookingPaymentStatus::Pending->value], true)
        );
        $hasUnpaidOrPartialBalance = in_array((string) ($booking->payment_status ?? 'unpaid'), ['unpaid', 'partial'], true)
            || (float) ($booking->balance_due ?? 0) > 0;

        $hasSupplierPnr = filled($booking->pnr)
            || $booking->supplierBookings->contains(fn ($sb) => filled($sb->pnr));
        $supplierFailedOrManualReview = in_array((string) ($booking->supplier_booking_status ?? ''), ['failed', 'manual_review'], true);

        $ticketIssued = $booking->tickets->isNotEmpty()
            || in_array((string) ($booking->ticketing_status ?? ''), ['ticketed', 'issued'], true);

        $hasInvoice = $booking->documents->contains(fn ($d) => $d->document_type === BookingDocumentType::Invoice);
        $hasReceipt = $booking->documents->contains(fn ($d) => $d->document_type === BookingDocumentType::PaymentReceipt);
        $hasItinerary = $booking->documents->contains(fn ($d) => $d->document_type === BookingDocumentType::TicketItinerary) || $ticketIssued;
        $hasBookingConfirmation = $booking->documents->contains(fn ($d) => $d->document_type === BookingDocumentType::BookingConfirmation);

        $enabled = [];
        $disabled = [];
        $reasons = [];

        $this->setAction($enabled, $disabled, $reasons, 'generate_invoice', true, 'Fare details are required first.');
        $this->setAction($enabled, $disabled, $reasons, 'record_payment', true, 'Payment collection is unavailable for this booking.');
        $this->setAction(
            $enabled,
            $disabled,
            $reasons,
            'send_payment_reminder',
            $hasContact && $hasUnpaidOrPartialBalance,
            ! $hasContact
                ? 'Customer or agent contact is required first.'
                : 'No unpaid or partial balance exists.'
        );
        $this->setAction($enabled, $disabled, $reasons, 'add_note', true, 'Note action is not available.');
        $this->setAction($enabled, $disabled, $reasons, 'assign_staff', true, 'Staff assignment is not available.');

        $this->setAction(
            $enabled,
            $disabled,
            $reasons,
            'create_supplier_booking',
            (bool) ($supplierActions['can_create_pnr'] ?? false),
            (string) ($supplierActions['create_pnr_reason'] ?? 'Supplier PNR is not available.')
        );
        $this->setAction(
            $enabled,
            $disabled,
            $reasons,
            'issue_ticket',
            (bool) ($supplierActions['can_issue_ticket_live'] ?? false),
            (string) ($supplierActions['ticketing_status_message'] ?? 'Automated ticketing is not available.')
        );
        $this->setAction(
            $enabled,
            $disabled,
            $reasons,
            'generate_receipt',
            $paymentVerified,
            'Verified payment is required.'
        );
        $this->setAction(
            $enabled,
            $disabled,
            $reasons,
            'generate_itinerary',
            $ticketIssued,
            'Ticket must be issued first.'
        );
        $this->setAction(
            $enabled,
            $disabled,
            $reasons,
            'generate_ticket_itinerary',
            $ticketIssued,
            'Ticket must be issued first.'
        );
        $this->setAction(
            $enabled,
            $disabled,
            $reasons,
            'send_ticket_email',
            $ticketIssued && $hasContact,
            ! $ticketIssued ? 'Ticket must be issued first.' : 'Customer or agent contact is required first.'
        );
        $this->setAction(
            $enabled,
            $disabled,
            $reasons,
            'generate_booking_confirmation',
            true,
            'Booking confirmation is not available.'
        );
        $this->setAction(
            $enabled,
            $disabled,
            $reasons,
            'send_payment_confirmation',
            $paymentVerified && $hasContact,
            ! $paymentVerified ? 'Verified payment is required first.' : 'Customer or agent contact is required first.'
        );

        $nextAction = (string) ($supplierActions['primary_cta_label'] ?? 'Review booking workflow');

        $workflow = [
            'payment' => $paymentVerified ? 'completed' : ($paymentPendingReview ? 'in_review' : 'pending'),
            'supplier_pnr' => ($supplierActions['has_pnr_or_reference'] ?? $hasSupplierPnr)
                ? 'completed'
                : (($supplierActions['stale_segment'] ?? false) || ($supplierActions['staff_review'] ?? false) || $supplierFailedOrManualReview ? 'blocked' : 'pending'),
            'ticketing' => $ticketIssued
                ? 'completed'
                : (($supplierActions['has_pnr_or_reference'] ?? false) && ($supplierActions['can_issue_ticket_live'] ?? false) ? 'ready' : 'blocked'),
            'documents_invoice' => ($hasInvoice || in_array('generate_invoice', $enabled, true)) ? 'ready' : 'pending',
            'documents_receipt' => $hasReceipt ? 'completed' : ($paymentVerified ? 'ready' : 'blocked'),
            'itinerary' => $hasItinerary ? 'completed' : ($ticketIssued ? 'ready' : 'blocked'),
            'booking_confirmation' => $hasBookingConfirmation ? 'completed' : 'ready',
        ];

        return [
            'next_action' => $nextAction,
            'enabled_actions' => array_values($enabled),
            'disabled_actions' => array_values($disabled),
            'disabled_reasons' => $reasons,
            'workflow_step_statuses' => $workflow,
            'primary_cta_tab' => (string) ($supplierActions['primary_cta_tab'] ?? 'overview'),
            'primary_cta_hash' => (string) ($supplierActions['primary_cta_hash'] ?? ''),
        ];
    }

    /**
     * @param  array<int, string>  $enabled
     * @param  array<int, string>  $disabled
     * @param  array<string, string>  $reasons
     */
    private function setAction(array &$enabled, array &$disabled, array &$reasons, string $key, bool $isEnabled, string $disabledReason): void
    {
        if ($isEnabled) {
            $enabled[] = $key;

            return;
        }

        $disabled[] = $key;
        $reasons[$key] = $disabledReason;
    }
}
