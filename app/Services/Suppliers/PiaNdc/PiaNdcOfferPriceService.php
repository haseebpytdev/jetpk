<?php

namespace App\Services\Suppliers\PiaNdc;

use App\Data\NormalizedFlightOfferData;
use App\Data\OfferValidationResultData;
use App\Models\SupplierConnection;
use App\Services\Suppliers\PiaNdc\Exceptions\PiaNdcException;
use App\Services\Suppliers\PiaNdc\Exceptions\PiaNdcValidationException;
use App\Support\Bookings\PiaNdcFareFamilyPolicy;
use App\Support\Security\SensitiveDataRedactor;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * PIA NDC DoOfferPrice — CLI diagnostic revalidation; public path remains no-op until approved.
 *
 * Hitit OfferPrice currently reaches the endpoint but may return zero or fee-only SF totals.
 * OrderCreate must use AirShopping provider_context and final supplier response as binding
 * validation until Hitit confirms correct OfferPrice structure/permissions.
 */
class PiaNdcOfferPriceService
{
    public function __construct(
        private readonly PiaNdcClient $client,
        private readonly PiaNdcConfigResolver $configResolver,
        private readonly PiaNdcXmlBuilder $xmlBuilder,
        private readonly PiaNdcXmlParser $xmlParser,
        private readonly PiaNdcResponseNormalizer $normalizer,
        private readonly PiaNdcCorrelationContext $correlationContext,
    ) {}

    public function revalidate(NormalizedFlightOfferData $offer, SupplierConnection $connection): OfferValidationResultData
    {
        Log::channel('pia-ndc')->info('pia_ndc.offer_price.skipped', [
            'supplier_connection_id' => $connection->id,
            'offer_id' => $offer->offer_id,
            'reason' => 'Public revalidation deferred; use pia-ndc:offer-price CLI.',
        ]);

        $context = is_array($offer->raw_payload['provider_context'] ?? null)
            ? $offer->raw_payload['provider_context']
            : [];

        return new OfferValidationResultData(
            is_valid: $context !== [],
            status: $context !== [] ? 'validated' : 'invalid_offer',
            original_offer_id: $offer->offer_id,
            validated_offer: $offer,
            warnings: [],
            meta: ['offer_price_supported' => false],
        );
    }

