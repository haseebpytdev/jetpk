<?php

namespace App\Services\Suppliers\Sabre\Gds;

use App\Data\FlightSearchRequestData;
use App\Data\NormalizedFlightOfferData;
use App\Models\SupplierConnection;
use App\Services\Suppliers\Sabre\Core\SabreClient;
use App\Support\FlightSearch\FlightOfferDisplayPresenter;
use Carbon\CarbonImmutable;

/**
 * B77: Per-segment OW Offers shop vs stored snapshot (same matching rules as B76 diagnostic), for pre-PNR stale-inventory guard.
 */
class SabreSegmentFreshShopSellabilityService
{
    public function __construct(
        protected SabreFlightSearchRequestBuilder $builder,
        protected SabreClient $client,
        protected SabreFlightSearchNormalizer $normalizer,
    ) {}

    /**
     * @param  array<string, mixed>  $offer  Normalized/validated offer snapshot
     * @return list<array<string, mixed>> Segment-shaped reports (see B76 JSON)
     */
    public function segmentReportsForOffer(array $offer, SupplierConnection $connection): array
    {
        $criteria = is_array($offer['search_criteria'] ?? null) ? $offer['search_criteria'] : [];
        $counts = is_array($offer['fare_breakdown']['passenger_counts'] ?? null)
            ? $offer['fare_breakdown']['passenger_counts']
            : [];
        $adults = max(1, (int) ($criteria['adults'] ?? $criteria['adult_count'] ?? $counts['adults'] ?? 1));
        $children = max(0, (int) ($criteria['children'] ?? $criteria['child_count'] ?? $counts['children'] ?? 0));
        $infants = max(0, (int) ($criteria['infants'] ?? $criteria['infant_count'] ?? $counts['infants'] ?? 0));
        $cabin = trim((string) ($criteria['cabin'] ?? $criteria['cabin_class'] ?? 'economy'));
        if ($cabin === '') {
            $cabin = 'economy';
        }
        $currency = trim((string) (
            $offer['currency']
            ?? $offer['fare_breakdown']['currency'] ?? $criteria['currency'] ?? 'PKR'
        ));
        if ($currency === '') {
            $currency = 'PKR';
        }

        $segments = $this->extractStoredSegmentsFromOfferSnapshot($offer);
        $reports = [];
        foreach ($segments as $idx => $stored) {
            $reports[] = $this->analyzeSegment(
                $idx,
                $stored,
                $adults,
                $children,
                $infants,
                $cabin,
                $currency,
                $connection,
            );
        }

        return $reports;
    }

