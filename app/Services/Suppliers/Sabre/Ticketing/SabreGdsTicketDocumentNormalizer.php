<?php

namespace App\Services\Suppliers\Sabre\Ticketing;

/**
 * Normalize getBooking flightTickets[] into safe ticket document metadata.
 */
final class SabreGdsTicketDocumentNormalizer
{
    /**
     * @param  array<string, mixed>  $getBookingJson
     * @return list<array<string, mixed>>
     */
    public function normalizeFromGetBooking(array $getBookingJson): array
    {
        $flightTickets = $getBookingJson['flightTickets'] ?? [];
        if (! is_array($flightTickets) || $flightTickets === []) {
            return [];
        }

        $travelers = is_array($getBookingJson['travelers'] ?? null) ? $getBookingJson['travelers'] : [];
        $travelerIndex = [];
        foreach ($travelers as $traveler) {
            if (! is_array($traveler)) {
                continue;
            }
            $idx = $traveler['travelerIndex'] ?? $traveler['nameAssociationId'] ?? null;
            if ($idx === null) {
                continue;
            }
            $given = trim((string) ($traveler['givenName'] ?? $traveler['firstName'] ?? ''));
            $family = trim((string) ($traveler['surname'] ?? $traveler['lastName'] ?? ''));
            $travelerIndex[(string) $idx] = trim($given.' '.$family);
        }

        $documents = [];
        foreach ($flightTickets as $ticket) {
            if (! is_array($ticket)) {
                continue;
            }
            $number = trim((string) ($ticket['number'] ?? $ticket['ticketNumber'] ?? ''));
            if ($number === '') {
                continue;
            }
            $travelerRef = (string) ($ticket['travelerIndex'] ?? $ticket['nameAssociationId'] ?? '');
            $statusName = trim((string) ($ticket['ticketStatusName'] ?? $ticket['status'] ?? ''));

            $documents[] = array_filter([
                'ticket_number' => $number,
                'ticket_status' => $statusName !== '' ? $statusName : null,
                'traveler_ref' => $travelerRef !== '' ? $travelerRef : null,
                'passenger_label' => $travelerRef !== '' ? ($travelerIndex[$travelerRef] ?? null) : null,
            ], fn ($v) => $v !== null && $v !== '');
        }

        return $documents;
    }
}
