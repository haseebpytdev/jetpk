<?php

namespace App\Services\Suppliers\AirBlue;

use App\Data\BaggageAllowanceData;
use App\Data\FareBreakdownData;
use App\Data\NormalizedFlightOfferData;
use App\Enums\AirBlueApiChannel;
use App\Enums\SupplierProvider;
use App\Models\SupplierConnection;

/**
 * Normalizes Zapways OTA parse output into OTA internal DTOs for AirBlue.
 */
class AirBlueOtaResponseNormalizer
{
    /**
     * @param  array<string, mixed>  $parsedResponse
     * @return list<NormalizedFlightOfferData>
     */
    public function normalizeSearchResponse(
        array $parsedResponse,
        SupplierConnection $connection,
        string $correlationId,
    ): array {
        $parsed = is_array($parsedResponse['parsed'] ?? null) ? $parsedResponse['parsed'] : [];
        $itineraries = is_array($parsed['priced_itineraries'] ?? null) ? $parsed['priced_itineraries'] : [];
        $grouped = $this->groupItinerariesByTrip($itineraries);
        $offers = [];

        foreach ($grouped as $group) {
            $offer = $this->buildOfferFromItineraries($group, $connection, $correlationId);
            if ($offer !== null) {
                $offers[] = $offer;
            }
            if (count($offers) >= 150) {
                break;
            }
        }

        return $offers;
    }

    /**
     * @param  array<string, mixed>  $parsedResponse
     * @param  array<string, mixed>  $existingContext
     * @return array<string, mixed>
     */
    public function normalizeBookingResponse(array $parsedResponse, array $existingContext = []): array
    {
        $parsed = is_array($parsedResponse['parsed'] ?? null) ? $parsedResponse['parsed'] : [];
        $booking = is_array($parsed['booking'] ?? null) ? $parsed['booking'] : [];
        $pnr = trim((string) ($booking['pnr'] ?? $parsed['pnr'] ?? ''));

        return [
            'provider_booking_reference' => $pnr,
            'pnr' => $pnr,
            'status' => 'option',
            'ticketing_status' => 'pending_ticketing',
            'last_ticketing_date' => null,
            'provider_context' => array_merge($existingContext, [
                'api_channel' => AirBlueApiChannel::ZapwaysOta->value,
                'pnr' => $pnr,
                'instance' => trim((string) ($booking['instance'] ?? $parsed['instance'] ?? '')),
            ]),
            'supplier_messages' => [],
        ];
    }

    /**
     * @param  array<string, mixed>  $parsedResponse
     * @param  array<string, mixed>  $existing
     * @return array<string, mixed>
     */
    public function normalizeRetrieveResponse(array $parsedResponse, array $existing = []): array
    {
        $parsed = is_array($parsedResponse['parsed'] ?? null) ? $parsedResponse['parsed'] : [];
        $booking = is_array($parsed['booking'] ?? null) ? $parsed['booking'] : [];
        $tickets = is_array($parsed['tickets'] ?? null) ? $parsed['tickets'] : [];
        $ticketNumbers = [];
        foreach ($tickets as $ticket) {
            if (is_array($ticket) && ($ticket['ticket_number'] ?? '') !== '') {
                $ticketNumbers[] = (string) $ticket['ticket_number'];
            }
        }

        return array_filter([
            'api_channel' => AirBlueApiChannel::ZapwaysOta->value,
            'pnr' => trim((string) ($booking['pnr'] ?? $parsed['pnr'] ?? $existing['pnr'] ?? '')),
            'instance' => trim((string) ($booking['instance'] ?? $parsed['instance'] ?? $existing['instance'] ?? '')),
            'ticketing_status' => $ticketNumbers !== [] ? 'ticketed' : ($existing['ticketing_status'] ?? 'pending_ticketing'),
            'ticket_numbers' => $ticketNumbers !== [] ? $ticketNumbers : null,
        ], fn ($v) => $v !== null && $v !== '');
    }

    /**
     * @param  array<string, mixed>  $parsedResponse
     * @param  array<string, mixed>  $existing
     * @return array<string, mixed>
     */
    public function normalizeTicketingResponse(array $parsedResponse, array $existing = []): array
    {
        $parsed = is_array($parsedResponse['parsed'] ?? null) ? $parsedResponse['parsed'] : [];
        $tickets = is_array($parsed['tickets'] ?? null) ? $parsed['tickets'] : [];
        $ticketNumbers = [];
        foreach ($tickets as $ticket) {
            if (is_array($ticket) && ($ticket['ticket_number'] ?? '') !== '') {
                $ticketNumbers[] = (string) $ticket['ticket_number'];
            }
        }

        return [
            'ticketing_status' => $ticketNumbers !== [] ? 'ticketed' : 'failed',
            'ticket_numbers' => $ticketNumbers,
            'provider_context' => array_merge($existing, [
                'api_channel' => AirBlueApiChannel::ZapwaysOta->value,
                'ticketing_status' => $ticketNumbers !== [] ? 'ticketed' : ($existing['ticketing_status'] ?? 'pending_ticketing'),
                'ticket_numbers' => $ticketNumbers,
            ]),
        ];
    }

