<?php

namespace App\Services\Suppliers\Sabre\Diagnostics;

use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Models\SupplierConnection;
use App\Services\Suppliers\Sabre\Core\SabreBookingClient;
use App\Services\Suppliers\Sabre\Core\SabreClient;
use App\Services\Suppliers\Sabre\SabreBookingPayloadBuilder;
use App\Services\Suppliers\Sabre\SabreBookingService;
use App\Services\Suppliers\Sabre\SabrePnrRetrieveProbe;
use App\Support\Bookings\SabreCertifiedRouteSelector;
use App\Support\Bookings\SabrePnrCertificationSupport;
use App\Support\Security\SensitiveDataRedactor;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Q1: PCC/credential capability matrix for Sabre REST surfaces (local/testing inspect tooling only).
 * Redacted rows only; optional {@code --send} safe probes with shared call budget. No ticketing issue / cancel / void.
 */
final class SabrePccCapabilityMatrix
{
    public const DEFAULT_MAX_CALLS = 25;

    /** @var list<string> */
    private const SHOP_PROBE_PATHS = [
        'shop_v4' => '/v4/offers/shop',
        'shop_v5' => '/v5/offers/shop',
    ];

    /** @var list<array{endpoint: string, style_label: string, style: string}> */
    private const PASSENGER_RECORDS_MATRIX = [
        ['endpoint' => '/v2.5.0/passenger/records?mode=create', 'style_label' => 'baseline', 'style' => SabreBookingPayloadBuilder::TRADITIONAL_PNR_CREATE_PASSENGER_NAME_RECORD_V1],
        ['endpoint' => '/v2.5.0/passenger/records?mode=create', 'style_label' => 'per_segment_fare_basis', 'style' => SabreBookingPayloadBuilder::TRADITIONAL_PNR_CREATE_PASSENGER_NAME_RECORD_V1_AIRPRICE_PER_SEGMENT_FARE_BASIS_COMPARE_V1],
        ['endpoint' => '/v2.5.0/passenger/records?mode=create', 'style_label' => 'retry_rebook_redisplay', 'style' => SabreBookingPayloadBuilder::TRADITIONAL_PNR_CREATE_PASSENGER_NAME_RECORD_V1_AIRBOOK_RETRY_REBOOK_REDISPLAY_COMPARE_V1],
        ['endpoint' => '/v2.5.0/passenger/records?mode=create', 'style_label' => 'validating_carrier_compare', 'style' => SabreBookingPayloadBuilder::TRADITIONAL_PNR_CREATE_PASSENGER_NAME_RECORD_V1_AIRPRICE_VALIDATING_CARRIER_COMPARE_V1],
        ['endpoint' => '/v2.4.0/passenger/records?mode=create', 'style_label' => 'baseline', 'style' => SabreBookingPayloadBuilder::TRADITIONAL_PNR_CREATE_PASSENGER_NAME_RECORD_V1],
        ['endpoint' => '/v2.3.0/passenger/records?mode=create', 'style_label' => 'baseline', 'style' => SabreBookingPayloadBuilder::TRADITIONAL_PNR_CREATE_PASSENGER_NAME_RECORD_V1],
        ['endpoint' => '/v2/passengers/create', 'style_label' => 'baseline', 'style' => SabreBookingPayloadBuilder::TRADITIONAL_PNR_CREATE_PASSENGER_NAME_RECORD_V1],
        ['endpoint' => '/v2/passenger/create', 'style_label' => 'baseline', 'style' => SabreBookingPayloadBuilder::TRADITIONAL_PNR_CREATE_PASSENGER_NAME_RECORD_V1],
    ];

    /** @var list<array{endpoint: string, style_label: string, style: string}> */
    private const TRIP_ORDERS_MATRIX = [
        ['endpoint' => '/v1/trip/orders/getBooking', 'style_label' => 'read_probe', 'style' => 'n/a'],
        ['endpoint' => '/v1/trip/orders/createBooking', 'style_label' => 'trip_orders_flight_details_sabre_v1', 'style' => 'trip_orders_flight_details_sabre_v1'],
        ['endpoint' => '/v1/trip/orders/createBooking', 'style_label' => 'trip_orders_flight_details_agency_v1', 'style' => 'trip_orders_flight_details_sabre_agency_v1'],
        ['endpoint' => '/v1/trip/orders/createBooking', 'style_label' => 'trip_orders_flight_offer_v1', 'style' => 'trip_orders_flight_offer_v1'],
    ];

    /** @var list<string> */
    private const PNR_READ_PATHS = [
        '/v2.5.0/passenger/records?mode=read',
        '/v2.4.0/passenger/records?mode=read',
        '/v1/reservations/retrieve',
        '/v1/trip/orders/getBooking',
    ];

