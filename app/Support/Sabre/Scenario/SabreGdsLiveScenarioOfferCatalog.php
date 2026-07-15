<?php

namespace App\Support\Sabre\Scenario;

use App\Data\FlightSearchRequestData;
use App\Models\SupplierConnection;
use App\Services\Suppliers\Sabre\Core\SabreClient;
use App\Services\Suppliers\Sabre\Gds\SabreFlightSearchNormalizer;
use App\Services\Suppliers\Sabre\SabreFlightSearchRequestBuilder;
use App\Services\Suppliers\Sabre\SabreStoredPricingContextDigest;
use App\Support\FlightSearch\FlightOfferDisplayPresenter;
use Throwable;

/**
 * Live Sabre GDS shop, offer normalization, scenario filtering, and fare selection for the scenario runner.
 */
final class SabreGdsLiveScenarioOfferCatalog
{
    public function __construct(
        protected SabreFlightSearchRequestBuilder $requestBuilder,
        protected SabreClient $client,
        protected SabreFlightSearchNormalizer $normalizer,
        protected SabreStoredPricingContextDigest $digestor,
        protected SabreGdsLiveScenarioPlanCandidateDiagnostics $planDiagnostics,
    ) {}

    /**
     * @param  array<string, mixed>  $scenario
     * @param  array<string, mixed>  $discoveryFilters
     * @return array{
     *     shop_http_status: int,
     *     normalized_offer_count: int,
     *     candidates: list<array{row: array<string, mixed>, snap: array<string, mixed>}>,
     *     eligible: list<array{row: array<string, mixed>, snap: array<string, mixed>}>
     * }
     */
    public function search(SupplierConnection $connection, array $scenario, array $discoveryFilters = []): array
    {
        $origin = (string) ($scenario['origin'] ?? '');
        $destination = (string) ($scenario['destination'] ?? '');
        $departureDate = (string) ($scenario['departure_date'] ?? '');
        $returnDate = $scenario['return_date'] ?? null;
        $tripType = (string) ($scenario['trip_type'] ?? 'one_way');
        $scenarioKey = (string) ($scenario['scenario_key'] ?? '');
        $carrier = $scenario['carrier'] ?? null;
        $stops = strtoupper(trim((string) ($scenario['stops'] ?? 'ANY')));

        $request = FlightSearchRequestData::fromArray([
            'origin' => $origin,
            'destination' => $destination,
            'depart_date' => $departureDate,
            'return_date' => is_string($returnDate) && trim($returnDate) !== '' ? trim($returnDate) : null,
            'adults' => 1,
            'children' => 0,
            'infants' => 0,
            'cabin' => 'economy',
            'trip_type' => $tripType === 'return' ? 'round_trip' : 'one_way',
            'currency' => 'PKR',
        ]);

        try {
            $shopPayload = $this->requestBuilder->build($request, $connection);
            $response = $this->client->postShopPayload($connection, $shopPayload);
        } catch (Throwable) {
            return [
                'shop_http_status' => 0,
                'normalized_offer_count' => 0,
                'candidates' => [],
                'eligible' => [],
                'shop_error' => 'shop_request_failed',
            ];
        }

        $shopHttpStatus = $response->status();
        $json = $response->json();
        if (! $response->successful() || ! is_array($json)) {
            return [
                'shop_http_status' => $shopHttpStatus,
                'normalized_offer_count' => 0,
                'candidates' => [],
                'eligible' => [],
                'shop_error' => 'shop_http_error',
            ];
        }

        $normalized = $this->normalizer->normalize($json, $connection, $request);
        $candidates = [];
        foreach ($normalized as $offer) {
            $snap = $this->normalizer->mergeSabrePricingLinkageHandoff(
                $this->normalizer->ensureSabreBookingContextOnCachedOffer($offer->toArray())
            );
            $row = $this->buildOfferRow($snap, $scenarioKey);
            $candidates[] = ['row' => $row, 'snap' => $snap];
        }

        $carrierFilter = is_string($carrier) && trim($carrier) !== '' ? strtoupper(trim($carrier)) : '';
        $eligible = $this->filterEligibleCandidates($candidates, $scenarioKey, $origin, $carrierFilter, $stops, $discoveryFilters);

        return [
            'shop_http_status' => $shopHttpStatus,
            'normalized_offer_count' => count($normalized),
            'candidates' => $candidates,
            'eligible' => $eligible,
        ];
    }

