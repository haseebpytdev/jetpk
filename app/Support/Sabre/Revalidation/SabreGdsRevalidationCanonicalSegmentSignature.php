<?php

namespace App\Support\Sabre\Revalidation;

/**
 * Single canonical segment-signature representation for shop-selected offers, revalidation drafts,
 * and BFM revalidation response candidates. Schedule identity only; fare basis and cabin are gated separately.
 */
final class SabreGdsRevalidationCanonicalSegmentSignature
{
    public const VERSION = 'sabre_gds_revalidation_canonical_segment_signature_v3';

    public const HASH_TUPLE_SCHEMA_VERSION = 'sabre_gds_revalidation_canonical_segment_hash_tuple_v1';

    public const HASH_TUPLE_FIELD_COUNT = 7;

    /**
     * @return list<string>
     */
    public function hashTupleFieldLabels(): array
    {
        return [
            'route_origin',
            'route_destination',
            'marketing_carrier',
            'canonical_operating_carrier_slot',
            'normalized_flight_number',
            'departure_wall_clock',
            'arrival_wall_clock',
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $segments  Ordered segment rows (shop, draft, or normalized response)
     */
    public function hashFromSegments(array $segments): string
    {
        $tuples = $this->scheduleHashTuplesFromSegments($segments);
        if ($tuples === []) {
            return '';
        }

        return hash('sha256', json_encode([
            'canonical_signature_version' => self::VERSION,
            'tuple_schema_version' => self::HASH_TUPLE_SCHEMA_VERSION,
            'segment_tuples' => $tuples,
        ], JSON_THROW_ON_ERROR));
    }

    /**
     * @param  list<array<string, mixed>>  $segments
     * @return list<list<string>>
     */
    public function scheduleHashTuplesFromSegments(array $segments): array
    {
        $tuples = [];
        foreach (array_values($segments) as $segment) {
            if (! is_array($segment)) {
                continue;
            }
            $tuples[] = $this->scheduleHashTupleFromSegment($segment);
        }

        return $tuples;
    }

    /**
     * @param  array<string, mixed>  $segment
     * @return list<string>
     */
    public function scheduleHashTupleFromSegment(array $segment): array
    {
        $identity = $this->canonicalScheduleIdentityRow($segment);

        return [
            $identity['origin'],
            $identity['destination'],
            $identity['marketing_carrier'],
            $identity['canonical_operating_carrier_slot'],
            $identity['flight_number'],
            $this->comparableWallClock((string) ($segment['departure_at'] ?? $segment['depart_at'] ?? '')),
            $this->comparableWallClock((string) ($segment['arrival_at'] ?? '')),
        ];
    }

    /**
     * @param  list<string>  $tuple
     */
    public function scheduleHashTupleDigest(array $tuple): string
    {
        return hash('sha256', json_encode([
            'tuple_schema_version' => self::HASH_TUPLE_SCHEMA_VERSION,
            'tuple' => array_values($tuple),
        ], JSON_THROW_ON_ERROR));
    }

    /**
     * @param  list<array<string, mixed>>  $segments
     * @return list<string>
     */
    public function scheduleHashTupleSegmentDigests(array $segments): array
    {
        $digests = [];
        foreach ($this->scheduleHashTuplesFromSegments($segments) as $tuple) {
            $digests[] = $this->scheduleHashTupleDigest($tuple);
        }

        return $digests;
    }

    /**
     * @param  list<array<string, mixed>>  $segments
     * @return list<string>
     */
    public function signaturePartsFromSegments(array $segments): array
    {
        $parts = [];
        foreach ($this->scheduleHashTuplesFromSegments($segments) as $tuple) {
            $parts[] = implode("\x1f", $tuple);
        }

        return $parts;
    }

    /**
     * Single canonical schedule-identity row used for hashing, comparison, and diagnostics.
     *
     * @param  array<string, mixed>  $segment
     * @return array{
     *     origin: string,
     *     destination: string,
     *     departure_wall_clock_slot: string,
     *     arrival_wall_clock_slot: string,
     *     marketing_carrier: string,
     *     canonical_operating_carrier_slot: string,
     *     flight_number: string,
     *     booking_class: string,
     *     operating_carrier_shape_category: string
     * }
     */
    public function canonicalScheduleIdentityRow(array $segment): array
    {
        $marketing = $this->normalizeMarketingCarrier($segment);
        $operatingSlot = array_key_exists('canonical_operating_carrier_slot', $segment)
            ? (string) $segment['canonical_operating_carrier_slot']
            : $this->canonicalOperatingCarrierForSignature($segment, $marketing);

        return [
            'origin' => $this->normalizeAirport((string) ($segment['origin'] ?? '')),
            'destination' => $this->normalizeAirport((string) ($segment['destination'] ?? '')),
            'departure_wall_clock_slot' => $this->normalizeSignatureWallClockSlot((string) ($segment['departure_at'] ?? $segment['depart_at'] ?? '')),
            'arrival_wall_clock_slot' => $this->normalizeSignatureWallClockSlot((string) ($segment['arrival_at'] ?? '')),
            'marketing_carrier' => $marketing,
            'canonical_operating_carrier_slot' => $operatingSlot,
            'flight_number' => $this->normalizeFlightNumber((string) ($segment['flight_number'] ?? $segment['marketing_flight_number'] ?? '')),
            'booking_class' => $this->normalizeBookingClass((string) (
                $segment['booking_class']
                ?? $segment['class_of_service']
                ?? $segment['booking_code']
                ?? ''
            )),
            'operating_carrier_shape_category' => $this->operatingCarrierShapeCategory($segment),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $segments
     * @return list<array<string, mixed>>
     */
    public function canonicalScheduleIdentityRows(array $segments): array
    {
        $rows = [];
        foreach (array_values($segments) as $segment) {
            if (! is_array($segment)) {
                continue;
            }
            $identity = $this->canonicalScheduleIdentityRow($segment);
            $rows[] = array_merge(
                array_filter([
                    'origin' => $identity['origin'],
                    'destination' => $identity['destination'],
                    'departure_at' => (string) ($segment['departure_at'] ?? $segment['depart_at'] ?? ''),
                    'arrival_at' => (string) ($segment['arrival_at'] ?? ''),
                    'marketing_carrier' => $identity['marketing_carrier'],
                    'operating_carrier_shape_category' => $segment['operating_carrier_shape_category'] ?? $identity['operating_carrier_shape_category'],
                    'flight_number' => $identity['flight_number'],
                    'booking_class' => $identity['booking_class'],
                    'fare_basis_code' => strtoupper(trim((string) ($segment['fare_basis_code'] ?? ''))) ?: null,
                    'cabin_code' => strtoupper(trim((string) ($segment['cabin_code'] ?? ''))) ?: null,
                    'operating_carrier' => $identity['canonical_operating_carrier_slot'] !== '' ? $identity['canonical_operating_carrier_slot'] : null,
                ], static fn ($value) => $value !== null && $value !== ''),
                ['canonical_operating_carrier_slot' => $identity['canonical_operating_carrier_slot']],
                $this->safeScheduleLinkageDiagnosticFields($segment),
            );
        }

        return $rows;
    }

    /**
     * @param  list<array<string, mixed>>  $expected
     * @param  list<array<string, mixed>>  $actual
     * @return array<string, mixed>
     */
    public function safeLinkageDigestComparison(array $expected, array $actual): array
    {
        $expectedTuples = $this->scheduleHashTuplesFromSegments($expected);
        $actualTuples = $this->scheduleHashTuplesFromSegments($actual);
        $labels = $this->hashTupleFieldLabels();
        $categories = [];
        $tupleMismatchFieldNames = [];
        if (count($expectedTuples) !== count($actualTuples)) {
            $categories[] = 'segment_count';
        }
        $limit = max(count($expectedTuples), count($actualTuples));
        for ($i = 0; $i < $limit; $i++) {
            $expTuple = $expectedTuples[$i] ?? [];
            $actTuple = $actualTuples[$i] ?? [];
            foreach ($labels as $fieldIndex => $fieldLabel) {
                if (($expTuple[$fieldIndex] ?? '') === ($actTuple[$fieldIndex] ?? '')) {
                    continue;
                }
                $tupleMismatchFieldNames[] = $fieldLabel;
                $categories[] = $this->mismatchCategoryForTupleField($fieldLabel);
            }
        }

        $expectedParts = $this->signaturePartsFromSegments($expected);
        $actualParts = $this->signaturePartsFromSegments($actual);

        return array_filter([
            'tuple_schema_version' => self::HASH_TUPLE_SCHEMA_VERSION,
            'tuple_field_count' => self::HASH_TUPLE_FIELD_COUNT,
            'expected_segment_count' => count($expectedTuples),
            'actual_segment_count' => count($actualTuples),
            'expected_segment_parts_digest' => $this->digestParts($expectedParts),
            'actual_segment_parts_digest' => $this->digestParts($actualParts),
            'expected_segment_tuple_digests' => $this->scheduleHashTupleSegmentDigests($expected) ?: null,
            'actual_segment_tuple_digests' => $this->scheduleHashTupleSegmentDigests($actual) ?: null,
            'expected_segment_signature_digest' => $this->hashFromSegments($expected) ?: null,
            'actual_segment_signature_digest' => $this->hashFromSegments($actual) ?: null,
            'tuple_mismatch_field_names' => $tupleMismatchFieldNames !== [] ? array_values(array_unique($tupleMismatchFieldNames)) : null,
            'mismatch_categories' => $categories !== [] ? array_values(array_unique($categories)) : null,
        ], static fn ($value) => $value !== null && $value !== []);
    }

    public function normalizeFlightNumber(string $value): string
    {
        $value = strtoupper(trim($value));
        if ($value === '') {
            return '';
        }
        $digits = preg_replace('/\D+/', '', $value) ?? '';
        if ($digits === '') {
            return $value;
        }
        $normalized = ltrim($digits, '0');

        return $normalized !== '' ? $normalized : '0';
    }

    public function normalizeSignatureDateTime(string $value): string
    {
        return $this->bfmLocalClock()->normalizeSignatureDateTime($value);
    }

    /**
     * Cross-source schedule identity uses local wall-clock; shop ISO datetimes and BFM clock-only schedules must align.
     */
    public function normalizeSignatureWallClockSlot(string $value): string
    {
        $wall = $this->comparableWallClock($value);

        return $wall !== '' ? '|'.$wall : '';
    }

    /**
     * Calendar-agnostic wall-clock comparison for schedule continuity checks.
     */
    public function comparableWallClock(string $value): string
    {
        return $this->bfmLocalClock()->normalizedWallClockFromRaw($value);
    }

    /**
     * @param  array<string, mixed>  $schedule
     */
    public function scheduleEndpointClockRaw(array $schedule, string $endpoint): string
    {
        return $this->bfmLocalClock()->endpointClockRaw($schedule, $endpoint);
    }

    /**
     * @param  array<string, mixed>  $schedule
     */
    public function scheduleEndpointClockSourceShapeCategory(array $schedule, string $endpoint): string
    {
        return $this->bfmLocalClock()->endpointClockSourceShapeCategory($schedule, $endpoint);
    }

    /**
     * @param  array<string, mixed>  $schedule
     */
    public function scheduleEndpointDateAdjustmentDays(array $schedule, string $endpoint): int
    {
        return $this->bfmLocalClock()->endpointDateAdjustmentDays($schedule, $endpoint);
    }

    public function rawDateTimeShapeCategory(string $raw): string
    {
        return $this->bfmLocalClock()->rawDateTimeShapeCategory($raw);
    }

    /**
     * @param  list<array<string, mixed>>  $segments
     * @return list<array<string, mixed>>
     */
    public function safeCanonicalHashTupleValueRows(array $segments): array
    {
        $rows = [];
        foreach (array_values($segments) as $index => $segment) {
            if (! is_array($segment)) {
                continue;
            }
            $tuple = $this->scheduleHashTupleFromSegment($segment);
            $labels = $this->hashTupleFieldLabels();
            $row = ['ordinal' => $index + 1];
            foreach ($labels as $fieldIndex => $label) {
                $row[$label] = $tuple[$fieldIndex] ?? '';
            }
            $rows[] = array_merge($row, array_filter([
                'departure_clock_source_shape' => $segment['departure_clock_source_shape'] ?? $this->rawDateTimeShapeCategory(
                    (string) ($segment['departure_at'] ?? $segment['depart_at'] ?? ''),
                ),
                'arrival_clock_source_shape' => $segment['arrival_clock_source_shape'] ?? $this->rawDateTimeShapeCategory(
                    (string) ($segment['arrival_at'] ?? ''),
                ),
                'arrival_date_adjustment_days' => array_key_exists('arrival_date_adjustment_days', $segment)
                    ? (int) $segment['arrival_date_adjustment_days']
                    : null,
                'departure_date_adjustment_days' => array_key_exists('departure_date_adjustment_days', $segment)
                    ? (int) $segment['departure_date_adjustment_days']
                    : null,
                'schedule_desc_ref_category' => isset($segment['schedule_desc_ref'])
                    ? 'scalar_ref'
                    : null,
            ], static fn ($value) => $value !== null && $value !== ''));
        }

        return $rows;
    }

    /**
     * @param  list<array<string, mixed>>  $expected
     * @param  list<array<string, mixed>>  $actual
     * @return list<array<string, mixed>>
     */
    public function safeTupleFieldComparisonsBySegment(array $expected, array $actual): array
    {
        $expectedRows = $this->safeCanonicalHashTupleValueRows($expected);
        $actualRows = $this->safeCanonicalHashTupleValueRows($actual);
        $labels = $this->hashTupleFieldLabels();
        $comparisons = [];
        $limit = max(count($expectedRows), count($actualRows));
        for ($i = 0; $i < $limit; $i++) {
            $exp = $expectedRows[$i] ?? [];
            $act = $actualRows[$i] ?? [];
            $ordinal = (int) ($exp['ordinal'] ?? $act['ordinal'] ?? ($i + 1));
            foreach ($labels as $label) {
                $selectedValue = (string) ($exp[$label] ?? '');
                $candidateValue = (string) ($act[$label] ?? '');
                if ($selectedValue === $candidateValue) {
                    continue;
                }
                $comparisons[] = array_filter([
                    'ordinal' => $ordinal,
                    'field' => $label,
                    'selected_value' => $selectedValue,
                    'candidate_value' => $candidateValue,
                    'selected_departure_clock_source_shape' => $exp['departure_clock_source_shape'] ?? null,
                    'selected_arrival_clock_source_shape' => $exp['arrival_clock_source_shape'] ?? null,
                    'candidate_departure_clock_source_shape' => $act['departure_clock_source_shape'] ?? null,
                    'candidate_arrival_clock_source_shape' => $act['arrival_clock_source_shape'] ?? null,
                    'candidate_arrival_date_adjustment_days' => $act['arrival_date_adjustment_days'] ?? null,
                    'candidate_departure_date_adjustment_days' => $act['departure_date_adjustment_days'] ?? null,
                    'candidate_schedule_desc_ref_category' => $act['schedule_desc_ref_category'] ?? null,
                ], static fn ($value) => $value !== null && $value !== '');
            }
        }

        return $comparisons;
    }

    private function bfmLocalClock(): SabreGdsBfmScheduleEndpointLocalClock
    {
        return app(SabreGdsBfmScheduleEndpointLocalClock::class);
    }

    /**
     * @param  array<string, mixed>  $segment
     */
    public function normalizeMarketingCarrier(array $segment): string
    {
        $carrier = $segment['marketing_carrier'] ?? $segment['carrier'] ?? $segment['airline_code'] ?? '';
        if (is_array($carrier)) {
            $carrier = $carrier['code'] ?? $carrier['airlineCode'] ?? $carrier['marketing'] ?? '';
        }

        return $this->normalizeAirlineCode((string) $carrier);
    }

    /**
     * Operating carrier code included in schedule identity when it genuinely differs from marketing.
     *
     * @param  array<string, mixed>  $segment
     */
    public function canonicalOperatingCarrierForSignature(array $segment, string $marketingCarrier = ''): string
    {
        $marketing = $marketingCarrier !== '' ? $marketingCarrier : $this->normalizeMarketingCarrier($segment);
        $rawOperating = $this->rawOperatingCarrierCode($segment);
        if ($rawOperating === '') {
            return '';
        }
        if ($marketing !== '' && $rawOperating === $marketing) {
            return '';
        }

        return $rawOperating;
    }

    /**
     * Safe category for diagnostics (no raw opaque identifiers).
     *
     * @param  array<string, mixed>  $segment
     */
    public function operatingCarrierShapeCategory(array $segment): string
    {
        $marketing = $this->normalizeMarketingCarrier($segment);
        $rawOperating = $this->rawOperatingCarrierCodeWithoutEquivalenceCollapse($segment);
        if ($rawOperating === '') {
            return 'absent';
        }
        if ($marketing === '' || $rawOperating === $marketing) {
            return 'same_as_marketing';
        }

        return 'different_from_marketing';
    }

    /**
     * @param  array<string, mixed>  $segment
     */
    public function normalizeOperatingCarrier(array $segment, string $marketingCarrier = ''): string
    {
        return $this->canonicalOperatingCarrierForSignature($segment, $marketingCarrier);
    }

    /**
     * @param  array<string, mixed>  $schedule  BFM scheduleDesc row
     * @param  array<string, mixed>  $context  Safe linkage context (e.g. schedule_desc_ref)
     * @return array<string, mixed>
     */
    public function segmentRowFromScheduleDesc(array $schedule, array $context = []): array
    {
        $marketing = $this->normalizeMarketingCarrierFromSchedule($schedule);
        $rawOperatingForShape = $this->rawOperatingCarrierCodeFromSchedule($schedule);
        $operatingShapeCategory = $this->operatingCarrierShapeCategory(array_filter([
            'marketing_carrier' => $marketing,
            'operating_carrier' => $rawOperatingForShape !== '' ? $rawOperatingForShape : null,
        ], static fn ($value) => $value !== null && $value !== ''));
        $rawOperating = $rawOperatingForShape;
        if ($marketing === '' && $rawOperating !== '') {
            $marketing = $rawOperating;
            $rawOperating = '';
        } elseif ($marketing !== '' && $rawOperating === $marketing) {
            $rawOperating = '';
        }

        $origin = strtoupper(trim((string) (
            data_get($schedule, 'departure.airport')
            ?? data_get($schedule, 'departure.airportCode')
            ?? data_get($schedule, 'departure.locationCode')
            ?? ''
        )));
        $destination = strtoupper(trim((string) (
            data_get($schedule, 'arrival.airport')
            ?? data_get($schedule, 'arrival.airportCode')
            ?? data_get($schedule, 'arrival.locationCode')
            ?? ''
        )));
        $flightNumber = $this->normalizeFlightNumber((string) (
            data_get($schedule, 'carrier.marketingFlightNumber')
            ?? data_get($schedule, 'carrier.flightNumber')
            ?? ''
        ));

        $row = array_filter([
            'origin' => $origin,
            'destination' => $destination,
            'departure_at' => $this->scheduleEndpointClockRaw($schedule, 'departure'),
            'arrival_at' => $this->scheduleEndpointClockRaw($schedule, 'arrival'),
            'marketing_carrier' => $marketing,
            'flight_number' => $flightNumber,
            'departure_clock_source_shape' => $this->scheduleEndpointClockSourceShapeCategory($schedule, 'departure'),
            'arrival_clock_source_shape' => $this->scheduleEndpointClockSourceShapeCategory($schedule, 'arrival'),
            'departure_date_adjustment_days' => $this->scheduleEndpointDateAdjustmentDays($schedule, 'departure'),
            'arrival_date_adjustment_days' => $this->scheduleEndpointDateAdjustmentDays($schedule, 'arrival'),
            'schedule_desc_ref' => isset($context['schedule_desc_ref']) ? (string) $context['schedule_desc_ref'] : null,
        ], static fn ($value) => $value !== null && $value !== '');

        $base = array_merge($row, $rawOperating !== '' ? ['operating_carrier' => $rawOperating] : []);
        $identity = $this->canonicalScheduleIdentityRow($base);
        $normalized = array_merge($base, [
            'marketing_carrier' => $identity['marketing_carrier'] !== '' ? $identity['marketing_carrier'] : ($base['marketing_carrier'] ?? ''),
            'canonical_operating_carrier_slot' => $identity['canonical_operating_carrier_slot'],
            'operating_carrier_shape_category' => $operatingShapeCategory,
        ]);
        if ($identity['canonical_operating_carrier_slot'] === '') {
            unset($normalized['operating_carrier'], $normalized['operating_airline'], $normalized['operating_airline_code']);
        } else {
            $normalized['operating_carrier'] = $identity['canonical_operating_carrier_slot'];
        }

        return array_merge(
            array_filter($normalized, static fn ($value) => $value !== null && $value !== ''),
            ['canonical_operating_carrier_slot' => $identity['canonical_operating_carrier_slot']],
        );
    }

    /**
     * @param  array<string, mixed>  $schedule
     */
    public function normalizeMarketingCarrierFromSchedule(array $schedule): string
    {
        $carrierNode = data_get($schedule, 'carrier');
        $segment = [
            'marketing_carrier' => data_get($schedule, 'carrier.marketing'),
            'carrier' => is_array($carrierNode)
                ? ($carrierNode['marketing'] ?? $carrierNode['marketingAirline'] ?? $carrierNode['marketingCode'] ?? $carrierNode['code'] ?? null)
                : (data_get($schedule, 'carrier.marketing') ?? data_get($schedule, 'carrier.marketingAirline')),
        ];
        $marketing = $this->normalizeMarketingCarrier($segment);
        if ($marketing !== '') {
            return $marketing;
        }

        return $this->rawOperatingCarrierCodeFromSchedule($schedule);
    }

    /**
     * @param  array<string, mixed>  $segment
     */
    private function rawOperatingCarrierCode(array $segment): string
    {
        $operating = $this->rawOperatingCarrierCodeWithoutEquivalenceCollapse($segment);
        if ($operating === '') {
            return '';
        }
        $marketing = $this->normalizeMarketingCarrier($segment);
        if ($marketing !== '' && $operating === $marketing) {
            return '';
        }

        return $operating;
    }

    /**
     * @param  array<string, mixed>  $segment
     */
    private function rawOperatingCarrierCodeWithoutEquivalenceCollapse(array $segment): string
    {
        $operating = $segment['operating_carrier']
            ?? $segment['operatingCarrier']
            ?? $segment['operating_airline']
            ?? $segment['operating_airline_code']
            ?? '';
        if (is_array($operating)) {
            $operating = $operating['code'] ?? $operating['airlineCode'] ?? '';
        }
        $operating = $this->normalizeAirlineCode((string) $operating);
        if ($operating !== '') {
            return $operating;
        }
        $carrierNode = $segment['carrier'] ?? null;
        if (! is_array($carrierNode)) {
            return '';
        }
        $nested = $carrierNode['operating']
            ?? $carrierNode['operatingAirline']
            ?? $carrierNode['operatingAirlineCode']
            ?? $carrierNode['operatingCarrier']
            ?? '';
        if (is_array($nested)) {
            $nested = $nested['code'] ?? $nested['airlineCode'] ?? '';
        }

        return $this->normalizeAirlineCode((string) $nested);
    }

    /**
     * @param  array<string, mixed>  $schedule
     */
    private function rawOperatingCarrierCodeFromSchedule(array $schedule): string
    {
        $operatingRaw = data_get($schedule, 'carrier.operating')
            ?? data_get($schedule, 'carrier.operatingAirline')
            ?? data_get($schedule, 'carrier.operatingAirlineCode')
            ?? data_get($schedule, 'carrier.operatingCarrier')
            ?? data_get($schedule, 'operatingAirline')
            ?? data_get($schedule, 'operatingAirlineCode');

        if (is_array($operatingRaw)) {
            $operatingRaw = data_get($operatingRaw, 'code') ?? data_get($operatingRaw, 'airlineCode');
        }

        return $this->normalizeAirlineCode((string) $operatingRaw);
    }

    public function normalizeBookingClass(string $value): string
    {
        return strtoupper(substr(trim($value), 0, 8));
    }

    /**
     * @param  list<string>  $parts
     */
    private function digestParts(array $parts): string
    {
        if ($parts === []) {
            return '';
        }

        return hash('sha256', json_encode($parts, JSON_THROW_ON_ERROR));
    }

    private function normalizeAirport(string $value): string
    {
        return strtoupper(substr(trim($value), 0, 8));
    }

    private function normalizeAirlineCode(string $value): string
    {
        return strtoupper(substr(trim($value), 0, 8));
    }

    private function mismatchCategoryForTupleField(string $fieldLabel): string
    {
        return match ($fieldLabel) {
            'route_origin' => 'origin',
            'route_destination' => 'destination',
            'marketing_carrier' => 'marketing_carrier',
            'canonical_operating_carrier_slot' => 'operating_carrier',
            'normalized_flight_number' => 'flight_number',
            'departure_wall_clock' => 'departure_wall_clock',
            'arrival_wall_clock' => 'arrival_wall_clock',
            default => 'segment_part',
        };
    }

    /**
     * @param  array<string, mixed>  $segment
     * @return array<string, mixed>
     */
    private function safeScheduleLinkageDiagnosticFields(array $segment): array
    {
        $fields = [];
        foreach ([
            'departure_clock_source_shape',
            'arrival_clock_source_shape',
            'departure_date_adjustment_days',
            'arrival_date_adjustment_days',
            'schedule_desc_ref',
            'schedule_desc_lookup_mode',
            'schedule_desc_ref_digest',
        ] as $key) {
            if (! array_key_exists($key, $segment)) {
                continue;
            }
            if (in_array($key, ['departure_date_adjustment_days', 'arrival_date_adjustment_days'], true)) {
                $fields[$key] = (int) $segment[$key];

                continue;
            }
            $value = $segment[$key];
            if ($value === null || $value === '') {
                continue;
            }
            $fields[$key] = $value;
        }

        return $fields;
    }
}
