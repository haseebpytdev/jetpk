<?php

namespace App\Enums;

enum BookingCancellationStatus: string
{
    case Requested = 'requested';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Processed = 'processed';
    case Cancelled = 'cancelled';
}
