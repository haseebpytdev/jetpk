<?php

namespace App\Support\Bookings;

use App\Models\Booking;

/**
 * Customer-facing booking progress timeline for portal booking detail views.
 */
class BookingDetailTimelinePresenter
{
    /**
     * @return list<array{key: string, label: string, state: string, detail: string|null, at: mixed}>
     */
    public static function forBooking(Booking $booking, array $meta, bool $hasPnr): array
    {
        $paymentCode = PaymentOperationalStatus::fromValue((string) ($booking->payment_status ?? 'unpaid'))['code'];
        $paymentState = match ($paymentCode) {
            'paid' => 'completed',
            'proof_submitted', 'partial' => 'active',
            'rejected' => 'warning',
            default => 'pending',
        };
        $paymentDetail = match ($paymentCode) {
            'paid' => 'Payment verified.',
            'proof_submitted' => 'Payment proof submitted — awaiting verification.',
            'partial' => 'Partial payment verified — balance may remain.',
            'rejected' => 'Payment proof was rejected — please submit again or contact support.',
            default => 'Payment pending.',
        };

        $hasItinerarySync = is_array($meta['pnr_itinerary_snapshot'] ?? null)
            && is_array($meta['pnr_itinerary_snapshot']['segments'] ?? null)
            && $meta['pnr_itinerary_snapshot']['segments'] !== [];

        $itineraryState = $hasItinerarySync ? 'completed' : ($hasPnr ? 'active' : 'pending');
        $itineraryDetail = match (true) {
            $hasItinerarySync => 'Final airline itinerary synced from PNR.',
            $hasPnr => 'PNR created — final airline itinerary not yet synced.',
            default => 'Awaiting airline confirmation (PNR).',
        };

        $ticketing = TicketingOperationalStatus::fromValues(
            (string) ($booking->ticketing_status ?? 'not_started'),
            (string) ($booking->payment_status ?? 'unpaid'),
            $hasPnr,
            $booking->tickets->isNotEmpty(),
            (string) (($meta['supplier_provider'] ?? null) ?: ($booking->supplier ?? '')),
            (string) ($booking->cancellation_status ?? ''),
        );
        $ticketingState = match ($ticketing['code']) {
            'issued', 'voided' => 'completed',
            'pending', 'ready' => 'active',
            'failed' => 'warning',
            default => 'pending',
        };

        return [
            [
                'key' => 'created',
                'label' => 'Booking created',
                'state' => 'completed',
                'detail' => null,
                'at' => $booking->created_at,
            ],
            [
                'key' => 'payment',
                'label' => 'Payment',
                'state' => $paymentState,
                'detail' => $paymentDetail,
                'at' => null,
            ],
            [
                'key' => 'pnr',
                'label' => 'PNR / airline booking',
                'state' => $hasPnr ? 'completed' : 'pending',
                'detail' => $hasPnr ? ('PNR: '.($booking->pnr ?? 'confirmed')) : 'Not yet confirmed with the airline.',
                'at' => null,
            ],
            [
                'key' => 'itinerary',
                'label' => 'Itinerary sync',
                'state' => $itineraryState,
                'detail' => $itineraryDetail,
                'at' => null,
            ],
            [
                'key' => 'ticketing',
                'label' => 'Ticketing',
                'state' => $ticketingState,
                'detail' => $ticketing['meaning'],
                'at' => null,
            ],
        ];
    }
}
