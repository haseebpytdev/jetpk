<?php

namespace App\Services\Suppliers\EjmiralAerost;

use App\Models\SupplierConnection;
use App\Services\Suppliers\EjmiralAerost\Exceptions\EjmiralAerostValidationException;

/**
 * AEROST ticketing: POST /api/ota/v1/res/ticketing
 * UAT only; max one attempt; no blind retry.
 */
class EjmiralAerostTicketingService
{
    public function __construct(
        private readonly EjmiralAerostConfigResolver $configResolver,
        private readonly EjmiralAerostClient $client,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function ticket(SupplierConnection $connection, string $ticketRef, float $cashAmount): array
    {
        $config = $this->configResolver->resolve($connection);
        if (! $config['live_ticketing_enabled'] && ! app()->runningUnitTests()) {
            throw new EjmiralAerostValidationException('live_ticketing_disabled', 422, 'EJMIRAL AEROST live ticketing is disabled.');
        }

        $ticketRef = trim($ticketRef);
        if ($ticketRef === '') {
            throw new EjmiralAerostValidationException('validation_error', 422, 'ticket_ref is required.');
        }
        if ($cashAmount <= 0) {
            throw new EjmiralAerostValidationException('validation_error', 422, 'cash_amount must be the full reservation amount.');
        }

        $response = $this->client->post($connection, 'res/ticketing', [
            'ticket_ref' => $ticketRef,
            'payment_method' => 'CASH',
            'cash_amount' => $cashAmount,
        ], ['operation' => 'ticketing']);

        $body = $response['body'];

        return [
            'booking_status' => (string) ($body['booking_status'] ?? $body['status'] ?? ''),
            'pnr_present' => trim((string) ($body['PNR'] ?? $body['pnr'] ?? '')) !== '',
            'invoice_present' => trim((string) ($body['invoice'] ?? $body['invoice_number'] ?? '')) !== '',
            'success' => in_array(strtolower((string) ($body['booking_status'] ?? $body['status'] ?? '')), ['ticketed', 'issued', 'success', 'ok'], true)
                || trim((string) ($body['invoice'] ?? $body['invoice_number'] ?? '')) !== '',
        ];
    }
}
