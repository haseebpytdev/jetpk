<?php

namespace App\Support\BackOffice;

use App\Models\BookingRefund;

final class BackOfficeRefundPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function present(BookingRefund $refund): array
    {
        return [
            'id' => (string) $refund->id,
            'booking_id' => (string) $refund->booking_id,
            'status' => $refund->status->value,
            'amount' => (float) $refund->amount,
            'currency' => $refund->currency,
            'approved_at' => $refund->approved_at?->toIso8601String(),
            'paid_at' => $refund->paid_at?->toIso8601String(),
            'rejected_at' => $refund->rejected_at?->toIso8601String(),
        ];
    }
}
