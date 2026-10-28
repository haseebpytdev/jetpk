<?php

namespace App\Services\Suppliers\AirBlue;

use App\Data\BaggageAllowanceData;
use App\Data\FareBreakdownData;
use App\Data\NormalizedFlightOfferData;
use App\Enums\AirBlueApiChannel;
use App\Enums\AirBlueZapwaysProtocolVersion;
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
                'protocol_version' => (string) ($existingContext['protocol_version'] ?? AirBlueZapwaysProtocolVersion::V2->value),
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

        $ticketingRecords = is_array($parsed['ticketing_records'] ?? null) ? $parsed['ticketing_records'] : null;
        $allRefunded = false;
        if (is_array($ticketingRecords) && $ticketingRecords !== []) {
            $allRefunded = true;
            foreach ($ticketingRecords as $record) {
                if (! is_array($record)) {
                    continue;
                }
                $status = strtoupper(trim((string) ($record['ticketing_status'] ?? '')));
                $doc = trim((string) ($record['ticket_document_number'] ?? ''));
                if ($status === '' && $doc === '') {
                    continue;
                }
                if ($status !== 'REFUNDED') {
                    $allRefunded = false;
                    break;
                }
            }
        }

        return array_filter([
            'api_channel' => AirBlueApiChannel::ZapwaysOta->value,
            'protocol_version' => (string) ($existing['protocol_version'] ?? AirBlueZapwaysProtocolVersion::V2->value),
            'pnr' => trim((string) ($booking['pnr'] ?? $parsed['pnr'] ?? $existing['pnr'] ?? '')),
            'instance' => trim((string) ($booking['instance'] ?? $parsed['instance'] ?? $existing['instance'] ?? '')),
            'ticketing_status' => $ticketNumbers !== [] ? 'ticketed' : ($existing['ticketing_status'] ?? 'pending_ticketing'),
            'ticket_numbers' => $ticketNumbers !== [] ? $ticketNumbers : null,
            'ticketing_records' => $ticketingRecords,
            'refund_status' => $allRefunded ? 'refunded' : ($existing['refund_status'] ?? null),
            'seats' => is_array($parsed['seats'] ?? null) ? $parsed['seats'] : null,
            'items' => is_array($parsed['items'] ?? null) ? $parsed['items'] : null,
            'transaction_history' => is_array($parsed['transaction_history'] ?? null) ? $parsed['transaction_history'] : null,
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

        $paymentSuccessOnly = (bool) ($parsed['payment_success_only'] ?? false);

        return [
            'ticketing_status' => $ticketNumbers !== [] ? 'ticketed' : ($paymentSuccessOnly ? 'paid' : 'failed'),
            'ticket_numbers' => $ticketNumbers,
            'provider_context' => array_merge($existing, [
                'api_channel' => AirBlueApiChannel::ZapwaysOta->value,
                'protocol_version' => (string) ($existing['protocol_version'] ?? AirBlueZapwaysProtocolVersion::V2->value),
                'ticketing_status' => $ticketNumbers !== [] ? 'ticketed' : ($paymentSuccessOnly ? 'paid' : ($existing['ticketing_status'] ?? 'pending_ticketing')),
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
        $parsed = is_array($parsedResponse['parsed'] ?? null) ? $parsedResponse['parsed'] : [];
        $errors = is_array($parsedResponse['errors'] ?? null) ? $parsedResponse['errors'] : [];
        $soapFault = is_array($parsedResponse['soap_fault'] ?? null) ? $parsedResponse['soap_fault'] : null;

        if ($soapFault !== null || $errors !== []) {
            return [
                'cancellation_status' => 'failed',
                'api_channel' => AirBlueApiChannel::ZapwaysOta->value,
                'held_seats_removed' => false,
                'held_items_removed' => false,
                'error_code' => (string) ($soapFault['code'] ?? ($errors[0]['code'] ?? 'cancel_failed')),
            ];
        }

        return [
            'cancellation_status' => 'cancelled',
            'api_channel' => AirBlueApiChannel::ZapwaysOta->value,
            'held_seats_removed' => is_array($parsed['seats'] ?? null) ? $parsed['seats'] === [] : true,
            'held_items_removed' => is_array($parsed['items'] ?? null) ? $parsed['items'] === [] : true,
        ];
    }

    /**
     * Normalize Zapways full-refund AirBookModify / Read truth into airblue_context fields.
     * Does not overwrite original supplier_payable_amount / ticket_numbers / markup.
     *
     * @param  array<string, mixed>  $parsedResponse
     * @param  array<string, mixed>  $existing
     * @return array{
     *     refund_status: string,
     *     refunded_ticket_count: int,
     *     refund_transaction_amount: float|null,
     *     refund_currency: string|null,
     *     seats_refunded: bool,
     *     items_refunded: bool,
     *     ticketing_records: list<array<string, mixed>>,
     *     provider_context: array<string, mixed>
     * }
     */
    public function normalizeRefundResponse(array $parsedResponse, array $existing = []): array
    {
        $parsed = is_array($parsedResponse['parsed'] ?? null) ? $parsedResponse['parsed'] : [];
        $errors = is_array($parsedResponse['errors'] ?? null) ? $parsedResponse['errors'] : [];
        $soapFault = is_array($parsedResponse['soap_fault'] ?? null) ? $parsedResponse['soap_fault'] : null;
        $records = is_array($parsed['ticketing_records'] ?? null) ? $parsed['ticketing_records'] : [];
        $history = is_array($parsed['transaction_history'] ?? null) ? $parsed['transaction_history'] : [];
        $seats = is_array($parsed['seats'] ?? null) ? $parsed['seats'] : [];
        $items = is_array($parsed['items'] ?? null) ? $parsed['items'] : [];

        $refundedDocs = [];
        foreach ($records as $record) {
            if (! is_array($record)) {
                continue;
            }
            $status = strtoupper(trim((string) ($record['ticketing_status'] ?? '')));
            if ($status !== 'REFUNDED') {
                continue;
            }
            $doc = trim((string) ($record['ticket_document_number'] ?? ''));
            if ($doc !== '') {
                $refundedDocs[$doc] = true;
            } else {
                $refundedDocs['coupon_'.count($refundedDocs)] = true;
            }
        }
        $refundedTicketCount = count($refundedDocs);
        $applicableCount = 0;
        foreach ($records as $record) {
            if (is_array($record) && (
                trim((string) ($record['ticketing_status'] ?? '')) !== ''
                || trim((string) ($record['ticket_document_number'] ?? '')) !== ''
            )) {
                $applicableCount++;
            }
        }
        $allCouponsRefunded = $applicableCount > 0 && $refundedTicketCount > 0 && $refundedTicketCount >= $this->uniqueTicketingDocumentCount($records)
            && $this->allTicketingRecordsRefunded($records);

        $refundAmount = null;
        $refundCurrency = null;
        foreach ($history as $tx) {
            if (! is_array($tx)) {
                continue;
            }
            $amount = (float) ($tx['amount'] ?? 0);
            if ($amount < 0) {
                $refundAmount = $amount;
                $currency = trim((string) ($tx['currency'] ?? ''));
                $refundCurrency = $currency !== '' ? strtoupper($currency) : null;
                break;
            }
        }

        $seatsRefunded = false;
        foreach ($seats as $seat) {
            if (is_array($seat) && strcasecmp(trim((string) ($seat['status'] ?? '')), 'Refunded') === 0) {
                $seatsRefunded = true;
                break;
            }
        }
        $itemsRefunded = false;
        foreach ($items as $item) {
            if (is_array($item) && strcasecmp(trim((string) ($item['status'] ?? '')), 'Refunded') === 0) {
                $itemsRefunded = true;
                break;
            }
        }

        if ($soapFault !== null || $errors !== []) {
            $refundStatus = 'failed';
        } elseif ($allCouponsRefunded) {
            $refundStatus = 'refunded';
        } else {
            $refundStatus = 'failed';
        }

        $providerContext = array_merge($existing, [
            'api_channel' => AirBlueApiChannel::ZapwaysOta->value,
            'protocol_version' => (string) ($existing['protocol_version'] ?? AirBlueZapwaysProtocolVersion::V2->value),
            'refund_status' => $refundStatus,
            'refunded_ticket_count' => $refundedTicketCount,
            'ticketing_records' => $records,
            'seats' => $seats !== [] ? $seats : ($existing['seats'] ?? null),
            'items' => $items !== [] ? $items : ($existing['items'] ?? null),
            'transaction_history' => $history !== [] ? $history : ($existing['transaction_history'] ?? null),
        ]);
        // Preserve commercial history — never overwrite original supplier payable from refund tx.
        if (array_key_exists('supplier_payable_amount', $existing)) {
            $providerContext['supplier_payable_amount'] = $existing['supplier_payable_amount'];
        }
        if (array_key_exists('ticket_numbers', $existing) && is_array($existing['ticket_numbers'])) {
            $providerContext['ticket_numbers'] = $existing['ticket_numbers'];
        }
        if ($refundAmount !== null) {
            $providerContext['refund_transaction_amount'] = $refundAmount;
        }
        if ($refundCurrency !== null) {
            $providerContext['refund_currency'] = $refundCurrency;
        }
        if ($refundStatus === 'refunded') {
            $providerContext['refunded_at'] = now()->toIso8601String();
        }
        $providerContext = array_filter($providerContext, static fn ($v) => $v !== null && $v !== '');

        return [
            'refund_status' => $refundStatus,
            'refunded_ticket_count' => $refundedTicketCount,
            'refund_transaction_amount' => $refundAmount,
            'refund_currency' => $refundCurrency,
            'seats_refunded' => $seatsRefunded,
            'items_refunded' => $itemsRefunded,
            'ticketing_records' => $records,
            'provider_context' => $providerContext,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $records
     */
    private function allTicketingRecordsRefunded(array $records): bool
    {
        $seen = false;
        foreach ($records as $record) {
            if (! is_array($record)) {
                continue;
            }
            $status = strtoupper(trim((string) ($record['ticketing_status'] ?? '')));
            $doc = trim((string) ($record['ticket_document_number'] ?? ''));
            if ($status === '' && $doc === '') {
                continue;
            }
            $seen = true;
            if ($status !== 'REFUNDED') {
                return false;
            }
        }

        return $seen;
    }

    /**
     * @param  list<array<string, mixed>>  $records
     */
    private function uniqueTicketingDocumentCount(array $records): int
    {
        $docs = [];
        foreach ($records as $record) {
            if (! is_array($record)) {
                continue;
            }
            $status = strtoupper(trim((string) ($record['ticketing_status'] ?? '')));
            if ($status !== 'REFUNDED') {
                continue;
            }
            $doc = trim((string) ($record['ticket_document_number'] ?? ''));
            $docs[$doc !== '' ? $doc : 'anon_'.count($docs)] = true;
        }

        return count($docs);
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
        $fareBasisFromPtc = null;

        foreach ($group as $itinerary) {
            $fare = is_array($itinerary['total_fare'] ?? null) ? $itinerary['total_fare'] : [];
            $total += (float) ($fare['total'] ?? 0);
            $base += (float) ($fare['base'] ?? 0);
            $taxes += (float) ($fare['taxes'] ?? 0) + (float) ($fare['fees'] ?? 0);
            $currency = (string) ($fare['currency'] ?? $currency);
            if ($fareBasisFromPtc === null) {
                foreach (is_array($itinerary['fare_breakdowns'] ?? null) ? $itinerary['fare_breakdowns'] : [] as $breakdown) {
                    if (! is_array($breakdown)) {
                        continue;
                    }
                    $candidate = trim((string) ($breakdown['fare_basis'] ?? ''));
                    if ($candidate !== '') {
                        $fareBasisFromPtc = $candidate;
                        break;
                    }
                }
            }
            foreach (is_array($itinerary['segments'] ?? null) ? $itinerary['segments'] : [] as $seg) {
                if (! is_array($seg)) {
                    continue;
                }
                $origin = strtoupper(trim((string) ($seg['departure_airport'] ?? '')));
                $destination = strtoupper(trim((string) ($seg['arrival_airport'] ?? '')));
                $carrierCode = strtoupper(trim((string) ($seg['marketing_carrier'] ?? 'PA'))) ?: 'PA';
                $operating = strtoupper(trim((string) ($seg['operating_carrier'] ?? $carrierCode))) ?: $carrierCode;
                $fareType = trim((string) ($seg['fare_type'] ?? ''));
                $rbd = trim((string) ($seg['rbd'] ?? ''));
                $cabinClass = trim((string) ($seg['cabin_class'] ?? ''));
                $segmentFareBasis = trim((string) ($seg['fare_basis'] ?? ''));
                $segmentRows[] = array_filter([
                    // Canonical consolidator / presenter keys
                    'origin' => $origin,
                    'destination' => $destination,
                    'airline_code' => $carrierCode,
                    'operating_airline_code' => $operating,
                    'departure_at' => (string) ($seg['departure_datetime'] ?? ''),
                    'arrival_at' => (string) ($seg['arrival_datetime'] ?? ''),
                    'flight_number' => (string) ($seg['flight_number'] ?? ''),
                    'booking_class' => $rbd !== '' ? $rbd : null,
                    'fare_basis' => $segmentFareBasis !== '' ? $segmentFareBasis : null,
                    // Legacy AirBlue keys retained for booking/priced-itinerary consumers
                    'departure_airport' => $origin,
                    'arrival_airport' => $destination,
                    'carrier' => $carrierCode,
                    'rbd' => $rbd,
                    'fare_type' => $fareType,
                    'cabin_class' => $cabinClass,
                ], static fn (mixed $v): bool => $v !== null && $v !== '');
            }
        }

        if ($segmentRows === []) {
            return null;
        }

        $first = $segmentRows[0];
        $last = $segmentRows[array_key_last($segmentRows)];
        $carrier = strtoupper((string) ($first['airline_code'] ?? $first['carrier'] ?? 'PA'));
        $offerRef = 'airblue-ota-'.substr(sha1(json_encode($group).$correlationId), 0, 16);

        $credentials = is_array($connection->credentials) ? $connection->credentials : [];
        $protocolVersion = AirBlueZapwaysProtocolVersion::fromCredentials($credentials)->value;

        $fareType = trim((string) ($first['fare_type'] ?? ''));
        $rbd = trim((string) ($first['rbd'] ?? $first['booking_class'] ?? ''));
        $cabinClassRaw = trim((string) ($first['cabin_class'] ?? ''));
        $cabin = $this->mapCabinClass($cabinClassRaw);
        $fareBasis = trim((string) ($first['fare_basis'] ?? ''));
        if ($fareBasis === '' && is_string($fareBasisFromPtc)) {
            $fareBasis = $fareBasisFromPtc;
        }

        $providerContext = array_filter([
            'api_channel' => AirBlueApiChannel::ZapwaysOta->value,
            'protocol_version' => $protocolVersion,
            'supplier_connection_id' => $connection->id,
            'correlation_id' => $correlationId,
            'priced_itineraries' => $group,
            'offer_ref_key' => $offerRef,
            // Exact supplier FareType / RBD for consolidator brand_code + booking_class.
            'fare_type_code' => $fareType !== '' ? $fareType : null,
            'rbd' => $rbd !== '' ? $rbd : null,
            'fare_basis' => $fareBasis !== '' ? $fareBasis : null,
        ], static fn (mixed $v): bool => $v !== null && $v !== '');

        $customerDisplayFields = array_filter([
            'booking_class' => $rbd !== '' ? $rbd : null,
            'fare_basis' => $fareBasis !== '' ? $fareBasis : null,
            'fare_type' => $fareType !== '' ? $fareType : null,
            'cabin_class' => $cabinClassRaw !== '' ? $cabinClassRaw : null,
        ], static fn (mixed $v): bool => $v !== null && $v !== '');

        return new NormalizedFlightOfferData(
            offer_id: $offerRef,
            supplier_provider: SupplierProvider::Airblue->value,
            supplier_connection_id: $connection->id,
            airline_code: $carrier ?: 'PA',
            airline_name: 'AirBlue',
            flight_number: (string) ($first['flight_number'] ?? ''),
            origin: (string) ($first['origin'] ?? $first['departure_airport'] ?? ''),
            destination: (string) ($last['destination'] ?? $last['arrival_airport'] ?? ''),
            departure_at: (string) ($first['departure_at'] ?? ''),
            arrival_at: (string) ($last['arrival_at'] ?? ''),
            duration_minutes: $this->durationMinutes((string) ($first['departure_at'] ?? ''), (string) ($last['arrival_at'] ?? '')),
            stops: max(0, count($segmentRows) - 1),
            cabin: $cabin,
            // Exact supplier FareType only — never invent marketing labels.
            fare_family: $fareType !== '' ? $fareType : null,
            // Do not invent refundability; false means unknown/not asserted as refundable.
            refundable: false,
            seats_left: null,
            segments: $segmentRows,
            // Zapways search fixtures do not expose baggage — do not invent allowances.
            baggage: new BaggageAllowanceData(checked: null, cabin: null, summary: null),
            fare_breakdown: new FareBreakdownData(
                base_fare: $base,
                taxes: $taxes,
                supplier_fees: 0,
                supplier_total: $total,
                currency: $currency,
            ),
            raw_reference: $offerRef,
            raw_payload: [
                'provider_context' => $providerContext,
                'customer_display_fields' => $customerDisplayFields,
            ],
            marketing_carrier_chain: [$carrier ?: 'PA'],
            operating_carrier_chain: [$carrier ?: 'PA'],
            validating_carrier: $carrier ?: 'PA',
            primary_display_carrier: $carrier ?: 'PA',
            branded_fares: [],
        );
    }

    /**
     * Map Zapways CabinClass onto JetPakistan cabin tokens without inventing a different cabin.
     */
    private function mapCabinClass(string $cabinClass): string
    {
        $raw = strtoupper(trim($cabinClass));
        if ($raw === '') {
            return 'economy';
        }

        return match ($raw) {
            'Y', 'ECONOMY', 'ECO', 'COACH' => 'economy',
            'W', 'PREMIUM_ECONOMY', 'PREMIUMECONOMY', 'PREMIUM ECONOMY' => 'premium_economy',
            'C', 'J', 'BUSINESS' => 'business',
            'F', 'FIRST' => 'first',
            default => strtolower($cabinClass),
        };
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
