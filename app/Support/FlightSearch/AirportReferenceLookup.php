<?php

namespace App\Support\FlightSearch;

use App\Models\Airport;
use Illuminate\Support\Facades\Cache;

/**
 * Stable airport reference lookups with per-request memo + short TTL cache.
 * Never caches auth, pricing authority, or live offers.
 */
final class AirportReferenceLookup
{
    private const CACHE_PREFIX = 'ota:airport_ref:v1:';

    private const TTL_SECONDS = 3600;

    /** @var array<string, array{city: string, country: string}|null> */
    private static array $requestMemo = [];

    /**
     * @return array{city: string, country: string}|null
     */
    public static function cityCountry(string $iata): ?array
    {
        $code = strtoupper(trim($iata));
        if ($code === '' || strlen($code) !== 3) {
            return null;
        }

        if (array_key_exists($code, self::$requestMemo)) {
            return self::$requestMemo[$code];
        }

        $cached = Cache::remember(self::CACHE_PREFIX.$code, self::TTL_SECONDS, static function () use ($code): ?array {
            $row = Airport::query()
                ->where('iata_code', $code)
                ->first(['iata_code', 'city', 'country']);
            if ($row === null) {
                return null;
            }

            return [
                'city' => trim((string) $row->city),
                'country' => trim((string) $row->country),
            ];
        });

        return self::$requestMemo[$code] = is_array($cached) ? $cached : null;
    }

    public static function cityCountryLine(string $iata): string
    {
        $row = self::cityCountry($iata);
        if ($row === null) {
            return '';
        }

        $city = $row['city'];
        $country = $row['country'];
        if ($city !== '' && $country !== '') {
            return $city.', '.$country;
        }

        return $city !== '' ? $city : $country;
    }

    /** @internal tests */
    public static function flushRequestMemo(): void
    {
        self::$requestMemo = [];
    }
}
