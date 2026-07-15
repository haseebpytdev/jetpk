<?php

namespace App\Services\Suppliers\Iati;

use App\Data\BaggageAllowanceData;
use App\Data\FareBreakdownData;
use App\Data\NormalizedFlightOfferData;
use App\Enums\SupplierProvider;
use App\Models\SupplierConnection;
use App\Support\FlightSearch\BaggageDisplayNormalizer;
use App\Support\Pricing\IatiFarePricingResolver;
use Carbon\Carbon;

/**
 * Normalizes IATI search/fare/booking responses into OTA internal DTOs.
 */
class IatiResponseNormalizer
{
    public function __construct(
        private readonly IatiFareRulesService $fareRulesService,
    ) {}

    /**
     * @param  array<string, mixed>  $response
     * @return list<NormalizedFlightOfferData>
     */
    public function normalizeSearchResponse(
        array $response,
        SupplierConnection $connection,
        string $correlationId,
        int $adults = 1,
        int $children = 0,
        int $infants = 0,
    ): array {
        $data = $this->unwrap($response);
        $departures = array_values(is_array($data['departure_flights'] ?? null) ? $data['departure_flights'] : []);
        $returns = array_values(is_array($data['return_flights'] ?? null) ? $data['return_flights'] : []);
        $offers = [];
        $paxCounts = ['adults' => $adults, 'children' => $children, 'infants' => $infants];

        foreach ($departures as $departure) {
            if (! is_array($departure)) {
                continue;
            }

            if ($returns === []) {
                $normalized = $this->buildItineraryOffer($departure, null, $connection, $correlationId, $paxCounts);
                if ($normalized !== null) {
                    $offers[] = $normalized;
                }

                continue;
            }

            foreach ($returns as $return) {
                if (! is_array($return) || ! $this->canCombine($departure, $return)) {
                    continue;
                }

                $normalized = $this->buildItineraryOffer($departure, $return, $connection, $correlationId, $paxCounts);
                if ($normalized !== null) {
                    $offers[] = $normalized;
                }

                if (count($offers) >= 200) {
                    return $offers;
                }
            }
        }

        return $offers;
    }

    /**
     * @param  array<string, mixed>  $fareResponse
     * @return array{
     *     fare_detail_key: string,
     *     offer_keys: list<string>,
     *     total: float,
     *     base: float,
     *     tax: float,
     *     currency: string,
     *     change_rules: list<string>,
     *     provider_context: array<string, mixed>
     * }
     */
    public function normalizeFareResponse(array $fareResponse, array $existingContext = []): array
    {
        $data = $this->unwrap($fareResponse);
        $fareDetailKey = trim((string) ($data['fare_detail_key'] ?? ''));
        if ($fareDetailKey === '') {
            throw new \InvalidArgumentException('IATI fare_detail_key missing from fare response.');
        }

        $offers = array_values(is_array($data['offers'] ?? null) ? $data['offers'] : []);
        $fareOffers = $this->summarizeFareOffers($offers);
        $offerKeys = array_values(array_filter(array_map(
            fn (array $row): string => trim((string) ($row['offer_key'] ?? '')),
            $fareOffers,
        )));

        $selectedPricing = $this->combinedOfferPrice($offers);
        $changeRules = $this->fareRulesService->extractFromFareData($data);

        return [
            'fare_detail_key' => $fareDetailKey,
            'offer_keys' => $offerKeys,
            'fare_offers' => $fareOffers,
            'total' => $selectedPricing['total'],
            'base' => $selectedPricing['base'],
            'tax' => $selectedPricing['tax'],
            'currency' => $selectedPricing['currency'],
            'change_rules' => $changeRules,
            'provider_context' => array_merge($existingContext, [
                'fare_detail_key' => $fareDetailKey,
                'fare_offers' => $fareOffers,
                'offer_keys' => $offerKeys,
                'fare_response' => $this->privateSummary($data),
            ]),
        ];
    }

    /**
     * @param  array<string, mixed>  $response
     * @param  array<string, mixed>  $existingContext
     * @return array<string, mixed>
     */
    public function normalizeBookingResponse(array $response, string $mode, array $existingContext = []): array
    {
        $data = $this->unwrap($response);
        $order = $this->firstOrder($data, $mode);

        return [
            'provider_booking_reference' => trim((string) ($order['order_id'] ?? '')),
            'pnr' => trim((string) ($order['pnr'] ?? '')),
            'airline_locator' => trim((string) ($order['airline_locator'] ?? $order['locator'] ?? '')),
            'status' => trim((string) ($order['status'] ?? ($mode === 'book' ? 'confirmed' : 'option'))),
            'ticketing_status' => $mode === 'book' ? 'ticketed' : 'pending_ticketing',
            'last_ticketing_date' => trim((string) ($order['last_ticketing_date'] ?? data_get($order, 'option_info.last_ticketing_date', ''))),
            'provider_context' => array_merge($existingContext, [
                'mode' => $mode,
                'order_id' => trim((string) ($order['order_id'] ?? '')),
                'pnr' => trim((string) ($order['pnr'] ?? '')),
            ]),
            'supplier_messages' => [],
        ];
    }

