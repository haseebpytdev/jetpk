<?php

namespace App\Support\BackOffice;

use App\Models\BookingPayment;

final class BackOfficePaymentPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function present(BookingPayment $payment): array
    {
        return [
            'id' => (string) $payment->id,
            'booking_id' => (string) $payment->booking_id,
            'status' => $payment->status->value,
            'amount' => (float) $payment->amount,
            'currency' => $payment->currency,
            'method' => $payment->method->value,
            'verified_at' => $payment->verified_at?->toIso8601String(),
            'rejected_at' => $payment->rejected_at?->toIso8601String(),
        ];
    }
}
