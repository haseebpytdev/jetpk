<?php

namespace App\Services\Suppliers\PiaNdc;

use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Models\SupplierBooking;
use App\Models\SupplierConnection;
use App\Models\User;
use App\Services\Suppliers\PiaNdc\Exceptions\PiaNdcCancellationException;
use App\Services\Suppliers\PiaNdc\Exceptions\PiaNdcException;
use App\Support\Bookings\PiaNdcBookingStatusInterpreter;
use App\Support\Bookings\PiaNdcOperationAuditRecorder;
use App\Support\Bookings\PiaNdcOperationLabels;
use App\Support\Bookings\PiaNdcVoidLocalReconciliation;
use Carbon\Carbon;

class PiaNdcVoidTicketService
{
    public const VOID_CONFIRM_PHRASE = 'VOID_PIA_NDC_TICKET';

    public function __construct(
        private readonly PiaNdcClient $client,
        private readonly PiaNdcConfigResolver $configResolver,
        private readonly PiaNdcXmlBuilder $xmlBuilder,
        private readonly PiaNdcResponseNormalizer $normalizer,
        private readonly PiaNdcOrderOperationPreflight $preflight,
        private readonly PiaNdcRetrieveService $retrieveService,
        private readonly PiaNdcCorrelationContext $correlationContext,
        private readonly PiaNdcOperationAuditRecorder $operationAuditRecorder,
    ) {}

    public function canVoidBooking(Booking $booking): bool
    {
        return $this->voidBlockedReason($booking) === null;
    }

    public function voidBlockedReason(Booking $booking): ?string
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $provider = strtolower(trim((string) ($meta['supplier_provider'] ?? $booking->supplier ?? '')));
        if ($provider !== SupplierProvider::PiaNdc->value) {
            return 'Not a PIA NDC booking.';
        }

        if ($this->preflight->duplicateVoidGuard($booking)) {
            return 'Ticket already voided.';
        }

        $context = is_array($meta['pia_ndc_context'] ?? null) ? $meta['pia_ndc_context'] : [];
        if (($context['option_pnr_released'] ?? false) === true) {
            return 'PIA NDC option PNR was released.';
        }

        $interpreted = strtolower(trim((string) ($context['interpreted_status'] ?? '')));
        if (in_array($interpreted, [
            PiaNdcBookingStatusInterpreter::STATUS_RELEASED,
            PiaNdcBookingStatusInterpreter::STATUS_NO_ACTIVE_SEGMENTS,
        ], true)) {
            return 'PIA NDC order has no active segments.';
        }

        $resolved = $this->preflight->orderContext($booking);
        if ($resolved['order_id'] === '' || $resolved['owner_code'] === '') {
            return 'PIA NDC order context is incomplete.';
        }

        $booking->loadMissing('tickets');
        if (! $this->preflight->realTicketNumbersPresent($context) && $booking->tickets->isEmpty()) {
            return 'No issued ticket is available to void.';
        }

