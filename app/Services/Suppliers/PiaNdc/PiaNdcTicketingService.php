<?php

namespace App\Services\Suppliers\PiaNdc;

use App\Data\TicketingResultData;
use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Models\SupplierBooking;
use App\Models\SupplierConnection;
use App\Models\TicketingAttempt;
use App\Models\User;
use App\Services\Suppliers\PiaNdc\Exceptions\PiaNdcException;
use App\Services\Suppliers\PiaNdc\Exceptions\PiaNdcTicketingException;
use App\Support\Bookings\PiaNdcBookingStatusInterpreter;
use App\Support\Bookings\PiaNdcOperationAuditRecorder;
use App\Support\Bookings\PiaNdcOperationLabels;
use App\Support\Security\SensitiveDataRedactor;
use Illuminate\Support\Facades\Log;

class PiaNdcTicketingService
{
    public const ISSUE_CONFIRM_PHRASE = 'ISSUE_PIA_NDC_TICKET';

    public function __construct(
        private readonly PiaNdcClient $client,
        private readonly PiaNdcConfigResolver $configResolver,
        private readonly PiaNdcXmlBuilder $xmlBuilder,
        private readonly PiaNdcResponseNormalizer $normalizer,
        private readonly PiaNdcTicketPreviewService $ticketPreviewService,
        private readonly PiaNdcOrderOperationPreflight $preflight,
        private readonly PiaNdcRetrieveService $retrieveService,
        private readonly PiaNdcCorrelationContext $correlationContext,
        private readonly PiaNdcOperationAuditRecorder $operationAuditRecorder,
    ) {}

