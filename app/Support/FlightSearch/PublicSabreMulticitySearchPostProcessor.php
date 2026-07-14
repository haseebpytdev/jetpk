<?php

namespace App\Support\FlightSearch;

use App\Services\Suppliers\Sabre\Gds\SabreFlightSearchNormalizer;
use App\Support\Sabre\Scenario\SabreGdsLiveScenarioMulticityCandidateDedupSorter;
use App\Support\Sabre\Scenario\SabreGdsLiveScenarioMulticityCandidateNormalizer;
use Illuminate\Support\Facades\Log;

/**
 * Applies true multi-city candidate normalization, mixed-carrier filtering, and dedup for public search.
 */
final class PublicSabreMulticitySearchPostProcessor
{
    public function __construct(
        protected SabreFlightSearchNormalizer $normalizer,
        protected SabreGdsLiveScenarioMulticityCandidateNormalizer $candidateNormalizer,
        protected SabreMixedCarrierSearchResultsFilter $mixedCarrierSearchFilter,
        protected SabreGdsLiveScenarioMulticityCandidateDedupSorter $candidateDedupSorter,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $offers
     * @param  array<string, mixed>  $criteria
     * @return array{offers: list<array<string, mixed>>, diagnostics: array<string, mixed>, warnings: list<string>}
     */
    public function process(array $offers, array $criteria): array
    {
        if (! PublicMulticityInquiryPolicy::isMulticitySearch($criteria)) {
            return ['offers' => $offers, 'diagnostics' => [], 'warnings' => []];
        }

        $slices = $this->buildSlices($criteria);
        if (count($slices) < 2) {
            return ['offers' => [], 'diagnostics' => [], 'warnings' => []];
        }

        $sabreOffers = [];
        foreach ($offers as $offer) {
            if (! is_array($offer)) {
                continue;
            }
            if (strtolower((string) ($offer['supplier_provider'] ?? '')) === 'sabre') {
                $sabreOffers[] = $offer;
            }
        }

        $candidates = [];
        $offersByDedupKey = [];
        foreach ($sabreOffers as $offer) {
            $snap = $this->normalizer->mergeSabrePricingLinkageHandoff(
                $this->normalizer->ensureSabreBookingContextOnCachedOffer($offer)
            );
            $candidate = $this->candidateNormalizer->normalize($snap, $slices);
            $dedupKey = $this->candidateDedupSorter->buildDedupKey($candidate);
            $candidates[] = $candidate;
            $offersByDedupKey[$dedupKey] ??= [];
            $offersByDedupKey[$dedupKey][] = $offer;
        }

        $normalizedCount = count($candidates);
        $filterResult = $this->mixedCarrierSearchFilter->filterMulticityPlanCandidates($candidates);
        $filteredCandidates = $filterResult['candidates'];
        $dedupResult = $this->candidateDedupSorter->deduplicateAndSort($filteredCandidates);

        $retainedOffers = [];
        foreach ($dedupResult['candidates'] as $candidate) {
            $dedupKey = $this->candidateDedupSorter->buildDedupKey($candidate);
            $matches = $offersByDedupKey[$dedupKey] ?? [];
            if ($matches === []) {
                continue;
            }
            $retainedOffers[] = $this->enrichOffer($this->pickPreferredOffer($matches, $candidate), $candidate);
        }

        $diagnostics = array_merge($filterResult['diagnostics'], $dedupResult['diagnostics'], [
            'multicity_response_offer_count' => count($sabreOffers),
            'multicity_normalized_offer_count' => $normalizedCount,
            'multicity_search_path' => 'true_multicity_shop',
            'multicity_stitching_used' => false,
        ]);

        $warnings = [];
        if ($this->mixedCarrierSearchFilter->allOffersFilteredByPolicy($filterResult['diagnostics'])) {
            $warnings[] = SabreMixedCarrierSearchResultsFilter::EMPTY_MULTICITY_RESULTS_CUSTOMER_MESSAGE;
        } elseif ($retainedOffers === [] && $normalizedCount > 0) {
            $warnings[] = SabreMixedCarrierSearchResultsFilter::EMPTY_MULTICITY_RESULTS_CUSTOMER_MESSAGE;
        }

        Log::info('flight_search.public_diagnostics', [
            'stage' => 'multicity_public_post_process',
            'search_id' => (string) ($criteria['search_id'] ?? ''),
            ...$diagnostics,
            'retained_offer_count' => count($retainedOffers),
        ]);

        return [
            'offers' => $retainedOffers,
            'diagnostics' => $diagnostics,
            'warnings' => $warnings,
        ];
    }

    /**
     * @param  array<string, mixed>  $criteria
     * @return list<array{origin: string, destination: string, departure_date: string}>
     */
    protected function buildSlices(array $criteria): array
    {
        $segments = is_array($criteria['segments'] ?? null) ? $criteria['segments'] : [];
        $slices = [];
        foreach ($segments as $segment) {
            if (! is_array($segment)) {
                continue;
            }
            $origin = strtoupper(trim((string) ($segment['origin'] ?? '')));
            $destination = strtoupper(trim((string) ($segment['destination'] ?? '')));
            $departureDate = trim((string) ($segment['departure_date'] ?? ''));
            if ($origin === '' || $destination === '' || $departureDate === '') {
                continue;
            }
            $slices[] = [
                'origin' => $origin,
                'destination' => $destination,
                'departure_date' => $departureDate,
            ];
        }

        return $slices;
    }

    /**
     * @param  list<array<string, mixed>>  $offers
     * @param  array<string, mixed>  $candidate
     * @return array<string, mixed>
     */
    protected function pickPreferredOffer(array $offers, array $candidate): array
    {
        $best = $offers[0];
        foreach (array_slice($offers, 1) as $offer) {
            if ($this->shouldPreferOffer($offer, $best, $candidate)) {
                $best = $offer;
            }
        }

        return $best;
    }

    /**
     * @param  array<string, mixed>  $incoming
     * @param  array<string, mixed>  $existing
     * @param  array<string, mixed>  $candidate
     */
    protected function shouldPreferOffer(array $incoming, array $existing, array $candidate): bool
    {
        $incomingFare = (float) ($incoming['final_customer_price'] ?? $incoming['fare_breakdown']['supplier_total'] ?? PHP_FLOAT_MAX);
        $existingFare = (float) ($existing['final_customer_price'] ?? $existing['fare_breakdown']['supplier_total'] ?? PHP_FLOAT_MAX);
        if ($incomingFare !== $existingFare) {
            return $incomingFare < $existingFare;
        }

        $incomingKey = trim((string) ($incoming['offer_id'] ?? $incoming['id'] ?? ''));
        $existingKey = trim((string) ($existing['offer_id'] ?? $existing['id'] ?? ''));
        $preferredKey = trim((string) ($candidate['source_offer_id'] ?? $candidate['internal_offer_key'] ?? ''));

        if ($preferredKey !== '') {
            $incomingHash = substr(hash('sha256', $incomingKey), 0, 16);
            $existingHash = substr(hash('sha256', $existingKey), 0, 16);
            if ($incomingHash === $preferredKey && $existingHash !== $preferredKey) {
                return true;
            }
            if ($existingHash === $preferredKey && $incomingHash !== $preferredKey) {
                return false;
            }
        }

        return strcmp($incomingKey, $existingKey) < 0;
    }

    /**
     * @param  array<string, mixed>  $offer
     * @param  array<string, mixed>  $candidate
     * @return array<string, mixed>
     */
    protected function enrichOffer(array $offer, array $candidate): array
    {
        return array_merge($offer, [
            'multicity_inquiry_only' => true,
            'multicity_plan_only_not_certified' => true,
            'block_reason' => PublicMulticityInquiryPolicy::BLOCK_REASON,
            'route_by_slice' => is_array($candidate['route_by_slice'] ?? null) ? $candidate['route_by_slice'] : [],
            'full_route_display' => $candidate['full_route_display'] ?? null,
            'carrier_chain' => $candidate['carrier_chain'] ?? null,
            'validating_carrier' => $candidate['validating_carrier'] ?? null,
            'brand_code' => $candidate['brand_code'] ?? null,
            'brand_name' => $candidate['brand_name'] ?? null,
            'classification' => $candidate['classification'] ?? null,
            'internal_offer_key' => $candidate['internal_offer_key'] ?? $candidate['source_offer_id'] ?? null,
            'source_offer_reference' => $candidate['source_offer_id'] ?? null,
            'supplier_offer_key_present' => ($candidate['supplier_offer_key_present'] ?? false) === true,
            'segment_marketing_carriers' => is_array($candidate['segment_marketing_carriers'] ?? null)
                ? $candidate['segment_marketing_carriers']
                : [],
        ]);
    }
}