    /**
     * Non-mutating checkout availability probe via DoOfferPrice (R12Q).
     *
     * @param  array<string, mixed>  $providerContext
     * @return array{available: bool, reason_code: string, summary: array<string, mixed>}
     */
    public function validateCheckoutAvailability(
        SupplierConnection $connection,
        array $providerContext,
        ?float $expectedSupplierTotal = null,
        ?string $expectedFareTypeCode = null,
        ?string $expectedOfferItemRefId = null,
    ): array {
        if (! (bool) config('suppliers.pia_ndc.checkout_offer_price_enabled', true)) {
            return [
                'available' => true,
                'reason_code' => 'offer_price_skipped_by_config',
                'summary' => ['offer_price_supported' => false],
            ];
        }

        try {
            $result = $this->executeOfferPriceAttempt(
                connection: $connection,
                providerContext: $providerContext,
                shape: PiaNdcXmlBuilder::OFFER_PRICE_SHAPE_CURRENT,
                probeCorrelationId: null,
                selectedOfferIndex: 0,
                selectedPublicOfferId: null,
                airShoppingSupplierTotal: $expectedSupplierTotal,
                sourceDiagnosticPath: null,
                storageRoot: 'offer-price-checkout',
            );
        } catch (Throwable $exception) {
            Log::channel('pia-ndc')->warning('pia_ndc.offer_price.checkout_failed', [
                'supplier_connection_id' => $connection->id,
                'exception' => $exception::class,
            ]);

            return [
                'available' => false,
                'reason_code' => 'offer_price_exception',
                'summary' => ['exception' => $exception::class],
            ];
        }

        $summary = is_array($result['summary'] ?? null) ? $result['summary'] : [];
        $normalized = is_array($result['normalized'] ?? null) ? $result['normalized'] : [];
        $providerErrorCode = trim((string) ($summary['provider_error_code'] ?? ''));
        $httpStatus = isset($summary['http_status']) ? (int) $summary['http_status'] : null;
        $httpOk = $httpStatus !== null && $httpStatus >= 200 && $httpStatus < 300;

        if (! $httpOk) {
            return [
                'available' => false,
                'reason_code' => 'offer_price_http_error',
                'summary' => $summary,
            ];
        }

        if ($providerErrorCode !== '') {
            return [
                'available' => false,
                'reason_code' => 'offer_price_provider_error',
                'summary' => $summary,
            ];
        }

        $rawPricedOfferPresent = (bool) ($summary['raw_priced_offer_present'] ?? $normalized['raw_priced_offer_present'] ?? false);
        $commerciallyValid = (bool) ($summary['commercially_valid_price'] ?? $normalized['commercially_valid_price'] ?? false);
        if (! $rawPricedOfferPresent && ! $commerciallyValid) {
            return [
                'available' => false,
                'reason_code' => 'offer_price_unavailable',
                'summary' => $summary,
            ];
        }

        $responseCtx = is_array($normalized['provider_context'] ?? null) ? $normalized['provider_context'] : [];
        $responseFareType = trim((string) ($responseCtx['fare_type_code'] ?? ''));
        $expectedFareType = trim((string) ($expectedFareTypeCode ?? $providerContext['fare_type_code'] ?? ''));
        if (
            $responseFareType !== ''
            && $expectedFareType !== ''
            && ! PiaNdcFareFamilyPolicy::labelsMatch($expectedFareType, $responseFareType)
        ) {
            return [
                'available' => false,
                'reason_code' => 'offer_price_fare_type_mismatch',
                'summary' => $summary,
            ];
        }

        $responseItemRef = trim((string) ($responseCtx['offer_item_ref_id'] ?? ''));
        $expectedItemRef = trim((string) ($expectedOfferItemRefId ?? $providerContext['offer_item_ref_id'] ?? ''));
        if ($responseItemRef !== '' && $expectedItemRef !== '' && ! hash_equals($expectedItemRef, $responseItemRef)) {
            return [
                'available' => false,
                'reason_code' => 'offer_price_offer_item_mismatch',
                'summary' => $summary,
            ];
        }

        if ($commerciallyValid && ($summary['fare_changed'] ?? false) === true) {
            return [
                'available' => false,
                'reason_code' => 'offer_price_total_mismatch',
                'summary' => $summary,
            ];
        }

        return [
            'available' => true,
            'reason_code' => 'offer_price_available',
            'summary' => $summary,
        ];
    }

    /**
     * CLI-only OfferPrice diagnostic against Hitit Crane NDC 20.1.
     *
     * @param  array<string, mixed>  $providerContext
     * @return array{
     *     success: bool,
     *     diagnostic_path: string,
     *     summary: array<string, mixed>,
     *     normalized: array<string, mixed>
     * }
     */
    public function runOfferPriceDiagnostic(
        SupplierConnection $connection,
        array $providerContext,
        int $selectedOfferIndex = 0,
        ?string $selectedPublicOfferId = null,
        ?float $airShoppingSupplierTotal = null,
        ?string $sourceDiagnosticPath = null,
        string $shape = PiaNdcXmlBuilder::OFFER_PRICE_SHAPE_CURRENT,
    ): array {
        return $this->executeOfferPriceAttempt(
            connection: $connection,
            providerContext: $providerContext,
            shape: $shape,
            probeCorrelationId: null,
            selectedOfferIndex: $selectedOfferIndex,
            selectedPublicOfferId: $selectedPublicOfferId,
            airShoppingSupplierTotal: $airShoppingSupplierTotal,
            sourceDiagnosticPath: $sourceDiagnosticPath,
            storageRoot: 'offer-price',
        );
    }

