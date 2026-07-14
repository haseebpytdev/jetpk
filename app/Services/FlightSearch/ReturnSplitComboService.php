<?php

namespace App\Services\FlightSearch;

use App\Support\FlightSearch\AirlineDisplayNameResolver;
use App\Support\FlightSearch\FlightOfferDisplayPresenter;
use App\Support\Suppliers\SupplierSourcePresenter;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * RETURN-SPLIT-SELECT-R1: indexes round-trip search offers into outbound/return leg keys
 * for a two-step selection UI while preserving one complete offer per combo for checkout.
 */
class ReturnSplitComboService
{
    /**
     * @param  array<string, mixed>  $criteria
     * @param  list<array<string, mixed>>  $offers
     * @return array<string, mixed>
     */
    public function buildIndex(array $criteria, array $offers): array
    {
        $tripType = (string) ($criteria['trip_type'] ?? 'one_way');
        if ($tripType !== 'round_trip' || ! $this->isEnabled()) {
            return $this->emptyIndex();
        }

        $reqO = strtoupper(trim((string) ($criteria['origin'] ?? '')));
        $reqD = strtoupper(trim((string) ($criteria['destination'] ?? '')));
        if ($reqO === '' || $reqD === '') {
            return $this->emptyIndex();
        }

        $combos = [];
        $excludedCount = 0;

        foreach ($offers as $offer) {
            if (! is_array($offer)) {
                continue;
            }

            $combo = $this->normalizeCombo($offer, $criteria, $reqO, $reqD);
            if ($combo === null) {
                $excludedCount++;

                continue;
            }

            $combos[] = $combo;
        }

        return [
            'prepared_at' => now()->toIso8601String(),
            'combo_count' => count($combos),
            'excluded_count' => $excludedCount,
            'combos' => $combos,
        ];
    }

    /**
     * @param  array<string, mixed>  $index
     * @param  list<array<string, mixed>>  $offers  Filtered offers (post client filters)
     * @param  array<string, mixed>  $criteria
     * @param  array<string, string|null>  $airlineLogos
     * @param  array<string, string>  $cityMap
     * @param  array<string, string>  $airlineNameMap
     * @return list<array<string, mixed>>
     */
    public function buildOutboundOptions(
        array $index,
        array $offers,
        array $criteria,
        array $airlineLogos,
        array $cityMap,
        array $airlineNameMap,
        string $searchId,
    ): array {
        $allowedComboIds = $this->comboIdsFromOffers($offers);
        $groups = [];

        foreach ($index['combos'] ?? [] as $combo) {
            if (! is_array($combo)) {
                continue;
            }
            $comboId = (string) ($combo['combo_id'] ?? '');
            if ($comboId === '' || ! isset($allowedComboIds[$comboId])) {
                continue;
            }

            $outKey = (string) ($combo['outbound_key'] ?? '');
            if ($outKey === '') {
                continue;
            }

            if (! isset($groups[$outKey])) {
                $groups[$outKey] = [
                    'outbound_key' => $outKey,
                    'combo_ids' => [],
                    'from_total_amount' => null,
                    'cheapest_combo_id' => null,
                ];
            }

            $groups[$outKey]['combo_ids'][] = $comboId;
            $amount = $this->comboFinalPrice($combo);
            if ($amount !== null && ($groups[$outKey]['from_total_amount'] === null || $amount < $groups[$outKey]['from_total_amount'])) {
                $groups[$outKey]['from_total_amount'] = $amount;
                $groups[$outKey]['cheapest_combo_id'] = $comboId;
            }
        }

        $options = [];
        foreach ($groups as $group) {
            $cheapestId = (string) ($group['cheapest_combo_id'] ?? '');
            $sampleOffer = $this->findOfferInList($offers, $cheapestId);
            if ($sampleOffer === null) {
                continue;
            }

            $presentation = FlightOfferDisplayPresenter::buildPresentation($sampleOffer, $criteria, $cityMap, $airlineNameMap);
            $journeys = is_array($presentation['journeys_display'] ?? null) ? $presentation['journeys_display'] : [];
            $outJourney = $journeys[0] ?? null;
            if (! is_array($outJourney)) {
                continue;
            }

            $code = strtoupper((string) ($sampleOffer['airline_code'] ?? ($sampleOffer['carrier_code'] ?? '')));
            $fromAmount = $group['from_total_amount'];
            $fromDisplay = $fromAmount !== null && $fromAmount > 0
                ? 'PKR '.number_format((float) $fromAmount, 0)
                : 'Fare unavailable';

            $options[] = array_merge([
                'outbound_key' => (string) $group['outbound_key'],
                'journey_display' => $outJourney,
                'airline_code' => $code,
                'airline_name' => AirlineDisplayNameResolver::resolveForOffer($sampleOffer, $airlineNameMap),
                'airline_logo_url' => $airlineLogos[$code] ?? null,
                'from_total_amount' => $fromAmount,
                'from_total_display' => $fromDisplay,
                'combo_count' => count($group['combo_ids'] ?? []),
                'cabin' => (string) ($sampleOffer['cabin'] ?? ''),
                'fare_family' => (string) ($sampleOffer['fare_family'] ?? ''),
                'return_options_url' => route('flights.return-options', [
                    'search_id' => $searchId,
                    'outbound_key' => (string) $group['outbound_key'],
                ]),
            ], $this->mapSplitOptionFields($sampleOffer, $criteria, $cheapestId, $cityMap, $airlineNameMap), [
                'offer_id' => (string) $group['outbound_key'],
            ]);
        }

        usort($options, function (array $a, array $b): int {
            $pa = $a['from_total_amount'] ?? PHP_FLOAT_MAX;
            $pb = $b['from_total_amount'] ?? PHP_FLOAT_MAX;
            if ($pa === $pb) {
                return strcmp((string) ($a['outbound_key'] ?? ''), (string) ($b['outbound_key'] ?? ''));
            }

            return $pa <=> $pb;
        });

        return array_values($options);
    }

