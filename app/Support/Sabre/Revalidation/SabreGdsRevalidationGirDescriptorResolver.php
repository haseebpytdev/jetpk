<?php

namespace App\Support\Sabre\Revalidation;

/**
 * Authoritative Sabre groupedItineraryResponse descriptor resolution for revalidation linkage.
 * Uses numeric id/ref maps only (no array-position or ref-minus-one fallback).
 */
final class SabreGdsRevalidationGirDescriptorResolver
{
    /**
     * @param  mixed  $rows
     * @return list<array<string, mixed>>
     */
    public function listDescRows(mixed $rows): array
    {
        if (! is_array($rows)) {
            return [];
        }

        return array_values(array_filter($rows, static fn ($row): bool => is_array($row)));
    }

    /**
     * @param  list<array<string, mixed>>  $descList
     * @return array{
     *     rows: list<array<string, mixed>>,
     *     lookup: array<int, array<string, mixed>>,
     *     explicit: bool,
     *     ambiguous_keys: list<int>
     * }
     */
    public function buildResolutionSlice(array $descList): array
    {
        $rows = array_values(array_filter($descList, static fn ($row): bool => is_array($row)));
        $lookup = [];
        $ambiguousKeys = [];
        $explicit = false;
        foreach ($rows as $item) {
            foreach (['id', 'ref'] as $key) {
                if (! array_key_exists($key, $item)) {
                    continue;
                }
                $value = $item[$key];
                if (! is_numeric($value)) {
                    continue;
                }
                $explicit = true;
                $intKey = (int) $value;
                if (isset($lookup[$intKey]) && $lookup[$intKey] !== $item) {
                    $ambiguousKeys[$intKey] = true;
                }
                $lookup[$intKey] = $item;
            }
        }

        return [
            'rows' => $rows,
            'lookup' => $lookup,
            'explicit' => $explicit,
            'ambiguous_keys' => array_keys($ambiguousKeys),
        ];
    }

    /**
     * @param  array{
     *     rows: list<array<string, mixed>>,
     *     lookup: array<int, array<string, mixed>>,
     *     explicit: bool,
     *     ambiguous_keys?: list<int>
     * }  $slice
     */
    public function resolveDescriptor(array $slice, int $ref): ?array
    {
        if ($ref < 0) {
            return null;
        }
        if (in_array($ref, $slice['ambiguous_keys'] ?? [], true)) {
            return null;
        }

        return $slice['lookup'][$ref] ?? null;
    }

    public function lookupModeForResolution(array $slice, int $ref, ?array $resolved): string
    {
        if ($ref < 0 || $resolved === null) {
            return 'invalid';
        }
        if (in_array($ref, $slice['ambiguous_keys'] ?? [], true)) {
            return 'invalid';
        }
        if (isset($slice['lookup'][$ref])) {
            return 'id_map';
        }

        return 'invalid';
    }

    /**
     * @param  array<string, mixed>|scalar  $legWrap
     */
    public function legDescriptorRefFromWrap(mixed $legWrap): int
    {
        if (! is_array($legWrap)) {
            return is_numeric($legWrap) ? (int) $legWrap : -1;
        }
        foreach (['id', 'ref'] as $key) {
            if (isset($legWrap[$key]) && is_numeric($legWrap[$key])) {
                return (int) $legWrap[$key];
            }
        }

        return -1;
    }

    /**
     * @param  array<string, mixed>|scalar  $schedWrap
     */
    public function scheduleRefFromLegScheduleWrap(mixed $schedWrap): int
    {
        if (! is_array($schedWrap)) {
            return is_numeric($schedWrap) ? (int) $schedWrap : -1;
        }
        foreach (['ref', 'scheduleRef', 'scheduleDescRef', 'id'] as $key) {
            if (isset($schedWrap[$key]) && is_numeric($schedWrap[$key])) {
                return (int) $schedWrap[$key];
            }
        }
        $nested = $schedWrap['schedule'] ?? null;
        if (is_array($nested)) {
            foreach (['ref', 'scheduleRef', 'scheduleDescRef', 'id'] as $key) {
                if (isset($nested[$key]) && is_numeric($nested[$key])) {
                    return (int) $nested[$key];
                }
            }
        }

        return -1;
    }

