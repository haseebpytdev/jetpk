<?php

namespace App\Services\Suppliers\Adapters;

use App\Contracts\Suppliers\FlightSupplierInterface;
use App\Data\FlightSearchRequestData;
use App\Data\FlightSearchResultData;
use App\Data\NormalizedFlightOfferData;
use App\Data\OfferValidationResultData;
use App\Enums\SupplierConnectionStatus;
use App\Enums\SupplierEnvironment;
use App\Enums\SupplierProvider;
use App\Models\SupplierConnection;
use App\Services\Suppliers\Sabre\Core\SabreClient;
use App\Services\Suppliers\Sabre\Gds\SabreFlightSearchRequestBuilder;
use App\Services\Suppliers\Sabre\Ndc\SabreNdcOfferSearchService;
use App\Services\Suppliers\Sabre\SabreFlightSearchNormalizer;
use App\Services\Suppliers\SupplierDiagnosticLogger;
use App\Support\FlightSearch\SabreSelectedOfferDeterministicMatcher;
use App\Support\Platform\PlatformModuleEnforcer;
use App\Support\Suppliers\SabreChannelGateResolver;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Log;
use Throwable;

class SabreFlightSupplierAdapter implements FlightSupplierInterface
{
    public function __construct(
        protected SabreClient $client,
        protected SabreFlightSearchNormalizer $normalizer,
        protected SupplierDiagnosticLogger $diagnosticLogger,
        protected PlatformModuleEnforcer $platformModuleEnforcer,
        protected SabreChannelGateResolver $channelGateResolver,
        protected SabreNdcOfferSearchService $ndcOfferSearchService,
    ) {}

