<?php

namespace App\Support\Payments;

use App\Models\Booking;

/**
 * Customer payable totals for checkout and payment (promo affects OTA payable only).
 */
class BookingPayableResolver
{
    public static function fareTotal(Booking $booking): float
    {
        $booking->loadMissing('fareBreakdown');

        return round((float) ($booking->fareBreakdown?->total ?? 0), 2);
    }

    public static function promoDiscount(Booking $booking): float
    {
        return round((float) ($booking->promo_discount_amount ?? 0), 2);
    }

    public static function hasPromoApplied(Booking $booking): bool
    {
        return filled($booking->promo_code) && self::promoDiscount($booking) > 0;
    }

    public static function payableBeforePromo(Booking $booking): float
    {
        if ($booking->payable_before_promo !== null) {
            return round((float) $booking->payable_before_promo, 2);
        }

        return self::fareTotal($booking);
    }

    public static function customerPayableTotal(Booking $booking): float
    {
        if ($booking->payable_after_promo !== null) {
            return round((float) $booking->payable_after_promo, 2);
        }

        return self::fareTotal($booking);
    }

    public static function verifiedPaidAmount(Booking $booking): float
    {
        if ($booking->relationLoaded('payments')) {
            return round((float) $booking->payments
                ->where('status.value', 'verified')
                ->sum('amount'), 2);
        }

        return round((float) $booking->verifiedPayments()->sum('amount'), 2);
    }

    public static function balanceDue(Booking $booking): float
    {
        if ($booking->balance_due !== null) {
            return max(0, round((float) $booking->balance_due, 2));
        }

        $payable = self::customerPayableTotal($booking);
        $paid = self::verifiedPaidAmount($booking);

        return max(0, round($payable - $paid, 2));
    }

    public static function allowsZeroPayableCheckout(Booking $booking): bool
    {
        if (! config('ota.promo.allow_zero_payable', false)) {
            return false;
        }

        return (bool) ($booking->promoCode?->internal_testing_only ?? false);
    }
}
