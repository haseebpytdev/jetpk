<?php

namespace App\Services\Suppliers\PiaNdc;

use App\Data\FlightSearchRequestData;
use App\Data\FlightSearchResultData;
use App\Data\NormalizedFlightOfferData;
use App\Enums\SupplierProvider;
use App\Models\SupplierConnection;
use App\Services\Suppliers\PiaNdc\Exceptions\PiaNdcException;
use App\Services\Suppliers\SupplierConnectionService;
use App\Services\Suppliers\SupplierDiagnosticLogger;
use App\Support\Security\SensitiveDataRedactor;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Throwable;

class PiaNdcFlightSearchService
{
    public function __construct(
        private readonly PiaNdcClient $client,
        private readonly PiaNdcConfigResolver $configResolver,
        private readonly PiaNdcXmlBuilder $xmlBuilder,
        private readonly PiaNdcResponseNormalizer $normalizer,
        private readonly PiaNdcXmlParser $xmlParser,
        private readonly PiaNdcCorrelationContext $correlationContext,
        private readonly SupplierDiagnosticLogger $diagnosticLogger,
        private readonly SupplierConnectionService $supplierConnectionService,
    ) {}

    /**
     * CLI-only AirShopping attempt that saves sanitized raw/normalized diagnostics.
     *
     * @return array{result: FlightSearchResultData, diagnostic_path: string}
     */
    public function runAirShoppingDiagnostic(
        FlightSearchRequestData $request,
        SupplierConnection $connection,
    ): array {
        $config = $this->configResolver->resolve($connection);
        $correlationId = $this->correlationContext->newCorrelationId();
        $requestXml = $this->xmlBuilder->buildAirShoppingRequest($request, $config);
        $sanitizedRequestXml = $this->client->sanitizeXmlForDiagnostics($requestXml);
        $route = strtoupper($request->origin).'-'.strtoupper($request->destination);
        $date = $request->departure_date;

        $httpStatus = null;
        $responseXml = null;
        $parsedResponse = null;
        $offers = [];
        $warnings = [];
        $meta = ['connection_id' => $connection->id, 'correlation_id' => $correlationId];
        $nodeCounts = array_fill_keys(PiaNdcXmlParser::AIR_SHOPPING_DEBUG_NODE_LOCAL_NAMES, 0);
        $providerErrorCode = null;
        $providerErrorMessage = null;

        try {
            $response = $this->client->call($connection, 'air_shopping', $requestXml, [
                'request_context' => 'pia-ndc:test-search',
                'correlation_id' => $correlationId,
            ]);
            $diagnostic = is_array($response['_ota_diagnostic'] ?? null) ? $response['_ota_diagnostic'] : [];
            $correlationId = (string) ($diagnostic['correlation_id'] ?? $correlationId);
            $httpStatus = isset($diagnostic['http_status']) ? (int) $diagnostic['http_status'] : null;
            $parsedResponse = $response;
            $responseXml = is_string($response['raw_xml'] ?? null)
                ? $this->client->sanitizeXmlForDiagnostics($response['raw_xml'])
                : null;
            $offers = $this->normalizer->normalizeSearchResponse($response, $connection, $correlationId);
            $warnings = $this->searchWarnings($response, $offers);
            $meta = ['connection_id' => $connection->id, 'correlation_id' => $correlationId];
            if ($responseXml !== null) {
                $nodeCounts = $this->xmlParser->countNodesByLocalName($responseXml);
            }

            $this->diagnosticLogger->log(
                connection: $connection,
                action: 'search',
                status: $offers === [] ? 'warning' : 'success',
                durationMs: isset($diagnostic['duration_ms']) ? (int) $diagnostic['duration_ms'] : null,
                safeMessage: $offers === [] ? 'PIA NDC returned no fares for the route/date.' : 'PIA NDC search completed.',
                correlationId: $correlationId !== '' ? $correlationId : null,
                meta: ['offers_count' => count($offers), 'request_context' => 'pia-ndc:test-search'],
            );
        } catch (PiaNdcException $exception) {
            $safeMeta = $exception->safeDiagnosticMeta('air_shopping');
            $correlationId = (string) ($safeMeta['correlation_id'] ?? $correlationId);
            $httpStatus = isset($safeMeta['http_status']) ? (int) $safeMeta['http_status'] : null;
            $meta = array_merge(['connection_id' => $connection->id], $safeMeta);
            $warnings = ['Provider search is temporarily unavailable.'];
            $responseXml = is_string($exception->context['response_xml'] ?? null)
                ? $exception->context['response_xml']
                : null;
            if ($responseXml !== null) {
                $nodeCounts = $this->xmlParser->countNodesByLocalName($responseXml);
                try {
                    $parsedResponse = $this->xmlParser->parse($responseXml);
                } catch (Throwable) {
                    $parsedResponse = null;
                }
            }
            [$providerErrorCode, $providerErrorMessage] = $this->resolveProviderError($parsedResponse, $safeMeta);

            $this->diagnosticLogger->log(
                connection: $connection,
                action: 'search',
                status: 'failed',
                safeMessage: $exception->safeMessage,
                correlationId: $correlationId !== '' ? $correlationId : null,
                meta: array_merge($safeMeta, ['request_context' => 'pia-ndc:test-search']),
            );
        } catch (Throwable $exception) {
            Log::channel('pia-ndc')->warning('pia_ndc.search.unexpected', [
                'supplier_connection_id' => $connection->id,
                'exception' => $exception::class,
                'request_context' => 'pia-ndc:test-search',
            ]);
            $warnings = ['Provider search is temporarily unavailable.'];
        }

        if ($providerErrorCode === null && $providerErrorMessage === null) {
            [$providerErrorCode, $providerErrorMessage] = $this->resolveProviderError($parsedResponse, $meta);
        }

        $result = new FlightSearchResultData(
            supplier_provider: SupplierProvider::PiaNdc,
            offers: $offers,
            warnings: $warnings,
            meta: $meta,
        );

        $diagnosticPath = $this->saveAirShoppingDiagnosticFiles(
            connectionId: (int) $connection->id,
            correlationId: $correlationId,
            endpoint: (string) $config['endpoint_url'],
            route: $route,
            date: $date,
            httpStatus: $httpStatus,
            providerErrorCode: $providerErrorCode,
            providerErrorMessage: $providerErrorMessage,
            offersCount: count($offers),
            nodeCounts: $nodeCounts,
            requestXml: $sanitizedRequestXml,
            responseXml: $responseXml,
            result: $result,
            parsedResponse: $parsedResponse,
        );

        return [
            'result' => $result,
            'diagnostic_path' => $diagnosticPath,
        ];
    }

