<?php

namespace App\Services\Ai\Lab;

use App\Services\FlightSearch\FlightSearchService;

/**
 * Confirmed read-only flight search for AI lab — no booking/hold mutations.
 */
final class FlightSearchReadOnlyExecutor
{
    public function __construct(
        private readonly FlightSearchService $flightSearch,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array{message: string, recommendations: list<array<string, mixed>>, search_record: array<string, mixed>}
     */
    public function execute(array $payload): array
    {
        $query = is_array($payload['query'] ?? null) ? $payload['query'] : $payload;

        $origin = strtoupper(trim((string) ($query['origin'] ?? '')));
        $destination = strtoupper(trim((string) ($query['destination'] ?? '')));
        $departure = (string) ($query['departure_date'] ?? $query['depart_date'] ?? '');
        $return = (string) ($query['return_date'] ?? '');
        $tripType = strtolower((string) ($query['trip_type'] ?? 'one_way'));
        $adults = max(1, (int) ($query['adults'] ?? 1));
        $children = max(0, (int) ($query['children'] ?? 0));
        $infants = max(0, (int) ($query['infants'] ?? 0));

        $criteria = [
            'origin' => $origin,
            'destination' => $destination,
            'depart_date' => $departure,
            'return_date' => $return !== '' ? $return : null,
            'adults' => $adults,
            'children' => $children,
            'infants' => $infants,
            'trip_type' => $tripType === 'return' ? 'return' : 'one_way',
        ];

        $searchRecord = [
            'tool' => 'FLIGHT_SEARCH_READ_ONLY',
            'origin' => $origin,
            'destination' => $destination,
            'departure_date' => $departure !== '' ? $departure : null,
            'return_date' => $return !== '' ? $return : null,
            'trip_type' => $criteria['trip_type'],
            'passengers' => [
                'adults' => $adults,
                'children' => $children,
                'infants' => $infants,
            ],
            'recorded_at' => now()->toIso8601String(),
            'live_supplier_called' => true,
            'mutation' => false,
        ];

        if ($origin === '' || $destination === '' || $departure === '') {
            return [
                'message' => 'I need origin, destination, and departure date before I can search.',
                'recommendations' => [],
                'search_record' => $searchRecord,
            ];
        }

        $result = $this->flightSearch->searchWithMeta($criteria, null, 'ai_assistant_read_only');
        $offers = is_array($result['offers'] ?? null) ? $result['offers'] : [];
        $warnings = is_array($result['warnings'] ?? null) ? $result['warnings'] : [];

        $recommendations = [];
        $limit = 5;
        foreach (array_slice($offers, 0, $limit) as $index => $offer) {
            if (! is_array($offer)) {
                continue;
            }
            $price = $offer['total_price'] ?? $offer['price'] ?? null;
            $currency = (string) ($offer['currency'] ?? 'PKR');
            $carrier = (string) ($offer['marketing_carrier'] ?? $offer['airline'] ?? '');
            $flightNo = (string) ($offer['flight_number'] ?? '');
            $title = trim("{$origin} → {$destination}".($carrier !== '' ? " · {$carrier}" : '').($flightNo !== '' ? " {$flightNo}" : ''));
            $params = http_build_query(array_filter([
                'from' => $origin,
                'to' => $destination,
                'depart_date' => $departure,
                'return_date' => $return,
                'adults' => $adults,
                'children' => $children,
                'infants' => $infants,
            ]));
            $recommendations[] = [
                'id' => 'ro-search-'.md5($origin.$destination.$departure.(string) $index),
                'title' => $title,
                'subtitle' => $price !== null ? number_format((float) $price).' '.$currency : 'Live search result',
                'results_url' => '/flights/results?'.$params,
                'price' => $price !== null ? (float) $price : null,
                'currency' => $currency,
                'labels' => ['read-only', 'live-search'],
            ];
        }

        $routeLabel = "{$origin} → {$destination}";
        if ($recommendations === []) {
            $message = "I searched live availability for {$routeLabel} but found no bookable offers right now.";
            if ($warnings !== []) {
                $message .= ' '.implode(' ', array_slice($warnings, 0, 2));
            }
        } else {
            $count = count($recommendations);
            $message = "Here are {$count} live option".($count === 1 ? '' : 's')." for {$routeLabel} (read-only — no hold or booking was made).";
        }

        $searchRecord['offer_count'] = count($offers);
        $searchRecord['returned_count'] = count($recommendations);

        return [
            'message' => $message,
            'recommendations' => $recommendations,
            'search_record' => $searchRecord,
        ];
    }
}