    /**
     * @param  list<array{row: array<string, mixed>, snap: array<string, mixed>}>  $eligible
     * @param  array<string, mixed>  $scenario
     * @return list<array<string, mixed>>
     */
    public function buildPlanSummaries(array $eligible, array $scenario = [], ?int $limit = null, array $options = []): array
    {
        $summaries = [];
        foreach ($eligible as $candidate) {
            if ($limit !== null && count($summaries) >= $limit) {
                break;
            }
            $row = is_array($candidate['row'] ?? null) ? $candidate['row'] : [];
            $snap = is_array($candidate['snap'] ?? null) ? $candidate['snap'] : [];
            $brandOptions = FlightOfferDisplayPresenter::buildFareFamilyOptionsDisplay($snap);
            $brandCodes = [];
            foreach ($brandOptions as $option) {
                $code = strtoupper(trim((string) ($option['brand_code'] ?? '')));
                if ($code !== '') {
                    $brandCodes[] = $code;
                }
            }
            $brandCodes = array_values(array_unique($brandCodes));
            if (($row['brand_code'] ?? null) === null && $brandCodes !== []) {
                $row['brand_code'] = $brandCodes[0];
            }
            $summaries[] = $this->planDiagnostics->diagnose($snap, $row, $scenario, $options);
        }

        return $summaries;
    }

    /**
     * @param  list<array{row: array<string, mixed>, snap: array<string, mixed>}>  $eligible
     * @return array{
     *     candidate: array{row: array<string, mixed>, snap: array<string, mixed>}|null,
     *     selected_fare_family_option: array<string, mixed>|null,
     *     fare_option_key: string|null,
     *     brand_code: string|null,
     *     selection_error: string|null
     * }
     */
    public function pickCandidate(array $eligible, string $farePick): array
    {
        if ($eligible === []) {
            return [
                'candidate' => null,
                'selected_fare_family_option' => null,
                'fare_option_key' => null,
                'brand_code' => null,
                'selection_error' => 'no_eligible_gds_offer',
            ];
        }

        $farePick = trim($farePick);
        if ($farePick === 'all-brands') {
            return $this->pickLowestBrandPair($eligible);
        }

        if (str_starts_with(strtolower($farePick), 'brand:')) {
            $brandCode = strtoupper(trim(substr($farePick, 6)));

            return $this->pickByBrandCode($eligible, $brandCode);
        }

        $sorted = $eligible;
        if ($farePick === 'lowest') {
            usort($sorted, static fn (array $a, array $b): int => ((float) ($a['row']['total_fare'] ?? PHP_FLOAT_MAX))
                <=> ((float) ($b['row']['total_fare'] ?? PHP_FLOAT_MAX)));
        } elseif ($farePick === 'highest') {
            usort($sorted, static fn (array $a, array $b): int => ((float) ($b['row']['total_fare'] ?? 0))
                <=> ((float) ($a['row']['total_fare'] ?? 0)));
        }

        $selected = $sorted[0];
        $fareSelection = $this->resolveDefaultFareSelection($selected['snap']);

        return [
            'candidate' => $selected,
            'selected_fare_family_option' => $fareSelection['selected_fare_family_option'],
            'fare_option_key' => $fareSelection['fare_option_key'],
            'brand_code' => $fareSelection['brand_code'],
            'selection_error' => $fareSelection['selection_error'],
        ];
    }