    public function search(FlightSearchRequestData $request, SupplierConnection $connection): FlightSearchResultData
    {
        try {
            $config = $this->configResolver->resolve($connection);
            $xml = $this->xmlBuilder->buildAirShoppingRequest($request, $config);
            $response = $this->client->call($connection, 'air_shopping', $xml, ['request_context' => 'search']);
            $diagnostic = is_array($response['_ota_diagnostic'] ?? null) ? $response['_ota_diagnostic'] : [];
            $correlationId = (string) ($diagnostic['correlation_id'] ?? '');
            $offers = $this->normalizer->normalizeSearchResponse($response, $connection, $correlationId);

            if ($offers !== []) {
                $this->supplierConnectionService->recordSupplierSearchSuccess(
                    $connection,
                    'pia_ndc_air_shopping',
                    count($offers),
                );
            }

            $this->diagnosticLogger->log(
                connection: $connection,
                action: 'search',
                status: $offers === [] ? 'warning' : 'success',
                durationMs: isset($diagnostic['duration_ms']) ? (int) $diagnostic['duration_ms'] : null,
                safeMessage: $offers === [] ? 'PIA NDC returned no fares for the route/date.' : 'PIA NDC search completed.',
                correlationId: $correlationId !== '' ? $correlationId : null,
                meta: ['offers_count' => count($offers)],
            );

            return new FlightSearchResultData(
                supplier_provider: SupplierProvider::PiaNdc,
                offers: $offers,
                warnings: $this->searchWarnings($response, $offers),
                meta: ['connection_id' => $connection->id, 'correlation_id' => $correlationId],
            );
        } catch (PiaNdcException $exception) {
            $safeMeta = $exception->safeDiagnosticMeta('air_shopping');

            $this->diagnosticLogger->log(
                connection: $connection,
                action: 'search',
                status: 'failed',
                safeMessage: $exception->safeMessage,
                correlationId: isset($safeMeta['correlation_id']) ? (string) $safeMeta['correlation_id'] : null,
                meta: $safeMeta,
            );

            return new FlightSearchResultData(
                supplier_provider: SupplierProvider::PiaNdc,
                offers: [],
                warnings: ['Provider search is temporarily unavailable.'],
                meta: array_merge(['connection_id' => $connection->id], $safeMeta),
            );
        } catch (Throwable $exception) {
            Log::channel('pia-ndc')->warning('pia_ndc.search.unexpected', [
                'supplier_connection_id' => $connection->id,
                'exception' => $exception::class,
            ]);

            return new FlightSearchResultData(
                supplier_provider: SupplierProvider::PiaNdc,
                offers: [],
                warnings: ['Provider search is temporarily unavailable.'],
                meta: ['connection_id' => $connection->id],
            );
        }
    }

