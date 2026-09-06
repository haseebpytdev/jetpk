<?php

namespace App\Services\Homepage;

use App\Enums\ClientPageSettingStatus;
use App\Enums\JetpkHomepageFareRefreshStatus;
use App\Models\Agency;
use App\Models\Airport;
use App\Models\ClientPageSetting;
use App\Models\ClientProfile;
use App\Services\FlightSearch\FlightSearchService;
use App\Support\Client\ClientPageKeys;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Read-only shopping search refresher for JetPK homepage trending route fares.
 */
final class JetpkHomepageRouteFareRefreshService
{
    public function __construct(
        private readonly FlightSearchService $flightSearch,
    ) {}

    /**
     * @return array{refreshed: int, success: int, failed: int, skipped: int, results: list<array<string, mixed>>}
     */
    public function refreshProfile(ClientProfile $profile, bool $persist = true, ?Agency $agency = null): array
    {
        $lockKey = 'jetpk:homepage-route-fares:'.$profile->id;
        $lock = Cache::lock($lockKey, (int) config('jetpk_homepage.refresh_lock_seconds', 900));

        if (! $lock->get()) {
            return [
                'refreshed' => 0,
                'success' => 0,
                'failed' => 0,
                'skipped' => 0,
                'results' => [[
                    'status' => 'locked',
                    'message' => 'Refresh already in progress.',
                ]],
            ];
        }

        try {
            return $this->refreshProfileUnlocked($profile, $persist, $agency);
        } finally {
            $lock->release();
        }
    }

