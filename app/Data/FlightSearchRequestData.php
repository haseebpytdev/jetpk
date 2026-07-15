<?php

namespace App\Data;

use App\Support\FlightSearch\TravellerCountRules;

class FlightSearchRequestData
{
    /**
     * @param  list<array{origin: string, destination: string, departure_date: string}>|null  $segments
     */
    public function __construct(
        public string $origin,
        public string $destination,
        public string $departure_date,
        public ?string $return_date = null,
        public string $trip_type = 'one_way',
        public int $adults = 1,
        public int $children = 0,
        public int $infants = 0,
        public string $cabin = 'economy',
        public string $currency = 'PKR',
        public ?int $agency_id = null,
        public string $source_channel = 'public_guest',
        public ?string $search_id = null,
        public ?array $segments = null,
        public bool $direct_only = false,
        public ?string $return_origin = null,
    ) {}

    public function returnOrigin(): string
    {
        $preferred = strtoupper(trim((string) ($this->return_origin ?? '')));

        return $preferred !== '' ? $preferred : $this->origin;
    }

    /**
     * @param  array<string, mixed>  $criteria
     */
    public static function fromArray(array $criteria, ?int $agencyId = null, string $sourceChannel = 'public_guest'): self
    {
        $tripType = (string) ($criteria['trip_type'] ?? 'one_way');
        $segments = self::normalizeSegments($criteria['segments'] ?? null);
        $directOnly = filter_var($criteria['direct_only'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $requestedOrigin = strtoupper(trim((string) ($criteria['requested_origin'] ?? $criteria['origin'] ?? '')));
        $searchOrigin = strtoupper(trim((string) ($criteria['origin'] ?? '')));
        $returnOrigin = $requestedOrigin !== '' ? $requestedOrigin : $searchOrigin;

        if ($tripType === 'multi_city' && $segments !== null && $segments !== []) {
            $first = $segments[0];
            $counts = TravellerCountRules::normalizeCounts(
                (int) ($criteria['adults'] ?? 1),
                (int) ($criteria['children'] ?? 0),
                (int) ($criteria['infants'] ?? 0),
            );

            return new self(
                origin: strtoupper(trim((string) ($first['origin'] ?? ''))),
                destination: strtoupper(trim((string) ($first['destination'] ?? ''))),
                departure_date: (string) ($first['departure_date'] ?? ''),
                return_date: null,
                trip_type: 'multi_city',
                adults: $counts['adults'],
                children: $counts['children'],
                infants: $counts['infants'],
                cabin: (string) ($criteria['cabin'] ?? 'economy'),
                currency: (string) ($criteria['currency'] ?? 'PKR'),
                agency_id: $agencyId,
                source_channel: $sourceChannel,
                search_id: self::searchIdFromCriteria($criteria),
                segments: $segments,
                direct_only: $directOnly,
                return_origin: $returnOrigin !== '' ? $returnOrigin : null,
            );
        }

        $depart = (string) ($criteria['depart_date'] ?? $criteria['departure_date'] ?? '');
        $origin = $searchOrigin;
        $destination = strtoupper(trim((string) ($criteria['destination'] ?? '')));
        $counts = TravellerCountRules::normalizeCounts(
            (int) ($criteria['adults'] ?? 1),
            (int) ($criteria['children'] ?? 0),
            (int) ($criteria['infants'] ?? 0),
        );

        return new self(
            origin: $origin,
            destination: $destination,
            departure_date: $depart,
            return_date: isset($criteria['return_date']) ? (string) $criteria['return_date'] : null,
            trip_type: $tripType,
            adults: $counts['adults'],
            children: $counts['children'],
            infants: $counts['infants'],
            cabin: (string) ($criteria['cabin'] ?? 'economy'),
            currency: (string) ($criteria['currency'] ?? 'PKR'),
            agency_id: $agencyId,
            source_channel: $sourceChannel,
            search_id: self::searchIdFromCriteria($criteria),
            segments: null,
            direct_only: $directOnly,
            return_origin: $returnOrigin !== '' ? $returnOrigin : null,
        );
    }

    /**
     * @param  array<string, mixed>  $criteria
     */
    protected static function searchIdFromCriteria(array $criteria): ?string
    {
        $id = trim((string) ($criteria['search_id'] ?? ''));

        return $id !== '' ? $id : null;
    }

    /**
     * @return list<array{origin: string, destination: string, departure_date: string}>|null
     */
    protected static function normalizeSegments(mixed $raw): ?array
    {
        if (! is_array($raw) || $raw === []) {
            return null;
        }

        $out = [];
        foreach ($raw as $row) {
            if (! is_array($row)) {
                continue;
            }
            $out[] = [
                'origin' => strtoupper(trim((string) ($row['origin'] ?? ''))),
                'destination' => strtoupper(trim((string) ($row['destination'] ?? ''))),
                'departure_date' => trim((string) ($row['departure_date'] ?? '')),
            ];
        }

        return $out === [] ? null : $out;
    }
}
