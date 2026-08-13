<?php

namespace App\Support\Dashboard;

use App\Models\Booking;
use App\Support\Bookings\BookingAuthoritativeCurrencyResolver;

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
        return BookingAuthoritativeCurrencyResolver::resolveWithSource($booking);
    }

    public static function resolveBookingCurrency(Booking $booking): string
    {
        return BookingAuthoritativeCurrencyResolver::resolve($booking);
    }

    public static function normalizeIsoCurrency(mixed $value): string
    {
        return BookingAuthoritativeCurrencyResolver::normalizeIsoCurrency($value);
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
                'displayLabel' => self::formatDisplayLabel($amountMinor, $normalized),
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
        $operational = BookingOperationalMoneyResolver::present($booking);
        $booking->loadMissing('fareBreakdown');
        $fareCurrency = self::normalizeIsoCurrency($booking->fareBreakdown?->currency);
        $bookingCurrency = self::normalizeIsoCurrency($booking->currency);
        if ($fareCurrency !== '' && $bookingCurrency !== '' && $fareCurrency !== $bookingCurrency) {
            $operational['needsReview'] = true;
        }

        if ($operational['currencyStatus'] === self::STATUS_RESOLVED || $amountMinor <= 0) {
            return $operational;
        }

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

    /**
     * JetPakistan operational money label.
     * PKR → Rs. XX,XXX.XX; other ISO → "{ISO} XX,XXX.XX" (never relabeled as PKR).
     */
    public static function formatDisplayLabel(int|float $amount, string $currency): string
    {
        $normalized = self::normalizeIsoCurrency($currency);
        if ($normalized === '') {
            return 'Amount unavailable';
        }

        $formatted = self::formatDecimalAmount($amount);

        return $normalized === 'PKR'
            ? 'Rs. '.$formatted
            : $normalized.' '.$formatted;
    }
}
