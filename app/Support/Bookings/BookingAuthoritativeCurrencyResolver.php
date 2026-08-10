<?php

namespace App\Support\Bookings;

use App\Models\Booking;

/**
 * Authoritative booking transaction currency from persisted supplier/fare provenance.
 */
final class BookingAuthoritativeCurrencyResolver
{
    /**
     * @return array{currency: ?string, source: ?string}
     */
    public static function resolveWithSource(Booking $booking): array
    {
        $booking->loadMissing('fareBreakdown');

        $candidates = [
            ['source' => 'meta.original_currency', 'value' => data_get($booking->meta, 'original_currency')],
            ['source' => 'meta.offer_currency', 'value' => data_get($booking->meta, 'offer_currency')],
            ['source' => 'fareBreakdown.currency', 'value' => $booking->fareBreakdown?->currency],
            ['source' => 'meta.currency', 'value' => data_get($booking->meta, 'currency')],
            ['source' => 'booking.currency', 'value' => $booking->currency],
        ];

        foreach ($candidates as $candidate) {
            $normalized = self::normalizeIsoCurrency($candidate['value']);
            if ($normalized !== '') {
                return ['currency' => $normalized, 'source' => $candidate['source']];
            }
        }

        return ['currency' => null, 'source' => null];
    }

    public static function resolve(Booking $booking): string
    {
        $resolved = self::resolveWithSource($booking);

        return $resolved['currency'] ?? '';
    }

    public static function normalizeIsoCurrency(mixed $value): string
    {
        $normalized = strtoupper(trim((string) $value));

        if ($normalized === '' || strlen($normalized) !== 3 || ! ctype_alpha($normalized)) {
            return '';
        }

        return $normalized;
    }

  /**
     * Default currency for payment writes when explicit currency is absent.
     */
    public static function resolvePaymentDefault(Booking $booking, mixed $explicitCurrency = null): string
    {
        $explicit = self::normalizeIsoCurrency($explicitCurrency);
        if ($explicit !== '') {
            return $explicit;
        }

        $resolved = self::resolve($booking);

        return $resolved !== '' ? $resolved : 'PKR';
    }
}
