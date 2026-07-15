<?php

namespace App\Services\Suppliers\AlHaider;

use App\Data\UmrahGroupPackageData;
use App\Data\UmrahGroupSearchResultData;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Read-only orchestration for Al-Haider Umrah group package search and detail.
 */
class AlHaiderUmrahGroupService
{
    private const CACHE_PREFIX = 'alhaider:umrah_groups:';

    private const STALE_SUFFIX = ':stale';

    private const AIRLINES_CACHE_KEY = 'alhaider:airlines';

    public function __construct(
        private readonly AlHaiderClient $client,
        private readonly AlHaiderPackageNormalizer $normalizer,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function search(array $filters = [], bool $forceFresh = false): UmrahGroupSearchResultData
    {
        if (! (bool) config('suppliers.al_haider.enabled')) {
            return new UmrahGroupSearchResultData(
                api_disabled: true,
                meta: ['state' => 'disabled'],
            );
        }

        if (! $this->client->isConfigured()) {
            return new UmrahGroupSearchResultData(
                api_disabled: true,
                warnings: ['Al-Haider credentials are not configured.'],
                meta: ['state' => 'missing_credentials'],
            );
        }

        $normalizedFilters = $this->normalizeFilters($filters);
        $cacheKey = self::CACHE_PREFIX.md5(json_encode($normalizedFilters) ?: '');

        if (! $forceFresh) {
            $cached = Cache::get($cacheKey);
            if (is_array($cached) && isset($cached['packages']) && is_array($cached['packages'])) {
                return $this->resultFromCachedPayload($cached, fromCache: true);
            }
        }

        try {
            $airlineMap = $this->resolveAirlineMap();
            $response = $this->client->listGroups($normalizedFilters);
            $rawGroups = $response['groups'] ?? [];
            if (! is_array($rawGroups)) {
                $rawGroups = [];
            }

            $packages = $this->normalizer->normalizeMany($rawGroups, $airlineMap);
            $payload = [
                'packages' => array_map(static fn (UmrahGroupPackageData $p) => $p->toArray(), $packages),
                'fetched_at' => now()->toIso8601String(),
            ];

            $ttl = max(60, (int) config('suppliers.al_haider.cache_ttl_seconds', 600));
            Cache::put($cacheKey, $payload, $ttl);
            Cache::put($cacheKey.self::STALE_SUFFIX, $payload, 86400);

            return new UmrahGroupSearchResultData(
                packages: $packages,
                meta: ['state' => 'ok', 'count' => count($packages)],
            );
        } catch (\Throwable $exception) {
            $reason = $exception instanceof AlHaiderProviderException ? $exception->errorCode : 'exception';

            Log::warning('alhaider.search_failed', [
                'supplier' => 'alhaider',
                'message' => $exception->getMessage(),
                'reason' => $reason,
                'filter_summary' => $this->filterSummary($normalizedFilters),
            ]);

            if ($reason === 'supplier_auth_token_limit') {
                return new UmrahGroupSearchResultData(
                    api_unavailable: true,
                    warnings: ['Live group availability is temporarily unavailable. Please try again.'],
                    meta: ['state' => 'unavailable', 'reason' => 'supplier_auth_token_limit'],
                );
            }

            $stale = Cache::get($cacheKey.self::STALE_SUFFIX);
            if (is_array($stale) && isset($stale['packages'])) {
                $result = $this->resultFromCachedPayload($stale, fromCache: true, fromStale: true);
                $result->warnings[] = 'Showing cached results; packages may not be current.';

                return $result;
            }

            return new UmrahGroupSearchResultData(
                api_unavailable: true,
                warnings: ['Umrah group packages are temporarily unavailable. Please try again shortly.'],
                meta: ['state' => 'unavailable'],
            );
        }
    }

    public function getPackageDetail(string $publicId, bool $forceFresh = false): ?UmrahGroupPackageData
    {
        $groupId = $this->resolveGroupId($publicId);
        if ($groupId === '') {
            return null;
        }

        if (! (bool) config('suppliers.al_haider.enabled') || ! $this->client->isConfigured()) {
            return null;
        }

        $cacheKey = self::CACHE_PREFIX.'detail:'.$groupId;

        if (! $forceFresh) {
            $cached = Cache::get($cacheKey);
            if (is_array($cached) && isset($cached['package']) && is_array($cached['package'])) {
                return $this->packageFromArray($cached['package']);
            }
        }

        try {
            $airlineMap = $this->resolveAirlineMap();
            $response = $this->client->getGroupDetail($groupId);
            $group = $response['group'] ?? null;
            if (! is_array($group) || $group === []) {
                return null;
            }

            try {
                $seatsResponse = $this->client->getAvailableSeats($groupId);
                if (isset($seatsResponse['seats']) && is_numeric($seatsResponse['seats'])) {
                    $group['available_no_of_pax'] = (int) $seatsResponse['seats'];
                }
            } catch (\Throwable) {
                // Seats endpoint is optional enrichment.
            }

            $package = $this->normalizer->normalize($group, $airlineMap);
            $ttl = max(60, (int) config('suppliers.al_haider.cache_ttl_seconds', 600));
            $payload = ['package' => $package->toArray()];
            Cache::put($cacheKey, $payload, $ttl);
            Cache::put($cacheKey.self::STALE_SUFFIX, $payload, 86400);

            return $package;
        } catch (\Throwable $exception) {
            $reason = $exception instanceof AlHaiderProviderException ? $exception->errorCode : 'exception';

            Log::warning('alhaider.detail_failed', [
                'supplier' => 'alhaider',
                'supplier_package_id' => $groupId,
                'message' => $exception->getMessage(),
                'reason' => $reason,
            ]);

            if ($reason === 'supplier_auth_token_limit') {
                return null;
            }

            $stale = Cache::get($cacheKey.self::STALE_SUFFIX);
            if (is_array($stale) && isset($stale['package'])) {
                return $this->packageFromArray($stale['package']);
            }

            return null;
        }
    }

    /**
     * @return list<array{id: int, name: string, short_name: string, logo_url: ?string}>
     */
    public function listAirlinesForFilters(): array
    {
        if (! (bool) config('suppliers.al_haider.enabled') || ! $this->client->isConfigured()) {
            return [];
        }

        try {
            $map = $this->resolveAirlineMap();
            $airlines = [];
            foreach ($map as $id => $info) {
                $airlines[] = [
                    'id' => (int) $id,
                    'name' => (string) ($info['name'] ?? 'Airline'),
                    'short_name' => (string) ($info['short_name'] ?? ''),
                    'logo_url' => $info['logo'] ?? null,
                ];
            }

            usort($airlines, static fn (array $a, array $b): int => strcmp($a['name'], $b['name']));

            return $airlines;
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function resolveAirlineMap(): array
    {
        $cached = Cache::get(self::AIRLINES_CACHE_KEY);
        if (is_array($cached)) {
            return $cached;
        }

        $response = $this->client->listAirlines();
        $map = $this->normalizer->airlineMapFromResponse($response);
        Cache::put(self::AIRLINES_CACHE_KEY, $map, max(300, (int) config('suppliers.al_haider.cache_ttl_seconds', 600)));

        return $map;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, string>
     */
    private function normalizeFilters(array $filters): array
    {
        $normalized = [];
        foreach (['sector', 'dept_date', 'type'] as $key) {
            $value = trim((string) ($filters[$key] ?? ''));
            if ($value !== '') {
                $normalized[$key] = $value;
            }
        }

        $airlineId = trim((string) ($filters['airline_id'] ?? ''));
        if ($airlineId !== '' && ctype_digit($airlineId)) {
            $normalized['airline_id'] = $airlineId;
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function resultFromCachedPayload(array $payload, bool $fromCache, bool $fromStale = false): UmrahGroupSearchResultData
    {
        $packages = [];
        foreach ($payload['packages'] as $row) {
            if (is_array($row)) {
                $packages[] = $this->packageFromArray($row);
            }
        }

        return new UmrahGroupSearchResultData(
            packages: $packages,
            meta: ['state' => $fromStale ? 'stale_cache' : 'cache', 'count' => count($packages)],
            from_cache: $fromCache,
            from_stale_cache: $fromStale,
        );
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function packageFromArray(array $row): UmrahGroupPackageData
    {
        return new UmrahGroupPackageData(
            supplier: (string) ($row['supplier'] ?? 'alhaider'),
            supplier_package_id: (string) ($row['supplier_package_id'] ?? ''),
            public_id: (string) ($row['public_id'] ?? ''),
            title: (string) ($row['title'] ?? 'Umrah Group Package'),
            departure_city: isset($row['departure_city']) ? (string) $row['departure_city'] : null,
            destination: isset($row['destination']) ? (string) $row['destination'] : null,
            sector: isset($row['sector']) ? (string) $row['sector'] : null,
            departure_date: isset($row['departure_date']) ? (string) $row['departure_date'] : null,
            return_date: isset($row['return_date']) ? (string) $row['return_date'] : null,
            duration_days: isset($row['duration_days']) ? (int) $row['duration_days'] : null,
            airline: isset($row['airline']) ? (string) $row['airline'] : null,
            airline_logo_url: isset($row['airline_logo_url']) ? (string) $row['airline_logo_url'] : null,
            package_type: isset($row['package_type']) ? (string) $row['package_type'] : null,
            price: (float) ($row['price'] ?? 0),
            price_child: isset($row['price_child']) ? (float) $row['price_child'] : null,
            price_infant: isset($row['price_infant']) ? (float) $row['price_infant'] : null,
            currency: (string) ($row['currency'] ?? 'PKR'),
            availability_status: (string) ($row['availability_status'] ?? 'limited'),
            seats_available: (int) ($row['seats_available'] ?? 0),
            baggage: isset($row['baggage']) ? (string) $row['baggage'] : null,
            meal: isset($row['meal']) ? (string) $row['meal'] : null,
            legs: is_array($row['legs'] ?? null) ? $row['legs'] : [],
            makkah_hotel: isset($row['makkah_hotel']) ? (string) $row['makkah_hotel'] : null,
            madinah_hotel: isset($row['madinah_hotel']) ? (string) $row['madinah_hotel'] : null,
            included_services: is_array($row['included_services'] ?? null) ? $row['included_services'] : [],
        );
    }

    private function resolveGroupId(string $publicId): string
    {
        $publicId = trim($publicId);
        if (str_starts_with(strtoupper($publicId), 'ALH-')) {
            return substr($publicId, 4);
        }

        return $publicId;
    }

    /**
     * @param  array<string, string>  $filters
     */
    private function filterSummary(array $filters): string
    {
        $parts = [];
        foreach ($filters as $key => $value) {
            $parts[] = $key.'='.$value;
        }

        return implode(',', $parts);
    }
}
