<?php

namespace App\Services\Suppliers\Sabre\PnrRetrieve;

/**
 * B84B.1/B84B.2: Map Trip Orders {@code POST /v1/trip/orders/getBooking} JSON into safe preview rows and
 * sanitized {@code meta.pnr_itinerary_snapshot} (no raw response storage).
 */
final class SabreTripOrdersGetBookingItineraryMapper
{
    public const ENDPOINT_PATH = '/v1/trip/orders/getBooking';

    public const SOURCE = 'sabre_trip_orders_get_booking';

    /** @var list<string> */
    public const ACCEPTABLE_SEGMENT_STATUSES = ['HK'];

    /** @var list<string> */
    public const BLOCKED_SEGMENT_STATUSES = ['HX', 'UC', 'UN', 'NO'];

    private const MAX_CANDIDATES = 32;

    /**
     * @param  array<string, mixed>  $json
     * @param  array<string, mixed>  $digest  Optional: {@code http_status}, {@code response_error_codes}, {@code response_error_messages}
     * @return array<string, mixed>
     */
    public function mapPreview(array $json, array $digest = []): array
    {
        $httpStatus = (int) ($digest['http_status'] ?? 0);
        $errorCodes = $this->stringList($digest['response_error_codes'] ?? []);
        $errorMessages = $this->stringList($digest['response_error_messages'] ?? []);
        $this->mergeErrorCodesFromJson($json, $errorCodes);

        $resourceUnavailable = $this->resourceUnavailablePresent($errorCodes, $errorMessages);
        $rawCandidates = $this->collectSegmentCandidates($json);
        $rows = [];
        $seen = [];

        foreach ($rawCandidates as $cand) {
            $mapped = $this->mapCandidateRow($cand);
            $dedupeKey = $this->dedupeKey($mapped);
            if ($dedupeKey !== '' && isset($seen[$dedupeKey])) {
                continue;
            }
            if ($dedupeKey !== '') {
                $seen[$dedupeKey] = true;
            }
            $rows[] = $mapped;
        }

        $rows = array_values(array_slice($rows, 0, self::MAX_CANDIDATES));
        foreach ($rows as $i => &$row) {
            $row['index'] = $i;
        }
        unset($row);

        $mappable = 0;
        foreach ($rows as $row) {
            if (($row['missing_required_fields'] ?? []) === []) {
                $mappable++;
            }
        }

        $httpOk = in_array($httpStatus, [200, 201], true);
        $candidateCount = count($rows);
        $safeToMap = $httpOk
            && $candidateCount >= 1
            && $mappable === $candidateCount
            && ! $resourceUnavailable;

        return [
            'candidate_segment_count' => $candidateCount,
            'mappable_segment_count' => $mappable,
            'safe_to_map_preview' => $safeToMap,
            'resource_unavailable_present' => $resourceUnavailable,
            'error_codes_sanitized' => array_slice(array_values(array_unique($errorCodes)), 0, 12),
            'candidate_rows' => $rows,
        ];
    }

    /**
     * @param  array<string, mixed>  $preview  Output of {@see mapPreview()}
     * @return array{can_sync: bool, reason_code: ?string, blocked_segment_statuses: list<string>}
     */
    public function evaluateSyncEligibility(array $preview): array
    {
        $blockedStatuses = $this->nonAcceptableSegmentStatuses($preview);
        $reason = null;
        if (($preview['resource_unavailable_present'] ?? false) === true) {
            $reason = 'blocked_resource_unavailable';
        } elseif (! ($preview['safe_to_map_preview'] ?? false)) {
            $reason = 'unmappable';
        } elseif ($blockedStatuses !== []) {
            $reason = 'blocked_segment_status';
        }

        return [
            'can_sync' => $reason === null,
            'reason_code' => $reason,
            'blocked_segment_statuses' => $blockedStatuses,
        ];
    }