    /**
     * Run all OfferPrice request-shape probes for zero-price compatibility testing.
     *
     * @param  array<string, mixed>  $providerContext
     * @return list<array{
     *     success: bool,
     *     diagnostic_path: string,
     *     summary: array<string, mixed>,
     *     normalized: array<string, mixed>
     * }>
     */
    public function runOfferPriceShapeProbe(
        SupplierConnection $connection,
        array $providerContext,
        int $selectedOfferIndex = 0,
        ?string $selectedPublicOfferId = null,
        ?float $airShoppingSupplierTotal = null,
        ?string $sourceDiagnosticPath = null,
        ?string $onlyShape = null,
    ): array {
        $probeCorrelationId = $this->correlationContext->newCorrelationId();
        $shapes = $onlyShape !== null && $onlyShape !== ''
            ? [$onlyShape]
            : PiaNdcXmlBuilder::OFFER_PRICE_PROBE_SHAPES;

        $results = [];
        foreach ($shapes as $shape) {
            if (! in_array($shape, PiaNdcXmlBuilder::OFFER_PRICE_PROBE_SHAPES, true)) {
                throw new PiaNdcValidationException('invalid_offer_price_shape', 422, 'Unknown OfferPrice shape: '.$shape);
            }

            $results[] = $this->executeOfferPriceAttempt(
                connection: $connection,
                providerContext: $providerContext,
                shape: $shape,
                probeCorrelationId: $probeCorrelationId,
                selectedOfferIndex: $selectedOfferIndex,
                selectedPublicOfferId: $selectedPublicOfferId,
                airShoppingSupplierTotal: $airShoppingSupplierTotal,
                sourceDiagnosticPath: $sourceDiagnosticPath,
                storageRoot: 'offer-price-probe',
            );
        }

        return $results;
    }

