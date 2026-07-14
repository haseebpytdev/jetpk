<?php

namespace App\Services\Suppliers\AirBlue;

use App\Data\BaggageAllowanceData;
use App\Data\FareBreakdownData;
use App\Data\NormalizedFlightOfferData;
use App\Enums\SupplierProvider;
use App\Models\SupplierConnection;
use Carbon\Carbon;

/**
 * Normalizes AirBlue XML parse output into OTA internal DTOs.
 */
class AirBlueResponseNormalizer
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
        $shoppingRef = trim((string) ($parsed['shopping_response_ref_id'] ?? ''));
        $offers = is_array($parsed['offers'] ?? null) ? $parsed['offers'] : [];
        $dataLists = is_array($parsed['data_lists'] ?? null) ? $parsed['data_lists'] : [];
        $segmentsById = $this->indexSegments($dataLists);
        $journeysById = $this->indexJourneys($dataLists);
        $normalized = [];

        foreach ($offers as $offer) {
            if (! is_array($offer)) {
                continue;
            }
            $built = $this->buildOffer($offer, $connection, $correlationId, $shoppingRef, $segmentsById, $journeysById, $dataLists);
            if ($built !== null) {
                $normalized[] = $built;
            }
            if (count($normalized) >= 150) {
                break;
            }
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $parsedResponse
     * @param  array<string, mixed>  $existingContext
     * @return array<string, mixed>
     */
    public function normalizeBookingResponse(array $parsedResponse, array $existingContext = []): array
    {
        $parsed = is_array($parsedResponse['parsed'] ?? null) ? $parsedResponse['parsed'] : [];
        $order = is_array($parsed['order'] ?? null) ? $parsed['order'] : [];

        return [
            'provider_booking_reference' => trim((string) ($order['order_id'] ?? '')),
            'pnr' => trim((string) ($order['order_id'] ?? '')),
            'status' => trim((string) ($order['status'] ?? 'option')) ?: 'option',
            'ticketing_status' => 'pending_ticketing',
            'last_ticketing_date' => trim((string) ($order['payment_time_limit'] ?? $parsed['payment_time_limit'] ?? '')),
            'provider_context' => array_merge($existingContext, [
                'order_id' => trim((string) ($order['order_id'] ?? '')),
                'owner_code' => trim((string) ($order['owner_code'] ?? $existingContext['owner_code'] ?? '')),
                'payment_time_limit' => trim((string) ($order['payment_time_limit'] ?? $parsed['payment_time_limit'] ?? '')),
            ]),
            'supplier_messages' => $this->warningMessages($parsedResponse),
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
        $order = is_array($parsed['order'] ?? null) ? $parsed['order'] : [];
        $tickets = is_array($parsed['ticket_doc_infos'] ?? null) ? $parsed['ticket_doc_infos'] : [];

        $ticketNumbers = [];
        foreach ($tickets as $ticket) {
            if (is_array($ticket) && ($ticket['ticket_number'] ?? '') !== '') {
                $ticketNumbers[] = (string) $ticket['ticket_number'];
            }
        }

        $result = array_filter([
            'order_id' => trim((string) ($order['order_id'] ?? $existing['order_id'] ?? '')),
            'owner_code' => trim((string) ($order['owner_code'] ?? $existing['owner_code'] ?? '')),
            'status' => trim((string) ($order['status'] ?? '')) ?: null,
            'ticketing_status' => $ticketNumbers !== [] ? 'ticketed' : ($existing['ticketing_status'] ?? 'pending_ticketing'),
            'ticket_numbers' => $ticketNumbers !== [] ? $ticketNumbers : null,
            'payment_time_limit' => trim((string) ($order['payment_time_limit'] ?? $parsed['payment_time_limit'] ?? $existing['payment_time_limit'] ?? '')) ?: null,
        ], fn ($v) => $v !== null && $v !== '');

        return $result;
    }

    /**
     * @param  array<string, mixed>  $parsedResponse
     * @return array{amount: float, currency: string}
     */
    public function normalizeTicketPreviewResponse(array $parsedResponse): array
    {
        $parsed = is_array($parsedResponse['parsed'] ?? null) ? $parsedResponse['parsed'] : [];
        $preview = is_array($parsed['ticket_preview'] ?? null) ? $parsed['ticket_preview'] : [];
        $order = is_array($parsed['order'] ?? null) ? $parsed['order'] : [];
        $price = is_array($order['total_price'] ?? null) ? $order['total_price'] : [];

        return [
            'amount' => (float) ($preview['amount'] ?? $price['total'] ?? 0),
            'currency' => (string) ($preview['currency'] ?? $price['currency'] ?? 'PKR'),
        ];
    }

    /**
     * @param  array<string, mixed>  $parsedResponse
     * @return array<string, mixed>
     */
    public function normalizeTicketingResponse(array $parsedResponse, array $existingContext = []): array
    {
        $parsed = is_array($parsedResponse['parsed'] ?? null) ? $parsedResponse['parsed'] : [];
        $tickets = is_array($parsed['ticket_doc_infos'] ?? null) ? $parsed['ticket_doc_infos'] : [];
        $ticketNumbers = [];
        foreach ($tickets as $ticket) {
            if (is_array($ticket) && ($ticket['ticket_number'] ?? '') !== '') {
                $ticketNumbers[] = (string) $ticket['ticket_number'];
            }
        }

        return [
            'ticketing_status' => $ticketNumbers !== [] ? 'ticketed' : 'ticketing_failed',
            'ticket_numbers' => $ticketNumbers,
            'provider_context' => array_merge($existingContext, [
                'ticket_numbers' => $ticketNumbers,
                'ticketed_at' => now()->toIso8601String(),
            ]),
            'supplier_messages' => $this->warningMessages($parsedResponse),
        ];
    }

    /**
     * @param  array<string, mixed>  $parsedResponse
     * @return array<string, mixed>
     */
    public function normalizeCancelPreviewResponse(array $parsedResponse): array
    {
        $parsed = is_array($parsedResponse['parsed'] ?? null) ? $parsedResponse['parsed'] : [];
        $preview = is_array($parsed['cancel_preview'] ?? null) ? $parsed['cancel_preview'] : [];

        return [
            'penalty' => $preview['penalty'] ?? null,
            'refundable_amount' => $preview['refundable'] ?? null,
            'currency' => (string) ($preview['currency'] ?? 'PKR'),
            'cancellation_status' => 'preview',
        ];
    }

    /**
     * @param  array<string, mixed>  $parsedResponse
     * @return array<string, mixed>
     */
    public function normalizeCancelCommitResponse(array $parsedResponse): array
    {
        return [
            'cancellation_status' => 'cancelled',
            'supplier_messages' => $this->warningMessages($parsedResponse),
        ];
    }

    /**
     * @param  array<string, mixed>  $parsedResponse
     * @return array<string, mixed>
     */
    public function normalizeVoidResponse(array $parsedResponse, array $existing = []): array
    {
        $parsed = is_array($parsedResponse['parsed'] ?? null) ? $parsedResponse['parsed'] : [];

        return [
            'void_status' => 'voided',
            'payment_time_limit' => trim((string) ($parsed['payment_time_limit'] ?? $existing['payment_time_limit'] ?? '')),
            'supplier_messages' => $this->warningMessages($parsedResponse),
        ];
    }

    /**
     * @param  array<string, mixed>  $offer
     * @param  array<string, array<string, mixed>>  $segmentsById
     * @param  array<string, array<string, mixed>>  $journeysById
     * @param  array<string, mixed>  $dataLists
     */
    private function buildOffer(
        array $offer,
        SupplierConnection $connection,
        string $correlationId,
        string $shoppingRef,
        array $segmentsById,
        array $journeysById,
        array $dataLists,
    ): ?NormalizedFlightOfferData {
        $offerId = trim((string) ($offer['offer_id'] ?? ''));
        if ($offerId === '') {
            return null;
        }

        $journeyRefs = is_array($offer['journey_refs'] ?? null) ? $offer['journey_refs'] : [];
        $segmentRows = $this->resolveSegmentRows($journeyRefs, $journeysById, $segmentsById);
        if ($segmentRows === []) {
            return null;
        }

        $first = $segmentRows[0];
        $last = $segmentRows[array_key_last($segmentRows)];
        $departureAt = (string) ($first['departure_at'] ?? '');
        $arrivalAt = (string) ($last['arrival_at'] ?? '');
        $duration = $this->durationMinutes($departureAt, $arrivalAt);
        $ownerCode = trim((string) ($offer['owner_code'] ?? ''));
        $price = is_array($offer['total_price'] ?? null) ? $offer['total_price'] : ['total' => 0, 'base' => 0, 'tax' => 0, 'currency' => 'PKR'];
        $offerItems = is_array($offer['offer_items'] ?? null) ? $offer['offer_items'] : [];
        $firstItem = is_array($offerItems[0] ?? null) ? $offerItems[0] : [];
        $paxRefs = is_array($firstItem['pax_refs'] ?? null) ? $firstItem['pax_refs'] : [];
        $offerItemRefs = [];
        foreach ($offerItems as $item) {
            if (! is_array($item)) {
                continue;
            }
            foreach ($paxRefs as $paxRef) {
                $offerItemRefs[] = [
                    'offer_item_ref_id' => (string) ($item['offer_item_id'] ?? ''),
                    'pax_ref_id' => (string) $paxRef,
                ];
            }
            if ($offerItemRefs === [] && ($item['offer_item_id'] ?? '') !== '') {
                $offerItemRefs[] = [
                    'offer_item_ref_id' => (string) $item['offer_item_id'],
                    'pax_ref_id' => (string) ($paxRefs[0] ?? 'ADTPax-1'),
                ];
            }
        }

        $providerContext = [
            'provider' => SupplierProvider::Airblue->value,
            'api_channel' => 'crane_ndc',
            'shopping_response_ref_id' => $shoppingRef,
            'offer_ref_id' => $offerId,
            'owner_code' => $ownerCode,
            'offer_item_refs' => $offerItemRefs,
            'offer_item_ref_id' => (string) ($firstItem['offer_item_id'] ?? 'OfferItem-1'),
            'pax_ref_id' => (string) ($paxRefs[0] ?? 'ADTPax-1'),
            'pax_journey_ref_ids' => $journeyRefs,
            'pax_segment_ref_ids' => array_values(array_filter(array_map(fn ($s) => (string) ($s['pax_segment_id'] ?? ''), $segmentRows))),
            'fare_basis' => (string) ($firstItem['fare_basis'] ?? ''),
            'rbd' => (string) ($firstItem['rbd'] ?? $first['rbd'] ?? ''),
            'cabin_type' => (string) ($firstItem['cabin'] ?? 'Y'),
            'payment_time_limit' => (string) ($firstItem['payment_time_limit'] ?? ''),
            'search_correlation_id' => $correlationId,
        ];

        $carrier = strtoupper((string) ($first['carrier'] ?? 'PA'));

        return new NormalizedFlightOfferData(
            offer_id: 'air-blue-'.substr(sha1($offerId.$correlationId), 0, 16),
            supplier_provider: SupplierProvider::Airblue->value,
            supplier_connection_id: $connection->id,
            airline_code: $carrier ?: 'PA',
            airline_name: 'AirBlue',
            flight_number: (string) ($first['flight_number'] ?? ''),
            origin: (string) ($first['departure_airport'] ?? ''),
            destination: (string) ($last['arrival_airport'] ?? ''),
            departure_at: $departureAt,
            arrival_at: $arrivalAt,
            duration_minutes: $duration,
            stops: max(0, count($segmentRows) - 1),
            cabin: (string) ($firstItem['cabin'] ?? 'ECONOMY'),
            fare_family: null,
            refundable: false,
            seats_left: null,
            segments: $segmentRows,
            baggage: new BaggageAllowanceData(checked: '20 KG', cabin: '7 KG', summary: null),
            fare_breakdown: new FareBreakdownData(
                base_fare: (float) ($price['base'] ?? 0),
                taxes: (float) ($price['tax'] ?? 0),
                supplier_fees: 0,
                supplier_total: (float) ($price['total'] ?? 0),
                currency: (string) ($price['currency'] ?? 'PKR'),
            ),
            expires_at: ($firstItem['payment_time_limit'] ?? '') !== '' ? (string) $firstItem['payment_time_limit'] : null,
            raw_reference: $offerId,
            raw_payload: ['provider_context' => $providerContext],
            marketing_carrier_chain: [$carrier ?: 'PA'],
            operating_carrier_chain: [$carrier ?: 'PA'],
            validating_carrier: $carrier ?: 'PA',
            primary_display_carrier: $carrier ?: 'PA',
        );
    }

    /**
     * @param  array<string, mixed>  $dataLists
     * @return array<string, array<string, mixed>>
     */
    private function indexSegments(array $dataLists): array
    {
        $indexed = [];
        foreach (is_array($dataLists['pax_segments'] ?? null) ? $dataLists['pax_segments'] : [] as $segment) {
            if (! is_array($segment)) {
                continue;
            }
            $id = (string) ($segment['pax_segment_id'] ?? '');
            if ($id !== '') {
                $indexed[$id] = $segment;
            }
        }

        return $indexed;
    }

    /**
     * @param  array<string, mixed>  $dataLists
     * @return array<string, array<string, mixed>>
     */
    private function indexJourneys(array $dataLists): array
    {
        $indexed = [];
        foreach (is_array($dataLists['pax_journeys'] ?? null) ? $dataLists['pax_journeys'] : [] as $journey) {
            if (! is_array($journey)) {
                continue;
            }
            $id = (string) ($journey['pax_journey_id'] ?? '');
            if ($id !== '') {
                $indexed[$id] = $journey;
            }
        }

        return $indexed;
    }

    /**
     * @param  list<string>  $journeyRefs
     * @param  array<string, array<string, mixed>>  $journeysById
     * @param  array<string, array<string, mixed>>  $segmentsById
     * @return list<array<string, mixed>>
     */
    private function resolveSegmentRows(array $journeyRefs, array $journeysById, array $segmentsById): array
    {
        $rows = [];
        foreach ($journeyRefs as $journeyRef) {
            $journey = $journeysById[$journeyRef] ?? null;
            if (! is_array($journey)) {
                continue;
            }
            foreach (is_array($journey['pax_segment_refs'] ?? null) ? $journey['pax_segment_refs'] : [] as $segmentRef) {
                $segment = $segmentsById[(string) $segmentRef] ?? null;
                if (! is_array($segment)) {
                    continue;
                }
                $rows[] = [
                    'pax_segment_id' => (string) ($segment['pax_segment_id'] ?? ''),
                    'airline_code' => (string) ($segment['carrier'] ?? 'PK'),
                    'flight_number' => (string) ($segment['flight_number'] ?? ''),
                    'origin' => (string) ($segment['departure_airport'] ?? ''),
                    'destination' => (string) ($segment['arrival_airport'] ?? ''),
                    'departure_at' => (string) ($segment['departure_at'] ?? ''),
                    'arrival_at' => (string) ($segment['arrival_at'] ?? ''),
                    'departure_airport' => (string) ($segment['departure_airport'] ?? ''),
                    'arrival_airport' => (string) ($segment['arrival_airport'] ?? ''),
                    'rbd' => (string) ($segment['rbd'] ?? ''),
                ];
            }
        }

        return $rows;
    }

    private function durationMinutes(string $departureAt, string $arrivalAt): int
    {
        try {
            $dep = Carbon::parse($departureAt);
            $arr = Carbon::parse($arrivalAt);

            return max(0, (int) $dep->diffInMinutes($arr));
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * @param  array<string, mixed>  $parsedResponse
     * @return list<string>
     */
    private function warningMessages(array $parsedResponse): array
    {
        $messages = [];
        foreach (is_array($parsedResponse['warnings'] ?? null) ? $parsedResponse['warnings'] : [] as $warning) {
            if (is_array($warning) && ($warning['message'] ?? '') !== '') {
                $messages[] = (string) $warning['message'];
            }
        }

        return $messages;
    }
}
