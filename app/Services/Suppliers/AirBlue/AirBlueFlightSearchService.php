<?php

namespace App\Services\Suppliers\AirBlue;

use App\Data\FlightSearchRequestData;
use App\Data\FlightSearchResultData;
use App\Data\NormalizedFlightOfferData;
use App\Enums\AirBlueApiChannel;
use App\Enums\SupplierProvider;
use App\Models\SupplierConnection;
use App\Services\Suppliers\AirBlue\Exceptions\AirBlueException;
use App\Services\Suppliers\SupplierDiagnosticLogger;
use Illuminate\Support\Facades\Log;
use Throwable;

class AirBlueFlightSearchService
{
    public function __construct(
        private readonly AirBlueClient $client,
        private readonly AirBlueConfigResolver $configResolver,
        private readonly AirBlueXmlBuilder $ndcXmlBuilder,
        private readonly AirBlueOtaXmlBuilder $otaXmlBuilder,
        private readonly AirBlueResponseNormalizer $ndcNormalizer,
        private readonly AirBlueOtaResponseNormalizer $otaNormalizer,
        private readonly SupplierDiagnosticLogger $diagnosticLogger,
    ) {}

    public function search(FlightSearchRequestData $request, SupplierConnection $connection): FlightSearchResultData
    {
        try {
            $channel = $this->configResolver->apiChannel($connection);

            if ($channel === AirBlueApiChannel::ZapwaysOta) {
                return $this->searchOta($request, $connection);
            }

            return $this->searchNdc($request, $connection);
        } catch (AirBlueException $exception) {
            $this->diagnosticLogger->log(
                connection: $connection,
                action: 'search',
                status: 'failed',
                safeMessage: $exception->safeMessage,
                meta: ['error_code' => $exception->normalizedCode],
            );

            return new FlightSearchResultData(
                supplier_provider: SupplierProvider::Airblue,
                offers: [],
                warnings: ['Provider search is temporarily unavailable.'],
                meta: ['connection_id' => $connection->id, 'error_code' => $exception->normalizedCode],
            );
        } catch (Throwable $exception) {
            Log::channel('air-blue')->warning('airblue.search.unexpected', [
                'supplier_connection_id' => $connection->id,
                'exception' => $exception::class,
            ]);

            return new FlightSearchResultData(
                supplier_provider: SupplierProvider::Airblue,
                offers: [],
                warnings: ['Provider search is temporarily unavailable.'],
                meta: ['connection_id' => $connection->id],
            );
        }
    }

    private function searchNdc(FlightSearchRequestData $request, SupplierConnection $connection): FlightSearchResultData
    {
        $config = $this->configResolver->resolveNdc($connection);
        $xml = $this->ndcXmlBuilder->buildAirShoppingRequest($request, $config);
        $response = $this->client->callNdc($connection, 'air_shopping', $xml, ['request_context' => 'search']);
        $diagnostic = is_array($response['_ota_diagnostic'] ?? null) ? $response['_ota_diagnostic'] : [];
        $correlationId = (string) ($diagnostic['correlation_id'] ?? '');
        $offers = $this->ndcNormalizer->normalizeSearchResponse($response, $connection, $correlationId);

        $this->logSearchResult($connection, $offers, $diagnostic, $correlationId, 'Crane NDC');

        return new FlightSearchResultData(
            supplier_provider: SupplierProvider::Airblue,
            offers: $offers,
            warnings: $offers === [] ? ['No fares available for this route/date.'] : [],
            meta: [
                'connection_id' => $connection->id,
                'correlation_id' => $correlationId,
                'api_channel' => AirBlueApiChannel::CraneNdc->value,
            ],
        );
    }

    private function searchOta(FlightSearchRequestData $request, SupplierConnection $connection): FlightSearchResultData
    {
        $config = $this->configResolver->resolveOta($connection);
        $xml = $this->otaXmlBuilder->buildAirLowFareSearchRequest($request, $config);
        $response = $this->client->callOta($connection, 'air_low_fare_search', $xml, ['request_context' => 'search']);
        $diagnostic = is_array($response['_ota_diagnostic'] ?? null) ? $response['_ota_diagnostic'] : [];
        $correlationId = (string) ($diagnostic['correlation_id'] ?? '');
        $offers = $this->otaNormalizer->normalizeSearchResponse($response, $connection, $correlationId);

        $this->logSearchResult($connection, $offers, $diagnostic, $correlationId, 'Zapways OTA');

        return new FlightSearchResultData(
            supplier_provider: SupplierProvider::Airblue,
            offers: $offers,
            warnings: $offers === [] ? ['No fares available for this route/date.'] : [],
            meta: [
                'connection_id' => $connection->id,
                'correlation_id' => $correlationId,
                'api_channel' => AirBlueApiChannel::ZapwaysOta->value,
            ],
        );
    }

    /**
     * @param  list<NormalizedFlightOfferData>  $offers
     * @param  array<string, mixed>  $diagnostic
     */
    private function logSearchResult(
        SupplierConnection $connection,
        array $offers,
        array $diagnostic,
        string $correlationId,
        string $channelLabel,
    ): void {
        $this->diagnosticLogger->log(
            connection: $connection,
            action: 'search',
            status: $offers === [] ? 'warning' : 'success',
            durationMs: isset($diagnostic['duration_ms']) ? (int) $diagnostic['duration_ms'] : null,
            safeMessage: $offers === [] ? 'AirBlue returned no fares for the route/date.' : 'AirBlue search completed ('.$channelLabel.').',
            correlationId: $correlationId !== '' ? $correlationId : null,
            meta: ['offers_count' => count($offers)],
        );
    }
}
