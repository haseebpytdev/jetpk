<?php

namespace App\Services\Suppliers\Iati;

use App\Data\TicketingResultData;
use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Models\SupplierBooking;
use App\Models\TicketingAttempt;
use App\Models\User;
use App\Services\Suppliers\Iati\Exceptions\IatiException;
use App\Services\Suppliers\Iati\Exceptions\IatiTicketingException;
use App\Services\Suppliers\SupplierDiagnosticLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * IATI ticketing = confirm option via POST /option/{orderId}/book, or deferred direct POST /book.
 */
class IatiTicketingService
{
    public function __construct(
        private readonly IatiClient $client,
        private readonly IatiConfigResolver $configResolver,
        private readonly IatiPayloadBuilder $payloadBuilder,
        private readonly IatiResponseNormalizer $normalizer,
        private readonly IatiRetrieveService $retrieveService,
        private readonly SupplierDiagnosticLogger $diagnosticLogger,
    ) {}

    public function issueTickets(Booking $booking, SupplierBooking $supplierBooking, User $actor): TicketingResultData
    {
        if ($supplierBooking->provider !== SupplierProvider::Iati->value) {
            throw new IatiTicketingException('supplier_provider_mismatch', 422, 'Supplier provider mismatch for IATI ticketing.');
        }

        if ($this->hasSuccessfulTicketingAttempt($booking)) {
            return new TicketingResultData(
                success: false,
                status: 'failed',
                provider: SupplierProvider::Iati->value,
                error_code: 'duplicate_ticketing_guard',
                error_message: 'Tickets have already been issued for this IATI booking.',
            );
        }

        $connection = $supplierBooking->supplierConnection;
        if ($connection === null) {
            return new TicketingResultData(
                success: false,
                status: 'failed',
                provider: SupplierProvider::Iati->value,
                error_code: 'missing_connection',
                error_message: 'IATI supplier connection is missing.',
            );
        }

        $meta = is_array($booking->meta) ? $booking->meta : [];
        $iatiContext = is_array($meta['iati_context'] ?? null) ? $meta['iati_context'] : [];
        $mode = (string) ($iatiContext['mode'] ?? 'option');
        $orderId = trim((string) ($supplierBooking->supplier_reference ?? $supplierBooking->supplier_api_booking_id ?? $iatiContext['order_id'] ?? ''));

        try {
            if ($mode === 'book' && ($booking->ticketing_status ?? '') === 'ticketed') {
                $sync = $this->retrieveService->syncBooking($booking, $connection, $actor);

                return new TicketingResultData(
                    success: true,
                    status: 'issued',
                    provider: SupplierProvider::Iati->value,
                    tickets: $sync['ticket_numbers'] ?? [],
                    safe_summary: ['order_id' => $orderId, 'synced' => true],
                );
            }

            if ($mode === 'deferred_book' && $orderId === '') {
                return $this->issueDeferredDirectBook($booking, $supplierBooking, $connection, $actor, $iatiContext);
            }

            if ($orderId === '') {
                return new TicketingResultData(
                    success: false,
                    status: 'failed',
                    provider: SupplierProvider::Iati->value,
                    error_code: 'missing_order_id',
                    error_message: 'IATI order reference is missing.',
                );
            }

            $path = '/option/'.rawurlencode($orderId).'/book';
            $response = $this->client->post($connection, $path, [], [
                'request_context' => 'ticketing_option_book',
                'booking_id' => $booking->id,
                'user_id' => $actor->id,
            ]);

            $normalized = $this->normalizer->normalizeBookingResponse($response, 'book', $iatiContext);
            $this->persistTicketingResult($booking, $supplierBooking, $normalized, 'book');
            $booking->refresh();
            $sync = $this->retrieveService->syncBooking($booking, $connection, $actor, $normalized);

            $this->diagnosticLogger->log(
                connection: $connection,
                action: 'issue_tickets',
                status: 'success',
                safeMessage: 'IATI ticketing (book confirm) completed.',
                meta: ['booking_id' => $booking->id, 'order_id' => $orderId],
            );

            $ticketNumbers = $sync['ticket_numbers'] ?? [];

            return new TicketingResultData(
                success: true,
                status: 'issued',
                provider: SupplierProvider::Iati->value,
                tickets: array_map(fn (string $no) => ['ticket_number' => $no], $ticketNumbers),
                safe_summary: [
                    'order_id' => $orderId,
                    'pnr' => $sync['pnr'] ?? $normalized['pnr'],
                    'ticket_numbers' => $ticketNumbers,
                ],
            );
        } catch (IatiException $exception) {
            $this->diagnosticLogger->log(
                connection: $connection,
                action: 'issue_tickets',
                status: 'failed',
                safeMessage: $exception->safeMessage,
                meta: ['error_code' => $exception->normalizedCode, 'booking_id' => $booking->id],
            );

            return new TicketingResultData(
                success: false,
                status: 'failed',
                provider: SupplierProvider::Iati->value,
                error_code: $exception->normalizedCode,
                error_message: $exception->safeMessage,
                safe_summary: ['error_code' => $exception->normalizedCode],
            );
        } catch (\Throwable $exception) {
            Log::channel('iati')->error('iati.ticketing.unexpected', [
                'booking_id' => $booking->id,
                'exception' => $exception::class,
            ]);

            return new TicketingResultData(
                success: false,
                status: 'failed',
                provider: SupplierProvider::Iati->value,
                error_code: 'supplier_provider_error',
                error_message: 'IATI booking confirmation failed. Admin review required.',
            );
        }
    }

