<?php

namespace App\Support\Homepage;

/**
 * Builds JetPakistan flight-search URLs for homepage trending routes and destinations.
 */
final class JetpkHomepageRouteSearchUrlBuilder
{
    /**
     * @param  array<string, mixed>  $item
     * @param  array<string, mixed>|null  $fareCache
     */
    public function fromRouteItem(array $item, ?array $fareCache = null, bool $respectManualOverride = true): string
    {
        if ($respectManualOverride && $this->isManualCta($item)) {
            $custom = trim((string) ($item['cta_url'] ?? ''));
            if ($custom !== '') {
                return str_starts_with($custom, 'http') ? $custom : client_url($custom);
            }
        }

        $offset = max(1, (int) config('jetpk_homepage.route_date_offset_days', 7));
        $depart = trim((string) ($fareCache['travel_date'] ?? ''))
            ?: now(config('app.timezone', 'Asia/Karachi'))->addDays($offset)->toDateString();
        $tripType = (string) ($item['trip_type'] ?? config('jetpk_homepage.default_trip_type', 'one_way'));

        $params = [
            'from' => strtoupper((string) ($item['from'] ?? '')),
            'to' => strtoupper((string) ($item['to'] ?? '')),
            'depart' => $depart,
            'trip_type' => $tripType === 'return' ? 'return' : 'one_way',
            'cabin' => (string) ($item['cabin'] ?? config('jetpk_homepage.default_cabin', 'economy')),
            'adults' => max(1, (int) ($item['adults'] ?? config('jetpk_homepage.default_adults', 1))),
            'children' => 0,
            'infants' => 0,
        ];

        if ($tripType === 'return') {
            $stay = max(1, (int) ($item['return_stay_days'] ?? config('jetpk_homepage.default_return_stay_days', 7)));
            $params['return'] = trim((string) ($fareCache['return_date'] ?? ''))
                ?: now(config('app.timezone', 'Asia/Karachi'))->addDays($offset + $stay)->toDateString();
        }

        return client_route('flights.results', $params);
    }

    /**
     * @param  array<string, mixed>|null  $fareCache
     */
    public function fromDestination(string $origin, string $destination, ?array $fareCache = null): string
    {
        $offset = max(1, (int) config('jetpk_homepage.route_date_offset_days', 7));
        $depart = trim((string) ($fareCache['travel_date'] ?? ''))
            ?: now(config('app.timezone', 'Asia/Karachi'))->addDays($offset)->toDateString();
        $tripType = (string) config('jetpk_homepage.default_trip_type', 'one_way');

        return client_route('flights.results', [
            'from' => strtoupper($origin),
            'to' => strtoupper($destination),
            'depart' => $depart,
            'trip_type' => $tripType === 'return' ? 'return' : 'one_way',
            'cabin' => (string) config('jetpk_homepage.default_cabin', 'economy'),
            'adults' => max(1, (int) config('jetpk_homepage.default_adults', 1)),
            'children' => 0,
            'infants' => 0,
        ]);
    }

    /**
     * @param  array<string, mixed>  $item
     */
    public function isManualCta(array $item): bool
    {
        return strtolower(trim((string) ($item['cta_mode'] ?? 'auto'))) === 'manual';
    }
}
