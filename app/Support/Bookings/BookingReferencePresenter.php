<?php

namespace App\Support\Bookings;

use App\Models\Booking;

/**
 * Portal-safe booking reference: returns stored booking_reference unchanged (no display prefixes).
 */
final class BookingReferencePresenter
{
    public static function forPortal(Booking $booking): string
    {
        $raw = trim((string) ($booking->booking_reference ?? ''));

        return $raw !== '' ? $raw : ('#'.$booking->id);
    }
}