    /**
     * @param  array<string, mixed>  $response
     * @return array<string, mixed>
     */
    public function normalizeRetrieveResponse(array $response, array $existing = []): array
    {
        $data = $this->unwrap($response);
        $status = strtoupper(trim((string) ($data['status'] ?? '')));
        $ticketNumbers = $this->extractTicketNumbers($data);

        $pnr = $this->extractRetrievePnr($data, $existing);
        $lastTicketingDate = trim((string) ($data['last_ticketing_date'] ?? data_get($data, 'option_info.last_ticketing_date', '')));

        return array_filter([
            'order_id' => trim((string) ($data['order_id'] ?? $existing['order_id'] ?? '')),
            'pnr' => $pnr !== '' ? $pnr : null,
            'status' => $status !== '' ? $status : null,
            'ticketing_status' => $status === 'BOOKED' ? 'ticketed' : ($existing['ticketing_status'] ?? null),
            'ticket_numbers' => $ticketNumbers !== [] ? $ticketNumbers : null,
            'airline_locator' => trim((string) ($data['ticketed_office_name'] ?? '')) ?: null,
            'last_ticketing_date' => $lastTicketingDate !== '' ? $lastTicketingDate : null,
            'cancellable' => array_key_exists('cancellable', $data) ? (bool) $data['cancellable'] : null,
        ], fn ($v) => $v !== null && $v !== '');
    }

    /**
     * @param  array<string, mixed>  $response
     * @return array<string, mixed>
     */
    public function normalizeCancelResponse(array $response): array
    {
        $data = $this->unwrap($response);
        $cancelled = array_key_exists('cancelled', $data) ? (bool) $data['cancelled'] : true;

        return [
            'cancellation_status' => $cancelled ? 'cancelled' : 'cancel_failed',
            'refund_status' => null,
            'provider_cancellation_reference' => null,
            'penalty' => null,
            'refundable_amount' => null,
            'supplier_messages' => [trim((string) ($data['description'] ?? ''))],
        ];
    }

    /**
     * @param  array<string, mixed>  $departure
     * @param  array<string, mixed>|null  $return
     * @param  array<string, int>  $paxCounts
     */
    protected function buildItineraryOffer(
        array $departure,
        ?array $return,
        SupplierConnection $connection,
        string $correlationId,
        array $paxCounts,
    ): ?NormalizedFlightOfferData {
        $farePairs = $this->farePairs($departure, $return);
        if ($farePairs === []) {
            return null;
        }

        $departureLegs = $this->itineraryLegs($departure);
        if ($departureLegs === []) {
            return null;
        }

        $returnLegs = $return ? $this->itineraryLegs($return) : [];
        $primaryPair = $farePairs[0];
        $departureFare = $primaryPair['departure'];
        $returnFare = $primaryPair['return'];

        $price = $this->combinedFarePrice($departureFare, $returnFare);
        if ($price['total'] <= 0) {
            return null;
        }

        $segments = $this->normalizeSegments($departureLegs, $departureFare);
        foreach ($returnLegs as $index => $leg) {
            $segments[] = $this->normalizeSegment($leg, $returnFare ?? $departureFare, $index, 'return');
        }

        if ($segments === []) {
            return null;
        }

        $first = $segments[0];
        $last = $segments[count($segments) - 1];
        $stops = max(0, count($departureLegs) - 1);
        $durationMinutes = $this->totalDurationMinutes($departureLegs) + $this->totalDurationMinutes($returnLegs);
        $baggage = $this->baggageFromLeg($departureLegs[0]);
        $refundable = $this->isRefundable($departureFare) || ($returnFare ? $this->isRefundable($returnFare) : false);
        $brandedFares = $this->buildBrandedFaresFromPairs($farePairs, $departureLegs, $return ? $this->itineraryLegs($return) : []);

        $departureFareKey = (string) ($departureFare['fare_key'] ?? '');
        $returnFareKey = $returnFare ? (string) ($returnFare['fare_key'] ?? '') : '';
        $offerId = 'iati_'.substr(md5(json_encode([
            $departure['provider_key'] ?? '',
            $return['provider_key'] ?? '',
            $departureFareKey,
            $returnFareKey,
            $first['departure_at'] ?? '',
        ])), 0, 16);

        $carrierDisplay = NormalizedFlightOfferData::deriveMultiSegmentCarrierDisplay(
            $segments,
            $this->validatingCarrier($departureFare),
            (string) ($first['airline_code'] ?? 'XX'),
        );

        $providerContext = [
            'departure_fare_key' => $departureFareKey,
            'return_fare_key' => $returnFareKey !== '' ? $returnFareKey : null,
            'pax_counts' => $paxCounts,
            'search_correlation_id' => $correlationId,
            'fare_type' => $this->fareType($departureFare),
            'cabin_type' => $this->cabinFromFare($departureFare),
        ];

        $customerDisplayFields = $this->customerDisplayFields($departureFare, $returnFare, $departureLegs, $baggage);

        return new NormalizedFlightOfferData(
            offer_id: $offerId,
            supplier_provider: SupplierProvider::Iati->value,
            supplier_connection_id: $connection->id,
            airline_code: $carrierDisplay['primary_display_carrier'],
            airline_name: $carrierDisplay['headline_airline_name'],
            flight_number: $carrierDisplay['headline_flight_number'],
            origin: (string) ($first['origin'] ?? ''),
            destination: (string) ($last['destination'] ?? ''),
            departure_at: (string) ($first['departure_at'] ?? ''),
            arrival_at: (string) ($last['arrival_at'] ?? ''),
            duration_minutes: $durationMinutes,
            stops: $stops,
            cabin: strtolower($this->cabinFromFare($departureFare)),
            fare_family: $this->brandName($departureFare, $returnFare, 0),
            refundable: $refundable,
            seats_left: null,
            segments: $segments,
            baggage: $baggage,
            fare_breakdown: new FareBreakdownData(
                base_fare: $price['base'],
                taxes: $price['tax'],
                supplier_fees: 0.0,
                supplier_total: $price['total'],
                currency: $price['currency'],
                passenger_pricing: $this->passengerPricing($departureFare, $returnFare),
                passenger_pricing_available: true,
                passenger_counts: $paxCounts,
                breakdown_reconciled: true,
            ),
            raw_reference: $offerId,
            raw_payload: [
                'provider_context' => $providerContext,
                'customer_display_fields' => $customerDisplayFields,
            ],
            marketing_carrier_chain: $carrierDisplay['marketing_carrier_chain'],
            operating_carrier_chain: $carrierDisplay['operating_carrier_chain'],
            validating_carrier: $this->validatingCarrier($departureFare),
            primary_display_carrier: $carrierDisplay['primary_display_carrier'],
            mixed_carrier: $carrierDisplay['mixed_carrier'],
            all_airline_codes: $carrierDisplay['all_airline_codes'],
            branded_fares: $brandedFares,
        );
    }

