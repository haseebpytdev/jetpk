<?php

namespace App\Support\Dashboard;

use App\Models\Booking;
use App\Support\Bookings\BookingAuthoritativeCurrencyResolver;

/**
 * JetPakistan operational money: prefer booking-time commercial PKR snapshot.
 * Never invent FX. Never treat missing currency as PKR.
 */
final class BookingOperationalMoneyResolver
{
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
    public static function present(Booking $booking): array
    {
        $booking->loadMissing(['fareBreakdown', 'holdSession']);

        $pkrSnapshot = self::pkrSnapshotAmount($booking);
        if ($pkrSnapshot !== null) {
            return DashboardMoneyPresenter::presentMinorUnits(
                (int) round($pkrSnapshot),
                'PKR',
                'operational.converted_total_pkr',
            );
        }

        $fareTotal = (float) ($booking->fareBreakdown?->total ?? 0);
        $paid = (float) ($booking->amount_paid ?? 0);
        $amount = $fareTotal > 0 ? $fareTotal : $paid;
        $resolved = BookingAuthoritativeCurrencyResolver::resolveWithSource($booking);
        $holdCurrency = BookingAuthoritativeCurrencyResolver::normalizeIsoCurrency(
            $booking->holdSession?->validated_total_currency,
        );

        $currency = $resolved['currency'] ?: ($holdCurrency !== '' ? $holdCurrency : null);
        $source = $resolved['source'] ?? ($holdCurrency !== '' ? 'hold.validated_total_currency' : null);

        if ($amount > 0 && $currency) {
            return DashboardMoneyPresenter::presentMinorUnits((int) round($amount), $currency, $source);
        }

        if ($amount > 0) {
            $formatted = DashboardMoneyPresenter::formatDecimalAmount((int) round($amount));

            return [
                'amount' => $formatted,
                'amountMinor' => (int) round($amount),
                'currency' => null,
                'currencyStatus' => DashboardMoneyPresenter::STATUS_UNRESOLVED,
                'currencySource' => null,
                'displayLabel' => $formatted.' (currency not recorded)',
                'currencyLabel' => 'Currency not recorded',
                'needsReview' => true,
            ];
        }

        return DashboardMoneyPresenter::presentMinorUnits(0, $currency, $source);
    }

    public static function pkrSnapshotAmount(Booking $booking): ?float
    {
        $booking->loadMissing('holdSession');

        $candidates = [
            data_get($booking->meta, 'converted_total_pkr'),
            data_get($booking->meta, 'customer_total_pkr'),
            data_get($booking->meta, 'displayed_total_pkr'),
            $booking->holdSession?->converted_total_pkr,
        ];

        foreach ($candidates as $candidate) {
            if ($candidate === null || $candidate === '') {
                continue;
            }
            $amount = (float) $candidate;
            if ($amount > 0) {
                return $amount;
            }
        }

        $fareCurrency = BookingAuthoritativeCurrencyResolver::normalizeIsoCurrency($booking->fareBreakdown?->currency);
        $bookingCurrency = BookingAuthoritativeCurrencyResolver::normalizeIsoCurrency($booking->currency);
        $fareTotal = (float) ($booking->fareBreakdown?->total ?? 0);
        if ($fareTotal > 0 && ($fareCurrency === 'PKR' || ($fareCurrency === '' && $bookingCurrency === 'PKR'))) {
            return $fareTotal;
        }

        return null;
    }
}
