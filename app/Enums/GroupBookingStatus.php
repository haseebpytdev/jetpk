<?php

namespace App\Enums;

enum GroupBookingStatus: string
{
    case PendingPassengerDetails = 'pending_passenger_details';
    case ReservedAwaitingPayment = 'reserved_awaiting_payment';
    case PaymentPending = 'payment_pending';
    case ManualPaymentSubmitted = 'manual_payment_submitted';
    case ManualPaymentPendingReview = 'manual_payment_pending_review';
    case Confirmed = 'confirmed';
    case Expired = 'expired';
    case Cancelled = 'cancelled';
    case Released = 'released';
    case SupplierReleaseFailed = 'supplier_release_failed';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::PendingPassengerDetails => 'Pending passenger details',
            self::ReservedAwaitingPayment => 'Reserved — awaiting payment',
            self::PaymentPending => 'Payment pending',
            self::ManualPaymentSubmitted => 'Manual payment submitted',
            self::ManualPaymentPendingReview => 'Manual payment pending review',
            self::Confirmed => 'Confirmed',
            self::Expired => 'Expired',
            self::Cancelled => 'Cancelled',
            self::Released => 'Released',
            self::SupplierReleaseFailed => 'Supplier release failed',
            self::Failed => 'Failed',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