    public function search(FlightSearchRequestData $request, SupplierConnection $connection): FlightSearchResultData
    {
        $requestMeta = $this->safeSearchContextMeta($request, $connection);

        if (! $connection->isActive() || $connection->status !== SupplierConnectionStatus::Active) {
            $this->diagnosticLogger->log(
                connection: $connection,
                action: 'search',
                status: 'warning',
                safeMessage: 'Sabre supplier connection is inactive.',
                meta: array_merge($requestMeta, ['reason_code' => 'sabre_provider_error']),
            );
            Log::warning('sabre.adapter.connection_inactive', array_merge(['provider' => 'sabre'], $requestMeta));

            return new FlightSearchResultData(
                supplier_provider: SupplierProvider::Sabre,
                offers: [],
                warnings: ['Sabre supplier connection is inactive.'],
                meta: ['connection_id' => $connection->id]
            );
        }

        if (! in_array($connection->environment, [SupplierEnvironment::Sandbox, SupplierEnvironment::Live], true)) {
            $this->diagnosticLogger->log(
                connection: $connection,
                action: 'search',
                status: 'warning',
                safeMessage: 'Sabre search requires sandbox or live environment.',
                meta: array_merge($requestMeta, ['reason_code' => 'sabre_provider_error']),
            );
            Log::warning('sabre.adapter.environment_blocked', array_merge(['provider' => 'sabre'], $requestMeta));

            return new FlightSearchResultData(
                supplier_provider: SupplierProvider::Sabre,
                offers: [],
                warnings: ['Sabre search is only enabled for sandbox or live environments.'],
                meta: ['connection_id' => $connection->id]
            );
        }

        if (! $this->client->connectionHasTokenCredentials($connection)) {
            $this->diagnosticLogger->log(
                connection: $connection,
                action: 'search',
                status: 'failed',
                safeMessage: 'Sabre credentials are not configured.',
                meta: array_merge($requestMeta, ['reason_code' => 'sabre_request_invalid']),
            );
            Log::warning('sabre.adapter.missing_credentials', array_merge(['provider' => 'sabre'], $requestMeta));

            return new FlightSearchResultData(
                supplier_provider: SupplierProvider::Sabre,
                offers: [],
                warnings: ['Sabre credentials are not configured.'],
                meta: ['connection_id' => $connection->id]
            );
        }

        $laneDiagnostics = $this->channelGateResolver->diagnostics($connection);
        $selectedLanes = is_array($laneDiagnostics['selected_sabre_lanes'] ?? null)
            ? $laneDiagnostics['selected_sabre_lanes']
            : [];

        if ($selectedLanes === []) {
            $this->diagnosticLogger->log(
                connection: $connection,
                action: 'search',
                status: 'warning',
                safeMessage: 'Sabre search lanes disabled for this connection.',
                meta: array_merge($requestMeta, $laneDiagnostics, ['reason_code' => 'sabre_lanes_disabled']),
            );
            Log::info('sabre.adapter.lanes_disabled', array_merge(['provider' => 'sabre'], $requestMeta, $laneDiagnostics));

            return new FlightSearchResultData(
                supplier_provider: SupplierProvider::Sabre,
                offers: [],
                warnings: [],
                meta: array_merge(['connection_id' => $connection->id], $laneDiagnostics),
            );
        }

        if (! in_array('gds', $selectedLanes, true)) {
            return $this->searchNdcLaneOnly($connection, $request, $requestMeta, $laneDiagnostics);
        }

        try {
            if (config('suppliers.sabre.branded_fares_search_enabled')) {
                try {
                    Log::warning('sabre.branded_fares_search_probe', array_merge(
                        [
                            'provider' => 'sabre',
                            'sabre.branded_fares_search_probe_enabled' => true,
                            'branded_fares_request_variant' => app(SabreFlightSearchRequestBuilder::class)->brandedFareRequestVariant(),
                            'endpoint_path' => (string) config('suppliers.sabre.shop_path', '/v4/offers/shop'),
                        ],
                        $requestMeta,
                    ));
                } catch (Throwable) {
                    // metadata-only probe must not break search
                }
            }

            $response = $this->client->searchFlights($request, $connection);
            $offers = $this->normalizer->normalize($response, $connection, $request);
            if (config('suppliers.sabre.branded_fares_probe_enabled')) {
                $this->logBrandedFaresProbe($request, $response, $offers);
            }
            $inventory = array_merge(
                $this->normalizer->inventorySummary($response),
                $this->normalizer->normalizationOutcomeDiagnostics($offers),
                $this->normalizer->batchRouteDiagnostics($request, $offers),
                $this->normalizer->routeContinuityDiagnostics($offers),
                $this->normalizer->getDisplayDiagnostics(),
            );

            if ($offers === []) {
                $pccStored = (bool) ($requestMeta['pcc_present'] ?? false);
                $pccInPayload = $this->client->includesPccInShopRequest($connection);
                $reasonCode = ($pccStored && $pccInPayload)
                    ? 'normalization_zero_offers'
                    : 'pcc_missing_or_not_used';

                $meta = array_merge($requestMeta, $inventory, [
                    'reason_code' => $reasonCode,
                    'normalized_offer_count' => 0,
                ]);

                $this->diagnosticLogger->log(
                    connection: $connection,
                    action: 'search',
                    status: 'warning',
                    safeMessage: 'Sabre response contained no normalizable offers.',
                    meta: $meta,
                );
                Log::warning(
                    $reasonCode === 'pcc_missing_or_not_used'
                        ? 'sabre.adapter.pcc_missing_or_not_used'
                        : 'sabre.adapter.normalization_zero_offers',
                    array_merge(['provider' => 'sabre'], $meta)
                );

                return new FlightSearchResultData(
                    supplier_provider: SupplierProvider::Sabre,
                    offers: [],
                    warnings: ['No Sabre offers were returned for this search.'],
                    meta: ['connection_id' => $connection->id]
                );
            }

            $successMeta = array_merge($requestMeta, $inventory, [
                'normalized_offer_count' => count($offers),
            ]);

            $this->diagnosticLogger->log(
                connection: $connection,
                action: 'search',
                status: 'success',
                safeMessage: 'Sabre search returned normalized offers.',
                meta: $successMeta,
            );
            Log::info('sabre.adapter.search_success', array_merge(['provider' => 'sabre'], $successMeta));

            return new FlightSearchResultData(
                supplier_provider: SupplierProvider::Sabre,
                offers: $offers,
                warnings: [],
                meta: array_merge(
                    ['connection_id' => $connection->id, 'selected_sabre_lanes' => $selectedLanes],
                    in_array('ndc', $selectedLanes, true) ? ['ndc_lane_pending_merge' => true] : [],
                ),
            );
        } catch (Throwable $e) {
            $reason = $this->classifyAdapterFailure($e);
            $logMeta = array_merge($requestMeta, [
                'reason_code' => $reason,
                'exception_class' => $e::class,
            ]);

            $this->diagnosticLogger->log(
                connection: $connection,
                action: 'search',
                status: 'failed',
                safeMessage: 'Sabre adapter search failed.',
                meta: $logMeta,
            );
            Log::warning('sabre.adapter.search_failed', array_merge(['provider' => 'sabre'], $logMeta));

            return new FlightSearchResultData(
                supplier_provider: SupplierProvider::Sabre,
                offers: [],
                warnings: ['Sabre search is temporarily unavailable. Please try again later.'],
                meta: ['connection_id' => $connection->id]
            );
        }
    }

