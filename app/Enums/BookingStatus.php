<?php

namespace App\Enums;

enum BookingStatus: string
{
    case Draft = 'draft';
    case Pending = 'pending';
    case FareReview = 'fare_review';
    case Confirmed = 'confirmed';
    case PaymentPending = 'payment_pending';
    case Paid = 'paid';
    case TicketingPending = 'ticketing_pending';
    case Ticketed = 'ticketed';
    case Cancelled = 'cancelled';
    case Expired = 'expired';
    case Failed = 'failed';
    case Refunded = 'refunded';
}
