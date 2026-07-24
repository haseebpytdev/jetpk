<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Http\Requests\PublicFlightSearchRequest;
use App\Http\Requests\StoreMulticityInquiryRequest;
use App\Models\Agency;
use App\Models\Airport;
use App\Models\FlightSearchMarketingSnapshot;
use App\Models\User;
use App\Services\FlightSearch\FlightDeparturePolicy;
use App\Services\FlightSearch\FlightSearchResultStore;
use App\Services\FlightSearch\FlightSearchService;
use App\Services\FlightSearch\NearbyDateFareStripService;
use App\Services\FlightSearch\ReturnSplitComboService;
use App\Services\Support\SupportTicketService;
use App\Enums\SupportTicketCategory;
use App\Services\Suppliers\Iati\IatiSelectedOfferRevalidationGate;
use App\Services\Suppliers\Sabre\Gds\SabreSelectedOfferRevalidationGate;
use App\Services\TravelData\AirlineBrandingService;
use App\Support\Booking\AgentBookingContext;
use App\Support\Client\ClientCheckoutContextResolver;
use App\Support\Bookings\CheckoutFareBreakdownPresenter;
use App\Support\FlightSearch\AirlineDisplayNameResolver;
use App\Support\FlightSearch\FlightOfferDisplayPresenter;
use App\Support\FlightSearch\ItineraryFareConsolidator;
use App\Support\FlightSearch\PublicFlightSearchSecurity;
use App\Support\FlightSearch\PublicMulticityInquiryPolicy;
use App\Support\FlightSearch\SabreFareVerificationDigest;
use App\Support\FlightSearch\SabreMixedCarrierSearchResultsFilter;
use App\Support\FlightSearch\SabreOfferFreshness;
use App\Support\Suppliers\SupplierSourcePresenter;
use Carbon\Carbon;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ViewErrorBag;
use Illuminate\View\View;
use Throwable;

class FlightController extends Controller
{
    public function __construct(
        protected FlightSearchService $flightSearch,
        protected FlightSearchResultStore $searchStore,
        protected AirlineBrandingService $airlineBranding,
        protected FlightDeparturePolicy $departurePolicy,
        protected SabreSelectedOfferRevalidationGate $sabreSelectedOfferRevalidationGate,
        protected IatiSelectedOfferRevalidationGate $iatiSelectedOfferRevalidationGate,
        protected SabreOfferFreshness $sabreOfferFreshness,
        protected ReturnSplitComboService $returnSplitComboService,
        protected NearbyDateFareStripService $nearbyDateFareStripService,
        protected SupportTicketService $supportTicketService,
    ) {}

    public function search(Request $request): View
    {
        return view('frontend.flights.search', [
            'defaults' => [
                'origin' => $request->string('from')->toString(),
                'destination' => $request->string('to')->toString(),
                'depart' => $request->string('depart')->toString(),
                'return_date' => $request->string('return_date')->toString(),
                'trip_type' => $request->string('trip_type', 'one_way')->toString(),
            ],
            'minDate' => now()->format('Y-m-d'),
        ]);
    }

    public function results(PublicFlightSearchRequest $request): View
    {
        $criteria = $request->criteria();
        [$result, $searchId, $warnings] = $this->runSearch($criteria, $request);

        $providers = collect($result['offers'] ?? [])
            ->map(fn (array $offer): string => (string) ($offer['supplier_provider'] ?? 'unknown'))
            ->countBy()
            ->all();
        $supplierTotals = collect($result['offers'])->map(function (array $o): float {
            $fb = $o['fare_breakdown'] ?? null;

            return is_array($fb) && isset($fb['supplier_total'])
                ? (float) $fb['supplier_total']
                : (float) (($o['base_fare'] ?? 0) + ($o['taxes'] ?? 0));
        });
        $finalTotals = collect($result['offers'])->map(fn (array $o): float => (float) ($o['final_customer_price'] ?? 0));
        $sabreDigestCount = 0;
        $fareMismatchCount = 0;
        $staleCandidateCount = 0;
        foreach ($result['offers'] ?? [] as $o) {
            if (! is_array($o) || strtolower((string) ($o['supplier_provider'] ?? '')) !== 'sabre') {
                continue;
            }
            $dig = $o['fare_verification_digest'] ?? null;
            if (! is_array($dig)) {
                continue;
            }
            $sabreDigestCount++;
            $st = (string) ($dig['fare_verification_status'] ?? '');
            if (in_array($st, [
                SabreFareVerificationDigest::STATUS_PRICE_MISMATCH,
                SabreFareVerificationDigest::STATUS_NORMALIZER_RAW_MISMATCH,
            ], true)) {
                $fareMismatchCount++;
            }
            if (! empty($dig['stale_cached_result_possible'])) {
                $staleCandidateCount++;
            }
        }
        Log::info('flight_search.fare_verification', [
            'search_id' => $searchId,
            'offer_count' => count($result['offers'] ?? []),
            'sabre_digest_count' => $sabreDigestCount,
            'mismatch_count' => $fareMismatchCount,
            'stale_candidate_count' => $staleCandidateCount,
        ]);
        Log::info('flight_search.completed', [
            'providers' => $providers,
            'offers_count' => count($result['offers']),
            'min_supplier_total' => $supplierTotals->isEmpty() ? null : $supplierTotals->min(),
            'min_final_customer_price' => $finalTotals->isEmpty() ? null : $finalTotals->min(),
            'currency' => collect($result['offers'])->pluck('currency')->filter()->first(),
            'conversion_statuses' => collect($result['offers'])->pluck('conversion_status')->filter()->values()->all(),
            'offer_pricing_preview' => collect($result['offers'])->take(5)->map(function (array $offer): array {
                return [
                    'provider' => (string) ($offer['supplier_provider'] ?? 'unknown'),
                    'supplier_currency' => (string) ($offer['supplier_currency'] ?? $offer['currency'] ?? ''),
                    'supplier_total' => (float) ($offer['supplier_total_source'] ?? (($offer['base_fare'] ?? 0) + ($offer['taxes'] ?? 0))),
                    'pricing_currency' => (string) ($offer['pricing_currency'] ?? $offer['currency'] ?? ''),
                    'markup' => (float) ($offer['markup'] ?? 0),
                    'service_fee' => (float) ($offer['service_fee'] ?? 0),
                    'final_customer_price' => (float) ($offer['final_customer_price'] ?? 0),
                    'conversion_status' => (string) ($offer['conversion_status'] ?? 'unknown'),
                ];
            })->values()->all(),
            'duration_ms' => null,
        ]);

        $viewData = [
            'criteria' => $criteria,
            'searchId' => $searchId,
            'warnings' => $warnings,
            'searchSummary' => $this->formatSearchSummary($criteria),
            'inlineDisplay' => $this->buildInlineSearchDisplay($criteria),
            'debugFares' => PublicFlightSearchSecurity::allowsDebugFares($request),
            'offerFreshnessRefresh' => [
                'required' => (bool) $request->session()->get('offer_freshness_refresh_required', false),
                'selected_offer_id' => trim((string) $request->session()->get('offer_freshness_selected_offer_id', '')),
                'block_code' => trim((string) $request->session()->get('offer_freshness_block_code', '')),
                'message' => $this->sessionFlightIdErrorMessage($request),
            ],
            'revalidateOfferUrl' => client_route('flights.results.revalidate-offer'),
            'nearbyDatesUrl' => client_route('flights.results.nearby-dates'),
            'returnSplitFlow' => $this->searchStore->returnSplitFlowActive($searchId),
            'returnOptionsDataUrl' => client_route('flights.return-options.data'),
            'selectReturnComboUrl' => client_route('flights.select-return-combo'),
            'agentBookingModeActive' => AgentBookingContext::isActive($request),
            'agentBookingAgencyName' => AgentBookingContext::agencyDisplayName($request) ?? '',
            'resultsEmptyPolicyMessage' => PublicMulticityInquiryPolicy::isMulticitySearch($criteria)
                ? SabreMixedCarrierSearchResultsFilter::EMPTY_MULTICITY_RESULTS_CUSTOMER_MESSAGE
                : SabreMixedCarrierSearchResultsFilter::EMPTY_RESULTS_CUSTOMER_MESSAGE,
            'isMulticityInquiryOnly' => PublicMulticityInquiryPolicy::isMulticitySearch($criteria),
            'multicityInquiryNotice' => PublicMulticityInquiryPolicy::INQUIRY_NOTICE,
            'multicityInquiryUrl' => client_route('flights.multicity.inquiry'),
        ];

        return view(client_view('frontend.flights.results', 'frontend'), $viewData);
    }

    public function resultsSearchData(PublicFlightSearchRequest $request): JsonResponse
    {
        $criteria = $request->criteria();
        [, $searchId, $warnings] = $this->runSearch($criteria, $request);

        $freshness = app(SabreOfferFreshness::class);

        return response()->json([
            'search_id' => $searchId,
            'summary' => [
                'text' => $this->formatSearchSummary($criteria),
            ],
            'inline_display' => $this->buildInlineSearchDisplay($criteria),
            'criteria' => $criteria,
            'warnings' => $warnings,
            'initial_results_url' => client_route('flights.results.data', ['search_id' => $searchId]),
            'search_freshness' => $freshness->sanitizeForCustomerApi($freshness->buildSearchFreshnessMeta([
                'search_created_at' => now()->toIso8601String(),
                'created_at' => now()->toIso8601String(),
            ])),
        ]);
    }

    public function revalidateSelectedOffer(Request $request): JsonResponse
    {
        $searchId = trim((string) $request->input('search_id', ''));
        $offerId = trim((string) $request->input('offer_id', $request->input('flight_id', '')));

        if ($searchId === '' || $offerId === '') {
            return response()->json([
                'success' => false,
                'status' => 'invalid_request',
                'message' => (string) __('This fare needs to be refreshed because airline prices and availability can change quickly.'),
            ], 422);
        }

        if (! PublicFlightSearchSecurity::isValidSearchId($searchId)) {
            return response()->json([
                'success' => false,
                'status' => 'invalid_search',
                'message' => (string) __('This fare search has expired. Please search again.'),
            ], 410);
        }

        $payload = $this->searchStore->get($searchId);
        if ($payload === null) {
            return response()->json([
                'success' => false,
                'status' => 'expired_search',
                'message' => (string) __('This fare search has expired. Please search again.'),
            ], 410);
        }

        $offer = $this->searchStore->findOffer($searchId, $offerId);
        if ($offer === null) {
            return response()->json([
                'success' => false,
                'status' => 'offer_not_found',
                'message' => (string) __('We could not confirm this fare with the airline. Please refresh your search or choose another option.'),
            ], 404);
        }

        if (strcasecmp((string) ($offer['supplier_provider'] ?? ''), 'iati') === 0) {
            return $this->revalidateIatiSelectedOffer($request, $searchId, $offerId, $offer, $payload);
        }

        if (strcasecmp((string) ($offer['supplier_provider'] ?? ''), 'sabre') !== 0) {
            return response()->json([
                'success' => false,
                'status' => 'unsupported_supplier',
                'message' => (string) __('This fare needs to be refreshed because airline prices and availability can change quickly.'),
            ], 422);
        }

        $channel = AgentBookingContext::resolveCheckoutChannel($request);
        $agency = $channel['agency'];
        if ($agency === null) {
            return response()->json([
                'success' => false,
                'status' => 'unavailable',
                'message' => (string) __('Booking is temporarily unavailable.'),
            ], 503);
        }

        $criteria = is_array($payload['criteria'] ?? null) ? $payload['criteria'] : [];
        $refresh = $this->sabreSelectedOfferRevalidationGate->refreshSelectedOffer(
            $agency,
            $offer,
            $criteria,
            $payload,
        );

        if (! ($refresh['success'] ?? false)
            && ($refresh['status'] ?? '') === 'search_refresh_required') {
            $refresh = $this->refreshSelectedOfferViaSearch($agency, $searchId, $offerId, $offer, $criteria, $payload);
        }

        if (($refresh['success'] ?? false) === true) {
            $metaPatch = is_array($refresh['meta_patch'] ?? null) ? $refresh['meta_patch'] : [];
            $this->searchStore->patchOfferRevalidationMeta($searchId, $offerId, $metaPatch);
            $payload = $this->searchStore->get($searchId);
            $offer = $this->searchStore->findOffer($searchId, $offerId) ?? $offer;
            $freshness = app(SabreOfferFreshness::class);
            $freshnessMeta = $freshness->sanitizeForCustomerApi(
                $freshness->buildOfferFreshnessMeta($offer, $payload, $metaPatch),
            );

            Log::info('flight_search.selected_offer_refresh.success', [
                'search_id' => $searchId,
                'offer_id' => $offerId,
                'reason' => (string) ($metaPatch['selected_offer_refresh_reason'] ?? ''),
            ]);

            return response()->json([
                'success' => true,
                'status' => 'success',
                'message' => (string) ($refresh['message'] ?? $freshness->customerSafeMessage('refresh_search_success')),
                'offer_freshness' => $freshnessMeta,
                'search_freshness' => $freshness->sanitizeForCustomerApi($freshness->buildSearchFreshnessMeta($payload)),
                'passengers_url' => $this->buildCustomerSelectUrl(
                    $this->offerIsCustomerBookable($offer, $criteria),
                    $offer,
                    $searchId,
                    $criteria,
                ),
            ]);
        }

        $metaPatch = is_array($refresh['meta_patch'] ?? null) ? $refresh['meta_patch'] : [];
        if ($metaPatch !== []) {
            $this->searchStore->patchOfferRevalidationMeta($searchId, $offerId, $metaPatch);
        }

        $freshness = app(SabreOfferFreshness::class);
        $offer = $this->searchStore->findOffer($searchId, $offerId) ?? $offer;
        $freshnessMeta = $freshness->sanitizeForCustomerApi(
            $freshness->buildOfferFreshnessMeta($offer, $payload, $metaPatch),
        );

        Log::info('flight_search.selected_offer_refresh.failed', [
            'search_id' => $searchId,
            'offer_id' => $offerId,
            'block_code' => (string) ($refresh['block_code'] ?? ''),
            'diagnostic' => (string) ($refresh['diagnostic'] ?? ''),
        ]);

        return response()->json([
            'success' => false,
            'status' => (string) ($refresh['status'] ?? 'failed'),
            'message' => (string) ($refresh['message'] ?? $freshness->customerSafeMessage('selected_offer_revalidation_failed')),
            'offer_freshness' => $freshnessMeta,
        ], 422);
    }