    /**
     * @param  array<string, int>  $nodeCounts
     * @param  ?array<string, mixed>  $parsedResponse
     */
    private function saveAirShoppingDiagnosticFiles(
        int $connectionId,
        string $correlationId,
        string $endpoint,
        string $route,
        string $date,
        ?int $httpStatus,
        ?string $providerErrorCode,
        ?string $providerErrorMessage,
        int $offersCount,
        array $nodeCounts,
        string $requestXml,
        ?string $responseXml,
        FlightSearchResultData $result,
        ?array $parsedResponse,
    ): string {
        $directory = storage_path(
            'app/diagnostics/pia-ndc/air-shopping/'.$connectionId.'/'.($correlationId !== '' ? $correlationId : 'unknown'),
        );
        File::ensureDirectoryExists($directory);

        file_put_contents($directory.'/request.xml', $requestXml);
        file_put_contents($directory.'/response.xml', $responseXml ?? '');

        $normalizedPayload = SensitiveDataRedactor::redact([
            'offers_count' => $offersCount,
            'offers' => array_map(
                static fn ($offer) => SensitiveDataRedactor::redact($offer->toArray()),
                $result->offers,
            ),
            'warnings' => $result->warnings,
            'provider_warnings' => is_array($parsedResponse['warnings'] ?? null) ? $parsedResponse['warnings'] : [],
            'provider_errors' => is_array($parsedResponse['errors'] ?? null) ? $parsedResponse['errors'] : [],
            'parsed_offer_count' => is_array($parsedResponse['parsed']['offers'] ?? null)
                ? count($parsedResponse['parsed']['offers'])
                : 0,
        ]);
        file_put_contents(
            $directory.'/normalized.json',
            json_encode($normalizedPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        );

        $summary = [
            'connection_id' => $connectionId,
            'endpoint' => $endpoint,
            'correlation_id' => $correlationId,
            'route' => $route,
            'date' => $date,
            'http_status' => $httpStatus,
            'provider_error_code' => $providerErrorCode,
            'provider_error_message' => $providerErrorMessage,
            'offers_count' => $offersCount,
            'detected_warning_count' => $nodeCounts['Warning'] ?? 0,
            'detected_offer_nodes_count' => $nodeCounts['Offer'] ?? 0,
            'detected_priced_offer_nodes_count' => $nodeCounts['PricedOffer'] ?? 0,
            'detected_offer_item_nodes_count' => $nodeCounts['OfferItem'] ?? 0,
        ];
        file_put_contents(
            $directory.'/summary.json',
            json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        );

        return $directory;
    }

    /**
     * @param  ?array<string, mixed>  $parsedResponse
     * @param  array<string, mixed>  $meta
     * @return array{0: ?string, 1: ?string}
     */
    private function resolveProviderError(?array $parsedResponse, array $meta): array
    {
        if (is_array($parsedResponse['errors'] ?? null) && $parsedResponse['errors'] !== []) {
            $first = $parsedResponse['errors'][0];

            return [
                isset($first['code']) ? (string) $first['code'] : null,
                isset($first['message']) ? (string) $first['message'] : null,
            ];
        }

        if (is_array($meta['provider_errors'] ?? null) && $meta['provider_errors'] !== []) {
            $first = $meta['provider_errors'][0];

            return [
                isset($first['code']) ? (string) $first['code'] : null,
                isset($first['message']) ? (string) $first['message'] : null,
            ];
        }

        if (isset($meta['fault_code']) && is_scalar($meta['fault_code'])) {
            return [
                (string) $meta['fault_code'],
                isset($meta['fault_message']) && is_scalar($meta['fault_message']) ? (string) $meta['fault_message'] : null,
            ];
        }

        return [null, null];
    }

    /**
     * @param  array<string, mixed>  $parsedResponse
     * @param  list<NormalizedFlightOfferData>  $offers
     * @return list<string>
     */
    private function searchWarnings(array $parsedResponse, array $offers): array
    {
        if ($offers !== []) {
            return [];
        }

        $parsed = is_array($parsedResponse['parsed'] ?? null) ? $parsedResponse['parsed'] : [];
        $parsedOffers = is_array($parsed['offers'] ?? null) ? $parsed['offers'] : [];
        if ($parsedOffers !== []) {
            return ['Offers were returned but could not be normalized for display.'];
        }

        return ['No fares available for this route/date.'];
    }
}
