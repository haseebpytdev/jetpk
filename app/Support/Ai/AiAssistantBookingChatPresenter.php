<?php

namespace App\Support\Ai;

use App\Enums\BookingStatus;
use App\Models\Booking;

/**
 * Customer-safe booking summary for Ask JetPakistan in-chat lookup.
 * Never exposes unrelated PII or internal fields.
 */
final class AiAssistantBookingChatPresenter
{
    /**
     * @return array<string, mixed>
     */
    public function present(Booking $booking): array
    {
        $booking->loadMissing(['passengers', 'contact', 'tickets', 'supplierBookings']);
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $criteria = is_array($meta['search_criteria'] ?? null) ? $meta['search_criteria'] : [];
        $offer = is_array($meta['flight_offer_snapshot'] ?? null) ? $meta['flight_offer_snapshot'] : [];

        $origin = (string) ($criteria['origin'] ?? $offer['origin'] ?? '');
        $destination = (string) ($criteria['destination'] ?? $offer['destination'] ?? '');
        $depart = (string) ($criteria['depart_date'] ?? $booking->travel_date?->format('Y-m-d') ?? '');
        $return = (string) ($criteria['return_date'] ?? '');
        $airline = (string) ($offer['validating_carrier_name'] ?? $offer['airline_name'] ?? '');

        $ticketed = $booking->tickets->isNotEmpty()
            || $booking->supplierBookings->contains(fn ($sb) => filled($sb->pnr));

        return [
            'booking_reference' => $booking->booking_reference,
            'status' => $booking->status instanceof BookingStatus ? $booking->status->value : (string) $booking->status,
            'status_label' => $this->statusLabel($booking),
            'route' => trim($origin.' → '.$destination, ' →'),
            'origin' => $origin,
            'destination' => $destination,
            'depart_date' => $depart !== '' ? $depart : null,
            'return_date' => $return !== '' ? $return : null,
            'airline' => $airline !== '' ? $airline : null,
            'ticketed' => $ticketed,
            'trip_type' => (string) ($criteria['trip_type'] ?? ($return !== '' ? 'round_trip' : 'one_way')),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function summarizeForChat(array $data): string
    {
        $ref = (string) ($data['booking_reference'] ?? 'your booking');
        $status = (string) ($data['status_label'] ?? $data['status'] ?? 'unknown');
        $route = (string) ($data['route'] ?? '');
        $depart = (string) ($data['depart_date'] ?? '');
        $return = (string) ($data['return_date'] ?? '');
        $airline = (string) ($data['airline'] ?? '');
        $ticketed = (bool) ($data['ticketed'] ?? false);

        $lines = ["I found booking **{$ref}**."];
        $lines[] = 'Status: '.$status.'.';
        if ($route !== '' && $route !== '→') {
            $lines[] = 'Route: '.$route.'.';
        }
        if ($depart !== '') {
            $lines[] = 'Departure: '.$depart.'.';
        }
        if ($return !== '') {
            $lines[] = 'Return: '.$return.'.';
        }
        if ($airline !== '') {
            $lines[] = 'Airline: '.$airline.'.';
        }
        $lines[] = $ticketed
            ? 'This booking appears ticketed/confirmed on our side.'
            : 'Ticketing is not confirmed yet — check payment/status in Lookup Booking.';

        return implode("\n", $lines);
    }

    private function statusLabel(Booking $booking): string
    {
        if ($booking->status instanceof BookingStatus) {
            return match ($booking->status) {
                BookingStatus::PaymentPending => 'Pending payment',
                BookingStatus::Confirmed, BookingStatus::Paid, BookingStatus::Ticketed => 'Confirmed',
                BookingStatus::TicketingPending => 'Ticketing in progress',
                BookingStatus::Cancelled => 'Cancelled',
                BookingStatus::Expired => 'Expired',
                BookingStatus::Refunded => 'Refunded',
                default => $booking->status->value,
            };
        }

        return (string) $booking->status;
    }
}