    /**
     * IATI fare confirmation (/fare) for a selected public offer — no booking/ticket/payment.
     */
    protected function revalidateIatiSelectedOffer(
        Request $request,
        string $searchId,
        string $offerId,
        array $offer,
        array $payload,
    ): JsonResponse {
        $channel = AgentBookingContext::resolveCheckoutChannel($request);
        $agency = $channel['agency'];
        if ($agency === null) {
            return response()->json([
                'success' => false,
                'status' => 'unavailable',
                'message' => (string) __('Booking is temporarily unavailable.'),
            ], 503);
        }

        $criteria = is_array($payload['criteria'] ?? null) ? $payload['criteria'] : [];
        $selectedFareOptionId = trim((string) $request->input('selected_fare_option_id', ''));
        $selectedFareOptionId = $selectedFareOptionId !== '' ? $selectedFareOptionId : null;

        $refresh = $this->iatiSelectedOfferRevalidationGate->refreshSelectedOffer(
            $agency,
            $offer,
            $criteria,
            $payload,
            $selectedFareOptionId,
            $searchId,
        );

        $revalidation = is_array($refresh['revalidation'] ?? null) ? $refresh['revalidation'] : [];
        $metaPatch = is_array($refresh['meta_patch'] ?? null) ? $refresh['meta_patch'] : [];

        if ($metaPatch !== []) {
            $this->searchStore->patchOfferRevalidationMeta($searchId, $offerId, $metaPatch);
            $validatedFare = is_array($metaPatch['fare_breakdown'] ?? null) ? $metaPatch['fare_breakdown'] : null;
            $validatedRaw = is_array($metaPatch['raw_payload'] ?? null) ? $metaPatch['raw_payload'] : null;
            if ($validatedFare !== null || $validatedRaw !== null) {
                $offerPatch = [];
                if ($validatedFare !== null) {
                    $offerPatch['fare_breakdown'] = $validatedFare;
                }
                if ($validatedRaw !== null) {
                    $offerPatch['raw_payload'] = $validatedRaw;
                }
                $this->searchStore->refreshOfferFromSearch($searchId, $offerId, $offerPatch);
            }
        }

        $offer = $this->searchStore->findOffer($searchId, $offerId) ?? $offer;
        $publicStatus = (string) ($revalidation['revalidation_status'] ?? 'failed');
        $canContinue = in_array($publicStatus, ['valid', 'changed'], true);

        if (($refresh['success'] ?? false) === true && $canContinue) {
            Log::info('flight_search.iati_selected_offer_refresh.success', [
                'search_id' => $searchId,
                'offer_id' => $offerId,
                'revalidation_status' => $publicStatus,
            ]);

            return response()->json([
                'success' => true,
                'status' => 'success',
                'message' => (string) ($refresh['message'] ?? $revalidation['safe_customer_message'] ?? ''),
                'revalidation' => $revalidation,
                'offer_freshness' => [
                    'provider_label' => 'IATI',
                    'offer_freshness_status' => 'revalidated',
                    'revalidation_status' => $publicStatus,
                    'revalidation_required' => false,
                    'price_changed' => (bool) ($revalidation['price_changed'] ?? false),
                ],
                'passengers_url' => $this->buildCustomerSelectUrl(
                    $this->offerIsCustomerBookable($offer, $criteria),
                    $offer,
                    $searchId,
                    $criteria,
                ),
            ]);
        }

        Log::info('flight_search.iati_selected_offer_refresh.failed', [
            'search_id' => $searchId,
            'offer_id' => $offerId,
            'revalidation_status' => $publicStatus,
        ]);

        $httpStatus = $publicStatus === 'expired' ? 410 : 422;

        return response()->json([
            'success' => false,
            'status' => (string) ($refresh['status'] ?? $publicStatus),
            'message' => (string) ($refresh['message'] ?? $revalidation['safe_customer_message'] ?? ''),
            'revalidation' => $revalidation,
            'offer_freshness' => [
                'provider_label' => 'IATI',
                'offer_freshness_status' => $publicStatus === 'expired' ? 'expired' : 'revalidation_failed',
                'revalidation_status' => $publicStatus,
                'revalidation_required' => true,
            ],
        ], $httpStatus);
    }

    public function resultsData(Request $request): JsonResponse
    {
        $searchId = trim((string) $request->query('search_id', ''));
        if ($searchId === '') {
            return PublicFlightSearchSecurity::missingSearchIdResponse();
        }

        if (! PublicFlightSearchSecurity::isValidSearchId($searchId)) {
            return PublicFlightSearchSecurity::invalidSearchIdResponse();
        }

        $payload = $this->searchStore->get($searchId);
        if ($payload === null) {
            return PublicFlightSearchSecurity::expiredSearchIdResponse();
        }

        $debugAllowed = PublicFlightSearchSecurity::allowsDebugFares($request);

        $page = max(1, (int) $request->query('page', 1));
        $perPage = (int) $request->query('per_page', 12);
        if ($perPage < 1) {
            $perPage = 12;
        }
        $perPage = min($perPage, 25);
        $sort = (string) $request->query('sort', 'recommended');
        $filters = [
            'airline' => strtoupper(trim((string) $request->query('airline', ''))),
            'stops' => trim((string) $request->query('stops', '')),
            'refundable' => trim((string) $request->query('refundable', '')),
            'cabin' => trim((string) $request->query('cabin', '')),
            'baggage' => trim((string) $request->query('baggage', '')),
            'departure_window' => trim((string) $request->query('departure_window', '')),
            'arrival_window' => trim((string) $request->query('arrival_window', '')),
            'min_price' => $request->query('min_price'),
            'max_price' => $request->query('max_price'),
            'max_duration' => $request->query('max_duration'),
            'duration_bucket' => trim((string) $request->query('duration_bucket', '')),
            'layover_airport' => strtoupper(trim((string) $request->query('layover_airport', ''))),
            'fare_family' => trim((string) $request->query('fare_family', '')),
            'bookable_only' => trim((string) $request->query('bookable_only', '')),
            'operating_airline' => strtoupper(trim((string) $request->query('operating_airline', ''))),
        ];

        /** @var list<array<string, mixed>> $offers */
        $offers = $this->searchStore->displayOffersFromPayload($payload);
        $critForFilters = is_array($payload['criteria'] ?? null) ? $payload['criteria'] : [];
        $beforeFilter = count($offers);
        $offers = $this->filterOffers($offers, $filters, $critForFilters);
        $hasActiveFilters = collect($filters)->contains(function (mixed $v): bool {
            return $v !== null && $v !== '';
        });
        if ($hasActiveFilters) {
            Log::info('flight_search.pipeline', [
                'stage' => 'ajax_results_client_filter',
                'pre_filter_count' => $beforeFilter,
                'post_filter_count' => count($offers),
                'filter_rejected_count' => $beforeFilter - count($offers),
            ]);
        }
        $offers = $this->sortOffers($offers, $sort, $critForFilters);

        if ($this->searchStore->returnSplitFlowActive($searchId)) {
            return $this->resultsDataReturnSplitOutbound(
                $request,
                $payload,
                $searchId,
                $offers,
                $filters,
                $sort,
                $page,
                $perPage,
                $debugAllowed,
            );
        }

        $total = count($offers);
        $offset = ($page - 1) * $perPage;
        $slice = array_slice($offers, $offset, $perPage);
        $airlineNameMap = AirlineDisplayNameResolver::mapForCodes(
            AirlineDisplayNameResolver::collectCodesFromOffers($offers)
        );
        $filterMeta = $this->buildFilterMeta($offers, $critForFilters, $airlineNameMap);

        $airlineLogos = $this->airlineBranding->mapLogosForOffers($slice);
        $iataCodes = [];
        foreach ($slice as $offRow) {
            $iataCodes = array_merge($iataCodes, FlightOfferDisplayPresenter::collectIataCodes($offRow));
        }
        $cityMap = FlightOfferDisplayPresenter::airportCityMap($iataCodes);

        $sabreUiMismatchCount = 0;
        $sabreUiMismatchSamples = [];

        $data = array_map(function (array $offer) use ($payload, $searchId, $airlineLogos, $cityMap, $airlineNameMap, $request, $debugAllowed, &$sabreUiMismatchCount, &$sabreUiMismatchSamples): array {
            $mapped = $this->mapOfferForResultsApi(
                $offer,
                $payload,
                $searchId,
                $request,
                $airlineLogos,
                $cityMap,
                $airlineNameMap,
                $sabreUiMismatchCount,
                $sabreUiMismatchSamples,
            );

            return PublicFlightSearchSecurity::sanitizeResultsOffer($mapped, $debugAllowed);
        }, $slice);

        $sabreSliceCount = 0;
        foreach ($slice as $o) {
            if (is_array($o) && strtolower((string) ($o['supplier_provider'] ?? '')) === 'sabre') {
                $sabreSliceCount++;
            }
        }
        Log::info('flight_search.ui_price_verification', [
            'provider' => 'sabre',
            'offer_count' => $sabreSliceCount,
            'mismatch_count' => $sabreUiMismatchCount,
            'sample_short_offer_ids' => $sabreUiMismatchSamples,
            'search_id' => $searchId,
            'page' => $page,
        ]);

        $freshness = app(SabreOfferFreshness::class);

        $response = [
            'search_id' => $searchId,
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'has_more' => ($offset + $perPage) < $total,
            'filters' => $filterMeta,
            'offers' => $data,
            'warnings' => [],
            'empty_message' => $this->resolveResultsEmptyMessage($payload, $total),
            'search_freshness' => $freshness->sanitizeForCustomerApi($freshness->buildSearchFreshnessMeta($payload)),
        ];

        if ($debugAllowed) {
            $filter = is_array($payload['mixed_carrier_filter'] ?? null) ? $payload['mixed_carrier_filter'] : [];
            if ($filter !== []) {
                $response['mixed_carrier_filter'] = [
                    'mixed_carrier_filter_enabled' => (bool) ($filter['mixed_carrier_filter_enabled'] ?? false),
                    'offers_before_mixed_filter' => (int) ($filter['offers_before_mixed_filter'] ?? 0),
                    'offers_after_mixed_filter' => (int) ($filter['offers_after_mixed_filter'] ?? 0),
                    'mixed_carrier_offers_filtered_count' => (int) ($filter['mixed_carrier_offers_filtered_count'] ?? 0),
                    'mixed_carrier_filtered_carrier_chains' => is_array($filter['mixed_carrier_filtered_carrier_chains'] ?? null)
                        ? array_slice($filter['mixed_carrier_filtered_carrier_chains'], 0, SabreMixedCarrierSearchResultsFilter::MAX_CARRIER_CHAIN_SAMPLES)
                        : [],
                    'same_carrier_offers_remaining_count' => (int) ($filter['same_carrier_offers_remaining_count'] ?? 0),
                ];
            }
            $multicityDiagnostics = is_array($payload['multicity_diagnostics'] ?? null) ? $payload['multicity_diagnostics'] : [];
            if ($multicityDiagnostics !== []) {
                $response['multicity_diagnostics'] = [
                    'multicity_response_offer_count' => (int) ($multicityDiagnostics['multicity_response_offer_count'] ?? 0),
                    'multicity_normalized_offer_count' => (int) ($multicityDiagnostics['multicity_normalized_offer_count'] ?? 0),
                    'mixed_carrier_offers_filtered_count' => (int) ($multicityDiagnostics['mixed_carrier_offers_filtered_count'] ?? 0),
                    'multicity_candidates_before_dedup' => (int) ($multicityDiagnostics['multicity_candidates_before_dedup'] ?? 0),
                    'multicity_candidates_after_dedup' => (int) ($multicityDiagnostics['multicity_candidates_after_dedup'] ?? 0),
                    'multicity_duplicate_candidates_removed_count' => (int) ($multicityDiagnostics['multicity_duplicate_candidates_removed_count'] ?? 0),
                    'multicity_dedup_enabled' => (bool) ($multicityDiagnostics['multicity_dedup_enabled'] ?? false),
                    'multicity_dedup_key_version' => (string) ($multicityDiagnostics['multicity_dedup_key_version'] ?? ''),
                    'multicity_search_path' => (string) ($multicityDiagnostics['multicity_search_path'] ?? ''),
                ];
            }
        }

        return response()->json($response);
    }