    /**
     * @param  array<string, mixed>  $index
     * @param  list<array<string, mixed>>  $offers
     * @param  array<string, mixed>  $criteria
     * @param  array<string, string|null>  $airlineLogos
     * @param  array<string, string>  $cityMap
     * @param  array<string, string>  $airlineNameMap
     * @return array{options: list<array<string, mixed>>, outbound_journey: array<string, mixed>|null, cheapest_total: float|null}
     */
    public function buildReturnOptions(
        array $index,
        string $outboundKey,
        array $offers,
        array $criteria,
        array $airlineLogos,
        array $cityMap,
        array $airlineNameMap,
        string $searchId,
    ): array {
        $outboundKey = trim($outboundKey);
        $cheapestTotal = null;
        $outboundJourney = null;
        $rows = [];

        foreach ($index['combos'] ?? [] as $combo) {
            if (! is_array($combo)) {
                continue;
            }
            if ((string) ($combo['outbound_key'] ?? '') !== $outboundKey) {
                continue;
            }

            $comboId = (string) ($combo['combo_id'] ?? '');
            $offer = $this->findOfferInList($offers, $comboId);
            if ($offer === null) {
                continue;
            }

            $total = $this->comboFinalPrice($combo);
            if ($total !== null && ($cheapestTotal === null || $total < $cheapestTotal)) {
                $cheapestTotal = $total;
            }

            $presentation = FlightOfferDisplayPresenter::buildPresentation($offer, $criteria, $cityMap, $airlineNameMap);
            $journeys = is_array($presentation['journeys_display'] ?? null) ? $presentation['journeys_display'] : [];
            if ($outboundJourney === null && is_array($journeys[0] ?? null)) {
                $outboundJourney = $journeys[0];
            }
            $returnJourney = is_array($journeys[1] ?? null) ? $journeys[1] : null;
            if ($returnJourney === null) {
                continue;
            }

            $code = strtoupper((string) ($offer['airline_code'] ?? ($offer['carrier_code'] ?? '')));
            $priceDisplay = $total !== null && $total > 0
                ? 'PKR '.number_format((float) $total, 0)
                : 'Fare unavailable';

            $fareDeltaDisplay = null;
            if ($total !== null && $cheapestTotal !== null && $total > $cheapestTotal) {
                $fareDeltaDisplay = '+'.number_format((float) ($total - $cheapestTotal), 0).' PKR vs cheapest';
            }

            $rows[] = array_merge([
                'combo_id' => $comboId,
                'return_key' => (string) ($combo['return_key'] ?? ''),
                'journey_display' => $returnJourney,
                'airline_code' => $code,
                'airline_name' => AirlineDisplayNameResolver::resolveForOffer($offer, $airlineNameMap),
                'airline_logo_url' => $airlineLogos[$code] ?? null,
                'total_amount' => $total,
                'total_display' => $priceDisplay,
                'fare_delta_display' => $fareDeltaDisplay,
                'cabin' => (string) ($offer['cabin'] ?? ''),
                'fare_family' => (string) ($offer['fare_family'] ?? ''),
                'validating_carrier' => (string) ($combo['validating_carrier'] ?? ($offer['validating_carrier'] ?? '')),
                'can_book' => $total !== null && $total > 0,
                'select_combo_url' => route('flights.select-return-combo'),
            ], $this->mapSplitOptionFields($offer, $criteria, $comboId, $cityMap, $airlineNameMap));
        }

        // Recompute fare deltas now that cheapest is known
        foreach ($rows as $idx => $row) {
            $total = $row['total_amount'] ?? null;
            if ($total !== null && $cheapestTotal !== null && $total > $cheapestTotal) {
                $rows[$idx]['fare_delta_display'] = '+'.number_format((float) ($total - $cheapestTotal), 0).' PKR vs cheapest';
            } elseif ($total !== null && $cheapestTotal !== null && abs($total - $cheapestTotal) < 0.01) {
                $rows[$idx]['fare_delta_display'] = null;
            }
        }

        usort($rows, function (array $a, array $b): int {
            $pa = $a['total_amount'] ?? PHP_FLOAT_MAX;
            $pb = $b['total_amount'] ?? PHP_FLOAT_MAX;
            if ($pa === $pb) {
                return strcmp((string) ($a['return_key'] ?? ''), (string) ($b['return_key'] ?? ''));
            }

            return $pa <=> $pb;
        });

        return [
            'options' => array_values($rows),
            'outbound_journey' => $outboundJourney,
            'cheapest_total' => $cheapestTotal,
        ];
    }

