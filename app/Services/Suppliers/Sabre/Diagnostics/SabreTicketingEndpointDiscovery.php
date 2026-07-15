<?php

namespace App\Services\Suppliers\Sabre\Diagnostics;

use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Models\SupplierConnection;
use App\Services\Suppliers\Sabre\Core\SabreBookingClient;
use App\Services\Suppliers\Sabre\Core\SabreClient;
use App\Services\Suppliers\Sabre\SabreBookingService;
use App\Support\Security\SensitiveDataRedactor;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * T2B: Local/testing-only Sabre ticketing REST endpoint discovery (inspect-only matrix + optional safe entitlement probes).
 * Never sends issue-ticket, FOP/payment, cancelBooking, or void/refund payloads. No DB writes.
 */
final class SabreTicketingEndpointDiscovery
{
    public const DEFAULT_MAX_CALLS = 12;

    /** @var list<string> */
    private const FORBIDDEN_BODY_KEYS = [
        'passengers', 'passenger', 'travelers', 'traveler', 'personName', 'givenName', 'surname',
        'email', 'phone', 'contact', 'payment', 'payments', 'fop', 'creditCard', 'card',
        'document', 'passport', 'dateOfBirth', 'birthDate', 'printer', 'commission', 'tourCode',
        'segments', 'flightSegment', 'itinerary', 'ticketing', 'issueTicket', 'fulfillmentRequest',
    ];

    /** @var list<string> */
    private const DESTRUCTIVE_PATH_FRAGMENTS = [
        '/v1/trip/orders/cancelBooking',
        'cancelBooking',
        '/void',
        '/refund',
        'voidTicket',
        'refundTicket',
    ];