    /**
     * B77 pass rule: flight + time match; if snapshot has RBD, require same RBD at same time.
     *
     * @param  array<string, mixed>  $segmentReport  One element from {@see segmentReportsForOffer()}
     */
    public function segmentPassesPnrFreshShopGuard(array $segmentReport): bool
    {
        if (($segmentReport['fresh_flight_found'] ?? false) !== true) {
            return false;
        }
        if (($segmentReport['fresh_same_time_found'] ?? false) !== true) {
            return false;
        }
        $origRbd = $segmentReport['original_rbd'] ?? null;
        if (is_string($origRbd) && trim($origRbd) !== '') {
            return ($segmentReport['fresh_same_rbd_found'] ?? null) === true;
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $offer
     * @return list<array<string, mixed>>
     */
    public function extractStoredSegmentsFromOfferSnapshot(array $offer): array
    {
        $raw = $offer['segments'] ?? [];
        if (! is_array($raw)) {
            return [];
        }
        $ordered = array_values(array_filter($raw, fn ($x): bool => is_array($x)));
        if ($ordered !== [] && ! FlightOfferDisplayPresenter::shouldPreserveOfferSegmentOrder($offer)) {
            usort($ordered, function (array $a, array $b): int {
                $da = trim((string) ($a['departure_at'] ?? $a['depart_at'] ?? ''));
                $db = trim((string) ($b['departure_at'] ?? $b['depart_at'] ?? ''));

                return strcmp($da, $db);
            });
        }

        $out = [];
        foreach ($ordered as $seg) {
            if (! is_array($seg)) {
                continue;
            }
            $carrier = strtoupper(trim((string) ($seg['airline_code'] ?? $seg['carrier'] ?? '')));
            $flightNum = trim((string) ($seg['flight_number'] ?? ''));
            $origin = strtoupper(trim((string) ($seg['origin'] ?? '')));
            $destination = strtoupper(trim((string) ($seg['destination'] ?? '')));
            $departureAt = trim((string) ($seg['departure_at'] ?? $seg['depart_at'] ?? ''));

            if ($origin === '' || $destination === '' || $departureAt === '') {
                continue;
            }

            $out[] = [
                'origin' => $origin,
                'destination' => $destination,
                'departure_at' => $departureAt,
                'carrier' => $carrier,
                'flight_number' => $flightNum,
                'booking_class' => isset($seg['booking_class']) ? strtoupper(trim((string) $seg['booking_class'])) : '',
                'fare_basis_code' => isset($seg['fare_basis_code']) ? trim((string) $seg['fare_basis_code']) : '',
                'marketing_flight_label' => self::marketingFlightLabel($carrier, $flightNum),
            ];
        }

        return $out;
    }

    public static function marketingFlightLabel(?string $carrier, ?string $flightNum): string
    {
        $c = strtoupper(trim((string) $carrier));
        $f = trim((string) $flightNum);
        if ($f === '') {
            return '';
        }
        $fu = strtoupper($f);
        if ($c !== '' && str_starts_with($fu, $c)) {
            return $fu;
        }

        return $c !== '' ? $c.$fu : $fu;
    }

    /**
     * @param  array<string, mixed>  $stored
     * @return array<string, mixed>
     */
    protected function analyzeSegment(
        int $storageIndex,
        array $stored,
        int $adults,
        int $children,
        int $infants,
        string $cabin,
        string $currency,
        SupplierConnection $connection,
    ): array {
        $route = $stored['origin'].'-'.$stored['destination'];
        $departDate = $this->departureDateForShop($stored['departure_at']);

        $request = FlightSearchRequestData::fromArray([
            'origin' => $stored['origin'],
            'destination' => $stored['destination'],
            'depart_date' => $departDate,
            'adults' => $adults,
            'children' => $children,
            'infants' => $infants,
            'cabin' => $cabin,
            'trip_type' => 'one_way',
            'currency' => $currency,
        ]);

        $payload = $this->builder->build($request, $connection);

        try {
            $response = $this->client->postShopPayload($connection, $payload);
        } catch (\Throwable) {
            return $this->segmentShell(
                $storageIndex,
                $route,
                $stored,
                false,
                false,
                null,
                [],
                'shop_request_failed'
            );
        }

        if ($response->failed()) {
            return $this->segmentShell(
                $storageIndex,
                $route,
                $stored,
                false,
                false,
                null,
                [],
                'shop_http_error'
            );
        }

        $json = $response->json();
        if (! is_array($json)) {
            return $this->segmentShell(
                $storageIndex,
                $route,
                $stored,
                false,
                false,
                null,
                [],
                'invalid_shop_json'
            );
        }

        /** @var list<NormalizedFlightOfferData> $offers */
        $offers = $this->normalizer->normalize($json, $connection, $request);
        $flat = $this->flattenNormalizedSegments($offers);

        $targetLabel = $stored['marketing_flight_label'];
        $flightHits = [];
        foreach ($flat as $seg) {
            if (! is_array($seg)) {
                continue;
            }
            if (! $this->segmentMatchesOd($seg, $stored['origin'], $stored['destination'])) {
                continue;
            }
            $mc = strtoupper(trim((string) ($seg['airline_code'] ?? $seg['carrier'] ?? '')));
            $fn = trim((string) ($seg['flight_number'] ?? ''));
            if (self::marketingFlightLabel($mc, $fn) === $targetLabel) {
                $flightHits[] = $seg;
            }
        }

        $freshFlightFound = $flightHits !== [];
        $freshSameTime = false;
        foreach ($flightHits as $hit) {
            if ($this->sameDepartureInstant($stored['departure_at'], (string) ($hit['departure_at'] ?? ''))) {
                $freshSameTime = true;
                break;
            }
        }

        $candidateRbds = [];
        foreach ($flightHits as $hit) {
            $cls = isset($hit['booking_class']) ? strtoupper(trim((string) $hit['booking_class'])) : '';
            if ($cls !== '') {
                $candidateRbds[] = $cls;
            }
        }
        $candidateRbds = $this->sanitizeBookingClassList($candidateRbds);

        $origRbd = $stored['booking_class'];
        $freshSameRbd = null;
        if ($origRbd !== '') {
            $freshSameRbd = false;
            foreach ($flightHits as $hit) {
                if (! $this->sameDepartureInstant($stored['departure_at'], (string) ($hit['departure_at'] ?? ''))) {
                    continue;
                }
                $cls = strtoupper(trim((string) ($hit['booking_class'] ?? '')));
                if ($cls !== '' && $cls === $origRbd) {
                    $freshSameRbd = true;
                    break;
                }
            }
        }

        $issue = $this->resolveProbableIssue(
            count($offers) === 0,
            $freshFlightFound,
            $freshSameTime,
            $origRbd,
            $freshSameRbd,
        );

        return $this->segmentShell(
            $storageIndex,
            $route,
            $stored,
            $freshFlightFound,
            $freshSameTime,
            $freshSameRbd,
            $candidateRbds,
            $issue,
        );
    }

    /**
     * @param  list<NormalizedFlightOfferData>  $offers
     * @return list<array<string, mixed>>
     */
    protected function flattenNormalizedSegments(array $offers): array
    {
        $flat = [];
        foreach ($offers as $offer) {
            if (! $offer instanceof NormalizedFlightOfferData) {
                continue;
            }
            foreach ($offer->segments as $seg) {
                if (is_array($seg)) {
                    $flat[] = $seg;
                }
            }
        }

        return $flat;
    }

    /**
     * @param  array<string, mixed>  $seg
     */
    protected function segmentMatchesOd(array $seg, string $origin, string $destination): bool
    {
        $o = strtoupper(trim((string) ($seg['origin'] ?? '')));
        $d = strtoupper(trim((string) ($seg['destination'] ?? '')));

        return $o === $origin && $d === $destination;
    }

    protected function sameDepartureInstant(string $storedIso, string $freshIso): bool
    {
        $storedIso = trim($storedIso);
        $freshIso = trim($freshIso);
        if ($storedIso === '' || $freshIso === '') {
            return false;
        }

        try {
            $a = CarbonImmutable::parse($storedIso);
            $b = CarbonImmutable::parse($freshIso);
        } catch (\Throwable) {
            return strtoupper($storedIso) === strtoupper($freshIso);
        }

        return abs($a->diffInSeconds($b, false)) <= 120;
    }

    protected function departureDateForShop(string $departureAt): string
    {
        try {
            return CarbonImmutable::parse($departureAt)->format('Y-m-d');
        } catch (\Throwable) {
            return substr($departureAt, 0, 10);
        }
    }

    /**
     * @param  array<string, mixed>  $stored
     * @param  list<string>  $rbds
     * @return array<string, mixed>
     */
    protected function segmentShell(
        int $index,
        string $route,
        array $stored,
        bool $flightFound,
        bool $sameTime,
        ?bool $sameRbd,
        array $rbds,
        string $issue,
    ): array {
        return [
            'index' => $index,
            'route' => $route,
            'flight_number' => $stored['marketing_flight_label'],
            'original_departure' => $stored['departure_at'],
            'original_rbd' => $stored['booking_class'] !== '' ? $stored['booking_class'] : null,
            'fresh_flight_found' => $flightFound,
            'fresh_same_time_found' => $sameTime,
            'fresh_same_rbd_found' => $sameRbd,
            'fresh_candidate_rbd_values_sanitized' => array_values($rbds),
            'probable_issue' => $issue,
        ];
    }

    protected function resolveProbableIssue(
        bool $noNormalizedOffers,
        bool $freshFlightFound,
        bool $freshSameTime,
        string $originalRbd,
        ?bool $freshSameRbd,
    ): string {
        if ($noNormalizedOffers) {
            return 'no_normalized_offers';
        }
        if (! $freshFlightFound) {
            return 'flight_not_in_shop_inventory';
        }
        if (! $freshSameTime) {
            return 'departure_time_mismatch';
        }
        if ($originalRbd !== '' && $freshSameRbd === false) {
            return 'booking_class_mismatch';
        }

        return 'ok';
    }

    /**
     * @param  list<string>  $classes
     * @return list<string>
     */
    protected function sanitizeBookingClassList(array $classes): array
    {
        $keep = [];
        foreach ($classes as $c) {
            $s = strtoupper(trim($c));
            $s = preg_replace('/[^A-Z0-9]/', '', $s) ?? '';
            if ($s !== '' && strlen($s) <= 10) {
                $keep[$s] = true;
            }
        }
        $keys = array_keys($keep);
        sort($keys);

        return $keys;
    }
}
