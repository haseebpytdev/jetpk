<?php

namespace App\Enums;

enum BookingPaymentStatus: string
{
    case Pending = 'pending';
    case Submitted = 'submitted';
    case Verified = 'verified';
    case Rejected = 'rejected';
    case Refunded = 'refunded';
    case Partial = 'partial';
}
