<?php

namespace App\Services\Suppliers\Sabre\Ticketing;

use App\Models\Booking;

/**
 * Build Enhanced Air Ticket REST AirTicketRQ from booking context (Binham/IATI reference aligned).
 */
final class SabreGdsTicketingRequestBuilder
{
    /**
     * @return array{payload: array<string, mixed>, missing: list<string>}
     */
    public function build(Booking $booking): array
    {
        $pnr = trim((string) ($booking->pnr ?? $booking->supplier_reference ?? ''));
        $missing = [];
        if ($pnr === '') {
            $missing[] = 'pnr';
        }

        $countryCode = strtoupper(trim((string) config('suppliers.sabre.ticketing_printer_country_code', 'PK')));
        $lniata = trim((string) config('suppliers.sabre.ticketing_printer_lniata', ''));
        if ($lniata === '') {
            $missing[] = 'printer_lniata';
        }

        $priceQuoteRecords = $this->resolvePriceQuoteRecords($booking);
        if ($priceQuoteRecords === []) {
            $missing[] = 'price_quote_record';
        }

        $receivedFrom = trim((string) config('suppliers.sabre.ticketing_received_from', 'OTA'));
        if ($receivedFrom === '') {
            $receivedFrom = 'OTA';
        }

        $payload = [
            'AirTicketRQ' => array_filter([
                'DesignatePrinter' => [
                    'Printers' => [
                        'Ticket' => array_filter([
                            'CountryCode' => $countryCode,
                            'LNIATA' => $lniata !== '' ? $lniata : null,
                        ]),
                    ],
                ],
                'Itinerary' => ['ID' => $pnr],
                'Ticketing' => [
                    [
                        'PricingQualifiers' => [
                            'PriceQuote' => [
                                [
                                    'Record' => $priceQuoteRecords,
                                ],
                            ],
                        ],
                    ],
                ],
                'PostProcessing' => [
                    'acceptPriceChanges' => true,
                    'actionOnPQExpired' => 'R',
                    'EndTransaction' => [
                        'Source' => ['ReceivedFrom' => $receivedFrom],
                    ],
                ],
            ]),
        ];

        return ['payload' => $payload, 'missing' => $missing];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function resolvePriceQuoteRecords(Booking $booking): array
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $records = [];

        $fetchResponse = data_get($meta, 'sabre_fetch_response.fares');
        if (is_array($fetchResponse)) {
            foreach ($fetchResponse as $fare) {
                if (! is_array($fare)) {
                    continue;
                }
                $recordId = $fare['recordId'] ?? $fare['record_id'] ?? $fare['Number'] ?? null;
                if ($recordId === null || $recordId === '') {
                    continue;
                }
                $records[] = ['Number' => (int) $recordId, 'Reissue' => false];
            }
        }

        if ($records === []) {
            $pqMeta = data_get($meta, 'sabre_pricing_context.price_quote_records');
            if (is_array($pqMeta)) {
                foreach ($pqMeta as $pq) {
                    if (! is_array($pq)) {
                        continue;
                    }
                    $num = $pq['number'] ?? $pq['Number'] ?? null;
                    if ($num === null || $num === '') {
                        continue;
                    }
                    $records[] = ['Number' => (int) $num, 'Reissue' => false];
                }
            }
        }

        if ($records === []) {
            $records[] = ['Number' => 1, 'Reissue' => false];
        }

        return $records;
    }
}
