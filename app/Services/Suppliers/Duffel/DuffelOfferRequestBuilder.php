<?php

namespace App\Services\Suppliers\Duffel;

use App\Data\FlightSearchRequestData;
use Carbon\Carbon;

class DuffelOfferRequestBuilder
{
    /** @var list<string> */
    private const CABIN_CLASSES = ['economy', 'premium_economy', 'business', 'first'];

    /**
     * @return array<string, mixed>
     */
    public function build(FlightSearchRequestData $request): array
    {
        $segments = $request->segments ?? null;
        if ($request->trip_type === 'multi_city' && is_array($segments) && count($segments) >= 2) {
            $slices = [];
            foreach ($segments as $segment) {
                $slices[] = [
                    'origin' => $this->normalizeIata((string) ($segment['origin'] ?? '')),
                    'destination' => $this->normalizeIata((string) ($segment['destination'] ?? '')),
                    'departure_date' => $this->normalizeDate((string) ($segment['departure_date'] ?? '')),
                ];
            }
            $data = [
                'slices' => $slices,
                'passengers' => $this->buildPassengers($request),
            ];
        } else {
            $data = [
                'slices' => [
                    [
                        'origin' => $this->normalizeIata($request->origin),
                        'destination' => $this->normalizeIata($request->destination),
                        'departure_date' => $this->normalizeDate($request->departure_date),
                    ],
                ],
                'passengers' => $this->buildPassengers($request),
            ];

            if ($request->return_date !== null && trim($request->return_date) !== '') {
                $data['slices'][] = [
                    'origin' => $this->normalizeIata($request->destination),
                    'destination' => $this->normalizeIata($request->origin),
                    'departure_date' => $this->normalizeDate($request->return_date),
                ];
            }
        }

        $cabin = $this->normalizeCabin($request->cabin);
        if ($cabin !== null) {
            $data['cabin_class'] = $cabin;
        }

        return ['data' => $data];
    }

    private function normalizeIata(string $code): string
    {
        $clean = strtoupper(preg_replace('/\s+/', '', $code) ?? '');

        return strlen($clean) <= 3 ? $clean : substr($clean, 0, 3);
    }

    private function normalizeDate(string $date): string
    {
        $trimmed = trim($date);
        if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $trimmed, $matches) === 1) {
            return $matches[1];
        }

        try {
            return Carbon::parse($trimmed)->format('Y-m-d');
        } catch (\Throwable) {
            return $trimmed;
        }
    }

    private function normalizeCabin(?string $cabin): ?string
    {
        $normalized = strtolower(trim((string) $cabin));

        return in_array($normalized, self::CABIN_CLASSES, true) ? $normalized : null;
    }

    /**
     * @return list<array<string, int|string>>
     */
    private function buildPassengers(FlightSearchRequestData $request): array
    {
        $adults = max(1, $request->adults);
        $children = max(0, $request->children);
        $infants = min(max(0, $request->infants), $adults);

        $passengers = [];
        for ($i = 0; $i < $adults; $i++) {
            $passengers[] = ['type' => 'adult'];
        }
        for ($i = 0; $i < $children; $i++) {
            $passengers[] = ['age' => 10];
        }
        for ($i = 0; $i < $infants; $i++) {
            $passengers[] = ['age' => 1];
        }

        return $passengers;
    }
}