    /**
     * @param  array<string, mixed>  $response
     * @return array<string, mixed>
     */
    protected function unwrap(array $response): array
    {
        if (isset($response['result']) && is_array($response['result'])) {
            return $response['result'];
        }

        return $response;
    }

    /**
     * @param  array<string, mixed>  $departure
     * @param  array<string, mixed>|null  $return
     * @return list<array{departure: array<string, mixed>, return: array<string, mixed>|null}>
     */
    protected function farePairs(array $departure, ?array $return): array
    {
        $departureFares = array_values(is_array($departure['fares'] ?? null) ? $departure['fares'] : []);
        if ($departureFares === []) {
            return [];
        }

        if ($return === null) {
            return array_map(fn ($fare) => ['departure' => $fare, 'return' => null], $departureFares);
        }

        $returnFares = array_values(is_array($return['fares'] ?? null) ? $return['fares'] : []);
        $pairs = [];
        foreach ($departureFares as $departureFare) {
            foreach ($returnFares as $returnFare) {
                $pairs[] = ['departure' => $departureFare, 'return' => $returnFare];
                if (count($pairs) >= 24) {
                    return $pairs;
                }
            }
        }

        return $pairs;
    }

    /**
     * @param  array<string, mixed>  $itinerary
     * @return list<array<string, mixed>>
     */
    protected function itineraryLegs(array $itinerary): array
    {
        return array_values(is_array($itinerary['legs'] ?? null) ? $itinerary['legs'] : []);
    }

    /**
     * @param  array<string, mixed>  $departure
     * @param  array<string, mixed>  $return
     */
    protected function canCombine(array $departure, array $return): bool
    {
        $departurePackage = is_array($departure['package_info'] ?? null) ? $departure['package_info'] : [];
        $returnPackage = is_array($return['package_info'] ?? null) ? $return['package_info'] : [];
        $departurePackaged = ! empty($departurePackage['packaged']);
        $returnPackaged = ! empty($returnPackage['packaged']);

        if (! $departurePackaged && ! $returnPackaged) {
            return true;
        }

        return (string) ($departurePackage['package_key'] ?? '') === (string) ($returnPackage['package_key'] ?? '');
    }

    /**
     * @param  list<array<string, mixed>>  $legs
     * @param  array<string, mixed>  $fare
     * @return list<array<string, mixed>>
     */
    protected function normalizeSegments(array $legs, array $fare): array
    {
        $segments = [];
        foreach ($legs as $index => $leg) {
            $segments[] = $this->normalizeSegment($leg, $fare, $index, 'outbound');
        }

        return $segments;
    }

    /**
     * @param  array<string, mixed>  $leg
     * @param  array<string, mixed>  $fare
     * @return array<string, mixed>
     */
    protected function normalizeSegment(array $leg, array $fare, int $index, string $direction): array
    {
        $departure = is_array($leg['departure_info'] ?? null) ? $leg['departure_info'] : [];
        $arrival = is_array($leg['arrival_info'] ?? null) ? $leg['arrival_info'] : [];
        $airline = is_array($leg['airline_info'] ?? null) ? $leg['airline_info'] : [];
        $classCodes = is_array(data_get($fare, 'fare_info.class_codes')) ? data_get($fare, 'fare_info.class_codes') : [];
        $cabins = is_array(data_get($fare, 'fare_info.cabin_types')) ? data_get($fare, 'fare_info.cabin_types') : [];

        $carrier = strtoupper(trim((string) ($airline['carrier_code'] ?? $airline['operating_airline_code'] ?? 'XX')));
        $operatingCode = strtoupper(trim((string) ($airline['operating_airline_code'] ?? '')));
        $depAt = $this->parseDateTime((string) ($departure['date'] ?? ''));
        $arrAt = $this->parseDateTime((string) ($arrival['date'] ?? ''));
        $legBaggage = $this->baggageFromLeg($leg);

        return [
            'direction' => $direction,
            'origin' => strtoupper(trim((string) ($departure['airport_code'] ?? ''))),
            'destination' => strtoupper(trim((string) ($arrival['airport_code'] ?? ''))),
            'departure_at' => $depAt,
            'arrival_at' => $arrAt,
            'airline_code' => $carrier,
            'airline_name' => trim((string) ($airline['carrier_name'] ?? $airline['operator_name'] ?? $carrier)),
            'operating_airline_code' => $operatingCode !== '' ? $operatingCode : null,
            'operating_airline_name' => trim((string) ($airline['operator_name'] ?? '')) ?: null,
            'flight_number' => trim((string) ($leg['flight_number'] ?? '')),
            'booking_class' => (string) ($classCodes[$index] ?? $classCodes[0] ?? ''),
            'fare_basis' => $this->fareBasisFromFare($fare, $index),
            'cabin' => strtolower((string) ($cabins[$index] ?? $cabins[0] ?? 'economy')),
            'duration_minutes' => $this->legDurationMinutes($leg),
            'departure_terminal' => trim((string) ($departure['terminal'] ?? '')) ?: null,
            'arrival_terminal' => trim((string) ($arrival['terminal'] ?? '')) ?: null,
            'aircraft' => trim((string) ($leg['aircraft_type'] ?? $leg['equipment'] ?? $leg['aircraft'] ?? '')) ?: null,
            'baggage_checked' => $legBaggage->checked,
            'baggage_cabin' => $legBaggage->cabin,
        ];
    }

