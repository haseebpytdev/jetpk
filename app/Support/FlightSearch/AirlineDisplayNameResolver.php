<?php

namespace App\Support\FlightSearch;

use App\Models\Airline;
use App\Services\TravelData\AirlineCanonicalResolver;
use Illuminate\Support\Facades\DB;

/**
 * Resolves IATA/ICAO codes to customer-facing airline names for flight result UI.
 */
class AirlineDisplayNameResolver
{
    /**
     * @param  list<array<string, mixed>>  $offers
     * @return list<string>
     */
    public static function collectCodesFromOffers(array $offers): array
    {
        $codes = [];
        foreach ($offers as $offer) {
            if (! is_array($offer)) {
                continue;
            }
            foreach ([
                $offer['airline_code'] ?? null,
                $offer['carrier_code'] ?? null,
                $offer['primary_display_carrier'] ?? null,
                $offer['validating_carrier'] ?? null,
            ] as $raw) {
                $c = strtoupper(trim((string) $raw));
                if ($c !== '' && $c !== 'XX') {
                    $codes[] = $c;
                }
            }
            $chain = $offer['all_airline_codes'] ?? $offer['marketing_carrier_chain'] ?? null;
            if (is_array($chain)) {
                foreach ($chain as $rawChain) {
                    $c = strtoupper(trim((string) $rawChain));
                    if ($c !== '' && $c !== 'XX') {
                        $codes[] = $c;
                    }
                }
            }
            foreach (is_array($offer['segments'] ?? null) ? $offer['segments'] : [] as $seg) {
                if (! is_array($seg)) {
                    continue;
                }
                foreach ([
                    $seg['airline_code'] ?? null,
                    $seg['operating_airline_code'] ?? null,
                ] as $rawSeg) {
                    $c = strtoupper(trim((string) $rawSeg));
                    if ($c !== '' && $c !== 'XX') {
                        $codes[] = $c;
                    }
                }
            }
        }

        return array_values(array_unique($codes));
    }

    /**
     * @param  list<string>  $codes
     * @return array<string, string> Uppercase code => display name
     */
    public static function mapForCodes(array $codes): array
    {
        $normalized = array_values(array_unique(array_filter(array_map(
            fn (mixed $c): string => strtoupper(trim((string) $c)),
            $codes
        ), fn (string $c): bool => $c !== '' && $c !== 'XX')));

        if ($normalized === []) {
            return [];
        }

        /** @var array<string, string> $configNames */
        $configNames = (array) config('ota.airline_display_names', []);
        $map = [];
        $needDb = [];

        foreach ($normalized as $code) {
            $canonicalName = self::canonicalDisplayName($code);
            if ($canonicalName !== null) {
                $map[$code] = $canonicalName;

                continue;
            }

            $cfg = trim((string) ($configNames[$code] ?? ''));
            if ($cfg !== '') {
                $map[$code] = $cfg;
            } else {
                $needDb[] = $code;
            }
        }

        if ($needDb !== []) {
            $rows = Airline::query()
                ->active()
                ->where(function ($q) use ($needDb): void {
                    $q->whereIn(DB::raw('UPPER(TRIM(iata_code))'), $needDb)
                        ->orWhereIn(DB::raw('UPPER(TRIM(icao_code))'), $needDb);
                })
                ->get(['iata_code', 'icao_code', 'name']);

            foreach ($rows as $row) {
                $name = trim((string) $row->name);
                if ($name === '') {
                    continue;
                }
                foreach ([$row->iata_code, $row->icao_code] as $rawKey) {
                    $key = strtoupper(trim((string) $rawKey));
                    if ($key !== '' && in_array($key, $needDb, true) && ! isset($map[$key])) {
                        $map[$key] = $name;
                    }
                }
            }
        }

        foreach ($normalized as $code) {
            if (! isset($map[$code])) {
                $map[$code] = $code;
            }
        }

        return $map;
    }

    /**
     * @param  array<string, string>  $prefetchedMap
     */
    public static function resolve(string $code, ?string $fallbackName = null, array $prefetchedMap = []): string
    {
        $code = strtoupper(trim($code));
        $fallbackName = trim((string) $fallbackName);

        if ($fallbackName !== '' && ! self::isCodeLikeName($fallbackName, $code)) {
            return $fallbackName;
        }

        if ($code === '' || $code === 'XX') {
            return $fallbackName !== '' ? $fallbackName : ($code !== '' ? $code : 'Airline');
        }

        $canonicalName = self::canonicalDisplayName($code);
        if ($canonicalName !== null) {
            return $canonicalName;
        }

        if (isset($prefetchedMap[$code])) {
            return $prefetchedMap[$code];
        }

        return self::mapForCodes([$code])[$code] ?? $code;
    }

    private static function canonicalDisplayName(string $code): ?string
    {
        $resolver = app(AirlineCanonicalResolver::class);
        $canonicalIata = $resolver->resolveToCanonicalIata($code);
        if ($canonicalIata === null) {
            return null;
        }

        $name = $resolver->canonicalDisplayName($canonicalIata);

        return $name !== null && trim($name) !== '' ? $name : null;
    }

    public static function isCodeLikeName(string $name, string $code = ''): bool
    {
        $name = trim($name);
        if ($name === '') {
            return true;
        }

        $upper = strtoupper($name);
        $code = strtoupper(trim($code));

        if ($code !== '' && $upper === $code) {
            return true;
        }

        if (preg_match('/^[A-Z0-9]{2}(\s*\+\s*[A-Z0-9]{2})+$/', $upper)) {
            return true;
        }

        return (bool) preg_match('/^[A-Z0-9]{2}$/', $upper);
    }

    /**
     * Headline label for a result card from stored offer fields.
     *
     * @param  array<string, mixed>  $offer
     * @param  array<string, string>  $prefetchedMap
     */
    public static function resolveForOffer(array $offer, array $prefetchedMap): string
    {
        $primaryCode = strtoupper(trim((string) (
            $offer['primary_display_carrier']
            ?? $offer['airline_code']
            ?? $offer['carrier_code']
            ?? ''
        )));
        if ($primaryCode === '') {
            $primaryCode = strtoupper(trim((string) ($offer['airline_code'] ?? '')));
        }

        $rawName = trim((string) ($offer['airline_name'] ?? ''));

        return self::resolve($primaryCode, $rawName, $prefetchedMap);
    }
}
