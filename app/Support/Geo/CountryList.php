<?php

namespace App\Support\Geo;

/**
 * Platform-wide ISO 3166-1 country options (name + alpha-2 + alpha-3).
 *
 * Form values use alpha-2 to match existing booking passenger and profile storage.
 */
final class CountryList
{
    /** @var list<array{name: string, alpha2: string, alpha3: string}>|null */
    private static ?array $sorted = null;

    /**
     * @return list<array{name: string, alpha2: string, alpha3: string}>
     */
    public static function all(): array
    {
        if (self::$sorted !== null) {
            return self::$sorted;
        }

        /** @var list<array{name: string, alpha2: string, alpha3: string}> $rows */
        $rows = config('countries', []);

        usort($rows, static fn (array $a, array $b): int => strcasecmp($a['name'], $b['name']));

        self::$sorted = $rows;

        return self::$sorted;
    }

    /**
     * Backward-compatible checkout/profile select shape.
     *
     * @return list<array{code: string, name: string, alpha3: string}>
     */
    public static function forSelect(): array
    {
        return array_map(static fn (array $country): array => [
            'code' => $country['alpha2'],
            'name' => $country['name'],
            'alpha3' => $country['alpha3'],
        ], self::all());
    }

    /** @return list<string> */
    public static function alpha2Codes(): array
    {
        return array_column(self::all(), 'alpha2');
    }

    public static function isValidAlpha2(?string $code): bool
    {
        if ($code === null || trim($code) === '') {
            return false;
        }

        $normalized = strtoupper(trim($code));

        return in_array($normalized, self::alpha2Codes(), true);
    }

    /**
     * @return array{name: string, alpha2: string, alpha3: string}|null
     */
    public static function findByAlpha2(?string $code): ?array
    {
        if ($code === null || trim($code) === '') {
            return null;
        }

        $normalized = strtoupper(trim($code));

        foreach (self::all() as $country) {
            if ($country['alpha2'] === $normalized) {
                return $country;
            }
        }

        return null;
    }

    public static function normalizeAlpha2(?string $code): ?string
    {
        if ($code === null || trim($code) === '') {
            return null;
        }

        $normalized = strtoupper(trim($code));

        return self::isValidAlpha2($normalized) ? $normalized : null;
    }
}
