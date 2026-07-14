<?php

namespace App\Services\Suppliers\Sabre\Ndc;

use App\Data\FlightSearchRequestData;
use App\Models\SupplierConnection;
use App\Services\Suppliers\Sabre\Core\SabreClient;
use App\Support\Suppliers\SabreChannelGateResolver;
use App\Support\Suppliers\SabreNdcEntitlementEvidenceStore;
use App\Support\Suppliers\SabreNdcNoOfferReasonClassifier;
use App\Support\Suppliers\SabreNdcOfferShopSafeErrorExtractor;
use App\Support\Suppliers\SabreNdcSearchDiagnostics;
use Illuminate\Http\Client\ConnectionException;

/**
 * Dry-run and optional live Sabre NDC offer shop probe — /v5/offers/shop only.
 */
final class SabreNdcSearchDryRunService
{
    public const CONFIRM_PHRASE = 'SEND-SABRE-NDC-SEARCH';

    public function __construct(
        private readonly SabreChannelGateResolver $channelGateResolver,
        private readonly SabreNdcOfferShopRequestBuilder $requestBuilder,
        private readonly SabreNdcOfferSearchNormalizer $normalizer,
        private readonly SabreClient $sabreClient,
        private readonly SabreNdcOfferShopSafeErrorExtractor $safeErrorExtractor,
        private readonly SabreNdcEntitlementEvidenceStore $evidenceStore,
    ) {}