    /**
     * D3: When getBooking reports RESOURCE_UNAVAILABLE, distinguish partial verification (safe locator/segment
     * signals present) from a total block with no usable partial data.
     *
     * @param  array<string, mixed>  $preview
     * @param  array<string, mixed>  $locatorObservability
     */
    public function refineResourceUnavailableReason(array $preview, array $locatorObservability): string
    {
        if ($this->hasPartialResourceUnavailableSignals($preview, $locatorObservability)) {
            return 'partial_resource_unavailable';
        }

        return 'blocked_resource_unavailable';
    }

    /**
     * @param  array<string, mixed>  $preview
     * @param  array<string, mixed>  $locatorObservability
     */
    public function hasPartialResourceUnavailableSignals(array $preview, array $locatorObservability): bool
    {
        if (($locatorObservability['airline_locator_present'] ?? false) === true) {
            return true;
        }

        if ((int) ($preview['mappable_segment_count'] ?? 0) >= 1) {
            return true;
        }

        return (int) ($preview['candidate_segment_count'] ?? 0) >= 1;
    }

    /**
     * @param  array<string, mixed>  $preview
     * @return array<string, mixed>|null
     */
    public function buildSnapshot(array $preview, string $pnr, ?string $syncedAt = null): ?array
    {
        $eligibility = $this->evaluateSyncEligibility($preview);
        if (! $eligibility['can_sync']) {
            return null;
        }

        $rows = is_array($preview['candidate_rows'] ?? null) ? $preview['candidate_rows'] : [];
        $segments = [];
        foreach ($rows as $row) {
            if (! is_array($row) || ($row['missing_required_fields'] ?? []) !== []) {
                continue;
            }
            $segments[] = [
                'origin' => (string) ($row['origin'] ?? ''),
                'destination' => (string) ($row['destination'] ?? ''),
                'departure_at' => (string) ($row['departure_at'] ?? ''),
                'arrival_at' => (string) ($row['arrival_at'] ?? ''),
                'airline_code' => (string) ($row['marketing_airline'] ?? ''),
                'operating_airline_code' => (string) ($row['operating_airline'] ?? ''),
                'flight_number' => (string) ($row['flight_number'] ?? ''),
                'booking_class' => (string) ($row['booking_class'] ?? ''),
                'segment_status' => (string) ($row['segment_status'] ?? ''),
            ];
        }

        if ($segments === []) {
            return null;
        }

        $first = $segments[0];
        $last = $segments[count($segments) - 1];
        $synced = $syncedAt ?? now()->toIso8601String();

        return [
            'source' => self::SOURCE,
            'endpoint_path' => self::ENDPOINT_PATH,
            'synced_at' => $synced,
            'pnr' => strtoupper(substr(trim($pnr), 0, 32)),
            'origin' => (string) ($first['origin'] ?? ''),
            'destination' => (string) ($last['destination'] ?? ''),
            'departure_at' => (string) ($first['departure_at'] ?? ''),
            'arrival_at' => (string) ($last['arrival_at'] ?? ''),
            'stops' => max(0, count($segments) - 1),
            'segments' => $segments,
        ];
    }

    /**
     * @param  array<string, mixed>  $preview
     * @return list<string>
     */
    public function nonAcceptableSegmentStatuses(array $preview): array
    {
        $blocked = [];
        $rows = is_array($preview['candidate_rows'] ?? null) ? $preview['candidate_rows'] : [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $status = strtoupper(trim((string) ($row['segment_status'] ?? '')));
            if ($status === '') {
                $blocked[] = '(empty)';

                continue;
            }
            if (in_array($status, self::BLOCKED_SEGMENT_STATUSES, true)) {
                $blocked[] = $status;

                continue;
            }
            if (! in_array($status, self::ACCEPTABLE_SEGMENT_STATUSES, true)) {
                $blocked[] = $status;
            }
        }

        return array_values(array_unique($blocked));
    }

    /**
     * @param  array<string, mixed>  $preview
     */
    public function segmentStatusesAllowSync(array $preview): bool
    {
        return $this->nonAcceptableSegmentStatuses($preview) === [];
    }

