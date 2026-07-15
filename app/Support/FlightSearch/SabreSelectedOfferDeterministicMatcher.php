<?php

namespace App\Support\FlightSearch;

use App\Data\NormalizedFlightOfferData;

/**
 * Deterministic selected-offer matching after Sabre refresh (new offer IDs, same itinerary/fare).
 */
final class SabreSelectedOfferDeterministicMatcher
{
    private const TOTAL_MATCH_THRESHOLD = 0.01;

    /**
     * @param  list<NormalizedFlightOfferData>  $offers
     * @param  array<string, mixed>  $selectedContext
     * @return array{offer: NormalizedFlightOfferData, match_strategy: string}|null
     */
    public function match(array $offers, NormalizedFlightOfferData|string $source, array $selectedContext = []): ?array
    {
        $sourceOffer = is_string($source) ? null : $source;
        $sourceId = is_string($source) ? trim($source) : trim($source->offer_id);

        foreach ($offers as $candidate) {
            if ($sourceId !== '' && $candidate->offer_id === $sourceId) {
                return ['offer' => $candidate, 'match_strategy' => 'offer_id_exact'];
            }
        }

        if ($sourceOffer === null) {
            return null;
        }

        foreach ($offers as $candidate) {
            if ($this->matchesBasicItinerary($candidate, $sourceOffer)) {
                return ['offer' => $candidate, 'match_strategy' => 'itinerary_signature'];
            }
        }

        $brandCode = strtoupper(trim((string) ($selectedContext['brand_code'] ?? '')));
        $fareBasis = strtoupper(trim((string) ($selectedContext['fare_basis'] ?? '')));
        $bookingClass = strtoupper(trim((string) ($selectedContext['booking_class'] ?? '')));
        $selectedTotal = (float) ($selectedContext['selected_price_total'] ?? $sourceOffer->fare_breakdown->supplier_total ?? 0);

        foreach ($offers as $candidate) {
            if (! $this->matchesSegmentChain($candidate, $sourceOffer)) {
                continue;
            }

            $candidateArray = $candidate->toArray();
            $brandedMatch = $this->matchesBrandedFareContext($candidateArray, $brandCode, $fareBasis, $bookingClass, $selectedTotal);
            if ($brandedMatch) {
                return ['offer' => $candidate, 'match_strategy' => 'branded_fare_context'];
            }
        }

        return null;
    }

    /**
     * @param  list<array<string, mixed>>  $offers
     * @param  array<string, mixed>  $selectedOffer
     * @param  array<string, mixed>  $selectedContext
     * @return array{offer: array<string, mixed>, match_strategy: string}|null
     */
    public function matchArrayOffers(array $offers, array $selectedOffer, array $selectedContext = []): ?array
    {
        $normalized = [];
        foreach ($offers as $row) {
            if (! is_array($row)) {
                continue;
            }
            $normalized[] = NormalizedFlightOfferData::fromArray($row);
        }

        $source = NormalizedFlightOfferData::fromArray($selectedOffer);
        $match = $this->match($normalized, $source, $selectedContext);
        if ($match === null) {
            return null;
        }

        return [
            'offer' => $match['offer']->toArray(),
            'match_strategy' => $match['match_strategy'],
        ];
    }

    protected function matchesBasicItinerary(NormalizedFlightOfferData $candidate, NormalizedFlightOfferData $source): bool
    {
        return SabreMarketEndpointEquivalence::endpointMatchesRequested($candidate->origin, $source->origin)
            && SabreMarketEndpointEquivalence::endpointMatchesRequested($candidate->destination, $source->destination)
            && $candidate->departure_at === $source->departure_at
            && ($candidate->flight_number ?? '') === ($source->flight_number ?? '')
            && strtolower($candidate->cabin) === strtolower($source->cabin)
            && $candidate->airline_code === $source->airline_code;
    }