    /**
     * @param  array{
     *     carrier_code?: ?string,
     *     carrier_mode?: ?string,
     * }  $buildOptions
     * @return array<string, mixed>
     */
    public function run(
        SupplierConnection $connection,
        FlightSearchRequestData $request,
        bool $sendLive = false,
        ?string $variant = null,
        array $buildOptions = [],
    ): array {
        $lane = $this->channelGateResolver->diagnostics($connection);
        $endpointPath = (string) config('suppliers.sabre.ndc.offer_shop_path', '/v5/offers/shop');
        $trace = SabreNdcSearchDiagnostics::traceContext($request, $connection, $lane);
        $blockers = $this->buildBlockers($connection, $lane);
        $selectedVariant = $this->requestBuilder->selectedVariant($variant);

        $payload = $this->requestBuilder->build($request, $connection, $selectedVariant, $buildOptions);
        $carrierMeta = $this->requestBuilder->finalizePayload($payload, $buildOptions);
        $payload = $carrierMeta['payload'];
        $requestShapeSummary = $this->requestBuilder->requestShapeSummary($request, $payload, $connection, $selectedVariant);

        $base = array_merge($trace, [
            'endpoint_path' => $endpointPath,
            'dry_run' => ! $sendLive,
            'blockers' => $blockers,
            'selected_variant' => $selectedVariant,
            'request_shape' => $this->requestBuilder->payloadStructureSummary($payload),
            'request_shape_summary' => $requestShapeSummary,
            'pcc_present' => $this->requestBuilder->includesPccInPayload($connection),
            'gds_called' => false,
            'mutation_attempted' => false,
            'live_supplier_call_attempted' => false,
            'selected_lane' => 'ndc',
            'carrier_filter_applied' => (bool) ($carrierMeta['carrier_filter_applied'] ?? false),
            'unsupported_carrier_filter' => (bool) ($carrierMeta['unsupported_carrier_filter'] ?? false),
            'carrier_mode' => $carrierMeta['carrier_mode'] ?? null,
            'carrier_code' => $carrierMeta['carrier_code'] ?? null,
            'diagnostic_data_source_variant' => $this->requestBuilder->isDataSourceDiagnosticVariant($selectedVariant),
            'non_public_diagnostic_lane' => $this->requestBuilder->isDiagnosticOnlyVariant($selectedVariant)
                || $this->requestBuilder->isDataSourceDiagnosticVariant($selectedVariant),
        ]);

        if ($blockers !== []) {
            $disabledOnly = $blockers === ['search_disabled_by_env'];
            $diagnostics = array_merge($base, [
                'no_offer_reason' => SabreNdcNoOfferReasonClassifier::classify([
                    'blockers' => $blockers,
                ]),
                'reason_code' => $disabledOnly
                    ? 'sabre_ndc_live_search_http_disabled'
                    : 'sabre_ndc_search_blocked',
            ]);

            return $diagnostics;
        }

        if (! $sendLive) {
            return array_merge($base, [
                'reason_code' => 'sabre_ndc_dry_run_ready',
                'no_offer_reason' => null,
                'safe_message' => 'NDC shop request validated; no HTTP attempted.',
            ]);
        }

        try {
            $response = $this->sabreClient->postAuthenticatedJson($connection, $endpointPath, $payload);
            $json = is_array($response->json()) ? $response->json() : [];
            $shape = $this->normalizer->responseShapeSummary($json);
            $offers = $response->successful()
                ? $this->normalizer->normalize($json, $connection, $request)
                : [];
            $offerCountRaw = (int) ($shape['offer_count'] ?? 0);
            $normalizedCount = count($offers);
            $safeError = $this->safeErrorExtractor->extract(
                $response->status(),
                $json,
                $response->body(),
                [
                    'offer_count_raw' => $offerCountRaw,
                    'normalized_offer_count' => $normalizedCount,
                ],
            );
            $offers = $response->successful()
                ? $offers
                : [];

            $diagnostics = array_merge($base, $shape, $safeError, [
                'http_status' => $response->status(),
                'live_supplier_call_attempted' => true,
                'normalized_offer_count' => $normalizedCount,
                'offer_count_raw' => $offerCountRaw,
                'response_shape' => (string) ($safeError['response_shape'] ?? ($shape['response_shape'] ?? 'unknown')),
            ]);

            if (! $response->successful()) {
                $diagnostics['reason_code'] = 'sabre_ndc_search_http_'.$response->status();
            } else {
                $diagnostics['reason_code'] = $offers === [] ? 'sabre_ndc_zero_offers' : 'sabre_ndc_search_success';
            }

            $diagnostics['no_offer_reason'] = SabreNdcNoOfferReasonClassifier::classify($diagnostics);

            if ($this->requestBuilder->isDataSourceDiagnosticVariant($selectedVariant)
                || in_array($selectedVariant, [
                    SabreNdcOfferShopRequestBuilder::VARIANT_NDC_ONLY,
                    SabreNdcOfferShopRequestBuilder::VARIANT_ATPCO_ONLY_DIAGNOSTIC,
                    SabreNdcOfferShopRequestBuilder::VARIANT_NDC_PLUS_ATPCO_DIAGNOSTIC,
                ], true)) {
                $this->evidenceStore->storeVariantProbe((int) $connection->id, $selectedVariant, $diagnostics);
            }

            return $diagnostics;
        } catch (ConnectionException $e) {
            $diagnostics = array_merge($base, [
                'reason_code' => 'sabre_ndc_timeout',
                'safe_error_family' => 'transport_timeout',
                'safe_error_code' => 'transport_timeout',
                'safe_error_message' => 'Sabre NDC shop transport timeout.',
                'exception_class' => $e::class,
                'live_supplier_call_attempted' => true,
                'no_offer_reason' => 'ndc_http_error',
            ]);

            return $diagnostics;
        } catch (\Throwable $e) {
            $diagnostics = array_merge($base, [
                'reason_code' => 'sabre_ndc_provider_error',
                'safe_error_family' => 'unexpected',
                'safe_error_code' => 'unexpected',
                'safe_error_message' => 'Sabre NDC shop failed unexpectedly.',
                'exception_class' => $e::class,
                'live_supplier_call_attempted' => true,
                'no_offer_reason' => 'ndc_http_error',
            ]);

            return $diagnostics;
        }
    }

    /**
     * @param  array<string, mixed>  $lane
     * @return list<string>
     */
    private function buildBlockers(SupplierConnection $connection, array $lane): array
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
}
