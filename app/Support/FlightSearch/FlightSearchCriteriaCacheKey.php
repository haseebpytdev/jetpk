<?php

namespace App\Support\FlightSearch;

use App\Models\Agency;

/**
 * Deterministic flight-search cache-key builder isolating every dimension that can change offers.
 *
 * Safe summaries exclude secrets and passenger-identifying data. Used for supplier-result cache,
 * nearby-date strip cache, and search-session diagnostics.
 */
final class FlightSearchCriteriaCacheKey
{
    public const SCHEMA_VERSION = 'v1';

    public const CACHE_PREFIX = 'flight_search_criteria:';

    public const NEARBY_STRIP_PREFIX = 'nearby_date_strip:';

    /**
     * @param  array<string, mixed>  $criteria
     * @param  array<string, mixed>  $context
     * @return array{
     *     fingerprint: string,
     *     cache_key: string,
     *     summary: array<string, mixed>,
     *     normalized: array<string, mixed>
     * }
     */
    public function build(array $criteria, array $context = []): array
    {
        $normalized = $this->normalize($criteria, $context);
        $encoded = json_encode($normalized, JSON_THROW_ON_ERROR);
        $fingerprint = hash('sha256', $encoded);

        return [
            'fingerprint' => $fingerprint,
            'cache_key' => self::CACHE_PREFIX.$fingerprint,
            'summary' => $this->safeSummary($normalized),
            'normalized' => $normalized,
        ];
    }

    /**
     * @param  array<string, mixed>  $criteria
     * @param  array<string, mixed>  $context
     */
    public function buildNearbyStripKey(array $criteria, Agency $agency, string $selectedDepartDate, int $radiusDays, array $context = []): string
    {
        $stripCriteria = array_merge($criteria, [
            'depart_date' => $selectedDepartDate,
            'departure_date' => $selectedDepartDate,
        ]);

        $built = $this->build($stripCriteria, array_merge($context, [
            'agency_id' => $agency->id,
            'cache_lane' => 'nearby_date_strip',
            'nearby_strip_radius_days' => $radiusDays,
        ]));

        return self::NEARBY_STRIP_PREFIX.$built['fingerprint'];
    }