    /**
     * @param  array<string, mixed>  $json
     * @return list<array{source: string, index: int, row: array<string, mixed>, profile: string}>
     */
    protected function collectSegmentCandidates(array $json): array
    {
        $out = [];

        $flights = $json['flights'] ?? null;
        if (is_array($flights) && array_is_list($flights)) {
            foreach ($flights as $i => $row) {
                if (is_array($row) && $this->looksLikeTripOrdersFlightRow($row)) {
                    $out[] = ['source' => 'flights', 'index' => (int) $i, 'row' => $row, 'profile' => 'trip_orders_flight'];
                }
            }
        }

        $all = $json['allSegments'] ?? null;
        if (is_array($all) && array_is_list($all)) {
            foreach ($all as $i => $row) {
                if (is_array($row) && $this->looksLikeTripOrdersAllSegmentRow($row)) {
                    $out[] = ['source' => 'allSegments', 'index' => (int) $i, 'row' => $row, 'profile' => 'trip_orders_all_segment'];
                }
            }
        }

        if (is_array($flights) && array_is_list($flights)) {
            foreach ($flights as $fi => $flight) {
                if (! is_array($flight)) {
                    continue;
                }
                foreach (['segments', 'allSegments'] as $sk) {
                    $list = $flight[$sk] ?? null;
                    if (! is_array($list) || ! array_is_list($list)) {
                        continue;
                    }
                    foreach ($list as $si => $seg) {
                        if (! is_array($seg)) {
                            continue;
                        }
                        $profile = $this->detectSegmentProfile($seg);
                        if ($profile === null) {
                            continue;
                        }
                        $out[] = [
                            'source' => 'flights.'.$fi.'.'.$sk,
                            'index' => (int) $si,
                            'row' => $seg,
                            'profile' => $profile,
                        ];
                    }
                }
            }
        }

        $journeys = $json['journeys'] ?? null;
        if (is_array($journeys) && array_is_list($journeys)) {
            foreach ($journeys as $ji => $journey) {
                if (! is_array($journey)) {
                    continue;
                }
                $list = $journey['segments'] ?? null;
                if (! is_array($list) || ! array_is_list($list)) {
                    continue;
                }
                foreach ($list as $si => $seg) {
                    if (! is_array($seg)) {
                        continue;
                    }
                    $profile = $this->detectSegmentProfile($seg);
                    if ($profile === null) {
                        continue;
                    }
                    $out[] = [
                        'source' => 'journeys.'.$ji.'.segments',
                        'index' => (int) $si,
                        'row' => $seg,
                        'profile' => $profile,
                    ];
                }
            }
        }

        return array_slice($out, 0, self::MAX_CANDIDATES * 2);
    }

    /**
     * @param  array{source: string, index: int, row: array<string, mixed>, profile: string}  $cand
     * @return array<string, mixed>
     */
    protected function mapCandidateRow(array $cand): array
    {
        $seg = $cand['row'];
        $mapped = match ($cand['profile']) {
            'trip_orders_flight' => $this->mapTripOrdersFlightRow($seg),
            'trip_orders_all_segment' => $this->mapTripOrdersAllSegmentRow($seg),
            default => $this->mapGenericSegmentRow($seg),
        };

        $missing = [];
        foreach ([
            'origin' => $mapped['origin'],
            'destination' => $mapped['destination'],
            'departure_at' => $mapped['departure_at'],
            'arrival_at' => $mapped['arrival_at'],
            'marketing_airline' => $mapped['marketing_airline'],
            'flight_number' => $mapped['flight_number'],
        ] as $field => $val) {
            if ($val === '') {
                $missing[] = $field;
            }
        }

        return array_merge($mapped, [
            'index' => $cand['index'],
            'candidate_source' => $this->truncate($cand['source'], 80),
            'missing_required_fields' => $missing,
        ]);
    }

