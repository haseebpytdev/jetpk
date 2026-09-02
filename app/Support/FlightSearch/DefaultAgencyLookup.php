<?php

namespace App\Support\FlightSearch;

use App\Models\Agency;

/**
 * Default agency resolution with per-request memoization (JP-LARAVEL-PERF-01).
 */
final class DefaultAgencyLookup
{
    private static ?Agency $memo = null;

    private static ?string $memoSlug = null;

    private static bool $resolved = false;

    public static function byConfiguredSlug(): ?Agency
    {
        $slug = (string) config('ota.default_agency_slug');
        if ($slug === '') {
            return null;
        }

        if (self::$resolved && self::$memoSlug === $slug) {
            return self::$memo;
        }

        self::$memoSlug = $slug;
        self::$memo = Agency::query()->where('slug', $slug)->first();
        self::$resolved = true;

        return self::$memo;
    }

    /** @internal tests */
    public static function flushRequestMemo(): void
    {
        self::$memo = null;
        self::$memoSlug = null;
        self::$resolved = false;
    }
}
