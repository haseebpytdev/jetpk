<?php

namespace App\Support\Dashboard;

use App\Models\Booking;

/**
 * Authoritative money presentation for dashboard API payloads.
 */
final class DashboardMoneyPresenter
{
    public const STATUS_RESOLVED = 'resolved';

    public const STATUS_UNRESOLVED = 'unresolved';

    /**
     * @return array{currency: ?string, source: ?string}
     */
    public static function resolveBookingCurrencyWithSource(Booking $booking): array
    {
        $booking->loadMissing('fareBreakdown');

        $candidates = [
            ['source' => 'booking.currency', 'value' => $booking->currency],
            ['source' => 'fareBreakdown.currency', 'value' => $booking->fareBreakdown?->currency],
            ['source' => 'meta.currency', 'value' => data_get($booking->meta, 'currency')],
            ['source' => 'meta.offer_currency', 'value' => data_get($booking->meta, 'offer_currency')],
            ['source' => 'meta.original_currency', 'value' => data_get($booking->meta, 'original_currency')],
        ];

        foreach ($candidates as $candidate) {
            $normalized = self::normalizeIsoCurrency($candidate['value']);
            if ($normalized !== '') {
                return ['currency' => $normalized, 'source' => $candidate['source']];
            }
        }

        return ['currency' => null, 'source' => null];
    }

    public static function resolveBookingCurrency(Booking $booking): string
    {
        $resolved = self::resolveBookingCurrencyWithSource($booking);

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
     * @return array{
     *     amount: string,
     *     amountMinor: int,
     *     currency: ?string,
     *     currencyStatus: string,
     *     currencySource: ?string,
     *     displayLabel: string,
     *     currencyLabel: ?string,
     *     needsReview: bool
     * }
     */
    public static function presentMinorUnits(
        int $amountMinor,
        ?string $currency = null,
        ?string $currencySource = null,
    ): array {
        $normalized = self::normalizeIsoCurrency($currency);
        $resolved = $normalized !== '';

        if ($resolved) {
            $formatted = self::formatDecimalAmount($amountMinor);

            return [
                'amount' => $formatted,
                'amountMinor' => $amountMinor,
                'currency' => $normalized,
                'currencyStatus' => self::STATUS_RESOLVED,
                'currencySource' => $currencySource,
                'displayLabel' => $formatted.' '.$normalized,
                'currencyLabel' => null,
                'needsReview' => false,
            ];
        }

        return [
            'amount' => self::formatDecimalAmount($amountMinor),
            'amountMinor' => $amountMinor,
            'currency' => null,
            'currencyStatus' => self::STATUS_UNRESOLVED,
            'currencySource' => null,
            'displayLabel' => 'Amount unavailable',
            'currencyLabel' => 'Currency not recorded',
            'needsReview' => true,
        ];
    }

  /**
     * @return array{
     *     amount: string,
     *     amountMinor: int,
     *     currency: ?string,
     *     currencyStatus: string,
     *     currencySource: ?string,
     *     displayLabel: string,
     *     currencyLabel: ?string,
     *     needsReview: bool
     * }
     */
    public static function presentBookingTotal(Booking $booking, int $amountMinor): array
    {
        $resolved = self::resolveBookingCurrencyWithSource($booking);

        return self::presentMinorUnits($amountMinor, $resolved['currency'], $resolved['source']);
    }

    /**
     * @deprecated Use presentMinorUnits(); never returns bare financial amounts.
     */
    public static function formatAmountLabel(float|int $amount, string $currency): string
    {
        $presented = self::presentMinorUnits((int) round((float) $amount), $currency);

        return $presented['displayLabel'];
    }

    public static function formatDecimalAmount(int|float $amount): string
    {
        return number_format((float) $amount, 2, '.', ',');
    }
}
