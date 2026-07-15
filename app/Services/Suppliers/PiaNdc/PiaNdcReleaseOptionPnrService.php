<?php

namespace App\Services\Suppliers\PiaNdc;

use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Models\SupplierConnection;
use App\Models\User;
use App\Services\Suppliers\PiaNdc\Exceptions\PiaNdcException;
use App\Services\Suppliers\PiaNdc\Exceptions\PiaNdcValidationException;
use App\Support\Bookings\PiaNdcOperationAuditRecorder;
use App\Support\Security\SensitiveDataRedactor;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Controlled release (DoOrderCancelPreview + DoOrderCancelCommit) for unticketed PIA NDC option PNRs (R12F).
 */
class PiaNdcReleaseOptionPnrService
{
    public const RELEASE_CONFIRM_PHRASE = 'RELEASE_PIA_OPTION_PNR';

    private const PREVIEW_SHAPE = 'hitit_cancel_preview_sample_exact';

    private const COMMIT_SHAPE = 'hitit_cancel_commit_sample_exact';

    public function __construct(
        private readonly PiaNdcClient $client,
        private readonly PiaNdcConfigResolver $configResolver,
        private readonly PiaNdcXmlBuilder $xmlBuilder,
        private readonly PiaNdcXmlParser $xmlParser,
        private readonly PiaNdcResponseNormalizer $normalizer,
        private readonly PiaNdcCorrelationContext $correlationContext,
        private readonly PiaNdcReleaseExecutionLock $executionLock,
        private readonly PiaNdcBookingStatusRefreshService $statusRefreshService,
        private readonly PiaNdcOperationAuditRecorder $operationAuditRecorder,
    ) {}

    /**
     * @return list<string>
     */
    public function clearStaleReleaseLocks(?int $connectionId = null): array
    {
        return $this->executionLock->clearStaleLocks($connectionId);
    }

    public function canReleaseBooking(Booking $booking): bool
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $provider = strtolower(trim((string) ($meta['supplier_provider'] ?? $booking->supplier ?? '')));
        if ($provider !== SupplierProvider::PiaNdc->value) {
            return false;
        }

        $context = is_array($meta['pia_ndc_context'] ?? null) ? $meta['pia_ndc_context'] : [];
        if (($context['option_pnr_released'] ?? false) === true || ($context['cancel_committed'] ?? false) === true) {
            return false;
        }

        $interpreted = strtolower(trim((string) ($context['interpreted_status'] ?? '')));
        if (in_array($interpreted, ['released', 'no_active_segments', 'ticketed'], true)) {
            return false;
        }

        $supplierStatus = strtolower(trim((string) ($booking->supplier_booking_status ?? '')));
        if (in_array($supplierStatus, ['released', 'cancelled', 'closed', 'ticketed'], true)) {
            return false;
        }

        $orderId = $this->resolveBookingOrderId($booking, $context);
        if ($orderId === '') {
            return false;
        }

        if ($this->bookingHasBlockingTickets($booking, $context)) {
            return false;
        }

