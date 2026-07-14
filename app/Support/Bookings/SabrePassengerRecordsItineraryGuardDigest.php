<?php

namespace App\Support\Bookings;

use App\Models\Booking;

/**
 * B64: Display-safe digest for B63 Passenger Records pre-live itinerary guard (no PII, no raw Sabre payload).
 */
final class SabrePassengerRecordsItineraryGuardDigest
{
    public const ERROR_CODE = 'sabre_passenger_records_itinerary_guard';

    /**
     * @return array<string, string>|null Labeled strings for admin/staff UI only.
     */
    public static function fromBooking(Booking $booking): ?array
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $outcome = $meta['sabre_checkout_outcome'] ?? null;
        if (! is_array($outcome) || (string) ($outcome['error_code'] ?? '') !== self::ERROR_CODE) {
            return null;
        }

        $latestAttempt = $booking->relationLoaded('supplierBookingAttempts')
            ? $booking->supplierBookingAttempts->sortByDesc('id')->first()
            : null;
        $attemptSafe = is_array($latestAttempt?->safe_summary) ? $latestAttempt->safe_summary : [];

        $guardTrigger = trim((string) ($outcome['guard_trigger'] ?? $attemptSafe['guard_trigger'] ?? ''));
        $segmentCount = (int) ($outcome['segment_count'] ?? $attemptSafe['segment_count'] ?? 0);
        $orderCorrected = ($outcome['segment_order_corrected'] ?? $attemptSafe['segment_order_corrected'] ?? false) === true;
        $liveAttempted = ($outcome['live_call_attempted'] ?? $attemptSafe['live_call_attempted'] ?? false) === true;

        return [
            'headline' => 'Manual Review Required',
            'reason' => 'Passenger Records risky itinerary guard',
            'guard_trigger' => $guardTrigger !== '' ? $guardTrigger : '—',
            'segment_count' => $segmentCount > 0 ? (string) $segmentCount : '—',
            'segment_order_corrected' => $orderCorrected ? 'Yes' : 'No',
            'live_call_attempted' => $liveAttempted ? 'Yes' : 'No',
            'pnr' => filled($booking->pnr) ? 'Created' : 'Not created',
            'ticketing' => 'Disabled / pending manual',
            'suggested_action' => 'Create/check booking manually in Sabre or use alternate supplier flow.',
        ];
    }
}