    public function isEnabled(): bool
    {
        return (bool) config('ota.return_split_select_enabled', true);
    }

    public function indexIsUsable(?array $index): bool
    {
        return is_array($index)
            && (int) ($index['combo_count'] ?? 0) > 0
            && is_array($index['combos'] ?? null);
    }

    /**
     * @param  array<string, mixed>  $index
     */
    public function outboundKeyExists(array $index, string $outboundKey): bool
    {
        $outboundKey = trim($outboundKey);
        if ($outboundKey === '') {
            return false;
        }

        foreach ($index['combos'] ?? [] as $combo) {
            if (is_array($combo) && (string) ($combo['outbound_key'] ?? '') === $outboundKey) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $offer
     * @return array<string, mixed>|null
     */
    protected function normalizeCombo(array $offer, array $criteria, string $reqO, string $reqD): ?array
    {
        $comboId = (string) ($offer['id'] ?? $offer['offer_id'] ?? '');
        if ($comboId === '') {
            return null;
        }

        $segments = is_array($offer['segments'] ?? null) ? array_values($offer['segments']) : [];
        $split = FlightOfferDisplayPresenter::splitRoundTripSegments($segments, $reqO, $reqD);
        if ($split === null) {
            return null;
        }

        $outboundSegs = is_array($split['outbound'] ?? null) ? $split['outbound'] : [];
        $returnSegs = is_array($split['return'] ?? null) ? $split['return'] : [];
        if ($outboundSegs === [] || $returnSegs === []) {
            return null;
        }

        if (! $this->passesSameCarrierGate($outboundSegs, $returnSegs, $offer)) {
            return null;
        }

        $outboundKey = $this->buildLegKey($outboundSegs, $offer);
        $returnKey = $this->buildLegKey($returnSegs, $offer);
        if ($outboundKey === '' || $returnKey === '') {
            return null;
        }

        $final = (float) ($offer['final_customer_price'] ?? 0);
        $baseFare = (float) ($offer['base_fare'] ?? 0);
        $taxes = (float) ($offer['taxes'] ?? 0);

        return [
            'combo_id' => $comboId,
            'outbound_key' => $outboundKey,
            'return_key' => $returnKey,
            'outbound_segments' => $outboundSegs,
            'return_segments' => $returnSegs,
            'total' => $final > 0 ? $final : ($baseFare + $taxes),
            'base_fare' => $baseFare,
            'taxes' => $taxes,
            'currency' => (string) ($offer['currency'] ?? 'PKR'),
            'final_customer_price' => $final,
            'cabin' => (string) ($offer['cabin'] ?? ''),
            'fare_family' => (string) ($offer['fare_family'] ?? ''),
            'validating_carrier' => (string) ($offer['validating_carrier'] ?? ''),
            'marketing_carrier_chain' => is_array($offer['marketing_carrier_chain'] ?? null) ? $offer['marketing_carrier_chain'] : [],
            'operating_carrier_chain' => is_array($offer['operating_carrier_chain'] ?? null) ? $offer['operating_carrier_chain'] : [],
            'supplier_provider' => (string) ($offer['supplier_provider'] ?? ''),
            'expires_at' => $offer['expires_at'] ?? null,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $legSegments
     * @param  array<string, mixed>  $offer
     */
    protected function buildLegKey(array $legSegments, array $offer): string
    {
        $parts = [];
        foreach ($legSegments as $seg) {
            if (! is_array($seg)) {
                return '';
            }

            $carrier = $this->segmentMarketingCarrier($seg, $offer);
            $flightNumber = trim((string) ($seg['flight_number'] ?? ''));
            $departureAt = trim((string) ($seg['departure_at'] ?? ''));
            $origin = strtoupper(trim((string) ($seg['origin'] ?? '')));
            $destination = strtoupper(trim((string) ($seg['destination'] ?? '')));
            $bookingClass = strtoupper(trim((string) ($seg['booking_class'] ?? '')));

            if ($carrier === '' || $flightNumber === '' || $departureAt === '' || $origin === '' || $destination === '') {
                return '';
            }

            $parts[] = implode('|', [$carrier, $flightNumber, $departureAt, $origin, $destination, $bookingClass]);
        }

        if ($parts === []) {
            return '';
        }

        return hash('sha256', implode('::', $parts));
    }

    /**
     * @param  list<array<string, mixed>>  $outboundSegs
     * @param  list<array<string, mixed>>  $returnSegs
     * @param  array<string, mixed>  $offer
     */
    protected function passesSameCarrierGate(array $outboundSegs, array $returnSegs, array $offer): bool
    {
        $outCarrier = $this->primaryMarketingCarrier($outboundSegs, $offer);
        $retCarrier = $this->primaryMarketingCarrier($returnSegs, $offer);

        return $outCarrier !== '' && $retCarrier !== '' && $outCarrier === $retCarrier;
    }

    /**
     * @param  list<array<string, mixed>>  $legSegments
     * @param  array<string, mixed>  $offer
     */
    protected function primaryMarketingCarrier(array $legSegments, array $offer): string
    {
        $first = $legSegments[0] ?? null;
        if (! is_array($first)) {
            return '';
        }

        return $this->segmentMarketingCarrier($first, $offer);
    }

    /**
     * @param  array<string, mixed>  $seg
     * @param  array<string, mixed>  $offer
     */
    protected function segmentMarketingCarrier(array $seg, array $offer): string
    {
        $code = strtoupper(trim((string) ($seg['airline_code'] ?? '')));
        if ($code !== '') {
            return $code;
        }

        return strtoupper(trim((string) ($offer['airline_code'] ?? ($offer['carrier_code'] ?? ''))));
    }

    /**
     * RETURN-SPLIT-SELECT-R3 — enrich split card API rows with display/pricing fields.
     *
     * @param  array<string, mixed>  $offer
     * @param  array<string, mixed>  $criteria
     * @return array<string, mixed>
     */
    /**
     * @param  array<string, mixed>  $offer
     * @param  array<string, mixed>  $criteria
     * @param  array<string, string>  $cityMap
     * @param  array<string, string>  $airlineNameMap
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function mapSplitOptionFields(
        array $offer,
        array $criteria,
        string $comboId,
        array $cityMap = [],
        array $airlineNameMap = [],
        array $overrides = [],
    ): array {
        $pricing = is_array($offer['pricing_components'] ?? null) ? $offer['pricing_components'] : [];
        $adults = max(1, (int) ($criteria['adults'] ?? 1));
        $children = max(0, (int) ($criteria['children'] ?? 0));
        $infants = max(0, (int) ($criteria['infants'] ?? 0));
        $final = (float) ($offer['final_customer_price'] ?? 0);
        $displayedPrice = $final > 0 ? (int) round($final) : null;
        $pricingCurrency = (string) ($offer['pricing_currency'] ?? $offer['currency'] ?? 'PKR');

        $fareFamilyOptionsDisplay = FlightOfferDisplayPresenter::buildFareFamilyOptionsDisplay($offer);
        $brandedFaresPresentation = FlightOfferDisplayPresenter::buildBrandedFaresPresentationFields($fareFamilyOptionsDisplay, $offer);
        $presentation = FlightOfferDisplayPresenter::buildPresentation($offer, $criteria, $cityMap, $airlineNameMap);

        $passengerPricing = is_array(data_get($offer, 'fare_breakdown.passenger_pricing'))
            ? data_get($offer, 'fare_breakdown.passenger_pricing')
            : null;

        $row = array_merge([
            'offer_id' => $comboId,
            'combo_id' => $comboId,
            'sample_combo_id' => $comboId,
            'provider' => (string) ($offer['supplier_provider'] ?? ''),
            'supplier_source_label' => SupplierSourcePresenter::labelForOffer(
                (string) ($offer['supplier_provider'] ?? ''),
                isset($offer['source_type']) ? (string) $offer['source_type'] : null,
                isset($offer['provider_channel']) ? (string) $offer['provider_channel'] : ($offer['distribution_channel'] ?? null),
                null,
            ),
            'refundable' => (bool) ($offer['refundable'] ?? false),
            'baggage_summary_display' => (string) ($offer['baggage_summary_display'] ?? $offer['baggage'] ?? ''),
            'baggage_checked_display' => (string) ($offer['baggage_checked_display'] ?? ''),
            'baggage_cabin_display' => (string) ($offer['baggage_cabin_display'] ?? ''),
            'baggage_lines' => is_array($offer['baggage_lines'] ?? null) ? $offer['baggage_lines'] : [],
            'passenger_mix_display' => $adults.' adult, '.$children.' child, '.$infants.' infant',
            'displayed_price' => $displayedPrice,
            'final_customer_price' => $final > 0 ? $final : null,
            'base_fare' => (float) ($offer['base_fare'] ?? 0),
            'taxes' => (float) ($offer['taxes'] ?? 0),
            'markup' => (float) ($offer['markup'] ?? 0),
            'service_fee' => (float) ($offer['service_fee'] ?? 0),
            'pricing_currency' => $pricingCurrency,
            'supplier_currency' => (string) ($offer['supplier_currency'] ?? $pricingCurrency),
            'supplier_total' => (float) ($offer['supplier_total_source'] ?? (($offer['base_fare'] ?? 0) + ($offer['taxes'] ?? 0))),
            'conversion_status' => (string) ($offer['conversion_status'] ?? 'same_currency'),
            'passenger_pricing' => $passengerPricing,
            'passenger_pricing_available' => (bool) (
                data_get($offer, 'fare_breakdown.passenger_pricing_available')
                ?? (is_array($passengerPricing) && $passengerPricing !== [])
            ),
            'passenger_pricing_trusted' => (bool) data_get($offer, 'fare_breakdown.passenger_pricing_trusted', false),
            'has_confirmed_pkr_quote' => $displayedPrice !== null && $displayedPrice > 0,
            'journeys_display' => is_array($presentation['journeys_display'] ?? null) ? $presentation['journeys_display'] : [],
            'segments' => is_array($presentation['segments_display'] ?? null) ? $presentation['segments_display'] : [],
            'layover_summary' => is_array($presentation['layover_summary'] ?? null) ? $presentation['layover_summary'] : [],
            'layovers_display' => is_array($presentation['layovers_display'] ?? null) ? $presentation['layovers_display'] : [],
            'connection_details_unavailable' => (bool) ($presentation['connection_details_unavailable'] ?? false),
            'pricing_snapshot' => [
                'base_fare' => (float) ($pricing['base_fare'] ?? $offer['base_fare'] ?? 0),
                'taxes' => (float) ($pricing['taxes'] ?? $offer['taxes'] ?? 0),
                'admin_markup' => (float) ($pricing['admin_markup'] ?? 0),
                'service_fee' => (float) ($pricing['service_fee'] ?? 0),
                'final_total' => (float) ($pricing['final_total'] ?? $offer['final_customer_price'] ?? $offer['total'] ?? 0),
                'pricing_currency' => (string) ($pricing['pricing_currency'] ?? $offer['currency'] ?? 'PKR'),
                'applied_rules' => is_array($pricing['applied_rules'] ?? null) ? $pricing['applied_rules'] : [],
            ],
        ], $brandedFaresPresentation);

        return array_merge($row, $overrides);
    }

    /**
     * @param  array<string, mixed>  $combo
     */
    protected function comboFinalPrice(array $combo): ?float
    {
        $final = (float) ($combo['final_customer_price'] ?? 0);
        if ($final > 0) {
            return $final;
        }
        $total = (float) ($combo['total'] ?? 0);

        return $total > 0 ? $total : null;
    }

    /**
     * @param  list<array<string, mixed>>  $offers
     * @return array<string, true>
     */
    protected function comboIdsFromOffers(array $offers): array
    {
        $map = [];
        foreach ($offers as $offer) {
            if (! is_array($offer)) {
                continue;
            }
            $id = (string) ($offer['id'] ?? $offer['offer_id'] ?? '');
            if ($id !== '') {
                $map[$id] = true;
            }
        }

        return $map;
    }

    /**
     * @param  list<array<string, mixed>>  $offers
     * @return array<string, mixed>|null
     */
    protected function findOfferInList(array $offers, string $comboId): ?array
    {
        foreach ($offers as $offer) {
            if (! is_array($offer)) {
                continue;
            }
            $id = (string) ($offer['id'] ?? $offer['offer_id'] ?? '');
            if ($id === $comboId) {
                return $offer;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    protected function emptyIndex(): array
    {
        return [
            'prepared_at' => now()->toIso8601String(),
            'combo_count' => 0,
            'excluded_count' => 0,
            'combos' => [],
        ];
    }

    /**
     * @param  array<string, mixed>  $criteria
     * @param  list<array<string, mixed>>  $offers
     */
    public function safeBuildIndexForStore(array $criteria, array $offers, string $searchId): array
    {
        try {
            $index = $this->buildIndex($criteria, $offers);
            Log::info('return_split_search_prepared', [
                'search_id' => $searchId,
                'combo_count' => (int) ($index['combo_count'] ?? 0),
                'excluded_count' => (int) ($index['excluded_count'] ?? 0),
            ]);

            return $index;
        } catch (Throwable $e) {
            Log::warning('return_split_search_prepare_failed', [
                'search_id' => $searchId,
                'message' => $e->getMessage(),
            ]);

            return $this->emptyIndex();
        }
    }

    /**
     * OTA-RETURN-SPLIT-TRUE-FARE-COMBO-CHECKOUT-CLARITY-1: truthful split checkout sidebar.
     * Preserves independent outbound/return fare families; combo-level pricing only (not misleading leg totals).
     *
     * @param  array<string, mixed>  $comboOffer
     * @param  array<string, mixed>  $criteria
     * @return array<string, mixed>|null
     */
    public function buildCheckoutSplitSummary(
        string $searchId,
        string $comboId,
        string $outboundKey,
        string $outboundFareOptionKey,
        string $returnFareOptionKey,
        array $comboOffer,
        array $criteria,
    ): ?array {
        $searchId = trim($searchId);
        $comboId = trim($comboId);
        $outboundKey = trim($outboundKey);
        $outboundFareOptionKey = trim($outboundFareOptionKey);
        $returnFareOptionKey = trim($returnFareOptionKey);
        if ($searchId === '' || $comboId === '' || $outboundKey === '') {
            return null;
        }

        $store = app(FlightSearchResultStore::class);
        $payload = $store->get($searchId);
        if ($payload === null) {
            return null;
        }

        $index = is_array($payload['return_split'] ?? null) ? $payload['return_split'] : [];
        if (! $this->indexIsUsable($index)) {
            return null;
        }

        $comboMeta = null;
        foreach ($index['combos'] ?? [] as $combo) {
            if (! is_array($combo)) {
                continue;
            }
            if ((string) ($combo['combo_id'] ?? '') === $comboId) {
                $comboMeta = $combo;
                break;
            }
        }
        if ($comboMeta === null || (string) ($comboMeta['outbound_key'] ?? '') !== $outboundKey) {
            return null;
        }

        $offers = is_array($payload['offers'] ?? null) ? $payload['offers'] : [];
        $iataCodes = FlightOfferDisplayPresenter::collectIataCodes($comboOffer);
        $cityMap = FlightOfferDisplayPresenter::airportCityMap($iataCodes);
        $airlineNameMap = AirlineDisplayNameResolver::mapForCodes(
            AirlineDisplayNameResolver::collectCodesFromOffers([$comboOffer])
        );

        $presentation = FlightOfferDisplayPresenter::buildPresentation($comboOffer, $criteria, $cityMap, $airlineNameMap);
        $journeys = is_array($presentation['journeys_display'] ?? null) ? $presentation['journeys_display'] : [];
        $outJourney = is_array($journeys[0] ?? null) ? $journeys[0] : null;
        $retJourney = is_array($journeys[1] ?? null) ? $journeys[1] : null;
        if ($outJourney === null || $retJourney === null) {
            return null;
        }

        $outboundOfferForFare = $this->findOfferForOutboundFareKey($index, $offers, $outboundKey, $outboundFareOptionKey)
            ?? $comboOffer;
        $outboundIntent = $this->resolveLegFareIntent($outboundOfferForFare, $outboundFareOptionKey);
        if ($outboundIntent === null && $outboundFareOptionKey !== '') {
            $outboundIntent = $this->resolveLegFareIntent($comboOffer, $outboundFareOptionKey);
        }
        $returnIntent = $this->resolveLegFareIntent($comboOffer, $returnFareOptionKey);

        $outboundLeg = $this->finalizeLegCheckoutSummary(
            $this->mapLegCheckoutSummary($outJourney, $comboOffer, $outboundIntent, $outboundFareOptionKey),
            false,
        );
        $returnLeg = $this->finalizeLegCheckoutSummary(
            $this->mapLegCheckoutSummary($retJourney, $comboOffer, $returnIntent, $returnFareOptionKey),
            false,
        );

        $basePrice = $this->resolveCheapestComboTotalForOutboundKey($index, $outboundKey);
        if ($basePrice === null) {
            $basePrice = $this->comboFinalPrice($comboMeta);
        }
        $selectedTotal = $this->resolveComboAuthoritativeTotal($comboOffer, $returnIntent);
        $fareDifference = ($basePrice !== null && $selectedTotal !== null)
            ? round((float) $selectedTotal - (float) $basePrice, 2)
            : null;

        $routeLabel = FlightOfferDisplayPresenter::formatCriteriaRouteLabel($criteria);
        if ($routeLabel === '') {
            $origin = strtoupper(trim((string) ($criteria['origin'] ?? '')));
            $destination = strtoupper(trim((string) ($criteria['destination'] ?? '')));
            if ($origin !== '' && $destination !== '') {
                $routeLabel = $origin.' ⇄ '.$destination;
            }
        }

        return [
            'is_return_split' => true,
            'route_label' => $routeLabel,
            'combo_id' => $comboId,
            'outbound_key' => $outboundKey,
            'outbound_fare_option_key' => $outboundFareOptionKey,
            'return_fare_option_key' => $returnFareOptionKey,
            'outbound_selected_fare_family_option' => $outboundIntent,
            'return_selected_fare_family_option' => $returnIntent,
            'pricing_mode' => 'combo_total',
            'outbound' => $outboundLeg,
            'return' => $returnLeg,
            'totals' => [
                'pricing_mode' => 'combo_total',
                'base_price' => $basePrice,
                'base_price_display' => $this->formatCheckoutPriceDisplay($basePrice),
                'selected_total' => $selectedTotal,
                'selected_total_display' => $this->formatCheckoutPriceDisplay($selectedTotal),
                'fare_difference' => $fareDifference,
                'fare_difference_display' => $this->formatFareDifferenceDisplay($fareDifference),
                'grand_total' => $selectedTotal,
                'grand_total_display' => $this->formatCheckoutPriceDisplay($selectedTotal),
            ],
        ];
    }

    /**
     * Resolve an offer for outbound branded-fare lookup: cheapest combo first, then any combo where the key matches.
     *
     * @param  array<string, mixed>  $index
     * @param  list<array<string, mixed>>  $offers
     * @return array<string, mixed>|null
     */
    public function findOfferForOutboundFareKey(
        array $index,
        array $offers,
        string $outboundKey,
        string $outboundFareOptionKey,
    ): ?array {
        $outboundKey = trim($outboundKey);
        $outboundFareOptionKey = trim($outboundFareOptionKey);
        if ($outboundKey === '') {
            return null;
        }

        $sample = $this->findSampleComboOfferForOutboundKey($index, $offers, $outboundKey);
        if ($outboundFareOptionKey === '') {
            return $sample;
        }
        if ($sample !== null && $this->resolveLegFareIntent($sample, $outboundFareOptionKey) !== null) {
            return $sample;
        }

        foreach ($index['combos'] ?? [] as $combo) {
            if (! is_array($combo) || (string) ($combo['outbound_key'] ?? '') !== $outboundKey) {
                continue;
            }
            $comboId = (string) ($combo['combo_id'] ?? '');
            if ($comboId === '') {
                continue;
            }
            $offer = $this->findOfferInList($offers, $comboId);
            if ($offer !== null && $this->resolveLegFareIntent($offer, $outboundFareOptionKey) !== null) {
                return $offer;
            }
        }

        return $sample;
    }

    /**
     * @param  array<string, mixed>  $index
     */
    public function resolveCheapestComboTotalForOutboundKey(array $index, string $outboundKey): ?float
    {
        $outboundKey = trim($outboundKey);
        if ($outboundKey === '') {
            return null;
        }

        $cheapest = null;
        foreach ($index['combos'] ?? [] as $combo) {
            if (! is_array($combo) || (string) ($combo['outbound_key'] ?? '') !== $outboundKey) {
                continue;
            }
            $price = $this->comboFinalPrice($combo);
            if ($price !== null && ($cheapest === null || $price < $cheapest)) {
                $cheapest = $price;
            }
        }

        return $cheapest;
    }

    /**
     * @param  array<string, mixed>  $index
     * @param  list<array<string, mixed>>  $offers
     * @return array<string, mixed>|null
     */
    public function findSampleComboOfferForOutboundKey(array $index, array $offers, string $outboundKey): ?array
    {
        $outboundKey = trim($outboundKey);
        if ($outboundKey === '') {
            return null;
        }

        $cheapestId = null;
        $cheapestPrice = null;
        foreach ($index['combos'] ?? [] as $combo) {
            if (! is_array($combo) || (string) ($combo['outbound_key'] ?? '') !== $outboundKey) {
                continue;
            }
            $price = $this->comboFinalPrice($combo);
            if ($price !== null && ($cheapestPrice === null || $price < $cheapestPrice)) {
                $cheapestPrice = $price;
                $cheapestId = (string) ($combo['combo_id'] ?? '');
            }
        }

        if ($cheapestId === null || $cheapestId === '') {
            return null;
        }

        return $this->findOfferInList($offers, $cheapestId);
    }

    /**
     * @param  array<string, mixed>  $offer
     * @return array<string, mixed>|null
     */
    protected function resolveLegFareIntent(?array $offer, string $fareOptionKey): ?array
    {
        $fareOptionKey = trim($fareOptionKey);
        if ($offer === null || $fareOptionKey === '') {
            return null;
        }

        $resolved = FlightOfferDisplayPresenter::findFareFamilyOptionByKey($offer, $fareOptionKey);
        if ($resolved === null) {
            return null;
        }

        return FlightOfferDisplayPresenter::sanitizeSelectedFareFamilyIntent($resolved, $offer);
    }

    /**
     * @param  array<string, mixed>  $journey
     * @param  array<string, mixed>  $offer
     * @param  array<string, mixed>|null  $fareIntent
     * @return array<string, mixed>
     */
    protected function mapLegCheckoutSummary(
        array $journey,
        array $offer,
        ?array $fareIntent,
        string $fareOptionKey,
    ): array {
        $segments = is_array($journey['segments_display'] ?? null) ? $journey['segments_display'] : [];
        $firstSeg = is_array($segments[0] ?? null) ? $segments[0] : [];

        $airlineCode = strtoupper(trim((string) ($firstSeg['airline_code'] ?? $offer['airline_code'] ?? $offer['carrier_code'] ?? '')));
        $flightNumber = trim((string) ($firstSeg['flight_number'] ?? $offer['flight_number'] ?? ''));
        if ($flightNumber !== '' && $airlineCode !== '' && ! preg_match('/^[A-Z0-9]{2}/i', $flightNumber)) {
            $flightNumber = $airlineCode.' '.$flightNumber;
        }

        $fareName = is_array($fareIntent) ? trim((string) ($fareIntent['name'] ?? '')) : '';
        if ($fareName === '') {
            $fareName = trim((string) ($offer['fare_family'] ?? ''));
        }
        if ($fareName === '') {
            $fareName = 'Standard fare';
        }

        $price = null;
        $priceDisplay = null;
        $priceIsApproximate = false;
        if (is_array($fareIntent)
            && isset($fareIntent['displayed_price'])
            && is_numeric($fareIntent['displayed_price'])
            && (int) $fareIntent['displayed_price'] > 0) {
            $price = (float) $fareIntent['displayed_price'];
            $priceDisplay = trim((string) ($fareIntent['price_display'] ?? ''));
            $priceIsApproximate = (bool) ($fareIntent['price_is_approximate'] ?? false);
            if ($priceDisplay === '') {
                $currency = strtoupper(trim((string) ($fareIntent['displayed_currency'] ?? 'PKR')));
                $priceDisplay = $currency.' '.number_format((int) $price, 0);
            }
            $priceDisplay = preg_replace('/^Approx\.\s*/i', '', $priceDisplay) ?? $priceDisplay;
            $priceIsApproximate = false;
        }

        $baggage = is_array($fareIntent)
            ? trim((string) ($fareIntent['baggage_summary'] ?? $fareIntent['baggage'] ?? ''))
            : '';
        if ($baggage === '') {
            $baggage = trim((string) ($offer['baggage_summary_display'] ?? $offer['baggage'] ?? ''));
        }

        $origin = trim((string) ($journey['origin'] ?? ''));
        $destination = trim((string) ($journey['destination'] ?? ''));

        return [
            'route_label' => $origin !== '' && $destination !== '' ? $origin.' → '.$destination : '',
            'airline' => trim((string) ($firstSeg['airline_name'] ?? $offer['airline_name'] ?? '')),
            'airline_code' => $airlineCode,
            'flight_number' => $flightNumber,
            'departure_airport' => $origin,
            'departure_city' => trim((string) ($journey['origin_city'] ?? '')),
            'arrival_airport' => $destination,
            'arrival_city' => trim((string) ($journey['destination_city'] ?? '')),
            'departure_time' => trim((string) ($journey['departure_time_display'] ?? '')),
            'arrival_time' => trim((string) ($journey['arrival_time_display'] ?? '')),
            'date_label' => trim((string) ($journey['departure_date_display'] ?? '')),
            'duration' => trim((string) ($journey['duration_display'] ?? '')),
            'stops_label' => trim((string) ($journey['stops_display'] ?? '')),
            'branded_fare_title' => $fareName,
            'branded_fare_code' => is_array($fareIntent) ? trim((string) ($fareIntent['brand_code'] ?? '')) : '',
            'fare_option_key' => trim($fareOptionKey),
            'cabin' => trim((string) (is_array($fareIntent) ? ($fareIntent['cabin'] ?? '') : ($offer['cabin'] ?? ''))),
            'baggage' => $baggage !== '' ? $baggage : 'Baggage per fare rules',
            'price' => $price,
            'price_display' => $priceDisplay,
            'price_is_approximate' => $priceIsApproximate,
            'journey' => $journey,
        ];
    }

    /**
     * @param  array<string, mixed>  $comboOffer
     * @param  array<string, mixed>|null  $returnIntent
     */
    protected function resolveComboAuthoritativeTotal(array $comboOffer, ?array $returnIntent): ?float
    {
        if (is_array($returnIntent)
            && isset($returnIntent['displayed_price'])
            && is_numeric($returnIntent['displayed_price'])
            && (int) $returnIntent['displayed_price'] > 0) {
            return (float) $returnIntent['displayed_price'];
        }

        $final = (float) ($comboOffer['final_customer_price'] ?? $comboOffer['total'] ?? 0);

        return $final > 0 ? $final : null;
    }

    /**
     * @param  array<string, mixed>  $leg
     * @return array<string, mixed>
     */
    protected function finalizeLegCheckoutSummary(array $leg, bool $includeLegPrice): array
    {
        $leg['fare_family_title'] = (string) ($leg['branded_fare_title'] ?? 'Standard fare');
        $leg['fare_family_code'] = (string) ($leg['branded_fare_code'] ?? '');

        if ($includeLegPrice) {
            $leg['selected_price'] = $leg['price'] ?? null;
            $leg['base_price'] = null;
            $leg['fare_difference'] = null;

            return $leg;
        }

        unset($leg['price'], $leg['price_display'], $leg['price_is_approximate']);
        $leg['selected_price'] = null;
        $leg['base_price'] = null;
        $leg['fare_difference'] = null;

        return $leg;
    }

    protected function formatCheckoutPriceDisplay(?float $amount): ?string
    {
        if ($amount === null || $amount <= 0) {
            return null;
        }

        return 'PKR '.number_format((float) $amount, 0);
    }

    protected function formatFareDifferenceDisplay(?float $difference): ?string
    {
        if ($difference === null) {
            return null;
        }

        $currency = 'PKR';
        $abs = abs($difference);
        if ($difference > 0.01) {
            return '+ '.$currency.' '.number_format($abs, 0);
        }
        if ($difference < -0.01) {
            return '- '.$currency.' '.number_format($abs, 0);
        }

        return '+ '.$currency.' 0';
    }
}
