<?php

namespace App\Services\Suppliers\Sabre\Ticketing;

/**
 * Parse Enhanced Air Ticket REST response into safe ticket records.
 */
final class SabreGdsTicketingResponseParser
{
    /**
     * @param  array<string, mixed>  $json
     * @return array{
     *     success: bool,
     *     status: string,
     *     tickets: list<array<string, mixed>>,
     *     safe_summary: array<string, mixed>
     * }
     */
    public function parse(array $json, string $pnr): array
    {
        $appResults = data_get($json, 'AirTicketRS.ApplicationResults');
        $status = is_array($appResults) ? strtoupper(trim((string) ($appResults['status'] ?? ''))) : '';

        if ($status !== 'COMPLETE') {
            $normalizer = new SabreGdsTicketingSafeErrorNormalizer;
            $error = $normalizer->fromHttpResponse(200, $json);

            return [
                'success' => false,
                'status' => 'failed',
                'tickets' => [],
                'error_code' => $error['error_code'],
                'error_message' => $error['error_message'],
                'safe_summary' => $error['safe_summary'],
            ];
        }

        $summaries = data_get($json, 'AirTicketRS.Ticket.Summary', []);
        if (! is_array($summaries)) {
            $summaries = [];
        }
        if ($summaries !== [] && ! isset($summaries[0])) {
            $summaries = [$summaries];
        }

        $tickets = [];
        foreach ($summaries as $summary) {
            if (! is_array($summary)) {
                continue;
            }
            $ticketNumber = trim((string) ($summary['DocumentNumber'] ?? $summary['documentNumber'] ?? ''));
            if ($ticketNumber === '') {
                continue;
            }
            $firstName = trim((string) ($summary['FirstName'] ?? $summary['firstName'] ?? ''));
            $lastName = trim((string) ($summary['LastName'] ?? $summary['lastName'] ?? ''));
            $passengerName = trim($firstName.' '.$lastName);

            $tickets[] = array_filter([
                'ticket_number' => $ticketNumber,
                'pnr' => $pnr !== '' ? $pnr : null,
                'passenger_name' => $passengerName !== '' ? $passengerName : null,
                'issued_at' => now()->toIso8601String(),
            ], fn ($v) => $v !== null && $v !== '');
        }

        if ($tickets === []) {
            $normalizer = new SabreGdsTicketingSafeErrorNormalizer;
            $error = $normalizer->fromParseFailure($json, 'Sabre ticketing completed but no ticket numbers were returned.');

            return [
                'success' => false,
                'status' => 'failed',
                'tickets' => [],
                'error_code' => $error['error_code'],
                'error_message' => $error['error_message'],
                'safe_summary' => array_merge($error['safe_summary'], [
                    'application_status' => $status,
                    'ticket_count' => 0,
                ]),
            ];
        }

        return [
            'success' => true,
            'status' => 'ticketed',
            'tickets' => $tickets,
            'safe_summary' => [
                'application_status' => $status,
                'ticket_count' => count($tickets),
            ],
        ];
    }
}
