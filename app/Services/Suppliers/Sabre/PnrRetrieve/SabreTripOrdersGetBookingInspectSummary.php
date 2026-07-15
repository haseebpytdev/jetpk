<?php

namespace App\Services\Suppliers\Sabre\PnrRetrieve;

/**
 * B84B.3: Safe getBooking status summary + cancel inference for {@see SabrePnrRetrieveProbe} (--map-preview).
 * No PII, no raw JSON, no DB writes.
 */
final class SabreTripOrdersGetBookingInspectSummary
{
    private const MAX_PATHS = 24;

    private const MAX_DEPTH = 14;

    /** @var list<string> */
    private const PII_BRANCH_KEYS = [
        'travelers',
        'traveler',
        'contactinfo',
        'contact',
        'payments',
        'payment',
        'specialservices',
        'specialservice',
        'customerinfo',
        'personname',
        'passenger',
        'passengers',
    ];

    /** @var list<string> */
    private const SEGMENT_PATH_KEY_FRAGMENTS = [
        'segments',
        'allsegments',
        'flightsegment',
        'flightsegments',
    ];

    /** @var list<string> */
    private const AIR_PATH_KEY_FRAGMENTS = [
        'flights',
        'airsegments',
        'airsegment',
        'journeys',
    ];

    /** @var list<string> */
    private const ORDER_PATH_KEY_FRAGMENTS = [
        'orderitems',
        'orderitem',
        'reservationitems',
        'lineitems',
    ];

    /** @var list<string> */
    private const AIRLINE_CARRIER_VENDOR_LOCATOR_KEY_FRAGMENTS = [
        'airlinepnr',
        'airlinelocator',
        'airline_locator',
        'carrierlocator',
        'carrier_locator',
        'vendorlocator',
        'vendor_locator',
        'hostlocator',
        'host_locator',
        'operatingcarrierlocator',
        'marketingcarrierlocator',
        'supplierreference',
    ];

    /** @var list<string> */
    private const SABRE_RECORD_LOCATOR_KEY_FRAGMENTS = [
        'recordlocator',
        'record_locator',
    ];

    /** @var list<string> */
    private const TRIP_ORDERS_ID_KEY_FRAGMENTS = [
        'bookingid',
        'bookingsignature',
        'orderid',
        'reservationid',
    ];

    private const MAX_LOCATOR_PATHS = 12;

    /** @var array<string, list<string>> category => key fragments (lowercase) */
    private const CANCEL_RELATED_PATH_CATEGORIES = [
        'cancel_data' => ['canceldata'],
        'order_id' => ['orderid'],
        'order_item_ids' => ['orderitemid', 'orderitemids'],
        'air_item_ids' => ['airitemid', 'airitemids'],
        'service_item_ids' => ['serviceitemid', 'serviceitemids'],
        'segment_ids' => ['segmentid', 'segmentids'],
        'cancel_penalty_refund' => ['cancelpenalty', 'penalty', 'refund', 'refundamount', 'cancellationfee'],
        'links_actions' => ['links', 'actions', 'href', 'method', 'rel'],
    ];