        return null;
    }

    /**
     * Live DoVoidTicket — persists void status on success (admin path).
     *
     * @return array<string, mixed>
     */
    public function voidTicket(Booking $booking, SupplierConnection $connection, ?User $actor = null): array
    {
        $result = $this->runVoid($booking, $connection, [
            'dry_run' => false,
            'persist' => true,
            'require_fresh_retrieve' => true,
            'actor' => $actor,
        ]);

        if (($result['void_status'] ?? '') === PiaNdcVoidLocalReconciliation::TICKETING_STATUS_REQUIRES_REVIEW) {
            throw new PiaNdcCancellationException(
                (string) ($result['error_code'] ?? 'void_unconfirmed'),
                422,
                (string) ($result['error_message'] ?? 'Void response is ambiguous; admin review required.'),
                $result,
            );
        }

        if (($result['void_status'] ?? '') !== PiaNdcVoidLocalReconciliation::TICKETING_STATUS_VOIDED) {
            throw new PiaNdcCancellationException(
                (string) ($result['error_code'] ?? 'void_failed'),
                422,
                (string) ($result['error_message'] ?? 'Void ticket failed, admin review required.'),
                $result,
            );
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function voidTicketDryRun(Booking $booking, SupplierConnection $connection, array $options = []): array
    {
        return $this->runVoid($booking, $connection, array_merge([
            'dry_run' => true,
            'persist' => false,
            'require_fresh_retrieve' => false,
        ], $options));
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function runVoid(Booking $booking, SupplierConnection $connection, array $options = []): array
    {
        $dryRun = (bool) ($options['dry_run'] ?? false);
        $persist = (bool) ($options['persist'] ?? ! $dryRun);
        $requireFreshRetrieve = (bool) ($options['require_fresh_retrieve'] ?? false);
        $correlationId = $this->correlationContext->newCorrelationId();

        if ($this->preflight->duplicateVoidGuard($booking)) {
            return $this->finalizeVoidRun($booking, $connection, $options, $this->voidResult(
                dryRun: $dryRun,
                supplierCalled: false,
                booking: $booking,
                orderId: '',
                ownerCode: '',
                requestBuilt: false,
                realTicketNumbersPresent: false,
                errorCode: 'duplicate_void_guard',
                errorMessage: 'Ticket already voided.',
            ));
        }

        try {
            $resolved = $this->preflight->assertOrderContext($booking, 'void');
        } catch (PiaNdcCancellationException $exception) {
            return $this->finalizeVoidRun($booking, $connection, $options, $this->voidResult(
                dryRun: $dryRun,
                supplierCalled: false,
                booking: $booking,
                orderId: '',
                ownerCode: '',
                requestBuilt: false,
                realTicketNumbersPresent: false,
                errorCode: $exception->normalizedCode,
                errorMessage: $exception->safeMessage,
            ));
        }

        $orderId = $resolved['order_id'];
        $ownerCode = $resolved['owner_code'];
        $context = $resolved['context'];
        $preflightSummary = null;
        $supplierCalled = false;
        $realTicketNumbersPresent = $this->preflight->realTicketNumbersPresent($context);

        if ($requireFreshRetrieve && ! $dryRun) {
            $retrieveResult = $this->preflight->freshRetrieve($booking, $connection);
            $supplierCalled = true;
            try {
                $preflightSummary = $this->preflight->assertRetrieveSucceeded($retrieveResult, 'void');
            } catch (PiaNdcCancellationException $exception) {
                return $this->finalizeVoidRun($booking, $connection, $options, $this->voidResult(
                    dryRun: false,
                    supplierCalled: true,
                    booking: $booking,
                    orderId: $orderId,
                    ownerCode: $ownerCode,
                    requestBuilt: false,
                    realTicketNumbersPresent: false,
                    errorCode: $exception->normalizedCode,
                    errorMessage: $exception->safeMessage,
                    preflightSummary: $preflightSummary,
                ));
            }

            $booking = $booking->fresh() ?? $booking;
            $context = is_array($booking->meta['pia_ndc_context'] ?? null) ? $booking->meta['pia_ndc_context'] : $context;
            $realTicketNumbersPresent = $this->preflight->realTicketNumbersPresent($preflightSummary);

            if ($this->preflight->duplicateVoidGuard($booking)) {
                return $this->finalizeVoidRun($booking, $connection, $options, $this->voidResult(
                    dryRun: false,
                    supplierCalled: true,
                    booking: $booking,
                    orderId: $orderId,
                    ownerCode: $ownerCode,
                    requestBuilt: false,
                    realTicketNumbersPresent: $realTicketNumbersPresent,
                    errorCode: 'duplicate_void_guard',
                    errorMessage: 'Ticket already voided.',
                    preflightSummary: $preflightSummary,
                ));
            }
        } elseif ($dryRun) {
            $realTicketNumbersPresent = $this->preflight->realTicketNumbersPresent($context);
        }

        if (! $realTicketNumbersPresent) {
            $errorCode = $this->preflight->onlyPlaceholderTicketNumbers($context)
                ? 'placeholder_ticket_numbers'
                : 'missing_ticket_numbers';

            return $this->finalizeVoidRun($booking, $connection, $options, $this->voidResult(
                dryRun: $dryRun,
                supplierCalled: $supplierCalled,
                booking: $booking,
                orderId: $orderId,
                ownerCode: $ownerCode,
                requestBuilt: false,
                realTicketNumbersPresent: false,
                errorCode: $errorCode,
                errorMessage: 'Void refused: real ticket numbers are required.',
                preflightSummary: $preflightSummary,
            ));
        }

        $config = $this->configResolver->resolve($connection);
        $requestXml = $this->xmlBuilder->buildVoidTicketRequest($config, $orderId, $ownerCode);
        $sanitizedRequestXml = $this->client->sanitizeXmlForDiagnostics($requestXml);

        if ($dryRun) {
            $summary = $this->voidResult(
                dryRun: true,
                supplierCalled: false,
                booking: $booking,
                orderId: $orderId,
                ownerCode: $ownerCode,
                requestBuilt: true,
                realTicketNumbersPresent: true,
                preflightSummary: $preflightSummary,
            );
            $summary['diagnostic_path'] = $this->preflight->saveOperationDiagnostic(
                $connection->id,
                'void-ticket',
                $correlationId,
                $summary,
                $sanitizedRequestXml,
            );

            return $this->finalizeVoidRun($booking, $connection, $options, $summary);
        }

        try {
            $supplierCalled = true;
            $response = $this->client->call($connection, 'void_ticket', $requestXml, [
                'booking_id' => $booking->id,
                'request_context' => 'void_ticket',
                'correlation_id' => $correlationId,
            ]);
            $result = $this->normalizer->normalizeVoidResponse($response, $context);
            $ticketDocInfos = is_array($result['ticket_doc_infos'] ?? null) ? $result['ticket_doc_infos'] : [];
            if ($ticketDocInfos !== [] && $this->normalizer->hasBlockingTicketNumbers($ticketDocInfos)) {
                $result['void_status'] = null;
                $result['ticketing_status'] = PiaNdcVoidLocalReconciliation::TICKETING_STATUS_REQUIRES_REVIEW;
                $result['error_code'] = 'void_unconfirmed';
                $result['error_message'] = 'Supplier still reports active ticket coupons after void.';
                $result['success'] = false;
                if ($persist) {
                    PiaNdcVoidLocalReconciliation::applyAmbiguousVoid($booking->fresh() ?? $booking, $result);
                }
            } elseif ($persist && ($result['void_status'] ?? '') === PiaNdcVoidLocalReconciliation::TICKETING_STATUS_VOIDED) {
                $this->persistVoidResult($booking, $result, $context);
                $this->retrieveService->retrieveAndSync($booking->fresh() ?? $booking, $connection);
                PiaNdcVoidLocalReconciliation::applySuccessfulVoid($booking->fresh() ?? $booking, $result);
            }

            $summary = $this->voidResult(
                dryRun: false,
                supplierCalled: true,
                booking: $booking,
                orderId: $orderId,
                ownerCode: $ownerCode,
                requestBuilt: true,
                realTicketNumbersPresent: true,
                voidStatus: (string) ($result['void_status'] ?? ''),
                errorCode: $result['error_code'] ?? null,
                errorMessage: $result['error_message'] ?? null,
                preflightSummary: $preflightSummary,
            );
            $summary['diagnostic_path'] = $this->preflight->saveOperationDiagnostic(
                $connection->id,
                'void-ticket',
                $correlationId,
                $summary,
                $sanitizedRequestXml,
            );

            $summary['has_blocking_ticket_numbers'] = (bool) ($preflightSummary['has_blocking_ticket_numbers'] ?? false);
            $summary['ticket_numbers'] = is_array($context['ticket_numbers'] ?? null)
                ? $context['ticket_numbers']
                : (is_array($result['ticket_numbers'] ?? null) ? $result['ticket_numbers'] : []);

            return $this->finalizeVoidRun($booking, $connection, $options, $summary);
        } catch (PiaNdcException $exception) {
            $summary = $this->voidResult(
                dryRun: false,
                supplierCalled: true,
                booking: $booking,
                orderId: $orderId,
                ownerCode: $ownerCode,
                requestBuilt: true,
                realTicketNumbersPresent: true,
                errorCode: $exception->normalizedCode,
                errorMessage: 'Void ticket failed, admin review required.',
                preflightSummary: $preflightSummary,
            );
            $summary['diagnostic_path'] = $this->preflight->saveOperationDiagnostic(
                $connection->id,
                'void-ticket',
                $correlationId,
                $summary,
                $sanitizedRequestXml,
            );

            return $this->finalizeVoidRun($booking, $connection, $options, $summary);
        }
    }

    /**
     * @param  array<string, mixed>  $options
     * @param  array<string, mixed>  $summary
     * @return array<string, mixed>
     */
    private function finalizeVoidRun(
        Booking $booking,
        SupplierConnection $connection,
        array $options,
        array $summary,
    ): array {
        $summary = PiaNdcOperationLabels::applyToSummary($summary, 'void_ticket');
        $persist = (bool) ($options['persist'] ?? false);
        $dryRun = (bool) ($options['dry_run'] ?? false);
        if ($persist && ! $dryRun) {
            $actor = $options['actor'] ?? null;
            $this->operationAuditRecorder->recordVoidTicket(
                $booking->fresh() ?? $booking,
                $connection,
                $actor instanceof User ? $actor : null,
                $summary,
            );
        }

        return $summary;
    }

    /**
     * @param  array<string, mixed>  $result
     * @param  array<string, mixed>  $existingContext
     */
    private function persistVoidResult(Booking $booking, array $result, array $existingContext): void
    {
        $ticketNumbers = is_array($result['ticket_numbers'] ?? null)
            ? $result['ticket_numbers']
            : (is_array($existingContext['ticket_numbers'] ?? null) ? $existingContext['ticket_numbers'] : []);

        $context = array_merge($existingContext, array_filter([
            'void_status' => 'voided',
            'voided_at' => now()->toIso8601String(),
            'ticketing_status' => 'voided',
            'interpreted_status' => PiaNdcBookingStatusInterpreter::STATUS_OPTION_PNR_AFTER_VOID,
            'ticket_numbers' => $ticketNumbers !== [] ? $ticketNumbers : null,
            'ticket_doc_infos' => is_array($result['ticket_doc_infos'] ?? null) ? $result['ticket_doc_infos'] : null,
            'payment_time_limit' => $result['payment_time_limit'] ?? null,
            'order_status' => $result['order_status'] ?? null,
        ], fn ($value) => $value !== null && $value !== '' && $value !== []));
        $context['has_blocking_ticket_numbers'] = false;
        $context['option_pnr_released'] = false;
        unset($context['option_pnr_released_at'], $context['cancel_committed'], $context['cancellation_status']);

        $meta = is_array($booking->meta) ? $booking->meta : [];
        $meta['pia_ndc_context'] = $context;
        $booking->meta = $meta;

        $paymentRequiredBy = null;
        $pnrExpiresAt = null;
        $paymentTimeLimit = trim((string) ($result['payment_time_limit'] ?? ''));
        if ($paymentTimeLimit !== '') {
            try {
                $paymentRequiredBy = Carbon::parse($paymentTimeLimit);
                $pnrExpiresAt = $paymentRequiredBy->copy();
            } catch (\Throwable) {
                $paymentRequiredBy = null;
                $pnrExpiresAt = null;
            }
        }

        $booking->forceFill([
            'supplier_booking_status' => 'option_pnr_after_void',
            'payment_required_by' => $paymentRequiredBy,
            'pnr_expires_at' => $pnrExpiresAt,
            'meta' => $meta,
        ])->save();

        SupplierBooking::query()
            ->where('booking_id', $booking->id)
            ->where('provider', SupplierProvider::PiaNdc->value)
            ->update(['status' => 'pending_payment_or_ticketing']);
    }

    /**
     * @param  ?array<string, mixed>  $preflightSummary
     * @return array<string, mixed>
     */
    private function voidResult(
        bool $dryRun,
        bool $supplierCalled,
        Booking $booking,
        string $orderId,
        string $ownerCode,
        bool $requestBuilt,
        bool $realTicketNumbersPresent,
        ?string $voidStatus = null,
        ?string $errorCode = null,
        ?string $errorMessage = null,
        ?array $preflightSummary = null,
    ): array {
        return array_filter([
            'dry_run' => $dryRun,
            'supplier_called' => $supplierCalled,
            'operation' => PiaNdcOperationLabels::displayForConfigKey('void_ticket'),
            'booking_id' => $booking->id,
            'order_id' => $orderId,
            'owner_code' => $ownerCode,
            'real_ticket_numbers_present' => $realTicketNumbersPresent,
            'request_built' => $requestBuilt,
            'void_status' => $voidStatus,
            'order_status' => $preflightSummary['order_status'] ?? null,
            'error_code' => $errorCode,
            'error_message' => $errorMessage,
            'success' => $errorCode === null && ($dryRun ? $requestBuilt && $realTicketNumbersPresent : $voidStatus === 'voided'),
        ], fn ($value) => $value !== null);
    }
}