    /**
     * @param  array<string, mixed>  $providerContext
     * @return array{
     *     success: bool,
     *     diagnostic_path: string,
     *     summary: array<string, mixed>,
     *     normalized: array<string, mixed>
     * }
     */
    private function executeOfferPriceAttempt(
        SupplierConnection $connection,
        array $providerContext,
        string $shape,
        ?string $probeCorrelationId,
        int $selectedOfferIndex,
        ?string $selectedPublicOfferId,
        ?float $airShoppingSupplierTotal,
        ?string $sourceDiagnosticPath,
        string $storageRoot,
    ): array {
        $config = $this->configResolver->resolve($connection);
        $correlationId = $probeCorrelationId ?? $this->correlationContext->newCorrelationId();
        $attemptCorrelationId = $probeCorrelationId !== null
            ? $correlationId
            : $this->correlationContext->newCorrelationId();
        $requestXml = $this->xmlBuilder->buildOfferPriceRequest($config, $providerContext, $shape);
        $sanitizedRequestXml = $this->client->sanitizeXmlForDiagnostics($requestXml);

        $httpStatus = null;
        $responseXml = null;
        $parsedResponse = null;
        $normalized = [];
        $providerErrorCode = null;
        $providerErrorMessage = null;
        $nodeCounts = array_fill_keys(PiaNdcXmlParser::AIR_SHOPPING_DEBUG_NODE_LOCAL_NAMES, 0);

        try {
            $response = $this->client->call($connection, 'offer_price', $requestXml, [
                'request_context' => 'pia-ndc:offer-price',
                'correlation_id' => $attemptCorrelationId,
            ]);
            $diagnostic = is_array($response['_ota_diagnostic'] ?? null) ? $response['_ota_diagnostic'] : [];
            $attemptCorrelationId = (string) ($diagnostic['correlation_id'] ?? $attemptCorrelationId);
            $httpStatus = isset($diagnostic['http_status']) ? (int) $diagnostic['http_status'] : null;
            $parsedResponse = $response;
            $responseXml = is_string($response['raw_xml'] ?? null)
                ? $this->client->sanitizeXmlForDiagnostics($response['raw_xml'])
                : null;
            $normalized = $this->normalizer->normalizeOfferPriceResponse($response, $providerContext, $airShoppingSupplierTotal);
            if ($responseXml !== null) {
                $nodeCounts = $this->xmlParser->countNodesByLocalName($responseXml);
            }
        } catch (PiaNdcException $exception) {
            $safeMeta = $exception->safeDiagnosticMeta('offer_price');
            $attemptCorrelationId = (string) ($safeMeta['correlation_id'] ?? $attemptCorrelationId);
            $httpStatus = isset($safeMeta['http_status']) ? (int) $safeMeta['http_status'] : null;
            $responseXml = is_string($exception->context['response_xml'] ?? null)
                ? $exception->context['response_xml']
                : null;
            if ($responseXml !== null) {
                $nodeCounts = $this->xmlParser->countNodesByLocalName($responseXml);
                try {
                    $parsedResponse = $this->xmlParser->parse($responseXml);
                    $normalized = $this->normalizer->normalizeOfferPriceResponse($parsedResponse, $providerContext, $airShoppingSupplierTotal);
                } catch (Throwable) {
                    $parsedResponse = null;
                    $normalized = [];
                }
            }
            [$providerErrorCode, $providerErrorMessage] = $this->resolveProviderError($parsedResponse, $safeMeta);
        } catch (Throwable $exception) {
            Log::channel('pia-ndc')->warning('pia_ndc.offer_price.unexpected', [
                'supplier_connection_id' => $connection->id,
                'exception' => $exception::class,
                'request_context' => 'pia-ndc:offer-price',
                'shape' => $shape,
            ]);
            $providerErrorCode = 'unexpected_error';
            $providerErrorMessage = 'Offer price diagnostic failed unexpectedly.';
        }

        if ($providerErrorCode === null && $providerErrorMessage === null) {
            [$providerErrorCode, $providerErrorMessage] = $this->resolveProviderError($parsedResponse, []);
        }

        $evaluation = $this->evaluateOfferPriceOutcome($httpStatus, $providerErrorCode, $normalized);
        $fareChanged = $this->compareFareChanged($airShoppingSupplierTotal, $normalized['offer_price_total'] ?? null);

        $summary = [
            'connection_id' => $connection->id,
            'endpoint' => (string) $config['endpoint_url'],
            'correlation_id' => $attemptCorrelationId,
            'probe_correlation_id' => $probeCorrelationId,
            'shape' => $shape,
            'source_air_shopping_diagnostic_path' => $sourceDiagnosticPath,
            'selected_offer_index' => $selectedOfferIndex,
            'selected_public_offer_id' => $selectedPublicOfferId,
            'shopping_response_ref_id' => trim((string) ($providerContext['shopping_response_ref_id'] ?? '')),
            'offer_ref_id_present' => trim((string) ($providerContext['offer_ref_id'] ?? '')) !== '',
            'offer_item_ref_id' => trim((string) ($providerContext['offer_item_ref_id'] ?? '')),
            'pax_ref_id' => trim((string) ($providerContext['pax_ref_id'] ?? '')),
            'http_status' => $httpStatus,
            'provider_error_code' => $providerErrorCode,
            'provider_error_message' => $providerErrorMessage,
            'priced_offer_count' => (int) ($normalized['priced_offer_count'] ?? 0),
            'detected_offer_nodes_count' => $nodeCounts['Offer'] ?? 0,
            'detected_offer_item_nodes_count' => $nodeCounts['OfferItem'] ?? 0,
            'detected_total_amount_count' => ($this->xmlParser->countNodesByLocalName(
                $responseXml ?? '',
                ['TotalAmount'],
            )['TotalAmount'] ?? 0),
            'total_amount' => $normalized['offer_price_total'] ?? null,
            'offer_price_total' => $normalized['offer_price_total'] ?? null,
            'air_shopping_total' => $normalized['air_shopping_total'] ?? $airShoppingSupplierTotal,
            'fee_amount_total' => $normalized['fee_amount_total'] ?? null,
            'fee_descriptions' => $normalized['fee_descriptions'] ?? [],
            'currency' => $normalized['currency'] ?? null,
            'zero_price' => $evaluation['zero_price'],
            'partial_price' => $evaluation['partial_price'],
            'fee_only_price' => $evaluation['fee_only_price'],
            'commercially_valid_price' => $evaluation['commercially_valid_price'],
            'raw_priced_offer_present' => $evaluation['raw_priced_offer_present'],
            'fare_comparison_available' => $normalized['fare_comparison_available'] ?? false,
            'fare_difference' => $normalized['fare_difference'] ?? null,
            'fare_difference_percent' => $normalized['fare_difference_percent'] ?? null,
            'fare_changed' => $fareChanged,
            'success' => $evaluation['success'],
        ];

        $diagnosticPath = $this->saveOfferPriceDiagnosticFiles(
            storageRoot: $storageRoot,
            connectionId: (int) $connection->id,
            correlationId: $probeCorrelationId ?? $attemptCorrelationId,
            shape: $probeCorrelationId !== null ? $shape : null,
            requestXml: $sanitizedRequestXml,
            responseXml: $responseXml,
            normalized: $normalized,
            summary: $summary,
            parsedResponse: $parsedResponse,
        );

        $summary['diagnostic_path'] = $diagnosticPath;

        return [
            'success' => $evaluation['success'],
            'diagnostic_path' => $diagnosticPath,
            'summary' => $summary,
            'normalized' => $normalized,
        ];
    }