    public function __construct(
        protected SabreClient $sabreClient,
        protected SabreBookingService $sabreBooking,
        protected SabreBookingClient $bookingClient,
        protected SabrePnrRetrieveProbe $pnrRetrieveProbe,
        protected SabreTicketingEndpointDiscovery $ticketingDiscovery,
        protected SabrePnrCertificationSupport $certificationSupport,
        protected SabreCertifiedRouteSelector $certifiedRouteSelector,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(
        SupplierConnection $connection,
        ?Booking $booking,
        bool $send,
        int $maxCalls,
    ): array {
        $maxCalls = max(1, $maxCalls);
        $budget = new SabrePccCapabilityCallBudget($maxCalls);
        $rows = [];

        $auth = $this->buildAuthSection($connection, $send, $budget);
        $rows = array_merge($rows, $auth['rows']);

        $discoveryByPath = $this->indexDiscoveryByPath(
            $send ? $this->probeDiscoveryPaths($connection, $budget) : [],
        );

        foreach ($this->buildFlightShoppingRows($connection, $booking, $send, $budget) as $row) {
            $rows[] = $row;
        }

        $compareInspect = $booking !== null
            ? $this->indexCompareRows($this->sabreBooking->compareBookingEndpointsForCommand($booking, false, false, null, null))
            : [];

        foreach ($this->buildPassengerRecordsRows($connection, $booking, $send, $budget, $discoveryByPath, $compareInspect) as $row) {
            $rows[] = $row;
        }

        foreach ($this->buildPnrReadRows($booking, $send, $budget) as $row) {
            $rows[] = $row;
        }

        foreach ($this->buildTripOrdersRows($connection, $booking, $send, $budget, $discoveryByPath, $compareInspect) as $row) {
            $rows[] = $row;
        }

        $ticketingRemaining = $budget->remaining();
        $ticketingPayload = $this->ticketingDiscovery->discover(
            $connection,
            $booking,
            $send && $ticketingRemaining > 0,
            $ticketingRemaining,
        );
        foreach ($this->ticketingRowsFromDiscovery($ticketingPayload) as $row) {
            $rows[] = $row;
        }
        if ($send) {
            $liveTicketing = 0;
            foreach ((array) ($ticketingPayload['candidates'] ?? []) as $candidate) {
                if (is_array($candidate) && ($candidate['live_call_attempted'] ?? false) === true) {
                    $liveTicketing++;
                }
            }
            $budget->consume($liveTicketing);
        }

        foreach ($this->buildDestructiveExcludedRows() as $row) {
            $rows[] = $row;
        }

        $summary = $this->buildSummary($connection, $booking, $rows, $auth);

        $payload = SensitiveDataRedactor::redact([
            'matrix_version' => 'q1_v1',
            'connection_id' => $connection->id,
            'booking_id' => $booking?->id,
            'inspect_only' => ! $send,
            'live_call_attempted' => $send,
            'max_calls' => $maxCalls,
            'calls_made' => $budget->used(),
            'sections' => [
                'auth_environment',
                'flight_shopping',
                'passenger_records_cpnr',
                'pnr_retrieve_read',
                'trip_orders',
                'ticketing_discovery',
                'destructive_excluded',
            ],
            'rows' => $rows,
            'ticketing_discovery_summary' => [
                'recommended_next_action' => $ticketingPayload['recommended_next_action'] ?? null,
                'pnr_present' => ($ticketingPayload['pnr_present'] ?? false) === true,
            ],
            'excluded_destructive_endpoints' => SabreTicketingEndpointDiscovery::excludedDestructiveEndpoints(),
            'summary' => $summary,
        ]);

        $this->certificationSupport->assertOutputSafe($payload);

        return $payload;
    }

    /**
     * @return array{rows: list<array<string, mixed>>, token_success: bool}
     */
    protected function buildAuthSection(SupplierConnection $connection, bool $send, SabrePccCapabilityCallBudget $budget): array
    {
        $base = rtrim((string) ($connection->base_url ?: config('suppliers.sabre.default_base_url')), '/');
        $host = parse_url(str_contains($base, '://') ? $base : 'https://'.$base, PHP_URL_HOST);
        $presence = $this->credentialPresenceBooleans($connection);
        $tokenSuccess = false;
        $tokenError = null;

        try {
            $this->sabreClient->getAccessToken($connection);
            $tokenSuccess = true;
        } catch (Throwable $e) {
            $tokenError = substr($e->getMessage(), 0, 120);
        }

        $row = [
            'section' => 'auth_environment',
            'endpoint_path' => 'oauth/token',
            'method' => 'POST',
            'live_call_attempted' => true,
            'http_status' => $tokenSuccess ? 200 : 0,
            'access_result' => $tokenSuccess ? 'ready' : 'transport_error',
            'base_host' => is_string($host) && $host !== '' ? $host : 'unknown',
            'environment' => $this->inferEnvironmentLabel($base),
            'token_success' => $tokenSuccess,
            'pcc_present' => $presence['pcc_present'],
            'epr_present' => $presence['epr_present'],
            'token_error_truncated' => $tokenError,
        ];

        return ['rows' => [$row], 'token_success' => $tokenSuccess];
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function buildFlightShoppingRows(
        SupplierConnection $connection,
        ?Booking $booking,
        bool $send,
        SabrePccCapabilityCallBudget $budget,
    ): array {
        $shopPath = (string) config('suppliers.sabre.shop_path', '/v4/offers/shop');
        if ($shopPath !== '' && ! str_starts_with($shopPath, '/')) {
            $shopPath = '/'.$shopPath;
        }
        $revalidatePath = (string) config('suppliers.sabre.revalidate_path', '/v4/shop/flights/revalidate');
        if ($revalidatePath !== '' && ! str_starts_with($revalidatePath, '/')) {
            $revalidatePath = '/'.$revalidatePath;
        }

        $paths = [
            'configured_shop' => $shopPath,
            ...self::SHOP_PROBE_PATHS,
            'configured_revalidate' => $revalidatePath,
        ];
        $paths = array_values(array_unique($paths));

        $refs = $this->offerReferenceHintsFromBooking($booking);
        $rows = [];
        foreach ($paths as $label => $path) {
            $row = [
                'section' => 'flight_shopping',
                'probe_label' => is_string($label) ? $label : 'shop',
                'endpoint_path' => $path,
                'method' => 'POST',
                'live_call_attempted' => false,
                'http_status' => 'not_sent',
                'access_result' => 'inspect_only',
                'entitlement_result' => null,
                'validation_result' => null,
                'safe_error_code' => '',
                'offer_refs_returned' => $refs,
            ];
            if ($send && $budget->canConsume()) {
                $probe = $this->postEmptyProbe($connection, $path);
                $budget->consume(1);
                $row = array_merge($row, $this->mapEmptyProbeToRow($probe));
                $row['live_call_attempted'] = true;
            }
            $rows[] = SensitiveDataRedactor::redact($row);
        }

        return $rows;
    }

    /**
     * @param  array<string, array<string, mixed>>  $discoveryByPath
     * @param  array<string, array<string, mixed>>  $compareInspect
     * @return list<array<string, mixed>>
     */
    protected function buildPassengerRecordsRows(
        SupplierConnection $connection,
        ?Booking $booking,
        bool $send,
        SabrePccCapabilityCallBudget $budget,
        array $discoveryByPath,
        array $compareInspect,
    ): array {
        $rows = [];
        $certPairSent = false;

        foreach (self::PASSENGER_RECORDS_MATRIX as $def) {
            $endpoint = $def['endpoint'];
            $style = $def['style'];
            $key = $endpoint.'|'.$style;
            $certSafe = $this->isCertificationSafeCreate($booking, $endpoint, $style);
            $compare = $compareInspect[$key] ?? null;
            $discovery = $discoveryByPath[$this->discoveryKey($endpoint)] ?? null;

            $row = [
                'section' => 'passenger_records_cpnr',
                'endpoint_path' => $endpoint,
                'payload_style_label' => $def['style_label'],
                'payload_style' => $style,
                'method' => 'POST',
                'certification_safe' => $certSafe,
                'live_call_attempted' => false,
                'http_status' => 'not_sent',
                'access_result' => 'inspect_only',
                'wire_inspect_valid' => $this->wireInspectValidFromCompare($compare),
                'schema_rejected' => null,
                'host_error' => null,
                'pnr_created' => false,
                'classification_hint' => null,
            ];

            if ($discovery !== null) {
                $row = array_merge($row, $this->mergeDiscoveryFields($discovery));
            }

            if ($compare !== null) {
                $row['wire_inspect_valid'] = $this->wireInspectValidFromCompare($compare);
                $row['wire_top_level_keys'] = $compare['wire_top_level_keys'] ?? [];
            }

            if ($send && $budget->canConsume() && $discovery === null) {
                $probe = $this->postEmptyProbe($connection, $endpoint);
                $budget->consume(1);
                $row = array_merge($row, $this->mapEmptyProbeToRow($probe));
                $row['live_call_attempted'] = true;
            } elseif ($send && $discovery !== null) {
                $row = array_merge($row, $this->mergeDiscoveryFields($discovery));
                $row['live_call_attempted'] = true;
            }

            if ($send && $certSafe && ! $certPairSent && $budget->canConsume() && $booking !== null) {
                $liveRows = $this->sabreBooking->compareBookingEndpointsForCommand(
                    $booking,
                    true,
                    false,
                    $endpoint,
                    $style,
                );
                $certPairSent = true;
                $budget->consume(1);
                if (isset($liveRows[0]) && is_array($liveRows[0])) {
                    $row = array_merge($row, $this->mergeCompareLiveRow($liveRows[0]));
                    $row['live_call_attempted'] = true;
                }
            }

            $row = $this->finalizeRowAccessResult($row, $endpoint);

            $rows[] = SensitiveDataRedactor::redact($row);
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function buildPnrReadRows(?Booking $booking, bool $send, SabrePccCapabilityCallBudget $budget): array
    {
        $rows = [];
        $pnr = $booking !== null ? $this->resolvePnrPresent($booking) : false;

        if ($booking !== null && $send && $pnr && $budget->canConsume()) {
            $probe = $this->pnrRetrieveProbe->probe($booking, true, null, 'default', false, false, false);
            $budget->consume(count((array) ($probe['attempted'] ?? [])));
            foreach ((array) ($probe['attempted'] ?? []) as $attempt) {
                if (! is_array($attempt)) {
                    continue;
                }
                $rows[] = SensitiveDataRedactor::redact($this->mapPnrReadRow($attempt, true));
            }

            return $rows;
        }

        foreach (self::PNR_READ_PATHS as $path) {
            $rows[] = SensitiveDataRedactor::redact([
                'section' => 'pnr_retrieve_read',
                'endpoint_path' => $path,
                'method' => 'POST',
                'live_call_attempted' => false,
                'http_status' => 'not_sent',
                'access_result' => 'inspect_only',
                'requires_pnr' => true,
                'pnr_present' => $pnr,
                'safe_to_map' => null,
                'can_retrieve_itinerary' => null,
                'can_retrieve_ttl' => null,
            ]);
        }

        return $rows;
    }

    /**
     * @param  array<string, array<string, mixed>>  $discoveryByPath
     * @param  array<string, array<string, mixed>>  $compareInspect
     * @return list<array<string, mixed>>
     */
    protected function buildTripOrdersRows(
        SupplierConnection $connection,
        ?Booking $booking,
        bool $send,
        SabrePccCapabilityCallBudget $budget,
        array $discoveryByPath,
        array $compareInspect,
    ): array {
        $rows = [];
        $agencyPhoneConfig = trim((string) config('suppliers.sabre.agency_phone', '')) !== '';

        foreach (self::TRIP_ORDERS_MATRIX as $def) {
            $endpoint = $def['endpoint'];
            $style = $def['style'];
            $isRead = $endpoint === '/v1/trip/orders/getBooking';
            $key = $isRead ? '' : $endpoint.'|'.$style;
            $compare = $key !== '' ? ($compareInspect[$key] ?? null) : null;
            $discovery = $discoveryByPath[$this->discoveryKey($endpoint)] ?? null;

            $row = [
                'section' => 'trip_orders',
                'endpoint_path' => $endpoint,
                'payload_style_label' => $def['style_label'],
                'payload_style' => $style,
                'method' => 'POST',
                'certification_safe' => false,
                'live_call_attempted' => false,
                'http_status' => 'not_sent',
                'access_result' => 'inspect_only',
                'schema_accepted' => null,
                'agency_phone_config_present' => $agencyPhoneConfig,
                'agency_phone_issue_likely_profile' => null,
                'pnr_created' => false,
            ];

            if ($compare !== null) {
                $row['wire_inspect_valid'] = ($compare['wire_contract_valid'] ?? $compare['wire_agency_phone_ok'] ?? null);
                $row['wire_has_agency_phone'] = $compare['wire_has_agency_phone'] ?? null;
            }

            if ($isRead) {
                $row['requires_pnr'] = true;
                $row['pnr_present'] = $booking !== null && $this->resolvePnrPresent($booking);
            }

            if ($send && $budget->canConsume()) {
                if ($isRead && $booking !== null && $this->resolvePnrPresent($booking)) {
                    $probe = $this->pnrRetrieveProbe->probe($booking, true, $endpoint, 'trip_orders_get_booking', false, false, false);
                    $budget->consume(1);
                    $attempt = is_array($probe['best_candidate'] ?? null) ? $probe['best_candidate'] : [];
                    $row = array_merge($row, $this->mapPnrReadRow($attempt, true));
                    $row['live_call_attempted'] = true;
                } elseif (! $isRead) {
                    if ($discovery === null) {
                        $probe = $this->postEmptyProbe($connection, $endpoint);
                        $budget->consume(1);
                        $row = array_merge($row, $this->mapEmptyProbeToRow($probe));
                    } else {
                        $row = array_merge($row, $this->mergeDiscoveryFields($discovery));
                    }
                    $row['live_call_attempted'] = true;
                }
            } elseif ($discovery !== null) {
                $row = array_merge($row, $this->mergeDiscoveryFields($discovery));
            }

            $row = $this->finalizeRowAccessResult($row, $endpoint);
            if (str_contains($this->errorMessageBlob($row), 'AGENCY_PHONE_MISSING')) {
                $row['agency_phone_issue_likely_profile'] = true;
            }

            $rows[] = SensitiveDataRedactor::redact($row);
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    protected function finalizeRowAccessResult(array $row, string $endpoint): array
    {
        if (($row['live_call_attempted'] ?? false) !== true && ($row['http_status'] ?? 'not_sent') === 'not_sent') {
            $row['access_result'] = 'inspect_only';

            return $row;
        }

        $row['access_result'] = self::classifyMatrixAccessResult(
            $this->httpStatusInt($row['http_status'] ?? 0),
            null,
            $endpoint,
            (string) ($row['safe_error_code'] ?? ''),
            $this->errorMessageBlob($row),
        );

        return $this->applySemanticHints($row);
    }

    /**
     * @param  array<string, mixed>  $ticketingPayload
     * @return list<array<string, mixed>>
     */
    protected function ticketingRowsFromDiscovery(array $ticketingPayload): array
    {
        $rows = [];
        foreach ((array) ($ticketingPayload['candidates'] ?? []) as $candidate) {
            if (! is_array($candidate)) {
                continue;
            }
            $rows[] = [
                'section' => 'ticketing_discovery',
                'family' => $candidate['family'] ?? '',
                'endpoint_path' => $candidate['endpoint_path'] ?? '',
                'method' => $candidate['method'] ?? 'POST',
                'body_style' => $candidate['body_style'] ?? '',
                'live_call_attempted' => ($candidate['live_call_attempted'] ?? false) === true,
                'http_status' => $candidate['http_status'] ?? null,
                'access_result' => $candidate['access_result'] ?? 'inspect_only',
                'destructive' => ($candidate['destructive'] ?? false) === true,
                'entitlement_hint' => $candidate['entitlement_hint'] ?? '',
                'notes' => 'ticketing_issue_excluded',
            ];
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function buildDestructiveExcludedRows(): array
    {
        $paths = [
            '/v1/trip/orders/cancelBooking',
            'void',
            'refund',
            'ticket/issue',
            'ticket/exchange',
        ];
        $rows = [];
        foreach (SabreTicketingEndpointDiscovery::excludedDestructiveEndpoints() as $ex) {
            $rows[] = [
                'section' => 'destructive_excluded',
                'endpoint_path' => $ex['endpoint_path'] ?? '',
                'method' => 'n/a',
                'live_call_attempted' => false,
                'access_result' => 'destructive_excluded',
                'status' => $ex['status'] ?? 'excluded_destructive',
                'notes' => $ex['notes'] ?? '',
            ];
        }
        foreach ($paths as $path) {
            $rows[] = [
                'section' => 'destructive_excluded',
                'endpoint_path' => $path,
                'method' => 'n/a',
                'live_call_attempted' => false,
                'access_result' => 'destructive_excluded',
                'status' => 'excluded_destructive',
            ];
        }

        return $rows;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  array{rows: list<array<string, mixed>>, token_success: bool}  $auth
     * @return array<string, mixed>
     */
    protected function buildSummary(SupplierConnection $connection, ?Booking $booking, array $rows, array $auth): array
    {
        $certified = [];
        $blocked = [];
        foreach ($rows as $row) {
            $ar = (string) ($row['access_result'] ?? '');
            $section = (string) ($row['section'] ?? '');
            if (in_array($ar, ['ready', 'reachable_validation_error'], true) || ($row['pnr_created'] ?? false) === true) {
                $certified[] = $section.':'.$ar;
            }
            if (in_array($ar, ['not_authorized', 'forbidden', 'profile_configuration_error', 'host_application_error', 'entitlement_missing'], true)) {
                $blocked[] = $section.':'.$ar;
            }
        }
        $certified = array_values(array_unique($certified));
        $blocked = array_values(array_unique($blocked));

        $configuredPath = (string) config('suppliers.sabre.booking_path', '/v2.5.0/passenger/records?mode=create');
        $recommended = $configuredPath !== '' ? $configuredPath : '/v2.5.0/passenger/records?mode=create';

        if ($booking !== null) {
            $cap = $this->sabreBooking->bookingCapabilityReportForCommand($booking);
            if (is_string($cap['recommended_next_action'] ?? null) && ($cap['recommended_next_action'] ?? '') !== '') {
                $recommended = (string) $cap['recommended_next_action'];
            }
        }

        $nextActions = [
            'Confirm Sabre PCC/TJR office profile agency phone when Trip Orders returns AGENCY_PHONE_MISSING.',
            'Mixed/interline: Passenger Records may return NO FARES/RBD/CARRIER — use Trip Orders after profile fix or manual Sabre pricing.',
            '/v2/passengers/create often returns not_authorized; prefer Passenger Records v2.5 mode=create when entitled.',
            'Ticketing remains discovery-only; do not enable issue-ticket until entitlement certified.',
        ];

        if (! ($auth['token_success'] ?? false)) {
            array_unshift($nextActions, 'Fix OAuth credentials / connection before endpoint probes.');
        }

        return [
            'recommended_current_booking_path' => $recommended,
            'certified_categories' => $certified,
            'blocked_categories' => $blocked,
            'next_actions' => $nextActions,
            'expected_evidence' => [
                'one_way_direct_same_carrier' => 'Passenger Records v2.5 mode=create (e.g. booking 20 / FCMYSY)',
                'mixed_interline' => 'Passenger Records NO FARES; Trip Orders AGENCY_PHONE_MISSING',
                'v2_passengers_create' => 'not_authorized',
                'ticketing' => 'not_certified_disabled',
            ],
        ];
    }

    protected static function entitlementHintForProbe(string $accessResult, int $httpStatus, string $safeErrorCode): string
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
            'profile_configuration_error' => 'agency_or_pcc_profile_configuration',
            'host_application_error' => 'host_application_error',
            'transport_error' => 'transport_error',
            default => $httpStatus > 0 ? 'http_status_unclassified' : 'http_status_zero_or_transport',
        };
    }

    public static function classifyMatrixAccessResult(
        int $httpStatus,
        ?string $transport,
        string $endpointPath,
        string $safeErrorCode,
        string $errorBlob,
    ): string {
        if ($transport === 'timeout' || $transport === 'network') {
            return 'transport_error';
        }
        $blob = strtoupper($safeErrorCode.' '.$errorBlob);
        if (str_contains($blob, 'AGENCY_PHONE_MISSING')) {
            return 'profile_configuration_error';
        }
        if (str_contains($blob, 'NOT_AUTHORIZED') || str_contains($blob, 'NOT AUTHORIZED')) {
            return 'not_authorized';
        }
        $ep = strtolower($endpointPath);
        if (($httpStatus === 403 || $httpStatus === 401)
            && (str_contains($ep, '/v2/passengers/create') || str_contains($ep, '/v2/passenger/create'))) {
            return 'not_authorized';
        }
        if (str_contains($blob, 'NO FARES') || str_contains($blob, 'NO FARE')) {
            return 'host_application_error';
        }
        if (str_contains($blob, 'MANDATORY_DATA_MISSING')) {
            return 'reachable_validation_error';
        }
        if ($httpStatus === 0 && trim($errorBlob) === '') {
            return 'inspect_only';
        }

        $base = SabreBookingService::discoveryAccessResultForProbe($httpStatus, $transport);
        if ($base === 'forbidden' && str_contains($blob, 'ENTITLEMENT')) {
            return 'entitlement_missing';
        }

        return $base;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    protected function applySemanticHints(array $row): array
    {
        $blob = $this->errorMessageBlob($row);
        if (str_contains(strtoupper($blob), 'NO FARES') || str_contains(strtoupper($blob), 'NO FARE')) {
            $row['classification_hint'] = 'pnr_requires_manual_sabre_pricing';
        }

        return $row;
    }

    protected function isCertificationSafeCreate(?Booking $booking, string $endpoint, string $style): bool
    {
        if ($booking === null) {
            return false;
        }
        if (! $this->sabreBooking->mayPerformLiveSabreBookingCall()) {
            return false;
        }
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $provider = strtolower(trim((string) ($meta['supplier_provider'] ?? $booking->supplier ?? '')));
        if ($provider !== SupplierProvider::Sabre->value) {
            return false;
        }

        $selection = $this->certifiedRouteSelector->selectForBooking($booking);
        if (($selection['live_booking_allowed'] ?? false) !== true) {
            return false;
        }

        return $endpoint === (string) ($selection['endpoint_path'] ?? '')
            && $style === (string) ($selection['payload_style'] ?? '');
    }

    /**
     * @return array{http_status: int, safe_error_code: string, safe_error_message_truncated: string, access_result: string}
     */
    protected function postEmptyProbe(SupplierConnection $connection, string $path): array
    {
        try {
            $token = $this->sabreClient->getAccessToken($connection);
        } catch (Throwable) {
            return [
                'http_status' => 0,
                'safe_error_code' => '',
                'safe_error_message_truncated' => 'oauth_failed',
                'access_result' => 'transport_error',
            ];
        }

        $base = rtrim((string) ($connection->base_url ?: config('suppliers.sabre.default_base_url')), '/');
        $timeouts = $this->sabreClient->httpTimeoutSettings();
        $url = $base.($path[0] === '/' ? $path : '/'.$path);
        $httpStatus = 0;
        $transport = null;
        $arr = [];
        try {
            $response = Http::withToken($token)
                ->acceptJson()
                ->timeout($timeouts['timeout_seconds'])
                ->connectTimeout($timeouts['connect_timeout_seconds'])
                ->withBody('{}', 'application/json')
                ->post($url);
            $httpStatus = $response->status();
            $json = $response->json();
            $arr = is_array($json) ? $json : [];
        } catch (ConnectionException $e) {
            $transport = str_contains(strtolower($e->getMessage()), 'timeout') ? 'timeout' : 'network';
        } catch (Throwable) {
            $transport = 'network';
        }

        $digest = $arr !== [] ? $this->bookingClient->digestBookingResponseJsonForProbe($arr) : [];
        $safeCode = $this->firstDigestCode($digest);
        $safeMsg = $this->firstDigestMessage($digest);
        $access = self::classifyMatrixAccessResult($httpStatus, $transport, $path, $safeCode, $safeMsg);

        return [
            'http_status' => $httpStatus,
            'safe_error_code' => $safeCode,
            'safe_error_message_truncated' => $safeMsg,
            'access_result' => $access,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function probeDiscoveryPaths(SupplierConnection $connection, SabrePccCapabilityCallBudget $budget): array
    {
        $wanted = [];
        foreach (self::PASSENGER_RECORDS_MATRIX as $def) {
            $wanted[$this->discoveryKey($def['endpoint'])] = $def['endpoint'];
        }
        foreach (self::TRIP_ORDERS_MATRIX as $def) {
            if ($def['endpoint'] !== '/v1/trip/orders/getBooking') {
                $wanted[$this->discoveryKey($def['endpoint'])] = $def['endpoint'];
            }
        }
        $wanted[$this->discoveryKey('/v2/passengers/create')] = '/v2/passengers/create';
        $wanted[$this->discoveryKey('/v2/passenger/create')] = '/v2/passenger/create';

        $out = [];
        foreach (array_values(array_unique($wanted)) as $path) {
            if (! $budget->canConsume()) {
                break;
            }
            $probe = $this->postEmptyProbe($connection, $path);
            $budget->consume(1);
            $flags = SabreBookingService::discoveryEndpointFlags($path);
            $out[] = [
                'endpoint_path' => $path,
                'method' => 'POST',
                'http_status' => $probe['http_status'],
                'access_result' => $probe['access_result'],
                'likely_create_endpoint' => $flags['likely_create_endpoint'],
                'non_create_endpoint' => $flags['non_create_endpoint'],
                'entitlement_hint' => self::entitlementHintForProbe(
                    (string) $probe['access_result'],
                    (int) $probe['http_status'],
                    $probe['safe_error_code'],
                ),
                'safe_error_code' => $probe['safe_error_code'],
                'safe_error_message_truncated' => $probe['safe_error_message_truncated'],
            ];
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $discoveryRows
     * @return array<string, array<string, mixed>>
     */
    protected function indexDiscoveryByPath(array $discoveryRows): array
    {
        $index = [];
        foreach ($discoveryRows as $row) {
            $path = (string) ($row['endpoint_path'] ?? '');
            if ($path === '') {
                continue;
            }
            $index[$this->discoveryKey($path)] = $row;
        }

        return $index;
    }

    /**
     * @param  list<array<string, mixed>>  $compareRows
     * @return array<string, array<string, mixed>>
     */
    protected function indexCompareRows(array $compareRows): array
    {
        $index = [];
        foreach ($compareRows as $row) {
            $ep = (string) ($row['endpoint_path'] ?? '');
            $st = (string) ($row['payload_style'] ?? '');
            if ($ep === '') {
                continue;
            }
            $index[$ep.'|'.$st] = $row;
        }

        return $index;
    }

    /**
     * @param  array<string, mixed>  $probe
     * @return array<string, mixed>
     */
    protected function mapEmptyProbeToRow(array $probe): array
    {
        return [
            'http_status' => $probe['http_status'] ?? 0,
            'safe_error_code' => $probe['safe_error_code'] ?? '',
            'safe_error_message_truncated' => $probe['safe_error_message_truncated'] ?? '',
            'access_result' => $probe['access_result'] ?? 'unknown',
            'entitlement_result' => $probe['access_result'] ?? null,
            'validation_result' => in_array($probe['access_result'] ?? '', ['reachable_validation_error'], true)
                ? 'validation_error'
                : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $discovery
     * @return array<string, mixed>
     */
    protected function mergeDiscoveryFields(array $discovery): array
    {
        return [
            'http_status' => $discovery['http_status'] ?? 0,
            'safe_error_code' => $discovery['safe_error_code'] ?? '',
            'safe_error_message_truncated' => $discovery['safe_error_message_truncated'] ?? '',
            'access_result' => $discovery['access_result'] ?? 'unknown',
            'entitlement_result' => $discovery['entitlement_hint'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $live
     * @return array<string, mixed>
     */
    protected function mergeCompareLiveRow(array $live): array
    {
        $codes = array_map('strval', (array) ($live['response_error_codes'] ?? []));
        $msgs = array_map('strval', (array) ($live['response_error_messages'] ?? []));

        return [
            'http_status' => $live['http_status'] ?? 0,
            'safe_error_code' => $codes[0] ?? (string) ($live['response_top_level_error_code'] ?? ''),
            'safe_error_message_truncated' => substr($msgs[0] ?? (string) ($live['response_top_level_message'] ?? ''), 0, 200),
            'response_error_codes' => array_slice($codes, 0, 12),
            'response_error_messages' => array_slice($msgs, 0, 8),
            'pnr_created' => ($live['pnr_created'] ?? false) === true,
            'pnr_present' => ($live['pnr_present'] ?? false) === true,
            'classification_hint' => $live['classification'] ?? null,
            'status' => $live['status'] ?? 'live_attempted',
        ];
    }

    /**
     * @param  array<string, mixed>  $attempt
     * @return array<string, mixed>
     */
    protected function mapPnrReadRow(array $attempt, bool $live): array
    {
        $codes = array_map('strval', (array) ($attempt['error_codes_sanitized'] ?? []));
        $msgs = array_map('strval', (array) ($attempt['error_messages_truncated'] ?? []));

        return [
            'section' => 'pnr_retrieve_read',
            'endpoint_path' => $attempt['endpoint_path'] ?? '',
            'method' => 'POST',
            'live_call_attempted' => $live,
            'http_status' => $attempt['http_status'] ?? 0,
            'access_result' => $attempt['access_result'] ?? 'inspect_only',
            'requires_pnr' => true,
            'pnr_present' => true,
            'safe_to_map' => ($attempt['has_itinerary_ref'] ?? false) === true
                || ($attempt['segment_count_inferred'] ?? 0) > 0,
            'can_retrieve_itinerary' => ($attempt['segment_count_inferred'] ?? 0) > 0,
            'can_retrieve_ttl' => ($attempt['has_travel_itinerary'] ?? false) === true,
            'safe_error_code' => $codes[0] ?? '',
            'safe_error_message_truncated' => substr($msgs[0] ?? '', 0, 200),
        ];
    }

    /**
     * @param  array<string, mixed>|null  $compare
     */
    protected function wireInspectValidFromCompare(?array $compare): ?bool
    {
        if ($compare === null) {
            return null;
        }
        if (isset($compare['wire_traditional_pnr_contract_valid'])) {
            return ($compare['wire_traditional_pnr_contract_valid'] ?? false) === true;
        }
        if (isset($compare['wire_contract_valid'])) {
            return ($compare['wire_contract_valid'] ?? false) === true;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    protected function errorMessageBlob(array $row): string
    {
        $parts = [
            (string) ($row['safe_error_message_truncated'] ?? ''),
            (string) ($row['safe_error_code'] ?? ''),
        ];
        foreach ((array) ($row['response_error_messages'] ?? []) as $m) {
            $parts[] = (string) $m;
        }
        foreach ((array) ($row['response_error_codes'] ?? []) as $c) {
            $parts[] = (string) $c;
        }

        $parts = array_values(array_filter(
            $parts,
            static fn (string $p): bool => trim($p) !== '',
        ));

        return $parts === [] ? '' : strtoupper(implode(' ', $parts));
    }

    /**
     * @param  array<string, mixed>  $digest
     */
    protected function firstDigestCode(array $digest): string
    {
        $top = isset($digest['response_top_level_error_code']) && is_string($digest['response_top_level_error_code'])
            ? $digest['response_top_level_error_code'] : '';
        if ($top !== '') {
            return substr($top, 0, 120);
        }
        $codes = (array) ($digest['response_error_codes'] ?? []);

        return isset($codes[0]) && is_string($codes[0]) ? substr($codes[0], 0, 120) : '';
    }

    /**
     * @param  array<string, mixed>  $digest
     */
    protected function firstDigestMessage(array $digest): string
    {
        $top = isset($digest['response_top_level_message']) && is_string($digest['response_top_level_message'])
            ? $digest['response_top_level_message'] : '';
        if ($top !== '') {
            return substr($top, 0, 200);
        }
        $messages = (array) ($digest['response_error_messages'] ?? []);

        return isset($messages[0]) && is_string($messages[0]) ? substr($messages[0], 0, 200) : '';
    }

    protected function httpStatusInt(mixed $status): int
    {
        if (is_int($status)) {
            return $status;
        }
        if (is_string($status) && is_numeric($status)) {
            return (int) $status;
        }

        return 0;
    }

    protected function discoveryKey(string $path): string
    {
        $path = strtolower(trim($path));
        if (str_contains($path, '?')) {
            $path = explode('?', $path, 2)[0];
        }

        return $path;
    }

    /**
     * @return array{pcc_present: bool, epr_present: bool}
     */
    protected function credentialPresenceBooleans(SupplierConnection $connection): array
    {
        $cred = is_array($connection->credentials) ? $connection->credentials : [];
        $settings = is_array($connection->settings) ? $connection->settings : [];

        return [
            'pcc_present' => $this->firstCredentialValue($cred, $settings, ['pcc', 'PCC', 'pseudo_city_code', 'pseudoCityCode']) !== '',
            'epr_present' => $this->firstCredentialValue($cred, $settings, ['epr', 'EPR', 'username', 'sign_in', 'signIn']) !== '',
        ];
    }

    /**
     * @param  array<string, mixed>  $cred
     * @param  array<string, mixed>  $settings
     * @param  list<string>  $keys
     */
    protected function firstCredentialValue(array $cred, array $settings, array $keys): string
    {
        foreach ($keys as $key) {
            foreach ([$cred, $settings] as $bag) {
                if (isset($bag[$key]) && is_scalar($bag[$key]) && trim((string) $bag[$key]) !== '') {
                    return 'present';
                }
            }
        }

        return '';
    }

    protected function inferEnvironmentLabel(string $baseUrl): string
    {
        $lower = strtolower($baseUrl);
        if (str_contains($lower, 'cert') || str_contains($lower, 'test') || str_contains($lower, 'sws-crt')) {
            return 'cert';
        }

        return 'live';
    }

    /**
     * @return array{has_offer_ref: bool, has_pricing_ref: bool}
     */
    protected function offerReferenceHintsFromBooking(?Booking $booking): array
    {
        if ($booking === null) {
            return ['has_offer_ref' => false, 'has_pricing_ref' => false];
        }
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $snapshot = is_array($meta['normalized_offer_snapshot'] ?? null) ? $meta['normalized_offer_snapshot'] : [];
        $raw = is_array($snapshot['raw_payload'] ?? null) ? $snapshot['raw_payload'] : [];
        $ids = is_array($raw['sabre_shop_identifiers'] ?? null) ? $raw['sabre_shop_identifiers'] : [];

        return [
            'has_offer_ref' => isset($ids['offer_id']) || isset($snapshot['supplier_offer_id']),
            'has_pricing_ref' => isset($ids['pricing_source']) || isset($meta['sabre_revalidate_inspect']),
        ];
    }

    protected function resolvePnrPresent(Booking $booking): bool
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        foreach (['supplier_pnr', 'pnr', 'record_locator'] as $key) {
            if (trim((string) ($meta[$key] ?? '')) !== '') {
                return true;
            }
        }

        return trim((string) ($booking->pnr ?? '')) !== '';
    }

    public static function resolveConnection(?int $connectionId, ?Booking $booking): ?SupplierConnection
    {
        if ($connectionId !== null && $connectionId > 0) {
            $c = SupplierConnection::query()
                ->where('provider', SupplierProvider::Sabre)
                ->find($connectionId);
            if ($c !== null) {
                return $c;
            }
        }
        if ($booking !== null) {
            return SabreTicketingEndpointDiscovery::resolveConnectionForBooking($booking);
        }

        return SupplierConnection::query()
            ->where('provider', SupplierProvider::Sabre)
            ->orderBy('id')
            ->first();
    }
}

/**
 * Shared HTTP probe budget for {@see SabrePccCapabilityMatrix} (--max-calls).
 */
final class SabrePccCapabilityCallBudget
{
    private int $used = 0;

    public function __construct(private int $max) {}

    public function canConsume(): bool
    {
        return $this->used < $this->max;
    }

    public function consume(int $n = 1): void
    {
        $this->used += max(0, $n);
    }

    public function remaining(): int
    {
        return max(0, $this->max - $this->used);
    }

    public function used(): int
    {
        return $this->used;
    }
}
