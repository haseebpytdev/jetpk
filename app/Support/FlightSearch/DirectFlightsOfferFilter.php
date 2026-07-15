<?php

namespace App\Support\FlightSearch;

/**
 * Post-normalization direct-only filter (stops = 0). Does not mutate supplier payloads.
 */
final class DirectFlightsOfferFilter
{
    /**
     * @param  array<string, mixed>  $criteria
     */
    public function isEnabled(array $criteria): bool
    {
        return filter_var($criteria['direct_only'] ?? false, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * @param  list<array<string, mixed>>  $offers
     * @return array{offers: list<array<string, mixed>>, diagnostics: array<string, mixed>}
     */
    public function filterDisplayOffers(array $offers): array
    {
        $before = count($offers);
        $filtered = [];
        foreach ($offers as $offer) {
            if ($this->isDirectOffer($offer)) {
                $filtered[] = $offer;
            }
        }

        return [
            'offers' => $filtered,
            'diagnostics' => [
                'direct_only_filter_enabled' => true,
                'offers_before_direct_filter' => $before,
                'offers_after_direct_filter' => count($filtered),
                'direct_filter_dropped_count' => $before - count($filtered),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $offer
     */
    public function isDirectOffer(array $offer): bool
    {
        if (array_key_exists('stops', $offer)) {
            return max(0, (int) $offer['stops']) === 0;
        }

        $segments = $offer['segments'] ?? null;
        if (! is_array($segments) || $segments === []) {
            return false;
        }

        $outboundSegments = $this->outboundSegments($segments, $offer);

        return count($outboundSegments) <= 1;
    }

    /**
     * @param  list<array<string, mixed>>  $segments
     * @return list<array<string, mixed>>
     */
    protected function outboundSegments(array $segments, array $offer): array
    {
        $outbound = [];
        foreach ($segments as $segment) {
            if (! is_array($segment)) {
                continue;
            }
            $direction = strtolower(trim((string) ($segment['direction'] ?? '')));
            if ($direction === 'return' || $direction === 'inbound') {
                break;
            }
            $outbound[] = $segment;
        }

        if ($outbound !== []) {
            return $outbound;
        }

        $tripType = strtolower(trim((string) ($offer['trip_type'] ?? '')));
        if (in_array($tripType, ['round_trip', 'roundtrip', 'return'], true) && count($segments) >= 2) {
            $half = (int) ceil(count($segments) / 2);

            return array_slice($segments, 0, max(1, $half));
        }

        return $segments;
    }
}
