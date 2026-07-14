<?php

namespace App\Support\Sabre\Scenario;

use App\Data\FlightSearchRequestData;
use App\Models\SupplierConnection;
use App\Services\Suppliers\Sabre\Core\SabreClient;
use App\Services\Suppliers\Sabre\Gds\SabreFlightSearchNormalizer;
use App\Services\Suppliers\Sabre\SabreFlightSearchRequestBuilder;
use App\Support\FlightSearch\SabreMixedCarrierSearchResultsFilter;
use Throwable;

/**
 * Executes a single true Sabre multi-city BFM shop (one POST, multiple ODIs) for scenario-runner plan mode.
 */
final class SabreGdsLiveScenarioMulticityShopService
{
    public const BLOCK_SHOP_NOT_IMPLEMENTED = 'multicity_shop_not_implemented';

    /** @var list<string> */
    public const IMPLEMENTATION_GAPS_WHEN_UNSUPPORTED = [
        'SabreFlightSearchRequestBuilder::build() must accept trip_type=multi_city with segments',
        'SabreFlightSearchNormalizer must match multi-city leg endpoints',
        'Scenario runner must not stitch separate one-way searches',
    ];

    public function __construct(
        protected SabreFlightSearchRequestBuilder $requestBuilder,
        protected SabreClient $client,
        protected SabreFlightSearchNormalizer $normalizer,
        protected SabreGdsLiveScenarioMulticityCandidateNormalizer $candidateNormalizer,
        protected SabreMixedCarrierSearchResultsFilter $mixedCarrierSearchFilter,
        protected SabreGdsLiveScenarioMulticityCandidateDedupSorter $candidateDedupSorter,
    ) {}

    public function shopRequestSupported(): bool
    {
        return method_exists($this->requestBuilder, 'build')
            && class_exists(FlightSearchRequestData::class);
    }