    /**
     * NDC lane without GDS/BFM — routes to {@see SabreNdcOfferSearchService}.
     *
     * @param  array<string, mixed>  $requestMeta
     * @param  array<string, mixed>  $laneDiagnostics
     */
    protected function searchNdcLaneOnly(
        SupplierConnection $connection,
        FlightSearchRequestData $request,
        array $requestMeta,
        array $laneDiagnostics,
    ): FlightSearchResultData {
        Log::info('sabre.gds_suppressed_for_ndc_only_search', array_merge($requestMeta, [
            'event' => 'sabre.gds_suppressed_for_ndc_only_search',
            'selected_sabre_lanes' => is_array($laneDiagnostics['selected_sabre_lanes'] ?? null)
                ? $laneDiagnostics['selected_sabre_lanes']
                : ['ndc'],
            'gds_called' => false,
            'mutation_attempted' => false,
            'branded_fares_search_probe_skipped' => true,
            'endpoint_path_gds' => (string) config('suppliers.sabre.shop_path', '/v4/offers/shop'),
        ]));

        $ndcResult = $this->ndcOfferSearchService->search($request, $connection);
        $ndcDiagnostics = is_array($ndcResult['diagnostics'] ?? null) ? $ndcResult['diagnostics'] : [];
        $offers = is_array($ndcResult['offers'] ?? null) ? $ndcResult['offers'] : [];
        $warnings = is_array($ndcResult['warnings'] ?? null) ? $ndcResult['warnings'] : [];

        $meta = array_merge(
            [
                'connection_id' => $connection->id,
                'selected_sabre_lanes' => ['ndc'],
                'gds_suppressed' => true,
                'gds_results_suppressed' => true,
                'gds_called' => false,
                'supplier_lane' => 'sabre_ndc',
            ],
            $laneDiagnostics,
            ['ndc_search' => $ndcDiagnostics],
        );

        $reasonCode = (string) ($ndcDiagnostics['reason_code'] ?? 'sabre_ndc_lane');
        $logStatus = $offers !== [] ? 'success' : ($reasonCode === 'sabre_ndc_live_search_http_disabled' ? 'warning' : 'warning');

        $this->diagnosticLogger->log(
            connection: $connection,
            action: 'search',
            status: $logStatus,
            safeMessage: (string) ($ndcDiagnostics['safe_message'] ?? 'Sabre NDC search completed.'),
            meta: array_merge($requestMeta, $meta, ['reason_code' => $reasonCode]),
        );
        Log::info('sabre.adapter.ndc_lane_search', array_merge(['provider' => 'sabre'], $requestMeta, $meta));

        return new FlightSearchResultData(
            supplier_provider: SupplierProvider::Sabre,
            offers: $offers,
            warnings: $warnings,
            meta: $meta,
        );
    }