    /**
     * @param  array<string, mixed>  $json
     * @param  array<string, mixed>  $context  {@code http_status}, optional {@code map_preview}
     * @return array<string, mixed>
     */
    public function buildForProbeRow(array $json, array $context = []): array
    {
        $httpStatus = (int) ($context['http_status'] ?? 0);
        $mapPreview = is_array($context['map_preview'] ?? null) ? $context['map_preview'] : [];
        $topLevelKeys = $this->sanitizeKeyList(array_keys($json));

        $summary = [
            'booking_id_present' => $this->nonEmptyScalarPresent($json, ['bookingId', 'booking_id']),
            'booking_signature_present' => $this->nonEmptyScalarPresent($json, ['bookingSignature', 'booking_signature']),
            'is_cancelable_value' => $this->extractNullableBool($json, ['isCancelable', 'is_cancelable']),
            'is_ticketed_value' => $this->extractNullableBool($json, ['isTicketed', 'is_ticketed']),
            'traveler_count' => $this->countListBranch($json, ['travelers', 'traveler']),
            'fare_count' => $this->countListBranch($json, ['fares', 'fareOffers', 'fare_offers']),
            'special_service_count' => $this->countListBranch($json, ['specialServices', 'special_services']),
            'remark_count' => $this->countListBranch($json, ['remarks', 'remark']),
            'contact_info_present' => $this->branchPresent($json, ['contactInfo', 'contact_info', 'contact']),
            'creation_details_present' => $this->branchPresent($json, ['creationDetails', 'creation_details']),
            'request_keys_sanitized' => $this->sanitizeRequestKeys($json),
            'top_level_keys_sanitized' => $topLevelKeys,
        ];

        $paths = $this->discoverSafeItemPaths($json);
        $cancel = $this->inferCancellationVerification($httpStatus, $summary, $paths, $mapPreview);

        return [
            'get_booking_status_summary' => $summary,
            'possible_air_item_paths' => $paths['possible_air_item_paths'],
            'possible_segment_paths' => $paths['possible_segment_paths'],
            'possible_order_item_paths' => $paths['possible_order_item_paths'],
            'cancel_verification_possible' => $cancel['cancel_verification_possible'],
            'cancel_verification_status' => $cancel['cancel_verification_status'],
            'cancel_verification_reason' => $cancel['cancel_verification_reason'],
            'airline_locator_observability' => $this->buildAirlineLocatorObservability($json),
        ];
    }

    /**
     * D1: Safe airline/carrier/vendor locator detection for getBooking (paths + locator-shaped values only; no PII).
     *
     * @param  array<string, mixed>  $json
     * @return array{
     *   airline_locator_present: bool,
     *   airline_locator_path: ?string,
     *   airline_locator_paths: list<string>,
     *   airline_locator_value: ?string,
     *   sabre_record_locator_present: bool,
     *   sabre_record_locator_path: ?string,
     *   sabre_record_locator_value: ?string,
     *   trip_orders_confirmation_id_present: bool
     * }
     */
    public function buildAirlineLocatorObservability(array $json): array
    {
        $airlinePaths = [];
        $airlineValues = [];
        $sabrePaths = [];
        $sabreValues = [];
        $tripOrdersConfirmationPresent = $this->nonEmptyScalarPresent($json, ['confirmationId', 'confirmation_id']);

        $walker = function (mixed $node, string $path, int $depth) use (&$walker, &$airlinePaths, &$airlineValues, &$sabrePaths, &$sabreValues): void {
            if ($depth > self::MAX_DEPTH || ! is_array($node)) {
                return;
            }
            foreach ($node as $k => $v) {
                if (is_int($k)) {
                    $childPath = $path === '' ? (string) $k : $path.'.'.$k;
                    if (is_array($v)) {
                        $walker($v, $childPath, $depth + 1);
                    }

                    continue;
                }
                if (! is_string($k)) {
                    if (is_array($v)) {
                        $walker($v, $path, $depth + 1);
                    }

                    continue;
                }
                if ($this->isPiiBranchKey($k) || $this->isSensitiveKeyName($k)) {
                    continue;
                }
                $childPath = $path === '' ? $k : $path.'.'.$k;
                $lk = strtolower($k);
                if (is_scalar($v) && trim((string) $v) !== '') {
                    if ($this->keyMatchesFragments($lk, self::SABRE_RECORD_LOCATOR_KEY_FRAGMENTS)) {
                        $this->pushLocatorObservation($sabrePaths, $sabreValues, $childPath, $v);
                    } elseif ($this->isAirlineCarrierVendorLocatorKey($lk, $childPath)) {
                        $this->pushLocatorObservation($airlinePaths, $airlineValues, $childPath, $v);
                    } elseif ($lk === 'confirmationid' && $this->isSegmentScopedConfirmationIdPath($childPath)) {
                        $this->pushLocatorObservation($airlinePaths, $airlineValues, $childPath, $v);
                    }
                }
                if (is_array($v)) {
                    $walker($v, $childPath, $depth + 1);
                }
            }
        };

        $walker($json, '', 0);

        $airlinePaths = $this->finalizeLocatorPaths($airlinePaths);
        $sabrePaths = $this->finalizeLocatorPaths($sabrePaths);
        $firstAirlineValue = $airlineValues[0]['value'] ?? null;

        return [
            'airline_locator_present' => $airlinePaths !== [],
            'airline_locator_path' => $airlinePaths[0] ?? null,
            'airline_locator_paths' => $airlinePaths,
            'airline_locator_value' => is_string($firstAirlineValue) ? $firstAirlineValue : null,
            'sabre_record_locator_present' => $sabrePaths !== [],
            'sabre_record_locator_path' => $sabrePaths[0] ?? null,
            'sabre_record_locator_value' => isset($sabreValues[0]['value']) && is_string($sabreValues[0]['value'])
                ? $sabreValues[0]['value']
                : null,
            'trip_orders_confirmation_id_present' => $tripOrdersConfirmationPresent,
        ];
    }

