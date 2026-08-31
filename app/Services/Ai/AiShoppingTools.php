<?php

namespace App\Services\Ai;

use App\Data\Ai\TravelIntent;
use App\Services\GroupTicketing\GroupInventorySearchService;
use App\Services\Share\PublicShareLinkService;

/**
 * Read-only shopping helpers for the AI assistant. No supplier mutations.
 */
final class AiShoppingTools
{
    public function __construct(
        private readonly GroupInventorySearchService $groups,
        private readonly PublicShareLinkService $shareLinks,
        private readonly DealRankingService $ranking,
    ) {}

    /**
     * Prefer structured deep-link to /flights/results. Never invent fares.
     *
     * @return array{
     *   recommendations: list<array<string, mixed>>,
     *   meta: array{AI_FLIGHT_SEARCH_READ_CALLS: int},
     *   freshness_note: string
     * }
     */
    public function searchFlights(TravelIntent $intent): array
    {
        $calls = 0;
        $recommendations = [];

        if (! (bool) config('ota.ai_assistant.flight_search_enabled', true)) {
            return [
                'recommendations' => [],
                'meta' => ['AI_FLIGHT_SEARCH_READ_CALLS' => 0],
                'freshness_note' => 'Flight search is temporarily unavailable.',
            ];
        }

        if ($intent->origin === null || $intent->destination === null) {
            return [
                'recommendations' => [],
                'meta' => ['AI_FLIGHT_SEARCH_READ_CALLS' => 0],
                'freshness_note' => 'Need origin and destination to open flight results.',
            ];
        }

        $calls = 1;
        $tripType = $intent->returnDate ? 'round_trip' : 'one_way';
        $depart = $intent->departDate ?: now()->addDays(7)->toDateString();
        $params = array_filter([
            'trip_type' => $tripType,
            'from' => $intent->origin,
            'to' => $intent->destination,
            'depart' => $depart,
            'return_date' => $intent->returnDate,
            'adults' => (string) $intent->adults,
            'children' => (string) $intent->children,
            'infants' => (string) $intent->infants,
            'cabin' => $intent->cabin ?: 'economy',
        ], static fn ($v) => $v !== null && $v !== '');

        $resultsUrl = '/flights/results?'.http_build_query($params);

        $link = $this->shareLinks->createFlightLink([
            'origin' => $intent->origin,
            'destination' => $intent->destination,
            'depart_date' => $depart,
            'return_date' => $intent->returnDate,
            'trip_type' => $tripType,
            'adults' => $intent->adults,
            'children' => $intent->children,
            'infants' => $intent->infants,
            'cabin' => $intent->cabin ?: 'economy',
            'display_currency' => $intent->currency,
            'airline_code' => $intent->airline,
            'payload' => ['source' => 'ai_assistant'],
        ], 'ai_assistant');

        $shortPath = $link->publicPath();
        $offerStub = [
            [
                'id' => 'deep-link-'.$intent->origin.'-'.$intent->destination,
                'price' => 0,
                'duration_minutes' => 0,
                'stops' => $intent->maxStops ?? 1,
            ],
        ];
        // Ranking reserved for live offer arrays; deep-link path has no live fares.
        $this->ranking->rank($offerStub);

        $recommendations[] = [
            'id' => 'flight-search-'.$intent->origin.'-'.$intent->destination,
            'type' => 'flight_search',
            'title' => $intent->origin.' → '.$intent->destination,
            'subtitle' => 'Depart '.$depart.($intent->returnDate ? ' · Return '.$intent->returnDate : ''),
            'labels' => ['BEST_MATCH'],
            'view_and_book_url' => $shortPath,
            'results_url' => $resultsUrl,
            'price' => null,
            'currency' => $intent->currency,
        ];

        return [
            'recommendations' => array_slice($recommendations, 0, 4),
            'meta' => ['AI_FLIGHT_SEARCH_READ_CALLS' => $calls],
            'freshness_note' => 'Fares change quickly. Open View & Book to see current prices — I do not invent fares.',
        ];
    }

    /**
     * @return array{
     *   recommendations: list<array<string, mixed>>,
     *   meta: array{AI_GROUP_SEARCH_READ_CALLS: int},
     *   freshness_note: string
     * }
     */
    public function searchGroups(TravelIntent $intent): array
    {
        if (! (bool) config('ota.ai_assistant.groups_enabled', true)) {
            return [
                'recommendations' => [],
                'meta' => ['AI_GROUP_SEARCH_READ_CALLS' => 0],
                'freshness_note' => 'Group search is temporarily unavailable.',
            ];
        }

        $calls = 1;
        $filters = [];
        if ($intent->origin && $intent->destination) {
            $filters['sector'] = $intent->origin.'-'.$intent->destination;
        }
        if ($intent->departDate) {
            $filters['dept_date'] = $intent->departDate;
        }
        if ($intent->airline) {
            $filters['airline'] = $intent->airline;
        }
        if ($intent->budget !== null) {
            $filters['price_max'] = $intent->budget;
        }

        $rows = $this->groups->search($filters)->take(8);
        $offers = [];
        foreach ($rows as $inv) {
            $offers[] = [
                'id' => (string) ($inv->public_id ?: $inv->getKey()),
                'price' => (float) $inv->price,
                'duration_minutes' => 0,
                'stops' => 0,
                '_model' => $inv,
            ];
        }

        $ranked = $offers === [] ? [] : $this->ranking->rank(array_map(
            static fn (array $o): array => [
                'id' => $o['id'],
                'price' => $o['price'],
                'duration_minutes' => $o['duration_minutes'],
                'stops' => $o['stops'],
            ],
            $offers
        ));
        $byId = [];
        foreach ($offers as $o) {
            $byId[$o['id']] = $o['_model'];
        }
        foreach ($ranked as &$r) {
            $r['labels'] = $r['labels'] ?? [];
        }
        unset($r);

        $picked = $ranked === [] ? [] : $this->ranking->topRecommendations($ranked, 4);
        $recommendations = [];
        foreach ($picked as $row) {
            $inv = $byId[$row['id']] ?? null;
            if ($inv === null) {
                continue;
            }
            $packageId = (string) ($inv->public_id ?: $inv->getKey());
            $link = $this->shareLinks->createGroupLink([
                'origin' => $intent->origin,
                'destination' => $intent->destination,
                'depart_date' => $inv->departure_date?->format('Y-m-d'),
                'display_currency' => 'PKR',
                'display_fare' => $inv->price,
                'airline_name' => $inv->airline_name,
                'payload' => [
                    'package_id' => $packageId,
                    'source' => 'ai_assistant',
                ],
            ], 'ai_assistant');

            $recommendations[] = [
                'id' => $packageId,
                'type' => 'group_offer',
                'title' => (string) ($inv->title ?: ($inv->sector ?: 'Group package')),
                'subtitle' => trim(($inv->airline_name ?: '').' · '.($inv->departure_date?->format('Y-m-d') ?: '')),
                'labels' => $row['labels'],
                'view_and_book_url' => $link->publicPath(),
                'package_url' => '/groups/'.$packageId,
                'price' => (float) $inv->price,
                'currency' => 'PKR',
                'seats_left' => max(0, (int) $inv->total_seats - (int) $inv->held_seats - (int) $inv->sold_seats),
            ];
        }

        return [
            'recommendations' => $recommendations,
            'meta' => ['AI_GROUP_SEARCH_READ_CALLS' => $calls],
            'freshness_note' => 'Group seats and prices can change. Open View & Book to confirm current availability.',
        ];
    }
}