    public function provider(): SupplierProvider
    {
        return SupplierProvider::Sabre;
    }

    public function validateOffer(NormalizedFlightOfferData|string $offer, FlightSearchRequestData $request, SupplierConnection $connection): OfferValidationResultData
    {
        $original = is_string($offer) ? null : $offer;
        $originalOfferId = is_string($offer) ? $offer : $offer->offer_id;
        $brandedContext = [];
        if ($original !== null) {
            $sourceArray = $original->toArray();
            $brandedContext = is_array(data_get($sourceArray, 'raw_payload.selected_branded_fare_checkout_context'))
                ? data_get($sourceArray, 'raw_payload.selected_branded_fare_checkout_context')
                : [];
        }

        if (trim((string) ($request->search_id ?? '')) === '') {
            Log::warning('sabre.adapter.validate_offer.missing_search_id', [
                'offer_id' => $originalOfferId,
                'origin' => $request->origin,
                'destination' => $request->destination,
            ]);
        }

        $searchResult = $this->search($request, $connection);
        $refreshOfferCount = count($searchResult->offers);
        $refreshMeta = is_array($searchResult->meta) ? $searchResult->meta : [];
        $reasonCode = (string) ($refreshMeta['reason_code'] ?? '');

        if ($searchResult->warnings !== [] && $refreshOfferCount === 0) {
            return new OfferValidationResultData(
                is_valid: false,
                status: 'provider_error',
                original_offer_id: $originalOfferId,
                warnings: ['Fare validation is temporarily unavailable. Please try again.'],
                meta: [
                    'reason_code' => 'fare_validation_temporarily_unavailable',
                    'refresh_offer_count' => 0,
                    'refresh_result' => $reasonCode !== '' ? $reasonCode : 'provider_error',
                ],
            );
        }

        $matcher = app(SabreSelectedOfferDeterministicMatcher::class);
        $matched = $matcher->match($searchResult->offers, $offer, $brandedContext);
        $matchStrategy = $matched['match_strategy'] ?? null;
        $matchedOffer = $matched['offer'] ?? null;

        if ($matchedOffer === null) {
            return new OfferValidationResultData(
                is_valid: false,
                status: 'unavailable',
                original_offer_id: $originalOfferId,
                warnings: ['This fare is no longer available. Please refresh results and select again.'],
                meta: [
                    'reason_code' => 'fare_no_longer_available',
                    'refresh_offer_count' => $refreshOfferCount,
                    'refresh_result' => 'no_selected_offer_match',
                    'selected_offer_matched' => false,
                ],
            );
        }

        $oldTotal = $original?->fare_breakdown->supplier_total;
        $newTotal = $matchedOffer->fare_breakdown->supplier_total;
        $priceChanged = $oldTotal !== null && abs($oldTotal - $newTotal) > 0.009;

        return new OfferValidationResultData(
            is_valid: ! $priceChanged,
            status: $priceChanged ? 'price_changed' : 'valid',
            original_offer_id: $originalOfferId,
            validated_offer: $matchedOffer,
            price_changed: $priceChanged,
            old_total: $oldTotal,
            new_total: $newTotal,
            currency: $matchedOffer->fare_breakdown->currency,
            warnings: $priceChanged ? ['Fare changed during validation. Please review the updated fare before continuing.'] : [],
            meta: [
                'refresh_offer_count' => $refreshOfferCount,
                'selected_offer_matched' => true,
                'match_strategy' => $matchStrategy,
                'refresh_result' => 'success',
            ],
        );
    }

