<?php

namespace App\Services\FlightSearch;

use App\Support\FlightSearch\FlightSearchCriteriaCacheKey;
use Illuminate\Support\Facades\Cache;

/**
 * Criteria-keyed supplier search result cache (distinct from per-session {@see FlightSearchResultStore}).
 */
class FlightSearchSupplierResultCache
{
    public function __construct(
        private readonly FlightSearchCriteriaCacheKey $cacheKeyBuilder,
    ) {}

    public function enabled(): bool
    {
        return (bool) config('ota-flights.search_result_cache.enabled', true);
    }

    public function ttlSeconds(): int
    {
        return max(60, (int) config('ota-flights.search_result_cache.ttl_seconds', 300));
    }

    /**
     * @param  array<string, mixed>  $criteria
     * @param  array<string, mixed>  $context
     * @return array{offers: list<array<string, mixed>>, warnings: list<string>, meta: array<string, mixed>}|null
     */
    public function get(array $criteria, array $context = []): ?array
    {
        if (! $this->enabled()) {
            return null;
        }

        $built = $this->cacheKeyBuilder->build($criteria, $context);
        $payload = Cache::get($built['cache_key']);
        if (! is_array($payload)) {
            return null;
        }

        $offers = is_array($payload['offers'] ?? null) ? $payload['offers'] : [];
        $warnings = is_array($payload['warnings'] ?? null) ? $payload['warnings'] : [];
        $meta = is_array($payload['meta'] ?? null) ? $payload['meta'] : [];

        return [
            'offers' => $offers,
            'warnings' => array_values(array_map(static fn ($w) => (string) $w, $warnings)),
            'meta' => $meta,
        ];
    }

    /**
     * @param  array<string, mixed>  $criteria
     * @param  array<string, mixed>  $context
     * @param  list<array<string, mixed>>  $offers
     * @param  list<string>  $warnings
     * @param  array<string, mixed>  $meta
     */
    public function put(
        array $criteria,
        array $context,
        array $offers,
        array $warnings,
        array $meta = [],
    ): string {
        $built = $this->cacheKeyBuilder->build($criteria, $context);

        Cache::put($built['cache_key'], [
            'offers' => $offers,
            'warnings' => array_values($warnings),
            'meta' => $meta,
            'cached_at' => now()->toIso8601String(),
            'criteria_fingerprint' => $built['fingerprint'],
            'criteria_summary' => $built['summary'],
        ], $this->ttlSeconds());

        return $built['fingerprint'];
    }

    /**
     * @param  array<string, mixed>  $criteria
     * @param  array<string, mixed>  $context
     * @return array{fingerprint: string, cache_key: string, summary: array<string, mixed>}
     */
    public function describe(array $criteria, array $context = []): array
    {
        $built = $this->cacheKeyBuilder->build($criteria, $context);

        return [
            'fingerprint' => $built['fingerprint'],
            'cache_key' => $built['cache_key'],
            'summary' => $built['summary'],
        ];
    }
}
