<?php

namespace App\Support\Dashboard;

use App\Models\Booking;

/**
 * Authoritative booking currency resolution for dashboard API payloads.
 */
final class DashboardMoneyPresenter
{
    public static function resolveBookingCurrency(Booking $booking): string
    {
        $booking->loadMissing('fareBreakdown');

        foreach ([
            $booking->currency,
            $booking->fareBreakdown?->currency,
            data_get($booking->meta, 'currency'),
            data_get($booking->meta, 'offer_currency'),
            data_get($booking->meta, 'original_currency'),
        ] as $candidate) {
            $normalized = self::normalizeIsoCurrency($candidate);
            if ($normalized !== '') {
                return $normalized;
            }
        }

        return '';
    }

    public static function normalizeIsoCurrency(mixed $value): string
    {
        $normalized = strtoupper(trim((string) $value));

        if ($normalized === '' || strlen($normalized) !== 3 || ! ctype_alpha($normalized)) {
            return '';
        }

        return $normalized;
    }

    public static function formatAmountLabel(float|int $amount, string $currency): string
    {
        $formatted = number_format((float) $amount, 2, '.', ',');

        if ($currency === '') {
            return $formatted;
        }

        return $formatted.' '.$currency;
    }
}