    /**
     * @param  array<string, mixed>  $criteria
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function normalize(array $criteria, array $context = []): array
    {
        $tripType = $this->normalizeTripType($criteria);
        $segments = $this->normalizeSegments($criteria, $tripType);

        $requestedOrigin = strtoupper(trim((string) (
            $criteria['requested_origin']
            ?? $criteria['origin']
            ?? $criteria['from']
            ?? ''
        )));
        $searchOrigin = strtoupper(trim((string) (
            $criteria['search_origin_variant']
            ?? $criteria['origin']
            ?? $criteria['from']
            ?? ''
        )));

        $destination = strtoupper(trim((string) ($criteria['destination'] ?? $criteria['to'] ?? '')));
        $returnOrigin = strtoupper(trim((string) ($criteria['return_origin'] ?? $requestedOrigin)));

        $departDate = $this->normalizeDate((string) ($criteria['depart_date'] ?? $criteria['departure_date'] ?? $criteria['depart'] ?? ''));
        $returnDate = $tripType === 'round_trip'
            ? $this->normalizeDate((string) ($criteria['return_date'] ?? ''))
            : null;

        $counts = TravellerCountRules::normalizeCounts(
            (int) ($criteria['adults'] ?? 1),
            (int) ($criteria['children'] ?? 0),
            (int) ($criteria['infants'] ?? 0),
        );

        $connectionScope = $this->normalizeConnectionScope($context['supplier_connection_scope'] ?? null);

        return [
            'schema_version' => self::SCHEMA_VERSION,
            'client_slug' => $this->normalizeSlug($context['client_slug'] ?? current_client_slug()),
            'agency_id' => $this->normalizePositiveInt($context['agency_id'] ?? $criteria['agency_id'] ?? null),
            'source_channel' => $this->normalizeChannel((string) ($context['source_channel'] ?? $criteria['source_channel'] ?? 'public_guest')),
            'agent_id' => $this->normalizePositiveInt($context['agent_id'] ?? $criteria['agent_id'] ?? null),
            'app_environment' => $this->normalizeEnvironment((string) ($context['app_environment'] ?? app()->environment())),
            'supplier_lane' => $this->normalizeSupplierLane((string) ($context['supplier_lane'] ?? 'all')),
            'supplier_connection_scope' => $connectionScope,
            'point_of_sale' => strtoupper(trim((string) ($context['point_of_sale'] ?? $criteria['point_of_sale'] ?? ''))),
            'trip_type' => $tripType,
            'requested_origin' => $requestedOrigin,
            'search_origin' => $searchOrigin,
            'destination' => $destination,
            'return_origin' => $returnOrigin,
            'depart_date' => $departDate,
            'return_date' => $returnDate,
            'segments' => $segments,
            'adults' => $counts['adults'],
            'children' => $counts['children'],
            'infants' => $counts['infants'],
            'cabin' => $this->normalizeCabin((string) ($criteria['cabin'] ?? 'economy')),
            'currency' => strtoupper(trim((string) ($criteria['currency'] ?? 'PKR'))) ?: 'PKR',
            'direct_only' => filter_var($criteria['direct_only'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'nearby_airports' => filter_var($criteria['nearby_airports'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'flexible_dates' => filter_var($criteria['flexible_dates'] ?? $criteria['flexible_date'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'preferred_airlines' => $this->normalizeAirlineList($criteria['preferred_airlines'] ?? $criteria['airlines'] ?? []),
            'excluded_airlines' => $this->normalizeAirlineList($criteria['excluded_airlines'] ?? []),
            'branded_fare_mode' => (string) ($context['branded_fare_mode'] ?? $criteria['branded_fare_mode'] ?? 'default'),
            'result_source' => (string) ($context['result_source'] ?? $criteria['result_source'] ?? 'public_search'),
            'search_mode' => (string) ($context['search_mode'] ?? $criteria['search_mode'] ?? 'standard'),
            'cache_lane' => (string) ($context['cache_lane'] ?? 'supplier_search'),
            'nearby_strip_radius_days' => isset($context['nearby_strip_radius_days'])
                ? max(0, (int) $context['nearby_strip_radius_days'])
                : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $normalized
     * @return array<string, mixed>
     */
    public function safeSummary(array $normalized): array
    {
        return [
            'schema_version' => $normalized['schema_version'] ?? self::SCHEMA_VERSION,
            'client_slug' => $normalized['client_slug'] ?? null,
            'agency_id' => $normalized['agency_id'] ?? null,
            'source_channel' => $normalized['source_channel'] ?? null,
            'supplier_lane' => $normalized['supplier_lane'] ?? null,
            'trip_type' => $normalized['trip_type'] ?? null,
            'requested_origin' => $normalized['requested_origin'] ?? null,
            'search_origin' => $normalized['search_origin'] ?? null,
            'destination' => $normalized['destination'] ?? null,
            'depart_date' => $normalized['depart_date'] ?? null,
            'return_date' => $normalized['return_date'] ?? null,
            'adults' => $normalized['adults'] ?? null,
            'children' => $normalized['children'] ?? null,
            'infants' => $normalized['infants'] ?? null,
            'cabin' => $normalized['cabin'] ?? null,
            'currency' => $normalized['currency'] ?? null,
            'direct_only' => $normalized['direct_only'] ?? false,
            'nearby_airports' => $normalized['nearby_airports'] ?? false,
            'flexible_dates' => $normalized['flexible_dates'] ?? false,
            'cache_lane' => $normalized['cache_lane'] ?? null,
            'fingerprint_prefix' => isset($normalized['schema_version'])
                ? substr(hash('sha256', json_encode($normalized, JSON_THROW_ON_ERROR)), 0, 12)
                : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $criteria
     */
    protected function normalizeTripType(array $criteria): string
    {
        $trip = strtolower(trim((string) ($criteria['trip_type'] ?? 'one_way')));

        return match ($trip) {
            'round_trip', 'return' => 'round_trip',
            'multi_city', 'multicity' => 'multi_city',
            default => 'one_way',
        };
    }

    /**
     * @param  array<string, mixed>  $criteria
     * @return list<array{origin: string, destination: string, departure_date: string}>
     */
    protected function normalizeSegments(array $criteria, string $tripType): array
    {
        if ($tripType !== 'multi_city') {
            return [];
        }

        $raw = $criteria['segments'] ?? null;
        if (! is_array($raw) || $raw === []) {
            return [];
        }

        $segments = [];
        foreach ($raw as $row) {
            if (! is_array($row)) {
                continue;
            }
            $segments[] = [
                'origin' => strtoupper(trim((string) ($row['origin'] ?? ''))),
                'destination' => strtoupper(trim((string) ($row['destination'] ?? ''))),
                'departure_date' => $this->normalizeDate((string) ($row['departure_date'] ?? '')),
            ];
        }

        return $segments;
    }

    /**
     * @param  mixed  $scope
     * @return list<array{connection_id: int, provider: string, lanes: list<string>}>
     */
    protected function normalizeConnectionScope(mixed $scope): array
    {
        if (! is_array($scope) || $scope === []) {
            return [];
        }

        $rows = [];
        foreach ($scope as $row) {
            if (! is_array($row)) {
                continue;
            }
            $connectionId = (int) ($row['connection_id'] ?? 0);
            if ($connectionId <= 0) {
                continue;
            }
            $lanes = is_array($row['lanes'] ?? null) ? $row['lanes'] : [];
            $lanes = array_values(array_unique(array_map(
                static fn ($lane): string => strtolower(trim((string) $lane)),
                $lanes
            )));
            sort($lanes);
            $rows[] = [
                'connection_id' => $connectionId,
                'provider' => strtolower(trim((string) ($row['provider'] ?? ''))),
                'lanes' => $lanes,
            ];
        }

        usort($rows, static fn (array $a, array $b): int => $a['connection_id'] <=> $b['connection_id']);

        return $rows;
    }

    /**
     * @param  mixed  $airlines
     * @return list<string>
     */
    protected function normalizeAirlineList(mixed $airlines): array
    {
        if (! is_array($airlines)) {
            return [];
        }

        $out = [];
        foreach ($airlines as $code) {
            $normalized = strtoupper(trim((string) $code));
            if ($normalized !== '' && preg_match('/^[A-Z0-9]{2}$/', $normalized) === 1) {
                $out[] = $normalized;
            }
        }

        sort($out);

        return array_values(array_unique($out));
    }

    protected function normalizeDate(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        try {
            return \Carbon\Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return $value;
        }
    }

    protected function normalizeSlug(mixed $slug): ?string
    {
        if (! is_string($slug)) {
            return null;
        }

        $slug = strtolower(trim($slug));

        return $slug !== '' ? $slug : null;
    }

    protected function normalizePositiveInt(mixed $value): ?int
    {
        $int = (int) $value;

        return $int > 0 ? $int : null;
    }

    protected function normalizeChannel(string $channel): string
    {
        $channel = strtolower(trim($channel));

        return $channel !== '' ? $channel : 'public_guest';
    }

    protected function normalizeEnvironment(string $environment): string
    {
        $environment = strtolower(trim($environment));

        return $environment !== '' ? $environment : 'production';
    }

    protected function normalizeSupplierLane(string $lane): string
    {
        $lane = strtolower(trim($lane));

        return in_array($lane, ['gds', 'ndc', 'all'], true) ? $lane : 'all';
    }

    protected function normalizeCabin(string $cabin): string
    {
        $cabin = strtolower(trim(str_replace('-', '_', $cabin)));

        return in_array($cabin, ['economy', 'premium_economy', 'business', 'first'], true)
            ? $cabin
            : 'economy';
    }
}