    /**
     * @param  array<string, mixed>  $seg
     * @return array<string, mixed>
     */
    protected function mapTripOrdersFlightRow(array $seg): array
    {
        return [
            'origin' => $this->airportCode($seg['fromAirportCode'] ?? null),
            'destination' => $this->airportCode($seg['toAirportCode'] ?? null),
            'departure_at' => $this->combineDateTime(
                $this->scalarString($seg['departureDate'] ?? null),
                $this->scalarString($seg['departureTime'] ?? null),
            ),
            'arrival_at' => $this->combineDateTime(
                $this->scalarString($seg['arrivalDate'] ?? null),
                $this->scalarString($seg['arrivalTime'] ?? null),
            ),
            'marketing_airline' => $this->airlineCode($seg['airlineCode'] ?? null),
            'operating_airline' => $this->airlineCode($seg['operatingAirlineCode'] ?? null),
            'flight_number' => $this->normalizeFlightNumber($seg['flightNumber'] ?? $seg['operatingFlightNumber'] ?? null),
            'booking_class' => $this->bookingClass($seg['bookingClass'] ?? null),
            'segment_status' => $this->segmentStatus(
                $seg['flightStatusCode'] ?? null,
                $seg['flightStatusName'] ?? null,
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $seg
     * @return array<string, mixed>
     */
    protected function mapTripOrdersAllSegmentRow(array $seg): array
    {
        $vendor = $this->airlineCode($seg['vendorCode'] ?? null);

        return [
            'origin' => $this->airportCode($seg['startLocationCode'] ?? null),
            'destination' => $this->airportCode($seg['endLocationCode'] ?? null),
            'departure_at' => $this->combineDateTime(
                $this->scalarString($seg['startDate'] ?? null),
                $this->scalarString($seg['startTime'] ?? null),
            ),
            'arrival_at' => $this->combineDateTime(
                $this->scalarString($seg['endDate'] ?? null),
                $this->scalarString($seg['endTime'] ?? null),
            ),
            'marketing_airline' => $vendor,
            'operating_airline' => '',
            'flight_number' => $this->parseFlightNumberFromAllSegmentsText(
                $this->scalarString($seg['text'] ?? null),
                $vendor,
            ),
            'booking_class' => '',
            'segment_status' => $this->truncate($this->scalarString($seg['type'] ?? null), 32),
        ];
    }

    /**
     * @param  array<string, mixed>  $seg
     * @return array<string, mixed>
     */
    protected function mapGenericSegmentRow(array $seg): array
    {
        return [
            'origin' => $this->extractAirport($seg, 'origin', 'departure', 'fromAirportCode', 'startLocationCode'),
            'destination' => $this->extractAirport($seg, 'destination', 'arrival', 'toAirportCode', 'endLocationCode'),
            'departure_at' => $this->extractDateTime($seg, true),
            'arrival_at' => $this->extractDateTime($seg, false),
            'marketing_airline' => $this->extractCarrier($seg, true),
            'operating_airline' => $this->extractCarrier($seg, false),
            'flight_number' => $this->normalizeFlightNumber($seg['flightNumber'] ?? $seg['flight_number'] ?? null),
            'booking_class' => $this->bookingClass($seg['bookingClass'] ?? $seg['classOfService'] ?? $seg['booking_class'] ?? null),
            'segment_status' => $this->segmentStatus($seg['status'] ?? null, $seg['segmentStatus'] ?? null),
        ];
    }

    /**
     * @param  array<string, mixed>  $seg
     */
    protected function looksLikeTripOrdersFlightRow(array $seg): bool
    {
        return $this->scalarString($seg['fromAirportCode'] ?? null) !== ''
            || $this->scalarString($seg['departureDate'] ?? null) !== '';
    }

    /**
     * @param  array<string, mixed>  $seg
     */
    protected function looksLikeTripOrdersAllSegmentRow(array $seg): bool
    {
        return $this->scalarString($seg['startLocationCode'] ?? null) !== ''
            || $this->scalarString($seg['startDate'] ?? null) !== '';
    }

    /**
     * @param  array<string, mixed>  $seg
     */
    protected function detectSegmentProfile(array $seg): ?string
    {
        if ($this->looksLikeTripOrdersFlightRow($seg)) {
            return 'trip_orders_flight';
        }
        if ($this->looksLikeTripOrdersAllSegmentRow($seg)) {
            return 'trip_orders_all_segment';
        }
        if ($this->extractAirport($seg, 'origin', 'departure', 'fromAirportCode', 'startLocationCode') !== '') {
            return 'generic';
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $mapped
     */
    protected function dedupeKey(array $mapped): string
    {
        $parts = [
            $mapped['origin'] ?? '',
            $mapped['destination'] ?? '',
            $mapped['departure_at'] ?? '',
            $mapped['marketing_airline'] ?? '',
            $mapped['flight_number'] ?? '',
        ];
        if ($parts === ['', '', '', '', '']) {
            return '';
        }

        return implode('|', $parts);
    }

    protected function combineDateTime(string $date, string $time): string
    {
        $dateNorm = $this->normalizeDatePart($date);
        $timeNorm = $this->normalizeTimePart($time);
        if ($dateNorm === '' || $timeNorm === '') {
            return '';
        }

        return $dateNorm.'T'.$timeNorm;
    }

    protected function normalizeDatePart(string $date): string
    {
        $d = trim($date);
        if ($d === '') {
            return '';
        }
        if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $d, $m)) {
            return $m[1];
        }
        if (preg_match('/^(\d{4})\D?(\d{2})\D?(\d{2})/', $d, $m)) {
            return sprintf('%04d-%02d-%02d', (int) $m[1], (int) $m[2], (int) $m[3]);
        }

        return '';
    }

    protected function normalizeTimePart(string $time): string
    {
        $t = trim($time);
        if ($t === '') {
            return '';
        }
        if (preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?\s*(AM|PM)?$/i', $t, $m)) {
            $hour = (int) $m[1];
            $min = (int) $m[2];
            $sec = isset($m[3]) && $m[3] !== '' ? (int) $m[3] : 0;
            $ampm = strtoupper($m[4] ?? '');
            if ($ampm === 'PM' && $hour < 12) {
                $hour += 12;
            }
            if ($ampm === 'AM' && $hour === 12) {
                $hour = 0;
            }

            return sprintf('%02d:%02d:%02d', $hour, $min, $sec);
        }
        if (preg_match('/^(\d{3,4})$/', $t, $m)) {
            $digits = str_pad($m[1], 4, '0', STR_PAD_LEFT);
            $hour = (int) substr($digits, 0, 2);
            $min = (int) substr($digits, 2, 2);

            return sprintf('%02d:%02d:00', $hour, $min);
        }

        return '';
    }

    protected function parseFlightNumberFromAllSegmentsText(string $text, string $vendorCode): string
    {
        $text = trim($text);
        $vendor = strtoupper(trim($vendorCode));
        if ($text === '') {
            return '';
        }
        if (preg_match('/^\d{1,4}$/', $text)) {
            return $text;
        }
        if ($vendor !== '' && preg_match('/^'.preg_quote($vendor, '/').'\s*(\d{1,4})$/i', $text, $m)) {
            return $m[1];
        }
        if (preg_match('/^([A-Z]{2})\s*(\d{1,4})$/i', $text, $m)) {
            if ($vendor === '' || strtoupper($m[1]) === $vendor) {
                return $m[2];
            }
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $seg
     */
    protected function extractAirport(array $seg, string ...$keys): string
    {
        foreach ($keys as $k) {
            $code = $this->airportCode($seg[$k] ?? null);
            if ($code !== '') {
                return $code;
            }
        }
        foreach (['departure', 'arrival'] as $nestedKey) {
            $nested = $seg[$nestedKey] ?? null;
            if (! is_array($nested)) {
                continue;
            }
            foreach (['locationCode', 'airport', 'airportCode', 'code'] as $nk) {
                $code = $this->airportCode($nested[$nk] ?? null);
                if ($code !== '') {
                    return $code;
                }
            }
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $seg
     */
    protected function extractDateTime(array $seg, bool $departure): string
    {
        if ($departure) {
            $combined = $this->combineDateTime(
                $this->scalarString($seg['departureDate'] ?? $seg['startDate'] ?? null),
                $this->scalarString($seg['departureTime'] ?? $seg['startTime'] ?? null),
            );
            if ($combined !== '') {
                return $combined;
            }
        } else {
            $combined = $this->combineDateTime(
                $this->scalarString($seg['arrivalDate'] ?? $seg['endDate'] ?? null),
                $this->scalarString($seg['arrivalTime'] ?? $seg['endTime'] ?? null),
            );
            if ($combined !== '') {
                return $combined;
            }
        }

        $keys = $departure
            ? ['departureDateTime', 'departure_at', 'departure_datetime']
            : ['arrivalDateTime', 'arrival_at', 'arrival_datetime'];
        foreach ($keys as $k) {
            $v = $this->scalarString($seg[$k] ?? null);
            if ($v !== '') {
                return $this->truncate($v, 32);
            }
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $seg
     */
    protected function extractCarrier(array $seg, bool $marketing): string
    {
        $keys = $marketing
            ? ['airlineCode', 'marketingAirline', 'marketing_airline', 'vendorCode', 'carrier']
            : ['operatingAirlineCode', 'operatingAirline', 'operating_airline', 'operatingCarrier'];
        foreach ($keys as $k) {
            $code = $this->airlineCode($seg[$k] ?? null);
            if ($code !== '') {
                return $code;
            }
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $json
     * @param  list<string>  $errorCodes
     */
    protected function mergeErrorCodesFromJson(array $json, array &$errorCodes): void
    {
        $errors = $json['errors'] ?? null;
        if (! is_array($errors)) {
            return;
        }
        foreach ($errors as $row) {
            if (! is_array($row)) {
                continue;
            }
            $code = $this->scalarString($row['code'] ?? $row['errorCode'] ?? null);
            if ($code !== '') {
                $errorCodes[] = $this->truncate($code, 80);
            }
        }
    }

    /**
     * @param  list<string>  $codes
     * @param  list<string>  $messages
     */
    protected function resourceUnavailablePresent(array $codes, array $messages): bool
    {
        foreach ($codes as $code) {
            if (strcasecmp(trim($code), 'RESOURCE_UNAVAILABLE') === 0) {
                return true;
            }
        }
        foreach ($messages as $msg) {
            if (stripos($msg, 'RESOURCE_UNAVAILABLE') !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    protected function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter(array_map(
            fn (mixed $v): string => is_string($v) || is_int($v) || is_float($v) ? trim((string) $v) : '',
            $value
        ), fn (string $s): bool => $s !== ''));
    }

    protected function airportCode(mixed $value): string
    {
        $s = strtoupper(trim($this->scalarString($value)));

        return strlen($s) >= 3 ? substr($s, 0, 3) : '';
    }

    protected function airlineCode(mixed $value): string
    {
        if (is_array($value)) {
            foreach (['code', 'airlineCode', 'marketingAirline', 'operatingAirline'] as $k) {
                $c = $this->airlineCode($value[$k] ?? null);
                if ($c !== '') {
                    return $c;
                }
            }

            return '';
        }

        $s = strtoupper(trim($this->scalarString($value)));
        if ($s === '' || ! preg_match('/^[A-Z0-9]{2,3}$/', $s)) {
            return '';
        }

        return substr($s, 0, 3);
    }

    protected function normalizeFlightNumber(mixed $value): string
    {
        if (is_int($value)) {
            return (string) $value;
        }
        $s = trim($this->scalarString($value));
        if ($s === '') {
            return '';
        }
        $s = preg_replace('/\s+/', '', $s) ?? $s;

        return $this->truncate($s, 16);
    }

    protected function bookingClass(mixed $value): string
    {
        $s = strtoupper(trim($this->scalarString($value)));

        return $s !== '' ? $this->truncate($s, 8) : '';
    }

    protected function segmentStatus(mixed $primary, mixed $fallback): string
    {
        $s = trim($this->scalarString($primary));
        if ($s === '') {
            $s = trim($this->scalarString($fallback));
        }

        return $s !== '' ? $this->truncate($s, 32) : '';
    }

    protected function scalarString(mixed $value): string
    {
        if (is_string($value)) {
            return trim($value);
        }
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return '';
    }

    protected function truncate(string $value, int $max = 120): string
    {
        if (strlen($value) <= $max) {
            return $value;
        }

        return substr($value, 0, $max);
    }
}
