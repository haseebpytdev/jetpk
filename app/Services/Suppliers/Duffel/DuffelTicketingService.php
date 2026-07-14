<?php

namespace App\Services\Suppliers\Duffel;

use App\Data\TicketingResultData;
use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Models\BookingPassenger;
use App\Models\SupplierConnection;
use App\Models\User;
use App\Services\Suppliers\SupplierDiagnosticLogger;
use App\Support\Security\SensitiveDataRedactor;
use Throwable;

/**
 * Syncs e-ticket numbers from an existing Duffel order (Duffel issues tickets on order creation).
 */
class DuffelTicketingService
{
    public function __construct(
        private readonly DuffelClient $client,
        private readonly SupplierDiagnosticLogger $diagnosticLogger,
    ) {}

    public function issueTickets(Booking $booking, SupplierConnection $connection, User $actor): TicketingResultData
    {
        $orderId = trim((string) ($booking->supplier_reference ?? ''));
        if ($orderId === '') {
            return new TicketingResultData(
                success: false,
                status: 'failed',
                provider: SupplierProvider::Duffel->value,
                error_code: 'supplier_reference_missing',
                error_message: 'Duffel order reference is required before ticketing sync.',
            );
        }

        try {
            $response = $this->client->getOrder($orderId, $connection);
            $order = is_array($response['data'] ?? null) ? $response['data'] : $response;
            $documents = is_array($order['documents'] ?? null) ? $order['documents'] : [];
            $passengers = $booking->passengers()->orderBy('passenger_index')->get();
            $tickets = $this->extractTickets($documents, $passengers->all(), $booking);

            if ($tickets === []) {
                $this->diagnosticLogger->log(
                    connection: $connection,
                    action: 'ticketing',
                    status: 'failed',
                    safeMessage: 'Duffel order has no electronic ticket documents yet.',
                    meta: ['order_id' => $orderId],
                );

                return new TicketingResultData(
                    success: false,
                    status: 'failed',
                    provider: SupplierProvider::Duffel->value,
                    error_code: 'tickets_not_available',
                    error_message: 'Duffel order does not contain ticket documents yet.',
                    response_payload: SensitiveDataRedactor::redact($response),
                    safe_summary: ['order_id' => $orderId],
                );
            }

            $this->diagnosticLogger->log(
                connection: $connection,
                action: 'ticketing',
                status: 'success',
                safeMessage: 'Duffel tickets synced from order.',
                meta: ['order_id' => $orderId, 'ticket_count' => count($tickets)],
            );

            return new TicketingResultData(
                success: true,
                status: 'issued',
                provider: SupplierProvider::Duffel->value,
                tickets: $tickets,
                response_payload: SensitiveDataRedactor::redact($response),
                safe_summary: [
                    'order_id' => $orderId,
                    'ticket_count' => count($tickets),
                ],
            );
        } catch (DuffelProviderException $exception) {
            $this->diagnosticLogger->log(
                connection: $connection,
                action: 'ticketing',
                status: 'failed',
                safeMessage: 'Duffel ticket sync failed.',
                meta: ['order_id' => $orderId, 'error_code' => $exception->normalizedCode],
            );

            return new TicketingResultData(
                success: false,
                status: 'failed',
                provider: SupplierProvider::Duffel->value,
                error_code: $exception->normalizedCode,
                error_message: 'Duffel ticket sync failed.',
                safe_summary: ['order_id' => $orderId],
            );
        } catch (Throwable $exception) {
            report($exception);

            return new TicketingResultData(
                success: false,
                status: 'failed',
                provider: SupplierProvider::Duffel->value,
                error_code: 'unexpected_error',
                error_message: 'Duffel ticket sync failed.',
            );
        }
    }

    /**
     * @param  list<array<string, mixed>>  $documents
     * @param  list<BookingPassenger>  $passengers
     * @return list<array<string, mixed>>
     */
    private function extractTickets(array $documents, array $passengers, Booking $booking): array
    {
        $tickets = [];
        $ticketIndex = 0;

        foreach ($documents as $document) {
            if (! is_array($document)) {
                continue;
            }

            $type = strtolower(trim((string) ($document['type'] ?? '')));
            if ($type !== '' && $type !== 'electronic_ticket') {
                continue;
            }

            $ticketNumber = trim((string) ($document['unique_identifier'] ?? ''));
            if ($ticketNumber === '') {
                continue;
            }

            $passenger = $passengers[$ticketIndex] ?? null;
            $tickets[] = [
                'passenger_id' => $passenger?->id,
                'ticket_number' => $ticketNumber,
                'pnr' => $booking->pnr,
                'airline_code' => null,
                'issued_at' => now(),
                'passenger_name' => $passenger !== null
                    ? trim((string) $passenger->first_name.' '.(string) $passenger->last_name)
                    : null,
            ];
            $ticketIndex++;
        }

        return $tickets;
    }
}
