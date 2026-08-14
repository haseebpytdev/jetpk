<?php

namespace App\Support\Dashboard;

use App\Models\Booking;
use App\Support\Bookings\BookingAuthoritativeCurrencyResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

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

        $fareTotal = (float) ($booking->fareBreakdown?->total ?? 0);
        $paid = (float) ($booking->amount_paid ?? 0);
        $amount = $fareTotal > 0 ? $fareTotal : $paid;
        $resolved = BookingAuthoritativeCurrencyResolver::resolveWithSource($booking);
        $holdCurrency = BookingAuthoritativeCurrencyResolver::normalizeIsoCurrency(
            $booking->holdSession?->validated_total_currency,
        );
        $original = BookingAuthoritativeCurrencyResolver::normalizeIsoCurrency(
            data_get($booking->meta, 'original_currency')
        );

        $currency = $resolved['currency']
            ?: ($original !== '' ? $original : null)
            ?: ($holdCurrency !== '' ? $holdCurrency : null);
        $source = $resolved['source'] ?? ($original !== '' ? 'meta.original_currency' : ($holdCurrency !== '' ? 'hold.validated_total_currency' : null));

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
        $booking->loadMissing(['holdSession', 'fareBreakdown']);

        $candidates = [
            data_get($booking->meta, 'converted_total_pkr'),
            data_get($booking->meta, 'customer_total_pkr'),
            data_get($booking->meta, 'displayed_total_pkr'),
            data_get($booking->meta, 'commercial_money.customer_total_pkr'),
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

    /**
     * Admin business display: authoritative booking-time PKR snapshot when available.
     *
     * @return array{
     *     amount: string,
     *     amountMinor: int,
     *     currency: ?string,
     *     currencyStatus: string,
     *     currencySource: ?string,
     *     displayLabel: string,
     *     currencyLabel: ?string,
     *     needsReview: bool,
     *     originalCurrency: ?string,
     *     originalAmountLabel: ?string
     * }
     */
    public static function presentAdminBusinessAmount(Booking $booking): array
    {
        $snapshot = self::pkrSnapshotAmount($booking);
        if ($snapshot !== null && $snapshot > 0) {
            $presented = DashboardMoneyPresenter::presentMinorUnits(
                (int) round($snapshot),
                'PKR',
                'booking.pkr_snapshot',
            );
            $originalCurrency = BookingAuthoritativeCurrencyResolver::normalizeIsoCurrency(
                data_get($booking->meta, 'original_currency')
                    ?: data_get($booking->meta, 'commercial_money.original_supplier_currency')
            );
            $originalAmount = (float) (
                data_get($booking->meta, 'commercial_money.original_supplier_amount')
                ?: ($booking->fareBreakdown?->total ?? 0)
            );
            $presented['originalCurrency'] = $originalCurrency !== '' ? $originalCurrency : null;
            $presented['originalAmountLabel'] = $presented['originalCurrency'] && $originalAmount > 0
                && $presented['originalCurrency'] !== 'PKR'
                ? DashboardMoneyPresenter::formatDisplayLabel($originalAmount, $presented['originalCurrency'])
                : null;

            return $presented;
        }

        $operational = self::present($booking);
        $operational['originalCurrency'] = $operational['currency'];
        $operational['originalAmountLabel'] = $operational['currencyStatus'] === DashboardMoneyPresenter::STATUS_RESOLVED
            ? $operational['displayLabel']
            : null;

        if (($operational['currency'] ?? '') === 'PKR') {
            return $operational;
        }

        if ($operational['currencyStatus'] === DashboardMoneyPresenter::STATUS_RESOLVED) {
            $operational['needsReview'] = true;
            $operational['currencyLabel'] = 'PKR snapshot unavailable';
        }

        return $operational;
    }

    public static function sumAdminPkrForQuery(Builder $baseQuery): float
    {
        $pkrTotal = 0.0;
        (clone $baseQuery)
            ->with(['fareBreakdown', 'holdSession'])
            ->orderBy('bookings.id')
            ->chunkById(200, function (Collection $bookings) use (&$pkrTotal): void {
                foreach ($bookings as $booking) {
                    if (! $booking instanceof Booking) {
                        continue;
                    }
                    $snapshot = self::pkrSnapshotAmount($booking);
                    if ($snapshot !== null) {
                        $pkrTotal += $snapshot;
                    }
                }
            }, 'bookings.id', 'id');

        return $pkrTotal;
    }
}