    public function __construct(
        protected SabreClient $sabreClient,
        protected SabreBookingClient $bookingClient,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function discover(
        SupplierConnection $connection,
        ?Booking $booking,
        bool $send,
        int $maxCalls,
        ?string $pathOverride = null,
        ?string $methodOverride = null,
    ): array {
        $pnr = $booking !== null ? $this->resolvePnrForProbe($booking) : '';
        $pnrPresent = $pnr !== '';
        $supplierApiBookingId = $booking !== null ? $this->resolveSupplierApiBookingId($booking) : null;

        $candidates = $this->buildCandidateMatrix($pnrPresent, $supplierApiBookingId);
        $custom = is_string($pathOverride) ? trim($pathOverride) : '';
        if ($custom !== '' && ! $this->isDestructivePath($custom)) {
            $method = strtoupper(trim((string) $methodOverride) ?: 'POST');
            $candidates[] = $this->customPathCandidate($custom, $method, $pnrPresent);
        }

        $liveCallAttempted = false;
        $callsMade = 0;
        $token = null;

        if ($send) {
            $liveCallAttempted = true;
            try {
                $token = $this->sabreClient->getAccessToken($connection);
            } catch (Throwable) {
                return SensitiveDataRedactor::redact($this->rootPayload(
                    $connection,
                    $booking,
                    $pnrPresent,
                    true,
                    $maxCalls,
                    array_map(
                        fn (array $row): array => $this->mergeProbeRow($row, [
                            'live_call_attempted' => true,
                            'http_status' => 0,
                            'available' => false,
                            'access_result' => 'transport_error',
                            'entitlement_hint' => 'oauth_failed',
                            'notes' => 'sabre_auth_failed',
                        ]),
                        $candidates,
                    ),
                    'resolve_sabre_connection_credentials',
                ));
            }
        }

        $base = rtrim((string) ($connection->base_url ?: config('suppliers.sabre.default_base_url')), '/');
        $timeouts = $this->sabreClient->httpTimeoutSettings();
        $probed = [];

        foreach ($candidates as $candidate) {
            $row = $candidate;
            if (! $send) {
                $row['live_call_attempted'] = false;
                $probed[] = SensitiveDataRedactor::redact($row);

                continue;
            }

            if (($row['should_probe'] ?? false) !== true) {
                $row['live_call_attempted'] = false;
                $probed[] = SensitiveDataRedactor::redact($row);

                continue;
            }

            if ($this->isDestructivePath((string) ($row['endpoint_path'] ?? ''))) {
                continue;
            }

            if ($callsMade >= $maxCalls) {
                $row['live_call_attempted'] = false;
                $row['notes'] = trim(((string) ($row['notes'] ?? '')).' max_calls_reached_skipped');
                $probed[] = SensitiveDataRedactor::redact($row);

                continue;
            }

            $probeResult = $this->executeSafeProbe(
                $connection,
                $base,
                (string) $token,
                $row,
                $pnr,
                $supplierApiBookingId,
                $timeouts['timeout_seconds'],
                $timeouts['connect_timeout_seconds'],
            );
            $callsMade++;
            $probed[] = SensitiveDataRedactor::redact(array_merge($row, $probeResult, ['live_call_attempted' => true]));
        }

        return SensitiveDataRedactor::redact($this->rootPayload(
            $connection,
            $booking,
            $pnrPresent,
            $liveCallAttempted,
            $maxCalls,
            $probed,
            $this->recommendedNextAction($probed, $send, $pnrPresent),
        ));
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function buildCandidateMatrix(bool $pnrPresent, ?string $supplierApiBookingId): array
    {
        $getBookingBody = $pnrPresent
            ? ($supplierApiBookingId !== null && $supplierApiBookingId !== ''
                ? 'confirmation_id_probe'
                : 'confirmation_id_probe')
            : 'empty_probe';

        $minimalBody = $pnrPresent ? 'minimal_confirmation_id_probe' : 'empty_probe';

        $rows = [
            $this->candidateRow(
                'trip_orders',
                '/v1/trip/orders/getBooking',
                'POST',
                $getBookingBody,
                'low',
                false,
                true,
                'read_status_is_ticketed',
                $pnrPresent ? 'safe_read_probe_when_pnr_present' : 'empty_probe_without_pnr',
            ),
            $this->candidateRow(
                'trip_orders',
                '/v1/trip/orders/{orderId}/fulfillment',
                'POST',
                $minimalBody,
                'medium',
                false,
                true,
                'fulfillment_entitlement_probe',
                'no_fop_or_payment_payload',
            ),
            $this->candidateRow(
                'trip_orders',
                '/v1/trip/orders/fulfill',
                'POST',
                $minimalBody,
                'medium',
                false,
                true,
                'fulfillment_entitlement_probe',
                'no_fop_or_payment_payload',
            ),
            $this->candidateRow(
                'trip_orders',
                '/v1/trip/orders/fulfillment',
                'POST',
                $minimalBody,
                'medium',
                false,
                true,
                'fulfillment_entitlement_probe',
                'no_fop_or_payment_payload',
            ),
            $this->candidateRow(
                'rest_air_ticket',
                '/v1/air/ticket',
                'POST',
                'empty_probe',
                'high',
                false,
                true,
                'air_ticket_entitlement_probe',
                'empty_body_only',
            ),
            $this->candidateRow(
                'rest_air_ticket',
                '/v2/air/ticket',
                'POST',
                'empty_probe',
                'high',
                false,
                true,
                'air_ticket_entitlement_probe',
                'empty_body_only',
            ),
            $this->candidateRow(
                'rest_air_ticket',
                '/v1/air/tickets',
                'POST',
                'empty_probe',
                'high',
                false,
                true,
                'air_ticket_entitlement_probe',
                'empty_body_only',
            ),
            $this->candidateRow(
                'passenger_records',
                '/v2.5.0/passenger/records?mode=update',
                'POST',
                'empty_probe',
                'high',
                false,
                true,
                'passenger_records_update_entitlement',
                'no_ticketing_payload',
            ),
            $this->candidateRow(
                'passenger_records',
                '/v2.4.0/passenger/records?mode=update',
                'POST',
                'empty_probe',
                'high',
                false,
                true,
                'passenger_records_update_entitlement',
                'no_ticketing_payload',
            ),
            $this->candidateRow(
                'soap',
                'EnhancedAirTicket / AirTicketRQ (SOAP)',
                'POST',
                'n/a',
                'high',
                false,
                false,
                'soap_legacy_family',
                'defer_to_t3_soap_discovery',
                'not_probed',
            ),
            $this->candidateRow(
                'soap_or_rest_aux',
                'DesignatePrinter / queue setup (vendor-specific)',
                'POST',
                'n/a',
                'medium',
                false,
                false,
                'printer_queue_setup',
                'defer_to_t3_soap_discovery',
                'not_probed',
            ),
        ];

        return $rows;
    }

    /**
     * @return array<string, mixed>
     */
    protected function candidateRow(
        string $family,
        string $endpointPath,
        string $method,
        string $bodyStyle,
        string $riskLevel,
        bool $destructive,
        bool $shouldProbe,
        string $purpose,
        string $notes,
        string $accessResult = 'inspect_only',
    ): array {
        return [
            'family' => $family,
            'endpoint_path' => $endpointPath,
            'method' => $method,
            'body_style' => $bodyStyle,
            'live_call_attempted' => false,
            'http_status' => null,
            'available' => null,
            'access_result' => $accessResult,
            'risk_level' => $riskLevel,
            'destructive' => $destructive,
            'should_probe' => $shouldProbe,
            'error_codes_sanitized' => [],
            'error_messages_truncated' => [],
            'top_level_keys_sanitized' => [],
            'entitlement_hint' => $purpose,
            'notes' => $notes,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function customPathCandidate(string $path, string $method, bool $pnrPresent): array
    {
        $bodyStyle = $pnrPresent ? 'minimal_confirmation_id_probe' : 'empty_probe';

        return $this->candidateRow(
            'custom',
            $this->normalizePath($path),
            $method,
            $bodyStyle,
            'medium',
            false,
            true,
            'custom_path_override',
            'custom_path_entitlement_probe',
        );
    }

    /**
     * Documented only — never included in {@code candidates} or live probes (IATI audit: cancel ≠ issue).
     *
     * @return list<array<string, string>>
     */
    public static function excludedDestructiveEndpoints(): array
    {
        return [
            [
                'family' => 'destructive',
                'endpoint_path' => '/v1/trip/orders/cancelBooking',
                'status' => 'excluded_destructive',
                'notes' => 'IATI cancel/void path; destructive; not e-ticket issuance; never probed',
            ],
            [
                'family' => 'destructive',
                'endpoint_path' => 'void/refund/cancel ticket endpoints (vendor-specific)',
                'status' => 'excluded_destructive',
                'notes' => 'void/refund family; never probed by this command',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    protected function executeSafeProbe(
        SupplierConnection $connection,
        string $base,
        string $token,
        array $row,
        string $pnr,
        ?string $supplierApiBookingId,
        int $timeout,
        int $connectTimeout,
    ): array {
        $path = (string) ($row['endpoint_path'] ?? '');
        if ($this->isDestructivePath($path)) {
            return [
                'http_status' => null,
                'available' => false,
                'access_result' => 'excluded_destructive',
                'entitlement_hint' => 'destructive_endpoint_skipped',
                'notes' => 'destructive_path_never_sent',
            ];
        }

        $method = strtoupper((string) ($row['method'] ?? 'POST'));
        $bodyStyle = (string) ($row['body_style'] ?? 'empty_probe');
        $body = $this->buildProbeBody($bodyStyle, $pnr, $supplierApiBookingId);
        $this->assertProbeBodySafe($body);

        $url = $base.$this->normalizePath($path);
        $httpStatus = 0;
        $transport = null;
        $arr = [];

        try {
            $pending = Http::withToken($token)
                ->acceptJson()
                ->timeout($timeout)
                ->connectTimeout($connectTimeout);

            $encoded = (string) json_encode($body);
            $response = match ($method) {
                'GET' => $pending->get($url),
                'OPTIONS' => $pending->send('OPTIONS', $url, ['body' => $encoded, 'headers' => ['Content-Type' => 'application/json']]),
                default => $pending->withBody($encoded, 'application/json')->post($url),
            };
            $httpStatus = $response->status();
            $json = $response->json();
            $arr = is_array($json) ? $json : [];
        } catch (ConnectionException $e) {
            $transport = $this->transportLabelFromConnectionException($e);
        } catch (Throwable) {
            $transport = 'network';
        }

        $access = self::ticketingDiscoveryAccessResult($httpStatus, $transport);
        $digest = $arr !== [] ? $this->bookingClient->digestBookingResponseJsonForProbe($arr) : [];
        $codes = isset($digest['response_error_codes']) && is_array($digest['response_error_codes'])
            ? array_slice(array_map('strval', $digest['response_error_codes']), 0, 12)
            : [];
        $messages = isset($digest['response_error_messages']) && is_array($digest['response_error_messages'])
            ? array_slice(array_map('strval', $digest['response_error_messages']), 0, 8)
            : [];
        $safeCode = '';
        if (isset($codes[0]) && is_string($codes[0])) {
            $safeCode = substr($codes[0], 0, 120);
        }
        $safeMsg = '';
        if (isset($messages[0]) && is_string($messages[0])) {
            $safeMsg = substr($messages[0], 0, 200);
        }

        return [
            'http_status' => $httpStatus > 0 ? $httpStatus : null,
            'available' => in_array($httpStatus, [200, 201], true),
            'access_result' => $access,
            'error_codes_sanitized' => $codes,
            'error_messages_truncated' => $messages,
            'top_level_keys_sanitized' => $this->sanitizeTopLevelKeys($arr),
            'entitlement_hint' => self::entitlementHint($access, $httpStatus, $safeCode),
            'notes' => trim((string) ($row['notes'] ?? '').' live_entitlement_probe'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildProbeBody(string $bodyStyle, string $pnr, ?string $supplierApiBookingId): array
    {
        return match ($bodyStyle) {
            'confirmation_id_probe', 'minimal_confirmation_id_probe' => [
                'confirmationId' => $supplierApiBookingId !== null && $supplierApiBookingId !== ''
                    ? $supplierApiBookingId
                    : $pnr,
            ],
            'record_locator_probe' => ['recordLocator' => $pnr],
            'empty_probe' => [],
            default => [],
        };
    }

    /**
     * @param  array<string, mixed>  $body
     */
    protected function assertProbeBodySafe(array $body): void
    {
        foreach (array_keys($body) as $key) {
            if (! is_string($key)) {
                continue;
            }
            $lk = strtolower($key);
            foreach (self::FORBIDDEN_BODY_KEYS as $forbidden) {
                if (str_contains($lk, strtolower($forbidden))) {
                    throw new \InvalidArgumentException('unsafe_probe_body_key:'.$key);
                }
            }
        }
    }

    protected static function entitlementHint(string $accessResult, int $httpStatus, string $safeErrorCode): string
    {
        return match ($accessResult) {
            'forbidden' => 'http_403_entitlement_or_access_denied',
            'not_authorized' => 'http_401_not_authorized',
            'not_found' => 'http_404_path_not_recognized',
            'method_not_allowed' => 'http_405_wrong_verb_or_surface',
            'ready' => 'http_2xx_probe_ack_endpoint_live',
            'reachable_validation_error' => $safeErrorCode !== ''
                ? 'http_4xx_contract_reachable;code='.substr($safeErrorCode, 0, 40)
                : 'http_4xx_contract_reachable',
            'transport_error' => 'transport_error',
            'excluded_destructive' => 'destructive_endpoint_excluded',
            'not_probed' => 'not_probed_static_candidate',
            'inspect_only' => 'inspect_only_matrix',
            default => $httpStatus > 0 ? 'http_status_unclassified' : 'http_status_zero_or_transport',
        };
    }

    public static function ticketingDiscoveryAccessResult(int $httpStatus, ?string $transport): string
    {
        if ($transport !== null) {
            return 'transport_error';
        }
        if ($httpStatus === 401) {
            return 'not_authorized';
        }

        return SabreBookingService::discoveryAccessResultForProbe($httpStatus, $transport);
    }

    protected function isDestructivePath(string $path): bool
    {
        $lower = strtolower($path);
        foreach (self::DESTRUCTIVE_PATH_FRAGMENTS as $frag) {
            if (str_contains($lower, strtolower($frag))) {
                return true;
            }
        }

        return false;
    }

    protected function normalizePath(string $path): string
    {
        $path = trim($path);
        if ($path === '') {
            return '/';
        }

        return $path[0] === '/' ? $path : '/'.$path;
    }

    protected function transportLabelFromConnectionException(ConnectionException $e): string
    {
        $m = strtolower($e->getMessage());
        if (str_contains($m, 'timed out') || str_contains($m, 'timeout') || str_contains($m, 'curl error 28')) {
            return 'timeout';
        }

        return 'network';
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
            $lk = strtolower($k);
            if (str_contains($lk, 'password') || str_contains($lk, 'token') || str_contains($lk, 'traveler')) {
                continue;
            }
            $keys[] = substr($k, 0, 80);
        }
        sort($keys);

        return array_slice(array_values(array_unique($keys)), 0, 32);
    }

    protected function resolvePnrForProbe(Booking $booking): string
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        foreach ([
            $booking->pnr,
            $booking->supplier_reference,
            data_get($meta, 'sabre_provider_snapshot.pnr'),
        ] as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                return strtoupper(trim($candidate));
            }
        }

        return '';
    }

    protected function resolveSupplierApiBookingId(Booking $booking): ?string
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        foreach ([
            data_get($meta, 'supplier_api_booking_id'),
            data_get($meta, 'sabre_provider_snapshot.supplier_api_booking_id'),
            data_get($meta, 'sabre_checkout_outcome.supplier_api_booking_id'),
        ] as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                return trim($candidate);
            }
        }

        return null;
    }

    public static function resolveConnectionForBooking(Booking $booking): ?SupplierConnection
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
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
     * @param  list<array<string, mixed>>  $candidates
     */
    protected function recommendedNextAction(array $candidates, bool $send, bool $pnrPresent): string
    {
        if (! $send) {
            return $pnrPresent
                ? 'run_with_send_for_entitlement_probes'
                : 'sync_pnr_then_run_with_send';
        }

        $getBookingReady = false;
        $anyReachable = false;
        $allTicketingBlocked = true;

        foreach ($candidates as $row) {
            $path = (string) ($row['endpoint_path'] ?? '');
            $access = (string) ($row['access_result'] ?? '');
            if ($access === 'ready' || $access === 'reachable_validation_error') {
                $anyReachable = true;
            }
            if (str_contains($path, 'getBooking') && $access === 'ready') {
                $getBookingReady = true;
            }
            if (in_array((string) ($row['family'] ?? ''), ['rest_air_ticket', 'trip_orders', 'passenger_records'], true)
                && in_array($access, ['ready', 'reachable_validation_error', 'forbidden', 'not_authorized'], true)) {
                $allTicketingBlocked = false;
            }
        }

        if ($getBookingReady) {
            return 'review_fulfillment_and_air_ticket_probe_results_for_t3';
        }
        if ($anyReachable) {
            return 'document_entitled_paths_and_request_sabre_ticketing_api_contract';
        }
        if ($allTicketingBlocked) {
            return 'request_sabre_ticketing_entitlement_or_soap_api_docs';
        }

        return 'continue_t2b_discovery_matrix_review';
    }

    /**
     * @param  list<array<string, mixed>>  $candidates
     * @return array<string, mixed>
     */
    protected function rootPayload(
        SupplierConnection $connection,
        ?Booking $booking,
        bool $pnrPresent,
        bool $liveCallAttempted,
        int $maxCalls,
        array $candidates,
        string $recommendedNextAction,
    ): array {
        return [
            'connection_id' => $connection->id,
            'booking_id' => $booking?->id,
            'provider' => SupplierProvider::Sabre->value,
            'pnr_present' => $pnrPresent,
            'live_call_attempted' => $liveCallAttempted,
            'max_calls' => $maxCalls,
            'candidates' => $candidates,
            'excluded_destructive_endpoints' => self::excludedDestructiveEndpoints(),
            'recommended_next_action' => $recommendedNextAction,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>  $merge
     * @return array<string, mixed>
     */
    protected function mergeProbeRow(array $row, array $merge): array
    {
        return array_merge($row, $merge);
    }
}