        return true;
    }

    /**
     * @return array{success: bool, diagnostic_path: string, summary: array<string, mixed>}
     */
    public function runReleaseForBooking(
        Booking $booking,
        User $actor,
        string $confirmPhrase,
        string $reason,
    ): array {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $context = is_array($meta['pia_ndc_context'] ?? null) ? $meta['pia_ndc_context'] : [];
        $orderId = $this->resolveBookingOrderId($booking, $context);
        $ownerCode = trim((string) ($context['owner_code'] ?? ''));
        if ($orderId === '' || $ownerCode === '') {
            throw new PiaNdcValidationException('missing_order_context', 422, 'PIA NDC order context is missing on this booking.');
        }

        $connection = $this->resolveBookingConnection($booking, $meta);
        if ($connection === null) {
            throw new PiaNdcValidationException('missing_connection', 422, 'PIA NDC supplier connection not found for this booking.');
        }

        $booking = $this->statusRefreshService->refreshIfRequiredForSensitiveAction($booking, $actor, 'release_option_pnr');

        $result = $this->runReleaseDiagnostic(
            connection: $connection,
            orderId: $orderId,
            ownerCode: $ownerCode,
            executeRelease: true,
            confirmPhrase: $confirmPhrase,
            reason: $reason,
            booking: $booking,
        );

        $this->persistBookingReleaseAttempt($booking, $connection, $actor, $reason, $result);

        return $result;
    }

    /**
     * @return array{success: bool, diagnostic_path: string, summary: array<string, mixed>}
     */
    public function runReleaseDiagnostic(
        SupplierConnection $connection,
        string $orderId,
        string $ownerCode,
        bool $executeRelease = false,
        ?string $confirmPhrase = null,
        ?string $reason = null,
        ?Booking $booking = null,
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

        if ($executeRelease) {
            $this->assertExecuteGuards($confirmPhrase, $reason);
            if ($this->executionLock->isCommitted($connection->id, $orderId, $ownerCode)) {
                throw new PiaNdcValidationException(
                    'release_already_committed',
                    422,
                    'Option PNR release was already committed for this order.',
                );
            }
        }

        $correlationId = $this->correlationContext->newCorrelationId();
        $config = $this->configResolver->resolve($connection);
        $diagnosticRoot = storage_path('app/diagnostics/pia-ndc/release-option-pnr/'.$connection->id.'/'.$correlationId);
        File::ensureDirectoryExists($diagnosticRoot);

        $retrieveStep = $this->runRetrieveStep($connection, $config, $orderId, $ownerCode, $correlationId, $diagnosticRoot);
        $this->assertRetrieveSucceeded($retrieveStep);

        if (($retrieveStep['summary']['has_blocking_ticket_numbers'] ?? false) === true) {
            throw new PiaNdcValidationException(
                'ticketed_order_release_blocked',
                422,
                'Release refused: order has issued ticket number(s).',
            );
        }

        if ($this->isInactiveOrderStatus($retrieveStep['summary']['order_status'] ?? null)) {
            throw new PiaNdcValidationException(
                'order_already_inactive',
                422,
                'Release refused: order status indicates already cancelled or closed.',
            );
        }

        $previewStep = $this->runPreviewStep($connection, $config, $orderId, $ownerCode, $correlationId, $diagnosticRoot);
        $this->assertPreviewSucceeded($previewStep);

        $commitStep = [
            'http_status' => null,
            'success' => false,
            'supplier_called' => false,
            'summary' => [],
            'normalized' => null,
            'request_xml' => null,
            'response_xml' => null,
        ];
        $finalRetrieveStep = [
            'http_status' => null,
            'success' => false,
            'summary' => [],
        ];
        $lockPath = null;

        $commitRequestXml = $this->xmlBuilder->buildCancelDiagnosticRequest($config, $orderId, $ownerCode, self::COMMIT_SHAPE);
        $sanitizedCommitRequestXml = $this->client->sanitizeXmlForDiagnostics($commitRequestXml);
        file_put_contents($diagnosticRoot.'/commit-request.xml', $sanitizedCommitRequestXml);

        if ($executeRelease) {
            $lockPath = $this->executionLock->acquire($connection->id, $orderId, $ownerCode, $correlationId);
            $commitStep = $this->runCommitStep(
                $connection,
                $config,
                $orderId,
                $ownerCode,
                $correlationId,
                $diagnosticRoot,
                $commitRequestXml,
            );
            $this->assertCommitSucceeded($commitStep);
            $this->executionLock->markCommitted($lockPath, true, $correlationId);

            $finalRetrieveStep = $this->runRetrieveStep(
                $connection,
                $config,
                $orderId,
                $ownerCode,
                $correlationId,
                $diagnosticRoot,
                'final-retrieve',
            );
        }

        $dryRun = ! $executeRelease;
        $success = $dryRun
            ? true
            : ($commitStep['success'] ?? false) === true;

        $summary = [
            'connection_id' => $connection->id,
            'correlation_id' => $correlationId,
            'order_id' => $orderId,
            'owner_code' => $ownerCode,
            'dry_run' => $dryRun,
            'execute_release' => $executeRelease,
            'operator_reason' => $executeRelease ? trim((string) $reason) : null,
            'booking_id' => $booking?->id,
            'retrieve_http_status' => $retrieveStep['http_status'] ?? null,
            'retrieve_success' => ($retrieveStep['success'] ?? false) === true,
            'preview_http_status' => $previewStep['http_status'] ?? null,
            'preview_success' => ($previewStep['success'] ?? false) === true,
            'commit_http_status' => $commitStep['http_status'] ?? null,
            'commit_success' => $executeRelease ? (($commitStep['success'] ?? false) === true) : null,
            'commit_supplier_called' => $executeRelease ? (($commitStep['supplier_called'] ?? false) === true) : false,
            'final_retrieve_http_status' => $finalRetrieveStep['http_status'] ?? null,
            'final_retrieve_success' => $executeRelease ? (($finalRetrieveStep['success'] ?? false) === true) : null,
            'cancellation_status' => $executeRelease
                ? ($commitStep['normalized']['cancellation_status'] ?? null)
                : null,
            'order_status' => $executeRelease
                ? ($finalRetrieveStep['summary']['order_status'] ?? $commitStep['normalized']['order_status'] ?? null)
                : ($retrieveStep['summary']['order_status'] ?? null),
            'ticket_numbers' => $executeRelease
                ? ($finalRetrieveStep['summary']['ticket_numbers'] ?? [])
                : ($retrieveStep['summary']['ticket_numbers'] ?? []),
            'has_blocking_ticket_numbers' => $executeRelease
                ? (bool) ($finalRetrieveStep['summary']['has_blocking_ticket_numbers'] ?? false)
                : (bool) ($retrieveStep['summary']['has_blocking_ticket_numbers'] ?? false),
            'preview_shape' => self::PREVIEW_SHAPE,
            'commit_shape' => self::COMMIT_SHAPE,
            'execution_lock_path' => $lockPath,
            'success' => $success,
        ];

        file_put_contents(
            $diagnosticRoot.'/summary.json',
            json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        );

        return [
            'success' => $success,
            'diagnostic_path' => $diagnosticRoot,
            'summary' => array_merge($summary, ['diagnostic_path' => $diagnosticRoot]),
        ];
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function resolveBookingConnection(Booking $booking, array $meta): ?SupplierConnection
    {
        $connectionId = (int) ($meta['supplier_connection_id'] ?? 0);
        if ($connectionId > 0) {
            $connection = SupplierConnection::query()
                ->where('id', $connectionId)
                ->where('provider', SupplierProvider::PiaNdc)
                ->first();
            if ($connection !== null) {
                return $connection;
            }
        }

        $booking->loadMissing('latestSupplierBooking.supplierConnection');
        $latest = $booking->latestSupplierBooking?->supplierConnection;
        if ($latest !== null && $latest->provider === SupplierProvider::PiaNdc) {
            return $latest;
        }

        return SupplierConnection::query()
            ->where('provider', SupplierProvider::PiaNdc)
            ->orderByDesc('is_active')
            ->first();
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function resolveBookingOrderId(Booking $booking, array $context): string
    {
        $orderId = trim((string) ($context['order_id'] ?? ''));
        if ($orderId === '' && Schema::hasColumn($booking->getTable(), 'supplier_reference')) {
            $orderId = trim((string) ($booking->supplier_reference ?? ''));
        }

        return $orderId;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function bookingHasBlockingTickets(Booking $booking, array $context): bool
    {
        $ticketDocInfos = is_array($context['ticket_doc_infos'] ?? null) ? $context['ticket_doc_infos'] : [];
        if ($ticketDocInfos !== [] && $this->normalizer->hasBlockingTicketNumbers($ticketDocInfos)) {
            return true;
        }

        $ticketNumbers = is_array($context['ticket_numbers'] ?? null) ? $context['ticket_numbers'] : [];
        if ($ticketNumbers !== [] && $ticketDocInfos === [] && $this->normalizer->hasBlockingTicketNumbers(
            array_map(fn (string $number): array => ['ticket_number' => $number], array_map('strval', $ticketNumbers)),
        )) {
            return true;
        }

        $booking->loadMissing('tickets');
        foreach ($booking->tickets as $ticket) {
            $number = trim((string) ($ticket->ticket_number ?? ''));
            if ($number !== '' && $this->normalizer->hasBlockingTicketNumbers([['ticket_number' => $number]])) {
                return true;
            }
        }

        return false;
    }

    private function assertExecuteGuards(?string $confirmPhrase, ?string $reason): void
    {
        if ($confirmPhrase !== self::RELEASE_CONFIRM_PHRASE) {
            throw new PiaNdcValidationException(
                'missing_confirmation',
                422,
                'Execute release requires --confirm="'.self::RELEASE_CONFIRM_PHRASE.'".',
            );
        }

        if (trim((string) $reason) === '') {
            throw new PiaNdcValidationException(
                'missing_operator_reason',
                422,
                'Execute release requires --reason with an operator note.',
            );
        }
    }

    /**
     * @param  array<string, mixed>  $step
     */
    private function assertRetrieveSucceeded(array $step): void
    {
        if (($step['success'] ?? false) !== true) {
            throw new PiaNdcValidationException(
                'preflight_retrieve_failed',
                422,
                'Release refused: retrieve did not succeed.',
            );
        }
    }

    /**
     * @param  array<string, mixed>  $step
     */
    private function assertPreviewSucceeded(array $step): void
    {
        if (($step['success'] ?? false) !== true) {
            throw new PiaNdcValidationException(
                'cancel_preview_failed',
                422,
                'Release refused: cancel preview did not succeed.',
            );
        }
    }

    /**
     * @param  array<string, mixed>  $step
     */
    private function assertCommitSucceeded(array $step): void
    {
        if (($step['success'] ?? false) !== true) {
            throw new PiaNdcValidationException(
                'cancel_commit_failed',
                422,
                'Release refused: cancel commit did not succeed.',
            );
        }
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function runRetrieveStep(
        SupplierConnection $connection,
        array $config,
        string $orderId,
        string $ownerCode,
        string $correlationId,
        string $diagnosticRoot,
        string $prefix = 'retrieve',
    ): array {
        $requestXml = $this->xmlBuilder->buildOrderRetrieveRequest($config, $orderId, $ownerCode);
        $sanitizedRequestXml = $this->client->sanitizeXmlForDiagnostics($requestXml);
        file_put_contents($diagnosticRoot.'/'.$prefix.'-request.xml', $sanitizedRequestXml);

        $httpStatus = null;
        $responseXml = null;
        $normalized = null;
        $providerErrorCode = null;

        try {
            $parsedResponse = $this->client->call($connection, 'order_retrieve', $requestXml, [
                'request_context' => 'pia-ndc:release-option-pnr',
                'correlation_id' => $correlationId,
                'booking_id' => null,
            ]);
            $diagnostic = is_array($parsedResponse['_ota_diagnostic'] ?? null) ? $parsedResponse['_ota_diagnostic'] : [];
            $httpStatus = isset($diagnostic['http_status']) ? (int) $diagnostic['http_status'] : null;
            $responseXml = is_string($parsedResponse['raw_xml'] ?? null)
                ? $this->client->sanitizeXmlForDiagnostics($parsedResponse['raw_xml'])
                : null;
            $normalized = $this->normalizer->normalizeOrderRetrieveDiagnosticResponse($parsedResponse, [
                'order_id' => $orderId,
                'owner_code' => $ownerCode,
            ]);
            if (($parsedResponse['errors'][0]['code'] ?? '') !== '') {
                $providerErrorCode = (string) $parsedResponse['errors'][0]['code'];
            }
        } catch (PiaNdcException $exception) {
            $safeMeta = $exception->safeDiagnosticMeta('order_retrieve');
            $httpStatus = isset($safeMeta['http_status']) ? (int) $safeMeta['http_status'] : null;
            $providerErrorCode = $exception->normalizedCode;
            $responseXml = is_string($exception->context['response_xml'] ?? null)
                ? $exception->context['response_xml']
                : null;
            if ($responseXml !== null) {
                try {
                    $parsedResponse = $this->xmlParser->parse($responseXml);
                    $normalized = $this->normalizer->normalizeOrderRetrieveDiagnosticResponse($parsedResponse, [
                        'order_id' => $orderId,
                        'owner_code' => $ownerCode,
                    ]);
                } catch (Throwable) {
                    $normalized = null;
                }
            }
        }

        if ($responseXml !== null) {
            file_put_contents($diagnosticRoot.'/'.$prefix.'-response.xml', $responseXml);
        }

        $resolvedOrderId = trim((string) ($normalized['order_id'] ?? $orderId));
        $httpOk = $httpStatus !== null && $httpStatus >= 200 && $httpStatus < 300;
        $success = $httpOk && $providerErrorCode === null && $resolvedOrderId !== '';

        $summary = [
            'http_status' => $httpStatus,
            'success' => $success,
            'order_id' => $resolvedOrderId,
            'order_status' => $normalized['order_status'] ?? null,
            'ticket_numbers' => $normalized['ticket_numbers'] ?? [],
            'has_blocking_ticket_numbers' => (bool) ($normalized['has_blocking_ticket_numbers'] ?? false),
            'provider_error_code' => $providerErrorCode,
        ];
        file_put_contents(
            $diagnosticRoot.'/'.$prefix.'-summary.json',
            json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        );
        if ($normalized !== null) {
            file_put_contents(
                $diagnosticRoot.'/'.$prefix.'-normalized.json',
                json_encode(SensitiveDataRedactor::redact($normalized), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            );
        }

        return [
            'http_status' => $httpStatus,
            'success' => $success,
            'summary' => $summary,
            'normalized' => $normalized,
        ];
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function runPreviewStep(
        SupplierConnection $connection,
        array $config,
        string $orderId,
        string $ownerCode,
        string $correlationId,
        string $diagnosticRoot,
    ): array {
        $requestXml = $this->xmlBuilder->buildCancelDiagnosticRequest($config, $orderId, $ownerCode, self::PREVIEW_SHAPE);
        $sanitizedRequestXml = $this->client->sanitizeXmlForDiagnostics($requestXml);
        file_put_contents($diagnosticRoot.'/preview-request.xml', $sanitizedRequestXml);

        return $this->runSupplierCancelOperation(
            connection: $connection,
            operationKey: 'cancel_preview',
            requestXml: $requestXml,
            correlationId: $correlationId,
            diagnosticRoot: $diagnosticRoot,
            prefix: 'preview',
            normalize: fn (?array $parsed, ?int $httpStatus, ?string $providerErrorCode, ?string $providerErrorMessage, ?array $soapFault): array => $this->normalizer->normalizeCancelPreviewDiagnosticResponse(
                parsedResponse: $parsed,
                httpStatus: $httpStatus,
                providerErrorCode: $providerErrorCode,
                providerErrorMessage: $providerErrorMessage,
                soapFault: $soapFault,
            ),
            successEvaluator: fn (array $normalized, ?int $httpStatus, ?string $providerErrorCode): bool => $httpStatus !== null
                && $httpStatus >= 200
                && $httpStatus < 300
                && $providerErrorCode === null
                && ($normalized['success'] ?? false) === true,
        );
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function runCommitStep(
        SupplierConnection $connection,
        array $config,
        string $orderId,
        string $ownerCode,
        string $correlationId,
        string $diagnosticRoot,
        string $commitRequestXml,
    ): array {
        unset($config, $ownerCode);

        return $this->runSupplierCancelOperation(
            connection: $connection,
            operationKey: 'cancel_commit',
            requestXml: $commitRequestXml,
            correlationId: $correlationId,
            diagnosticRoot: $diagnosticRoot,
            prefix: 'commit',
            normalize: fn (?array $parsed, ?int $httpStatus, ?string $providerErrorCode, ?string $providerErrorMessage, ?array $soapFault): array => $this->normalizer->normalizeCancelDiagnosticResponse(
                parsedResponse: $parsed,
                httpStatus: $httpStatus,
                providerErrorCode: $providerErrorCode,
                providerErrorMessage: $providerErrorMessage,
                soapFault: $soapFault,
            ),
            successEvaluator: fn (array $normalized, ?int $httpStatus, ?string $providerErrorCode): bool => $httpStatus !== null
                && $httpStatus >= 200
                && $httpStatus < 300
                && $providerErrorCode === null
                && ($normalized['cancellation_status'] ?? '') === 'cancelled'
                && ($normalized['success'] ?? false) === true,
        );
    }

    /**
     * @param  callable(?array, ?int, ?string, ?string, ?array): array<string, mixed>  $normalize
     * @param  callable(array<string, mixed>, ?int, ?string): bool  $successEvaluator
     * @return array<string, mixed>
     */
    private function runSupplierCancelOperation(
        SupplierConnection $connection,
        string $operationKey,
        string $requestXml,
        string $correlationId,
        string $diagnosticRoot,
        string $prefix,
        callable $normalize,
        callable $successEvaluator,
    ): array {
        $httpStatus = null;
        $responseXml = null;
        $normalized = null;
        $providerErrorCode = null;
        $providerErrorMessage = null;
        $soapFaultCode = null;
        $soapFaultString = null;

        try {
            $parsedResponse = $this->client->call($connection, $operationKey, $requestXml, [
                'request_context' => 'pia-ndc:release-option-pnr',
                'correlation_id' => $correlationId,
                'soap_action_override' => $this->xmlBuilder->soapActionForCancelOperation($operationKey),
            ]);
            $diagnostic = is_array($parsedResponse['_ota_diagnostic'] ?? null) ? $parsedResponse['_ota_diagnostic'] : [];
            $httpStatus = isset($diagnostic['http_status']) ? (int) $diagnostic['http_status'] : null;
            $responseXml = is_string($parsedResponse['raw_xml'] ?? null)
                ? $this->client->sanitizeXmlForDiagnostics($parsedResponse['raw_xml'])
                : null;
            $normalized = $normalize($parsedResponse, $httpStatus, null, null, null);
        } catch (PiaNdcException $exception) {
            $safeMeta = $exception->safeDiagnosticMeta($operationKey);
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
                $soapFaultCode = (string) ($exception->context['fault_code'] ?? '');
                $soapFaultString = (string) ($exception->context['fault_message'] ?? '');
            }
            $normalized = $normalize(
                $parsedResponse,
                $httpStatus,
                $providerErrorCode,
                $providerErrorMessage,
                is_array($parsedResponse) && is_array($parsedResponse['soap_fault'] ?? null)
                    ? $parsedResponse['soap_fault']
                    : (
                        ($soapFaultCode !== '' || $soapFaultString !== '')
                            ? ['code' => $soapFaultCode, 'message' => $soapFaultString]
                            : null
                    ),
            );
        }

        if ($responseXml !== null) {
            file_put_contents($diagnosticRoot.'/'.$prefix.'-response.xml', $responseXml);
        }
        if ($normalized !== null) {
            file_put_contents(
                $diagnosticRoot.'/'.$prefix.'-normalized.json',
                json_encode(SensitiveDataRedactor::redact($normalized), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            );
        }

        $success = $successEvaluator($normalized ?? [], $httpStatus, $providerErrorCode);
        $summary = [
            'http_status' => $httpStatus,
            'success' => $success,
            'provider_error_code' => $providerErrorCode,
            'provider_error_message' => $providerErrorMessage,
            'supplier_called' => true,
        ];
        file_put_contents(
            $diagnosticRoot.'/'.$prefix.'-summary.json',
            json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        );

        return [
            'http_status' => $httpStatus,
            'success' => $success,
            'supplier_called' => true,
            'summary' => $summary,
            'normalized' => $normalized,
            'request_xml' => $this->client->sanitizeXmlForDiagnostics($requestXml),
            'response_xml' => $responseXml,
        ];
    }

    /**
     * @param  array{success: bool, diagnostic_path: string, summary: array<string, mixed>}  $result
     */
    private function persistBookingReleaseAttempt(
        Booking $booking,
        SupplierConnection $connection,
        User $actor,
        string $reason,
        array $result,
    ): void {
        $summary = is_array($result['summary'] ?? null) ? $result['summary'] : [];
        $auditSummary = array_merge($summary, [
            'success' => (bool) ($result['success'] ?? false),
            'diagnostic_path' => $result['diagnostic_path'] ?? ($summary['diagnostic_path'] ?? null),
            'commit_success' => $summary['commit_success'] ?? null,
            'order_status' => $summary['order_status'] ?? null,
            'ticket_numbers' => $summary['ticket_numbers'] ?? [],
            'has_blocking_ticket_numbers' => $summary['has_blocking_ticket_numbers'] ?? null,
        ]);

        $this->operationAuditRecorder->recordReleaseOptionPnr(
            booking: $booking,
            connection: $connection,
            actor: $actor,
            summary: $auditSummary,
            operatorReason: $reason,
        );

        if (($result['success'] ?? false) === true) {
            $this->statusRefreshService->reconcileLocalAfterSuccessfulRelease(
                booking: $booking,
                connection: $connection,
                releaseSummary: $summary,
                actor: $actor,
            );
        }
    }

    private function isInactiveOrderStatus(mixed $status): bool
    {
        return in_array(strtoupper(trim((string) $status)), ['CANCELLED', 'CANCELED', 'CLOSED', 'VOIDED'], true);
    }
}
