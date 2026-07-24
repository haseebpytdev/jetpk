<?php

namespace App\Support\Sabre\Scenario;

use App\Models\Booking;
use App\Models\CommunicationLog;
use App\Models\SupplierBooking;
use App\Models\SupplierBookingAttempt;

/**
 * Captures and verifies zero database mutation for revalidation-only probes.
 */
final class SabreGdsRevalidationProbeDbSnapshot
{
    /**
     * @return array<string, int>
     */
    public function capture(): array
    {
        return [
            'bookings_count' => Booking::query()->count(),
            'bookings_max_id' => (int) (Booking::query()->max('id') ?? 0),
            'supplier_bookings_count' => SupplierBooking::query()->count(),
            'supplier_bookings_max_id' => (int) (SupplierBooking::query()->max('id') ?? 0),
            'supplier_booking_attempts_count' => SupplierBookingAttempt::query()->count(),
            'supplier_booking_attempts_max_id' => (int) (SupplierBookingAttempt::query()->max('id') ?? 0),
            'communication_logs_count' => CommunicationLog::query()->count(),
        ];
    }

    /**
     * @param  array<string, int>  $before
     * @param  array<string, int>  $after
     */
    public function assertUnchanged(array $before, array $after): ?string
    {
        foreach ($before as $key => $value) {
            if (($after[$key] ?? null) !== $value) {
                return $key;
            }
        }

        return null;
    }
}
