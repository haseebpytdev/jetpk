<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Airport;
use App\Support\FlightSearch\PublicFlightSearchSecurity;
use App\Support\TravelData\AirportDisplayLabelResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class AirportSearchController extends Controller
{
    /**
     * Primary autocomplete line, e.g. "Lahore (LHE) — Allama Iqbal International Airport".
     */
    protected static function formatAirportLabel(Airport $airport): string
    {
        $code = strtoupper(trim((string) ($airport->iata_code ?? '')));
        $name = self::resolvedAirportName($airport);
        $city = self::resolvedAirportCity($airport);
        $country = trim((string) ($airport->country ?? ''));

        if ($code !== '' && $name !== '' && $city !== '' && $country !== '') {
            return "{$code} — {$name}, {$city}, {$country}";
        }

        $resolved = AirportDisplayLabelResolver::resolve($code !== '' ? $code : null, $airport);
        $headline = $resolved['label'] !== '' ? $resolved['label'] : ($city !== '' ? $city : ($name !== '' ? $name : $code));

        if ($name !== '' && $name !== $city && $name !== $headline) {
            return $headline !== '' ? "{$headline} — {$name}" : $name;
        }

        return $headline !== '' ? $headline : $code;
    }

    protected static function resolvedAirportName(Airport $airport): string
    {
        $code = strtoupper(trim((string) ($airport->iata_code ?? '')));
        $overrides = config('airports_overrides', []);
        $override = is_array($overrides[$code] ?? null) ? $overrides[$code] : null;
        if ($override !== null) {
            $overrideName = trim((string) ($override['name'] ?? ''));
            if ($overrideName !== '') {
                return $overrideName;
            }
        }

        return trim((string) ($airport->name ?? ''));
    }

    protected static function resolvedAirportCity(Airport $airport): string
    {
        $code = strtoupper(trim((string) ($airport->iata_code ?? '')));
        $overrideCity = AirportDisplayLabelResolver::overrideCity($code !== '' ? $code : null);
        if ($overrideCity !== null) {
            return $overrideCity;
        }

        return trim((string) ($airport->city ?? ''));
    }

    /**
     * Secondary line — airport name when headline is city-centric, else country.
     */
    protected static function formatAirportDescription(Airport $airport): string
    {
        $city = trim((string) ($airport->city ?? ''));
        $name = trim((string) ($airport->name ?? ''));
        $country = trim((string) ($airport->country ?? ''));

        if ($name !== '' && $name !== $city) {
            return $country !== '' ? "{$name} · {$country}" : $name;
        }

        return $country;
    }

    public function __invoke(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        if ($q === '' || mb_strlen($q) < 2) {
            return response()->json([]);
        }

        $normalized = mb_strtolower(trim($q));
        $needle = Str::upper($normalized);
        $needleLike = $normalized.'%';
        $containsLike = '%'.$normalized.'%';
        $limit = max(1, min((int) $request->query('limit', 10), 15));
        $isCompact = Airport::queryTermIsCompactAirportToken($normalized);
        // v8: display city overrides (config/airports_overrides) in autocomplete labels.
        $cacheKey = 'airport_search_v8:'.md5($normalized.':'.$limit.':'.($isCompact ? '1' : '0'));

        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            return response()->json($cached);
        }

        $airports = Airport::query()
            ->withValidIata()
            ->where(function (Builder $q) use ($normalized): void {
                $q->commerciallySearchable();
                if (mb_strlen($normalized) === 3 && ctype_alpha($normalized)) {
                    $q->orWhereRaw('UPPER(TRIM(iata_code)) = ?', [Str::upper($normalized)]);
                }
            })
            ->when(
                $isCompact,
                static fn (Builder $q) => $q->searchCompactToken($normalized),
                static fn (Builder $q) => $q->search($normalized),
            )
            ->select([
                'iata_code',
                'icao_code',
                'name',
                'city',
                'country',
                'airport_type',
                'is_active',
                'priority_score',
                'route_count',
            ])
            ->selectRaw(
                'CASE
                    WHEN UPPER(COALESCE(iata_code, "")) = ? THEN 1000
                    WHEN UPPER(COALESCE(icao_code, "")) = ? THEN 900
                    WHEN LOWER(COALESCE(city, "")) LIKE ? THEN 800
                    WHEN LOWER(COALESCE(name, "")) LIKE ? THEN 700
                    WHEN LOWER(COALESCE(name, "")) LIKE ? THEN 600
                    WHEN LOWER(COALESCE(country, "")) LIKE ? THEN 500
                    WHEN LOWER(COALESCE(search_keywords, "")) LIKE ? THEN 450
                    WHEN LOWER(COALESCE(search_keywords, "")) LIKE ? THEN 400
                    ELSE 100
                END as rank_score',
                [
                    $needle,
                    $needle,
                    $needleLike,
                    $needleLike,
                    $containsLike,
                    $containsLike,
                    $needleLike,
                    $containsLike,
                ]
            )
            ->orderByDesc('rank_score')
            ->orderByDesc('is_active')
            ->orderByDesc('priority_score')
            ->orderByDesc('route_count')
            ->orderBy('city')
            ->limit($limit)
            ->get();

        $payload = $airports->map(static function (Airport $airport): array {
            $iata = strtoupper((string) $airport->iata_code);
            $city = self::resolvedAirportCity($airport);
            $name = self::resolvedAirportName($airport);
            $country = trim((string) ($airport->country ?? ''));

            return PublicFlightSearchSecurity::sanitizeAirportSuggestionRow([
                'iata' => $iata,
                'iata_code' => $iata, // backward-compatible key for older tests/consumers
                'name' => $name,
                'city' => $city,
                'country' => $country,
                'label' => self::formatAirportLabel($airport),
                'description' => self::formatAirportDescription($airport),
                'priority_score' => (int) ($airport->priority_score ?? 0),
                'airport_type' => $airport->airport_type ?? null,
            ]);
        })->filter(static fn (array $row): bool => ($row['iata'] ?? '') !== '')->values()->all();

        // Short TTL for misses so imports / is_active fixes show up quickly; longer for hits.
        Cache::put(
            $cacheKey,
            $payload,
            $payload === [] ? now()->addSeconds(90) : now()->addMinutes(20),
        );

        return response()->json($payload);
    }
}
