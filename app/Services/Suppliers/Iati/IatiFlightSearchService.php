<?php

namespace App\Services\Suppliers\Iati;

use App\Data\FlightSearchRequestData;
use App\Data\FlightSearchResultData;
use App\Enums\SupplierProvider;
use App\Models\SupplierConnection;
use App\Services\Suppliers\Iati\Exceptions\IatiException;
use App\Services\Suppliers\SupplierDiagnosticLogger;
use App\Support\Security\SensitiveDataRedactor;
use Illuminate\Support\Facades\Log;
use Throwable;

class IatiFlightSearchService
{
    public function __construct(
        private readonly IatiClient $client,
        private readonly IatiPayloadBuilder $payloadBuilder,
        private readonly IatiResponseNormalizer $normalizer,
        private readonly SupplierDiagnosticLogger $diagnosticLogger,
    ) {}

    public function search(FlightSearchRequestData $request, SupplierConnection $connection): FlightSearchResultData
    {
        try {
            $payload = $this->payloadBuilder->buildSearchPayload($request);
            $response = $this->client->post($connection, '/search', $payload, [
                'request_context' => 'search',
            ]);
            $diagnostic = is_array($response['_ota_diagnostic'] ?? null) ? $response['_ota_diagnostic'] : [];
            $correlationId = (string) ($diagnostic['correlation_id'] ?? '');
            $offers = $this->normalizer->normalizeSearchResponse(
                $response,
                $connection,
                $correlationId,
                $request->adults,
                $request->children,
                $request->infants,
            );

            $this->diagnosticLogger->log(
                connection: $connection,
                action: 'search',
                status: $offers === [] ? 'warning' : 'success',
                durationMs: isset($diagnostic['duration_ms']) ? (int) $diagnostic['duration_ms'] : null,
                safeMessage: $offers === [] ? 'IATI returned no fares for the route/date.' : 'IATI search completed.',
                correlationId: $correlationId !== '' ? $correlationId : null,
                meta: ['offers_count' => count($offers)],
            );

            return new FlightSearchResultData(
                supplier_provider: SupplierProvider::Iati,
                offers: $offers,
                warnings: $offers === [] ? ['IATI returned no fares for this route/date.'] : [],
                meta: ['connection_id' => $connection->id, 'correlation_id' => $correlationId],
            );
        } catch (IatiException $exception) {
            Log::channel('iati')->warning('iati.search.fallback', [
                'supplier_connection_id' => $connection->id,
                'fallback_reason' => 'iati_exception',
                'exception_class' => $exception::class,
                'exception_message' => $exception->getMessage(),
                'error_code' => $exception->normalizedCode,
                'http_status' => $exception->httpStatus,
                'context' => SensitiveDataRedactor::redact($exception->context),
            ]);

            $this->diagnosticLogger->log(
                connection: $connection,
                action: 'search',
                status: 'failed',
                safeMessage: $exception->safeMessage,
                meta: ['error_code' => $exception->normalizedCode],
            );

            return new FlightSearchResultData(
                supplier_provider: SupplierProvider::Iati,
                offers: [],
                warnings: ['Provider search is temporarily unavailable.'],
                meta: [
                    'connection_id' => $connection->id,
                    'error_code' => $exception->normalizedCode,
                    'audit' => [
                        'fallback_reason' => 'iati_exception',
                        'exception_class' => $exception::class,
                        'exception_message' => $exception->getMessage(),
                        'http_status' => $exception->httpStatus,
                    ],
                ],
            );
        } catch (Throwable $exception) {
            Log::channel('iati')->warning('iati.search.fallback', [
                'supplier_connection_id' => $connection->id,
                'fallback_reason' => 'unexpected_exception',
                'exception_class' => $exception::class,
                'exception_message' => $exception->getMessage(),
            ]);

            Log::channel('iati')->warning('iati.search.unexpected', [
                'supplier_connection_id' => $connection->id,
                'exception' => $exception::class,
            ]);

            return new FlightSearchResultData(
                supplier_provider: SupplierProvider::Iati,
                offers: [],
                warnings: ['Provider search is temporarily unavailable.'],
                meta: [
                    'connection_id' => $connection->id,
                    'audit' => [
                        'fallback_reason' => 'unexpected_exception',
                        'exception_class' => $exception::class,
                        'exception_message' => $exception->getMessage(),
                    ],
                ],
            );
        }
    }
}