    public function resultsNearbyDates(Request $request): JsonResponse
    {
        $searchId = trim((string) $request->query('search_id', ''));
        if ($searchId === '') {
            return PublicFlightSearchSecurity::missingSearchIdResponse();
        }

        if (! PublicFlightSearchSecurity::isValidSearchId($searchId)) {
            return PublicFlightSearchSecurity::invalidSearchIdResponse();
        }

        $payload = $this->searchStore->get($searchId);
        if ($payload === null) {
            return PublicFlightSearchSecurity::expiredSearchIdResponse();
        }

        $criteria = is_array($payload['criteria'] ?? null) ? $payload['criteria'] : [];
        $channel = AgentBookingContext::resolveCheckoutChannel($request);
        $agency = $channel['agency'];
        if ($agency === null) {
            return response()->json([
                'available' => false,
                'selected_date' => null,
                'dates' => [],
            ]);
        }

        try {
            $strip = $this->nearbyDateFareStripService->buildForCriteria(
                $criteria,
                $agency,
                fn (array $nextCriteria): string => client_route('flights.results', $this->criteriaToResultsQuery($nextCriteria)),
            );
        } catch (Throwable $e) {
            Log::warning('nearby_date_strip.endpoint_failed', [
                'search_id_prefix' => substr($searchId, 0, 8),
                'exception' => $e::class,
            ]);

            return response()->json([
                'available' => false,
                'selected_date' => trim((string) ($criteria['depart_date'] ?? '')) ?: null,
                'dates' => [],
            ]);
        }

        return response()->json($strip);
    }

    public function returnOptions(Request $request): View|RedirectResponse
    {
        $searchId = trim((string) $request->query('search_id', ''));
        $outboundKey = trim((string) $request->query('outbound_key', ''));

        if ($searchId === '' || $outboundKey === '' || ! PublicFlightSearchSecurity::isValidSearchId($searchId)) {
            return redirect()->to(client_home_flight_search_url())
                ->with('offer_warning', __('Please search again to see current availability.'));
        }

        $payload = $this->searchStore->get($searchId);
        if ($payload === null) {
            return redirect()->to(client_home_flight_search_url())
                ->with('offer_warning', __('This fare search has expired. Please search again.'));
        }

        $index = $this->searchStore->getReturnSplitIndex($searchId);
        if ($index === null || ! $this->returnSplitComboService->outboundKeyExists($index, $outboundKey)) {
            $crit = is_array($payload['criteria'] ?? null) ? $payload['criteria'] : [];

            return redirect()->to(client_route('flights.results', $this->criteriaToResultsQuery($crit)))
                ->withErrors(['flight_id' => __('This outbound option is no longer available. Please select another outbound flight.')]);
        }

        $criteria = is_array($payload['criteria'] ?? null) ? $payload['criteria'] : [];

        Log::info('return_outbound_selected', [
            'search_id' => $searchId,
            'outbound_key_prefix' => substr($outboundKey, 0, 8),
        ]);

        $viewData = [
            'criteria' => $criteria,
            'searchId' => $searchId,
            'outboundKey' => $outboundKey,
            'searchSummary' => $this->formatSearchSummary($criteria),
            'returnOptionsDataUrl' => client_route('flights.return-options.data'),
            'selectReturnComboUrl' => client_route('flights.select-return-combo'),
            'resultsUrl' => client_route('flights.results', $this->criteriaToResultsQuery($criteria)),
        ];

        return view(client_view('frontend.flights.return-options', 'frontend'), $viewData);
    }

    public function returnOptionsData(Request $request): JsonResponse
    {
        $searchId = trim((string) $request->query('search_id', ''));
        $outboundKey = trim((string) $request->query('outbound_key', ''));

        if ($searchId === '' || $outboundKey === '') {
            return PublicFlightSearchSecurity::missingSearchIdResponse();
        }

        if (! PublicFlightSearchSecurity::isValidSearchId($searchId)) {
            return PublicFlightSearchSecurity::invalidSearchIdResponse();
        }

        $payload = $this->searchStore->get($searchId);
        if ($payload === null) {
            return PublicFlightSearchSecurity::expiredSearchIdResponse();
        }

        $index = $this->searchStore->getReturnSplitIndex($searchId);
        if ($index === null || ! $this->returnSplitComboService->outboundKeyExists($index, $outboundKey)) {
            return response()->json([
                'success' => false,
                'status' => 'outbound_unavailable',
                'message' => (string) __('This outbound option is no longer available. Please select another outbound flight.'),
            ], 410);
        }

        $criteria = is_array($payload['criteria'] ?? null) ? $payload['criteria'] : [];
        $offers = $this->searchStore->displayOffersFromPayload($payload);

        $airlineNameMap = AirlineDisplayNameResolver::mapForCodes(
            AirlineDisplayNameResolver::collectCodesFromOffers($offers)
        );
        $iataCodes = [];
        foreach ($offers as $offRow) {
            if (is_array($offRow)) {
                $iataCodes = array_merge($iataCodes, FlightOfferDisplayPresenter::collectIataCodes($offRow));
            }
        }
        $cityMap = FlightOfferDisplayPresenter::airportCityMap($iataCodes);
        $airlineLogos = $this->airlineBranding->mapLogosForOffers($offers);

        $built = $this->returnSplitComboService->buildReturnOptions(
            $index,
            $outboundKey,
            $offers,
            $criteria,
            $airlineLogos,
            $cityMap,
            $airlineNameMap,
            $searchId,
        );

        $page = max(1, (int) $request->query('page', 1));
        $perPage = min(max(1, (int) $request->query('per_page', 12)), 25);
        $options = is_array($built['options'] ?? null) ? $built['options'] : [];
        $total = count($options);
        $offset = ($page - 1) * $perPage;
        $slice = array_slice($options, $offset, $perPage);

        $freshness = app(SabreOfferFreshness::class);

        return response()->json([
            'flow' => 'return_split_return',
            'search_id' => $searchId,
            'outbound_key' => $outboundKey,
            'outbound_journey' => $built['outbound_journey'] ?? null,
            'outbound_meta' => [
                'outbound_key' => $outboundKey,
                'from_total_amount' => $built['cheapest_total'] ?? null,
                'cheapest_total' => $built['cheapest_total'] ?? null,
            ],
            'cheapest_total' => $built['cheapest_total'] ?? null,
            'return_options' => $slice,
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'has_more' => ($offset + $perPage) < $total,
            'search_freshness' => $freshness->sanitizeForCustomerApi($freshness->buildSearchFreshnessMeta($payload)),
        ]);
    }

