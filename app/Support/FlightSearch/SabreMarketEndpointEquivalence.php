<?php

namespace App\Support\FlightSearch;

/**
 * Safe market endpoint equivalence for Sabre normalization (DXB/XNB surface, etc.).
 */
final class SabreMarketEndpointEquivalence
{
    /** @var array<string, list<string>> */
    private const EQUIVALENCE_GROUPS = [
        'DXB' => ['DXB', 'XNB'],
        'AUH' => ['AUH', 'AZI'],
    ];

    public static function canonical(string $code): string
    {
        $upper = strtoupper(trim($code));
        if ($upper === '') {
            return '';
        }

        foreach (self::EQUIVALENCE_GROUPS as $canonical => $aliases) {
            if (in_array($upper, $aliases, true)) {
                return $canonical;
            }
        }

        return $upper;
    }

    public static function areEquivalent(string $a, string $b): bool
    {
        $aCanon = self::canonical($a);
        $bCanon = self::canonical($b);
        if ($aCanon === '' || $bCanon === '') {
            return false;
        }

        return $aCanon === $bCanon;
    }

    public static function endpointMatchesRequested(string $offerEndpoint, string $requested): bool
    {
        $offer = strtoupper(trim($offerEndpoint));
        $req = strtoupper(trim($requested));
        if ($offer === '' || $req === '') {
            return true;
        }

        return $offer === $req || self::areEquivalent($offer, $req);
    }

    /**
     * @param  list<\App\Data\FlightSegmentData>|list<array<string, mixed>>  $segments
     */
    public static function itineraryTouchesRequestedMarket(array $segments, string $requested): bool
    {
        $req = strtoupper(trim($requested));
        if ($req === '') {
            return true;
        }

        foreach ($segments as $segment) {
            $origin = '';
            $destination = '';
            if (is_array($segment)) {
                $origin = strtoupper(trim((string) ($segment['origin'] ?? '')));
                $destination = strtoupper(trim((string) ($segment['destination'] ?? '')));
            } elseif ($segment instanceof \App\Data\FlightSegmentData) {
                $origin = strtoupper(trim($segment->origin));
                $destination = strtoupper(trim($segment->destination));
            }

            if (self::endpointMatchesRequested($origin, $req) || self::endpointMatchesRequested($destination, $req)) {
                return true;
            }
        }

        return false;
    }
}
