<?php

namespace App\Support\TravelData;

use App\Models\Airport;
use Illuminate\Support\Str;

/**
 * Resolves human-readable airport/city labels for IATA codes (display-only).
 *
 * Priority: config overrides → Airport.city → name-derived city → supplier fallback → code only.
 */
class AirportDisplayLabelResolver
{
    /**
     * @return array{label: string, city: ?string, code: ?string}
     */
    public static function resolve(?string $iata, ?Airport $airport = null, ?string $supplierCity = null): array
    {
        $code = self::normalizeIata($iata);
        $supplierCity = trim((string) $supplierCity);

        if ($code === null) {
            if ($supplierCity !== '') {
                return ['label' => $supplierCity, 'city' => $supplierCity, 'code' => null];
            }

            return ['label' => '', 'city' => null, 'code' => null];
        }

        $city = self::resolveCity($code, $airport, $supplierCity);
        $country = $airport !== null ? trim((string) ($airport->country ?? '')) : null;

        if ($city !== '') {
            return [
                'label' => "{$city} ({$code})",
                'city' => $city,
                'code' => $code,
            ];
        }

        if ($supplierCity !== '') {
            return ['label' => $supplierCity, 'city' => $supplierCity, 'code' => $code];
        }

        return ['label' => $code, 'city' => null, 'code' => $code];
    }

    /**
     * @return array{label: string, city: ?string, country: ?string}
     */
    public static function resolveEndpoint(?string $iata, ?Airport $airport = null, ?string $supplierCity = null): array
    {
        $resolved = self::resolve($iata, $airport, $supplierCity);
        $country = $airport !== null ? trim((string) ($airport->country ?? '')) : null;

        if ($resolved['label'] === '' && $iata === null) {
            return ['label' => '—', 'city' => null, 'country' => null];
        }

        if ($resolved['label'] === '' && self::normalizeIata($iata) !== null) {
            $code = self::normalizeIata($iata);

            return ['label' => $code ?? '—', 'city' => null, 'country' => $country !== '' ? $country : null];
        }

        return [
            'label' => $resolved['label'],
            'city' => $resolved['city'],
            'country' => $country !== '' ? $country : null,
        ];
    }

    public static function overrideCity(?string $iata): ?string
    {
        $code = self::normalizeIata($iata);
        if ($code === null) {
            return null;
        }

        $overrides = config('airports_overrides', []);
        $override = is_array($overrides[$code] ?? null) ? $overrides[$code] : null;
        if ($override === null) {
            return null;
        }

        $city = trim((string) ($override['city'] ?? ''));

        return $city !== '' ? $city : null;
    }

    private static function resolveCity(string $code, ?Airport $airport, string $supplierCity): string
    {
        $overrideCity = self::overrideCity($code);
        if ($overrideCity !== null) {
            return $overrideCity;
        }

        if ($airport !== null) {
            $dbCity = trim((string) ($airport->city ?? ''));
            if ($dbCity !== '') {
                return $dbCity;
            }

            $derived = self::deriveCityFromName(trim((string) ($airport->name ?? '')));
            if ($derived !== '') {
                return $derived;
            }
        }

        return $supplierCity;
    }

    private static function deriveCityFromName(string $name): string
    {
        if ($name === '') {
            return '';
        }

        $stripped = preg_replace('/\s+(International\s+)?Airport\s*$/iu', '', $name);
        $stripped = is_string($stripped) ? trim($stripped) : $name;

        if ($stripped === '') {
            return '';
        }

        if (str_contains($stripped, ' ')) {
            $parts = preg_split('/\s+/u', $stripped);
            if (is_array($parts) && count($parts) > 0) {
                return trim((string) end($parts));
            }
        }

        return $stripped;
    }

    private static function normalizeIata(?string $code): ?string
    {
        $code = Str::upper(trim((string) $code));

        return preg_match('/^[A-Z]{3}$/', $code) === 1 ? $code : null;
    }
}