    /**
     * @param  array<string, mixed>|scalar  $wrap
     */
    public function referenceWrapCategory(mixed $wrap, string $kind): string
    {
        if (! is_array($wrap)) {
            return is_numeric($wrap) ? 'scalar_ref' : 'invalid';
        }
        if ($kind === 'leg') {
            foreach (['id', 'ref'] as $key) {
                if (isset($wrap[$key]) && is_numeric($wrap[$key])) {
                    return $key === 'id' ? 'nested_id' : 'nested_ref';
                }
            }

            return 'invalid';
        }
        foreach (['ref', 'scheduleRef', 'scheduleDescRef', 'id'] as $key) {
            if (isset($wrap[$key]) && is_numeric($wrap[$key])) {
                return 'nested_'.$key;
            }
        }
        if (is_array($wrap['schedule'] ?? null)) {
            return 'nested_schedule_object';
        }

        return 'invalid';
    }

    /**
     * @param  list<array<string, mixed>>  $scheduleRows
     * @return list<array<string, mixed>>
     */
    public function safeScheduleSummaries(
        array $scheduleRows,
        SabreGdsRevalidationCanonicalSegmentSignature $canonical,
    ): array {
        $summaries = [];
        foreach ($scheduleRows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $summaries[] = $this->safeScheduleSummary($row, $canonical);
        }

        return $summaries;
    }

    /**
     * @param  array<string, mixed>  $schedule
     * @return array<string, mixed>
     */
    public function safeScheduleSummary(
        array $schedule,
        SabreGdsRevalidationCanonicalSegmentSignature $canonical,
    ): array {
        $segment = $canonical->segmentRowFromScheduleDesc($schedule);

        return array_filter([
            'origin' => $segment['origin'] ?? null,
            'destination' => $segment['destination'] ?? null,
            'marketing_carrier' => $segment['marketing_carrier'] ?? null,
            'operating_carrier_shape_category' => $segment['operating_carrier_shape_category'] ?? null,
            'flight_number' => $segment['flight_number'] ?? null,
            'departure_local_time' => $canonical->comparableWallClock((string) ($segment['departure_at'] ?? '')) ?: null,
            'arrival_local_time' => $canonical->comparableWallClock((string) ($segment['arrival_at'] ?? '')) ?: null,
            'departure_date_adjustment_days' => $canonical->scheduleEndpointDateAdjustmentDays($schedule, 'departure'),
            'arrival_date_adjustment_days' => $canonical->scheduleEndpointDateAdjustmentDays($schedule, 'arrival'),
            'descriptor_lookup_key_digest' => $this->descriptorLookupKeyDigest($schedule),
        ], static fn ($value) => $value !== null && $value !== '');
    }

    /**
     * @param  list<array<string, mixed>>  $scheduleRows
     */
    public function countMatchingScheduleSummaries(
        array $scheduleRows,
        SabreGdsRevalidationCanonicalSegmentSignature $canonical,
        string $origin,
        string $destination,
        string $marketingCarrier,
        string $flightNumber,
    ): int {
        $origin = strtoupper(trim($origin));
        $destination = strtoupper(trim($destination));
        $marketingCarrier = strtoupper(trim($marketingCarrier));
        $flightNumber = $canonical->normalizeFlightNumber($flightNumber);
        $count = 0;
        foreach ($scheduleRows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $summary = $this->safeScheduleSummary($row, $canonical);
            if (($summary['origin'] ?? '') !== $origin || ($summary['destination'] ?? '') !== $destination) {
                continue;
            }
            if ($marketingCarrier !== '' && ($summary['marketing_carrier'] ?? '') !== $marketingCarrier) {
                continue;
            }
            if ($flightNumber !== '' && $canonical->normalizeFlightNumber((string) ($summary['flight_number'] ?? '')) !== $flightNumber) {
                continue;
            }
            $count++;
        }

        return $count;
    }

    /**
     * @param  array<string, mixed>  $schedule
     */
    public function descriptorLookupKeyDigest(array $schedule): string
    {
        $keys = [];
        foreach (['id', 'ref'] as $key) {
            if (isset($schedule[$key]) && is_numeric($schedule[$key])) {
                $keys[] = $key.':'.(int) $schedule[$key];
            }
        }
        sort($keys);

        return $keys === [] ? '' : hash('sha256', implode('|', $keys));
    }

    public function resolvedDescriptorOrdinalCategory(int $ref): string
    {
        return $ref >= 0 ? 'numeric_ref_normalized' : 'invalid';
    }
}
