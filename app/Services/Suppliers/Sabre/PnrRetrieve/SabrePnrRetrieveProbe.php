<?php

namespace App\Services\Suppliers\Sabre\PnrRetrieve;

use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Models\SupplierConnection;
use App\Services\Suppliers\Sabre\Booking\SabreBookingService;
use App\Services\Suppliers\Sabre\Core\SabreBookingClient;
use App\Services\Suppliers\Sabre\Core\SabreClient;
use App\Support\Security\SensitiveDataRedactor;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * B84A/B84B.0: Sabre PNR retrieve probe (safe digests only; no DB writes).
 * Phase 3G-R: {@code probeDirectPnr()} for CLI {@code --pnr} without a Booking row + {@code retrieve_summary}.
 * B84B.0: Optional {@code --shape-tree} and {@code --map-preview} for Trip Orders getBooking diagnostics.
 * B84B.1: {@code --map-preview} delegates to {@see SabreTripOrdersGetBookingItineraryMapper}.
 * B84B.3: {@code --map-preview} on getBooking adds {@see SabreTripOrdersGetBookingInspectSummary}.
 */
final class SabrePnrRetrieveProbe
{
    private const SHAPE_TREE_MAX_DEPTH = 6;

    private const SHAPE_TREE_MAX_LIST_ITEMS = 4;

    /** @var list<string> */
    private const SHAPE_TREE_FOCUS_KEYS = [
        'allSegments',
        'flights',
        'journeys',
        'fares',
        'fareOffers',
        'startDate',
        'endDate',
        'errors',
    ];

