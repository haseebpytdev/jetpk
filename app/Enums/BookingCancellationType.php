<?php

namespace App\Enums;

enum BookingCancellationType: string
{
    case BookingCancel = 'booking_cancel';
    case TicketVoid = 'ticket_void';
    case TicketRefund = 'ticket_refund';
    case SupplierCancel = 'supplier_cancel';
}
