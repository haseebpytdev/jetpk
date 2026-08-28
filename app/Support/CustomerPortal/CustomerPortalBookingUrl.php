<?php

namespace App\Support\CustomerPortal;

use App\Models\Booking;

/**
 * Customer portal booking URL helpers (Draft rows may lack booking_reference).
 */
final class CustomerPortalBookingUrl
{
    public static function detailPath(Booking $booking): string
    {
        $ref = trim((string) ($booking->booking_reference ?? ''));
        if ($ref !== '') {
            return '/customer/bookings/'.$ref;
        }

        return '/customer/bookings/'.$booking->getKey();
    }
}
