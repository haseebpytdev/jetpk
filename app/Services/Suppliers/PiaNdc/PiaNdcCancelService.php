<?php

namespace App\Services\Suppliers\PiaNdc;

use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Models\SupplierConnection;
use App\Models\User;
use App\Services\Suppliers\PiaNdc\Exceptions\PiaNdcCancellationException;
use App\Services\Suppliers\PiaNdc\Exceptions\PiaNdcException;
use App\Services\Suppliers\PiaNdc\Exceptions\PiaNdcValidationException;
use App\Support\Security\SensitiveDataRedactor;
use Illuminate\Support\Facades\File;
use Throwable;

/**
 * PIA NDC cancel — preview/commit and CLI cancel diagnostic for unticketed option PNRs only.
 */
class PiaNdcCancelService
{
    private const PREVIEW_CONFIRM_PHRASE = 'PREVIEW_OPTION_PNR';

    private const COMMIT_CONFIRM_PHRASE = 'CANCEL_OPTION_PNR';

    public function __construct(
        private readonly PiaNdcClient $client,
        private readonly PiaNdcConfigResolver $configResolver,
        private readonly PiaNdcXmlBuilder $xmlBuilder,
        private readonly PiaNdcXmlParser $xmlParser,
        private readonly PiaNdcResponseNormalizer $normalizer,
        private readonly PiaNdcCorrelationContext $correlationContext,
        private readonly PiaNdcDiagnosticService $diagnosticService,
        private readonly PiaNdcRetrieveService $retrieveService,
        private readonly PiaNdcCancelExecutionLock $executionLock,
    ) {}

    /**
     * @return list<string>
     */
    public function clearStaleCancelLocks(?int $connectionId = null): array
    {
        return $this->executionLock->clearStaleLocks($connectionId);
    }