    public function issueTickets(Booking $booking, SupplierConnection $connection, User $actor, array $options = []): TicketingResultData
    {
        $dryRun = (bool) ($options['dry_run'] ?? false);
        if ($dryRun) {
            $summary = $this->issueTicketsDryRun($booking, $connection, $actor, $options);

            return new TicketingResultData(
                success: (bool) ($summary['success'] ?? false),
                status: (string) ($summary['status'] ?? 'dry_run'),
                provider: SupplierProvider::PiaNdc->value,
                error_code: $summary['error_code'] ?? null,
                error_message: $summary['error_message'] ?? null,
                safe_summary: $summary,
            );
        }

        return $this->issueTicketsLive($booking, $connection, $actor, $options);
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function issueTicketsDryRun(Booking $booking, SupplierConnection $connection, ?User $actor, array $options = []): array
    {
        return $this->buildTicketingRun($booking, $connection, $actor, array_merge([
            'dry_run' => true,
            'persist' => false,
            'require_fresh_retrieve' => true,
        ], $options));
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function issueTicketsLive(Booking $booking, SupplierConnection $connection, User $actor, array $options = []): TicketingResultData
    {
        $attempt = null;
        if ((bool) ($options['record_ticketing_attempt'] ?? false)) {
            $booking->loadMissing('latestSupplierBooking');
            $attempt = TicketingAttempt::query()->create([
                'agency_id' => $booking->agency_id,
                'booking_id' => $booking->id,
                'supplier_booking_id' => $booking->latestSupplierBooking?->id,
                'provider' => SupplierProvider::PiaNdc->value,
                'status' => 'processing',
                'attempted_by' => $actor->id,
                'attempted_at' => now(),
                'safe_summary' => ['operation' => PiaNdcOperationLabels::DISPLAY_ORDER_CHANGE, 'cli' => true],
            ]);
        }

        $summary = $this->buildTicketingRun($booking, $connection, $actor, array_merge([
            'dry_run' => false,
            'persist' => true,
            'require_fresh_retrieve' => true,
        ], $options));

        if (($summary['supplier_called'] ?? false) !== true) {
            $this->finalizeAttempt($attempt, 'failed', $summary);

            return new TicketingResultData(
                success: false,
                status: (string) ($summary['status'] ?? 'failed'),
                provider: SupplierProvider::PiaNdc->value,
                error_code: $summary['error_code'] ?? 'ticketing_blocked',
                error_message: (string) ($summary['error_message'] ?? 'Ticketing blocked.'),
                safe_summary: $summary,
            );
        }

        if (($summary['ticketing_status'] ?? '') !== 'ticketed') {
            $this->finalizeAttempt($attempt, 'failed', $summary);

            return new TicketingResultData(
                success: false,
                status: (string) ($summary['ticketing_status'] ?? 'failed'),
                provider: SupplierProvider::PiaNdc->value,
                error_code: $summary['error_code'] ?? 'ticketing_failed',
                error_message: (string) ($summary['error_message'] ?? 'Ticketing failed, admin review required.'),
                safe_summary: $summary,
            );
        }

        $ticketNumbers = is_array($summary['ticket_numbers'] ?? null) ? $summary['ticket_numbers'] : [];
        $tickets = array_map(fn (string $num) => ['ticket_number' => $num], $ticketNumbers);
        $this->finalizeAttempt($attempt, 'success', $summary);

        return new TicketingResultData(
            success: true,
            status: 'ticketed',
            provider: SupplierProvider::PiaNdc->value,
            tickets: $tickets,
            safe_summary: $summary,
        );
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    private function buildTicketingRun(
        Booking $booking,
        SupplierConnection $connection,
        ?User $actor,
        array $options,
    ): array {
        $dryRun = (bool) ($options['dry_run'] ?? false);
        $persist = (bool) ($options['persist'] ?? ! $dryRun);
        $requireFreshRetrieve = (bool) ($options['require_fresh_retrieve'] ?? false);
        $forcePreview = (bool) ($options['force_preview'] ?? false);
        $correlationId = $this->correlationContext->newCorrelationId();

        if ($this->preflight->duplicateTicketGuard($booking)) {
            return $this->finalizeTicketingRun($booking, $connection, $actor, $options, $this->ticketingResult(
                dryRun: $dryRun,
                supplierCalled: false,
                booking: $booking,
                orderId: '',
                ownerCode: '',
                requestBuilt: false,
                duplicateTicketGuard: true,
                status: 'duplicate_ticketing_guard',
                errorCode: 'duplicate_ticketing_guard',
                errorMessage: 'Tickets have already been issued for this booking.',
            ));
        }

        try {
            $resolved = $this->preflight->assertOrderContext($booking, 'ticketing');
        } catch (PiaNdcTicketingException $exception) {
            return $this->finalizeTicketingRun($booking, $connection, $actor, $options, $this->ticketingResult(
                dryRun: $dryRun,
                supplierCalled: false,
                booking: $booking,
                orderId: '',
                ownerCode: '',
                requestBuilt: false,
                duplicateTicketGuard: false,
                status: 'failed',
                errorCode: $exception->normalizedCode,
                errorMessage: $exception->safeMessage,
            ));
        }

        $orderId = $resolved['order_id'];
        $ownerCode = $resolved['owner_code'];
        $context = $resolved['context'];
        $preflightSummary = null;
        $supplierCalled = false;

        if ($requireFreshRetrieve && ! $dryRun) {
            $retrieveResult = $this->preflight->freshRetrieve($booking, $connection);
            $supplierCalled = true;
            try {
                $preflightSummary = $this->preflight->assertRetrieveSucceeded($retrieveResult, 'ticketing');
            } catch (PiaNdcTicketingException $exception) {
                return $this->finalizeTicketingRun($booking, $connection, $actor, $options, $this->ticketingResult(
                    dryRun: false,
                    supplierCalled: true,
                    booking: $booking,
                    orderId: $orderId,
                    ownerCode: $ownerCode,
                    requestBuilt: false,
                    duplicateTicketGuard: false,
                    status: 'failed',
                    errorCode: $exception->normalizedCode,
                    errorMessage: $exception->safeMessage,
                    preflightSummary: $preflightSummary,
                ));
            }

            $booking = $booking->fresh() ?? $booking;
            $context = is_array($booking->meta['pia_ndc_context'] ?? null) ? $booking->meta['pia_ndc_context'] : $context;

            $interpreted = (string) ($context['interpreted_status'] ?? '');
            if (($context['option_pnr_released'] ?? false) === true
                || in_array($interpreted, [
                    PiaNdcBookingStatusInterpreter::STATUS_RELEASED,
                    PiaNdcBookingStatusInterpreter::STATUS_NO_ACTIVE_SEGMENTS,
                ], true)) {
                return $this->finalizeTicketingRun($booking, $connection, $actor, $options, $this->ticketingResult(
                    dryRun: $dryRun,
                    supplierCalled: true,
                    booking: $booking,
                    orderId: $orderId,
                    ownerCode: $ownerCode,
                    requestBuilt: false,
                    duplicateTicketGuard: false,
                    status: 'failed',
                    errorCode: 'option_pnr_released',
                    errorMessage: 'Ticketing refused: airline option PNR is no longer active.',
                    preflightSummary: $preflightSummary,
                ));
            }

            if ($this->preflight->duplicateTicketGuard($booking)
                || $this->preflight->realTicketNumbersPresent($preflightSummary)) {
                return $this->finalizeTicketingRun($booking, $connection, $actor, $options, $this->ticketingResult(
                    dryRun: false,
                    supplierCalled: true,
                    booking: $booking,
                    orderId: $orderId,
                    ownerCode: $ownerCode,
                    requestBuilt: false,
                    duplicateTicketGuard: true,
                    status: 'duplicate_ticketing_guard',
                    errorCode: 'duplicate_ticketing_guard',
                    errorMessage: 'Ticketing refused: ticket numbers already exist.',
                    preflightSummary: $preflightSummary,
                ));
            }

            if ($this->preflight->paymentTimeLimitExpired($preflightSummary['payment_time_limit'] ?? null)) {
                return $this->finalizeTicketingRun($booking, $connection, $actor, $options, $this->ticketingResult(
                    dryRun: false,
                    supplierCalled: true,
                    booking: $booking,
                    orderId: $orderId,
                    ownerCode: $ownerCode,
                    requestBuilt: false,
                    duplicateTicketGuard: false,
                    status: 'failed',
                    errorCode: 'payment_time_limit_expired',
                    errorMessage: 'Ticketing refused: payment time limit expired.',
                    preflightSummary: $preflightSummary,
                ));
            }
        }

        try {
            $ticketingConfig = $this->configResolver->resolveForTicketing($connection);
        } catch (\Throwable $exception) {
            return $this->finalizeTicketingRun($booking, $connection, $actor, $options, $this->ticketingResult(
                dryRun: $dryRun,
                supplierCalled: $supplierCalled,
                booking: $booking,
                orderId: $orderId,
                ownerCode: $ownerCode,
                requestBuilt: false,
                duplicateTicketGuard: false,
                paymentType: null,
                mcoInvoiceConfigured: false,
                status: 'failed',
                errorCode: 'missing_mco_invoice',
                errorMessage: 'Ticketing failed, admin review required.',
                preflightSummary: $preflightSummary,
            ));
        }

        $paymentType = strtoupper((string) ($ticketingConfig['payment_type'] ?? 'MCO'));
        $mcoInvoiceConfigured = trim((string) ($ticketingConfig['mco_invoice_number'] ?? '')) !== '';

        $preview = is_array($context['ticket_preview'] ?? null) && ! $forcePreview
            ? $context['ticket_preview']
            : null;

        if ($preview === null && ! $dryRun) {
            $preview = $this->ticketPreviewService->preview($booking, $connection);
        } elseif ($preview === null && $dryRun) {
            $preview = ['amount' => 0.0, 'currency' => (string) ($ticketingConfig['currency'] ?? 'PKR')];
        }

        $payment = [
            'amount' => (float) ($preview['amount'] ?? 0),
            'currency' => (string) ($preview['currency'] ?? $ticketingConfig['currency']),
            'ticket_id' => (string) ($context['mco_ticket_id'] ?? $ticketingConfig['mco_invoice_number']),
            'type_code' => $paymentType,
        ];

        $requestXml = $this->xmlBuilder->buildTicketingOrderChangeRequest($ticketingConfig, $orderId, $ownerCode, $payment);
        $sanitizedRequestXml = $this->client->sanitizeXmlForDiagnostics($requestXml);

        if ($dryRun) {
            $summary = $this->ticketingResult(
                dryRun: true,
                supplierCalled: false,
                booking: $booking,
                orderId: $orderId,
                ownerCode: $ownerCode,
                requestBuilt: true,
                duplicateTicketGuard: false,
                paymentType: $paymentType,
                mcoInvoiceConfigured: $mcoInvoiceConfigured,
                status: 'dry_run',
                preflightSummary: $preflightSummary,
            );
            $summary['diagnostic_path'] = $this->preflight->saveOperationDiagnostic(
                $connection->id,
                'ticketing',
                $correlationId,
                $summary,
                $sanitizedRequestXml,
            );

            return $this->finalizeTicketingRun($booking, $connection, $actor, $options, $summary);
        }

        try {
            $supplierCalled = true;
            $response = $this->client->call($connection, 'order_change', $requestXml, [
                'booking_id' => $booking->id,
                'request_context' => 'ticketing',
                'correlation_id' => $correlationId,
            ]);
            $normalized = $this->normalizer->normalizeTicketingResponse($response, $context);

            if ($persist && ($normalized['ticketing_status'] ?? '') === 'ticketed') {
                $this->persistTicketing($booking, $normalized);
                $this->retrieveService->retrieveAndSync($booking->fresh() ?? $booking, $connection);
                $booking = $booking->fresh() ?? $booking;
                $context = is_array($booking->meta['pia_ndc_context'] ?? null) ? $booking->meta['pia_ndc_context'] : $context;
                $normalized['ticket_numbers'] = is_array($context['ticket_numbers'] ?? null)
                    ? $context['ticket_numbers']
                    : ($normalized['ticket_numbers'] ?? []);
            }

            $summary = $this->ticketingResult(
                dryRun: false,
                supplierCalled: true,
                booking: $booking,
                orderId: $orderId,
                ownerCode: $ownerCode,
                requestBuilt: true,
                duplicateTicketGuard: false,
                paymentType: $paymentType,
                mcoInvoiceConfigured: $mcoInvoiceConfigured,
                status: (string) ($normalized['ticketing_status'] ?? 'failed'),
                ticketNumbers: is_array($normalized['ticket_numbers'] ?? null) ? $normalized['ticket_numbers'] : [],
                preflightSummary: $preflightSummary,
            );
            $summary['diagnostic_path'] = $this->preflight->saveOperationDiagnostic(
                $connection->id,
                'ticketing',
                $correlationId,
                $summary,
                $sanitizedRequestXml,
            );

            return $this->finalizeTicketingRun($booking, $connection, $actor, $options, $summary);
        } catch (PiaNdcException $exception) {
            $summary = $this->ticketingResult(
                dryRun: false,
                supplierCalled: true,
                booking: $booking,
                orderId: $orderId,
                ownerCode: $ownerCode,
                requestBuilt: true,
                duplicateTicketGuard: false,
                paymentType: $paymentType,
                mcoInvoiceConfigured: $mcoInvoiceConfigured,
                status: 'failed',
                errorCode: $exception->normalizedCode,
                errorMessage: $exception->safeMessage,
                preflightSummary: $preflightSummary,
            );
            $summary['diagnostic_path'] = $this->preflight->saveOperationDiagnostic(
                $connection->id,
                'ticketing',
                $correlationId,
                $summary,
                $sanitizedRequestXml,
            );

            return $this->finalizeTicketingRun($booking, $connection, $actor, $options, $summary);
        } catch (\Throwable $exception) {
            Log::channel('pia-ndc')->warning('pia_ndc.ticketing.unexpected', [
                'booking_id' => $booking->id,
                'exception' => $exception::class,
            ]);

            throw new PiaNdcTicketingException(
                'ticketing_unexpected',
                500,
                'Ticketing failed, admin review required.',
                ['booking_id' => $booking->id],
                $exception,
            );
        }
    }

    /**
     * @param  array<string, mixed>  $normalized
     */
    private function persistTicketing(Booking $booking, array $normalized): void
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $context = array_merge(
            is_array($meta['pia_ndc_context'] ?? null) ? $meta['pia_ndc_context'] : [],
            is_array($normalized['provider_context'] ?? null) ? $normalized['provider_context'] : [],
        );
        if (($normalized['ticketing_status'] ?? '') === 'ticketed') {
            $context['ticketing_status'] = 'ticketed';
        }
        $meta['pia_ndc_context'] = $context;
        $booking->meta = $meta;
        $booking->save();

        SupplierBooking::query()
            ->where('booking_id', $booking->id)
            ->where('provider', SupplierProvider::PiaNdc->value)
            ->update(['status' => ($normalized['ticketing_status'] ?? '') === 'ticketed' ? 'ticketed' : 'confirmed']);
    }

    /**
     * @param  ?array<string, mixed>  $preflightSummary
     * @param  list<string>  $ticketNumbers
     * @return array<string, mixed>
     */
    private function ticketingResult(
        bool $dryRun,
        bool $supplierCalled,
        Booking $booking,
        string $orderId,
        string $ownerCode,
        bool $requestBuilt,
        bool $duplicateTicketGuard,
        ?string $paymentType = null,
        ?bool $mcoInvoiceConfigured = null,
        string $status = 'dry_run',
        ?string $errorCode = null,
        ?string $errorMessage = null,
        array $ticketNumbers = [],
        ?array $preflightSummary = null,
    ): array {
        return array_filter([
            'dry_run' => $dryRun,
            'supplier_called' => $supplierCalled,
            'operation' => PiaNdcOperationLabels::displayForConfigKey('order_change'),
            'booking_id' => $booking->id,
            'order_id' => $orderId,
            'owner_code' => $ownerCode,
            'duplicate_ticket_guard' => $duplicateTicketGuard,
            'payment_type' => $paymentType,
            'mco_invoice_configured' => $mcoInvoiceConfigured,
            'request_built' => $requestBuilt,
            'status' => $status,
            'ticketing_status' => $status === 'ticketed' ? 'ticketed' : null,
            'ticket_numbers' => $ticketNumbers !== [] ? $ticketNumbers : null,
            'order_status' => $preflightSummary['order_status'] ?? null,
            'payment_time_limit' => $preflightSummary['payment_time_limit'] ?? null,
            'preflight_retrieve_called' => $preflightSummary !== null ? true : null,
            'error_code' => $errorCode,
            'error_message' => $errorMessage,
            'success' => $errorCode === null && ($dryRun ? $requestBuilt : $status === 'ticketed'),
        ], fn ($value) => $value !== null);
    }

    /**
     * @param  array<string, mixed>  $options
     * @param  array<string, mixed>  $summary
     * @return array<string, mixed>
     */
    private function finalizeTicketingRun(
        Booking $booking,
        SupplierConnection $connection,
        ?User $actor,
        array $options,
        array $summary,
    ): array {
        $summary = PiaNdcOperationLabels::applyToSummary($summary, 'order_change');
        $persist = (bool) ($options['persist'] ?? false);
        $dryRun = (bool) ($options['dry_run'] ?? false);
        if ($persist && ! $dryRun) {
            $this->operationAuditRecorder->recordTicketing(
                $booking->fresh() ?? $booking,
                $connection,
                $actor,
                $summary,
            );
        }

        return $summary;
    }

    /**
     * @param  array<string, mixed>  $summary
     */
    private function finalizeAttempt(?TicketingAttempt $attempt, string $status, array $summary): void
    {
        if ($attempt === null) {
            return;
        }

        $attempt->forceFill([
            'status' => $status,
            'safe_summary' => SensitiveDataRedactor::redact($summary),
            'error_code' => $summary['error_code'] ?? null,
            'error_message' => $summary['error_message'] ?? null,
            'completed_at' => now(),
        ])->save();
    }
}
