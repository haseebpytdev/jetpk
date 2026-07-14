<?php

namespace App\Services\Suppliers\PiaNdc;

use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Models\SupplierConnection;
use App\Services\Suppliers\PiaNdc\Exceptions\PiaNdcException;
use App\Services\Suppliers\PiaNdc\Exceptions\PiaNdcValidationException;
use App\Support\Bookings\PiaNdcPnrItinerarySyncMapper;
use App\Support\Security\SensitiveDataRedactor;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * PIA NDC DoOrderRetrieve — sync and CLI diagnostics for live option PNR validation.
 */
class PiaNdcRetrieveService
{
    public function __construct(
        private readonly PiaNdcClient $client,
        private readonly PiaNdcConfigResolver $configResolver,
        private readonly PiaNdcXmlBuilder $xmlBuilder,
        private readonly PiaNdcXmlParser $xmlParser,
        private readonly PiaNdcResponseNormalizer $normalizer,
        private readonly PiaNdcCorrelationContext $correlationContext,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function retrieveAndSync(Booking $booking, SupplierConnection $connection): array
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $context = is_array($meta['pia_ndc_context'] ?? null) ? $meta['pia_ndc_context'] : [];
        $orderId = trim((string) ($context['order_id'] ?? $booking->supplier_reference ?? ''));
        $ownerCode = trim((string) ($context['owner_code'] ?? ''));
        if ($orderId === '' || $ownerCode === '') {
            return ['synced' => false, 'reason' => 'missing_order_context'];
        }

        try {
            $config = $this->configResolver->resolve($connection);
            $xml = $this->xmlBuilder->buildOrderRetrieveRequest($config, $orderId, $ownerCode);
            $response = $this->client->call($connection, 'order_retrieve', $xml, [
                'booking_id' => $booking->id,
                'request_context' => 'retrieve',
            ]);
            $normalized = $this->normalizer->normalizeOrderRetrieveDiagnosticResponse($response, $context);
            $this->mergeSync($booking, $normalized);

            return ['synced' => true, 'data' => $normalized];
        } catch (PiaNdcException $exception) {
            Log::channel('pia-ndc')->warning('pia_ndc.retrieve.failed', [
                'booking_id' => $booking->id,
                'error_code' => $exception->normalizedCode,
            ]);

            return ['synced' => false, 'reason' => $exception->normalizedCode];
        }
    }

    /**
     * CLI-only OrderRetrieve diagnostic (always calls supplier).
     *
     * @return array{success: bool, diagnostic_path: string, summary: array<string, mixed>}
     */
    public function runOrderRetrieveDiagnostic(
        SupplierConnection $connection,
        string $orderId,
        string $ownerCode,
    ): array {
        if ($connection->provider !== SupplierProvider::PiaNdc) {
            throw new PiaNdcValidationException('supplier_provider_mismatch', 422, 'Supplier connection is not PIA NDC.');
        }

        $orderId = trim($orderId);
        $ownerCode = trim($ownerCode);
        if ($orderId === '') {
            throw new PiaNdcValidationException('missing_order_reference', 422, 'Order ID / PNR is required.');
        }
        if ($ownerCode === '') {
            throw new PiaNdcValidationException('missing_owner_code', 422, 'Owner code is required.');
        }

        $config = $this->configResolver->resolve($connection);
        $correlationId = $this->correlationContext->newCorrelationId();
        $requestXml = $this->xmlBuilder->buildOrderRetrieveRequest($config, $orderId, $ownerCode);
        $sanitizedRequestXml = $this->client->sanitizeXmlForDiagnostics($requestXml);

        $httpStatus = null;
        $responseXml = null;
        $normalizedResponse = null;
        $providerErrorCode = null;
        $providerErrorMessage = null;

        try {
            $parsedResponse = $this->client->call($connection, 'order_retrieve', $requestXml, [
                'request_context' => 'pia-ndc:order-retrieve-diagnostic',
                'correlation_id' => $correlationId,
            ]);
            $diagnostic = is_array($parsedResponse['_ota_diagnostic'] ?? null) ? $parsedResponse['_ota_diagnostic'] : [];
            $correlationId = (string) ($diagnostic['correlation_id'] ?? $correlationId);
            $httpStatus = isset($diagnostic['http_status']) ? (int) $diagnostic['http_status'] : null;
            $responseXml = is_string($parsedResponse['raw_xml'] ?? null)
                ? $this->client->sanitizeXmlForDiagnostics($parsedResponse['raw_xml'])
                : null;
            $normalizedResponse = $this->normalizer->normalizeOrderRetrieveDiagnosticResponse($parsedResponse, [
                'order_id' => $orderId,
                'owner_code' => $ownerCode,
            ]);
            if (($parsedResponse['errors'][0]['code'] ?? '') !== '') {
                $providerErrorCode = (string) $parsedResponse['errors'][0]['code'];
                $providerErrorMessage = (string) ($parsedResponse['errors'][0]['message'] ?? '');
            }
        } catch (PiaNdcException $exception) {
            $safeMeta = $exception->safeDiagnosticMeta('order_retrieve');
            $correlationId = (string) ($safeMeta['correlation_id'] ?? $correlationId);
            $httpStatus = isset($safeMeta['http_status']) ? (int) $safeMeta['http_status'] : null;
            $providerErrorCode = $exception->normalizedCode;
            $providerErrorMessage = $exception->safeMessage;
            $responseXml = is_string($exception->context['response_xml'] ?? null)
                ? $exception->context['response_xml']
                : null;
            if ($responseXml !== null) {
                try {
                    $parsedResponse = $this->xmlParser->parse($responseXml);
                    $normalizedResponse = $this->normalizer->normalizeOrderRetrieveDiagnosticResponse($parsedResponse, [
                        'order_id' => $orderId,
                        'owner_code' => $ownerCode,
                    ]);
                } catch (Throwable) {
                    $normalizedResponse = null;
                }
            }
        }

        $resolvedOrderId = trim((string) ($normalizedResponse['order_id'] ?? $orderId));
        $httpOk = $httpStatus !== null && $httpStatus >= 200 && $httpStatus < 300;
        $success = $httpOk && $providerErrorCode === null && $resolvedOrderId !== '';

        $summary = [
            'connection_id' => $connection->id,
            'endpoint' => (string) $config['endpoint_url'],
            'correlation_id' => $correlationId,
            'order_id' => $resolvedOrderId,
            'pnr' => $normalizedResponse['pnr'] ?? $resolvedOrderId,
            'booking_reference' => $normalizedResponse['booking_reference'] ?? null,
            'airline_locator' => $normalizedResponse['airline_locator'] ?? null,
            'order_status' => $normalizedResponse['order_status'] ?? null,
            'segment_statuses' => $normalizedResponse['segment_statuses'] ?? [],
            'service_statuses' => $normalizedResponse['service_statuses'] ?? [],
            'payment_time_limit' => $normalizedResponse['payment_time_limit'] ?? null,
            'segment_count' => $normalizedResponse['segment_count'] ?? 0,
            'passenger_count' => $normalizedResponse['passenger_count'] ?? 0,
            'ticket_numbers' => $normalizedResponse['ticket_numbers'] ?? [],
            'has_blocking_ticket_numbers' => (bool) ($normalizedResponse['has_blocking_ticket_numbers'] ?? false),
            'total_amount' => $normalizedResponse['total_amount'] ?? null,
            'currency' => $normalizedResponse['currency'] ?? null,
            'owner_code' => $ownerCode,
            'http_status' => $httpStatus,
            'provider_error_code' => $providerErrorCode,
            'provider_error_message' => $providerErrorMessage,
            'success' => $success,
        ];

        $diagnosticPath = $this->saveRetrieveDiagnosticFiles(
            connectionId: $connection->id,
            correlationId: $correlationId,
            requestXml: $sanitizedRequestXml,
            responseXml: $responseXml,
            normalizedResponse: $normalizedResponse,
            summary: $summary,
        );
        $summary['diagnostic_path'] = $diagnosticPath;

        return [
            'success' => $success,
            'diagnostic_path' => $diagnosticPath,
            'summary' => $summary,
        ];
    }

