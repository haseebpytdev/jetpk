<?php

namespace App\Services\Suppliers\OneApi\Reservation;

use App\Services\Suppliers\OneApi\Support\OneApiBookResponseInterpreter;

/**
 * Parses OTA_ReadRS into safe reservation read fields.
 */
class OneApiReadResponseParser
{
    /**
     * @param  array<string, mixed>  $parsedTransport
     * @return array<string, mixed>
     */
    public function parse(array $parsedTransport): array
    {
        $xml = (string) ($parsedTransport['raw_xml'] ?? '');
        if ($xml === '') {
            return [
                'found' => false,
                'malformed' => true,
            ];
        }

        if (str_contains($xml, 'Errors') && ! str_contains($xml, 'OTA_ReadRS')) {
            return [
                'found' => false,
                'malformed' => true,
            ];
        }

        if (! str_contains($xml, 'OTA_ReadRS')) {
            return [
                'found' => false,
                'missing_reservation' => true,
            ];
        }

        $interpreted = OneApiBookResponseInterpreter::fromParsed($parsedTransport);

        return [
            'found' => true,
            'pnr' => $interpreted->pnr,
            'transaction_identifier' => $interpreted->transactionIdentifier,
            'ticketing_status' => $interpreted->ticketingStatus,
            'is_ticketed' => $interpreted->isTicketed,
            'is_on_hold' => $interpreted->isOnHold,
            'payment_amount' => $this->matchAmount($xml),
            'payment_currency' => $this->matchCurrency($xml),
            'travelers' => $this->parseTravelers($xml),
            'itinerary_segments' => $this->parseItinerary($xml),
            'ticket_numbers' => $this->parseTicketNumbers($xml),
        ];
    }

    private function matchAmount(string $xml): ?string
    {
        if (preg_match('/PaymentAmount[^>]*Amount="([^"]+)"/', $xml, $m)) {
            return $m[1];
        }

        return null;
    }

    private function matchCurrency(string $xml): ?string
    {
        if (preg_match('/PaymentAmount[^>]*CurrencyCode="([^"]+)"/', $xml, $m)) {
            return $m[1];
        }

        return null;
    }

    /**
     * @return list<array<string, string>>
     */
    private function parseTravelers(string $xml): array
    {
        if (! str_contains($xml, 'TravelerInfo') && ! str_contains($xml, 'AirTraveler')) {
            return [];
        }

        return [['parsed' => 'traveler_present']];
    }

    /**
     * @return list<array<string, string>>
     */
    private function parseItinerary(string $xml): array
    {
        if (! str_contains($xml, 'FlightSegment') && ! str_contains($xml, 'AirItinerary')) {
            return [];
        }

        return [['parsed' => 'itinerary_present']];
    }

    /**
     * @return list<string>
     */
    private function parseTicketNumbers(string $xml): array
    {
        preg_match_all('/TicketNumber="([^"]+)"/', $xml, $matches);

        return array_values(array_filter($matches[1] ?? []));
    }
}