    protected function matchesSegmentChain(NormalizedFlightOfferData $candidate, NormalizedFlightOfferData $source): bool
    {
        $candidateSegments = $candidate->toArray()['segments'] ?? [];
        $sourceSegments = $source->toArray()['segments'] ?? [];
        if (! is_array($candidateSegments) || ! is_array($sourceSegments) || $candidateSegments === [] || $sourceSegments === []) {
            return $this->matchesBasicItinerary($candidate, $source);
        }

        if (count($candidateSegments) !== count($sourceSegments)) {
            return false;
        }

        foreach ($candidateSegments as $idx => $candSeg) {
            $srcSeg = $sourceSegments[$idx] ?? null;
            if (! is_array($candSeg) || ! is_array($srcSeg)) {
                return false;
            }

            $cOrigin = strtoupper(trim((string) ($candSeg['origin'] ?? '')));
            $sOrigin = strtoupper(trim((string) ($srcSeg['origin'] ?? '')));
            $cDest = strtoupper(trim((string) ($candSeg['destination'] ?? '')));
            $sDest = strtoupper(trim((string) ($srcSeg['destination'] ?? '')));

            if (! SabreMarketEndpointEquivalence::endpointMatchesRequested($cOrigin, $sOrigin)
                || ! SabreMarketEndpointEquivalence::endpointMatchesRequested($cDest, $sDest)) {
                return false;
            }

            $cCarrier = strtoupper(trim((string) ($candSeg['carrier'] ?? $candSeg['airline_code'] ?? '')));
            $sCarrier = strtoupper(trim((string) ($srcSeg['carrier'] ?? $srcSeg['airline_code'] ?? '')));
            if ($cCarrier !== '' && $sCarrier !== '' && $cCarrier !== $sCarrier) {
                return false;
            }

            $cFlight = trim((string) ($candSeg['flight_number'] ?? ''));
            $sFlight = trim((string) ($srcSeg['flight_number'] ?? ''));
            if ($cFlight !== '' && $sFlight !== '' && $cFlight !== $sFlight) {
                return false;
            }

            $cDep = substr(trim((string) ($candSeg['departure_at'] ?? '')), 0, 16);
            $sDep = substr(trim((string) ($srcSeg['departure_at'] ?? '')), 0, 16);
            if ($cDep !== '' && $sDep !== '' && $cDep !== $sDep) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $candidate
     */
    protected function matchesBrandedFareContext(
        array $candidate,
        string $brandCode,
        string $fareBasis,
        string $bookingClass,
        float $selectedTotal,
    ): bool {
        $options = FlightOfferDisplayPresenter::buildFareFamilyOptionsDisplay($candidate);
        if ($options === []) {
            return $brandCode === '' && $fareBasis === '' && $bookingClass === '';
        }

        foreach ($options as $option) {
            $optBrand = strtoupper(trim((string) ($option['brand_code'] ?? $option['supplier_brand_code'] ?? '')));
            $optFb = strtoupper(trim((string) ($option['fare_basis'] ?? '')));
            $optRbd = strtoupper(trim((string) ($option['booking_class'] ?? '')));
            $optTotal = (float) ($option['price_total'] ?? 0);

            if ($brandCode !== '' && $optBrand !== '' && $optBrand !== $brandCode) {
                continue;
            }
            if ($fareBasis !== '' && $optFb !== '' && $optFb !== $fareBasis) {
                continue;
            }
            if ($bookingClass !== '' && $optRbd !== '' && $optRbd !== $bookingClass) {
                continue;
            }
            if ($selectedTotal > 0 && $optTotal > 0 && ! $this->totalsWithinTolerance($selectedTotal, $optTotal)) {
                continue;
            }

            return true;
        }

        return false;
    }

    protected function totalsWithinTolerance(float $expected, float $actual): bool
    {
        $threshold = max(self::TOTAL_MATCH_THRESHOLD, abs($expected) * 0.02);

        return abs($actual - $expected) <= $threshold;
    }
}
