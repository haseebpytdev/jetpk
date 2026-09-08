<?php

namespace App\Services\Homepage;

use App\Enums\ClientPageSettingStatus;
use App\Enums\JetpkHomepageFareRefreshStatus;
use App\Models\Agency;
use App\Models\ClientPageSetting;
use App\Models\ClientProfile;
use App\Support\Client\ClientPageKeys;
use App\Support\Client\JetpkHomepageFareDisplay;
use App\Support\Homepage\JetpkHomepageRouteSearchUrlBuilder;

/**
 * Read-only homepage fare provenance audit for trending routes and destinations.
 */
final class JetpkHomepageFareProvenanceAuditor
{
    public function __construct(
        private readonly JetpkHomepageRouteFareRefreshService $refreshService,
        private readonly JetpkHomepageRouteSearchUrlBuilder $searchUrlBuilder,
    ) {}

    /**
     * @return array{trending: list<array<string, mixed>>, destinations: list<array<string, mixed>>, meta: array<string, mixed>}
     */
    public function audit(ClientProfile $profile, ?Agency $agency = null): array
    {
        $agency ??= Agency::query()->where('slug', config('ota.default_agency_slug'))->first();

        $published = ClientPageSetting::query()
            ->where('client_profile_id', $profile->id)
            ->where('page_key', ClientPageKeys::HOME)
            ->where('status', ClientPageSettingStatus::Published)
            ->first();

        if ($published === null || ! is_array($published->content_json)) {
            return [
                'trending' => [],
                'destinations' => [],
                'meta' => ['error' => 'no_published_homepage'],
            ];
        }

        $content = $published->content_json;
        $routeItems = is_array($content['routes']['items'] ?? null) ? $content['routes']['items'] : [];
        $routeCache = is_array($content['_fare_cache']['routes'] ?? null) ? $content['_fare_cache']['routes'] : [];
        $destinationItems = is_array($content['destinations']['items'] ?? null) ? $content['destinations']['items'] : [];
        $destinationCache = is_array($content['_fare_cache']['destinations'] ?? null) ? $content['_fare_cache']['destinations'] : [];

        $trending = [];
        foreach ($routeItems as $item) {
            if (! is_array($item) || ! $this->isTruthy($item['enabled'] ?? '1')) {
                continue;
            }
            if (! $this->isTruthy($item['dynamic_fare_enabled'] ?? '0')) {
                continue;
            }

            $routeId = (string) ($item['id'] ?? '');
            $cached = is_array($routeCache[$routeId] ?? null) ? $routeCache[$routeId] : null;
            $displayed = JetpkHomepageFareDisplay::resolve($item, $cached);
            $search = $agency !== null
                ? $this->refreshService->refreshRouteItem($item, $agency, $cached)
                : ['status' => 'skipped', 'message' => 'agency_missing'];

            $minPrice = isset($search['chosen_fare']) ? (float) $search['chosen_fare'] : null;
            $displayedPrice = $displayed !== null ? (float) $displayed['amount'] : null;
            $ctaUrl = $this->searchUrlBuilder->fromRouteItem($item, is_array($search['cache'] ?? null) ? $search['cache'] : $cached, false);

            $trending[] = [
                'route' => $routeId,
                'origin' => strtoupper(trim((string) ($item['from'] ?? ''))),
                'destination' => strtoupper(trim((string) ($item['to'] ?? ''))),
                'trip_type' => (string) ($item['trip_type'] ?? 'one_way'),
                'search_date_context' => [
                    'departure_date' => $search['departure_date'] ?? ($cached['travel_date'] ?? null),
                    'return_date' => $search['return_date'] ?? ($cached['return_date'] ?? null),
                ],
                'offers_considered' => (int) ($search['result_count'] ?? 0),
                'eligible_offers' => (int) ($search['result_count'] ?? 0),
                'min_eligible_customer_price' => $minPrice,
                'winning_provider' => is_array($search['cache'] ?? null) ? ($search['cache']['fare_provider'] ?? null) : ($cached['fare_provider'] ?? null),
                'winning_date' => $search['departure_date'] ?? ($cached['travel_date'] ?? null),
                'displayed_price' => $displayedPrice,
                'displayed_label' => $displayed['label'] ?? JetpkHomepageFareDisplay::checkFareLabel(),
                'cta_url' => $ctaUrl,
                'price_match' => $minPrice !== null && $displayedPrice !== null && (int) round($minPrice) === (int) round($displayedPrice),
                'date_match' => ($search['departure_date'] ?? null) === ($cached['travel_date'] ?? $search['departure_date'] ?? null),
                'cta_match' => $ctaUrl !== null && $ctaUrl !== '',
                'search_status' => $search['status'] ?? null,
                'cache_fresh' => $cached !== null && JetpkHomepageFareDisplay::isFresh(
                    \Illuminate\Support\Carbon::parse((string) ($cached['fare_refreshed_at'] ?? now()->toIso8601String()))
                ),
            ];
        }

        $destinations = [];
        $originPool = config('jetpk_homepage.destination_origin_pool', ['KHI', 'LHE', 'ISB']);
        foreach ($destinationItems as $item) {
            if (! is_array($item) || ! $this->isTruthy($item['enabled'] ?? '1')) {
                continue;
            }

            $destId = (string) ($item['id'] ?? $item['code'] ?? '');
            $destination = strtoupper(trim((string) ($item['code'] ?? '')));
            if ($destId === '' || $destination === '') {
                continue;
            }

            $cached = is_array($destinationCache[$destId] ?? null)
                ? $destinationCache[$destId]
                : (is_array($destinationCache[$destination] ?? null) ? $destinationCache[$destination] : null);
            $displayed = JetpkHomepageFareDisplay::resolve($item, $cached);
            $search = $agency !== null
                ? $this->refreshService->refreshDestinationItem($item, $agency, $cached)
                : ['status' => 'skipped'];

            $perOrigin = [];
            if ($agency !== null && is_array($originPool)) {
                foreach ($originPool as $originCode) {
                    $origin = strtoupper(trim((string) $originCode));
                    if ($origin === '' || $origin === $destination) {
                        continue;
                    }
                    $synthetic = [
                        'id' => $destId.'-'.$origin,
                        'from' => $origin,
                        'to' => $destination,
                        'trip_type' => config('jetpk_homepage.default_trip_type', 'one_way'),
                    ];
                    $originResult = $this->refreshService->refreshRouteItem($synthetic, $agency, null);
                    if (($originResult['status'] ?? '') === JetpkHomepageFareRefreshStatus::Success->value) {
                        $perOrigin[$origin] = (float) ($originResult['chosen_fare'] ?? 0);
                    }
                }
            }

            $winningOrigin = is_array($search['cache'] ?? null)
                ? ($search['cache']['winning_origin'] ?? $search['origin'] ?? null)
                : ($cached['winning_origin'] ?? null);
            $winningPrice = isset($search['chosen_fare']) ? (float) $search['chosen_fare'] : null;
            $displayedPrice = $displayed !== null ? (float) $displayed['amount'] : null;
            $travelDate = is_array($search['cache'] ?? null)
                ? ($search['cache']['travel_date'] ?? null)
                : ($cached['travel_date'] ?? null);

            $destinations[] = [
                'destination' => $destination,
                'origins_tested' => array_keys($perOrigin),
                'per_origin_minimums' => $perOrigin,
                'winning_origin' => $winningOrigin,
                'winning_price' => $winningPrice,
                'winning_date' => $travelDate,
                'displayed_price' => $displayedPrice,
                'displayed_label' => $displayed['label'] ?? JetpkHomepageFareDisplay::checkFareLabel(),
                'public_link_origin' => $winningOrigin,
                'public_link_destination' => $destination,
                'public_link_date' => $travelDate,
                'price_match' => $winningPrice !== null && $displayedPrice !== null && (int) round($winningPrice) === (int) round($displayedPrice),
                'origin_match' => $winningOrigin !== null && $winningOrigin === ($cached['winning_origin'] ?? $winningOrigin),
                'date_match' => $travelDate !== null,
                'search_status' => $search['status'] ?? null,
            ];
        }

        return [
            'trending' => $trending,
            'destinations' => $destinations,
            'meta' => [
                'audited_at' => now()->toIso8601String(),
                'fare_freshness_hours' => (int) config('jetpk_homepage.fare_freshness_hours', 30),
                'allow_stale_fare_display' => (bool) config('jetpk_homepage.allow_stale_fare_display', false),
                'destination_origin_pool' => $originPool,
            ],
        ];
    }

    private function isTruthy(mixed $value): bool
    {
        return in_array((string) $value, ['1', 'true', 'yes', 'on'], true);
    }
}
