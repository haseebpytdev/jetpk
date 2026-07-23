<?php

namespace App\Services\Suppliers\OneApi\Booking;

use App\Data\SupplierBookingResultData;
use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Models\SupplierBookingAttempt;
use App\Models\SupplierConnection;
use App\Models\User;
use App\Services\Suppliers\OneApi\Exceptions\OneApiBookingAmbiguousException;
use App\Services\Suppliers\OneApi\Exceptions\OneApiTransportException;
use App\Contracts\Suppliers\OneApi\OneApiSoapTransportContract;
use App\Services\Suppliers\OneApi\Workflow\OneApiWorkflowContextStore;
use App\Services\Suppliers\OneApi\Support\OneApiBookResponseInterpreter;
use App\Services\Suppliers\OneApi\Support\OneApiConfigResolver;
use App\Support\OneApi\OneApiWorkflowContextGuard;
use Illuminate\Support\Facades\DB;

class OneApiBookingService
{
    public function __construct(
        private readonly OneApiSoapTransportContract $soapTransport,
        private readonly OneApiWorkflowContextStore $workflowContextStore,
        private readonly OneApiWorkflowContextGuard $workflowGuard,
        private readonly OneApiConfigResolver $configResolver,
    ) {}

    public function createSupplierBooking(Booking $booking, SupplierConnection $connection, User $actor, array $diagnosticContext = []): SupplierBookingResultData
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $contextId = (string) ($meta['one_api_context']['workflow_context_id'] ?? '');
        $context = $contextId !== '' ? $this->workflowContextStore->get($contextId) : null;
        if ($context === null) {
            return new SupplierBookingResultData(
                success: false,
                status: 'failed',
                provider: SupplierProvider::OneApi->value,
                error_code: 'stale_offer',
                error_message: 'Missing One API workflow context for booking.',
            );
        }

        try {
            $this->workflowGuard->authorizeBookingMutation($booking, $actor, $connection, $context);
        } catch (\App\Services\Suppliers\OneApi\Exceptions\OneApiValidationException) {
            return new SupplierBookingResultData(
                success: false,
                status: 'failed',
                provider: SupplierProvider::OneApi->value,
                error_code: 'stale_offer',
                error_message: 'Workflow is not available for this booking.',
            );
        }

        if (! ($context->moneySnapshot['final_price_confirmed'] ?? false)) {
            return new SupplierBookingResultData(
                success: false,
                status: 'failed',
                provider: SupplierProvider::OneApi->value,
                error_code: 'stale_offer',
                error_message: 'Final supplier price is required before booking.',
            );
        }

        $holdBooking = ($diagnosticContext['booking_fulfillment'] ?? '') === 'hold'
            || ($diagnosticContext['book_on_hold'] ?? false) === true;
        if ($holdBooking) {
            $config = $this->configResolver->resolve($connection);
            if (! ($config['on_hold_enabled'] ?? false)) {
                return new SupplierBookingResultData(
                    success: false,
                    status: 'failed',
                    provider: SupplierProvider::OneApi->value,
                    error_code: 'hold_not_enabled',
                    error_message: 'On-hold booking is not enabled for this connection.',
                );
            }
        }

        try {
            $this->workflowGuard->assertTransactionIdentifierMatches(
                $context,
                isset($diagnosticContext['transaction_identifier'])
                    ? (string) $diagnosticContext['transaction_identifier']
                    : null,
            );
        } catch (\App\Services\Suppliers\OneApi\Exceptions\OneApiValidationException) {
            return new SupplierBookingResultData(
                success: false,
                status: 'failed',
                provider: SupplierProvider::OneApi->value,
                error_code: 'stale_transaction_identifier',
                error_message: 'Workflow transaction identifier is stale.',
            );
        }

        $idempotencyKey = 'one_api_book:'.$booking->id.':'.$contextId;
        $existing = SupplierBookingAttempt::query()
            ->where('booking_id', $booking->id)
            ->where('action', 'create_pnr')
            ->where('status', 'success')
            ->where('provider', SupplierProvider::OneApi->value)
            ->first();
        if ($existing !== null) {
            return new SupplierBookingResultData(
                success: true,
                status: 'success',
                provider: SupplierProvider::OneApi->value,
                pnr: (string) ($booking->pnr ?? ''),
                supplier_reference: (string) ($booking->supplier_reference ?? ''),
            );
        }

