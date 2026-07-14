<?php

namespace App\Enums;

enum CommunicationTemplateEvent: string
{
    case BookingRequestReceived = 'booking_request_received';
    case BookingConfirmed = 'booking_confirmed';
    case BookingStatusChanged = 'booking_status_changed';
    case PaymentSubmitted = 'payment_submitted';
    case PaymentVerified = 'payment_verified';
    case PaymentRejected = 'payment_rejected';
    case SupplierBookingCreated = 'supplier_booking_created';
    case TicketIssued = 'ticket_issued';
    case BookingCancelled = 'booking_cancelled';
    case StaffAssigned = 'staff_assigned';
    case RefundRequested = 'refund_requested';
    case RefundPaid = 'refund_paid';
}