    /**
     * Extract Trip Orders bookingId / bookingSignature for cancel probe payloads (internal use only).
     *
     * @param  array<string, mixed>  $json
     * @return array{
     *   booking_id: ?string,
     *   booking_signature: ?string,
     *   is_cancelable: ?bool,
     *   is_ticketed: ?bool
     * }
     */
    public function extractTripOrderIdentifiers(array $json): array
    {
        $bookingId = $this->extractScalarValue($json, ['bookingId', 'booking_id']);
        $signature = $this->extractScalarValue($json, ['bookingSignature', 'booking_signature']);
        $isCancelable = $this->extractNullableBool($json, ['isCancelable', 'is_cancelable']);
        $isTicketed = $this->extractNullableBool($json, ['isTicketed', 'is_ticketed']);

        foreach (['booking', 'order', 'reservation'] as $branch) {
            $nested = $json[$branch] ?? null;
            if (! is_array($nested)) {
                continue;
            }
            $bookingId ??= $this->extractScalarValue($nested, ['bookingId', 'booking_id', 'id']);
            $signature ??= $this->extractScalarValue($nested, ['bookingSignature', 'booking_signature']);
            $isCancelable ??= $this->extractNullableBool($nested, ['isCancelable', 'is_cancelable']);
            $isTicketed ??= $this->extractNullableBool($nested, ['isTicketed', 'is_ticketed']);
        }

        return [
            'booking_id' => $bookingId,
            'booking_signature' => $signature,
            'is_cancelable' => $isCancelable,
            'is_ticketed' => $isTicketed,
        ];
    }

    /**
     * Safe pre-cancel flags from getBooking JSON (no PII, no raw values).
     *
     * @param  array<string, mixed>  $json
     * @return array{
     *   is_ticketed: ?bool,
     *   is_cancelable: ?bool,
     *   ticket_numbers_present: bool,
     *   booking_id_present: bool
     * }
     */
    /**
     * F3C: Safe getBooking field inventory for cancelBooking schema diagnosis (keys/paths/counts only).
     *
     * @param  array<string, mixed>  $json
     * @return array<string, mixed>
     */
    public function buildCancelSchemaInventory(array $json): array
    {
        $probeRow = $this->buildForProbeRow($json, ['http_status' => 200]);
        $cancelPaths = $this->discoverCancelRelatedPaths($json);

        return [
            'top_level_keys_sanitized' => $probeRow['get_booking_status_summary']['top_level_keys_sanitized'] ?? [],
            'get_booking_status_summary' => $probeRow['get_booking_status_summary'],
            'cancel_safety_flags' => $this->extractDirectCancelSafetyFlags($json),
            'cancel_related_presence' => $this->cancelRelatedPresenceFlags($json, $cancelPaths),
            'possible_cancel_related_paths' => $cancelPaths,
            'possible_air_item_paths' => $probeRow['possible_air_item_paths'],
            'possible_segment_paths' => $probeRow['possible_segment_paths'],
            'possible_order_item_paths' => $probeRow['possible_order_item_paths'],
        ];
    }