    /**
     * @param  array<string, mixed>  $departureFare
     * @param  array<string, mixed>|null  $returnFare
     * @return array{total: float, base: float, tax: float, currency: string}
     */
    protected function combinedFarePrice(array $departureFare, ?array $returnFare): array
    {
        $departure = $this->farePrice($departureFare);
        $return = $returnFare ? $this->farePrice($returnFare) : ['total' => 0.0, 'base' => 0.0, 'tax' => 0.0, 'currency' => $departure['currency']];

        return [
            'total' => $departure['total'] + $return['total'],
            'base' => $departure['base'] + $return['base'],
            'tax' => $departure['tax'] + $return['tax'],
            'currency' => $departure['currency'],
        ];
    }

    /**
     * @param  array<string, mixed>  $fare
     * @return array{total: float, base: float, tax: float, currency: string}
     */
    protected function farePrice(array $fare): array
    {
        $detail = is_array(data_get($fare, 'fare_info.fare_detail')) ? data_get($fare, 'fare_info.fare_detail') : [];
        $priceInfo = is_array($detail['price_info'] ?? null) ? $detail['price_info'] : [];
        $paxFares = is_array($detail['pax_fares'] ?? null) ? $detail['pax_fares'] : [];
        $passengerPricing = [];
        foreach ($paxFares as $paxFare) {
            if (! is_array($paxFare)) {
                continue;
            }
            $rowPrice = is_array($paxFare['price_info'] ?? null) ? $paxFare['price_info'] : [];
            $passengerPricing[] = [
                'currency' => strtoupper((string) ($paxFare['currency_code'] ?? $detail['currency_code'] ?? '')),
                'total' => (float) ($rowPrice['total_fare'] ?? 0),
                'base' => (float) ($rowPrice['base_fare'] ?? 0),
                'tax' => (float) ($rowPrice['tax'] ?? 0),
                'quantity' => (int) ($paxFare['number_of_pax'] ?? $paxFare['count'] ?? 1),
            ];
        }

        return [
            'currency' => IatiFarePricingResolver::resolveCurrency([
                'currency' => strtoupper((string) ($detail['currency_code'] ?? '')),
                'passenger_pricing' => $passengerPricing,
            ]),
            'total' => (float) ($priceInfo['total_fare'] ?? 0),
            'base' => (float) ($priceInfo['base_fare'] ?? 0),
            'tax' => (float) ($priceInfo['tax'] ?? 0),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $offers
     * @return array{total: float, base: float, tax: float, currency: string}
     */
    protected function combinedOfferPrice(array $offers): array
    {
        $total = 0.0;
        $base = 0.0;
        $tax = 0.0;
        $currency = 'PKR';

        foreach ($offers as $offer) {
            if (! is_array($offer)) {
                continue;
            }
            $total += (float) ($offer['total_price'] ?? $offer['price'] ?? 0);
            $base += (float) ($offer['base_price'] ?? 0);
            $tax += (float) ($offer['tax'] ?? 0);
            $currency = strtoupper((string) ($offer['currency'] ?? $offer['currency_code'] ?? $currency));
        }

        return compact('total', 'base', 'tax', 'currency');
    }

    /**
     * @param  array<string, mixed>  $fare
     */
    protected function isRefundable(array $fare): bool
    {
        $rules = is_array($fare['change_rules'] ?? null) ? $fare['change_rules'] : [];
        foreach ($rules as $rule) {
            if (! is_array($rule)) {
                continue;
            }
            if (($rule['type'] ?? '') === 'REFUND' && ($rule['before_departure_status'] ?? '') === 'PERMITTED') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $fare
     * @param  list<array<string, mixed>>  $departureLegs
     * @return array{baggage: ?BaggageAllowanceData, checked_source: ?string, cabin_source: ?string}
     */
    protected function resolveFareBaggage(array $fare, array $departureLegs, int $fareIndex): array
    {
        $fromFareLegs = $this->baggageFromFareLegs($fare);
        if ($fromFareLegs !== null) {
            return [
                'baggage' => $fromFareLegs,
                'checked_source' => 'fare_legs',
                'cabin_source' => 'fare_legs',
            ];
        }

        $fromFareFields = $this->baggageFromFareFields($fare);
        if ($fromFareFields !== null) {
            return [
                'baggage' => $fromFareFields,
                'checked_source' => 'fare_baggages',
                'cabin_source' => 'fare_baggages',
            ];
        }

        $fromDefaultOffer = $this->baggageFromDefaultOffer($fare);
        if ($fromDefaultOffer !== null) {
            return [
                'baggage' => $fromDefaultOffer,
                'checked_source' => 'default_offer',
                'cabin_source' => 'default_offer',
            ];
        }

        $fromAllowances = $this->baggageFromFareDetailAllowances($fare);
        if ($fromAllowances !== null) {
            return [
                'baggage' => $fromAllowances,
                'checked_source' => 'fare_detail_allowances',
                'cabin_source' => 'fare_detail_allowances',
            ];
        }

        $fromPaxFares = $this->baggageFromFarePaxFares($fare);
        if ($fromPaxFares !== null) {
            return [
                'baggage' => $fromPaxFares,
                'checked_source' => 'pax_fares',
                'cabin_source' => 'pax_fares',
            ];
        }

        if ($departureLegs !== []) {
            $leg = $departureLegs[0];
            if (is_array($leg)) {
                $fromLegIndex = $this->baggageFromLegAtFareIndex($leg, $fareIndex);

                return [
                    'baggage' => $fromLegIndex,
                    'checked_source' => $fromLegIndex?->checked !== null ? 'itinerary_leg_index' : null,
                    'cabin_source' => $fromLegIndex?->cabin !== null ? 'itinerary_leg_index' : null,
                ];
            }
        }

        return ['baggage' => null, 'checked_source' => null, 'cabin_source' => null];
    }

    /**
     * @param  array<string, mixed>  $fare
     */
    protected function baggageFromFareLegs(array $fare): ?BaggageAllowanceData
    {
        $fareLegs = is_array($fare['legs'] ?? null) ? $fare['legs'] : [];
        if ($fareLegs === []) {
            return null;
        }

        $firstLeg = is_array($fareLegs[0] ?? null) ? $fareLegs[0] : null;
        if ($firstLeg === null) {
            return null;
        }

        return $this->baggageFromLeg($firstLeg);
    }

    /**
     * @param  array<string, mixed>  $fare
     */
    protected function baggageFromFareFields(array $fare): ?BaggageAllowanceData
    {
        return $this->makeBaggageAllowance(
            $this->baggageLabel($fare['baggages'] ?? []),
            $this->baggageLabel($fare['cabin_baggages'] ?? []),
        );
    }

    /**
     * @param  array<string, mixed>  $fare
     */
    protected function baggageFromDefaultOffer(array $fare): ?BaggageAllowanceData
    {
        $defaultOffer = is_array($fare['default_offer'] ?? null) ? $fare['default_offer'] : [];
        if ($defaultOffer === []) {
            return null;
        }

        return $this->makeBaggageAllowance(
            $this->baggageLabel($defaultOffer['baggages'] ?? []),
            $this->baggageLabel($defaultOffer['cabin_baggages'] ?? []),
        );
    }

    /**
     * @param  array<string, mixed>  $fare
     */
    protected function baggageFromFareDetailAllowances(array $fare): ?BaggageAllowanceData
    {
        $allowances = data_get($fare, 'fare_info.fare_detail.baggage_allowances');
        if (! is_array($allowances) || $allowances === []) {
            return null;
        }

        $checkedItems = [];
        $cabinItems = [];
        foreach ($allowances as $row) {
            if (! is_array($row)) {
                continue;
            }
            $type = strtoupper(trim((string) ($row['type'] ?? $row['baggage_type'] ?? '')));
            $item = isset($row['amount'])
                ? ['amount' => $row['amount'], 'unit' => $row['unit'] ?? 'kg']
                : $row;
            if (in_array($type, ['CHECKED', 'CHECK_IN', 'HOLD', 'CHECKED_BAGGAGE'], true)) {
                $checkedItems[] = $item;
            } elseif (in_array($type, ['CABIN', 'CARRY_ON', 'HAND', 'CABIN_BAGGAGE'], true)) {
                $cabinItems[] = $item;
            }
        }

        return $this->makeBaggageAllowance(
            $this->baggageLabel($checkedItems),
            $this->baggageLabel($cabinItems),
        );
    }

    /**
     * @param  array<string, mixed>  $fare
     */
    protected function baggageFromFarePaxFares(array $fare): ?BaggageAllowanceData
    {
        $detail = is_array(data_get($fare, 'fare_info.fare_detail')) ? data_get($fare, 'fare_info.fare_detail') : [];
        $paxFares = is_array($detail['pax_fares'] ?? null) ? $detail['pax_fares'] : [];
        if ($paxFares === []) {
            return null;
        }

        $checked = null;
        $cabin = null;
        foreach ($paxFares as $paxFare) {
            if (! is_array($paxFare)) {
                continue;
            }
            $baggage = is_array($paxFare['baggage'] ?? null) ? $paxFare['baggage'] : [];
            $checked ??= $this->baggageLabel($baggage['checked'] ?? $baggage['baggages'] ?? []);
            $cabin ??= $this->baggageLabel($baggage['cabin'] ?? $baggage['cabin_baggages'] ?? []);
            if ($checked !== null && $cabin !== null) {
                break;
            }
        }

        return $this->makeBaggageAllowance($checked, $cabin);
    }

    /**
     * Pick one itinerary-leg baggage row per branded fare index (IATI aggregates all fares on the leg).
     *
     * @param  array<string, mixed>  $leg
     */
    protected function baggageFromLegAtFareIndex(array $leg, int $fareIndex): ?BaggageAllowanceData
    {
        return $this->makeBaggageAllowance(
            $this->baggageLabelAtIndex($leg['baggages'] ?? [], $fareIndex),
            $this->baggageLabelAtIndex($leg['cabin_baggages'] ?? [], $fareIndex),
        );
    }

    protected function makeBaggageAllowance(?string $checked, ?string $cabin): ?BaggageAllowanceData
    {
        if ($checked === null && $cabin === null) {
            return null;
        }

        return new BaggageAllowanceData(
            checked: $checked,
            cabin: $cabin,
            summary: BaggageDisplayNormalizer::formatAllowance($checked, $cabin)['summary'],
        );
    }

    /**
     * @param  array<string, mixed>  $leg
     */
    protected function baggageFromLeg(array $leg): BaggageAllowanceData
    {
        $checked = $this->baggageLabel($leg['baggages'] ?? []);
        $cabin = $this->baggageLabel($leg['cabin_baggages'] ?? []);

        return new BaggageAllowanceData(
            checked: $checked,
            cabin: $cabin,
            summary: BaggageDisplayNormalizer::formatAllowance($checked, $cabin)['summary'],
        );
    }

    protected function baggageLabelAtIndex(mixed $items, int $index): ?string
    {
        if (! is_array($items) || $items === []) {
            return null;
        }

        if (count($items) === 1) {
            return $this->baggageLabel($items);
        }

        $item = $items[$index] ?? null;
        if (! is_array($item)) {
            return null;
        }

        return $this->baggageLabel([$item]);
    }

    protected function baggageLabel(mixed $items): ?string
    {
        if (! is_array($items) || $items === []) {
            return null;
        }

        return BaggageDisplayNormalizer::labelsFromSupplierItems($items);
    }

    /**
     * @param  list<array<string, mixed>>  $legs
     */
    protected function totalDurationMinutes(array $legs): int
    {
        $total = 0;
        foreach ($legs as $leg) {
            $total += $this->legDurationMinutes($leg);
        }

        return $total;
    }

    /**
     * @param  array<string, mixed>  $leg
     */
    protected function legDurationMinutes(array $leg): int
    {
        $minutes = (int) ($leg['duration'] ?? $leg['duration_minutes'] ?? 0);
        if ($minutes > 0) {
            return $minutes;
        }

        $dep = $this->parseDateTime((string) data_get($leg, 'departure_info.date', ''));
        $arr = $this->parseDateTime((string) data_get($leg, 'arrival_info.date', ''));
        if ($dep === '' || $arr === '') {
            return 0;
        }

        try {
            return max(0, (int) Carbon::parse($dep)->diffInMinutes(Carbon::parse($arr)));
        } catch (\Throwable) {
            return 0;
        }
    }

    protected function parseDateTime(string $value): string
    {
        if (trim($value) === '') {
            return '';
        }

        try {
            return Carbon::parse($value)->toIso8601String();
        } catch (\Throwable) {
            return trim($value);
        }
    }

    /**
     * @param  array<string, mixed>  $fare
     */
    protected function validatingCarrier(array $fare): ?string
    {
        $carrier = strtoupper(trim((string) ($fare['validating_carrier'] ?? data_get($fare, 'fare_info.validating_carrier', ''))));

        return $carrier !== '' ? $carrier : null;
    }

    /**
     * @param  array<string, mixed>  $fare
     */
    protected function cabinFromFare(array $fare): string
    {
        $cabins = is_array(data_get($fare, 'fare_info.cabin_types')) ? data_get($fare, 'fare_info.cabin_types') : [];
        $first = strtoupper(trim((string) ($cabins[0] ?? 'ECONOMY')));

        return $first !== '' ? $first : 'ECONOMY';
    }

    /**
     * @param  array<string, mixed>  $fare
     */
    protected function fareType(array $fare): string
    {
        return trim((string) ($fare['fare_type'] ?? data_get($fare, 'fare_info.fare_type', '')));
    }

    /**
     * @param  array<string, mixed>  $departureFare
     * @param  array<string, mixed>|null  $returnFare
     */
    protected function brandName(array $departureFare, ?array $returnFare, int $index): string
    {
        $name = trim((string) data_get($departureFare, 'default_offer.brand_name', ''));
        if ($name !== '') {
            return $name;
        }

        return 'IATI Fare '.($index + 1);
    }

    /**
     * @param  array<string, mixed>  $departureFare
     * @param  array<string, mixed>|null  $returnFare
     * @return list<array<string, mixed>>
     */
    protected function passengerPricing(array $departureFare, ?array $returnFare): array
    {
        $rows = [];
        foreach ([$departureFare, $returnFare] as $fare) {
            if (! is_array($fare)) {
                continue;
            }
            $detail = is_array(data_get($fare, 'fare_info.fare_detail')) ? data_get($fare, 'fare_info.fare_detail') : [];
            $paxFares = is_array($detail['pax_fares'] ?? null) ? $detail['pax_fares'] : [];
            foreach ($paxFares as $paxFare) {
                if (! is_array($paxFare)) {
                    continue;
                }
                $priceInfo = is_array($paxFare['price_info'] ?? null) ? $paxFare['price_info'] : [];
                $rows[] = [
                    'type' => strtolower((string) ($paxFare['pax_type'] ?? $paxFare['type'] ?? 'adult')),
                    'quantity' => (int) ($paxFare['number_of_pax'] ?? $paxFare['count'] ?? 1),
                    'total' => (float) ($priceInfo['total_fare'] ?? 0),
                    'base' => (float) ($priceInfo['base_fare'] ?? 0),
                    'tax' => (float) ($priceInfo['tax'] ?? 0),
                    'currency' => IatiFarePricingResolver::resolveCurrency([
                        'currency' => strtoupper((string) ($detail['currency_code'] ?? '')),
                        'passenger_pricing' => [[
                            'currency' => strtoupper((string) ($paxFare['currency_code'] ?? $detail['currency_code'] ?? '')),
                        ]],
                    ]),
                ];
            }
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $data
     */
    protected function firstOrder(array $data, string $mode): array
    {
        if ($mode === 'option') {
            $options = array_values(is_array($data['options'] ?? null) ? $data['options'] : []);
            if ($options === [] && is_array($data['option'] ?? null)) {
                $options[] = $data['option'];
            }

            return is_array($options[0] ?? null) ? $options[0] : [];
        }

        $books = array_values(is_array($data['books'] ?? null) ? $data['books'] : []);

        return is_array($books[0] ?? null) ? $books[0] : [];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    protected function extractTicketNumbers(array $data): array
    {
        $numbers = [];
        foreach (data_get($data, 'booking_info.tickets', data_get($data, 'tickets', [])) as $ticket) {
            if (! is_array($ticket)) {
                continue;
            }
            $no = trim((string) ($ticket['ticket_number'] ?? $ticket['number'] ?? ''));
            if ($no !== '') {
                $numbers[] = $no;
            }
        }

        return array_values(array_unique($numbers));
    }

    /**
     * @param  list<array{departure: array<string, mixed>, return: array<string, mixed>|null}>  $farePairs
     * @param  list<array<string, mixed>>  $departureLegs
     * @param  list<array<string, mixed>>  $returnLegs
     * @return list<array<string, mixed>>
     */
    protected function buildBrandedFaresFromPairs(array $farePairs, array $departureLegs, array $returnLegs): array
    {
        if (count($farePairs) < 2) {
            return [];
        }

        $brandedFares = [];
        foreach (array_slice($farePairs, 0, 12) as $index => $pair) {
            $brandPrice = $this->combinedFarePrice($pair['departure'], $pair['return']);
            if ($brandPrice['total'] <= 0) {
                continue;
            }

            $baggageResolved = $this->resolveFareBaggage($pair['departure'], $departureLegs, (int) $index);
            $departureBaggage = $baggageResolved['baggage'];
            $changeRules = array_values(array_unique(array_merge(
                $this->fareRulesService->extractFromFare($pair['departure']),
                $pair['return'] ? $this->fareRulesService->extractFromFare($pair['return']) : [],
            )));

            $brandedFares[] = array_filter([
                'id' => 'iati_brand_'.$index,
                'name' => $this->brandName($pair['departure'], $pair['return'], $index),
                'brand_name' => $this->brandName($pair['departure'], $pair['return'], $index),
                'brand_code' => $this->brandCode($pair['departure'], $pair['return']),
                'price' => $brandPrice['total'],
                'price_total' => $brandPrice['total'],
                'currency' => $brandPrice['currency'],
                'refundable' => $this->isRefundable($pair['departure']) || ($pair['return'] ? $this->isRefundable($pair['return']) : false),
                'departure_fare_key' => (string) ($pair['departure']['fare_key'] ?? ''),
                'return_fare_key' => $pair['return'] ? (string) ($pair['return']['fare_key'] ?? '') : null,
                'baggage' => $departureBaggage?->summary,
                'baggage_summary' => $departureBaggage?->summary,
                'check_in_summary' => $departureBaggage?->checked,
                'carry_on_summary' => $departureBaggage?->cabin,
                'checked_baggage_source' => $baggageResolved['checked_source'],
                'cabin_baggage_source' => $baggageResolved['cabin_source'],
                'cabin' => strtolower($this->cabinFromFare($pair['departure'])),
                'booking_class' => $this->primaryBookingClass($pair['departure']),
                'fare_basis' => $this->fareBasisFromFare($pair['departure'], 0),
                'modification_rule' => $this->changeRuleSummary($pair['departure'], $pair['return'], 'CHANGE'),
                'cancellation_rule' => $this->changeRuleSummary($pair['departure'], $pair['return'], 'REFUND'),
                'refund_rule' => $this->changeRuleSummary($pair['departure'], $pair['return'], 'REFUND'),
            ], fn ($v) => $v !== null && $v !== '');
        }

        return $brandedFares;
    }

    /**
     * @param  array<string, mixed>  $departureFare
     * @param  array<string, mixed>|null  $returnFare
     * @param  list<array<string, mixed>>  $departureLegs
     * @return array<string, mixed>
     */
    protected function customerDisplayFields(
        array $departureFare,
        ?array $returnFare,
        array $departureLegs,
        BaggageAllowanceData $baggage,
    ): array {
        $ruleLines = array_values(array_unique(array_merge(
            $this->fareRulesService->extractFromFare($departureFare),
            $returnFare ? $this->fareRulesService->extractFromFare($returnFare) : [],
        )));

        $segmentBaggage = [];
        foreach ($departureLegs as $legIndex => $leg) {
            if (! is_array($leg)) {
                continue;
            }
            $legBag = $this->baggageFromLeg($leg);
            $segmentBaggage[] = array_filter([
                'segment_index' => $legIndex,
                'route' => trim((string) (($leg['origin'] ?? '').' → '.($leg['destination'] ?? '')), ' →'),
                'checked' => $legBag->checked,
                'cabin' => $legBag->cabin,
            ], fn ($v) => $v !== null && $v !== '');
        }

        return array_filter([
            'refund_rule' => $this->changeRuleSummary($departureFare, $returnFare, 'REFUND'),
            'change_rule' => $this->changeRuleSummary($departureFare, $returnFare, 'CHANGE'),
            'fare_basis' => $this->fareBasisFromFare($departureFare, 0),
            'booking_class' => $this->primaryBookingClass($departureFare),
            'baggage_lines' => array_values(array_filter([
                $baggage->checked !== null ? 'Checked: '.$baggage->checked : null,
                $baggage->cabin !== null ? 'Cabin: '.$baggage->cabin : null,
            ])),
            'fare_rule_lines' => $ruleLines !== [] ? $ruleLines : null,
            'segment_baggage' => $segmentBaggage !== [] ? $segmentBaggage : null,
            'passenger_baggage' => $this->passengerBaggageFromFare($departureFare, $returnFare),
        ], fn ($v) => $v !== null && $v !== '' && $v !== []);
    }

    /**
     * @param  array<string, mixed>  $fare
     */
    protected function fareBasisFromFare(array $fare, int $index): ?string
    {
        $codes = is_array(data_get($fare, 'fare_info.fare_basis_codes'))
            ? data_get($fare, 'fare_info.fare_basis_codes')
            : (is_array(data_get($fare, 'fare_info.fare_basis')) ? data_get($fare, 'fare_info.fare_basis') : []);
        $value = trim((string) ($codes[$index] ?? $codes[0] ?? data_get($fare, 'fare_info.fare_basis_code', '')));

        return $value !== '' ? $value : null;
    }

    /**
     * @param  array<string, mixed>  $fare
     */
    protected function primaryBookingClass(array $fare): ?string
    {
        $codes = is_array(data_get($fare, 'fare_info.class_codes')) ? data_get($fare, 'fare_info.class_codes') : [];
        $value = trim((string) ($codes[0] ?? ''));

        return $value !== '' ? $value : null;
    }

    /**
     * @param  array<string, mixed>  $departureFare
     * @param  array<string, mixed>|null  $returnFare
     */
    protected function brandCode(array $departureFare, ?array $returnFare): ?string
    {
        $code = trim((string) data_get($departureFare, 'default_offer.brand_code', ''));
        if ($code === '' && $returnFare !== null) {
            $code = trim((string) data_get($returnFare, 'default_offer.brand_code', ''));
        }

        return $code !== '' ? $code : null;
    }

    /**
     * @param  array<string, mixed>  $departureFare
     * @param  array<string, mixed>|null  $returnFare
     */
    protected function changeRuleSummary(array $departureFare, ?array $returnFare, string $type): ?string
    {
        $labels = [];
        foreach ([$departureFare, $returnFare] as $fare) {
            if (! is_array($fare)) {
                continue;
            }
            foreach ($this->fareRulesService->extractFromFare($fare) as $label) {
                if (str_starts_with(strtoupper($label), strtoupper($type))) {
                    $labels[] = $label;
                }
            }
        }
        $labels = array_values(array_unique($labels));

        return $labels !== [] ? implode('; ', $labels) : null;
    }

    /**
     * @param  array<string, mixed>  $departureFare
     * @param  array<string, mixed>|null  $returnFare
     * @return list<array<string, mixed>>
     */
    protected function passengerBaggageFromFare(array $departureFare, ?array $returnFare): array
    {
        $rows = [];
        foreach ([$departureFare, $returnFare] as $fare) {
            if (! is_array($fare)) {
                continue;
            }
            $detail = is_array(data_get($fare, 'fare_info.fare_detail')) ? data_get($fare, 'fare_info.fare_detail') : [];
            $paxFares = is_array($detail['pax_fares'] ?? null) ? $detail['pax_fares'] : [];
            foreach ($paxFares as $paxFare) {
                if (! is_array($paxFare)) {
                    continue;
                }
                $type = strtolower((string) ($paxFare['pax_type'] ?? $paxFare['type'] ?? 'adult'));
                $baggage = is_array($paxFare['baggage'] ?? null) ? $paxFare['baggage'] : [];
                $checked = $this->baggageLabel($baggage['checked'] ?? $baggage['baggages'] ?? []);
                $cabin = $this->baggageLabel($baggage['cabin'] ?? $baggage['cabin_baggages'] ?? []);
                if ($checked === null && $cabin === null) {
                    continue;
                }
                $rows[] = array_filter([
                    'passenger_type' => $type,
                    'checked' => $checked,
                    'cabin' => $cabin,
                ], fn ($v) => $v !== null && $v !== '');
            }
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function privateSummary(array $data): array
    {
        return [
            'fare_detail_key_present' => ! empty($data['fare_detail_key']),
            'offers_count' => is_array($data['offers'] ?? null) ? count($data['offers']) : 0,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $offers
     * @return list<array<string, mixed>>
     */
    protected function summarizeFareOffers(array $offers): array
    {
        $summaries = [];
        foreach ($offers as $index => $offer) {
            if (! is_array($offer)) {
                continue;
            }
            $offerKey = trim((string) ($offer['offer_key'] ?? ''));
            if ($offerKey === '') {
                continue;
            }

            $summaries[] = array_filter([
                'index' => $index,
                'offer_key' => $offerKey,
                'total_price' => isset($offer['total_price']) ? (float) $offer['total_price'] : (isset($offer['price']) ? (float) $offer['price'] : null),
                'currency' => strtoupper(trim((string) ($offer['currency'] ?? $offer['currency_code'] ?? ''))) ?: null,
                'baggage_summary' => $this->fareOfferBaggageSummary($offer),
                'can_book' => array_key_exists('can_book', $offer) ? (bool) $offer['can_book'] : null,
                'can_rezerve' => array_key_exists('can_rezerve', $offer) ? (bool) $offer['can_rezerve'] : null,
                'can_reserve' => array_key_exists('can_reserve', $offer) ? (bool) $offer['can_reserve'] : null,
                'default_offer' => array_key_exists('default_offer', $offer) ? (bool) $offer['default_offer'] : null,
                'fare_type' => trim((string) ($offer['fare_type'] ?? $offer['brand_name'] ?? '')) ?: null,
            ], fn ($value) => $value !== null && $value !== '');
        }

        return $summaries;
    }

    /**
     * @param  array<string, mixed>  $offer
     */
    protected function fareOfferBaggageSummary(array $offer): ?string
    {
        $checked = $this->baggageLabel($offer['baggages'] ?? []);
        $cabin = $this->baggageLabel($offer['cabin_baggages'] ?? []);

        if ($checked === null && $cabin === null) {
            return null;
        }

        return BaggageDisplayNormalizer::formatAllowance($checked, $cabin)['summary'] ?: null;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $existing
     */
    protected function extractRetrievePnr(array $data, array $existing): string
    {
        $candidates = [
            data_get($data, 'booking_info.pnr'),
            data_get($data, 'option_info.pnr'),
            $data['pnr'] ?? null,
        ];

        foreach (is_array($data['legs'] ?? null) ? $data['legs'] : [] as $leg) {
            if (! is_array($leg)) {
                continue;
            }
            $candidates[] = $leg['airline_pnr'] ?? null;
            foreach (is_array($leg['segments'] ?? null) ? $leg['segments'] : [] as $segment) {
                if (is_array($segment)) {
                    $candidates[] = $segment['airline_pnr'] ?? null;
                }
            }
        }

        $candidates[] = $existing['pnr'] ?? null;

        foreach ($candidates as $candidate) {
            $pnr = $this->normalizePnr((string) ($candidate ?? ''));
            if ($pnr !== '') {
                return $pnr;
            }
        }

        return '';
    }

    protected function normalizePnr(string $pnr): string
    {
        $normalized = strtoupper(trim($pnr));

        return $normalized === '' ? '' : substr($normalized, 0, 32);
    }
}
