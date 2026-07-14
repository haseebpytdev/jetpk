<?php

namespace App\Enums;

enum BookingDocumentType: string
{
    case BookingConfirmation = 'booking_confirmation';
    case PaymentReceipt = 'payment_receipt';
    case TicketItinerary = 'ticket_itinerary';
    case Invoice = 'invoice';
    case RefundNote = 'refund_note';
    case CancellationConfirmation = 'cancellation_confirmation';
}
