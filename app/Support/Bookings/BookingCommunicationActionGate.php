<?php

namespace App\Support\Bookings;

use App\Enums\BookingDocumentType;
use App\Models\Booking;

/**
 * Determines which manual communication actions are enabled for a booking (parity with admin Blade UI).
 */
final class BookingCommunicationActionGate
{
    /** @var list<string> */
    public const ACTIONS = [
        'booking_update',
        'payment_reminder',
        'invoice',
        'receipt',
        'ticket_itinerary',
        'cancellation_update',
        'refund_update',
    ];

    public static function isEnabled(Booking $booking, string $action): bool
    {
        $hasContact = $booking->contact !== null
            && (filled($booking->contact->email) || filled($booking->contact->phone));
        $hasUnpaidBalance = in_array((string) ($booking->payment_status ?? 'unpaid'), ['unpaid', 'partial'], true)
            || (float) ($booking->balance_due ?? 0) > 0;
        $hasInvoice = $booking->documents->contains(fn ($doc) => $doc->document_type === BookingDocumentType::Invoice);
        $hasReceipt = $booking->documents->contains(fn ($doc) => $doc->document_type === BookingDocumentType::PaymentReceipt);
        $hasItinerary = $booking->documents->contains(fn ($doc) => $doc->document_type === BookingDocumentType::TicketItinerary)
            || $booking->tickets->isNotEmpty();
        $hasCancellation = $booking->documents->contains(fn ($doc) => $doc->document_type === BookingDocumentType::CancellationConfirmation)
            || $booking->cancellationRequests->isNotEmpty();
        $hasRefund = $booking->documents->contains(fn ($doc) => $doc->document_type === BookingDocumentType::RefundNote)
            || $booking->refunds->isNotEmpty();

        return match ($action) {
            'booking_update' => $hasContact,
            'payment_reminder' => $hasContact && $hasUnpaidBalance,
            'invoice' => $hasContact && $hasInvoice,
            'receipt' => $hasContact && $hasReceipt,
            'ticket_itinerary' => $hasContact && $hasItinerary,
            'cancellation_update' => $hasContact && $hasCancellation,
            'refund_update' => $hasContact && $hasRefund,
            default => false,
        };
    }

    /**
     * @return array<string, array{enabled: bool, reason: string|null}>
     */
    public static function presentActionMap(Booking $booking): array
    {
        $map = [];
        foreach (self::ACTIONS as $action) {
            $enabled = self::isEnabled($booking, $action);
            $map[$action] = [
                'enabled' => $enabled,
                'reason' => $enabled ? null : 'not_available_for_booking_state',
            ];
        }

        return $map;
    }
}