    /**
     * @param  array<string, mixed>  $normalized
     * @return array{
     *     success: bool,
     *     zero_price: bool,
     *     partial_price: bool,
     *     fee_only_price: bool,
     *     commercially_valid_price: bool,
     *     raw_priced_offer_present: bool
     * }
     */
    private function evaluateOfferPriceOutcome(?int $httpStatus, ?string $providerErrorCode, array $normalized): array
    {
        $commerciallyValidPrice = (bool) ($normalized['commercially_valid_price'] ?? false);
        $httpOk = $httpStatus !== null && $httpStatus >= 200 && $httpStatus < 300;
        $success = $httpOk && $providerErrorCode === null && $commerciallyValidPrice;

        return [
            'success' => $success,
            'zero_price' => (bool) ($normalized['zero_price'] ?? false),
            'partial_price' => (bool) ($normalized['partial_price'] ?? false),
            'fee_only_price' => (bool) ($normalized['fee_only_price'] ?? false),
            'commercially_valid_price' => $commerciallyValidPrice,
            'raw_priced_offer_present' => (bool) ($normalized['raw_priced_offer_present'] ?? false),
        ];
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

    private function compareFareChanged(?float $airShoppingTotal, mixed $offerPriceTotal): ?bool
    {
        if ($airShoppingTotal === null || ! is_numeric($offerPriceTotal)) {
            return null;
        }

        return abs($airShoppingTotal - (float) $offerPriceTotal) > 0.009;
    }

    /**
     * @param  array<string, mixed>  $normalized
     * @param  array<string, mixed>  $summary
     * @param  ?array<string, mixed>  $parsedResponse
     */
    private function saveOfferPriceDiagnosticFiles(
        string $storageRoot,
        int $connectionId,
        string $correlationId,
        ?string $shape,
        string $requestXml,
        ?string $responseXml,
        array $normalized,
        array $summary,
        ?array $parsedResponse,
    ): string {
        $pathSuffix = $correlationId !== '' ? $correlationId : 'unknown';
        if ($shape !== null && $shape !== '') {
            $pathSuffix .= '/'.$shape;
        }

        $directory = storage_path('app/diagnostics/pia-ndc/'.$storageRoot.'/'.$connectionId.'/'.$pathSuffix);
        File::ensureDirectoryExists($directory);

        file_put_contents($directory.'/request.xml', $requestXml);
        file_put_contents($directory.'/response.xml', $responseXml ?? '');

        $normalizedPayload = SensitiveDataRedactor::redact(array_merge($normalized, [
            'provider_errors' => is_array($parsedResponse['errors'] ?? null) ? $parsedResponse['errors'] : [],
            'provider_warnings' => $normalized['provider_warnings'] ?? (
                is_array($parsedResponse['warnings'] ?? null) ? $parsedResponse['warnings'] : []
            ),
        ]));
        file_put_contents(
            $directory.'/normalized.json',
            json_encode($normalizedPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        );
        file_put_contents(
            $directory.'/summary.json',
            json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        );

        return $directory;
    }
}