    /** @var list<string> */
    private const SHAPE_TREE_PII_BRANCH_KEYS = [
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

    /** @var list<string> Explicit getBooking / Trip Orders keys that may hold ticket numbers (not isTicketed/fares). */
    private const EXPLICIT_TICKET_NUMBER_TOP_LEVEL_KEYS = [
        'ticketnumbers',
        'ticketnumber',
        'eticketnumbers',
        'etickets',
        'electronicticketnumbers',
    ];

    /** @var list<string> */
    public const DEFAULT_ENDPOINT_PATHS = [
        '/v2.5.0/passenger/records?mode=read',
        '/v2.4.0/passenger/records?mode=read',
        '/v1/reservations/retrieve',
        '/v1/trip/orders/getBooking',
    ];

    public function __construct(
        protected SabreClient $sabreClient,
        protected SabreBookingClient $bookingClient,
        protected SabreTripOrdersGetBookingItineraryMapper $itineraryMapper,
        protected SabreTripOrdersGetBookingInspectSummary $getBookingInspectSummary,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function probe(
        Booking $booking,
        bool $send,
        ?string $pathOverride,
        string $bodyStyle,
        bool $previewJson,
        bool $shapeTree = false,
        bool $mapPreview = false,
    ): array {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $provider = strtolower(trim((string) ($meta['supplier_provider'] ?? $booking->supplier ?? '')));
        $pnr = $this->resolvePnr($booking, $meta);
        if ($pnr === '') {
            return SensitiveDataRedactor::redact([
                'error' => 'booking_missing_pnr',
                'booking_id' => $booking->id,
                'provider' => $provider,
            ]);
        }

        if ($provider !== SupplierProvider::Sabre->value) {
            return SensitiveDataRedactor::redact([
                'error' => 'booking_not_sabre',
                'booking_id' => $booking->id,
                'pnr' => $pnr,
                'provider' => $provider,
            ]);
        }

        $connection = $this->resolveConnection($meta);
        if ($connection === null) {
            return SensitiveDataRedactor::redact([
                'error' => 'sabre_connection_missing',
                'booking_id' => $booking->id,
                'pnr' => $pnr,
                'provider' => $provider,
            ]);
        }

        $supplierApiBookingId = $this->resolveSupplierApiBookingId($booking, $meta);
        $probeResult = $this->probeEndpoints(
            $connection,
            $pnr,
            $supplierApiBookingId,
            $send,
            $pathOverride,
            $bodyStyle,
            $previewJson,
            $shapeTree,
            $mapPreview,
        );

        if (isset($probeResult['error'])) {
            return SensitiveDataRedactor::redact(array_merge($probeResult, [
                'booking_id' => $booking->id,
                'pnr' => $pnr,
                'provider' => $provider,
            ]));
        }

        return SensitiveDataRedactor::redact([
            'booking_id' => $booking->id,
            'pnr' => $pnr,
            'provider' => $provider,
            'endpoint_host' => $probeResult['endpoint_host'],
            'live_call_attempted' => $send,
            'attempted_endpoints' => $probeResult['attempted_endpoints'],
            'best_candidate_endpoint' => $probeResult['best_candidate_endpoint'],
            'safe_to_map' => false,
        ]);
    }

    /**
     * Direct record-locator probe (no Booking row). Requires live {@code $send}.
     *
     * @return array<string, mixed>
     */
    public function probeDirectPnr(
        SupplierConnection $connection,
        string $pnr,
        bool $send,
        ?string $pathOverride,
        string $bodyStyle,
        bool $previewJson,
        bool $shapeTree = false,
        bool $mapPreview = false,
    ): array {
        $pnr = strtoupper(trim($pnr));
        $probeResult = $this->probeEndpoints(
            $connection,
            $pnr,
            null,
            $send,
            $pathOverride,
            $bodyStyle,
            $previewJson,
            $shapeTree,
            $mapPreview,
        );

        if (isset($probeResult['error'])) {
            return SensitiveDataRedactor::redact(array_merge($probeResult, [
                'pnr' => $pnr,
                'connection_id' => $connection->id,
                'probe_mode' => 'direct_pnr',
            ]));
        }

        $bestRow = $probeResult['best_candidate_row'];
        $redacted = SensitiveDataRedactor::redact([
            'probe_mode' => 'direct_pnr',
            'connection_id' => $connection->id,
            'pnr' => $pnr,
            'provider' => SupplierProvider::Sabre->value,
            'endpoint_host' => $probeResult['endpoint_host'],
            'live_call_attempted' => $send,
            'attempted_endpoints' => $probeResult['attempted_endpoints'],
            'best_candidate_endpoint' => $probeResult['best_candidate_endpoint'],
            'safe_to_map' => is_array($bestRow) && ($bestRow['map_preview']['safe_to_map_preview'] ?? false) === true,
        ]);
        $redacted['retrieve_summary'] = $this->buildRetrieveSummary($pnr, $bestRow);

        return $redacted;
    }

    /**
     * @return array{
     *     error?: string,
     *     endpoint_host: string,
     *     attempted_endpoints: list<array<string, mixed>>,
     *     best_candidate_endpoint: ?string,
     *     best_candidate_row: ?array<string, mixed>
     * }
     */
    protected function probeEndpoints(
        SupplierConnection $connection,
        string $pnr,
        ?string $supplierApiBookingId,
        bool $send,
        ?string $pathOverride,
        string $bodyStyle,
        bool $previewJson,
        bool $shapeTree,
        bool $mapPreview,
    ): array {
        $base = rtrim((string) ($connection->base_url ?: config('suppliers.sabre.default_base_url')), '/');
        $host = parse_url(str_contains($base, '://') ? $base : 'https://'.$base, PHP_URL_HOST);
        $endpointHost = is_string($host) && $host !== '' ? $host : 'unknown';

        $paths = $this->resolveEndpointPaths($pathOverride);
        $attempted = [];
        $bestCandidate = null;
        $bestRow = null;
        $bestScore = -1;

        $token = null;
        if ($send) {
            try {
                $token = $this->sabreClient->getAccessToken($connection);
            } catch (Throwable) {
                return [
                    'error' => 'sabre_auth_failed',
                    'endpoint_host' => $endpointHost,
                    'attempted_endpoints' => [],
                    'best_candidate_endpoint' => null,
                    'best_candidate_row' => null,
                ];
            }
        }

        $timeouts = $this->sabreClient->httpTimeoutSettings();
        $timeout = $timeouts['timeout_seconds'];
        $connectTimeout = $timeouts['connect_timeout_seconds'];

        foreach ($paths as $path) {
            $style = $this->resolveBodyStyleForPath($path, $bodyStyle);
            $body = $this->buildRequestBody($style, $pnr, $supplierApiBookingId);
            $row = [
                'endpoint_path' => $path,
                'body_style' => $style,
                'http_status' => 0,
                'available' => false,
                'access_result' => $send ? 'unknown' : 'inspect_only',
                'top_level_keys_sanitized' => [],
                'application_results_status' => null,
                'error_codes_sanitized' => [],
                'error_messages_truncated' => [],
                'segment_count_inferred' => 0,
                'has_departure_datetime' => false,
                'has_arrival_datetime' => false,
                'has_itinerary_ref' => false,
                'has_travel_itinerary' => false,
                'raw_response_stored' => false,
            ];

            if ($previewJson) {
                $row['request_body_redacted'] = $this->redactRequestBodyForPreview($body);
            }

            if (! $send) {
                $attempted[] = $row;

                continue;
            }

            $url = $base.$this->normalizePath($path);
            $httpStatus = 0;
            $transport = null;
            $arr = [];
            try {
                $response = Http::withToken((string) $token)
                    ->acceptJson()
                    ->timeout($timeout)
                    ->connectTimeout($connectTimeout)
                    ->withBody((string) json_encode($body), 'application/json')
                    ->post($url);
                $httpStatus = $response->status();
                $json = $response->json();
                $arr = is_array($json) ? $json : [];
            } catch (ConnectionException $e) {
                $transport = $this->transportLabelFromConnectionException($e);
            } catch (Throwable) {
                $transport = 'network';
            }

            $access = SabreBookingService::discoveryAccessResultForProbe($httpStatus, $transport);
            $digest = $arr !== [] ? $this->bookingClient->digestBookingResponseJsonForProbe($arr) : [];
            $structure = $this->inferRetrieveStructureHints($arr);

            $codes = isset($digest['response_error_codes']) && is_array($digest['response_error_codes'])
                ? array_slice(array_map('strval', $digest['response_error_codes']), 0, 12)
                : [];
            $messages = isset($digest['response_error_messages']) && is_array($digest['response_error_messages'])
                ? array_slice(array_map('strval', $digest['response_error_messages']), 0, 8)
                : [];
            if (isset($digest['host_warning_messages_truncated']) && is_array($digest['host_warning_messages_truncated'])) {
                $messages = array_values(array_unique(array_merge(
                    $messages,
                    array_slice(array_map('strval', $digest['host_warning_messages_truncated']), 0, 8)
                )));
            }
            $messages = array_slice($messages, 0, 8);

            $appStatus = isset($digest['application_results_status']) && is_string($digest['application_results_status'])
                ? $this->truncateSafe(trim($digest['application_results_status']), 64)
                : null;

            $row = array_merge($row, [
                'http_status' => $httpStatus,
                'available' => in_array($httpStatus, [200, 201], true),
                'access_result' => $access,
                'top_level_keys_sanitized' => $this->sanitizeTopLevelKeys($arr),
                'application_results_status' => $appStatus,
                'error_codes_sanitized' => $codes,
                'error_messages_truncated' => $messages,
                'segment_count_inferred' => $structure['segment_count_inferred'],
                'has_departure_datetime' => $structure['has_departure_datetime'],
                'has_arrival_datetime' => $structure['has_arrival_datetime'],
                'has_itinerary_ref' => $structure['has_itinerary_ref'],
                'has_travel_itinerary' => $structure['has_travel_itinerary'],
            ]);

            if ($shapeTree && $arr !== []) {
                $row['shape_tree'] = $this->buildSafeShapeTree($arr);
            }
            if ($mapPreview) {
                $mapPreviewPayload = $this->itineraryMapper->mapPreview($arr, [
                    'http_status' => $httpStatus,
                    'response_error_codes' => $codes,
                    'response_error_messages' => $messages,
                ]);
                $row['map_preview'] = $mapPreviewPayload;
                if ($this->isTripOrdersGetBookingPath($path)) {
                    $row = array_merge($row, $this->getBookingInspectSummary->buildForProbeRow($arr, [
                        'http_status' => $httpStatus,
                        'map_preview' => $mapPreviewPayload,
                    ]));
                }
            }

            $row = SensitiveDataRedactor::redact($row);
            $attempted[] = $row;

            $score = $this->scoreCandidateRow($row);
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestCandidate = $path;
                $bestRow = $row;
            }
        }

        return [
            'endpoint_host' => $endpointHost,
            'attempted_endpoints' => $attempted,
            'best_candidate_endpoint' => $bestCandidate,
            'best_candidate_row' => $bestRow,
        ];
    }

    /**
     * @param  ?array<string, mixed>  $bestRow
     * @return array<string, mixed>
     */
    protected function buildRetrieveSummary(string $pnr, ?array $bestRow): array
    {
        if ($bestRow === null) {
            return [
                'pnr' => $pnr,
                'endpoint_path' => null,
                'http_status' => null,
                'retrieve_success' => false,
                'segment_count' => 0,
                'segment_statuses' => [],
                'carrier_chain' => null,
                'passenger_present' => false,
                'ticketing_present' => false,
                'ticket_numbers_present' => false,
                'warnings_errors_sanitized' => [],
            ];
        }

        $mapPreview = is_array($bestRow['map_preview'] ?? null) ? $bestRow['map_preview'] : [];
        $statusSummary = is_array($bestRow['get_booking_status_summary'] ?? null)
            ? $bestRow['get_booking_status_summary']
            : [];
        $segments = is_array($mapPreview['candidate_rows'] ?? null) ? $mapPreview['candidate_rows'] : [];
        if ($segments === [] && is_array($mapPreview['segments'] ?? null)) {
            $segments = $mapPreview['segments'];
        }

        $segmentStatuses = [];
        $carriers = [];
        foreach ($segments as $segment) {
            if (! is_array($segment)) {
                continue;
            }
            $status = strtoupper(trim((string) (
                $segment['segment_status']
                ?? $segment['flight_status_code']
                ?? $segment['status']
                ?? ''
            )));
            if ($status !== '') {
                $segmentStatuses[] = substr($status, 0, 8);
            }
            $carrier = strtoupper(trim((string) ($segment['marketing_airline'] ?? $segment['airline_code'] ?? '')));
            if ($carrier !== '') {
                $carriers[] = $carrier;
            }
        }
        $segmentStatuses = array_values(array_unique(array_slice($segmentStatuses, 0, 12)));
        $carriers = array_values(array_unique(array_slice($carriers, 0, 8)));
        $carrierChain = $carriers !== [] ? implode('+', $carriers) : null;

        $segmentCount = (int) ($mapPreview['mappable_segment_count'] ?? 0);
        if ($segmentCount === 0) {
            $segmentCount = (int) ($mapPreview['candidate_segment_count'] ?? 0);
        }
        if ($segmentCount === 0) {
            $segmentCount = (int) ($bestRow['segment_count_inferred'] ?? 0);
        }

        $passengerPresent = in_array('travelers', (array) ($bestRow['top_level_keys_sanitized'] ?? []), true)
            || ($statusSummary['contact_info_present'] ?? false) === true
            || ($bestRow['has_travel_itinerary'] ?? false) === true;

        $isTicketed = $statusSummary['is_ticketed_value'] ?? null;
        $ticketingPresent = $isTicketed === true;
        $ticketNumbersPresent = $this->detectExplicitTicketNumberFieldsPresent($bestRow);

        $warningsErrors = array_values(array_unique(array_merge(
            array_slice((array) ($bestRow['error_codes_sanitized'] ?? []), 0, 12),
            array_slice((array) ($bestRow['error_messages_truncated'] ?? []), 0, 8),
        )));

        $httpStatus = isset($bestRow['http_status']) ? (int) $bestRow['http_status'] : null;
        $retrieveSuccess = in_array($httpStatus, [200, 201], true)
            && (
                $segmentCount > 0
                || ($bestRow['has_itinerary_ref'] ?? false) === true
                || ($mapPreview['safe_to_map_preview'] ?? false) === true
            );

        return [
            'pnr' => $pnr,
            'endpoint_path' => $bestRow['endpoint_path'] ?? null,
            'http_status' => $httpStatus,
            'retrieve_success' => $retrieveSuccess,
            'segment_count' => $segmentCount,
            'segment_statuses' => $segmentStatuses,
            'carrier_chain' => $carrierChain,
            'passenger_present' => $passengerPresent,
            'ticketing_present' => $ticketingPresent,
            'ticket_numbers_present' => $ticketNumbersPresent,
            'warnings_errors_sanitized' => array_slice($warningsErrors, 0, 16),
        ];
    }

    /**
     * @param  array<string, mixed>  $bestRow
     */
    protected function detectExplicitTicketNumberFieldsPresent(array $bestRow): bool
    {
        foreach ((array) ($bestRow['top_level_keys_sanitized'] ?? []) as $key) {
            if (! is_string($key)) {
                continue;
            }
            $normalized = strtolower(trim($key));
            if (in_array($normalized, self::EXPLICIT_TICKET_NUMBER_TOP_LEVEL_KEYS, true)) {
                return true;
            }
        }

        $shapeTree = is_array($bestRow['shape_tree'] ?? null) ? $bestRow['shape_tree'] : [];
        foreach (['ticketNumbers', 'ticketNumber', 'eticketNumbers', 'eTickets', 'electronicTicketNumbers'] as $branch) {
            if (array_key_exists($branch, $shapeTree) && is_array($shapeTree[$branch])) {
                $node = $shapeTree[$branch];
                if (($node['_skipped'] ?? null) === 'pii_branch') {
                    continue;
                }
                if (($node['_type'] ?? '') === 'list' && (int) ($node['length'] ?? 0) > 0) {
                    return true;
                }
                if (($node['_type'] ?? '') === 'object' && ($node['keys'] ?? []) !== []) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * B84B.2: Live {@code POST /v1/trip/orders/getBooking} for one booking (no DB writes).
     *
     * @return array<string, mixed>
     */
    public function fetchTripOrdersGetBooking(Booking $booking): array
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $provider = strtolower(trim((string) ($meta['supplier_provider'] ?? $booking->supplier ?? '')));
        $pnr = $this->resolvePnr($booking, $meta);
        if ($pnr === '') {
            return SensitiveDataRedactor::redact([
                'error' => 'booking_missing_pnr',
                'booking_id' => $booking->id,
            ]);
        }

        if ($provider !== SupplierProvider::Sabre->value) {
            return SensitiveDataRedactor::redact([
                'error' => 'booking_not_sabre',
                'booking_id' => $booking->id,
                'pnr' => $pnr,
            ]);
        }

        $connection = $this->resolveConnection($meta);
        if ($connection === null) {
            return SensitiveDataRedactor::redact([
                'error' => 'sabre_connection_missing',
                'booking_id' => $booking->id,
                'pnr' => $pnr,
            ]);
        }

        $supplierApiBookingId = $this->resolveSupplierApiBookingId($booking, $meta);
        $result = $this->executeTripOrdersGetBookingPost($connection, $pnr, $supplierApiBookingId);

        return SensitiveDataRedactor::redact(array_merge($result, [
            'booking_id' => $booking->id,
            'pnr' => $pnr,
        ]));
    }

    /**
     * Phase 3G-Cancel: Live getBooking for direct --pnr cancel inspect (no Booking row).
     *
     * @return array<string, mixed>
     */
    public function fetchTripOrdersGetBookingDirect(SupplierConnection $connection, string $pnr): array
    {
        $pnr = strtoupper(trim($pnr));
        $result = $this->executeTripOrdersGetBookingPost($connection, $pnr, null);

        return SensitiveDataRedactor::redact(array_merge($result, [
            'connection_id' => $connection->id,
            'pnr' => $pnr,
        ]));
    }

    /**
     * @return array<string, mixed>
     */
    protected function executeTripOrdersGetBookingPost(
        SupplierConnection $connection,
        string $pnr,
        ?string $supplierApiBookingId,
    ): array {
        $pnr = strtoupper(trim($pnr));
        $base = rtrim((string) ($connection->base_url ?: config('suppliers.sabre.default_base_url')), '/');
        $path = '/v1/trip/orders/getBooking';
        $body = $this->buildRequestBody('trip_orders_get_booking', $pnr, $supplierApiBookingId);

        try {
            $token = $this->sabreClient->getAccessToken($connection);
        } catch (Throwable) {
            return [
                'error' => 'sabre_auth_failed',
                'pnr' => $pnr,
            ];
        }

        $timeouts = $this->sabreClient->httpTimeoutSettings();
        $httpStatus = 0;
        $arr = [];
        try {
            $response = Http::withToken((string) $token)
                ->acceptJson()
                ->timeout($timeouts['timeout_seconds'])
                ->connectTimeout($timeouts['connect_timeout_seconds'])
                ->withBody((string) json_encode($body), 'application/json')
                ->post($base.$this->normalizePath($path));
            $httpStatus = $response->status();
            $json = $response->json();
            $arr = is_array($json) ? $json : [];
        } catch (ConnectionException) {
            return [
                'error' => 'sabre_connection_failed',
                'pnr' => $pnr,
            ];
        } catch (Throwable) {
            return [
                'error' => 'sabre_request_failed',
                'pnr' => $pnr,
            ];
        }

        $digest = $arr !== [] ? $this->bookingClient->digestBookingResponseJsonForProbe($arr) : [];
        $codes = isset($digest['response_error_codes']) && is_array($digest['response_error_codes'])
            ? array_slice(array_map('strval', $digest['response_error_codes']), 0, 12)
            : [];
        $messages = isset($digest['response_error_messages']) && is_array($digest['response_error_messages'])
            ? array_slice(array_map('strval', $digest['response_error_messages']), 0, 8)
            : [];
        if (isset($digest['host_warning_messages_truncated']) && is_array($digest['host_warning_messages_truncated'])) {
            $messages = array_values(array_unique(array_merge(
                $messages,
                array_slice(array_map('strval', $digest['host_warning_messages_truncated']), 0, 8)
            )));
        }

        return [
            'endpoint_path' => $path,
            'http_status' => $httpStatus,
            'json' => $arr,
            'response_error_codes' => array_slice($codes, 0, 12),
            'response_error_messages' => array_slice($messages, 0, 8),
        ];
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    protected function resolvePnr(Booking $booking, array $meta): string
    {
        foreach ([
            $booking->pnr,
            $booking->supplier_reference,
            data_get($meta, 'sabre_provider_snapshot.pnr'),
            data_get($meta, 'pnr'),
        ] as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                return strtoupper(substr(trim($candidate), 0, 32));
            }
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    protected function resolveSupplierApiBookingId(Booking $booking, array $meta): ?string
    {
        foreach ([
            data_get($meta, 'sabre_provider_snapshot.supplier_api_booking_id'),
            data_get($meta, 'supplier_api_booking_id'),
        ] as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                return trim($candidate);
            }
        }

        $booking->loadMissing('supplierBookings');
        foreach ($booking->supplierBookings as $sb) {
            $ref = trim((string) ($sb->supplier_reference ?? ''));
            if ($ref !== '' && strtoupper($ref) !== strtoupper((string) $this->resolvePnr($booking, $meta))) {
                return $ref;
            }
            $apiId = trim((string) ($sb->supplier_api_booking_id ?? ''));
            if ($apiId !== '') {
                return $apiId;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    protected function resolveConnection(array $meta): ?SupplierConnection
    {
        $cid = data_get($meta, 'supplier_connection_id');
        $cid = is_numeric($cid) ? (int) $cid : 0;
        if ($cid > 0) {
            $c = SupplierConnection::query()->find($cid);
            if ($c !== null && $c->provider === SupplierProvider::Sabre) {
                return $c;
            }
        }

        return SupplierConnection::query()
            ->where('provider', SupplierProvider::Sabre)
            ->orderBy('id')
            ->first();
    }

    /**
     * @return list<string>
     */
    protected function resolveEndpointPaths(?string $pathOverride): array
    {
        $custom = is_string($pathOverride) ? trim($pathOverride) : '';
        if ($custom !== '') {
            return [$this->normalizePath($custom)];
        }

        return self::DEFAULT_ENDPOINT_PATHS;
    }

    protected function isTripOrdersGetBookingPath(string $path): bool
    {
        return str_contains(strtolower($path), 'getbooking');
    }

    protected function normalizePath(string $path): string
    {
        $path = trim($path);
        if ($path === '') {
            return '/';
        }

        return $path[0] === '/' ? $path : '/'.$path;
    }

    protected function resolveBodyStyleForPath(string $path, string $bodyStyleOption): string
    {
        $opt = strtolower(trim($bodyStyleOption));
        if ($opt !== '' && $opt !== 'auto') {
            return $opt;
        }
        $lower = strtolower($path);
        if (str_contains($lower, 'passenger/records')) {
            return 'passenger_records_read';
        }
        if (str_contains($lower, 'reservations/retrieve')) {
            return 'reservation_retrieve';
        }
        if (str_contains($lower, 'getbooking')) {
            return 'trip_orders_get_booking';
        }

        return 'passenger_records_read';
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildRequestBody(string $style, string $pnr, ?string $supplierApiBookingId): array
    {
        return match ($style) {
            'trip_orders_get_booking' => [
                'confirmationId' => $supplierApiBookingId !== null && $supplierApiBookingId !== ''
                    ? $supplierApiBookingId
                    : $pnr,
            ],
            'reservation_retrieve', 'passenger_records_read' => ['recordLocator' => $pnr],
            default => ['recordLocator' => $pnr],
        };
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    protected function redactRequestBodyForPreview(array $body): array
    {
        $out = [];
        foreach ($body as $k => $v) {
            if (is_string($v) && $v !== '') {
                $out[$k] = '***REDACTED***';
            } else {
                $out[$k] = $v;
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $json
     * @return list<string>
     */
    protected function sanitizeTopLevelKeys(array $json): array
    {
        $keys = [];
        foreach (array_keys($json) as $k) {
            if (! is_string($k) || $k === '') {
                continue;
            }
            if ($this->isSensitiveKeyName($k)) {
                continue;
            }
            $keys[] = $this->truncateSafe($k, 80);
        }
        sort($keys);

        return array_slice(array_values(array_unique($keys)), 0, 32);
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

    /**
     * @param  array<string, mixed>  $json
     * @return array{
     *   segment_count_inferred: int,
     *   has_departure_datetime: bool,
     *   has_arrival_datetime: bool,
     *   has_itinerary_ref: bool,
     *   has_travel_itinerary: bool
     * }
     */
    protected function inferRetrieveStructureHints(array $json): array
    {
        $segmentCount = 0;
        $hasDep = false;
        $hasArr = false;
        $hasItinRef = false;
        $hasTravelItin = false;

        $walker = function (mixed $node, int $depth) use (
            &$walker,
            &$segmentCount,
            &$hasDep,
            &$hasArr,
            &$hasItinRef,
            &$hasTravelItin,
        ): void {
            if ($depth > 14 || ! is_array($node)) {
                return;
            }
            foreach ($node as $k => $v) {
                if (! is_string($k)) {
                    if (is_array($v)) {
                        $walker($v, $depth + 1);
                    }

                    continue;
                }
                $lk = strtolower($k);
                if ($lk === 'itineraryref') {
                    $hasItinRef = true;
                }
                if ($lk === 'travelitinerary') {
                    $hasTravelItin = true;
                }
                if (in_array($lk, ['departuredatetime', 'departuredate', 'departure_at'], true)
                    && is_string($v) && trim($v) !== '') {
                    $hasDep = true;
                }
                if (in_array($lk, ['arrivaldatetime', 'arrivaldate', 'arrival_at'], true)
                    && is_string($v) && trim($v) !== '') {
                    $hasArr = true;
                }
                if ($lk === 'flightsegment' && is_array($v)) {
                    if ($v !== [] && array_is_list($v)) {
                        $segmentCount += count($v);
                    } else {
                        $segmentCount += 1;
                    }
                }
                if (is_array($v)) {
                    $walker($v, $depth + 1);
                }
            }
        };

        $walker($json, 0);

        return [
            'segment_count_inferred' => $segmentCount,
            'has_departure_datetime' => $hasDep,
            'has_arrival_datetime' => $hasArr,
            'has_itinerary_ref' => $hasItinRef,
            'has_travel_itinerary' => $hasTravelItin,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    protected function scoreCandidateRow(array $row): int
    {
        $score = 0;
        if (($row['available'] ?? false) === true) {
            $score += 100;
        }
        $score += min(40, (int) ($row['segment_count_inferred'] ?? 0) * 10);
        if (($row['has_departure_datetime'] ?? false) === true) {
            $score += 15;
        }
        if (($row['has_arrival_datetime'] ?? false) === true) {
            $score += 15;
        }
        if (($row['has_travel_itinerary'] ?? false) === true) {
            $score += 5;
        }
        if (($row['has_itinerary_ref'] ?? false) === true) {
            $score += 5;
        }

        return $score;
    }

    protected function transportLabelFromConnectionException(ConnectionException $e): string
    {
        $m = strtolower($e->getMessage());
        if (str_contains($m, 'timed out') || str_contains($m, 'timeout') || str_contains($m, 'curl error 28')) {
            return 'timeout';
        }

        return 'network';
    }

    protected function truncateSafe(string $value, int $max = 120): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        if (strlen($value) <= $max) {
            return $value;
        }

        return substr($value, 0, $max);
    }

    /**
     * B84B.0: Safe key/type tree for Trip Orders getBooking (no scalar values, PII branches skipped).
     *
     * @param  array<string, mixed>  $json
     * @return array<string, mixed>
     */
    protected function buildSafeShapeTree(array $json): array
    {
        $out = [];
        foreach (self::SHAPE_TREE_FOCUS_KEYS as $focusKey) {
            if (! array_key_exists($focusKey, $json)) {
                continue;
            }
            $node = $json[$focusKey];
            if ($this->isShapeTreePiiBranchKey($focusKey)) {
                $out[$focusKey] = ['_skipped' => 'pii_branch'];

                continue;
            }
            $out[$focusKey] = $this->shapeTreeNode($node, 0, self::SHAPE_TREE_MAX_DEPTH);
        }

        foreach (array_keys($json) as $k) {
            if (! is_string($k) || $k === '' || in_array($k, self::SHAPE_TREE_FOCUS_KEYS, true)) {
                continue;
            }
            if ($this->isShapeTreePiiBranchKey($k) || $this->isSensitiveKeyName($k)) {
                $out[$k] = ['_skipped' => 'pii_branch'];

                continue;
            }
            $out[$k] = $this->shapeTreeNode($json[$k], 0, 2);
        }

        ksort($out);

        return $out;
    }

    protected function shapeTreeNode(mixed $node, int $depth, int $maxDepth): mixed
    {
        if ($depth >= $maxDepth) {
            return ['_truncated' => 'max_depth'];
        }
        if ($node === null) {
            return ['_type' => 'null'];
        }
        if (is_bool($node)) {
            return ['_type' => 'bool'];
        }
        if (is_int($node) || is_float($node)) {
            return ['_type' => 'number'];
        }
        if (is_string($node)) {
            return ['_type' => 'string'];
        }
        if (! is_array($node)) {
            return ['_type' => gettype($node)];
        }
        if ($node === []) {
            return ['_type' => 'array', 'empty' => true];
        }
        if (array_is_list($node)) {
            $len = count($node);
            $items = [];
            $cap = min($len, self::SHAPE_TREE_MAX_LIST_ITEMS);
            for ($i = 0; $i < $cap; $i++) {
                $items[] = $this->shapeTreeNode($node[$i], $depth + 1, $maxDepth);
            }

            return [
                '_type' => 'list',
                'length' => $len,
                'items_sampled' => $cap,
                'items' => $items,
            ];
        }

        $out = ['_type' => 'object', 'keys' => []];
        $keys = array_keys($node);
        sort($keys);
        foreach ($keys as $k) {
            if (! is_string($k)) {
                continue;
            }
            if ($this->isShapeTreePiiBranchKey($k) || $this->isSensitiveKeyName($k)) {
                $out['keys'][$k] = ['_skipped' => 'pii_branch'];

                continue;
            }
            $out['keys'][$k] = $this->shapeTreeNode($node[$k], $depth + 1, $maxDepth);
        }

        return $out;
    }

    protected function isShapeTreePiiBranchKey(string $key): bool
    {
        $lk = strtolower($key);
        foreach (self::SHAPE_TREE_PII_BRANCH_KEYS as $frag) {
            if ($lk === $frag || str_contains($lk, $frag)) {
                return true;
            }
        }

        return $this->isSensitiveKeyName($key);
    }
}