    /**
     * One metadata-only line per Sabre shop to explain branded_fares mapping gaps (no raw payload / PII).
     *
     * @param  array<string, mixed>  $response
     * @param  list<NormalizedFlightOfferData>  $offers
     */
    protected function logBrandedFaresProbe(FlightSearchRequestData $request, array $response, array $offers): void
    {
        if (! config('suppliers.sabre.branded_fares_probe_enabled')) {
            return;
        }

        Log::warning('sabre.branded_fares_probe', array_merge(
            ['provider' => 'sabre'],
            $this->safeBrandedFaresProbeRouteMeta($request),
            $this->normalizer->brandedFaresProbeDiagnostics($response, $offers),
        ));
    }

    /**
     * @return array<string, string>
     */
    protected function safeBrandedFaresProbeRouteMeta(FlightSearchRequestData $request): array
    {
        return [
            'origin' => $request->origin,
            'destination' => $request->destination,
            'trip_type' => $request->trip_type,
            'departure_date' => $request->departure_date,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function safeSearchContextMeta(FlightSearchRequestData $request, SupplierConnection $connection): array
    {
        return [
            'search_id' => (string) ($request->search_id ?? ''),
            'connection_id' => $connection->id,
            'environment' => $connection->environment->value,
            'origin' => $request->origin,
            'destination' => $request->destination,
            'departure_date' => $request->departure_date,
            'trip_type' => $request->trip_type,
            'cabin' => $request->cabin,
            'passenger_counts' => [
                'adults' => $request->adults,
                'children' => $request->children,
                'infants' => $request->infants,
            ],
            'pcc_present' => $this->pccPresent($connection),
            'pcc_sent_in_shop_request' => $this->client->includesPccInShopRequest($connection),
        ];
    }

    protected function pccPresent(SupplierConnection $connection): bool
    {
        $cred = is_array($connection->credentials) ? $connection->credentials : [];
        $settings = is_array($connection->settings) ? $connection->settings : [];
        foreach (['pcc', 'PCC', 'pseudo_city_code', 'pseudoCityCode'] as $key) {
            if (trim((string) ($cred[$key] ?? '')) !== '') {
                return true;
            }
            if (trim((string) data_get($settings, $key)) !== '') {
                return true;
            }
        }

        return false;
    }

    protected function classifyAdapterFailure(Throwable $e): string
    {
        $prev = $e->getPrevious();
        if ($prev instanceof ConnectionException) {
            return 'sabre_timeout';
        }

        $msg = $e->getMessage();
        if (str_contains($msg, 'authentication failed')) {
            return 'sabre_token_failed';
        }
        if (str_contains($msg, 'authentication response is malformed')) {
            return 'sabre_provider_error';
        }
        if (str_contains($msg, 'search request failed')) {
            return 'sabre_search_failed';
        }
        if (str_contains($msg, 'response is malformed')) {
            return 'sabre_request_invalid';
        }
        if (str_contains($msg, 'temporarily unavailable')) {
            return 'sabre_provider_error';
        }

        return 'sabre_provider_error';
    }

    /**
     * @param  list<NormalizedFlightOfferData>  $offers
     */
    protected function matchReplayOffer(array $offers, NormalizedFlightOfferData|string $source): ?NormalizedFlightOfferData
    {
        $sourceOffer = is_string($source) ? null : $source;
        $sourceId = is_string($source) ? $source : $source->offer_id;

        foreach ($offers as $candidate) {
            if ($candidate->offer_id === $sourceId) {
                return $candidate;
            }

            if ($sourceOffer === null) {
                continue;
            }

            if (
                $candidate->airline_code === $sourceOffer->airline_code
                && $candidate->origin === $sourceOffer->origin
                && $candidate->destination === $sourceOffer->destination
                && $candidate->departure_at === $sourceOffer->departure_at
                && ($candidate->flight_number ?? '') === ($sourceOffer->flight_number ?? '')
                && strtolower($candidate->cabin) === strtolower($sourceOffer->cabin)
            ) {
                return $candidate;
            }
        }

        return null;
    }
}
