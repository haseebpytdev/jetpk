<?php

namespace App\Support\Bookings;

/**
 * Booking-time commercial PKR only. Never copy a foreign-currency total into PKR.
 */
final class BookingPkrSnapshot
{
    /**
     * @param  array<string, mixed>  $offer
     */
    public static function fromOffer(array $offer): ?float
    {
        $currency = BookingAuthoritativeCurrencyResolver::normalizeIsoCurrency(
            $offer['currency'] ?? $offer['supplier_currency'] ?? null
        );
        $total = (float) ($offer['total'] ?? $offer['supplier_total'] ?? 0);
        if ($currency === 'PKR' && $total > 0) {
            return $total;
        }

        foreach (['converted_total_pkr', 'customer_total_pkr', 'displayed_total_pkr'] as $key) {
            $candidate = data_get($offer, $key);
            if ($candidate === null || $candidate === '') {
                continue;
            }
            $amount = (float) $candidate;
            if ($amount > 0) {
                return $amount;
            }
        }

        return null;
    }
}