    /**
     * @param  list<array{row: array<string, mixed>, snap: array<string, mixed>}>  $eligible
     * @return array{
     *     candidate: array{row: array<string, mixed>, snap: array<string, mixed>}|null,
     *     selected_fare_family_option: array<string, mixed>|null,
     *     fare_option_key: string|null,
     *     brand_code: string|null,
     *     selection_error: string|null
     * }
     */
    protected function pickByBrandCode(array $eligible, string $brandCode): array
    {
        $best = null;
        $bestTotal = PHP_FLOAT_MAX;
        $bestSelection = null;

        foreach ($eligible as $candidate) {
            $snap = is_array($candidate['snap'] ?? null) ? $candidate['snap'] : [];
            foreach (FlightOfferDisplayPresenter::buildFareFamilyOptionsDisplay($snap) as $option) {
                if (strtoupper(trim((string) ($option['brand_code'] ?? ''))) !== $brandCode) {
                    continue;
                }
                $optionKey = trim((string) ($option['option_key'] ?? ''));
                $total = (float) ($option['displayed_price'] ?? $option['price_total'] ?? $candidate['row']['total_fare'] ?? PHP_FLOAT_MAX);
                if ($best === null || $total < $bestTotal) {
                    $best = $candidate;
                    $bestTotal = $total;
                    $bestSelection = [
                        'selected_fare_family_option' => $option,
                        'fare_option_key' => $optionKey !== '' ? $optionKey : null,
                        'brand_code' => $brandCode,
                        'selection_error' => null,
                    ];
                }
            }
        }

        if ($best === null || $bestSelection === null) {
            return [
                'candidate' => null,
                'selected_fare_family_option' => null,
                'fare_option_key' => null,
                'brand_code' => null,
                'selection_error' => 'brand_not_found',
            ];
        }

        return array_merge(['candidate' => $best], $bestSelection);
    }

    /**
     * @param  list<array{row: array<string, mixed>, snap: array<string, mixed>}>  $eligible
     * @return array{
     *     candidate: array{row: array<string, mixed>, snap: array<string, mixed>}|null,
     *     selected_fare_family_option: array<string, mixed>|null,
     *     fare_option_key: string|null,
     *     brand_code: string|null,
     *     selection_error: string|null
     * }
     */
    protected function pickLowestBrandPair(array $eligible): array
    {
        $best = null;
        $bestTotal = PHP_FLOAT_MAX;
        $bestSelection = null;

        foreach ($eligible as $candidate) {
            $snap = is_array($candidate['snap'] ?? null) ? $candidate['snap'] : [];
            $brandOptions = FlightOfferDisplayPresenter::buildFareFamilyOptionsDisplay($snap);
            if ($brandOptions === []) {
                $total = (float) ($candidate['row']['total_fare'] ?? PHP_FLOAT_MAX);
                if ($best === null || $total < $bestTotal) {
                    $best = $candidate;
                    $bestTotal = $total;
                    $bestSelection = $this->resolveDefaultFareSelection($snap);
                }

                continue;
            }
            foreach ($brandOptions as $option) {
                $total = (float) ($option['displayed_price'] ?? $option['price_total'] ?? $candidate['row']['total_fare'] ?? PHP_FLOAT_MAX);
                if ($best === null || $total < $bestTotal) {
                    $best = $candidate;
                    $bestTotal = $total;
                    $bestSelection = [
                        'selected_fare_family_option' => $option,
                        'fare_option_key' => trim((string) ($option['option_key'] ?? '')) ?: null,
                        'brand_code' => strtoupper(trim((string) ($option['brand_code'] ?? ''))) ?: null,
                        'selection_error' => null,
                    ];
                }
            }
        }

        if ($best === null || $bestSelection === null) {
            return [
                'candidate' => null,
                'selected_fare_family_option' => null,
                'fare_option_key' => null,
                'brand_code' => null,
                'selection_error' => 'no_eligible_gds_offer',
            ];
        }

        return array_merge(['candidate' => $best], $bestSelection);
    }