    /**
     * @param  array<string, mixed>  $iatiContext
     */
    protected function issueDeferredDirectBook(
        Booking $booking,
        SupplierBooking $supplierBooking,
        $connection,
        User $actor,
        array $iatiContext,
    ): TicketingResultData {
        $booking->loadMissing(['passengers', 'contact']);
        $config = $this->configResolver->resolve($connection);
        $fareDetailKey = trim((string) ($iatiContext['fare_detail_key'] ?? ''));
        $selectedOfferKey = trim((string) ($iatiContext['selected_offer_key'] ?? ''));

        if ($fareDetailKey === '' || $selectedOfferKey === '') {
            return new TicketingResultData(
                success: false,
                status: 'failed',
                provider: SupplierProvider::Iati->value,
                error_code: 'deferred_book_context_missing',
                error_message: 'Deferred IATI book context is incomplete. Admin review required.',
            );
        }

        $fareData = [
            'fare_detail_key' => $fareDetailKey,
            'provider_context' => $iatiContext,
        ];
        $passengers = $this->payloadBuilder->buildPassengersFromBooking($booking);
        $contact = $this->payloadBuilder->buildContactFromBooking($booking, $config['organization_id']);
        $payload = $this->payloadBuilder->buildBookPayload(
            $fareData,
            $iatiContext,
            $passengers,
            $contact,
            [$selectedOfferKey],
            true,
        );

        $response = $this->client->post($connection, '/book', $payload, [
            'request_context' => 'deferred_book',
            'booking_id' => $booking->id,
            'user_id' => $actor->id,
        ]);

        $normalized = $this->normalizer->normalizeBookingResponse($response, 'book', $iatiContext);
        $this->persistTicketingResult($booking, $supplierBooking, $normalized, 'book');
        $booking->refresh();
        $sync = $this->retrieveService->syncBooking($booking, $connection, $actor, $normalized);
        $orderId = trim((string) ($normalized['provider_booking_reference'] ?? $sync['order_id'] ?? ''));

        $this->diagnosticLogger->log(
            connection: $connection,
            action: 'issue_tickets',
            status: 'success',
            safeMessage: 'IATI deferred direct book completed.',
            meta: ['booking_id' => $booking->id, 'order_id' => $orderId],
        );

        $ticketNumbers = $sync['ticket_numbers'] ?? [];

        return new TicketingResultData(
            success: true,
            status: 'issued',
            provider: SupplierProvider::Iati->value,
            tickets: array_map(fn (string $no) => ['ticket_number' => $no], $ticketNumbers),
            safe_summary: [
                'mode' => 'deferred_book',
                'order_id' => $orderId,
                'pnr' => $sync['pnr'] ?? $normalized['pnr'],
                'ticket_numbers' => $ticketNumbers,
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $normalized
     */
    protected function persistTicketingResult(
        Booking $booking,
        SupplierBooking $supplierBooking,
        array $normalized,
        string $mode,
    ): void {
        DB::transaction(function () use ($booking, $supplierBooking, $normalized, $mode): void {
            $meta = is_array($booking->meta) ? $booking->meta : [];
            $iatiContext = array_merge(
                is_array($meta['iati_context'] ?? null) ? $meta['iati_context'] : [],
                is_array($normalized['provider_context'] ?? null) ? $normalized['provider_context'] : [],
                [
                    'mode' => $mode,
                    'order_id' => trim((string) ($normalized['provider_booking_reference'] ?? '')),
                    'ticketing_status' => 'ticketed',
                    'last_sync_at' => now()->toIso8601String(),
                ],
            );
            $meta['iati_context'] = $iatiContext;

            $booking->update([
                'supplier_reference' => $normalized['provider_booking_reference'] ?: $booking->supplier_reference,
                'supplier_api_booking_id' => $normalized['provider_booking_reference'] ?: $booking->supplier_api_booking_id,
                'pnr' => $normalized['pnr'] !== '' ? $normalized['pnr'] : $booking->pnr,
                'ticketing_status' => 'ticketed',
                'meta' => $meta,
            ]);

            $supplierBooking->update([
                'supplier_reference' => $normalized['provider_booking_reference'] ?: $supplierBooking->supplier_reference,
                'supplier_api_booking_id' => $normalized['provider_booking_reference'] ?: $supplierBooking->supplier_api_booking_id,
                'pnr' => $normalized['pnr'] !== '' ? $normalized['pnr'] : $supplierBooking->pnr,
                'status' => 'ticketed',
            ]);
        });
    }

    protected function hasSuccessfulTicketingAttempt(Booking $booking): bool
    {
        return TicketingAttempt::query()
            ->where('booking_id', $booking->id)
            ->where('provider', SupplierProvider::Iati->value)
            ->where('status', 'success')
            ->exists();
    }
}
