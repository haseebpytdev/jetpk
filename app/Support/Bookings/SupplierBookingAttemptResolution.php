<?php

namespace App\Support\Bookings;

use App\Models\SupplierBookingAttempt;
use Illuminate\Support\Collection;

/**
 * E3C: Skip blocked {@code supplier_booking_retry_not_allowed} wrapper attempts when
 * resolving the latest meaningful Passenger Records create attempt.
 */
final class SupplierBookingAttemptResolution
{
    public static function isRetryBlockedWrapperAttempt(?SupplierBookingAttempt $attempt): bool
    {
        if ($attempt === null) {
            return false;
        }

        if (strtolower((string) $attempt->status) !== 'blocked') {
            return false;
        }

        return strtolower(trim((string) $attempt->error_code)) === 'supplier_booking_retry_not_allowed';
    }

    /**
     * @param  Collection<int, SupplierBookingAttempt>|iterable<SupplierBookingAttempt>  $attempts
     */
    public static function resolveLatestMeaningfulCreateAttempt(iterable $attempts): ?SupplierBookingAttempt
    {
        $createAttempts = collect($attempts)
            ->filter(fn (SupplierBookingAttempt $attempt): bool => strtolower((string) $attempt->action) === 'create_pnr')
            ->sortByDesc(fn (SupplierBookingAttempt $attempt): int => (int) $attempt->id)
            ->values();

        foreach ($createAttempts as $attempt) {
            if (! self::isRetryBlockedWrapperAttempt($attempt)) {
                return $attempt;
            }
        }

        return null;
    }
}