    /**
     * @return array{refreshed: int, success: int, failed: int, skipped: int, results: list<array<string, mixed>>}
     */
    private function refreshProfileUnlocked(ClientProfile $profile, bool $persist, ?Agency $agency): array
    {
        $agency ??= Agency::query()->where('slug', config('ota.default_agency_slug'))->first();
        if ($agency === null) {
            return [
                'refreshed' => 0,
                'success' => 0,
                'failed' => 0,
                'skipped' => 0,
                'results' => [[
                    'status' => 'failed',
                    'message' => 'Default agency not configured.',
                ]],
            ];
        }

        $published = ClientPageSetting::query()
            ->where('client_profile_id', $profile->id)
            ->where('page_key', ClientPageKeys::HOME)
            ->where('status', ClientPageSettingStatus::Published)
            ->first();

        if ($published === null || ! is_array($published->content_json)) {
            return [
                'refreshed' => 0,
                'success' => 0,
                'failed' => 0,
                'skipped' => 0,
                'results' => [[
                    'status' => 'skipped',
                    'message' => 'No published homepage content.',
                ]],
            ];
        }

        $content = $published->content_json;
        $items = is_array($content['routes']['items'] ?? null) ? $content['routes']['items'] : [];
        $fareCache = is_array($content['_fare_cache']['routes'] ?? null) ? $content['_fare_cache']['routes'] : [];
        $destinationItems = is_array($content['destinations']['items'] ?? null) ? $content['destinations']['items'] : [];
        $destinationFareCache = is_array($content['_fare_cache']['destinations'] ?? null) ? $content['_fare_cache']['destinations'] : [];

        $summary = [
            'refreshed' => 0,
            'success' => 0,
            'failed' => 0,
            'skipped' => 0,
            'results' => [],
        ];

        foreach ($this->sortedActiveRoutes($items) as $item) {
            $routeId = (string) ($item['id'] ?? '');
            if ($routeId === '') {
                $summary['skipped']++;

                continue;
            }

            if (! $this->isTruthy($item['enabled'] ?? '1')) {
                $summary['skipped']++;
                $summary['results'][] = [
                    'route_id' => $routeId,
                    'origin' => $item['from'] ?? '',
                    'destination' => $item['to'] ?? '',
                    'status' => 'skipped',
                    'message' => 'Route disabled.',
                ];

                continue;
            }

            if (! $this->isTruthy($item['dynamic_fare_enabled'] ?? '0')) {
                $summary['skipped']++;
                $summary['results'][] = [
                    'route_id' => $routeId,
                    'origin' => $item['from'] ?? '',
                    'destination' => $item['to'] ?? '',
                    'status' => 'skipped',
                    'message' => 'Dynamic fare disabled.',
                ];

                continue;
            }

            $result = $this->refreshRouteItem($item, $agency, $fareCache[$routeId] ?? null);
            $summary['refreshed']++;
            $summary['results'][] = $result;

            if (($result['status'] ?? '') === JetpkHomepageFareRefreshStatus::Success->value) {
                $summary['success']++;
                if ($persist) {
                    $fareCache[$routeId] = $result['cache'];
                }
            } else {
                $summary['failed']++;
                if ($persist && isset($result['cache'])) {
                    $fareCache[$routeId] = $result['cache'];
                }
            }
        }

        foreach ($this->sortedActiveRoutes($destinationItems) as $item) {
            $destId = (string) ($item['id'] ?? $item['code'] ?? '');
            $destination = strtoupper(trim((string) ($item['code'] ?? '')));
            if ($destId === '' || $destination === '') {
                $summary['skipped']++;
                continue;
            }
            if (! $this->isTruthy($item['enabled'] ?? '1')) {
                $summary['skipped']++;
                continue;
            }

            $result = $this->refreshDestinationItem($item, $agency, $destinationFareCache[$destId] ?? $destinationFareCache[$destination] ?? null);
            $summary['refreshed']++;
            $summary['results'][] = $result;
            if (($result['status'] ?? '') === JetpkHomepageFareRefreshStatus::Success->value) {
                $summary['success']++;
            } else {
                $summary['failed']++;
            }
            if ($persist && isset($result['cache'])) {
                $destinationFareCache[$destId] = $result['cache'];
            }
        }

        if ($persist && $summary['refreshed'] > 0) {
            data_set($content, '_fare_cache.routes', $fareCache);
            data_set($content, '_fare_cache.destinations', $destinationFareCache);
            $published->update(['content_json' => $content]);

            $draft = ClientPageSetting::query()
                ->where('client_profile_id', $profile->id)
                ->where('page_key', ClientPageKeys::HOME)
                ->where('status', ClientPageSettingStatus::Draft)
                ->first();

            if ($draft !== null && is_array($draft->content_json)) {
                $draftContent = $draft->content_json;
                data_set($draftContent, '_fare_cache.routes', $fareCache);
                data_set($draftContent, '_fare_cache.destinations', $destinationFareCache);
                $draft->update(['content_json' => $draftContent]);
            }
        }

        return $summary;
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  array<string, mixed>|null  $previousCache
     * @return array<string, mixed>
     */
    public function refreshRouteItem(array $item, Agency $agency, ?array $previousCache = null): array
    {
        $routeId = (string) ($item['id'] ?? '');
        $origin = strtoupper(trim((string) ($item['from'] ?? '')));
        $destination = strtoupper(trim((string) ($item['to'] ?? '')));
        $tripType = (string) ($item['trip_type'] ?? 'one_way');
        $stayDays = max(1, (int) ($item['return_stay_days'] ?? config('jetpk_homepage.default_return_stay_days', 7)));
        $offsetDays = max(1, (int) config('jetpk_homepage.route_date_offset_days', 7));
        $base = now(config('app.timezone', 'Asia/Karachi'));
        $departureDate = $base->copy()->addDays($offsetDays)->toDateString();
        $returnDate = $base->copy()->addDays($offsetDays + $stayDays)->toDateString();

        $criteria = [
            'origin' => $origin,
            'destination' => $destination,
            'departure_date' => $departureDate,
            'trip_type' => $tripType === 'return' ? 'return' : 'one_way',
            'adults' => max(1, (int) ($item['adults'] ?? config('jetpk_homepage.default_adults', 1))),
            'children' => 0,
            'infants' => 0,
            'cabin' => trim((string) ($item['cabin'] ?? config('jetpk_homepage.default_cabin', 'economy'))) ?: 'economy',
        ];

        if ($criteria['trip_type'] === 'return') {
            $criteria['return_date'] = $returnDate;
        }

        try {
            $offers = $this->flightSearch->search($criteria, $agency, 'jetpk_homepage_route_fare');
            $cheapest = $this->pickCheapestOffer($offers);
            $resultCount = count($offers);

            if ($cheapest === null) {
                Log::info('jetpk.homepage_route_fare.no_results', [
                    'route_id' => $routeId,
                    'origin' => $origin,
                    'destination' => $destination,
                    'departure_date' => $departureDate,
                    'return_date' => $criteria['return_date'] ?? null,
                    'result_count' => $resultCount,
                ]);

                return [
                    'route_id' => $routeId,
                    'origin' => $origin,
                    'destination' => $destination,
                    'departure_date' => $departureDate,
                    'return_date' => $criteria['return_date'] ?? null,
                    'result_count' => $resultCount,
                    'status' => JetpkHomepageFareRefreshStatus::NoResults->value,
                    'message' => 'No valid fares returned.',
                    'cache' => $this->preserveOrClearCache($previousCache, JetpkHomepageFareRefreshStatus::NoResults->value, 'no_results'),
                ];
            }

            $amount = $this->offerTotal($cheapest);
            $currency = strtoupper((string) ($cheapest['pricing_currency'] ?? $cheapest['currency'] ?? 'PKR'));
            $provider = Str::limit((string) ($cheapest['supplier_provider'] ?? ''), 64, '');

            Log::info('jetpk.homepage_route_fare.success', [
                'route_id' => $routeId,
                'origin' => $origin,
                'destination' => $destination,
                'departure_date' => $departureDate,
                'return_date' => $criteria['return_date'] ?? null,
                'result_count' => $resultCount,
                'chosen_fare' => $amount,
                'currency' => $currency,
                'provider' => $provider,
            ]);

            return [
                'route_id' => $routeId,
                'origin' => $origin,
                'destination' => $destination,
                'departure_date' => $departureDate,
                'return_date' => $criteria['return_date'] ?? null,
                'result_count' => $resultCount,
                'chosen_fare' => $amount,
                'currency' => $currency,
                'status' => JetpkHomepageFareRefreshStatus::Success->value,
                'cache' => [
                    'resolved_fare' => $amount,
                    'resolved_currency' => $currency,
                    'fare_refreshed_at' => now()->toIso8601String(),
                    'fare_status' => JetpkHomepageFareRefreshStatus::Success->value,
                    'fare_provider' => $provider,
                    'fare_error' => null,
                    'travel_date' => $departureDate,
                    'return_date' => $criteria['return_date'] ?? null,
                ],
            ];
        } catch (Throwable $e) {
            Log::warning('jetpk.homepage_route_fare.failed', [
                'route_id' => $routeId,
                'origin' => $origin,
                'destination' => $destination,
                'departure_date' => $departureDate,
                'exception' => $e::class,
            ]);

            return [
                'route_id' => $routeId,
                'origin' => $origin,
                'destination' => $destination,
                'departure_date' => $departureDate,
                'return_date' => $criteria['return_date'] ?? null,
                'result_count' => 0,
                'status' => JetpkHomepageFareRefreshStatus::Failed->value,
                'message' => Str::limit(trim($e->getMessage()) ?: 'Search failed.', 500, ''),
                'cache' => $this->preserveOrClearCache($previousCache, JetpkHomepageFareRefreshStatus::Failed->value, 'search_failed'),
            ];
        }
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  array<string, mixed>|null  $previousCache
     * @return array<string, mixed>
     */
    public function refreshDestinationItem(array $item, Agency $agency, ?array $previousCache = null): array
    {
        $destId = (string) ($item['id'] ?? $item['code'] ?? '');
        $destination = strtoupper(trim((string) ($item['code'] ?? '')));
        $origins = $this->originPool($destination);
        $best = null;
        $winningOrigin = null;

        foreach ($origins as $origin) {
            $synthetic = [
                'id' => $destId.'-'.$origin,
                'from' => $origin,
                'to' => $destination,
                'trip_type' => config('jetpk_homepage.default_trip_type', 'one_way'),
                'adults' => config('jetpk_homepage.default_adults', 1),
                'cabin' => config('jetpk_homepage.default_cabin', 'economy'),
            ];
            $result = $this->refreshRouteItem($synthetic, $agency, null);
            if (($result['status'] ?? '') !== JetpkHomepageFareRefreshStatus::Success->value) {
                continue;
            }
            $amount = (float) ($result['chosen_fare'] ?? 0);
            if ($amount <= 0) {
                continue;
            }
            if ($best === null || $amount < (float) ($best['chosen_fare'] ?? PHP_INT_MAX)) {
                $best = $result;
                $winningOrigin = $origin;
            }
        }

        if ($best === null || $winningOrigin === null) {
            return [
                'route_id' => $destId,
                'origin' => null,
                'destination' => $destination,
                'status' => JetpkHomepageFareRefreshStatus::NoResults->value,
                'message' => 'No eligible origin-pool fare.',
                'cache' => $this->preserveOrClearCache($previousCache, JetpkHomepageFareRefreshStatus::NoResults->value, 'no_origin_fare'),
            ];
        }

        $cache = is_array($best['cache'] ?? null) ? $best['cache'] : [];
        $cache['winning_origin'] = $winningOrigin;
        $cache['destination'] = $destination;

        return [
            'route_id' => $destId,
            'origin' => $winningOrigin,
            'destination' => $destination,
            'departure_date' => $best['departure_date'] ?? null,
            'result_count' => $best['result_count'] ?? 0,
            'chosen_fare' => $best['chosen_fare'] ?? null,
            'currency' => $best['currency'] ?? 'PKR',
            'status' => JetpkHomepageFareRefreshStatus::Success->value,
            'cache' => $cache,
        ];
    }

    /**
     * @return list<string>
     */
    private function originPool(string $destination): array
    {
        $pool = config('jetpk_homepage.destination_origin_pool', ['KHI', 'LHE', 'ISB']);
        if (! is_array($pool) || $pool === []) {
            $pool = ['KHI', 'LHE', 'ISB'];
        }

        $codes = [];
        foreach ($pool as $code) {
            $iata = strtoupper(trim((string) $code));
            if ($iata === '' || $iata === $destination || ! preg_match('/^[A-Z]{3}$/', $iata)) {
                continue;
            }
            $codes[] = $iata;
        }

        return array_values(array_unique($codes));
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    private function sortedActiveRoutes(array $items): array
    {
        $routes = array_values(array_filter($items, static fn ($item) => is_array($item)));

        usort($routes, static function (array $a, array $b): int {
            return ((int) ($a['sort_order'] ?? 0)) <=> ((int) ($b['sort_order'] ?? 0));
        });

        return $routes;
    }

    /**
     * @param  list<array<string, mixed>>  $offers
     * @return array<string, mixed>|null
     */
    private function pickCheapestOffer(array $offers): ?array
    {
        if ($offers === []) {
            return null;
        }

        usort($offers, fn (array $a, array $b): int => $this->offerTotal($a) <=> $this->offerTotal($b));

        foreach ($offers as $offer) {
            if ($this->offerTotal($offer) > 0) {
                return $offer;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $offer
     */
    private function offerTotal(array $offer): float
    {
        $total = (float) ($offer['final_customer_price'] ?? $offer['total'] ?? 0);

        return $total > 0 ? $total : 0.0;
    }

    /**
     * @param  array<string, mixed>|null  $previousCache
     * @return array<string, mixed>
     */
    private function preserveOrClearCache(?array $previousCache, string $status, string $errorCode): array
    {
        if ($previousCache !== null && (float) ($previousCache['resolved_fare'] ?? 0) > 0) {
            return array_merge($previousCache, [
                'fare_refreshed_at' => now()->toIso8601String(),
                'fare_status' => $status,
                'fare_error' => $errorCode,
            ]);
        }

        return [
            'resolved_fare' => null,
            'resolved_currency' => $previousCache['resolved_currency'] ?? 'PKR',
            'fare_refreshed_at' => now()->toIso8601String(),
            'fare_status' => $status,
            'fare_provider' => $previousCache['fare_provider'] ?? null,
            'fare_error' => $errorCode,
        ];
    }

    private function isTruthy(mixed $value): bool
    {
        return in_array((string) $value, ['1', 'true', 'yes', 'on'], true);
    }

    public static function isCanonicalAirport(string $iata): bool
    {
        $code = strtoupper(trim($iata));
        if (! preg_match('/^[A-Z]{3}$/', $code)) {
            return false;
        }

        return Airport::query()->active()->where('iata_code', $code)->exists();
    }
}