    /**
     * @param  ?array<string, mixed>  $normalizedResponse
     * @param  array<string, mixed>  $summary
     */
    private function saveRetrieveDiagnosticFiles(
        int $connectionId,
        string $correlationId,
        string $requestXml,
        ?string $responseXml,
        ?array $normalizedResponse,
        array $summary,
    ): string {
        $directory = storage_path('app/diagnostics/pia-ndc/order-retrieve/'.$connectionId.'/'.$correlationId);
        File::ensureDirectoryExists($directory);

        file_put_contents($directory.'/request.xml', $requestXml);
        file_put_contents(
            $directory.'/summary.json',
            json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        );
        if ($responseXml !== null) {
            file_put_contents($directory.'/response.xml', $responseXml);
        }
        if ($normalizedResponse !== null) {
            file_put_contents(
                $directory.'/normalized_response.json',
                json_encode(SensitiveDataRedactor::redact($normalizedResponse), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            );
        }

        return $directory;
    }

    /**
     * @param  array<string, mixed>  $normalized
     */
    private function mergeSync(Booking $booking, array $normalized): void
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $existing = is_array($meta['pia_ndc_context'] ?? null) ? $meta['pia_ndc_context'] : [];
        $locallyVoided = ($existing['void_status'] ?? '') === 'voided'
            || in_array(strtolower(trim((string) ($booking->ticketing_status ?? ''))), ['voided', 'ticket_voided'], true);
        if ($locallyVoided) {
            $normalized['has_blocking_ticket_numbers'] = false;
            $normalized['ticketing_status'] = 'voided';
        }
        foreach ($normalized as $key => $value) {
            if (is_bool($value)) {
                $existing[$key] = $value;

                continue;
            }
            if ($value === null || $value === '' || $value === []) {
                continue;
            }
            $existing[$key] = $value;
        }
        if ($locallyVoided) {
            $existing['has_blocking_ticket_numbers'] = false;
            $existing['ticketing_status'] = 'voided';
            $existing['void_status'] = 'voided';
            $existing['option_pnr_released'] = false;
            unset($existing['option_pnr_released_at'], $existing['cancel_committed'], $existing['cancellation_status']);
        }
        $existing['last_sync_at'] = now()->toIso8601String();
        $meta['pia_ndc_context'] = $existing;
        $meta = PiaNdcPnrItinerarySyncMapper::applyRetrieveToBookingMeta($meta, $normalized);
        $booking->meta = $meta;
        if (($normalized['order_id'] ?? '') !== '') {
            $booking->supplier_reference = (string) $normalized['order_id'];
        }
        $booking->save();
    }
}