    public function selectReturnCombo(Request $request): RedirectResponse
    {
        $searchId = trim((string) $request->input('search_id', ''));
        $comboId = trim((string) $request->input('combo_id', ''));
        $outboundKey = trim((string) $request->input('outbound_key', ''));

        if ($searchId === '' || $comboId === '' || ! PublicFlightSearchSecurity::isValidSearchId($searchId)) {
            return redirect()->to(client_home_flight_search_url())
                ->with('offer_warning', __('Please search again to see current availability.'));
        }

        $payload = $this->searchStore->get($searchId);
        if ($payload === null) {
            Log::info('return_combo_missing_or_expired', [
                'search_id' => $searchId,
                'combo_id_prefix' => substr($comboId, 0, 8),
                'reason' => 'search_expired',
            ]);

            return redirect()->to(client_home_flight_search_url())
                ->with('offer_warning', __('This fare search has expired. Please search again.'));
        }

        $criteria = is_array($payload['criteria'] ?? null) ? $payload['criteria'] : [];
        $offer = $this->searchStore->findOffer($searchId, $comboId);
        $comboMeta = $this->searchStore->findCombo($searchId, $comboId);

        if ($offer === null || $comboMeta === null) {
            Log::info('return_combo_missing_or_expired', [
                'search_id' => $searchId,
                'combo_id_prefix' => substr($comboId, 0, 8),
                'reason' => 'combo_not_found',
            ]);

            if ($outboundKey !== '' && $this->returnSplitComboService->outboundKeyExists(
                $this->searchStore->getReturnSplitIndex($searchId) ?? [],
                $outboundKey
            )) {
                return redirect()->to(client_route('flights.return-options', [
                    'search_id' => $searchId,
                    'outbound_key' => $outboundKey,
                ]))->withErrors(['flight_id' => __('Fare expired or no longer available. Please select another return option.')]);
            }

            return redirect()->to(client_route('flights.results', $this->criteriaToResultsQuery($criteria)))
                ->withErrors(['flight_id' => __('Fare expired or no longer available. Please select another return option.')]);
        }

        if ($outboundKey !== '' && (string) ($comboMeta['outbound_key'] ?? '') !== $outboundKey) {
            return redirect()->to(client_route('flights.return-options', [
                'search_id' => $searchId,
                'outbound_key' => $outboundKey,
            ]))->withErrors(['flight_id' => __('Fare expired or no longer available. Please select another return option.')]);
        }

        Log::info('return_combo_selected', [
            'search_id' => $searchId,
            'combo_id_prefix' => substr($comboId, 0, 8),
            'outbound_key_prefix' => substr((string) ($comboMeta['outbound_key'] ?? ''), 0, 8),
        ]);

        $canSelect = $this->offerIsCustomerBookable($offer, $criteria);
        $selectUrl = $this->buildCustomerSelectUrl($canSelect, $offer, $searchId, $criteria);
        if ($selectUrl === null) {
            return redirect()->to(client_route('flights.return-options', [
                'search_id' => $searchId,
                'outbound_key' => (string) ($comboMeta['outbound_key'] ?? $outboundKey),
            ]))->withErrors(['flight_id' => __('This fare cannot be booked online right now. Please choose another option.')]);
        }

        $returnFareOptionKey = trim((string) $request->input('fare_option_key', ''));
        $outboundFareOptionKey = trim((string) $request->input('outbound_fare_option_key', ''));
        $resolvedOutboundKey = trim((string) ($comboMeta['outbound_key'] ?? $outboundKey));

        $checkoutQuery = array_filter([
            'fare_option_key' => $returnFareOptionKey,
            'return_fare_option_key' => $returnFareOptionKey,
            'outbound_fare_option_key' => $outboundFareOptionKey,
            'outbound_key' => $resolvedOutboundKey,
            'combo_id' => $comboId,
        ], static fn (string $v): bool => $v !== '');

        foreach ($checkoutQuery as $key => $value) {
            $separator = str_contains($selectUrl, '?') ? '&' : '?';
            $selectUrl .= $separator.rawurlencode($key).'='.rawurlencode($value);
        }

        if ($request->hasSession()) {
            $request->session()->put('return_split_selected_combo_id', $comboId);
            $request->session()->put('return_split_search_id', $searchId);
            $request->session()->put('return_split_outbound_key', $resolvedOutboundKey);
            $request->session()->put('return_split_outbound_fare_option_key', $outboundFareOptionKey);
            $request->session()->put('return_split_return_fare_option_key', $returnFareOptionKey);
        }

        return redirect()->to($selectUrl);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  list<array<string, mixed>>  $offers
     * @param  array<string, mixed>  $filters
     */
    protected function resultsDataReturnSplitOutbound(
        Request $request,
        array $payload,
        string $searchId,
        array $offers,
        array $filters,
        string $sort,
        int $page,
        int $perPage,
        bool $debugAllowed,
    ): JsonResponse {
        $criteria = is_array($payload['criteria'] ?? null) ? $payload['criteria'] : [];
        $index = $this->searchStore->getReturnSplitIndex($searchId) ?? [];

        $airlineNameMap = AirlineDisplayNameResolver::mapForCodes(
            AirlineDisplayNameResolver::collectCodesFromOffers($offers)
        );
        $filterMeta = $this->buildFilterMeta($offers, $criteria, $airlineNameMap);

        $airlineLogos = $this->airlineBranding->mapLogosForOffers($offers);
        $iataCodes = [];
        foreach ($offers as $offRow) {
            if (is_array($offRow)) {
                $iataCodes = array_merge($iataCodes, FlightOfferDisplayPresenter::collectIataCodes($offRow));
            }
        }
        $cityMap = FlightOfferDisplayPresenter::airportCityMap($iataCodes);

        $outboundOptions = $this->returnSplitComboService->buildOutboundOptions(
            $index,
            $offers,
            $criteria,
            $airlineLogos,
            $cityMap,
            $airlineNameMap,
            $searchId,
        );

        if ($sort === 'cheapest') {
            // already sorted by price in service
        } elseif ($sort === 'fastest') {
            usort($outboundOptions, function (array $a, array $b): int {
                $da = (int) data_get($a, 'journey_display.duration_minutes', PHP_INT_MAX);
                $db = (int) data_get($b, 'journey_display.duration_minutes', PHP_INT_MAX);

                return $da <=> $db;
            });
        } elseif ($sort === 'earliest_departure') {
            usort($outboundOptions, function (array $a, array $b): int {
                return strcmp(
                    (string) data_get($a, 'journey_display.departure_time_display', ''),
                    (string) data_get($b, 'journey_display.departure_time_display', '')
                );
            });
        }

        $total = count($outboundOptions);
        $offset = ($page - 1) * $perPage;
        $slice = array_slice($outboundOptions, $offset, $perPage);
        $freshness = app(SabreOfferFreshness::class);

        return response()->json([
            'flow' => 'return_split_outbound',
            'search_id' => $searchId,
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'has_more' => ($offset + $perPage) < $total,
            'filters' => $filterMeta,
            'outbound_options' => $slice,
            'warnings' => [],
            'search_freshness' => $freshness->sanitizeForCustomerApi($freshness->buildSearchFreshnessMeta($payload)),
        ]);
    }

    public function resultsOfferDetails(Request $request): View|RedirectResponse
    {
        $searchId = trim((string) $request->query('search_id', ''));

        if ($searchId !== '' && ! PublicFlightSearchSecurity::isValidSearchId($searchId)) {
            abort(404);
        }

        if ($searchId !== '') {
            $payload = $this->searchStore->get($searchId);
            $crit = is_array($payload['criteria'] ?? null) ? $payload['criteria'] : [];

            return $this->redirectSelectedOfferWarning($crit);
        }

        return $this->redirectSelectedOfferWarning();
    }

    /**
     * @param  array<string, mixed>  $offer
     * @param  array<string, mixed>  $payload
     * @param  array<string, string|null>  $airlineLogos
     * @param  array<string, string|null>  $cityMap
     * @param  array<string, string>  $airlineNameMap
     * @return array<string, mixed>
     */
    protected function mapOfferForResultsApi(
        array $offer,
        array $payload,
        string $searchId,
        Request $request,
        array $airlineLogos,
        array $cityMap,
        array $airlineNameMap,
        int &$sabreUiMismatchCount = 0,
        array &$sabreUiMismatchSamples = [],
    ): array {
        $crit = is_array($payload['criteria'] ?? null) ? $payload['criteria'] : [];
        $code = strtoupper((string) ($offer['airline_code'] ?? ($offer['carrier_code'] ?? '')));
        $airlineDisplayName = AirlineDisplayNameResolver::resolveForOffer($offer, $airlineNameMap);
        $supplierTotal = (float) ($offer['supplier_total_source'] ?? (($offer['base_fare'] ?? 0) + ($offer['taxes'] ?? 0)));
        $markup = (float) ($offer['markup'] ?? 0);
        $serviceFee = (float) ($offer['service_fee'] ?? 0);
        $final = (float) ($offer['final_customer_price'] ?? 0);
        $pricingCurrency = strtoupper((string) ($offer['pricing_currency'] ?? $offer['currency'] ?? 'PKR'));
        $conversionStatus = (string) ($offer['conversion_status'] ?? 'same_currency');
        $hasPkrFare = $this->offerHasConfirmedPkrFare($offer);
        $canSelect = $this->offerIsCustomerBookable($offer, $crit);
        $isMulticityInquiry = PublicMulticityInquiryPolicy::blocksAutomaticCheckout($crit, $offer);
        if ($isMulticityInquiry) {
            $canSelect = false;
        }
        $displayedPrice = ($hasPkrFare && $final > 0) ? (int) round($final) : null;
        $hasConfirmedPkrQuote = $displayedPrice !== null && $displayedPrice > 0;
        $priceDisplay = $hasConfirmedPkrQuote
            ? number_format((float) $displayedPrice, 0).' PKR'
            : 'Fare unavailable';
        $priceNote = ! $hasPkrFare
            ? ($conversionStatus === 'conversion_missing'
                ? 'Fares are quoted in Pakistani Rupees (PKR). PKR pricing could not be confirmed for this option—contact support.'
                : 'Fares are quoted in Pakistani Rupees (PKR). This option cannot be priced in PKR online.')
            : ($canSelect
                ? (($markup + $serviceFee) > 0
                    ? 'Total in PKR including taxes & fees and agency charges.'
                    : 'Total in PKR including taxes & fees.')
                : FlightDeparturePolicy::SAME_DAY_LEAD_MESSAGE);

        $disabledReason = $canSelect
            ? null
            : (! $hasPkrFare
                ? ($conversionStatus === 'conversion_missing'
                    ? 'PKR fare not confirmed for this option.'
                    : 'PKR fare not available online for this option.')
                : FlightDeparturePolicy::SAME_DAY_LEAD_MESSAGE);

        $providerLc = strtolower((string) ($offer['supplier_provider'] ?? ''));
        if ($providerLc === 'sabre' && ! $hasConfirmedPkrQuote) {
            Log::warning('price_display_missing_final_customer_price', [
                'short_offer_id' => SabreFareVerificationDigest::shortOfferId((string) ($offer['offer_id'] ?? $offer['id'] ?? '')),
                'supplier_total_source' => (float) ($offer['supplier_total_source'] ?? 0),
                'final_customer_price' => $final,
                'pricing_currency' => $pricingCurrency,
                'conversion_status' => $conversionStatus,
            ]);
        }

        if ($providerLc === 'sabre' && $hasConfirmedPkrQuote) {
            $pricedRollup = (int) round($supplierTotal + $markup + $serviceFee);
            $expectedUi = isset($offer['expected_ui_price']) ? (int) round((float) $offer['expected_ui_price']) : null;
            $mathMismatch = abs($displayedPrice - $pricedRollup) > 2;
            $digestMismatch = $expectedUi !== null && $expectedUi > 0 && abs($displayedPrice - $expectedUi) > 2;
            if ($mathMismatch || $digestMismatch) {
                $sabreUiMismatchCount++;
                if (count($sabreUiMismatchSamples) < 8) {
                    $sabreUiMismatchSamples[] = SabreFareVerificationDigest::shortOfferId((string) ($offer['offer_id'] ?? $offer['id'] ?? ''));
                }
            }
        }

        $presentation = FlightOfferDisplayPresenter::buildPresentation($offer, $crit, $cityMap, $airlineNameMap);
        $segmentsFormatted = $presentation['segments_display'];
        unset($presentation['segments_display']);

        $durationLabel = (string) ($presentation['itinerary_duration_display'] ?? '');
        if ($durationLabel === '') {
            $durationLabel = ((int) ($offer['duration_h'] ?? 0)).'h '.str_pad((string) ((int) ($offer['duration_m'] ?? 0)), 2, '0', STR_PAD_LEFT).'m';
        }

        $row = array_merge([
            'offer_id' => (string) ($offer['id'] ?? $offer['offer_id'] ?? ''),
            'supplier_provider' => (string) ($offer['supplier_provider'] ?? ''),
            'provider' => (string) ($offer['supplier_provider'] ?? 'unknown'),
            'supplier_source_label' => (string) ($offer['supplier_source_label'] ?? SupplierSourcePresenter::labelForOffer(
                (string) ($offer['supplier_provider'] ?? ''),
                isset($offer['source_type']) ? (string) $offer['source_type'] : null,
                isset($offer['provider_channel']) ? (string) $offer['provider_channel'] : ($offer['distribution_channel'] ?? null),
                null,
            )),
            'airline_code' => $code,
            'airline_name' => $airlineDisplayName,
            'airline_logo_url' => $airlineLogos[$code] ?? null,
            'route' => ($payload['criteria']['origin'] ?? '').' → '.($payload['criteria']['destination'] ?? ''),
            'departure_time' => $presentation['departure_time_display'],
            'arrival_time' => $presentation['arrival_time_display'],
            'duration' => $durationLabel,
            'stops' => (int) ($offer['stops'] ?? 0),
            'baggage' => (string) ($offer['baggage'] ?? ''),
            'refundable' => (bool) ($offer['refundable'] ?? false),
            'currency' => (string) ($offer['currency'] ?? 'PKR'),
            'supplier_currency' => (string) ($offer['supplier_currency'] ?? $pricingCurrency),
            'supplier_total' => $supplierTotal,
            'base_fare' => (float) ($offer['base_fare'] ?? 0),
            'taxes' => (float) ($offer['taxes'] ?? 0),
            'passenger_pricing' => is_array(data_get($offer, 'fare_breakdown.passenger_pricing'))
                ? data_get($offer, 'fare_breakdown.passenger_pricing')
                : null,
            'passenger_pricing_available' => (bool) (
                data_get($offer, 'fare_breakdown.passenger_pricing_available')
                ?? (is_array(data_get($offer, 'fare_breakdown.passenger_pricing'))
                    && data_get($offer, 'fare_breakdown.passenger_pricing') !== [])
            ),
            'passenger_pricing_trusted' => CheckoutFareBreakdownPresenter::passengerPricingTrustedForResultsRow([
                'passenger_pricing' => is_array(data_get($offer, 'fare_breakdown.passenger_pricing'))
                    ? data_get($offer, 'fare_breakdown.passenger_pricing')
                    : null,
                'passenger_pricing_available' => (bool) (
                    data_get($offer, 'fare_breakdown.passenger_pricing_available')
                    ?? (is_array(data_get($offer, 'fare_breakdown.passenger_pricing'))
                        && data_get($offer, 'fare_breakdown.passenger_pricing') !== [])
                ),
                'pricing_currency' => $pricingCurrency,
                'conversion_status' => $conversionStatus,
            ]),
            'passenger_counts' => is_array(data_get($offer, 'fare_breakdown.passenger_counts'))
                ? data_get($offer, 'fare_breakdown.passenger_counts')
                : [],
            'markup' => $markup,
            'service_fee' => $serviceFee,
            'final_customer_price' => $final,
            'displayed_price' => $displayedPrice,
            'has_confirmed_pkr_quote' => $hasConfirmedPkrQuote,
            'pricing_currency' => $pricingCurrency,
            'conversion_status' => $conversionStatus,
            'fx_rate' => $offer['pricing_components']['fx_rate'] ?? null,
            'price_display' => $priceDisplay,
            'price_note' => $priceNote,
            'can_book' => $canSelect,
            'disabled_reason' => $isMulticityInquiry
                ? PublicMulticityInquiryPolicy::INQUIRY_NOTICE
                : $disabledReason,
            'multicity_inquiry_only' => $isMulticityInquiry,
            'inquiry_only_notice' => $isMulticityInquiry ? PublicMulticityInquiryPolicy::INQUIRY_NOTICE : null,
            'inquiry_url' => $isMulticityInquiry ? client_route('flights.multicity.inquiry') : null,
            'block_reason' => $isMulticityInquiry ? PublicMulticityInquiryPolicy::BLOCK_REASON : null,
            'route_by_slice' => is_array($offer['route_by_slice'] ?? null) ? $offer['route_by_slice'] : null,
            'full_route_display' => $offer['full_route_display'] ?? null,
            'carrier_chain' => $offer['carrier_chain'] ?? ($presentation['marketing_carrier_chain_display'] ?? null),
            'brand_code' => $offer['brand_code'] ?? null,
            'brand_name' => $offer['brand_name'] ?? null,
            'supplier_offer_key_present' => ($offer['supplier_offer_key_present'] ?? false) === true,
            'flight_number' => (string) ($offer['flight_number'] ?? ''),
            'cabin' => (string) ($offer['cabin'] ?? ''),
            'fare_family' => (string) ($offer['fare_family'] ?? ''),
            'refund_rule' => trim((string) ($offer['refund_rule'] ?? '')),
            'change_rule' => trim((string) ($offer['change_rule'] ?? '')),
            'discount' => (float) ($offer['discount'] ?? 0),
            'operating_airline_code' => strtoupper((string) ($offer['operating_carrier_code'] ?? $offer['operating_airline_code'] ?? '')),
            'operating_airline_name' => self::resolveOperatingAirlineName($offer, $airlineNameMap),
            'seats_left' => isset($offer['seats_left']) ? (int) $offer['seats_left'] : null,
            'segments' => $segmentsFormatted,
            'details_url' => client_route('flights.results.offer', ['search_id' => $searchId, 'offer_id' => (string) ($offer['id'] ?? $offer['offer_id'] ?? '')]),
            'select_url' => $this->buildCustomerSelectUrl($canSelect, $offer, $searchId, $crit),
        ], $presentation);

        if ($providerLc === 'sabre') {
            $freshness = app(SabreOfferFreshness::class);
            $row['offer_freshness'] = $freshness->sanitizeForCustomerApi(
                $freshness->buildOfferFreshnessMeta($offer, $payload),
            );
        }

        if ($providerLc === 'iati') {
            $row['offer_freshness'] = [
                'provider_label' => 'IATI',
                'offer_freshness_status' => 'search_snapshot',
                'revalidation_required' => true,
                'revalidation_note' => 'Airline price validation is required before booking.',
                'search_age_display' => 'From current search results',
            ];
        }

        if (PublicFlightSearchSecurity::allowsDebugFares($request)) {
            $dig = is_array($offer['fare_verification_digest'] ?? null) ? $offer['fare_verification_digest'] : null;
            if ($dig !== null) {
                $row['fare_debug'] = array_merge(
                    SabreFareVerificationDigest::fareDebugForApi($dig),
                    [
                        'displayed_price' => $displayedPrice,
                        'fare_verification_status' => (string) ($dig['fare_verification_status'] ?? ''),
                    ]
                );
            }
        }

        return $row;
    }

    /**
     * @param  array<string, mixed>  $offer
     * @param  array<string, mixed>  $criteria
     */
    /**
     * @param  array<string, mixed>  $offer
     * @param  array<string, mixed>  $criteria
     * @param  array<string, mixed>|null  $searchPayload
     * @return array<string, mixed>
     */
    protected function refreshSelectedOfferViaSearch(
        Agency $agency,
        string $searchId,
        string $offerId,
        array $offer,
        array $criteria,
        ?array $searchPayload,
    ): array {
        $freshness = app(SabreOfferFreshness::class);
        $channel = AgentBookingContext::resolveCheckoutChannel(request());
        $result = $this->flightSearch->searchWithMeta(
            array_merge($criteria, ['search_id' => $searchId]),
            $agency,
            $channel['source_channel'],
            $channel['agent_id'],
        );
        $offers = is_array($result['offers'] ?? null) ? $result['offers'] : [];
        $brandedContext = [];
        $intent = is_array($offer['selected_fare_family_option'] ?? null) ? $offer['selected_fare_family_option'] : [];
        $fareOptionKey = trim((string) ($offer['fare_option_key'] ?? ''));
        if ($fareOptionKey !== '' && $intent !== []) {
            $brandedContext = app(\App\Support\Bookings\SabreSelectedBrandedFareCheckoutContext::class)->buildFromCheckout(
                $offer,
                $criteria,
                $searchId,
                $offerId,
                $fareOptionKey,
                $intent,
            );
        }

        $match = app(\App\Support\FlightSearch\SabreSelectedOfferDeterministicMatcher::class)
            ->matchArrayOffers($offers, $offer, $brandedContext);
        $freshOffer = $match['offer'] ?? null;

        if ($freshOffer === null) {
            $contextService = app(\App\Support\Bookings\SabreSelectedBrandedFareCheckoutContext::class);
            if ($brandedContext !== [] && ($contextService->assess($brandedContext, $searchPayload)['complete'] ?? false)) {
                return [
                    'success' => true,
                    'status' => 'success',
                    'message' => $freshness->customerSafeMessage('refresh_search_success'),
                    'block_code' => null,
                    'diagnostic' => null,
                    'freshness_meta' => $freshness->buildOfferFreshnessMeta($offer, $searchPayload),
                    'meta_patch' => [
                        'selected_offer_refresh_reason' => 'context_preserved_zero_refresh_offers',
                        'selected_offer_context_preserved' => true,
                    ],
                ];
            }

            return [
                'success' => false,
                'status' => 'failed',
                'message' => $freshness->customerSafeMessage('selected_offer_revalidation_failed'),
                'block_code' => 'selected_offer_revalidation_failed',
                'diagnostic' => SabreOfferFreshness::DIAG_SELECTED_OFFER_REVALIDATION_FAILED,
                'freshness_meta' => $freshness->buildOfferFreshnessMeta($offer, $searchPayload),
                'meta_patch' => [
                    'selected_offer_revalidation_status' => 'failed',
                    'selected_offer_revalidation_reason' => 'offer_not_in_fresh_search',
                    'sabre_checkout_freshness_block' => [
                        'classification' => SabreOfferFreshness::DIAG_SELECTED_OFFER_REVALIDATION_FAILED,
                        'code' => 'selected_offer_revalidation_failed',
                        'at' => now()->toIso8601String(),
                    ],
                ],
            ];
        }

        $now = now()->toIso8601String();
        $metaPatch = [
            'selected_offer_revalidation_status' => 'success',
            'selected_offer_last_revalidated_at' => $now,
            'last_revalidated_at' => $now,
            'revalidation_status' => 'success',
            'selected_offer_revalidation_reason' => null,
            'selected_offer_revalidation_at' => $now,
            'selected_offer_refresh_reason' => 'search_refresh',
        ];

        $this->searchStore->refreshOfferFromSearch($searchId, $offerId, array_merge($freshOffer, $metaPatch));
        $payload = $this->searchStore->get($searchId);
        $mergedOffer = $this->searchStore->findOffer($searchId, $offerId) ?? array_merge($freshOffer, $metaPatch);
        $freshnessMeta = $freshness->buildOfferFreshnessMeta($mergedOffer, $payload, $metaPatch);

        return [
            'success' => true,
            'status' => 'success',
            'message' => $freshness->customerSafeMessage('refresh_search_success'),
            'block_code' => null,
            'diagnostic' => null,
            'freshness_meta' => $freshnessMeta,
            'meta_patch' => array_merge($metaPatch, ['offer_freshness' => $freshnessMeta]),
        ];
    }

    /**
     * @param  array<string, mixed>  $offer
     * @param  array<string, mixed>  $criteria
     */
    protected function buildCustomerSelectUrl(bool $canSelect, array $offer, string $searchId, array $criteria): ?string
    {
        if (! $canSelect) {
            return null;
        }

        $params = array_merge([
            'flight_id' => (string) ($offer['id'] ?? $offer['offer_id'] ?? ''),
            'search_id' => $searchId,
            'offer_id' => (string) ($offer['id'] ?? $offer['offer_id'] ?? ''),
            'from' => (string) ($criteria['origin'] ?? ''),
            'to' => (string) ($criteria['destination'] ?? ''),
            'depart' => (string) ($criteria['depart_date'] ?? ''),
            'trip_type' => (string) ($criteria['trip_type'] ?? 'one_way'),
            'cabin' => (string) ($criteria['cabin'] ?? 'economy'),
            'adults' => (int) ($criteria['adults'] ?? 1),
            'children' => (int) ($criteria['children'] ?? 0),
            'infants' => (int) ($criteria['infants'] ?? 0),
        ], (($returnDate = trim((string) ($criteria['return_date'] ?? ''))) !== '' ? ['return_date' => $returnDate] : []));

        $checkoutContext = app(ClientCheckoutContextResolver::class);
        $checkoutContext->persist(request());
        $url = $checkoutContext->passengersUrl($params, request());

        return PublicFlightSearchSecurity::isAllowedInternalUrl($url) ? $url : null;
    }

    protected function sessionFlightIdErrorMessage(Request $request): string
    {
        $errors = $request->session()->get('errors');
        if ($errors instanceof ViewErrorBag) {
            return trim((string) $errors->first('flight_id'));
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $criteria
     * @return array<string, string|int>
     */
    protected function criteriaToResultsQuery(array $criteria): array
    {
        $query = [
            'from' => (string) ($criteria['origin'] ?? ''),
            'to' => (string) ($criteria['destination'] ?? ''),
            'depart' => (string) ($criteria['depart_date'] ?? ''),
            'trip_type' => (string) ($criteria['trip_type'] ?? 'one_way'),
            'cabin' => (string) ($criteria['cabin'] ?? 'economy'),
            'adults' => (int) ($criteria['adults'] ?? 1),
            'children' => (int) ($criteria['children'] ?? 0),
            'infants' => (int) ($criteria['infants'] ?? 0),
        ];
        $returnDate = trim((string) ($criteria['return_date'] ?? ''));
        if ($returnDate !== '') {
            $query['return_date'] = $returnDate;
        }

        if ((string) ($criteria['trip_type'] ?? '') === 'multi_city' && is_array($criteria['segments'] ?? null)) {
            foreach ($criteria['segments'] as $segment) {
                if (! is_array($segment)) {
                    continue;
                }
                $query['multi_from'][] = (string) ($segment['origin'] ?? '');
                $query['multi_to'][] = (string) ($segment['destination'] ?? '');
                $query['multi_depart'][] = (string) ($segment['departure_date'] ?? '');
            }
        }

        return $query;
    }

    /**
     * @param  array<string, mixed>  $criteria
     */
    protected function redirectSelectedOfferWarning(array $criteria = []): RedirectResponse
    {
        if ($this->hasResultsSearchContext($criteria)) {
            return redirect()->to(client_route('flights.results', $this->criteriaToResultsQuery($criteria)))
                ->withErrors(['flight_id' => __('This fare is no longer available. Please refresh results and select again.')]);
        }

        return redirect()->to(client_home_flight_search_url())
            ->with('offer_warning', __('Your selected fare expired. Please search again to see current availability.'));
    }

    /**
     * @param  array<string, mixed>  $criteria
     */
    protected function hasResultsSearchContext(array $criteria): bool
    {
        return trim((string) ($criteria['origin'] ?? '')) !== ''
            && trim((string) ($criteria['destination'] ?? '')) !== ''
            && trim((string) ($criteria['depart_date'] ?? '')) !== '';
    }

    public function details(Request $request, string $id): View
    {
        $criteria = [
            'origin' => $request->string('from')->toString(),
            'destination' => $request->string('to')->toString(),
            'depart_date' => $request->string('depart')->toString(),
            'trip_type' => $request->string('trip_type', 'one_way')->toString(),
            'cabin' => $request->string('cabin', 'economy')->toString(),
            'adults' => max(1, (int) $request->input('adults', 1)),
            'children' => max(0, (int) $request->input('children', 0)),
            'infants' => max(0, (int) $request->input('infants', 0)),
            'return_date' => $request->filled('return_date') ? $request->string('return_date')->toString() : null,
        ];

        if ($criteria['origin'] === '' || $criteria['destination'] === '' || $criteria['depart_date'] === '') {
            abort(404);
        }

        try {
            if (Carbon::parse($criteria['depart_date'])->startOfDay()->lt(now()->startOfDay())) {
                abort(404);
            }
        } catch (Throwable) {
            abort(404);
        }

        $channel = AgentBookingContext::resolveCheckoutChannel($request);
        $agency = $channel['agency'];
        $enriched = $this->flightSearch->search(
            $criteria,
            $agency,
            $channel['source_channel'],
            $channel['agent_id'],
        );
        $offer = collect($enriched)->firstWhere('id', $id);

        abort_if($offer === null, 404);

        $logo = $this->airlineBranding->getLogoForCode((string) ($offer['airline_code'] ?? ($offer['carrier_code'] ?? '')));

        $canContinueBooking = $this->offerIsCustomerBookable($offer, $criteria);

        return view(client_view('frontend.flights.details', 'frontend'), [
            'offer' => $offer,
            'criteria' => $criteria,
            'airlineLogo' => $logo,
            'canContinueBooking' => $canContinueBooking,
            'bookingBlockedReason' => $canContinueBooking ? null : (! $this->offerHasConfirmedPkrFare($offer)
                ? 'This fare cannot be booked online until a PKR total is confirmed.'
                : FlightDeparturePolicy::SAME_DAY_LEAD_MESSAGE),
        ]);
    }

    /**
     * Confirmed PKR customer quote (positive total, converted or native PKR).
     *
     * @param  array<string, mixed>  $offer
     */
    protected function offerHasConfirmedPkrFare(array $offer): bool
    {
        $final = (float) ($offer['final_customer_price'] ?? $offer['total'] ?? 0);
        $pricingCurrency = strtoupper((string) ($offer['pricing_currency'] ?? $offer['currency'] ?? 'PKR'));
        $conversionStatus = (string) ($offer['conversion_status'] ?? 'same_currency');

        return $final > 0
            && $pricingCurrency === 'PKR'
            && in_array($conversionStatus, ['same_currency', 'converted'], true);
    }

    /**
     * Book / checkout allowed only for confirmed PKR fares that meet departure lead-time rules.
     *
     * @param  array<string, mixed>  $offer
     * @param  array<string, mixed>  $criteria
     */
    protected function offerIsCustomerBookable(array $offer, array $criteria): bool
    {
        if (PublicMulticityInquiryPolicy::blocksAutomaticCheckout($criteria, $offer)) {
            return false;
        }

        if (! $this->offerHasConfirmedPkrFare($offer)) {
            return false;
        }

        return $this->departurePolicy->offerMeetsLeadTimeForBooking($offer, $criteria);
    }

    /**
     * @param  list<array<string, mixed>>  $offers
     * @return list<array<string, mixed>>
     */
    protected function sortOffers(array $offers, string $sort, array $criteria = []): array
    {
        if ($sort === '') {
            return $offers;
        }

        usort($offers, function (array $a, array $b) use ($sort, $criteria): int {
            $aCanBook = $this->offerIsCustomerBookable($a, $criteria);
            $bCanBook = $this->offerIsCustomerBookable($b, $criteria);
            if ($aCanBook !== $bCanBook) {
                return $aCanBook ? -1 : 1;
            }

            return match ($sort) {
                'price_desc' => (float) ($b['final_customer_price'] ?? 0) <=> (float) ($a['final_customer_price'] ?? 0),
                'departure_time', 'earliest_departure' => strcmp((string) ($a['depart_at'] ?? ''), (string) ($b['depart_at'] ?? '')),
                'latest_departure' => strcmp((string) ($b['depart_at'] ?? ''), (string) ($a['depart_at'] ?? '')),
                'arrival_time' => strcmp((string) ($a['arrive_at'] ?? ''), (string) ($b['arrive_at'] ?? '')),
                'duration', 'fastest' => ((int) ($a['duration_h'] ?? 0) * 60 + (int) ($a['duration_m'] ?? 0)) <=> ((int) ($b['duration_h'] ?? 0) * 60 + (int) ($b['duration_m'] ?? 0)),
                'airline_name', 'airline_az' => strcmp((string) ($a['airline_name'] ?? ''), (string) ($b['airline_name'] ?? '')),
                default => (float) ($a['final_customer_price'] ?? 0) <=> (float) ($b['final_customer_price'] ?? 0),
            };
        });

        return $offers;
    }

    /**
     * @param  list<array<string, mixed>>  $offers
     * @param  array<string, mixed>  $filters
     * @return list<array<string, mixed>>
     */
    protected function filterOffers(array $offers, array $filters, array $criteria = []): array
    {
        return array_values(array_filter($offers, function (array $offer) use ($filters, $criteria): bool {
            if (($filters['airline'] ?? '') !== '') {
                $want = strtoupper((string) $filters['airline']);
                $codes = $offer['all_airline_codes'] ?? null;
                if (! is_array($codes) || $codes === []) {
                    $codes = [strtoupper((string) ($offer['airline_code'] ?? ($offer['carrier_code'] ?? '')))];
                }
                $codes = array_values(array_unique(array_filter(array_map(
                    static fn (mixed $c): string => strtoupper(trim((string) $c)),
                    $codes
                ))));
                if (! in_array($want, $codes, true)) {
                    return false;
                }
            }

            if (($filters['stops'] ?? '') !== '') {
                $stops = (int) ($offer['stops'] ?? 0);
                if ($filters['stops'] === 'direct' && $stops !== 0) {
                    return false;
                }
                if ($filters['stops'] === '1_stop' && $stops !== 1) {
                    return false;
                }
                if ($filters['stops'] === '2_plus' && $stops < 2) {
                    return false;
                }
            }

            if (($filters['operating_airline'] ?? '') !== '') {
                $op = strtoupper((string) ($offer['operating_carrier_code'] ?? $offer['operating_airline_code'] ?? ''));
                if ($op === '' || $op !== $filters['operating_airline']) {
                    return false;
                }
            }

            if (($filters['refundable'] ?? '') !== '') {
                if (! ItineraryFareConsolidator::offerMatchesRefundableFilter($offer, (string) $filters['refundable'])) {
                    return false;
                }
            }

            if (($filters['cabin'] ?? '') !== '' && strtolower((string) ($offer['cabin'] ?? '')) !== strtolower((string) $filters['cabin'])) {
                return false;
            }

            if (($filters['fare_family'] ?? '') !== '') {
                if (! ItineraryFareConsolidator::offerMatchesFareFamilyFilter($offer, (string) $filters['fare_family'])) {
                    return false;
                }
            }

            if (($filters['bookable_only'] ?? '') !== '') {
                $bookable = $this->offerIsCustomerBookable($offer, $criteria);
                if ((string) $filters['bookable_only'] === '1' && ! $bookable) {
                    return false;
                }
            }

            if (($filters['baggage'] ?? '') !== '') {
                if (! ItineraryFareConsolidator::offerMatchesBaggageFilter($offer, (string) $filters['baggage'])) {
                    return false;
                }
            }

            if (($filters['departure_window'] ?? '') !== '' && ! $this->matchesTimeWindow((string) ($offer['depart_at'] ?? ''), (string) $filters['departure_window'])) {
                return false;
            }

            if (($filters['arrival_window'] ?? '') !== '' && ! $this->matchesTimeWindow((string) ($offer['arrive_at'] ?? ''), (string) $filters['arrival_window'])) {
                return false;
            }

            $durationMinutes = ((int) ($offer['duration_h'] ?? 0) * 60) + (int) ($offer['duration_m'] ?? 0);
            if ($filters['max_duration'] !== null && $filters['max_duration'] !== '' && $durationMinutes > (int) $filters['max_duration']) {
                return false;
            }

            if (($filters['duration_bucket'] ?? '') !== '' && ! $this->matchesDurationBucket($durationMinutes, (string) $filters['duration_bucket'])) {
                return false;
            }

            if (($filters['layover_airport'] ?? '') !== '') {
                $segments = is_array($offer['segments'] ?? null) ? $offer['segments'] : [];
                $layovers = $this->layoverAirportsFromSegments($segments);
                if (! in_array((string) $filters['layover_airport'], $layovers, true)) {
                    return false;
                }
            }

            $price = (float) ($offer['final_customer_price'] ?? 0);
            if ($filters['min_price'] !== null && $filters['min_price'] !== '' && $price < (float) $filters['min_price']) {
                return false;
            }
            if ($filters['max_price'] !== null && $filters['max_price'] !== '' && $price > (float) $filters['max_price']) {
                return false;
            }

            return true;
        }));
    }

    /**
     * @param  array<string, mixed>  $offer
     * @param  array<string, string>  $airlineNameMap
     */
    protected static function resolveOperatingAirlineName(array $offer, array $airlineNameMap): string
    {
        $opCode = strtoupper(trim((string) ($offer['operating_carrier_code'] ?? $offer['operating_airline_code'] ?? '')));
        if ($opCode === '') {
            return '';
        }

        return AirlineDisplayNameResolver::resolve(
            $opCode,
            (string) ($offer['operating_airline_name'] ?? ''),
            $airlineNameMap
        );
    }

    /**
     * @param  list<array<string, mixed>>  $offers
     * @param  array<string, string>  $airlineNameMap
     * @return array<string, mixed>
     */
    protected function buildFilterMeta(array $offers, array $criteria = [], array $airlineNameMap = []): array
    {
        $airlineCounts = [];
        $direct = 0;
        $oneStop = 0;
        $twoPlus = 0;
        $refundable = 0;
        $nonRefundable = 0;
        $prices = [];
        $cabinCounts = [];
        $baggageCounts = ['checked_baggage' => 0, 'cabin_baggage' => 0, 'no_baggage_info' => 0];
        $departureWindows = ['early_morning' => 0, 'morning' => 0, 'afternoon' => 0, 'evening' => 0];
        $arrivalWindows = ['early_morning' => 0, 'morning' => 0, 'afternoon' => 0, 'evening' => 0];
        $durations = [];
        $durationBuckets = ['under_6h' => 0, '6_12h' => 0, '12_20h' => 0, 'over_20h' => 0];
        $layovers = [];
        $fareFamilies = [];
        $operatingCounts = [];
        $bookable = 0;
        $unavailable = 0;
        foreach ($offers as $offer) {
            $codesForHistogram = $offer['all_airline_codes'] ?? null;
            if (! is_array($codesForHistogram) || $codesForHistogram === []) {
                $codesForHistogram = [strtoupper((string) ($offer['airline_code'] ?? ($offer['carrier_code'] ?? '')))];
            }
            foreach ($codesForHistogram as $rawCode) {
                $code = strtoupper(trim((string) $rawCode));
                if ($code === '') {
                    continue;
                }
                $primaryCode = strtoupper(trim((string) ($offer['airline_code'] ?? ($offer['carrier_code'] ?? ''))));
                $name = AirlineDisplayNameResolver::resolve(
                    $code,
                    $code === $primaryCode ? (string) ($offer['airline_name'] ?? '') : null,
                    $airlineNameMap
                );
                if (! isset($airlineCounts[$code])) {
                    $airlineCounts[$code] = ['code' => $code, 'name' => $name, 'count' => 0];
                }
                $airlineCounts[$code]['count']++;
            }
            $stops = (int) ($offer['stops'] ?? 0);
            if ($stops === 0) {
                $direct++;
            }
            if ($stops === 1) {
                $oneStop++;
            }
            if ($stops >= 2) {
                $twoPlus++;
            }
            if ((bool) ($offer['refundable'] ?? false)) {
                $refundable++;
            } else {
                $nonRefundable++;
            }
            $isBookable = $this->offerIsCustomerBookable($offer, $criteria);
            if ($isBookable) {
                $prices[] = (float) ($offer['final_customer_price'] ?? 0);
                $bookable++;
            } else {
                $unavailable++;
            }

            $cabin = strtolower((string) ($offer['cabin'] ?? ''));
            if ($cabin !== '') {
                $cabinCounts[$cabin] = ($cabinCounts[$cabin] ?? 0) + 1;
            }

            $bag = strtolower((string) ($offer['baggage'] ?? ''));
            $bagKey = str_contains($bag, 'kg') ? 'checked_baggage' : ($bag !== '' ? 'cabin_baggage' : 'no_baggage_info');
            $baggageCounts[$bagKey]++;

            $depWindow = $this->timeWindow((string) ($offer['depart_at'] ?? ''));
            $arrWindow = $this->timeWindow((string) ($offer['arrive_at'] ?? ''));
            if ($depWindow !== null) {
                $departureWindows[$depWindow]++;
            }
            if ($arrWindow !== null) {
                $arrivalWindows[$arrWindow]++;
            }

            $durationMinutes = ((int) ($offer['duration_h'] ?? 0) * 60) + (int) ($offer['duration_m'] ?? 0);
            $durations[] = $durationMinutes;
            $durationBuckets[$this->durationBucket($durationMinutes)]++;

            $segments = is_array($offer['segments'] ?? null) ? $offer['segments'] : [];
            foreach ($this->layoverAirportsFromSegments($segments) as $layCode) {
                $layovers[$layCode] = ($layovers[$layCode] ?? 0) + 1;
            }

            $fareFamily = trim((string) ($offer['fare_family'] ?? ''));
            if ($fareFamily !== '') {
                $fareFamilies[$fareFamily] = ($fareFamilies[$fareFamily] ?? 0) + 1;
            }

            $opCode = strtoupper((string) ($offer['operating_carrier_code'] ?? $offer['operating_airline_code'] ?? ''));
            if ($opCode !== '') {
                $operatingCounts[$opCode] = ($operatingCounts[$opCode] ?? 0) + 1;
            }
        }

        uasort($airlineCounts, fn (array $a, array $b): int => $b['count'] <=> $a['count']);

        return [
            'airlines' => array_values($airlineCounts),
            'stops' => [
                ['value' => 'direct', 'count' => $direct],
                ['value' => '1_stop', 'count' => $oneStop],
                ['value' => '2_plus', 'count' => $twoPlus],
            ],
            'refundable' => [
                ['value' => true, 'count' => $refundable],
                ['value' => false, 'count' => $nonRefundable],
            ],
            'price_range' => [
                'min' => $prices === [] ? 0 : min($prices),
                'max' => $prices === [] ? 0 : max($prices),
                'currency' => 'PKR',
            ],
            'cabin_classes' => collect($cabinCounts)->map(fn (int $count, string $cabinKey): array => [
                'value' => $cabinKey,
                'label' => $this->cabinMetaLabel($cabinKey),
                'count' => $count,
            ])->values()->all(),
            'operating_airlines' => collect($operatingCounts)->map(fn (int $count, string $code): array => [
                'code' => $code,
                'label' => $code,
                'count' => $count,
            ])->values()->all(),
            'baggage_options' => [
                ['value' => 'checked_baggage', 'label' => 'Checked baggage included', 'count' => $baggageCounts['checked_baggage']],
                ['value' => 'cabin_baggage', 'label' => 'Cabin baggage only', 'count' => $baggageCounts['cabin_baggage']],
                ['value' => 'no_baggage_info', 'label' => 'No baggage info', 'count' => $baggageCounts['no_baggage_info']],
            ],
            'departure_time_windows' => $this->windowMeta($departureWindows),
            'arrival_time_windows' => $this->windowMeta($arrivalWindows),
            'duration_range' => [
                'min_duration_minutes' => $durations === [] ? 0 : min($durations),
                'max_duration_minutes' => $durations === [] ? 0 : max($durations),
            ],
            'duration_buckets' => [
                ['value' => 'under_6h', 'count' => $durationBuckets['under_6h']],
                ['value' => '6_12h', 'count' => $durationBuckets['6_12h']],
                ['value' => '12_20h', 'count' => $durationBuckets['12_20h']],
                ['value' => 'over_20h', 'count' => $durationBuckets['over_20h']],
            ],
            'layover_airports' => array_values(array_map(fn (string $code, int $count): array => ['code' => $code, 'name' => $code, 'count' => $count], array_keys($layovers), $layovers)),
            'fare_families' => array_values(array_map(fn (string $value, int $count): array => ['value' => $value, 'label' => ucwords(str_replace('_', ' ', $value)), 'count' => $count], array_keys($fareFamilies), $fareFamilies)),
            'bookable_status' => [
                ['value' => 'bookable', 'count' => $bookable],
                ['value' => 'price_unavailable', 'count' => $unavailable],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $criteria
     */
    protected function formatSearchSummary(array $criteria): string
    {
        $trip = (string) ($criteria['trip_type'] ?? 'one_way');
        if ($trip === 'multi_city' && ! empty($criteria['segments']) && is_array($criteria['segments'])) {
            $parts = [];
            foreach ($criteria['segments'] as $seg) {
                if (! is_array($seg)) {
                    continue;
                }
                $parts[] = ($seg['origin'] ?? '').' → '.($seg['destination'] ?? '').' · '.($seg['departure_date'] ?? '');
            }

            return implode(' · ', array_filter($parts));
        }

        $from = (string) ($criteria['origin'] ?? '');
        $to = (string) ($criteria['destination'] ?? '');
        $dep = (string) ($criteria['depart_date'] ?? '');
        try {
            $depLabel = $dep !== '' ? Carbon::parse($dep)->format('l, M j, Y') : '';
        } catch (Throwable) {
            $depLabel = $dep;
        }

        if ($trip === 'round_trip') {
            $ret = (string) ($criteria['return_date'] ?? '');
            try {
                $retLabel = $ret !== '' ? Carbon::parse($ret)->format('l, M j, Y') : '';
            } catch (Throwable) {
                $retLabel = $ret;
            }

            return trim($from.' ⇄ '.$to.' · '.$depLabel.($retLabel !== '' ? ' / '.$retLabel : ''));
        }

        return trim($from.' → '.$to.' · '.$depLabel);
    }

    /**
     * @param  array<string, mixed>  $criteria
     * @return array{origin_code: string, destination_code: string, origin_subtitle: string, destination_subtitle: string, depart_main: string, depart_day: string, return_main: string, return_day: string}
     */
    protected function buildInlineSearchDisplay(array $criteria): array
    {
        $o = strtoupper(trim((string) ($criteria['origin'] ?? '')));
        $d = strtoupper(trim((string) ($criteria['destination'] ?? '')));
        $dep = (string) ($criteria['depart_date'] ?? '');
        $depMain = '';
        $depDay = '';
        try {
            if ($dep !== '') {
                $c = Carbon::parse($dep);
                $depMain = $c->format('M j, Y');
                $depDay = $c->format('l');
            }
        } catch (Throwable) {
        }
        $ret = (string) ($criteria['return_date'] ?? '');
        $retMain = '';
        $retDay = '';
        try {
            if ($ret !== '') {
                $cr = Carbon::parse($ret);
                $retMain = $cr->format('M j, Y');
                $retDay = $cr->format('l');
            }
        } catch (Throwable) {
        }

        return [
            'origin_code' => $o,
            'destination_code' => $d,
            'origin_subtitle' => $this->airportCityCountryLine($o),
            'destination_subtitle' => $this->airportCityCountryLine($d),
            'depart_main' => $depMain,
            'depart_day' => $depDay,
            'return_main' => $retMain,
            'return_day' => $retDay,
        ];
    }

    protected function airportCityCountryLine(string $iata): string
    {
        if ($iata === '') {
            return '';
        }

        $row = Airport::query()->where('iata_code', $iata)->first();
        if ($row === null) {
            return '';
        }

        $city = trim((string) $row->city);
        $country = trim((string) $row->country);
        if ($city !== '' && $country !== '') {
            return $city.', '.$country;
        }

        return $city !== '' ? $city : $country;
    }

    protected function cabinMetaLabel(string $value): string
    {
        return match (strtolower($value)) {
            'premium_economy' => 'Premium Economy',
            'business' => 'Business',
            'first' => 'First',
            'economy' => 'Economy',
            default => ucfirst(str_replace('_', ' ', $value)),
        };
    }

    private function matchesTimeWindow(string $dateTime, string $window): bool
    {
        return $this->timeWindow($dateTime) === $window;
    }

    private function timeWindow(string $dateTime): ?string
    {
        if ($dateTime === '') {
            return null;
        }

        $hour = (int) date('G', strtotime($dateTime));

        return match (true) {
            $hour <= 5 => 'early_morning',
            $hour <= 11 => 'morning',
            $hour <= 17 => 'afternoon',
            default => 'evening',
        };
    }

    private function windowMeta(array $counts): array
    {
        $labels = [
            'early_morning' => 'Early morning (00:00-05:59)',
            'morning' => 'Morning (06:00-11:59)',
            'afternoon' => 'Afternoon (12:00-17:59)',
            'evening' => 'Evening (18:00-23:59)',
        ];

        $meta = [];
        foreach ($labels as $value => $label) {
            $meta[] = ['value' => $value, 'label' => $label, 'count' => (int) ($counts[$value] ?? 0)];
        }

        return $meta;
    }

    private function matchesDurationBucket(int $minutes, string $bucket): bool
    {
        return $this->durationBucket($minutes) === $bucket;
    }

    private function durationBucket(int $minutes): string
    {
        return match (true) {
            $minutes < 360 => 'under_6h',
            $minutes < 720 => '6_12h',
            $minutes < 1200 => '12_20h',
            default => 'over_20h',
        };
    }

    /**
     * @param  list<array<string, mixed>>  $segments
     * @return list<string>
     */
    private function layoverAirportsFromSegments(array $segments): array
    {
        $layovers = [];
        for ($i = 0; $i < count($segments) - 1; $i++) {
            $destination = strtoupper((string) ($segments[$i]['destination'] ?? ''));
            if ($destination !== '') {
                $layovers[] = $destination;
            }
        }

        return array_values(array_unique($layovers));
    }

    /**
     * @param  array<string, mixed>  $criteria
     * @return array{0: array<string, mixed>, 1: string, 2: array<int, string>}
     */
    protected function runSearch(array $criteria, ?Request $request = null): array
    {
        $request = $request ?? request();
        $channel = AgentBookingContext::resolveCheckoutChannel($request);
        $agency = $channel['agency'];
        $result = $this->flightSearch->searchWithMeta(
            $criteria,
            $agency,
            $channel['source_channel'],
            $channel['agent_id'],
        );
        $allOffers = is_array($result['offers'] ?? null) ? $result['offers'] : [];
        $restrictPublicSuppliers = ! app()->environment('testing');
        /** @var list<string> $allowedList */
        $allowedList = config('ota.public_flight_results_suppliers', ['duffel', 'sabre']);
        $allowed = array_values(array_filter(array_map(
            static fn (mixed $v): string => strtolower(trim((string) $v)),
            is_array($allowedList) ? $allowedList : ['duffel', 'sabre']
        )));
        $storedOffers = $restrictPublicSuppliers
            ? array_values(array_filter(
                $allOffers,
                static function (array $offer) use ($allowed): bool {
                    $p = strtolower((string) ($offer['supplier_provider'] ?? ''));

                    return $p !== '' && in_array($p, $allowed, true);
                }
            ))
            : $allOffers;

        $preGateByProvider = collect($allOffers)
            ->map(fn (array $offer): string => strtolower((string) ($offer['supplier_provider'] ?? 'unknown')))
            ->countBy()
            ->all();
        $postGateByProvider = collect($storedOffers)
            ->map(fn (array $offer): string => strtolower((string) ($offer['supplier_provider'] ?? 'unknown')))
            ->countBy()
            ->all();
        $droppedByProvider = [];
        foreach ($preGateByProvider as $provider => $count) {
            $remaining = (int) ($postGateByProvider[$provider] ?? 0);
            $dropped = $count - $remaining;
            if ($dropped > 0) {
                $droppedByProvider[$provider] = $dropped;
            }
        }

        Log::info('flight_search.pipeline', [
            'stage' => 'public_results_provider_gate',
            'pre_gate_offer_count' => count($allOffers),
            'post_gate_offer_count' => count($storedOffers),
            'gate_dropped_count' => count($allOffers) - count($storedOffers),
            'allowed_suppliers' => $allowed,
        ]);

        Log::info('flight_search.public_diagnostics', [
            'stage' => 'public_results_provider_gate',
            'agency_id' => $agency?->id,
            'agency_slug' => $agency?->slug,
            'source_channel' => $channel['source_channel'],
            'allowed_suppliers' => $allowed,
            'pre_gate_offer_count_by_provider' => $preGateByProvider,
            'post_gate_offer_count_by_provider' => $postGateByProvider,
            'gate_dropped_by_provider' => $droppedByProvider,
            'blocking_reason' => $allOffers !== [] && $storedOffers === []
                ? 'public_results_supplier_gate_dropped_all_offers'
                : null,
        ]);

        $warnings = is_array($result['warnings'] ?? null) ? $result['warnings'] : [];
        if ($restrictPublicSuppliers && $allOffers !== [] && $storedOffers === []) {
            $warnings[] = 'Flight provider fares are currently unavailable. Please try again shortly.';
        }
        $warnings = $this->filterProviderFailureWarningsWhenOffersPresent($warnings, $storedOffers);

        $serviceMixedFilter = is_array($result['mixed_carrier_filter'] ?? null)
            ? $result['mixed_carrier_filter']
            : [];
        $postGateMixedFilter = app(SabreMixedCarrierSearchResultsFilter::class)->filterDisplayOffers($storedOffers);
        $storedOffers = $postGateMixedFilter['offers'];
        $postGateDiagnostics = $postGateMixedFilter['diagnostics'];
        $mixedCarrierFilter = ($postGateDiagnostics['mixed_carrier_offers_filtered_count'] ?? 0) > 0
            ? $postGateDiagnostics
            : ($serviceMixedFilter !== [] ? $serviceMixedFilter : $postGateDiagnostics);
        if (app(SabreMixedCarrierSearchResultsFilter::class)->allOffersFilteredByPolicy($mixedCarrierFilter)
            && ! in_array(SabreMixedCarrierSearchResultsFilter::EMPTY_RESULTS_CUSTOMER_MESSAGE, $warnings, true)) {
            $warnings[] = SabreMixedCarrierSearchResultsFilter::EMPTY_RESULTS_CUSTOMER_MESSAGE;
        }

        $multicityDiagnostics = is_array($result['multicity_diagnostics'] ?? null)
            ? $result['multicity_diagnostics']
            : [];
        $searchId = $this->searchStore->store($criteria, $storedOffers, $warnings, [
            'mixed_carrier_filter' => $mixedCarrierFilter,
            'multicity_diagnostics' => $multicityDiagnostics,
        ]);
        $this->captureMarketingSearchSnapshot($agency, $searchId, $criteria, $storedOffers, $warnings);
        if (function_exists('session')) {
            session()->put('home_recent_fares', [
                'criteria' => $criteria,
                'offers' => array_slice($storedOffers, 0, 3),
                'captured_at' => now()->toIso8601String(),
            ]);
        }
        $safeWarnings = [];
        foreach ($warnings as $w) {
            $line = (string) $w;
            if ($line === '') {
                continue;
            }
            if (str_contains($line, 'Duffel') && str_contains($line, 'unavailable')) {
                $safeWarnings[] = 'Provider search is temporarily unavailable.';
            } elseif (str_contains($line, 'Flight provider fares are currently unavailable')) {
                $safeWarnings[] = 'Provider search is temporarily unavailable.';
            } elseif (str_contains($line, 'Provider search is temporarily unavailable')) {
                $safeWarnings[] = 'Provider search is temporarily unavailable.';
            } else {
                $safeWarnings[] = $line;
            }
        }

        return [[
            'offers' => $storedOffers,
            'warnings' => $safeWarnings,
        ], $searchId, array_values(array_unique($safeWarnings))];
    }

    /**
     * Phase 3A: persist abandoned-search snapshot for logged-in customers (no email send).
     *
     * @param  list<array<string, mixed>>  $storedOffers
     * @param  list<string>  $warnings
     */
    protected function captureMarketingSearchSnapshot(
        ?Agency $agency,
        string $searchId,
        array $criteria,
        array $storedOffers,
        array $warnings,
    ): void {
        if (! (bool) config('ota.abandoned_search_followup.enabled', true)) {
            return;
        }

        if ((bool) config('ota.abandoned_search_followup.capture_logged_in_only', true)) {
            $user = Auth::user();
            if (! $user instanceof User || ! $user->isCustomer()) {
                return;
            }
        } else {
            $user = Auth::user();
            if (! $user instanceof User) {
                return;
            }
        }

        $email = strtolower(trim((string) $user->email));
        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return;
        }

        try {
            $searchedAt = now();
            $delayHours = max(1, (int) config('ota.abandoned_search_followup.delay_hours', 4));
            $expireHours = max(1, (int) config('ota.abandoned_search_followup.expire_hours', 48));
            $topOffers = $this->selectTopMarketingOffers($storedOffers, $criteria);

            FlightSearchMarketingSnapshot::query()->create([
                'agency_id' => $agency?->id,
                'search_id' => $searchId,
                'user_id' => $user->id,
                'recipient_email' => $email,
                'session_id' => function_exists('session') && session()->isStarted() ? session()->getId() : null,
                'source_channel' => 'public_search',
                'criteria' => $criteria,
                'criteria_fingerprint' => $this->buildMarketingCriteriaFingerprint($criteria),
                'top_offers' => array_map(
                    fn (array $offer): array => $this->slimMarketingOfferRow($offer, $criteria),
                    $topOffers
                ),
                'offer_count' => count($storedOffers),
                'searched_at' => $searchedAt,
                'send_after_at' => $searchedAt->copy()->addHours($delayHours),
                'expires_at' => $searchedAt->copy()->addHours($expireHours),
                'status' => FlightSearchMarketingSnapshot::STATUS_PENDING,
                'meta' => [
                    'top_offer_count' => count($topOffers),
                    'warning_count' => count($warnings),
                ],
            ]);

            Log::info('flight_search.marketing_snapshot_captured', [
                'search_id' => $searchId,
                'user_id' => $user->id,
                'top_offer_count' => count($topOffers),
                'offer_count' => count($storedOffers),
            ]);
        } catch (UniqueConstraintViolationException) {
            Log::notice('flight_search.marketing_snapshot_duplicate', [
                'search_id' => $searchId,
                'user_id' => $user->id,
            ]);
        } catch (Throwable $e) {
            Log::warning('flight_search.marketing_snapshot_failed', [
                'search_id' => $searchId,
                'user_id' => $user->id,
                'exception' => $e::class,
            ]);
            report($e);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $offers
     * @return list<array<string, mixed>>
     */
    protected function selectTopMarketingOffers(array $offers, array $criteria): array
    {
        $bookable = array_values(array_filter(
            $offers,
            fn (array $offer): bool => $this->offerIsCustomerBookable($offer, $criteria)
        ));

        usort($bookable, function (array $a, array $b): int {
            return (float) ($a['final_customer_price'] ?? 0) <=> (float) ($b['final_customer_price'] ?? 0);
        });

        return array_slice($bookable, 0, 5);
    }

    /**
     * @param  array<string, mixed>  $offer
     * @return array<string, mixed>
     */
    protected function slimMarketingOfferRow(array $offer, array $criteria): array
    {
        $code = strtoupper((string) ($offer['airline_code'] ?? ($offer['carrier_code'] ?? '')));
        $durationH = (int) ($offer['duration_h'] ?? 0);
        $durationM = (int) ($offer['duration_m'] ?? 0);

        return [
            'offer_id' => (string) ($offer['id'] ?? $offer['offer_id'] ?? ''),
            'airline_name' => trim((string) ($offer['airline_name'] ?? '')),
            'airline_code' => $code,
            'origin' => strtoupper(trim((string) ($offer['origin'] ?? $criteria['origin'] ?? ''))),
            'destination' => strtoupper(trim((string) ($offer['destination'] ?? $criteria['destination'] ?? ''))),
            'departure_at' => (string) ($offer['depart_at'] ?? $offer['departure_at'] ?? ''),
            'arrival_at' => (string) ($offer['arrive_at'] ?? $offer['arrival_at'] ?? ''),
            'duration' => $durationH.'h '.str_pad((string) $durationM, 2, '0', STR_PAD_LEFT).'m',
            'stops' => (int) ($offer['stops'] ?? 0),
            'price' => (float) ($offer['final_customer_price'] ?? 0),
            'currency' => strtoupper((string) ($offer['pricing_currency'] ?? $offer['currency'] ?? 'PKR')),
            'trip_type_label' => FlightOfferDisplayPresenter::formatCriteriaTripTypeLabel((string) ($criteria['trip_type'] ?? 'one_way')),
            'route_label' => FlightOfferDisplayPresenter::formatCriteriaRouteLabel($criteria),
        ];
    }

    /**
     * @param  array<string, mixed>  $criteria
     */
    protected function buildMarketingCriteriaFingerprint(array $criteria): string
    {
        $payload = [
            'origin' => strtoupper(trim((string) ($criteria['origin'] ?? ''))),
            'destination' => strtoupper(trim((string) ($criteria['destination'] ?? ''))),
            'trip_type' => (string) ($criteria['trip_type'] ?? 'one_way'),
            'depart_date' => (string) ($criteria['depart_date'] ?? ''),
            'return_date' => filled($criteria['return_date'] ?? null) ? (string) $criteria['return_date'] : null,
            'cabin' => strtolower((string) ($criteria['cabin'] ?? 'economy')),
            'adults' => (int) ($criteria['adults'] ?? 1),
            'children' => (int) ($criteria['children'] ?? 0),
            'infants' => (int) ($criteria['infants'] ?? 0),
        ];

        if ($payload['trip_type'] === 'multi_city' && is_array($criteria['segments'] ?? null)) {
            $payload['segments'] = array_values(array_map(
                static function (mixed $seg): array {
                    if (! is_array($seg)) {
                        return ['origin' => '', 'destination' => '', 'departure_date' => ''];
                    }

                    return [
                        'origin' => strtoupper(trim((string) ($seg['origin'] ?? ''))),
                        'destination' => strtoupper(trim((string) ($seg['destination'] ?? ''))),
                        'departure_date' => (string) ($seg['departure_date'] ?? ''),
                    ];
                },
                $criteria['segments']
            ));
        }

        return hash('sha256', (string) json_encode($payload, JSON_UNESCAPED_UNICODE));
    }

    /**
     * @param  list<string>  $warnings
     * @param  list<array<string, mixed>>  $storedOffers
     * @return list<string>
     */
    protected function filterProviderFailureWarningsWhenOffersPresent(array $warnings, array $storedOffers): array
    {
        if ($storedOffers === []) {
            return $warnings;
        }

        return array_values(array_filter($warnings, function (mixed $warning): bool {
            $line = trim((string) $warning);

            return $line !== '' && ! $this->isProviderWideSearchFailureWarning($line);
        }));
    }

    protected function isProviderWideSearchFailureWarning(string $line): bool
    {
        if (str_contains($line, 'Provider search is temporarily unavailable')) {
            return true;
        }

        if (str_contains($line, 'Flight provider fares are currently unavailable')) {
            return true;
        }

        if (str_contains($line, 'unavailable') && (
            str_contains($line, 'Duffel')
            || str_contains($line, 'IATI')
            || str_contains($line, 'Sabre')
            || str_contains($line, 'PIA')
        )) {
            return true;
        }

        return false;
    }

    /**
     * @param  array<string, mixed>|null  $payload
     */
    protected function resolveResultsEmptyMessage(?array $payload, int $total): ?string
    {
        if ($total > 0 || $payload === null) {
            return null;
        }

        $filter = is_array($payload['mixed_carrier_filter'] ?? null) ? $payload['mixed_carrier_filter'] : [];
        $criteria = is_array($payload['criteria'] ?? null) ? $payload['criteria'] : [];
        if (PublicMulticityInquiryPolicy::isMulticitySearch($criteria)) {
            return SabreMixedCarrierSearchResultsFilter::EMPTY_MULTICITY_RESULTS_CUSTOMER_MESSAGE;
        }

        if (app(SabreMixedCarrierSearchResultsFilter::class)->allOffersFilteredByPolicy($filter)) {
            return SabreMixedCarrierSearchResultsFilter::EMPTY_RESULTS_CUSTOMER_MESSAGE;
        }

        return null;
    }

    public function storeMulticityInquiry(StoreMulticityInquiryRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $searchId = trim((string) ($validated['search_id'] ?? ''));
        $offerId = trim((string) ($validated['offer_id'] ?? ''));

        if (! PublicFlightSearchSecurity::isValidSearchId($searchId)) {
            return redirect()->to(client_home_flight_search_url())
                ->withErrors(['flight_id' => __('This fare search has expired. Please search again.')]);
        }

        $payload = $this->searchStore->get($searchId);
        if ($payload === null) {
            return redirect()->to(client_home_flight_search_url())
                ->withErrors(['flight_id' => __('This fare search has expired. Please search again.')]);
        }

        $criteria = is_array($payload['criteria'] ?? null) ? $payload['criteria'] : [];
        if (! PublicMulticityInquiryPolicy::isMulticitySearch($criteria)) {
            return redirect()->to(client_home_flight_search_url())
                ->withErrors(['flight_id' => __('Multi-city inquiry is not available for this search.')]);
        }

        $offer = $this->searchStore->findOffer($searchId, $offerId);
        if ($offer === null) {
            return redirect()->to(client_route('flights.results', $this->criteriaToResultsQuery($criteria)))
                ->withErrors(['flight_id' => __('Selected multi-city fare is no longer available. Please search again.')]);
        }

        $user = $request->user();
        $requesterName = trim((string) ($validated['requester_name'] ?? ''));
        if ($requesterName === '' && $user !== null) {
            $requesterName = (string) ($user->name ?? 'Customer');
        }
        $requesterEmail = trim((string) ($validated['requester_email'] ?? ''));
        if ($requesterEmail === '' && $user !== null) {
            $requesterEmail = (string) ($user->email ?? '');
        }

        $agency = $this->supportTicketService->resolveDefaultAgency();
        $body = $this->buildMulticityInquiryBody($criteria, $offer, $searchId, $validated['notes'] ?? null);

        $ticket = $this->supportTicketService->createPublicTicket(
            $agency,
            [
                'subject' => 'Multi-city booking inquiry',
                'category' => SupportTicketCategory::Booking->value,
                'body' => $body,
                'requester_name' => $requesterName,
                'requester_email' => $requesterEmail,
            ],
            $user,
        );

        return redirect()
            ->to(client_route('support.submitted'))
            ->with('support_ticket_reference', $ticket->ticket_reference);
    }

    /**
     * @param  array<string, mixed>  $criteria
     * @param  array<string, mixed>  $offer
     */
    protected function buildMulticityInquiryBody(array $criteria, array $offer, string $searchId, mixed $notes): string
    {
        $lines = [
            'Multi-city booking inquiry (staff confirmation required).',
            'Block reason: '.PublicMulticityInquiryPolicy::BLOCK_REASON,
            'Search ID: '.$searchId,
            'Offer reference: '.substr(hash('sha256', (string) ($offer['offer_id'] ?? $offer['id'] ?? '')), 0, 16),
            'Route: '.(string) ($offer['full_route_display'] ?? FlightOfferDisplayPresenter::formatCriteriaRouteLabel($criteria)),
            'Slices: '.implode(', ', is_array($offer['route_by_slice'] ?? null) ? $offer['route_by_slice'] : []),
            'Carrier: '.(string) ($offer['carrier_chain'] ?? $offer['validating_carrier'] ?? ''),
            'Brand: '.trim((string) (($offer['brand_name'] ?? '') !== '' ? $offer['brand_name'] : ($offer['brand_code'] ?? ''))),
            'Fare: '.(string) ($offer['final_customer_price'] ?? 'unavailable').' '.(string) ($offer['pricing_currency'] ?? $offer['currency'] ?? 'PKR'),
            'Supplier offer key present: '.((($offer['supplier_offer_key_present'] ?? false) === true) ? 'yes' : 'no'),
        ];

        $noteText = trim((string) $notes);
        if ($noteText !== '') {
            $lines[] = 'Customer notes: '.$noteText;
        }

        return implode("\n", $lines);
    }
}
