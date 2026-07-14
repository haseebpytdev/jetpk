<?php

namespace App\Services\Suppliers\Sabre\Ndc;

use App\Data\FlightSearchRequestData;
use App\Data\NormalizedFlightOfferData;
use App\Models\SupplierConnection;
use App\Services\Suppliers\Sabre\Core\SabreClient;
use App\Services\Suppliers\SupplierDiagnosticLogger;
use App\Support\Suppliers\SabreChannelGateResolver;
use App\Support\Suppliers\SabreNdcNoOfferReasonClassifier;
use App\Support\Suppliers\SabreNdcOfferShopSafeErrorExtractor;
use App\Support\Suppliers\SabreNdcSearchDiagnostics;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Log;

/**
 * Sabre NDC Offer Shop — POST /v5/offers/shop when env-gated; never calls GDS/BFM.
 */
final class SabreNdcOfferSearchService
{
    public function __construct(
        private readonly SabreChannelGateResolver $channelGateResolver,
        private readonly SabreNdcOfferShopRequestBuilder $requestBuilder,
        private readonly SabreNdcOfferSearchNormalizer $normalizer,
        private readonly SabreClient $sabreClient,
        private readonly SupplierDiagnosticLogger $diagnosticLogger,
        private readonly SabreNdcOfferShopSafeErrorExtractor $safeErrorExtractor,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function status(): array
    {
        $enabled = (bool) config('suppliers.sabre.ndc.search_enabled', false);
        $blockers = $enabled ? [] : ['search_disabled_by_env'];

        return [
            'search_enabled' => $enabled,
            'blockers' => $blockers,
            'endpoint_path' => config('suppliers.sabre.ndc.offer_shop_path'),
            'live_supplier_call_attempted' => false,
            'mutation_attempted' => false,
            'gds_called' => false,
            'selected_lane' => 'ndc',
        ];
    }

    /**
     * @return array{
     *     offers: list<NormalizedFlightOfferData>,
     *     diagnostics: array<string, mixed>,
     *     warnings: list<string>
     * }
     */
    public function search(FlightSearchRequestData $request, SupplierConnection $connection): array
    {
        $lane = $this->channelGateResolver->diagnostics($connection);
        $trace = SabreNdcSearchDiagnostics::traceContext($request, $connection, $lane);
        $endpointPath = (string) config('suppliers.sabre.ndc.offer_shop_path', '/v5/offers/shop');

        $baseDiagnostics = array_merge($lane, $trace, [
            'lane' => 'sabre_ndc',
            'selected_lane' => 'ndc',
            'endpoint_path' => $endpointPath,
            'gds_called' => false,
            'mutation_attempted' => false,
            'live_supplier_call_attempted' => false,
        ]);

        $blockers = $this->searchBlockers($connection, $lane);
        if ($blockers !== []) {
            $disabledOnly = $blockers === ['search_disabled_by_env']
                || (count($blockers) === 1 && $blockers[0] === 'search_disabled_by_env');

            $diagnostics = array_merge($baseDiagnostics, [
                'blockers' => $blockers,
                'reason_code' => $disabledOnly ? 'sabre_ndc_live_search_http_disabled' : 'sabre_ndc_search_blocked',
                'no_offer_reason' => SabreNdcNoOfferReasonClassifier::classify([
                    'blockers' => $blockers,
                ]),
                'safe_message' => $disabledOnly
                    ? 'NDC lane enabled; live search HTTP disabled'
                    : 'Sabre NDC search blocked by lane gates.',
                'request_shape' => $this->requestBuilder->payloadStructureSummary(
                    $this->requestBuilder->build($request, $connection),
                ),
                'request_shape_summary' => $this->requestBuilder->requestShapeSummary(
                    $request,
                    $this->requestBuilder->build($request, $connection),
                    $connection,
                ),
            ]);

            $this->logSearchDiagnostic($connection, $request, $diagnostics, 'warning');

            return [
                'offers' => [],
                'diagnostics' => $diagnostics,
                'warnings' => $disabledOnly ? [] : ['Sabre NDC search is not available for this connection.'],
            ];
        }

        $selectedVariant = $this->requestBuilder->resolvePublicSearchVariant(null);
        $payload = $this->requestBuilder->build($request, $connection, $selectedVariant);
        $requestShape = $this->requestBuilder->payloadStructureSummary($payload);
        $requestShapeSummary = $this->requestBuilder->requestShapeSummary($request, $payload, $connection, $selectedVariant);

        Log::info('sabre.ndc.search.request_ready', array_merge($trace, [
            'event' => 'sabre.ndc.search.request_ready',
            'endpoint_path' => $endpointPath,
            'selected_variant' => $selectedVariant,
            'request_shape_summary' => $requestShapeSummary,
            'pcc_present' => $this->requestBuilder->includesPccInPayload($connection),
            'no_pii' => true,
            'live_supplier_call_about_to_attempt' => true,
            'mutation_attempted' => false,
            'gds_called' => false,
        ]));

        try {
            $response = $this->sabreClient->postAuthenticatedJson($connection, $endpointPath, $payload);
            $json = is_array($response->json()) ? $response->json() : [];
            $shape = $this->normalizer->responseShapeSummary($json);
            $offerCountRaw = (int) ($shape['offer_count'] ?? 0);
            $offers = $response->successful()
                ? $this->normalizer->normalize($json, $connection, $request)
                : [];
            $safeError = $this->safeErrorExtractor->extract(
                $response->status(),
                $json,
                $response->body(),
                [
                    'offer_count_raw' => $offerCountRaw,
                    'normalized_offer_count' => count($offers),
                ],
            );

            $diagnostics = array_merge($baseDiagnostics, $shape, $safeError, [
                'http_status' => $response->status(),
                'offer_count_raw' => $offerCountRaw,
                'live_supplier_call_attempted' => true,
                'request_shape' => $requestShape,
                'request_shape_summary' => $requestShapeSummary,
                'selected_variant' => $selectedVariant,
                'pcc_present' => $this->requestBuilder->includesPccInPayload($connection),
                'blockers' => [],
                'response_shape' => (string) ($safeError['response_shape'] ?? ($shape['response_shape'] ?? 'unknown')),
            ]);

            if (! $response->successful()) {
                $diagnostics['reason_code'] = 'sabre_ndc_search_http_'.$response->status();
                $diagnostics['no_offer_reason'] = SabreNdcNoOfferReasonClassifier::classify($diagnostics);
                $diagnostics['normalized_offer_count'] = 0;
                $diagnostics['safe_message'] = (string) ($safeError['safe_error_message'] ?? 'Sabre NDC offer shop HTTP error.');
                $this->logResponseSummary($trace, $endpointPath, $diagnostics);
                $this->logSearchDiagnostic($connection, $request, $diagnostics, 'failed');

                return [
                    'offers' => [],
                    'diagnostics' => $diagnostics,
                    'warnings' => [],
                ];
            }

            $offers = $response->successful()
                ? $offers
                : [];
            $diagnostics['normalized_offer_count'] = count($offers);
            $diagnostics['reason_code'] = $offers === [] ? 'sabre_ndc_zero_offers' : 'sabre_ndc_search_success';
            $diagnostics['no_offer_reason'] = $offers === []
                ? SabreNdcNoOfferReasonClassifier::classify($diagnostics)
                : null;

            $this->logResponseSummary($trace, $endpointPath, $diagnostics);

            $this->logSearchDiagnostic(
                $connection,
                $request,
                $diagnostics,
                $offers === [] ? 'warning' : 'success',
            );

            return [
                'offers' => $offers,
                'diagnostics' => $diagnostics,
                'warnings' => $offers === [] ? ['No Sabre NDC offers were returned for this search.'] : [],
            ];
        } catch (ConnectionException $e) {
            $diagnostics = array_merge($baseDiagnostics, [
                'reason_code' => 'sabre_ndc_timeout',
                'safe_error_family' => 'transport_timeout',
                'exception_class' => $e::class,
                'request_shape' => $requestShape,
                'no_offer_reason' => 'ndc_http_error',
            ]);
            $this->logResponseSummary($trace, $endpointPath, $diagnostics);
            $this->logSearchDiagnostic($connection, $request, $diagnostics, 'failed');

            return [
                'offers' => [],
                'diagnostics' => $diagnostics,
                'warnings' => ['Sabre NDC search is temporarily unavailable. Please try again later.'],
            ];
        } catch (\Throwable $e) {
            $diagnostics = array_merge($baseDiagnostics, [
                'reason_code' => 'sabre_ndc_provider_error',
                'safe_error_family' => 'unexpected',
                'exception_class' => $e::class,
                'request_shape' => $requestShape,
                'no_offer_reason' => 'ndc_http_error',
            ]);
            $this->logResponseSummary($trace, $endpointPath, $diagnostics);
            $this->logSearchDiagnostic($connection, $request, $diagnostics, 'failed');

            return [
                'offers' => [],
                'diagnostics' => $diagnostics,
                'warnings' => ['Sabre NDC search is temporarily unavailable. Please try again later.'],
            ];
        }
    }

    /**
     * @param  array<string, mixed>  $lane
     * @return list<string>
     */
    private function searchBlockers(SupplierConnection $connection, array $lane): array
    {
        $blockers = $this->channelGateResolver->ndcLaneBlockers($connection);

        if (! (bool) ($lane['effective_ndc_enabled'] ?? false)) {
            $blockers[] = 'effective_ndc_disabled';
        }

        if (! in_array('ndc', is_array($lane['selected_sabre_lanes'] ?? null) ? $lane['selected_sabre_lanes'] : [], true)) {
            $blockers[] = 'ndc_lane_not_selected';
        }

        if (! (bool) config('suppliers.sabre.ndc.search_enabled', false)) {
            $blockers[] = 'search_disabled_by_env';
        }

        return array_values(array_unique($blockers));
    }

    /**
     * @param  array<string, mixed>  $trace
     * @param  array<string, mixed>  $diagnostics
     */
    private function logResponseSummary(array $trace, string $endpointPath, array $diagnostics): void
    {
        Log::info('sabre.ndc.search.response_summary', array_merge($trace, [
            'event' => 'sabre.ndc.search.response_summary',
            'endpoint_path' => $endpointPath,
            'http_status' => $diagnostics['http_status'] ?? null,
            'response_shape' => $diagnostics['response_shape'] ?? 'unknown',
            'response_top_level_keys' => $diagnostics['response_top_level_keys'] ?? [],
            'application_results_status' => $diagnostics['application_results_status'] ?? null,
            'safe_error_family' => $diagnostics['safe_error_family'] ?? null,
            'safe_error_code' => $diagnostics['safe_error_code'] ?? null,
            'safe_error_message' => $diagnostics['safe_error_message'] ?? null,
            'message_code' => $diagnostics['message_code'] ?? null,
            'message_text' => $diagnostics['message_text'] ?? null,
            'message_count' => $diagnostics['message_count'] ?? null,
            'sabre_transaction_id' => $diagnostics['sabre_transaction_id'] ?? null,
            'validation_paths' => $diagnostics['validation_paths'] ?? [],
            'itinerary_group_count' => $diagnostics['itinerary_group_count'] ?? null,
            'itinerary_count' => $diagnostics['itinerary_count'] ?? null,
            'pricing_information_count' => $diagnostics['pricing_information_count'] ?? null,
            'schedule_desc_count' => $diagnostics['schedule_desc_count'] ?? null,
            'message_rows' => $diagnostics['message_rows'] ?? [],
            'offer_count_raw' => $diagnostics['offer_count_raw'] ?? null,
            'normalized_offer_count' => $diagnostics['normalized_offer_count'] ?? 0,
            'no_offer_reason' => $diagnostics['no_offer_reason'] ?? null,
            'gds_called' => false,
            'mutation_attempted' => false,
            'live_supplier_call_attempted' => (bool) ($diagnostics['live_supplier_call_attempted'] ?? true),
        ]));
    }

    /**
     * @param  array<string, mixed>  $diagnostics
     */
    private function logSearchDiagnostic(
        SupplierConnection $connection,
        FlightSearchRequestData $request,
        array $diagnostics,
        string $status,
    ): void {
        $safeMeta = array_merge($diagnostics, [
            'provider' => 'sabre',
            'supplier_lane' => 'sabre_ndc',
            'search_id' => (string) ($request->search_id ?? ''),
        ]);

        $this->diagnosticLogger->log(
            connection: $connection,
            action: 'search',
            status: $status,
            safeMessage: (string) ($diagnostics['safe_message'] ?? 'Sabre NDC offer shop completed.'),
            meta: $safeMeta,
        );

        Log::info('sabre.ndc.search', $safeMeta);
    }
}