    /**
     * @param  array<string, mixed>  $json
     * @return array<string, mixed>
     */
    public function extractDirectCancelSafetyFlags(array $json): array
    {
        $ids = $this->extractTripOrderIdentifiers($json);
        $topLevelKeys = $this->sanitizeKeyList(array_keys($json));
        $ticketNumberKeys = [
            'ticketnumbers',
            'ticketnumber',
            'eticketnumbers',
            'etickets',
            'electronicticketnumbers',
        ];
        $ticketNumbersPresent = false;
        foreach ($topLevelKeys as $key) {
            if (in_array(strtolower($key), $ticketNumberKeys, true)) {
                $ticketNumbersPresent = true;
                break;
            }
        }

        $bookingId = $ids['booking_id'];

        return [
            'is_ticketed' => $ids['is_ticketed'],
            'is_cancelable' => $ids['is_cancelable'],
            'ticket_numbers_present' => $ticketNumbersPresent,
            'booking_id_present' => is_string($bookingId) && trim($bookingId) !== '',
        ];
    }

    /**
     * @param  array<string, mixed>  $json
     * @param  list<string>  $keys
     */
    protected function extractScalarValue(array $json, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (! array_key_exists($key, $json)) {
                continue;
            }
            $v = $json[$key];
            if (is_string($v) && trim($v) !== '') {
                return substr(trim($v), 0, 120);
            }
            if (is_int($v) || is_float($v)) {
                return substr((string) $v, 0, 120);
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $json
     * @param  list<string>  $keys
     */
    protected function nonEmptyScalarPresent(array $json, array $keys): bool
    {
        foreach ($keys as $key) {
            if (! array_key_exists($key, $json)) {
                continue;
            }
            $v = $json[$key];
            if (is_string($v) && trim($v) !== '') {
                return true;
            }
            if (is_int($v) || is_float($v)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $json
     * @param  list<string>  $keys
     */
    protected function extractNullableBool(array $json, array $keys): ?bool
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $json) && is_bool($json[$key])) {
                return $json[$key];
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $json
     * @param  list<string>  $keys
     */
    protected function countListBranch(array $json, array $keys): int
    {
        foreach ($keys as $key) {
            $v = $json[$key] ?? null;
            if (is_array($v) && array_is_list($v)) {
                return count($v);
            }
            if (is_array($v) && $v !== []) {
                return 1;
            }
        }

        return 0;
    }

    /**
     * @param  array<string, mixed>  $json
     * @param  list<string>  $keys
     */
    protected function branchPresent(array $json, array $keys): bool
    {
        foreach ($keys as $key) {
            if (! array_key_exists($key, $json)) {
                continue;
            }
            $v = $json[$key];
            if (is_array($v) && $v !== []) {
                return true;
            }
            if (is_string($v) && trim($v) !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $json
     * @return list<string>
     */
    protected function sanitizeRequestKeys(array $json): array
    {
        $request = $json['request'] ?? null;
        if (! is_array($request)) {
            return [];
        }

        return $this->sanitizeKeyList(array_keys($request));
    }

    /**
     * @param  list<int|string>  $keys
     * @return list<string>
     */
    protected function sanitizeKeyList(array $keys): array
    {
        $out = [];
        foreach ($keys as $k) {
            if (! is_string($k) || $k === '') {
                continue;
            }
            if ($this->isSensitiveKeyName($k)) {
                continue;
            }
            $out[] = $this->truncate($k, 80);
        }
        sort($out);

        return array_slice(array_values(array_unique($out)), 0, 32);
    }

    /**
     * @param  array<string, mixed>  $json
     * @return array{
     *   possible_air_item_paths: list<array{path: string, count: int}>,
     *   possible_segment_paths: list<array{path: string, count: int}>,
     *   possible_order_item_paths: list<array{path: string, count: int}>
     * }
     */
    /**
     * @param  array<string, mixed>  $json
     * @return list<array{category: string, path: string, count: int}>
     */
    protected function discoverCancelRelatedPaths(array $json): array
    {
        $bucket = [];

        $walker = function (mixed $node, string $path, int $depth) use (&$walker, &$bucket): void {
            if ($depth > self::MAX_DEPTH || ! is_array($node)) {
                return;
            }
            foreach ($node as $k => $v) {
                if (! is_string($k)) {
                    if (is_array($v)) {
                        $walker($v, $path, $depth + 1);
                    }

                    continue;
                }
                if ($this->isPiiBranchKey($k) || $this->isSensitiveKeyName($k)) {
                    continue;
                }
                $childPath = $path === '' ? $k : $path.'.'.$k;
                $lk = strtolower($k);
                $category = $this->cancelRelatedCategoryForKey($lk);
                if ($category !== null) {
                    if (is_array($v)) {
                        $count = $this->countArrayItems($v);
                        if ($count > 0 || $lk === 'canceldata' || str_ends_with($lk, 'id')) {
                            $this->pushCancelRelatedPath($bucket, $category, $childPath, max($count, 1));
                        }
                    } elseif (is_scalar($v) && trim((string) $v) !== '') {
                        $this->pushCancelRelatedPath($bucket, $category, $childPath, 1);
                    }
                }
                if (is_array($v)) {
                    $walker($v, $childPath, $depth + 1);
                }
            }
        };

        $walker($json, '', 0);

        $rows = [];
        foreach ($bucket as $category => $paths) {
            foreach ($paths as $path => $count) {
                $rows[] = [
                    'category' => $category,
                    'path' => $path,
                    'count' => (int) $count,
                ];
            }
        }
        usort($rows, static fn (array $a, array $b): int => [$a['category'], $a['path']] <=> [$b['category'], $b['path']]);

        return array_slice($rows, 0, self::MAX_PATHS);
    }

    /**
     * @param  array<string, array<string, int>>  $bucket
     */
    protected function pushCancelRelatedPath(array &$bucket, string $category, string $path, int $count): void
    {
        $path = $this->truncate($path, 120);
        if (! isset($bucket[$category][$path])) {
            $bucket[$category][$path] = $count;

            return;
        }
        $bucket[$category][$path] = max($bucket[$category][$path], $count);
    }

    protected function cancelRelatedCategoryForKey(string $lowerKey): ?string
    {
        foreach (self::CANCEL_RELATED_PATH_CATEGORIES as $category => $fragments) {
            foreach ($fragments as $frag) {
                if ($lowerKey === $frag || str_contains($lowerKey, $frag)) {
                    return $category;
                }
            }
        }

        return null;
    }

    /**
     * @param  list<array{category: string, path: string, count: int}>  $cancelPaths
     * @return array<string, mixed>
     */
    protected function cancelRelatedPresenceFlags(array $json, array $cancelPaths): array
    {
        $byCategory = [];
        foreach ($cancelPaths as $row) {
            $cat = (string) ($row['category'] ?? '');
            if ($cat === '') {
                continue;
            }
            $byCategory[$cat] = ($byCategory[$cat] ?? 0) + (int) ($row['count'] ?? 0);
        }

        $orderItemCount = (int) ($byCategory['order_item_ids'] ?? 0);
        $airItemCount = (int) ($byCategory['air_item_ids'] ?? 0);
        $serviceItemCount = (int) ($byCategory['service_item_ids'] ?? 0);
        $segmentIdCount = (int) ($byCategory['segment_ids'] ?? 0);

        return [
            'cancel_data_present' => ($byCategory['cancel_data'] ?? 0) > 0
                || $this->branchPresent($json, ['cancelData', 'cancel_data']),
            'order_id_present' => ($byCategory['order_id'] ?? 0) > 0
                || $this->nonEmptyScalarPresent($json, ['orderId', 'order_id']),
            'order_item_ids_path_count' => $orderItemCount,
            'air_item_ids_path_count' => $airItemCount,
            'service_item_ids_path_count' => $serviceItemCount,
            'segment_ids_path_count' => $segmentIdCount,
            'cancel_penalty_or_refund_present' => ($byCategory['cancel_penalty_refund'] ?? 0) > 0,
            'links_or_actions_present' => ($byCategory['links_actions'] ?? 0) > 0,
        ];
    }

    protected function discoverSafeItemPaths(array $json): array
    {
        $air = [];
        $segments = [];
        $orders = [];

        $walker = function (mixed $node, string $path, int $depth) use (&$walker, &$air, &$segments, &$orders): void {
            if ($depth > self::MAX_DEPTH || ! is_array($node)) {
                return;
            }
            foreach ($node as $k => $v) {
                if (! is_string($k)) {
                    if (is_array($v)) {
                        $walker($v, $path, $depth + 1);
                    }

                    continue;
                }
                if ($this->isPiiBranchKey($k) || $this->isSensitiveKeyName($k)) {
                    continue;
                }
                $childPath = $path === '' ? $k : $path.'.'.$k;
                $lk = strtolower($k);
                if (is_array($v)) {
                    $count = $this->countArrayItems($v);
                    if ($count > 0) {
                        if ($this->keyMatchesFragments($lk, self::AIR_PATH_KEY_FRAGMENTS)) {
                            $this->pushPathCount($air, $childPath, $count);
                        }
                        if ($this->keyMatchesFragments($lk, self::SEGMENT_PATH_KEY_FRAGMENTS)) {
                            $this->pushPathCount($segments, $childPath, $count);
                        }
                        if ($this->keyMatchesFragments($lk, self::ORDER_PATH_KEY_FRAGMENTS)) {
                            $this->pushPathCount($orders, $childPath, $count);
                        }
                    }
                    $walker($v, $childPath, $depth + 1);
                }
            }
        };

        $walker($json, '', 0);

        return [
            'possible_air_item_paths' => $this->finalizePathCounts($air),
            'possible_segment_paths' => $this->finalizePathCounts($segments),
            'possible_order_item_paths' => $this->finalizePathCounts($orders),
        ];
    }

    /**
     * @param  array<string, int>  $bucket
     */
    protected function pushPathCount(array &$bucket, string $path, int $count): void
    {
        $path = $this->truncate($path, 120);
        if (! isset($bucket[$path])) {
            $bucket[$path] = $count;

            return;
        }
        $bucket[$path] = max($bucket[$path], $count);
    }

    /**
     * @param  array<string, int>  $bucket
     * @return list<array{path: string, count: int}>
     */
    protected function finalizePathCounts(array $bucket): array
    {
        $rows = [];
        foreach ($bucket as $path => $count) {
            $rows[] = ['path' => $path, 'count' => (int) $count];
        }
        usort($rows, static fn (array $a, array $b): int => strcmp($a['path'], $b['path']));

        return array_slice($rows, 0, self::MAX_PATHS);
    }

    /**
     * @param  array<string, mixed>  $summary
     * @param  array{
     *   possible_air_item_paths: list<array{path: string, count: int}>,
     *   possible_segment_paths: list<array{path: string, count: int}>,
     *   possible_order_item_paths: list<array{path: string, count: int}>
     * }  $paths
     * @param  array<string, mixed>  $mapPreview
     * @return array{
     *   cancel_verification_possible: bool,
     *   cancel_verification_status: string,
     *   cancel_verification_reason: string
     * }
     */
    protected function inferCancellationVerification(
        int $httpStatus,
        array $summary,
        array $paths,
        array $mapPreview,
    ): array {
        if ($httpStatus === 403) {
            return [
                'cancel_verification_possible' => false,
                'cancel_verification_status' => 'retrieve_forbidden',
                'cancel_verification_reason' => 'getBooking_http_403_not_authorized',
            ];
        }

        if (! in_array($httpStatus, [200, 201], true)) {
            return [
                'cancel_verification_possible' => false,
                'cancel_verification_status' => 'unknown_no_status_fields',
                'cancel_verification_reason' => 'getBooking_http_not_success',
            ];
        }

        $isCancelable = $summary['is_cancelable_value'] ?? null;
        $isTicketed = $summary['is_ticketed_value'] ?? null;
        if (! is_bool($isCancelable) && ! is_bool($isTicketed)) {
            return [
                'cancel_verification_possible' => false,
                'cancel_verification_status' => 'unknown_no_status_fields',
                'cancel_verification_reason' => 'isCancelable_and_isTicketed_not_present_as_booleans',
            ];
        }

        $candidateSegmentCount = (int) ($mapPreview['candidate_segment_count'] ?? 0);
        $mappableSegmentCount = (int) ($mapPreview['mappable_segment_count'] ?? 0);
        $airCount = $this->sumPathCounts($paths['possible_air_item_paths'] ?? []);
        $segmentPathCount = $this->sumPathCounts($paths['possible_segment_paths'] ?? []);
        $orderCount = $this->sumPathCounts($paths['possible_order_item_paths'] ?? []);
        $travelItemCount = $airCount + $segmentPathCount + $orderCount;

        if ($isCancelable === true) {
            return [
                'cancel_verification_possible' => true,
                'cancel_verification_status' => 'likely_active',
                'cancel_verification_reason' => 'isCancelable_true',
            ];
        }

        // isCancelable === false (or only isTicketed known with cancelable false default path)
        if ($isCancelable === false) {
            if ($candidateSegmentCount > 0 || $mappableSegmentCount > 0) {
                return [
                    'cancel_verification_possible' => true,
                    'cancel_verification_status' => 'likely_active',
                    'cancel_verification_reason' => 'isCancelable_false_but_mappable_or_candidate_segments_present',
                ];
            }

            if ($travelItemCount === 0) {
                return [
                    'cancel_verification_possible' => true,
                    'cancel_verification_status' => 'likely_cancelled',
                    'cancel_verification_reason' => 'isCancelable_false_no_segment_paths_no_air_or_order_items',
                ];
            }

            return [
                'cancel_verification_possible' => false,
                'cancel_verification_status' => 'unknown_no_segments',
                'cancel_verification_reason' => 'isCancelable_false_travel_item_paths_present_but_not_mappable',
            ];
        }

        // Only isTicketed known
        if ($travelItemCount === 0 && $candidateSegmentCount === 0) {
            return [
                'cancel_verification_possible' => false,
                'cancel_verification_status' => 'unknown_no_segments',
                'cancel_verification_reason' => 'only_isTicketed_present_no_travel_item_paths',
            ];
        }

        return [
            'cancel_verification_possible' => false,
            'cancel_verification_status' => 'unknown_no_segments',
            'cancel_verification_reason' => 'isCancelable_missing_inconclusive_travel_items',
        ];
    }

    /**
     * @param  list<array{path: string, count: int}>  $paths
     */
    protected function sumPathCounts(array $paths): int
    {
        $sum = 0;
        foreach ($paths as $row) {
            if (! is_array($row)) {
                continue;
            }
            $sum += (int) ($row['count'] ?? 0);
        }

        return $sum;
    }

    /**
     * @param  array<int|string, mixed>  $node
     */
    protected function countArrayItems(array $node): int
    {
        if ($node === []) {
            return 0;
        }
        if (array_is_list($node)) {
            $n = 0;
            foreach ($node as $item) {
                if (is_array($item)) {
                    $n++;
                }
            }

            return $n;
        }

        return 1;
    }

    /**
     * @param  list<string>  $fragments
     */
    protected function keyMatchesFragments(string $lowerKey, array $fragments): bool
    {
        foreach ($fragments as $frag) {
            if ($lowerKey === $frag || str_contains($lowerKey, $frag)) {
                return true;
            }
        }

        return false;
    }

    protected function isPiiBranchKey(string $key): bool
    {
        $lk = strtolower($key);
        foreach (self::PII_BRANCH_KEYS as $frag) {
            if ($lk === $frag || str_contains($lk, $frag)) {
                return true;
            }
        }

        return $this->isSensitiveKeyName($key);
    }

    protected function isSensitiveKeyName(string $key): bool
    {
        $lk = strtolower($key);
        foreach ([
            'password', 'token', 'authorization', 'secret', 'pcc', 'pseudo',
            'email', 'phone', 'telephone', 'passport', 'document', 'givenname',
            'surname', 'personname', 'birthdate', 'dateofbirth',
        ] as $frag) {
            if (str_contains($lk, $frag)) {
                return true;
            }
        }

        return false;
    }

    protected function truncate(string $value, int $max = 120): string
    {
        $value = trim($value);
        if ($value === '' || strlen($value) <= $max) {
            return $value;
        }

        return substr($value, 0, $max);
    }

    /**
     * @param  list<string>  $paths
     * @param  list<array{path: string, value: string}>  $values
     */
    protected function pushLocatorObservation(array &$paths, array &$values, string $path, mixed $rawValue): void
    {
        $path = $this->truncate($path, 120);
        if (! in_array($path, $paths, true)) {
            $paths[] = $path;
        }
        $safeValue = $this->sanitizeRecordLocatorScalar($rawValue);
        if ($safeValue === null) {
            return;
        }
        foreach ($values as $row) {
            if (($row['path'] ?? '') === $path) {
                return;
            }
        }
        $values[] = ['path' => $path, 'value' => $safeValue];
    }

    /**
     * @param  list<string>  $paths
     * @return list<string>
     */
    protected function finalizeLocatorPaths(array $paths): array
    {
        sort($paths);

        return array_slice(array_values(array_unique($paths)), 0, self::MAX_LOCATOR_PATHS);
    }

    protected function isAirlineCarrierVendorLocatorKey(string $lowerKey, string $path): bool
    {
        if ($this->keyMatchesFragments($lowerKey, self::TRIP_ORDERS_ID_KEY_FRAGMENTS)) {
            return false;
        }
        if ($lowerKey === 'confirmationid' && ! $this->isSegmentScopedConfirmationIdPath($path)) {
            return false;
        }
        if ($lowerKey === 'supplierreference' && ! $this->isSegmentScopedConfirmationIdPath($path)) {
            return false;
        }
        if ($this->keyMatchesFragments($lowerKey, self::SABRE_RECORD_LOCATOR_KEY_FRAGMENTS)) {
            return false;
        }

        return $this->keyMatchesFragments($lowerKey, self::AIRLINE_CARRIER_VENDOR_LOCATOR_KEY_FRAGMENTS);
    }

    protected function isSegmentScopedConfirmationIdPath(string $path): bool
    {
        $lower = strtolower($path);
        foreach (['.flights.', '.segments.', '.allsegments.', '.journeys.', 'flights.', 'segments.', 'allsegments.', 'journeys.'] as $needle) {
            if (str_contains($lower, $needle)) {
                return true;
            }
        }

        return false;
    }

    protected function sanitizeRecordLocatorScalar(mixed $value): ?string
    {
        if (! is_string($value) && ! is_int($value) && ! is_float($value)) {
            return null;
        }
        $s = strtoupper(trim((string) $value));
        if ($s === '') {
            return null;
        }
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $s)) {
            return null;
        }
        if (strlen($s) > 32) {
            return null;
        }
        if (! preg_match('/^[A-Z0-9]{6}$/', $s)) {
            return null;
        }

        return $s;
    }
}
