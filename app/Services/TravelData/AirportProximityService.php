<?php

namespace App\Services\TravelData;

use App\Models\Airport;
use Illuminate\Support\Facades\Cache;

/**
 * Resolves commercially searchable departure airports near a given IATA code.
 */
class AirportProximityService
{
    /**
     * @return list<string> Uppercase IATA codes excluding the requested origin.
     */
    public function getNearbyDepartureAirports(string $iata): array
    {
        $origin = strtoupper(trim($iata));
        if ($origin === '' || ! (bool) config('ota-flights.nearby_departure_airports.enabled', true)) {
            return [];
        }

        $cacheKey = 'ota.airport_proximity.'.$origin;
        $ttl = max(60, (int) config('ota-flights.nearby_departure_airports.cache_ttl_seconds', 3600));

        return Cache::remember($cacheKey, $ttl, function () use ($origin): array {
            return $this->resolveNearbyDepartureAirports($origin);
        });
    }

    /**
     * @return list<string>
     */
    protected function resolveNearbyDepartureAirports(string $origin): array
    {
        $anchor = Airport::query()
            ->active()
            ->withValidIata()
            ->whereRaw('UPPER(TRIM(iata_code)) = ?', [$origin])
            ->first();

        if ($anchor === null || $anchor->latitude === null || $anchor->longitude === null) {
            return [];
        }

        $maxRadiusKm = max(1, (float) config('ota-flights.nearby_departure_airports.max_radius_km', 350));
        $maxAirports = max(1, (int) config('ota-flights.nearby_departure_airports.max_airports', 4));
        $sameCountryOnly = (bool) config('ota-flights.nearby_departure_airports.same_country_only', true);

        $query = Airport::query()
            ->active()
            ->withValidIata()
            ->commerciallySearchable()
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->whereRaw('UPPER(TRIM(iata_code)) != ?', [$origin]);

        if ($sameCountryOnly && trim((string) $anchor->country_code) !== '') {
            $query->whereRaw('UPPER(TRIM(COALESCE(country_code, ""))) = ?', [strtoupper(trim((string) $anchor->country_code))]);
        }

        $candidates = $query->get(['iata_code', 'latitude', 'longitude', 'priority_score']);
        if ($candidates->isEmpty()) {
            return [];
        }

        $ranked = [];
        foreach ($candidates as $candidate) {
            $code = strtoupper(trim((string) $candidate->iata_code));
            if ($code === '' || $code === $origin) {
                continue;
            }

            $distanceKm = $this->haversineKm(
                (float) $anchor->latitude,
                (float) $anchor->longitude,
                (float) $candidate->latitude,
                (float) $candidate->longitude,
            );

            if ($distanceKm > $maxRadiusKm) {
                continue;
            }

            $ranked[] = [
                'iata' => $code,
                'distance_km' => $distanceKm,
                'priority_score' => (int) ($candidate->priority_score ?? 0),
            ];
        }

        if ($ranked === []) {
            return [];
        }

        usort($ranked, static function (array $a, array $b): int {
            $distanceCompare = $a['distance_km'] <=> $b['distance_km'];
            if ($distanceCompare !== 0) {
                return $distanceCompare;
            }

            return $b['priority_score'] <=> $a['priority_score'];
        });

        $out = [];
        foreach ($ranked as $row) {
            if (count($out) >= $maxAirports) {
                break;
            }
            $out[] = $row['iata'];
        }

        return $out;
    }

    protected function haversineKm(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadiusKm = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;

        return $earthRadiusKm * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
