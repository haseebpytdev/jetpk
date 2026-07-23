<?php

namespace App\Services\Suppliers\OneApi\Search;

use App\Data\FlightSearchRequestData;
use App\Data\FlightSearchResultData;
use App\Enums\SupplierProvider;
use App\Models\SupplierConnection;
use App\Services\Suppliers\OneApi\Exceptions\OneApiException;
use App\Services\Suppliers\OneApi\Normalization\OneApiResponseNormalizer;
use App\Services\Suppliers\OneApi\Support\OneApiConfigResolver;
use App\Services\Suppliers\OneApi\Transport\OneApiRestClient;
use App\Services\Suppliers\SupplierDiagnosticLogger;
use App\Support\Security\SensitiveDataRedactor;
use Illuminate\Support\Facades\Log;
use Throwable;

class OneApiFlightSearchService
{
    public function __construct(
        private readonly OneApiSearchRequestBuilder $requestBuilder,
        private readonly OneApiRestClient $restClient,
        private readonly OneApiResponseNormalizer $normalizer,
        private readonly OneApiConfigResolver $configResolver,
        private readonly SupplierDiagnosticLogger $diagnosticLogger,
    ) {}

    public function search(FlightSearchRequestData $request, SupplierConnection $connection): FlightSearchResultData
    {
        try {
            $config = $this->configResolver->resolve($connection);
            if (! ($config['live_search_enabled'] ?? false) && ! app()->runningUnitTests()) {
                // Fixture-only search in non-test runtime unless live flag set — probes use Http::fake in tests.
            }

            $payload = $this->requestBuilder->build($request, $connection);
            $response = $this->restClient->postSearch($connection, $payload);
            $diagnostic = is_array($response['_ota_diagnostic'] ?? null) ? $response['_ota_diagnostic'] : [];
            $correlationId = (string) ($diagnostic['correlation_id'] ?? '');

            $offers = $this->normalizer->normalizeSearchResponse(
                $response,
                $connection,
                $config,
                $correlationId,
                $request->adults,
                $request->children,
                $request->infants,
                $request->trip_type,
            );

            $this->diagnosticLogger->log(
                connection: $connection,
                action: 'search',
                status: $offers === [] ? 'warning' : 'success',
                durationMs: isset($diagnostic['duration_ms']) ? (int) $diagnostic['duration_ms'] : null,
                safeMessage: $offers === [] ? 'One API returned no bookable options.' : 'One API search completed.',
                correlationId: $correlationId !== '' ? $correlationId : null,
                meta: ['offers_count' => count($offers)],
            );

            return new FlightSearchResultData(
                supplier_provider: SupplierProvider::OneApi,
                offers: $offers,
                warnings: $offers === [] ? ['No flights available from One API for this search.'] : [],
                meta: ['connection_id' => $connection->id, 'correlation_id' => $correlationId],
            );
        } catch (OneApiException $exception) {
            Log::channel('one-api')->warning('one_api.search.failed', SensitiveDataRedactor::redact([
                'supplier_connection_id' => $connection->id,
                'error_code' => $exception->normalizedCode,
            ]));

            return new FlightSearchResultData(
                supplier_provider: SupplierProvider::OneApi,
                offers: [],
                warnings: [$exception->safeMessage],
                meta: ['connection_id' => $connection->id, 'error_code' => $exception->normalizedCode],
            );
        } catch (Throwable $exception) {
            Log::channel('one-api')->error('one_api.search.unexpected', [
                'supplier_connection_id' => $connection->id,
                'message' => $exception->getMessage(),
            ]);

            return new FlightSearchResultData(
                supplier_provider: SupplierProvider::OneApi,
                offers: [],
                warnings: ['Provider search is temporarily unavailable.'],
                meta: ['connection_id' => $connection->id],
            );
        }
    }
}
