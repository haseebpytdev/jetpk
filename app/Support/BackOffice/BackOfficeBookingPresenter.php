<?php

namespace App\Support\BackOffice;

use App\Models\Booking;

final class BackOfficeBookingPresenter
{
    /**
     * Safe booking snapshot for execution mutation JSON responses.
     *
     * @return array<string, mixed>
     */
    public static function present(Booking $booking): array
    {
        return [
            'id' => (string) $booking->id,
            'status' => $booking->status->value,
            'payment_status' => $booking->payment_status,
            'ticketing_status' => $booking->ticketing_status,
            'cancellation_status' => $booking->cancellation_status,
            'refund_status' => $booking->refund_status,
            'supplier_booking_status' => $booking->supplier_booking_status,
            'pnr' => $booking->pnr,
            'supplier_reference' => $booking->supplier_reference,
        ];
    }
}