    /**
     * @param  array{slices: list<array{origin: string, destination: string, departure_date: string}>, adult_count: int, child_count: int, infant_count: int, cabin_app: string}  $multicityInput
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function search(SupplierConnection $connection, array $multicityInput, ?int $candidateLimit = null, array $options = []): array
    {
        $slices = is_array($multicityInput['slices'] ?? null) ? $multicityInput['slices'] : [];
        $sliceCount = count($slices);
        $diagnostics = [
            'multicity_shop_request_supported' => $this->shopRequestSupported(),
            'multicity_slices_valid' => $sliceCount >= SabreGdsLiveScenarioMulticityInputLoader::MIN_SLICES,
            'multicity_slice_count' => $sliceCount,
            'multicity_search_executed' => false,
            'multicity_response_has_gir' => false,
            'multicity_response_offer_count' => 0,
            'multicity_normalized_offer_count' => 0,
            'multicity_candidate_selection_reason' => null,
            'multicity_plan_ready' => false,
            'multicity_block_reason' => null,
        ];

        if (! $diagnostics['multicity_shop_request_supported']) {
            $diagnostics['multicity_block_reason'] = self::BLOCK_SHOP_NOT_IMPLEMENTED;
            $diagnostics['implementation_gaps'] = self::IMPLEMENTATION_GAPS_WHEN_UNSUPPORTED;

            return $this->emptySearchResult($diagnostics);
        }

        if (! $diagnostics['multicity_slices_valid']) {
            $diagnostics['multicity_block_reason'] = 'multicity_slices_invalid';

            return $this->emptySearchResult($diagnostics);
        }

        $segments = [];
        foreach ($slices as $slice) {
            $segments[] = [
                'origin' => (string) ($slice['origin'] ?? ''),
                'destination' => (string) ($slice['destination'] ?? ''),
                'departure_date' => (string) ($slice['departure_date'] ?? ''),
            ];
        }

        $first = $slices[0];
        $request = FlightSearchRequestData::fromArray([
            'trip_type' => 'multi_city',
            'segments' => $segments,
            'origin' => (string) ($first['origin'] ?? ''),
            'destination' => (string) ($first['destination'] ?? ''),
            'departure_date' => (string) ($first['departure_date'] ?? ''),
            'adults' => (int) ($multicityInput['adult_count'] ?? 1),
            'children' => (int) ($multicityInput['child_count'] ?? 0),
            'infants' => (int) ($multicityInput['infant_count'] ?? 0),
            'cabin' => (string) ($multicityInput['cabin_app'] ?? 'economy'),
            'currency' => 'PKR',
        ]);

        try {
            $shopPayload = $this->requestBuilder->build($request, $connection);
            $odiCount = count((array) data_get($shopPayload, 'OTA_AirLowFareSearchRQ.OriginDestinationInformation', []));
            if ($odiCount < $sliceCount) {
                $diagnostics['multicity_block_reason'] = self::BLOCK_SHOP_NOT_IMPLEMENTED;
                $diagnostics['implementation_gaps'] = [
                    'Shop payload OriginDestinationInformation count ('.$odiCount.') < slice count ('.$sliceCount.')',
                ];

                return $this->emptySearchResult($diagnostics);
            }

            $response = $this->client->postShopPayload($connection, $shopPayload);
            $diagnostics['multicity_search_executed'] = true;
        } catch (Throwable) {
            $diagnostics['multicity_block_reason'] = 'multicity_shop_request_failed';

            return $this->emptySearchResult($diagnostics, shopError: 'shop_request_failed');
        }

        $shopHttpStatus = $response->status();
        $json = $response->json();
        if (! $response->successful() || ! is_array($json)) {
            $diagnostics['multicity_block_reason'] = 'multicity_shop_http_error';

            return $this->emptySearchResult($diagnostics, $shopHttpStatus, 'shop_http_error');
        }

        $hasGir = is_array($json['groupedItineraryResponse'] ?? null);
        $diagnostics['multicity_response_has_gir'] = $hasGir;
        if (! $hasGir) {
            $diagnostics['multicity_block_reason'] = 'multicity_response_missing_gir';

            return $this->emptySearchResult($diagnostics, $shopHttpStatus, 'shop_missing_gir');
        }

        $rawOfferCount = $this->countRawItineraries($json);
        $diagnostics['multicity_response_offer_count'] = $rawOfferCount;

        $normalized = $this->normalizer->normalize($json, $connection, $request);
        $diagnostics['multicity_normalized_offer_count'] = count($normalized);

        $candidates = [];
        foreach ($normalized as $offer) {
            $snap = $this->normalizer->mergeSabrePricingLinkageHandoff(
                $this->normalizer->ensureSabreBookingContextOnCachedOffer($offer->toArray())
            );
            $candidates[] = $this->candidateNormalizer->normalize($snap, $slices);
        }

        $preFilterCount = count($candidates);
        $filterResult = $this->mixedCarrierSearchFilter->filterMulticityPlanCandidates($candidates, $options);
        $candidates = $filterResult['candidates'];
        $diagnostics = array_merge($diagnostics, $filterResult['diagnostics']);
        $diagnostics['multicity_normalized_offer_count'] = $preFilterCount;
        $mixedFilterRemoved = $preFilterCount > count($candidates);
        if ($mixedFilterRemoved) {
            $diagnostics['multicity_candidate_selection_reason'] = 'mixed_carrier_policy_filtered';
        }

        $dedupResult = $this->candidateDedupSorter->deduplicateAndSort($candidates);
        $candidates = $dedupResult['candidates'];
        $diagnostics = array_merge($diagnostics, $dedupResult['diagnostics']);
        $dedupRemoved = (int) ($dedupResult['diagnostics']['multicity_duplicate_candidates_removed_count'] ?? 0) > 0;
        if ($dedupRemoved && ! $mixedFilterRemoved) {
            $diagnostics['multicity_candidate_selection_reason'] = 'deduped_display_candidates';
        } elseif ($dedupRemoved && $mixedFilterRemoved) {
            $diagnostics['multicity_candidate_selection_reason'] = 'mixed_carrier_filtered_and_deduped';
        }

        $limit = $candidateLimit !== null ? max(1, $candidateLimit) : null;
        if ($limit !== null && count($candidates) > $limit) {
            $diagnostics['multicity_candidate_selection_reason'] = 'lowest_fare_first';
            $candidates = array_slice($candidates, 0, $limit);
        } elseif ($candidates !== []) {
            $diagnostics['multicity_candidate_selection_reason'] = 'all_normalized_offers';
        } else {
            $diagnostics['multicity_candidate_selection_reason'] = 'no_matching_multicity_offers';
            if ($this->mixedCarrierSearchFilter->allOffersFilteredByPolicy($filterResult['diagnostics'])) {
                $diagnostics['multicity_block_reason'] = SabreMixedCarrierSearchResultsFilter::BLOCK_REASON_MULTICITY_ALL_FILTERED;
                $diagnostics['customer_message'] = SabreMixedCarrierSearchResultsFilter::EMPTY_MULTICITY_RESULTS_CUSTOMER_MESSAGE;
                $diagnostics['admin_debug_message'] = SabreMixedCarrierSearchResultsFilter::MULTICITY_ALL_FILTERED_ADMIN_MESSAGE;
            } else {
                $diagnostics['multicity_block_reason'] = 'multicity_no_eligible_offers';
            }
        }

        $diagnostics['multicity_plan_ready'] = $candidates !== [];

        return [
            'shop_http_status' => $shopHttpStatus,
            'shop_error' => null,
            'eligible_offer_count' => count($candidates),
            'candidate_count' => count($candidates),
            'candidates' => $candidates,
            'diagnostics' => $diagnostics,
            'automatic_booking_allowed' => false,
            'pnr_attempted' => false,
            'ticketing_attempted' => false,
            'airticket_attempted' => false,
            'cancellation_attempted' => false,
        ];
    }

    /**
     * @param  array<string, mixed>  $diagnostics
     * @return array<string, mixed>
     */
    protected function emptySearchResult(
        array $diagnostics,
        int $shopHttpStatus = 0,
        ?string $shopError = null,
    ): array {
        return [
            'shop_http_status' => $shopHttpStatus,
            'shop_error' => $shopError,
            'eligible_offer_count' => 0,
            'candidate_count' => 0,
            'candidates' => [],
            'diagnostics' => $diagnostics,
            'automatic_booking_allowed' => false,
            'pnr_attempted' => false,
            'ticketing_attempted' => false,
            'airticket_attempted' => false,
            'cancellation_attempted' => false,
        ];
    }

    /**
     * @param  array<string, mixed>  $json
     */
    protected function countRawItineraries(array $json): int
    {
        $groups = data_get($json, 'groupedItineraryResponse.itineraryGroups', []);
        if (! is_array($groups)) {
            return 0;
        }
        $count = 0;
        foreach ($groups as $group) {
            if (! is_array($group)) {
                continue;
            }
            $itins = $group['itineraries'] ?? [];
            if (is_array($itins)) {
                $count += count($itins);
            }
        }

        return $count;
    }
}
