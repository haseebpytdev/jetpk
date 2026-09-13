<?php

namespace App\Services\Ai\Lab;

/**
 * SHADOW_FLIGHT_SEARCH — record intended query, return stub response only.
 */
final class ShadowFlightSearchRecorder
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array{message: string, recommendations: list<array<string, mixed>>, shadow_record: array<string, mixed>}
     */
    public function record(array $payload): array
    {
        $query = is_array($payload['query'] ?? null) ? $payload['query'] : $payload;

        $origin = (string) ($query['origin'] ?? '');
        $destination = (string) ($query['destination'] ?? '');
        $departure = (string) ($query['departure_date'] ?? $query['depart_date'] ?? '');
        $return = (string) ($query['return_date'] ?? '');
        $tripType = (string) ($query['trip_type'] ?? 'one_way');
        $adults = (int) ($query['adults'] ?? 1);
        $children = (int) ($query['children'] ?? 0);
        $infants = (int) ($query['infants'] ?? 0);

        $record = [
            'tool' => 'SHADOW_FLIGHT_SEARCH',
            'origin' => $origin,
            'destination' => $destination,
            'departure_date' => $departure !== '' ? $departure : null,
            'return_date' => $return !== '' ? $return : null,
            'trip_type' => $tripType,
            'passengers' => [
                'adults' => $adults,
                'children' => $children,
                'infants' => $infants,
            ],
            'recorded_at' => now()->toIso8601String(),
            'live_supplier_called' => false,
        ];

        $routeLabel = $origin !== '' && $destination !== '' ? "{$origin} → {$destination}" : 'your route';
        $message = "Shadow search recorded for {$routeLabel} — no live supplier call was made.";

        $recommendations = [];
        if ($origin !== '' && $destination !== '') {
            $params = http_build_query(array_filter([
                'origin' => $origin,
                'destination' => $destination,
                'depart_date' => $departure,
                'return_date' => $return,
                'adults' => $adults,
                'children' => $children,
                'infants' => $infants,
            ]));
            $recommendations[] = [
                'id' => 'shadow-'.md5($origin.$destination.$departure),
                'title' => "Shadow: {$origin} to {$destination}",
                'subtitle' => 'Stub result — live search disabled in shadow mode',
                'results_url' => '/flights/search?'.$params,
                'price' => null,
                'currency' => 'PKR',
                'labels' => ['shadow', 'no-live-supplier'],
            ];
        }

        return [
            'message' => $message,
            'recommendations' => $recommendations,
            'shadow_record' => $record,
        ];
    }
}
