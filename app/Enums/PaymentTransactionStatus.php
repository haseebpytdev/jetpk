<?php

namespace App\Enums;

enum PaymentTransactionStatus: string
{
    case Initiated = 'initiated';
    case Pending = 'pending';
    case Created = 'created';
    case Paid = 'paid';
    case Failed = 'failed';
    case Declined = 'declined';
    case Cancelled = 'cancelled';
    case Expired = 'expired';
    case Refunded = 'refunded';
    case VerificationFailed = 'verification_failed';

    public function isTerminal(): bool
    {
        return in_array($this, [
            self::Paid,
            self::Failed,
            self::Declined,
            self::Cancelled,
            self::Expired,
            self::Refunded,
            self::VerificationFailed,
        ], true);
    }

    public function isPaid(): bool
    {
        return $this === self::Paid;
    }
}