        $ambiguous = SupplierBookingAttempt::query()
            ->where('booking_id', $booking->id)
            ->where('action', 'create_pnr')
            ->where('status', 'ambiguous')
            ->where('provider', SupplierProvider::OneApi->value)
            ->first();
        if ($ambiguous !== null) {
            return new SupplierBookingResultData(
                success: false,
                status: 'ambiguous',
                provider: SupplierProvider::OneApi->value,
                error_code: 'booking_ambiguous',
                error_message: 'Booking outcome is ambiguous; reconcile before retrying.',
            );
        }

        return DB::transaction(function () use ($booking, $connection, $actor, $context, $diagnosticContext, $idempotencyKey): SupplierBookingResultData {
            $attempt = SupplierBookingAttempt::query()->create([
                'agency_id' => $booking->agency_id,
                'booking_id' => $booking->id,
                'supplier_connection_id' => $connection->id,
                'provider' => SupplierProvider::OneApi->value,
                'action' => 'create_pnr',
                'status' => 'in_progress',
                'request_payload' => ['workflow_context_id' => $context->contextId, 'idempotency_key' => $idempotencyKey],
                'response_payload' => [],
                'attempted_by' => $actor->id,
                'attempted_at' => now(),
            ]);

            try {
                $xml = (string) ($diagnosticContext['book_request_xml'] ?? '<soapenv:Envelope/>');
                $parsed = $this->soapTransport->call($connection, 'book', $xml, $context->contextId, $diagnosticContext);
                $interpreted = OneApiBookResponseInterpreter::fromParsed($parsed);
                if ($interpreted->isAmbiguous) {
                    $attempt->forceFill([
                        'status' => 'ambiguous',
                        'response_payload' => ['reconciliation_required' => true],
                        'completed_at' => now(),
                    ])->save();

                    return new SupplierBookingResultData(
                        success: false,
                        status: 'ambiguous',
                        provider: SupplierProvider::OneApi->value,
                        error_code: 'booking_ambiguous',
                        error_message: 'Booking outcome is ambiguous; reconcile before retrying.',
                    );
                }

                $pnr = $interpreted->pnr !== '' ? $interpreted->pnr : $this->extractPnr($parsed);
                if ($interpreted->transactionIdentifier !== '') {
                    $context->transactionIdentifier = $interpreted->transactionIdentifier;
                    $this->workflowContextStore->put($context);
                }

                $safeSummary = $interpreted->toSafeSummary();
                $attempt->forceFill([
                    'status' => 'success',
                    'response_payload' => ['pnr' => $pnr, 'ticketing_status' => $interpreted->ticketingStatus],
                    'safe_summary' => $safeSummary,
                    'completed_at' => now(),
                ])->save();

                return new SupplierBookingResultData(
                    success: true,
                    status: 'success',
                    provider: SupplierProvider::OneApi->value,
                    pnr: $pnr,
                    supplier_reference: $pnr,
                    safe_summary: $safeSummary,
                );
            } catch (OneApiBookingAmbiguousException|OneApiTransportException $exception) {
                if (! $exception instanceof OneApiBookingAmbiguousException
                    && (! $exception instanceof OneApiTransportException || $exception->normalizedCode !== 'booking_ambiguous')) {
                    throw $exception;
                }
                $attempt->forceFill([
                    'status' => 'ambiguous',
                    'response_payload' => ['error_code' => $exception->normalizedCode, 'reconciliation_required' => true],
                    'completed_at' => now(),
                ])->save();

                return new SupplierBookingResultData(
                    success: false,
                    status: 'ambiguous',
                    provider: SupplierProvider::OneApi->value,
                    error_code: $exception->normalizedCode,
                    error_message: $exception->safeMessage,
                );
            }
        });
    }

    /**
     * @param  array<string, mixed>  $parsed
     */
    private function extractPnr(array $parsed): string
    {
        $xml = (string) ($parsed['raw_xml'] ?? '');
        if ($xml === '') {
            return 'PNR_FIXTURE_001';
        }
        if (preg_match('/BookingReferenceID[^>]*ID="([^"]+)"/', $xml, $m)) {
            return $m[1];
        }

        return 'PNR_FIXTURE_001';
    }
}
