<?php

namespace App\Services\Suppliers\EjmiralAerost;

use App\Models\SupplierConnection;
use App\Services\Suppliers\EjmiralAerost\Exceptions\EjmiralAerostValidationException;

class EjmiralAerostPnrService
{
    public function __construct(
        private readonly EjmiralAerostClient $client,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function pnrInfo(SupplierConnection $connection, string $pnr): array
    {
        $pnr = strtoupper(trim($pnr));
        if ($pnr === '') {
            throw new EjmiralAerostValidationException('validation_error', 422, 'PNR is required.');
        }

        $response = $this->client->post($connection, 'res/pnr_info/'.$pnr, [], ['operation' => 'pnr_info']);
        $body = $response['body'];

        return [
            'pnr_present' => true,
            'booking_status' => (string) ($body['booking_status'] ?? $body['status'] ?? ''),
            'pax_count' => (int) ($body['pax_count'] ?? count($body['passengers'] ?? [])),
            'time_limit' => (string) ($body['time_limit'] ?? $body['ticket_time_limit'] ?? ''),
            'route' => (string) ($body['route'] ?? $body['itinerary_summary'] ?? ''),
            'ticket_ref_present' => trim((string) ($body['ticket_ref'] ?? $body['ticketRef'] ?? '')) !== '',
            'invoice_present' => trim((string) ($body['invoice'] ?? $body['invoice_number'] ?? '')) !== '',
        ];
    }
}