    /**
     * @param  array<string, mixed>  $parsedResponse
     * @return array<string, mixed>
     */
    public function normalizeCancelResponse(array $parsedResponse): array
    {
        unset($parsedResponse);

        return [
            'cancellation_status' => 'cancelled',
            'api_channel' => AirBlueApiChannel::ZapwaysOta->value,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $itineraries
     * @return list<list<array<string, mixed>>>
     */
    private function groupItinerariesByTrip(array $itineraries): array
    {
        $outbound = [];
        $inbound = [];
        foreach ($itineraries as $itinerary) {
            if (! is_array($itinerary)) {
                continue;
            }
            $ref = (string) ($itinerary['origin_destination_ref'] ?? '1');
            if ($ref === '2') {
                $inbound[] = $itinerary;
            } else {
                $outbound[] = $itinerary;
            }
        }

        if ($inbound === []) {
            return array_map(fn ($item) => [$item], $outbound);
        }

        $groups = [];
        foreach ($outbound as $out) {
            foreach ($inbound as $in) {
                $groups[] = [$out, $in];
            }
        }

        return $groups !== [] ? $groups : array_map(fn ($item) => [$item], $outbound);
    }

    /**
     * @param  list<array<string, mixed>>  $group
     */
    private function buildOfferFromItineraries(
        array $group,
        SupplierConnection $connection,
        string $correlationId,
    ): ?NormalizedFlightOfferData {
        $segmentRows = [];
        $total = 0.0;
        $base = 0.0;
        $taxes = 0.0;
        $currency = 'PKR';

        foreach ($group as $itinerary) {
            $fare = is_array($itinerary['total_fare'] ?? null) ? $itinerary['total_fare'] : [];
            $total += (float) ($fare['total'] ?? 0);
            $base += (float) ($fare['base'] ?? 0);
            $taxes += (float) ($fare['taxes'] ?? 0) + (float) ($fare['fees'] ?? 0);
            $currency = (string) ($fare['currency'] ?? $currency);
            foreach (is_array($itinerary['segments'] ?? null) ? $itinerary['segments'] : [] as $seg) {
                if (! is_array($seg)) {
                    continue;
                }
                $segmentRows[] = [
                    'departure_airport' => strtoupper((string) ($seg['departure_airport'] ?? '')),
                    'arrival_airport' => strtoupper((string) ($seg['arrival_airport'] ?? '')),
                    'departure_at' => (string) ($seg['departure_datetime'] ?? ''),
                    'arrival_at' => (string) ($seg['arrival_datetime'] ?? ''),
                    'carrier' => strtoupper((string) ($seg['marketing_carrier'] ?? 'PA')),
                    'flight_number' => (string) ($seg['flight_number'] ?? ''),
                    'rbd' => (string) ($seg['rbd'] ?? ''),
                ];
            }
        }

        if ($segmentRows === []) {
            return null;
        }

        $first = $segmentRows[0];
        $last = $segmentRows[array_key_last($segmentRows)];
        $carrier = strtoupper((string) ($first['carrier'] ?? 'PA'));
        $offerRef = 'airblue-ota-'.substr(sha1(json_encode($group).$correlationId), 0, 16);

        $providerContext = [
            'api_channel' => AirBlueApiChannel::ZapwaysOta->value,
            'correlation_id' => $correlationId,
            'priced_itineraries' => $group,
            'offer_ref_key' => $offerRef,
        ];

        return new NormalizedFlightOfferData(
            offer_id: $offerRef,
            supplier_provider: SupplierProvider::Airblue->value,
            supplier_connection_id: $connection->id,
            airline_code: $carrier ?: 'PA',
            airline_name: 'AirBlue',
            flight_number: (string) ($first['flight_number'] ?? ''),
            origin: (string) ($first['departure_airport'] ?? ''),
            destination: (string) ($last['arrival_airport'] ?? ''),
            departure_at: (string) ($first['departure_at'] ?? ''),
            arrival_at: (string) ($last['arrival_at'] ?? ''),
            duration_minutes: $this->durationMinutes((string) ($first['departure_at'] ?? ''), (string) ($last['arrival_at'] ?? '')),
            stops: max(0, count($segmentRows) - 1),
            cabin: 'ECONOMY',
            fare_family: null,
            refundable: false,
            seats_left: null,
            segments: $segmentRows,
            baggage: new BaggageAllowanceData(checked: '20 KG', cabin: '7 KG', summary: null),
            fare_breakdown: new FareBreakdownData(
                base_fare: $base,
                taxes: $taxes,
                supplier_fees: 0,
                supplier_total: $total,
                currency: $currency,
            ),
            raw_reference: $offerRef,
            raw_payload: ['provider_context' => $providerContext],
            marketing_carrier_chain: [$carrier ?: 'PA'],
            operating_carrier_chain: [$carrier ?: 'PA'],
            validating_carrier: $carrier ?: 'PA',
            primary_display_carrier: $carrier ?: 'PA',
        );
    }

    private function durationMinutes(string $departure, string $arrival): int
    {
        if ($departure === '' || $arrival === '') {
            return 0;
        }

        try {
            $start = new \DateTimeImmutable($departure);
            $end = new \DateTimeImmutable($arrival);

            return max(0, (int) round(($end->getTimestamp() - $start->getTimestamp()) / 60));
        } catch (\Throwable) {
            return 0;
        }
    }
}