    /**
     * @return array<string, mixed>
     */
    public function cancelForBooking(Booking $booking, SupplierConnection $connection, User $actor): array
    {
        unset($actor);
        $preview = $this->preview($booking, $connection);

        return array_merge($preview, $this->commit($booking, $connection), [
            'success' => true,
            'status' => 'cancelled',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function preview(Booking $booking, SupplierConnection $connection): array
    {
        $orderId = $this->orderId($booking);
        $config = $this->configResolver->resolve($connection);
        $xml = $this->xmlBuilder->buildCancelPreviewRequest($config, $orderId);

        try {
            $response = $this->client->call($connection, 'cancel_preview', $xml, [
                'booking_id' => $booking->id,
                'request_context' => 'cancel_preview',
            ]);
            $preview = $this->normalizer->normalizeCancelPreviewResponse($response);
            $this->persistPreview($booking, $preview);

            return $preview;
        } catch (PiaNdcException $exception) {
            throw new PiaNdcCancellationException(
                $exception->normalizedCode,
                $exception->httpStatus,
                'Cancellation preview failed.',
                $exception->context,
                $exception,
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function commit(Booking $booking, SupplierConnection $connection): array
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $context = is_array($meta['pia_ndc_context'] ?? null) ? $meta['pia_ndc_context'] : [];
        if (($context['cancel_committed'] ?? false) === true) {
            throw new PiaNdcCancellationException('duplicate_cancellation_guard', 409, 'Cancellation already committed.');
        }

        $orderId = $this->orderId($booking);
        $ownerCode = trim((string) ($context['owner_code'] ?? ''));
        $config = $this->configResolver->resolve($connection);
        $xml = $this->xmlBuilder->buildCancelCommitRequest($config, $orderId, $ownerCode);

        try {
            $response = $this->client->call($connection, 'cancel_commit', $xml, [
                'booking_id' => $booking->id,
                'request_context' => 'cancel_commit',
            ]);
            $result = $this->normalizer->normalizeCancelCommitResponse($response);
            if (($result['cancellation_status'] ?? '') !== 'cancelled') {
                throw new PiaNdcCancellationException(
                    (string) ($result['provider_error_code'] ?? 'cancel_not_confirmed'),
                    422,
                    'Cancellation failed, admin review required.',
                    $result,
                );
            }
            $context['cancel_committed'] = true;
            $context['cancellation_status'] = $result['cancellation_status'] ?? 'cancelled';
            $meta['pia_ndc_context'] = $context;
            $booking->meta = $meta;
            $booking->save();

            return $result;
        } catch (PiaNdcException $exception) {
            throw new PiaNdcCancellationException(
                $exception->normalizedCode,
                $exception->httpStatus,
                'Cancellation failed, admin review required.',
                $exception->context,
                $exception,
            );
        }
    }

    /**
     * @param  array<string, mixed>  $preview
     */
    private function persistPreview(Booking $booking, array $preview): void
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $context = is_array($meta['pia_ndc_context'] ?? null) ? $meta['pia_ndc_context'] : [];
        $context['cancel_preview'] = $preview;
        $meta['pia_ndc_context'] = $context;
        $booking->meta = $meta;
        $booking->save();
    }

    private function orderId(Booking $booking): string
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $context = is_array($meta['pia_ndc_context'] ?? null) ? $meta['pia_ndc_context'] : [];
        $orderId = trim((string) ($context['order_id'] ?? $booking->supplier_reference ?? ''));
        if ($orderId === '') {
            throw new PiaNdcCancellationException('missing_order_context', 422, 'Cancellation preview failed.');
        }

        return $orderId;
    }

    /**
     * CLI-only OrderCancel diagnostic (dry-run by default; unticketed option PNR only).
     *
     * @return array{success: bool, diagnostic_path: string, summary: array<string, mixed>, probe_results?: list<array<string, mixed>>}
     */
    public function runOrderCancelDiagnostic(
        SupplierConnection $connection,
        string $orderId,
        string $ownerCode,
        bool $executeCancel = false,
        ?string $confirmPhrase = null,
        bool $probeShapes = false,
        ?string $shape = null,
        ?string $operationOverride = null,
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

        if ($probeShapes && $executeCancel) {
            throw new PiaNdcValidationException(
                'probe_shapes_execute_conflict',
                422,
                '--probe-shapes is dry-run only and cannot be combined with --execute-cancel.',
            );
        }

        if ($probeShapes) {
            return $this->runCancelShapeProbe($connection, $orderId, $ownerCode);
        }

        $resolvedShape = trim((string) $shape) !== ''
            ? trim((string) $shape)
            : PiaNdcXmlBuilder::CANCEL_SHAPE_CURRENT;

        $operationKey = $this->resolveOperationKey($resolvedShape, $operationOverride);

        if ($executeCancel) {
            if (trim((string) $shape) === '') {
                throw new PiaNdcValidationException(
                    'missing_cancel_shape',
                    422,
                    'Execute requires --shape=<shape_name>.',
                );
            }
            $this->assertCancelExecuteAllowed($resolvedShape, $operationKey, $confirmPhrase);
            $this->assertCancelExecuteGuards($connection, $orderId, $ownerCode, $confirmPhrase, $resolvedShape, $operationKey);
        }
        $config = $this->configResolver->resolve($connection);
        $correlationId = $this->correlationContext->newCorrelationId();
        $requestXml = $this->xmlBuilder->buildCancelDiagnosticRequest($config, $orderId, $ownerCode, $resolvedShape);
        $sanitizedRequestXml = $this->client->sanitizeXmlForDiagnostics($requestXml);

        $supplierCalled = false;
        $httpStatus = null;
        $responseXml = null;
        $normalizedResponse = null;
        $providerErrorCode = null;
        $providerErrorMessage = null;
        $soapFaultCode = null;
        $soapFaultString = null;
        $preflightRetrieve = null;
        $lockPath = null;

        if ($executeCancel) {
            $preflightRetrieve = $this->retrieveService->runOrderRetrieveDiagnostic($connection, $orderId, $ownerCode);
            $preflightSummary = is_array($preflightRetrieve['summary'] ?? null) ? $preflightRetrieve['summary'] : [];
            if (($preflightSummary['success'] ?? false) !== true) {
                throw new PiaNdcValidationException(
                    'preflight_retrieve_failed',
                    422,
                    'Cancel refused: preflight retrieve did not succeed.',
                );
            }
            if (($preflightSummary['has_blocking_ticket_numbers'] ?? false) === true) {
                throw new PiaNdcValidationException(
                    'ticketed_order_cancel_blocked',
                    422,
                    'Cancel refused: order has issued ticket number(s). R11 supports unticketed option PNR only.',
                );
            }
            if ($this->isInactiveOrderStatus($preflightSummary['order_status'] ?? null)) {
                throw new PiaNdcValidationException(
                    'order_already_inactive',
                    422,
                    'Cancel refused: order status indicates already cancelled or closed.',
                );
            }

            $lockKind = $this->executionLock->lockKindForOperation($operationKey);
            $lockPath = $this->executionLock->acquire($lockKind, $connection->id, $orderId, $ownerCode);

            try {
                $supplierCalled = true;
                $parsedResponse = $this->client->call($connection, $operationKey, $requestXml, [
                    'request_context' => 'pia-ndc:order-cancel-diagnostic',
                    'correlation_id' => $correlationId,
                    'soap_action_override' => $this->xmlBuilder->soapActionForCancelOperation($operationKey),
                ]);
                $diagnostic = is_array($parsedResponse['_ota_diagnostic'] ?? null) ? $parsedResponse['_ota_diagnostic'] : [];
                $correlationId = (string) ($diagnostic['correlation_id'] ?? $correlationId);
                $httpStatus = isset($diagnostic['http_status']) ? (int) $diagnostic['http_status'] : null;
                $responseXml = is_string($parsedResponse['raw_xml'] ?? null)
                    ? $this->client->sanitizeXmlForDiagnostics($parsedResponse['raw_xml'])
                    : null;
                $normalizedResponse = $operationKey === 'cancel_preview'
                    ? $this->normalizer->normalizeCancelPreviewDiagnosticResponse(
                        parsedResponse: $parsedResponse,
                        httpStatus: $httpStatus,
                        providerErrorCode: null,
                        providerErrorMessage: null,
                    )
                    : $this->normalizer->normalizeCancelDiagnosticResponse(
                        parsedResponse: $parsedResponse,
                        httpStatus: $httpStatus,
                        providerErrorCode: null,
                        providerErrorMessage: null,
                    );
            } catch (PiaNdcException $exception) {
                $safeMeta = $exception->safeDiagnosticMeta($operationKey);
                $correlationId = (string) ($safeMeta['correlation_id'] ?? $correlationId);
                $httpStatus = isset($safeMeta['http_status']) ? (int) $safeMeta['http_status'] : null;
                $providerErrorCode = $exception->normalizedCode;
                $providerErrorMessage = $exception->safeMessage;
                $responseXml = is_string($exception->context['response_xml'] ?? null)
                    ? $exception->context['response_xml']
                    : null;
                $parsedResponse = null;
                if ($responseXml !== null) {
                    try {
                        $parsedResponse = $this->xmlParser->parse($responseXml);
                    } catch (Throwable) {
                        $parsedResponse = null;
                    }
                }
                if ($providerErrorCode === 'soap_fault') {
                    $providerErrorMessage = (string) ($exception->context['fault_message'] ?? $providerErrorMessage);
                    $soapFaultCode = (string) ($exception->context['fault_code'] ?? '');
                    $soapFaultString = (string) ($exception->context['fault_message'] ?? '');
                }
                $normalizedResponse = $operationKey === 'cancel_preview'
                    ? $this->normalizer->normalizeCancelPreviewDiagnosticResponse(
                        parsedResponse: $parsedResponse,
                        httpStatus: $httpStatus,
                        providerErrorCode: $providerErrorCode,
                        providerErrorMessage: $providerErrorMessage,
                        soapFault: is_array($parsedResponse) && is_array($parsedResponse['soap_fault'] ?? null)
                            ? $parsedResponse['soap_fault']
                            : (
                                ($soapFaultCode !== '' || $soapFaultString !== '')
                                    ? ['code' => $soapFaultCode, 'message' => $soapFaultString]
                                    : null
                            ),
                    )
                    : $this->normalizer->normalizeCancelDiagnosticResponse(
                        parsedResponse: $parsedResponse,
                        httpStatus: $httpStatus,
                        providerErrorCode: $providerErrorCode,
                        providerErrorMessage: $providerErrorMessage,
                        soapFault: is_array($parsedResponse) && is_array($parsedResponse['soap_fault'] ?? null)
                            ? $parsedResponse['soap_fault']
                            : (
                                ($soapFaultCode !== '' || $soapFaultString !== '')
                                    ? ['code' => $soapFaultCode, 'message' => $soapFaultString]
                                    : null
                            ),
                    );
            }
        }

        if ($normalizedResponse !== null) {
            $soapFaultCode = $soapFaultCode ?? ($normalizedResponse['soap_fault_code'] ?? null);
            $soapFaultString = $soapFaultString ?? ($normalizedResponse['soap_fault_string'] ?? null);
            if (($providerErrorMessage === null || $providerErrorMessage === '') && is_string($soapFaultString)) {
                $providerErrorMessage = $soapFaultString;
            }
        }

        $success = $this->evaluateCancelDiagnosticSuccess(
            executeCancel: $executeCancel,
            supplierCalled: $supplierCalled,
            httpStatus: $httpStatus,
            providerErrorCode: $providerErrorCode,
            normalizedResponse: $normalizedResponse,
            operationKey: $operationKey,
        );

        $summary = [
            'connection_id' => $connection->id,
            'endpoint' => (string) $config['endpoint_url'],
            'correlation_id' => $correlationId,
            'order_id' => $orderId,
            'owner_code' => $ownerCode,
            'shape' => $resolvedShape,
            'operation' => $operationKey,
            'soap_action' => $this->xmlBuilder->soapActionForCancelOperation($operationKey),
            'dry_run' => ! $executeCancel,
            'supplier_called' => $supplierCalled,
            'execute_cancel' => $executeCancel,
            'preflight_retrieve_diagnostic_path' => is_array($preflightRetrieve)
                ? ($preflightRetrieve['diagnostic_path'] ?? null)
                : null,
            'has_blocking_ticket_numbers' => is_array($preflightRetrieve)
                ? (bool) (($preflightRetrieve['summary']['has_blocking_ticket_numbers'] ?? false))
                : null,
            'execution_lock_path' => $lockPath,
            'http_status' => $httpStatus,
            'provider_error_code' => $providerErrorCode,
            'provider_error_message' => $providerErrorMessage,
            'soap_fault_code' => $soapFaultCode,
            'soap_fault_string' => $soapFaultString,
            'order_status' => $normalizedResponse['order_status'] ?? null,
            'success' => $success,
        ];

        if ($operationKey === 'cancel_preview') {
            $summary['cancel_preview_status'] = $normalizedResponse['cancel_preview_status'] ?? null;
            $summary['preview_penalty'] = $normalizedResponse['penalty'] ?? null;
            $summary['preview_refundable_amount'] = $normalizedResponse['refundable_amount'] ?? null;
            $summary['preview_currency'] = $normalizedResponse['currency'] ?? null;
            $summary['preview_response_refs'] = $normalizedResponse['preview_response_refs'] ?? null;
        } else {
            $summary['cancellation_status'] = $normalizedResponse['cancellation_status'] ?? null;
        }

        $diagnosticPath = $this->saveCancelDiagnosticFiles(
            connectionId: $connection->id,
            correlationId: $correlationId,
            requestXml: $sanitizedRequestXml,
            responseXml: $responseXml,
            normalizedResponse: $normalizedResponse,
            summary: $summary,
            storageRoot: 'order-cancel',
        );
        $summary['diagnostic_path'] = $diagnosticPath;

        return [
            'success' => $success,
            'diagnostic_path' => $diagnosticPath,
            'summary' => $summary,
        ];
    }

    /**
     * @return array{success: bool, diagnostic_path: string, summary: array<string, mixed>, probe_results: list<array<string, mixed>>}
     */
    private function runCancelShapeProbe(
        SupplierConnection $connection,
        string $orderId,
        string $ownerCode,
    ): array {
        $config = $this->configResolver->resolve($connection);
        $probeCorrelationId = $this->correlationContext->newCorrelationId();
        $probeResults = [];

        foreach (PiaNdcXmlBuilder::CANCEL_PROBE_SHAPES as $shape) {
            foreach ($this->xmlBuilder->compatibleCancelOperationsForShape($shape) as $operationKey) {
                $probeResults[] = $this->saveCancelProbeVariant(
                    connection: $connection,
                    config: $config,
                    orderId: $orderId,
                    ownerCode: $ownerCode,
                    probeCorrelationId: $probeCorrelationId,
                    shape: $shape,
                    operationKey: $operationKey,
                );
            }
        }

        $summary = [
            'connection_id' => $connection->id,
            'correlation_id' => $probeCorrelationId,
            'order_id' => $orderId,
            'owner_code' => $ownerCode,
            'dry_run' => true,
            'supplier_called' => false,
            'probe_shapes' => true,
            'shape_count' => count(PiaNdcXmlBuilder::CANCEL_PROBE_SHAPES),
            'variant_count' => count($probeResults),
            'success' => true,
            'diagnostic_path' => storage_path('app/diagnostics/pia-ndc/order-cancel-probe/'.$connection->id.'/'.$probeCorrelationId),
        ];

        return [
            'success' => true,
            'diagnostic_path' => $summary['diagnostic_path'],
            'summary' => $summary,
            'probe_results' => $probeResults,
        ];
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function saveCancelProbeVariant(
        SupplierConnection $connection,
        array $config,
        string $orderId,
        string $ownerCode,
        string $probeCorrelationId,
        string $shape,
        string $operationKey,
    ): array {
        $requestXml = $this->xmlBuilder->buildCancelDiagnosticRequest($config, $orderId, $ownerCode, $shape);
        $sanitizedRequestXml = $this->client->sanitizeXmlForDiagnostics($requestXml);
        $folderName = count($this->xmlBuilder->compatibleCancelOperationsForShape($shape)) > 1
            ? $shape.'__'.$operationKey
            : $shape;

        $summary = [
            'connection_id' => $connection->id,
            'correlation_id' => $probeCorrelationId,
            'order_id' => $orderId,
            'owner_code' => $ownerCode,
            'shape' => $shape,
            'operation' => $operationKey,
            'soap_action' => $this->xmlBuilder->soapActionForCancelOperation($operationKey),
            'dry_run' => true,
            'supplier_called' => false,
            'success' => true,
        ];

        $diagnosticPath = $this->saveCancelDiagnosticFiles(
            connectionId: $connection->id,
            correlationId: $probeCorrelationId,
            requestXml: $sanitizedRequestXml,
            responseXml: null,
            normalizedResponse: null,
            summary: $summary,
            storageRoot: 'order-cancel-probe',
            shapeFolder: $folderName,
        );
        $summary['diagnostic_path'] = $diagnosticPath;

        return $summary;
    }

    private function resolveOperationKey(string $shape, ?string $operationOverride): string
    {
        if ($operationOverride !== null && trim($operationOverride) !== '') {
            return $this->xmlBuilder->resolveCancelOperationKey($operationOverride);
        }

        return $this->xmlBuilder->defaultCancelOperationForShape($shape);
    }

    /**
     * @param  ?array<string, mixed>  $normalizedResponse
     */
    private function evaluateCancelDiagnosticSuccess(
        bool $executeCancel,
        bool $supplierCalled,
        ?int $httpStatus,
        ?string $providerErrorCode,
        ?array $normalizedResponse,
        string $operationKey = '',
    ): bool {
        if (! $executeCancel) {
            return true;
        }

        $httpOk = $httpStatus !== null && $httpStatus >= 200 && $httpStatus < 300;

        if ($operationKey === 'cancel_preview') {
            return $supplierCalled
                && $httpOk
                && $providerErrorCode === null
                && ($normalizedResponse['success'] ?? false) === true;
        }

        return $supplierCalled
            && $httpOk
            && $providerErrorCode === null
            && ($normalizedResponse['cancellation_status'] ?? '') === 'cancelled'
            && ($normalizedResponse['success'] ?? false) === true;
    }

    private function isInactiveOrderStatus(mixed $status): bool
    {
        return in_array(strtoupper(trim((string) $status)), ['CANCELLED', 'CANCELED', 'CLOSED', 'VOIDED'], true);
    }

    private function assertCancelExecuteAllowed(string $shape, string $operationKey, ?string $confirmPhrase): void
    {
        if (in_array($shape, PiaNdcXmlBuilder::CANCEL_LEGACY_PROBE_SHAPES, true)) {
            throw new PiaNdcValidationException(
                'legacy_cancel_shape_blocked',
                422,
                'Legacy IATA_OrderCancelRQ shapes are dry-run/probe only until explicitly enabled.',
            );
        }

        if ($shape === 'hitit_cancel_commit_sample_exact') {
            throw new PiaNdcValidationException(
                'commit_sample_exact_blocked',
                422,
                'Shape hitit_cancel_commit_sample_exact is dry-run/probe only until Hitit confirms commit.',
            );
        }

        if ($operationKey !== 'cancel_preview') {
            throw new PiaNdcValidationException(
                'commit_execute_blocked',
                422,
                'Live doOrderCancelCommit execution is blocked in R11F. Use --shape='.implode('|', PiaNdcXmlBuilder::CANCEL_EXECUTE_ALLOWED_SHAPES).' with --confirm="'.self::PREVIEW_CONFIRM_PHRASE.'".',
            );
        }

        if (! in_array($shape, PiaNdcXmlBuilder::CANCEL_EXECUTE_ALLOWED_SHAPES, true)) {
            throw new PiaNdcValidationException(
                'shape_execute_blocked',
                422,
                'Shape '.$shape.' is dry-run/probe only. Live execute is limited to: '.implode(', ', PiaNdcXmlBuilder::CANCEL_EXECUTE_ALLOWED_SHAPES).' (doOrderCancelPreview).',
            );
        }

        if ($confirmPhrase === self::COMMIT_CONFIRM_PHRASE) {
            throw new PiaNdcValidationException(
                'preview_confirm_required',
                422,
                'Preview execute requires --confirm="'.self::PREVIEW_CONFIRM_PHRASE.'", not '.self::COMMIT_CONFIRM_PHRASE.'.',
            );
        }
    }

    private function assertCancelExecuteGuards(
        SupplierConnection $connection,
        string $orderId,
        string $ownerCode,
        ?string $confirmPhrase,
        string $shape,
        string $operationKey,
    ): void {
        unset($orderId, $ownerCode, $shape, $operationKey);

        if ($confirmPhrase !== self::PREVIEW_CONFIRM_PHRASE) {
            throw new PiaNdcValidationException(
                'missing_confirmation',
                422,
                'Execute preview requires --confirm="'.self::PREVIEW_CONFIRM_PHRASE.'".',
            );
        }

        if (! $connection->is_active) {
            throw new PiaNdcValidationException('connection_inactive', 422, 'Supplier connection is not active.');
        }

        $health = $this->diagnosticService->healthCheck($connection);
        if (! ($health['healthy'] ?? false)) {
            throw new PiaNdcValidationException('connection_unhealthy', 422, 'Supplier connection failed health check.');
        }
    }

    /**
     * @param  ?array<string, mixed>  $normalizedResponse
     * @param  array<string, mixed>  $summary
     */
    private function saveCancelDiagnosticFiles(
        int $connectionId,
        string $correlationId,
        string $requestXml,
        ?string $responseXml,
        ?array $normalizedResponse,
        array $summary,
        string $storageRoot = 'order-cancel',
        ?string $shapeFolder = null,
    ): string {
        $directory = storage_path('app/diagnostics/pia-ndc/'.$storageRoot.'/'.$connectionId.'/'.$correlationId);
        if ($shapeFolder !== null && $shapeFolder !== '') {
            $directory .= '/'.$shapeFolder;
        }
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
}
