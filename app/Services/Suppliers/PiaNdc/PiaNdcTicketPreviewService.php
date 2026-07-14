<?php

namespace App\Services\Suppliers\PiaNdc;

use App\Models\Booking;
use App\Models\SupplierConnection;
use App\Models\User;
use App\Services\Suppliers\PiaNdc\Exceptions\PiaNdcException;
use App\Services\Suppliers\PiaNdc\Exceptions\PiaNdcTicketingException;
use App\Support\Bookings\PiaNdcOperationAuditRecorder;
use App\Support\Bookings\PiaNdcOperationLabels;

class PiaNdcTicketPreviewService
{
    public const PREVIEW_CONFIRM_PHRASE = 'PREVIEW_PIA_NDC_TICKET';

    public function __construct(
        private readonly PiaNdcClient $client,
        private readonly PiaNdcConfigResolver $configResolver,
        private readonly PiaNdcXmlBuilder $xmlBuilder,
        private readonly PiaNdcResponseNormalizer $normalizer,
        private readonly PiaNdcOrderOperationPreflight $preflight,
        private readonly PiaNdcCorrelationContext $correlationContext,
        private readonly PiaNdcOperationAuditRecorder $operationAuditRecorder,
    ) {}

    /**
     * Live DoTicketPreview — persists preview on success (admin / internal ticketing path).
     *
     * @return array{amount: float, currency: string}
     */
    public function preview(Booking $booking, SupplierConnection $connection): array
    {
        $result = $this->runPreview($booking, $connection, [
            'dry_run' => false,
            'persist' => true,
            'require_fresh_retrieve' => false,
        ]);

        if (($result['preview'] ?? null) === null) {
            throw new PiaNdcTicketingException(
                (string) ($result['error_code'] ?? 'preview_failed'),
                422,
                'Ticketing preview failed, admin review required.',
                $result,
            );
        }

        return $result['preview'];
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function previewDryRun(Booking $booking, SupplierConnection $connection, array $options = []): array
    {
        return $this->runPreview($booking, $connection, array_merge([
            'dry_run' => true,
            'persist' => false,
            'require_fresh_retrieve' => false,
        ], $options));
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function runPreview(Booking $booking, SupplierConnection $connection, array $options = []): array
    {
        $dryRun = (bool) ($options['dry_run'] ?? false);
        $persist = (bool) ($options['persist'] ?? ! $dryRun);
        $requireFreshRetrieve = (bool) ($options['require_fresh_retrieve'] ?? false);
        $correlationId = $this->correlationContext->newCorrelationId();

        try {
            $resolved = $this->preflight->assertOrderContext($booking, 'ticketing');
        } catch (PiaNdcTicketingException $exception) {
            return $this->finalizePreviewRun($booking, $connection, $options, $this->previewResult(
                dryRun: $dryRun,
                supplierCalled: false,
                booking: $booking,
                orderId: '',
                ownerCode: '',
                requestBuilt: false,
                errorCode: $exception->normalizedCode,
                errorMessage: $exception->safeMessage,
            ));
        }

        $orderId = $resolved['order_id'];
        $ownerCode = $resolved['owner_code'];
        $preflightSummary = null;

        if ($requireFreshRetrieve && ! $dryRun) {
            $retrieveResult = $this->preflight->freshRetrieve($booking, $connection);
            try {
                $preflightSummary = $this->preflight->assertRetrieveSucceeded($retrieveResult, 'ticketing');
            } catch (PiaNdcTicketingException $exception) {
                return $this->finalizePreviewRun($booking, $connection, $options, $this->previewResult(
                    dryRun: false,
                    supplierCalled: true,
                    booking: $booking,
                    orderId: $orderId,
                    ownerCode: $ownerCode,
                    requestBuilt: false,
                    errorCode: $exception->normalizedCode,
                    errorMessage: $exception->safeMessage,
                    preflightSummary: $preflightSummary,
                ));
            }

            if ($this->preflight->duplicateTicketGuard($booking->fresh() ?? $booking)) {
                return $this->finalizePreviewRun($booking, $connection, $options, $this->previewResult(
                    dryRun: false,
                    supplierCalled: true,
                    booking: $booking,
                    orderId: $orderId,
                    ownerCode: $ownerCode,
                    requestBuilt: false,
                    errorCode: 'duplicate_ticketing_guard',
                    errorMessage: 'Ticket preview refused: ticket numbers already exist.',
                    preflightSummary: $preflightSummary,
                ));
            }
        }

        $config = $this->configResolver->resolve($connection);
        $requestXml = $this->xmlBuilder->buildTicketPreviewRequest($config, $orderId, $ownerCode);
        $sanitizedRequestXml = $this->client->sanitizeXmlForDiagnostics($requestXml);

        if ($dryRun) {
            $summary = $this->previewResult(
                dryRun: true,
                supplierCalled: false,
                booking: $booking,
                orderId: $orderId,
                ownerCode: $ownerCode,
                requestBuilt: true,
                preflightSummary: $preflightSummary,
            );
            $summary['diagnostic_path'] = $this->preflight->saveOperationDiagnostic(
                $connection->id,
                'ticket-preview',
                $correlationId,
                $summary,
                $sanitizedRequestXml,
            );

            return $this->finalizePreviewRun($booking, $connection, $options, $summary);
        }

        try {
            $response = $this->client->call($connection, 'ticket_preview', $requestXml, [
                'booking_id' => $booking->id,
                'request_context' => 'ticket_preview',
                'correlation_id' => $correlationId,
            ]);
            $preview = $this->normalizer->normalizeTicketPreviewResponse($response);
            if ($persist) {
                $this->persistPreview($booking, $preview);
            }

            $summary = $this->previewResult(
                dryRun: false,
                supplierCalled: true,
                booking: $booking,
                orderId: $orderId,
                ownerCode: $ownerCode,
                requestBuilt: true,
                preview: $preview,
                preflightSummary: $preflightSummary,
            );
            $summary['diagnostic_path'] = $this->preflight->saveOperationDiagnostic(
                $connection->id,
                'ticket-preview',
                $correlationId,
                $summary,
                $sanitizedRequestXml,
            );

            return $this->finalizePreviewRun($booking, $connection, $options, $summary);
        } catch (PiaNdcException $exception) {
            $summary = $this->previewResult(
                dryRun: false,
                supplierCalled: true,
                booking: $booking,
                orderId: $orderId,
                ownerCode: $ownerCode,
                requestBuilt: true,
                errorCode: $exception->normalizedCode,
                errorMessage: 'Ticketing preview failed, admin review required.',
                preflightSummary: $preflightSummary,
            );
            $summary['diagnostic_path'] = $this->preflight->saveOperationDiagnostic(
                $connection->id,
                'ticket-preview',
                $correlationId,
                $summary,
                $sanitizedRequestXml,
            );

            return $this->finalizePreviewRun($booking, $connection, $options, $summary);
        }
    }

    /**
     * @param  array<string, mixed>  $options
     * @param  array<string, mixed>  $summary
     * @return array<string, mixed>
     */
    private function finalizePreviewRun(
        Booking $booking,
        SupplierConnection $connection,
        array $options,
        array $summary,
    ): array {
        $summary = PiaNdcOperationLabels::applyToSummary($summary, 'ticket_preview');
        $persist = (bool) ($options['persist'] ?? false);
        $dryRun = (bool) ($options['dry_run'] ?? false);
        if ($persist && ! $dryRun) {
            $actor = $options['actor'] ?? null;
            $this->operationAuditRecorder->recordTicketPreview(
                $booking,
                $connection,
                $actor instanceof User ? $actor : null,
                $summary,
            );
        }

        return $summary;
    }

    /**
     * @param  array{amount: float, currency: string}  $preview
     */
    private function persistPreview(Booking $booking, array $preview): void
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $context = is_array($meta['pia_ndc_context'] ?? null) ? $meta['pia_ndc_context'] : [];
        $context['ticket_preview'] = $preview;
        $context['ticket_preview_at'] = now()->toIso8601String();
        $meta['pia_ndc_context'] = $context;
        $booking->meta = $meta;
        $booking->save();
    }

    /**
     * @param  ?array{amount: float, currency: string}  $preview
     * @param  ?array<string, mixed>  $preflightSummary
     * @return array<string, mixed>
     */
    private function previewResult(
        bool $dryRun,
        bool $supplierCalled,
        Booking $booking,
        string $orderId,
        string $ownerCode,
        bool $requestBuilt,
        ?array $preview = null,
        ?string $errorCode = null,
        ?string $errorMessage = null,
        ?array $preflightSummary = null,
    ): array {
        return array_filter([
            'dry_run' => $dryRun,
            'supplier_called' => $supplierCalled,
            'operation' => PiaNdcOperationLabels::displayForConfigKey('ticket_preview'),
            'booking_id' => $booking->id,
            'order_id' => $orderId,
            'owner_code' => $ownerCode,
            'request_built' => $requestBuilt,
            'preview' => $preview,
            'order_status' => $preflightSummary['order_status'] ?? null,
            'payment_time_limit' => $preflightSummary['payment_time_limit'] ?? null,
            'error_code' => $errorCode,
            'error_message' => $errorMessage,
            'success' => $errorCode === null && ($dryRun ? $requestBuilt : $preview !== null),
        ], fn ($value) => $value !== null);
    }
}