    /**
     * @param  array<string, mixed>  $snap
     * @return array{
     *     selected_fare_family_option: array<string, mixed>|null,
     *     fare_option_key: string|null,
     *     brand_code: string|null,
     *     selection_error: string|null
     * }
     */
    protected function resolveDefaultFareSelection(array $snap): array
    {
        $brandOptions = FlightOfferDisplayPresenter::buildFareFamilyOptionsDisplay($snap);
        if ($brandOptions !== []) {
            $option = $brandOptions[0];
            $optionKey = trim((string) ($option['option_key'] ?? ''));

            return [
                'selected_fare_family_option' => $option,
                'fare_option_key' => $optionKey !== '' ? $optionKey : null,
                'brand_code' => strtoupper(trim((string) ($option['brand_code'] ?? ''))) ?: null,
                'selection_error' => null,
            ];
        }

        $handoff = is_array($snap['sabre_booking_context'] ?? null) ? $snap['sabre_booking_context'] : [];
        $brandCode = strtoupper(trim((string) ($handoff['selected_brand_code'] ?? $handoff['brand_code'] ?? '')));

        return [
            'selected_fare_family_option' => $brandCode !== '' ? [
                'brand_code' => $brandCode,
                'booking_classes_by_segment' => $handoff['booking_classes_by_segment'] ?? [],
                'fare_basis_codes_by_segment' => $handoff['fare_basis_codes_by_segment'] ?? [],
                'displayed_price' => data_get($snap, 'fare_breakdown.supplier_total'),
            ] : null,
            'fare_option_key' => null,
            'brand_code' => $brandCode !== '' ? $brandCode : null,
            'selection_error' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $snap
     * @return array<string, mixed>
     */
    protected function buildOfferRow(array $snap, string $scenario): array
    {
        $readiness = $this->digestor->assessReadiness($snap);
        $digest = $this->digestor->digest($snap);

        $raw = is_array($snap['raw_payload'] ?? null) ? $snap['raw_payload'] : [];
        $ctx = is_array($raw['sabre_shop_context'] ?? null) ? $raw['sabre_shop_context'] : [];
        $handoff = is_array($snap['sabre_booking_context'] ?? null)
            ? $snap['sabre_booking_context']
            : (is_array($raw['sabre_booking_context'] ?? null) ? $raw['sabre_booking_context'] : []);

        $segments = is_array($snap['segments'] ?? null) ? array_values($snap['segments']) : [];
        $segmentCount = count($segments);
        $marketing = is_array($snap['marketing_carrier_chain'] ?? null) ? $snap['marketing_carrier_chain'] : [];
        $carrierChain = implode('+', array_map(static fn ($c): string => strtoupper(trim((string) $c)), $marketing));

        $routeParts = [];
        if ($segments !== []) {
            $routeParts[] = strtoupper(trim((string) ($segments[0]['origin'] ?? $snap['origin'] ?? '')));
            foreach ($segments as $seg) {
                if (! is_array($seg)) {
                    continue;
                }
                $routeParts[] = strtoupper(trim((string) ($seg['destination'] ?? '')));
            }
        }
        $route = implode('-', array_values(array_filter($routeParts, static fn (string $p): bool => $p !== '')));

        $bookingBySeg = is_array($handoff['booking_classes_by_segment'] ?? null)
            ? $handoff['booking_classes_by_segment']
            : (is_array($ctx['booking_class'] ?? null) ? $ctx['booking_class'] : []);
        $fareBasisBySeg = is_array($handoff['fare_basis_codes_by_segment'] ?? null)
            ? $handoff['fare_basis_codes_by_segment']
            : [];
        $cabinsBySeg = is_array($handoff['cabin_by_segment'] ?? null) ? $handoff['cabin_by_segment'] : [];
        $uniqueMarketing = array_values(array_unique(array_filter(array_map(
            static fn ($c): string => strtoupper(trim((string) $c)),
            $marketing
        ), static fn (string $c): bool => $c !== '')));
        $sameCarrier = count($uniqueMarketing) <= 1;
        $mixedCarrier = $segmentCount >= 2 && ! $sameCarrier;
        $brandCode = strtoupper(trim((string) ($handoff['selected_brand_code'] ?? $handoff['brand_code'] ?? '')));

        $fare = is_array($snap['fare_breakdown'] ?? null) ? $snap['fare_breakdown'] : [];
        $distributionChannel = $this->resolveDistributionChannel($snap, $ctx, $handoff);
        $autoReady = ($readiness['auto_pnr_pricing_context_ready'] ?? false) === true;
        $cpnrEligible = $distributionChannel !== 'ndc' && $autoReady;

        $row = [
            'offer_id' => substr(hash('sha256', (string) ($snap['offer_id'] ?? '')), 0, 16),
            'route' => $route,
            'carrier_chain' => $carrierChain,
            'marketing_carriers' => $uniqueMarketing,
            'validating_carrier' => strtoupper(trim((string) ($snap['validating_carrier'] ?? $digest['validating_carrier'] ?? ''))),
            'segment_count' => $segmentCount,
            'same_carrier' => $sameCarrier,
            'mixed_carrier' => $mixedCarrier,
            'distribution_channel' => $distributionChannel,
            'cpnr_eligible' => $cpnrEligible,
            'booking_classes_by_segment' => $this->capStringList($bookingBySeg),
            'fare_basis_codes_by_segment' => $this->capStringList($fareBasisBySeg !== []
                ? $fareBasisBySeg
                : (is_array($digest['fare_basis_codes'] ?? null) ? $digest['fare_basis_codes'] : [])),
            'cabin_by_segment' => $this->capStringList($cabinsBySeg),
            'brand_code' => $brandCode !== '' ? $brandCode : null,
            'total_fare' => isset($fare['supplier_total']) ? round((float) $fare['supplier_total'], 2) : null,
            'currency' => isset($fare['currency']) ? strtoupper(substr(trim((string) $fare['currency']), 0, 6)) : null,
            'auto_pnr_pricing_context_ready' => $autoReady,
            'pricing_context_policy' => (string) ($readiness['pricing_context_policy'] ?? ''),
        ];

        if ($scenario === 'ow_connecting' && $segmentCount === 2) {
            $row['connecting_carrier_profile'] = $mixedCarrier ? 'mixed_carrier' : 'same_carrier';
        }

        return $row;
    }

    /**
     * @param  list<array{row: array<string, mixed>, snap: array<string, mixed>}>  $candidates
     * @param  array<string, mixed>  $discoveryFilters
     * @return list<array{row: array<string, mixed>, snap: array<string, mixed>}>
     */
    protected function filterEligibleCandidates(
        array $candidates,
        string $scenario,
        string $origin,
        string $carrierFilter,
        string $stops,
        array $discoveryFilters = [],
    ): array {
        $rows = array_map(static fn (array $c): array => $c['row'], $candidates);
        $filteredRows = $this->filterOffersForScenario($rows, $scenario, $origin);
        $filteredRows = $this->filterOffersForStops($filteredRows, $stops);
        $filteredIds = [];
        foreach ($filteredRows as $filteredRow) {
            $oid = (string) ($filteredRow['offer_id'] ?? '');
            if ($oid !== '') {
                $filteredIds[$oid] = true;
            }
        }

        $eligible = [];
        foreach ($candidates as $candidate) {
            $row = $candidate['row'];
            $oid = (string) ($row['offer_id'] ?? '');
            if ($oid === '' || ! isset($filteredIds[$oid])) {
                continue;
            }
            if (($row['cpnr_eligible'] ?? false) !== true) {
                continue;
            }
            if (($row['auto_pnr_pricing_context_ready'] ?? false) !== true) {
                continue;
            }
            if (($row['distribution_channel'] ?? '') !== 'gds') {
                continue;
            }
            if ($scenario === 'ow_connecting'
                && ($row['connecting_carrier_profile'] ?? '') === 'mixed_carrier'
                && ($discoveryFilters['mixed_carrier'] ?? null) !== true
                && ($discoveryFilters['require_mixed_carrier'] ?? false) !== true) {
                continue;
            }
            if ($scenario === 'ow_mixed_connecting' && ($row['mixed_carrier'] ?? false) !== true) {
                continue;
            }
            if ($scenario === 'ow_mixed_multistop' && (($row['mixed_carrier'] ?? false) !== true || (int) ($row['segment_count'] ?? 0) < 3)) {
                continue;
            }
            if ($scenario === 'return_mixed' && ($row['mixed_carrier'] ?? false) !== true) {
                continue;
            }
            if ($carrierFilter !== '' && ! $this->offerMatchesCarrier($row, $carrierFilter)) {
                continue;
            }
            if (! $this->matchesDiscoveryFilters($row, $discoveryFilters)) {
                continue;
            }
            $eligible[] = $candidate;
        }

        if ($scenario === 'ow_connecting') {
            usort($eligible, static function (array $a, array $b): int {
                $aSame = ($a['row']['connecting_carrier_profile'] ?? '') === 'same_carrier' ? 0 : 1;
                $bSame = ($b['row']['connecting_carrier_profile'] ?? '') === 'same_carrier' ? 0 : 1;
                if ($aSame !== $bSame) {
                    return $aSame <=> $bSame;
                }

                return ((int) ($b['row']['auto_pnr_pricing_context_ready'] ?? 0))
                    <=> ((int) ($a['row']['auto_pnr_pricing_context_ready'] ?? 0));
            });
        }

        return $eligible;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    protected function offerMatchesCarrier(array $row, string $carrier): bool
    {
        if ($carrier === '' || $carrier === 'ANY') {
            return true;
        }
        if (strtoupper(trim((string) ($row['validating_carrier'] ?? ''))) === $carrier) {
            return true;
        }
        $chain = strtoupper(trim((string) ($row['carrier_chain'] ?? '')));
        if ($chain !== '' && str_contains($chain, $carrier)) {
            return true;
        }
        foreach ((array) ($row['marketing_carriers'] ?? []) as $mkt) {
            if (strtoupper(trim((string) $mkt)) === $carrier) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    protected function filterOffersForScenario(array $rows, string $scenario, string $origin): array
    {
        if ($scenario === '') {
            return $rows;
        }

        $filtered = array_values(array_filter($rows, function (array $row) use ($scenario, $origin): bool {
            $segmentCount = (int) ($row['segment_count'] ?? 0);

            return match ($scenario) {
                'ow_direct' => $segmentCount === 1,
                'ow_connecting' => $segmentCount === 2,
                'ow_two_stop' => $segmentCount === 3,
                'ow_three_stop' => $segmentCount === 4,
                'ow_four_stop' => $segmentCount === 5,
                'ow_mixed_connecting' => $segmentCount === 2,
                'ow_mixed_multistop' => $segmentCount >= 3,
                'return', 'return_mixed' => $this->offerMatchesReturnScenario($row, $origin),
                default => true,
            };
        }));

        if ($scenario === 'ow_direct') {
            usort($filtered, static fn (array $a, array $b): int => ((int) ($b['auto_pnr_pricing_context_ready'] ?? 0))
                <=> ((int) ($a['auto_pnr_pricing_context_ready'] ?? 0)));
        }

        return $filtered;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    protected function filterOffersForStops(array $rows, string $stops): array
    {
        if ($stops === '' || $stops === 'ANY') {
            return $rows;
        }

        $expectedSegments = match ($stops) {
            '0' => 1,
            '1' => 2,
            '2' => 3,
            '3' => 4,
            '4' => 5,
            default => null,
        };
        if ($expectedSegments === null) {
            return $rows;
        }

        return array_values(array_filter(
            $rows,
            static fn (array $row): bool => (int) ($row['segment_count'] ?? 0) === $expectedSegments,
        ));
    }

    /**
     * @param  array<string, mixed>  $row
     */
    protected function offerMatchesReturnScenario(array $row, string $origin): bool
    {
        $route = strtoupper(trim((string) ($row['route'] ?? '')));
        $origin = strtoupper(trim($origin));
        if ($route === '' || $origin === '') {
            return false;
        }

        return str_ends_with($route, '-'.$origin) && (int) ($row['segment_count'] ?? 0) >= 2;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>  $filters
     */
    protected function matchesDiscoveryFilters(array $row, array $filters): bool
    {
        if ($filters === []) {
            return true;
        }

        $segmentCount = (int) ($row['segment_count'] ?? 0);
        $stops = max(0, $segmentCount - 1);

        if (isset($filters['min_stops']) && $stops < (int) $filters['min_stops']) {
            return false;
        }
        if (isset($filters['max_stops']) && $stops > (int) $filters['max_stops']) {
            return false;
        }
        if (isset($filters['min_segments']) && $segmentCount < (int) $filters['min_segments']) {
            return false;
        }
        if (isset($filters['max_segments']) && $segmentCount > (int) $filters['max_segments']) {
            return false;
        }
        if (($filters['same_carrier'] ?? null) === true && ($row['same_carrier'] ?? false) !== true) {
            return false;
        }
        if (($filters['same_carrier'] ?? null) === false && ($row['same_carrier'] ?? false) === true) {
            return false;
        }
        if (($filters['mixed_carrier'] ?? null) === true && ($row['mixed_carrier'] ?? false) !== true) {
            return false;
        }
        if (($filters['mixed_carrier'] ?? null) === false && ($row['mixed_carrier'] ?? false) === true) {
            return false;
        }

        $validatingCarrier = strtoupper(trim((string) ($filters['validating_carrier'] ?? '')));
        if ($validatingCarrier !== ''
            && strtoupper(trim((string) ($row['validating_carrier'] ?? ''))) !== $validatingCarrier) {
            return false;
        }

        $carrierChainFilter = strtoupper(trim((string) ($filters['carrier_chain'] ?? '')));
        if ($carrierChainFilter !== '') {
            $chain = strtoupper(trim((string) ($row['carrier_chain'] ?? '')));
            if ($chain === '' || ! str_contains($chain, $carrierChainFilter)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $snap
     * @param  array<string, mixed>  $ctx
     * @param  array<string, mixed>  $handoff
     */
    protected function resolveDistributionChannel(array $snap, array $ctx, array $handoff): string
    {
        foreach ([$snap, $handoff, $ctx] as $map) {
            $channel = strtolower(trim((string) ($map['distribution_channel'] ?? '')));
            if ($channel === 'ndc') {
                return 'ndc';
            }
            if ($channel === 'gds') {
                return 'gds';
            }
        }

        foreach (['pricing_subsource', 'fare_source', 'itinerary_source'] as $key) {
            $v = strtolower(trim((string) ($ctx[$key] ?? '')));
            if ($v !== '' && str_contains($v, 'ndc')) {
                return 'ndc';
            }
        }

        return 'gds';
    }

    /**
     * @param  list<mixed>  $list
     * @return list<string>
     */
    protected function capStringList(array $list): array
    {
        $out = [];
        foreach (array_slice($list, 0, 12) as $item) {
            if (! is_scalar($item)) {
                continue;
            }
            $s = trim((string) $item);
            if ($s !== '') {
                $out[] = substr($s, 0, 16);
            }
        }

        return $out;
    }
}
