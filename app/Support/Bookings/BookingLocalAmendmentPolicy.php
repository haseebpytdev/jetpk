<?php

namespace App\Support\Bookings;

use App\Models\Booking;

/**
 * Local-only booking amendment eligibility (no supplier writes).
 */
final class BookingLocalAmendmentPolicy
{
    /**
     * @return array{
     *     canEditContact: bool,
     *     canEditPassengers: bool,
     *     contactPolicy: string,
     *     passengerPolicy: string,
     *     hasSupplierPnr: bool
     * }
     */
    public static function evaluate(Booking $booking): array
    {
        $rawStatus = $booking->status;
        if (is_object($rawStatus) && property_exists($rawStatus, 'value')) {
            $status = strtolower(trim((string) $rawStatus->value));
        } elseif ($rawStatus instanceof \BackedEnum) {
            $status = strtolower(trim((string) $rawStatus->value));
        } else {
            $status = strtolower(trim((string) ($rawStatus ?? '')));
        }

        $cancelledOrFailed = in_array($status, ['cancelled', 'canceled', 'failed', 'void'], true);
        $pnr = trim((string) ($booking->pnr ?? ''));
        $supplierRef = trim((string) ($booking->supplier_reference ?? data_get($booking->meta, 'supplier_pnr', '')));
        $hasSupplierPnr = $pnr !== '' || $supplierRef !== '';

        $rawTicketing = $booking->ticketing_status ?? '';
        if (is_object($rawTicketing) && property_exists($rawTicketing, 'value')) {
            $ticketingStatus = strtolower(trim((string) $rawTicketing->value));
        } elseif ($rawTicketing instanceof \BackedEnum) {
            $ticketingStatus = strtolower(trim((string) $rawTicketing->value));
        } else {
            $ticketingStatus = strtolower(trim((string) $rawTicketing));
        }
        $ticketed = filled($booking->ticketed_at) || $ticketingStatus === 'ticketed';

        $canEditContact = ! $cancelledOrFailed;
        $canEditPassengers = ! $cancelledOrFailed && ! $hasSupplierPnr && ! $ticketed;

        return [
            'canEditContact' => $canEditContact,
            'canEditPassengers' => $canEditPassengers,
            'contactPolicy' => $canEditContact
                ? ($hasSupplierPnr
                    ? 'Local JetPakistan contact record only — not synced to airline/supplier PNR.'
                    : 'Local contact amendment allowed before/without supplier PNR.')
                : 'Contact amendment is not available for cancelled or failed bookings.',
            'passengerPolicy' => $canEditPassengers
                ? 'Local passenger identity amendment allowed only before supplier PNR/ticketing.'
                : ($hasSupplierPnr || $ticketed
                    ? 'Passenger identity cannot be edited locally after supplier PNR or ticketing (prevents local/supplier divergence).'
                    : 'Passenger amendment is not available for this booking state.'),
            'hasSupplierPnr' => $hasSupplierPnr,
        ];
    }
}
