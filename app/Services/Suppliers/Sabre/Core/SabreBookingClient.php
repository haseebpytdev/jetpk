<?php

namespace App\Services\Suppliers\Sabre\Core;

use App\Enums\SupplierProvider;
use App\Models\SupplierConnection;
use App\Services\Suppliers\Sabre\SabreBookingPayloadBuilder;
use App\Services\Suppliers\Sabre\SabreCancelProbeDiagnostics;
use App\Support\Sabre\SabrePassengerRecordsApplicationResultDigest;
use App\Support\Sabre\SabrePassengerRecordsHttpValidationExcerptBuilder;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sabre booking HTTP adapter (Trip Orders createBooking and legacy passenger-record paths). Uses {@see SabreClient} for OAuth only.
 * Parses **HTTP 200** Trip Orders bodies: PNR-only paths, order/booking IDs without PNR → **needs_review** + `provider_booking_id`, and safe `errors`/`error` digests (codes, messages, field hints, missingFields, request/trace ids; capped excerpts) for staff review.
 * **B24:** **HTTP 400/422** (and other non-success) responses merge the same capped digest fields into `booking_diagnostics` (incl. `response_top_level_keys`, `response_top_level_error_code`, `response_top_level_type`, `response_additional_messages`) — never raw bodies.
 * **B27:** digest adds `response_error_paths` (JSON pointers / field tokens from `errors[]`, nested `validationErrors`, `invalidField`, etc.; capped, unique).
 * **B28:** Trip Orders POST body uses {@see SabreBookingPayloadBuilder::tripOrdersFinalWirePostBodyFromEnvelope()} (null strip + safe defaults); HTTP 400/422 may append a null-free shape hint when Sabre says "must not be null".
 * **B44/B45:** {@see digestBookingResponseJsonForProbe} merges Passenger Records {@code ApplicationResults} / {@code CreatePassengerNameRecordRS} capped codes, messages, field/path hints, {@code application_results_status}, and {@code passenger_records_error_digest_present} with Trip Orders {@code errors[]} parsing (no raw bodies). **B45:** Passenger Records REST top-level {@code errorCode}/{@code message}/{@code status}/{@code type} + {@code timeStamp} presence ({@code response_timestamp_present}). **B57:** same digest adds {@code host_warning_modules}, redacted {@code host_warning_messages_truncated}, {@code host_warning_sabre_codes}, {@code application_results_incomplete}.
 * Strips {@code _ota*} envelope keys before POST. Never logs Authorization, raw request bodies, or full provider JSON.
 */
final class SabreBookingClient
{
    private const SAFE_EXCERPT_MAX = 300;

    public function __construct(
        protected SabreClient $sabreClient,
        protected SabreBookingPayloadBuilder $bookingPayloadBuilder,
    ) {}

    /**
     * POST booking envelope to {@see config('suppliers.sabre.booking_path')}.
     *
     * @param  array<string, mixed>  $apiEnvelope  From {@see SabreBookingPayloadBuilder} build*Envelope (internal {@code _ota*} keys stripped before HTTP)
     * @param  array<string, mixed>  $diagnosticsContext  Safe flags only: booking_id, supplier_connection_id, passenger_count, segment_count, has_contact_email, has_contact_phone, has_booking_class, has_fare_basis, has_end_transaction
     * @return array<string, mixed>
     */
    public function createPassengerRecordBooking(
        SupplierConnection $connection,
        array $apiEnvelope,
        array $diagnosticsContext = [],
        ?string $endpointPathOverride = null,
    ): array {
        $path = trim((string) $endpointPathOverride) !== ''
            ? trim((string) $endpointPathOverride)
            : (string) config('suppliers.sabre.booking_path', '/v1/trip/orders/createBooking');
        $parts = $this->sabreClient->resolveEndpointParts($connection, $path);
        $timeouts = $this->sabreClient->httpTimeoutSettings();
        $started = microtime(true);

        $baseLog = array_merge([
            'provider' => SupplierProvider::Sabre->value,
            'endpoint_host' => $parts['endpoint_host'],
            'endpoint_path' => $parts['endpoint_path'],
            'timeout_seconds' => $timeouts['timeout_seconds'],
            'connect_timeout_seconds' => $timeouts['connect_timeout_seconds'],
            'supplier_connection_id' => $connection->id,
            'booking_id' => isset($diagnosticsContext['booking_id']) && is_numeric($diagnosticsContext['booking_id'])
                ? (int) $diagnosticsContext['booking_id']
                : null,
            'passenger_count' => isset($diagnosticsContext['passenger_count']) && is_numeric($diagnosticsContext['passenger_count'])
                ? (int) $diagnosticsContext['passenger_count']
                : null,
            'segment_count' => isset($diagnosticsContext['segment_count']) && is_numeric($diagnosticsContext['segment_count'])
                ? (int) $diagnosticsContext['segment_count']
                : null,
            'has_contact_email' => (bool) ($diagnosticsContext['has_contact_email'] ?? false),
            'has_contact_phone' => (bool) ($diagnosticsContext['has_contact_phone'] ?? false),
            'has_booking_class' => (bool) ($diagnosticsContext['has_booking_class'] ?? false),
            'has_fare_basis' => (bool) ($diagnosticsContext['has_fare_basis'] ?? false),
            'has_fare_reference' => (bool) ($diagnosticsContext['has_fare_reference'] ?? false),
            'has_price_quote_reference' => (bool) ($diagnosticsContext['has_price_quote_reference'] ?? false),
            'has_offer_reference' => (bool) ($diagnosticsContext['has_offer_reference'] ?? false),
            'has_revalidation_reference' => (bool) ($diagnosticsContext['has_revalidation_reference'] ?? false),
            'has_itinerary_reference' => (bool) ($diagnosticsContext['has_itinerary_reference'] ?? false),
            'has_validating_carrier' => (bool) ($diagnosticsContext['has_validating_carrier'] ?? false),
            'has_revalidated_fare' => (bool) ($diagnosticsContext['has_revalidated_fare'] ?? false),
            'has_revalidated_currency' => (bool) ($diagnosticsContext['has_revalidated_currency'] ?? false),
            'has_end_transaction' => (bool) ($diagnosticsContext['has_end_transaction'] ?? false),
            'has_commit_or_end_transaction' => (bool) ($diagnosticsContext['has_commit_or_end_transaction'] ?? ($diagnosticsContext['has_end_transaction'] ?? false)),
            'has_flight_offer' => (bool) ($diagnosticsContext['has_flight_offer'] ?? false),
            'has_flight_details' => (bool) ($diagnosticsContext['has_flight_details'] ?? false),
            'has_required_booking_product_object' => (bool) ($diagnosticsContext['has_required_booking_product_object'] ?? false),
            'has_segments_inside_flight_offer' => (bool) ($diagnosticsContext['has_segments_inside_flight_offer'] ?? false),
            'has_segments_inside_flight_details' => (bool) ($diagnosticsContext['has_segments_inside_flight_details'] ?? false),
            'payload_style' => is_string($diagnosticsContext['payload_style'] ?? null) && trim((string) $diagnosticsContext['payload_style']) !== ''
                ? trim((string) $diagnosticsContext['payload_style'])
                : null,
            'allow_nn_cert_operational' => ($diagnosticsContext['allow_nn_cert_operational'] ?? false) === true,
            'halt_on_status_nn_omitted' => ($diagnosticsContext['halt_on_status_nn_omitted'] ?? false) === true,
        ], $parts, $timeouts);

        $wireEnvelope = $this->resolvePassengerRecordsWireEnvelopeForPost($apiEnvelope);

        $extraHeaders = [];
        if (stripos((string) $path, 'passenger/records') !== false) {
            $bid = $baseLog['booking_id'] ?? null;
            $extraHeaders['Conversation-ID'] = is_int($bid) && $bid > 0 ? 'ota-'.$bid.'-'.time() : 'ota-send-'.time();
        }

        try {
            $response = $this->sabreClient->postAuthenticatedJson($connection, $path, $wireEnvelope, $extraHeaders);
        } catch (ConnectionException $e) {
            $durationMs = (int) round((microtime(true) - $started) * 1000);
            $safeMsg = self::truncateExceptionMessage($e);
            Log::warning('sabre.booking.connection_error', array_merge($baseLog, [
                'duration_ms' => $durationMs,
                'exception_class' => $e::class,
                'exception_safe_message' => $safeMsg,
                'http_status' => null,
                'reason_code' => 'sabre_booking_connection_error',
            ]));

            return $this->normalizedFailure(
                httpStatus: null,
                safeMessage: 'Sabre booking request failed due to a network error.',
                liveCallAttempted: true,
                errorCode: 'sabre_booking_connection_error',
                reasonCode: 'sabre_booking_connection_error',
                diagnostics: array_merge($baseLog, [
                    'duration_ms' => $durationMs,
                    'exception_class' => $e::class,
                    'exception_safe_message' => $safeMsg,
                    'http_status' => null,
                    'live_call_attempted' => true,
                ]),
            );
        } catch (Throwable $e) {
            $durationMs = (int) round((microtime(true) - $started) * 1000);
            $safeMsg = self::truncateExceptionMessage($e);
            Log::warning('sabre.booking.unexpected_error', array_merge($baseLog, [
                'duration_ms' => $durationMs,
                'exception_class' => $e::class,
                'exception_safe_message' => $safeMsg,
                'http_status' => null,
                'reason_code' => 'sabre_booking_unexpected_error',
            ]));

            return $this->normalizedFailure(
                httpStatus: null,
                safeMessage: 'Sabre booking request could not be completed.',
                liveCallAttempted: true,
                errorCode: 'sabre_booking_unexpected_error',
                reasonCode: 'sabre_booking_unexpected_error',
                diagnostics: array_merge($baseLog, [
                    'duration_ms' => $durationMs,
                    'exception_class' => $e::class,
                    'exception_safe_message' => $safeMsg,
                    'http_status' => null,
                    'live_call_attempted' => true,
                ]),
            );
        }

        return $this->normalizeBookingResponse($response, (int) round((microtime(true) - $started) * 1000), $baseLog);
    }

    /**
     * Sprint 0: POST cancelBooking for inspect/cert probes only (no retries; no raw body in return).
     *
     * @param  array<string, mixed>  $wireBody  e.g. {@code confirmationId} or {@code recordLocator} only
     * @param  array<string, mixed>  $diagnosticsContext  Safe flags: booking_id, supplier_connection_id, payload_style, primary_identifier_source
     * @return array<string, mixed>
     */
    public function inspectCancelBooking(
        SupplierConnection $connection,
        array $wireBody,
        array $diagnosticsContext = [],
        ?string $endpointPathOverride = null,
    ): array {
        $path = trim((string) $endpointPathOverride) !== ''
            ? trim((string) $endpointPathOverride)
            : (string) config('suppliers.sabre.cancel_endpoint_path', '/v1/trip/orders/cancelBooking');
        $parts = $this->sabreClient->resolveEndpointParts($connection, $path);
        $timeouts = $this->sabreClient->httpTimeoutSettings();
        $started = microtime(true);

        $baseLog = array_merge([
            'provider' => SupplierProvider::Sabre->value,
            'operation' => 'inspect_cancel_booking',
            'endpoint_host' => $parts['endpoint_host'],
            'endpoint_path' => $parts['endpoint_path'],
            'timeout_seconds' => $timeouts['timeout_seconds'],
            'connect_timeout_seconds' => $timeouts['connect_timeout_seconds'],
            'supplier_connection_id' => $connection->id,
            'booking_id' => isset($diagnosticsContext['booking_id']) && is_numeric($diagnosticsContext['booking_id'])
                ? (int) $diagnosticsContext['booking_id']
                : null,
            'payload_style' => is_string($diagnosticsContext['payload_style'] ?? null) && trim((string) $diagnosticsContext['payload_style']) !== ''
                ? trim((string) $diagnosticsContext['payload_style'])
                : null,
            'primary_identifier_source' => is_string($diagnosticsContext['primary_identifier_source'] ?? null)
                ? trim((string) $diagnosticsContext['primary_identifier_source'])
                : null,
        ], $parts, $timeouts);

        $extraHeaders = [];
        $bid = $baseLog['booking_id'] ?? null;
        $extraHeaders['Conversation-ID'] = is_int($bid) && $bid > 0 ? 'ota-'.$bid.'-'.time() : 'ota-cancel-'.time();
        $baseLog['conversation_id_sent'] = true;

        try {
            $response = $this->sabreClient->postAuthenticatedJson($connection, $path, $wireBody, $extraHeaders);
        } catch (ConnectionException $e) {
            $durationMs = (int) round((microtime(true) - $started) * 1000);
            $safeMsg = self::truncateExceptionMessage($e);
            Log::warning('sabre.cancel_inspect.connection_error', array_merge($baseLog, [
                'duration_ms' => $durationMs,
                'exception_class' => $e::class,
                'exception_safe_message' => $safeMsg,
                'http_status' => null,
                'reason_code' => 'sabre_cancel_connection_error',
            ]));

            return $this->normalizedFailure(
                httpStatus: null,
                safeMessage: 'Sabre cancel request failed due to a network error.',
                liveCallAttempted: true,
                errorCode: 'sabre_cancel_connection_error',
                reasonCode: 'sabre_cancel_connection_error',
                diagnostics: array_merge($baseLog, [
                    'duration_ms' => $durationMs,
                    'exception_class' => $e::class,
                    'exception_safe_message' => $safeMsg,
                    'http_status' => null,
                    'live_call_attempted' => true,
                ]),
            );
        } catch (Throwable $e) {
            $durationMs = (int) round((microtime(true) - $started) * 1000);
            $safeMsg = self::truncateExceptionMessage($e);
            Log::warning('sabre.cancel_inspect.unexpected_error', array_merge($baseLog, [
                'duration_ms' => $durationMs,
                'exception_class' => $e::class,
                'exception_safe_message' => $safeMsg,
                'http_status' => null,
                'reason_code' => 'sabre_cancel_unexpected_error',
            ]));

            return $this->normalizedFailure(
                httpStatus: null,
                safeMessage: 'Sabre cancel request could not be completed.',
                liveCallAttempted: true,
                errorCode: 'sabre_cancel_unexpected_error',
                reasonCode: 'sabre_cancel_unexpected_error',
                diagnostics: array_merge($baseLog, [
                    'duration_ms' => $durationMs,
                    'exception_class' => $e::class,
                    'exception_safe_message' => $safeMsg,
                    'http_status' => null,
                    'live_call_attempted' => true,
                ]),
            );
        }

        return $this->normalizeCancelInspectResponse($response, (int) round((microtime(true) - $started) * 1000), $baseLog);
    }

    /**
     * @return array<string, mixed>
     */
    protected function normalizeCancelInspectResponse(Response $response, int $durationMs, array $baseLog): array
    {
        $http = $response->status();
        $json = $response->json();
        $arr = is_array($json) ? $json : [];
        $digest = $arr !== [] ? $this->digestBookingResponseJsonForProbe($arr) : [];
        $safeKeys = $this->safeTopLevelKeys($arr);

        if (! $response->successful()) {
            $errorCode = match ($http) {
                403 => 'sabre_cancel_forbidden',
                400, 422 => 'sabre_cancel_validation_failed',
                404, 405 => 'sabre_cancel_endpoint_mismatch',
                default => 'sabre_cancel_http_failed',
            };
            Log::notice('sabre.cancel_inspect.http_failed', array_merge($baseLog, [
                'http_status' => $http,
                'duration_ms' => $durationMs,
                'reason_code' => $errorCode,
            ]));

            return $this->normalizedFailure(
                httpStatus: $http,
                safeMessage: 'Sabre cancel probe returned HTTP '.$http.'.',
                liveCallAttempted: true,
                errorCode: $errorCode,
                reasonCode: $errorCode,
                diagnostics: array_merge($baseLog, [
                    'duration_ms' => $durationMs,
                    'http_status' => $http,
                    'live_call_attempted' => true,
                    'response_safe_keys' => $safeKeys,
                ], $digest),
            );
        }

        Log::info('sabre.cancel_inspect.http_ok', array_merge($baseLog, [
            'http_status' => $http,
            'duration_ms' => $durationMs,
        ]));

        return [
            'success' => true,
            'status' => 'probe_ack',
            'provider' => SupplierProvider::Sabre->value,
            'safe_message' => 'Sabre cancel probe returned HTTP '.$http.' (inspect only; booking status not updated).',
            'http_status' => $http,
            'live_call_attempted' => true,
            'error_code' => null,
            'reason_code' => 'sabre_cancel_probe_ack',
            'booking_diagnostics' => array_merge($baseLog, [
                'duration_ms' => $durationMs,
                'http_status' => $http,
                'live_call_attempted' => true,
                'response_safe_keys' => $safeKeys,
            ], $digest),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function normalizeBookingResponse(Response $response, int $durationMs, array $baseLog): array
    {
        $http = $response->status();
        $json = $response->json();
        $arr = is_array($json) ? $json : [];

        if (! $response->successful()) {
            $errorCode = match ($http) {
                403 => 'sabre_booking_forbidden',
                400, 422 => 'sabre_booking_validation_failed',
                404, 405 => 'sabre_booking_endpoint_mismatch',
                default => 'sabre_booking_http_failed',
            };
            $reasonCode = $errorCode;
            $safe = match ($http) {
                403 => 'Sabre booking endpoint is forbidden for this credential/path. Try configured booking path or contact Sabre/provider.',
                default => $this->safeProviderMessage($arr, $http),
            };
            if (in_array($http, [400, 422], true)) {
                $excerpts = $this->safeValidationExcerpts($arr);
                if ($excerpts !== []) {
                    $safe = 'Sabre booking validation failed: '.implode(' | ', $excerpts);
                } else {
                    $safe = 'Sabre booking validation failed (HTTP '.$http.').';
                }
            }
            if (in_array($http, [404, 405], true)) {
                $safe = 'Sabre booking path or HTTP method does not match this environment (HTTP '.$http.').';
            }

            $httpErrorDigest = $arr !== [] ? $this->digestBookingResponseJsonForProbe($arr) : [];
            $structuredExcerpts = [];

            if (in_array($http, [400, 422], true)) {
                $structuredExcerpts = app(SabrePassengerRecordsHttpValidationExcerptBuilder::class)
                    ->buildStructuredExcerpts($arr);
                $scan = strtolower(implode(' ', $this->safeValidationExcerpts($arr)));
                $msgs = is_array($httpErrorDigest['response_error_messages'] ?? null)
                    ? $httpErrorDigest['response_error_messages']
                    : [];
                $scan .= ' '.strtolower(implode(' ', array_map('strval', $msgs)));
                if (str_contains($scan, 'must not be null') && ($baseLog['wire_payload_null_free'] ?? null) === true) {
                    $safe .= ' Sabre may expect a different field name/object shape; wire payload has no JSON null values.';
                }
            }

            Log::notice('sabre.booking.http_failed', array_merge($baseLog, [
                'http_status' => $http,
                'duration_ms' => $durationMs,
                'reason_code' => $reasonCode,
                'error_code' => $errorCode,
                'has_booking_class' => $baseLog['has_booking_class'] ?? null,
                'has_fare_basis' => $baseLog['has_fare_basis'] ?? null,
                'has_end_transaction' => $baseLog['has_end_transaction'] ?? null,
                'response_error_count' => $httpErrorDigest['response_error_count'] ?? null,
            ]));

            return $this->normalizedFailure(
                httpStatus: $http,
                safeMessage: $safe,
                liveCallAttempted: true,
                errorCode: $errorCode,
                reasonCode: $reasonCode,
                diagnostics: array_merge($baseLog, [
                    'duration_ms' => $durationMs,
                    'exception_class' => null,
                    'exception_safe_message' => null,
                    'http_status' => $http,
                    'live_call_attempted' => true,
                    'safe_validation_excerpts' => $this->safeValidationExcerpts($arr),
                    'safe_validation_excerpts_structured' => $structuredExcerpts,
                ], $httpErrorDigest),
            );
        }

        if ($this->jsonBodyLooksLikePassengerRecordsCreateRs($arr)) {
            return $this->normalizePassengerRecordsCpnrHttp200Response($arr, $http, $durationMs, $baseLog);
        }

        $digest = $this->parseTripOrdersCreateBookingSafeDigest($arr);
        $digestFlat = $this->flattenDigestForDiagnostics($digest);
        $pnr = $this->extractSabrePnrLocator($arr);
        $orderId = $this->extractSabreSupplierOrderId($arr);
        $providerStatus = $this->extractProviderStatus($arr);
        $hasAppErrors = $this->tripOrdersResponseHasApplicationErrors($arr);

        if ($hasAppErrors && $pnr === '' && $orderId === '') {
            Log::notice('sabre.booking.http_200_application_errors', array_merge($baseLog, [
                'http_status' => $http,
                'duration_ms' => $durationMs,
                'response_error_count' => $digest['response_error_count'] ?? 0,
            ]));

            return [
                'success' => false,
                'status' => 'needs_review',
                'provider' => SupplierProvider::Sabre->value,
                'pnr' => null,
                'record_locator' => null,
                'provider_booking_id' => null,
                'provider_status' => $providerStatus,
                'safe_message' => 'Sabre returned HTTP 200 with application-level errors and no booking locator. Staff review required.',
                'http_status' => $http,
                'live_call_attempted' => true,
                'error_code' => 'sabre_booking_application_error',
                'reason_code' => 'sabre_booking_application_error',
                'response_safe_keys' => $this->safeTopLevelKeys($arr),
                'booking_diagnostics' => array_merge($baseLog, [
                    'duration_ms' => $durationMs,
                    'http_status' => $http,
                    'live_call_attempted' => true,
                    'reason_code' => 'sabre_booking_application_error',
                    'error_code' => 'sabre_booking_application_error',
                ], $digestFlat),
            ];
        }

        if ($pnr === '' && $orderId === '') {
            Log::notice('sabre.booking.success_without_locator', [
                'http_status' => $http,
                'duration_ms' => $durationMs,
                'connection_id' => $baseLog['supplier_connection_id'] ?? null,
                'reason_code' => 'sabre_booking_success_missing_locator',
            ]);

            return [
                'success' => true,
                'status' => 'needs_review',
                'provider' => SupplierProvider::Sabre->value,
                'pnr' => null,
                'record_locator' => null,
                'provider_booking_id' => null,
                'provider_status' => $providerStatus,
                'safe_message' => 'Sabre booking endpoint returned success but no PNR/locator was found. Staff review required.',
                'http_status' => $http,
                'live_call_attempted' => true,
                'error_code' => null,
                'reason_code' => 'sabre_booking_success_missing_locator',
                'response_safe_keys' => $this->safeTopLevelKeys($arr),
                'booking_diagnostics' => array_merge($baseLog, [
                    'duration_ms' => $durationMs,
                    'http_status' => $http,
                    'live_call_attempted' => true,
                    'reason_code' => 'sabre_booking_success_missing_locator',
                ], $digestFlat),
            ];
        }

        if ($pnr === '' && $orderId !== '') {
            Log::notice('sabre.booking.success_order_reference_without_pnr', [
                'http_status' => $http,
                'duration_ms' => $durationMs,
                'connection_id' => $baseLog['supplier_connection_id'] ?? null,
                'reason_code' => 'sabre_booking_success_missing_locator',
            ]);

            return [
                'success' => true,
                'status' => 'needs_review',
                'provider' => SupplierProvider::Sabre->value,
                'pnr' => null,
                'record_locator' => null,
                'provider_booking_id' => self::truncateSafeString($orderId, 120),
                'provider_status' => $providerStatus,
                'safe_message' => 'Sabre returned an order/booking reference without a PNR/locator. Staff review required.',
                'http_status' => $http,
                'live_call_attempted' => true,
                'error_code' => null,
                'reason_code' => 'sabre_booking_success_missing_locator',
                'response_safe_keys' => $this->safeTopLevelKeys($arr),
                'booking_diagnostics' => array_merge($baseLog, [
                    'duration_ms' => $durationMs,
                    'http_status' => $http,
                    'live_call_attempted' => true,
                    'reason_code' => 'sabre_booking_success_missing_locator',
                ], $digestFlat),
            ];
        }

        $providerBookingId = $orderId !== '' ? $orderId : null;

        return [
            'success' => true,
            'status' => 'pending_payment_or_ticketing',
            'provider' => SupplierProvider::Sabre->value,
            'pnr' => $pnr !== '' ? $pnr : null,
            'record_locator' => $pnr !== '' ? $pnr : null,
            'provider_booking_id' => $providerBookingId,
            'provider_status' => $providerStatus,
            'safe_message' => $pnr !== ''
                ? 'Sabre PNR created; ticketing or payment may still be required.'
                : 'Sabre order/booking reference returned; PNR/locator not present yet. Ticketing or payment may still be required.',
            'http_status' => $http,
            'live_call_attempted' => true,
            'error_code' => null,
            'reason_code' => 'sabre_booking_success',
            'booking_diagnostics' => array_merge($baseLog, [
                'duration_ms' => $durationMs,
                'http_status' => $http,
                'live_call_attempted' => true,
                'reason_code' => 'sabre_booking_success',
            ], $digestFlat),
        ];
    }

    /**
     * Resolve the JSON body posted to Passenger Records create (or Trip Orders when schema is trip_orders).
     * CPNR: top-level {@code _ota*} strip only — no payload rebuild.
     *
     * @param  array<string, mixed>  $apiEnvelope
     * @return array<string, mixed>
     */
    public function resolvePassengerRecordsWireEnvelopeForPost(array $apiEnvelope): array
    {
        if (($apiEnvelope['_ota_payload_schema'] ?? '') === 'trip_orders_create_booking_v1') {
            return $this->bookingPayloadBuilder->tripOrdersFinalWirePostBodyFromEnvelope($apiEnvelope);
        }

        return $this->stripOtaInternalEnvelopeKeys($apiEnvelope);
    }

    /**
     * Remove keys prefixed with {@code _ota} (diagnostics-only) from the booking envelope before HTTP.
     *
     * @param  array<string, mixed>  $envelope
     * @return array<string, mixed>
     */
    protected function stripOtaInternalEnvelopeKeys(array $envelope): array
    {
        $out = [];
        foreach ($envelope as $k => $v) {
            if (is_string($k) && str_starts_with($k, '_ota')) {
                continue;
            }
            $out[$k] = $v;
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $json
     * @return list<string>
     */
    protected function safeTopLevelKeys(array $json, int $max = 48): array
    {
        $keys = [];
        foreach (array_keys($json) as $k) {
            if (is_string($k)) {
                $keys[] = $k;
            }
            if (count($keys) >= $max) {
                break;
            }
        }

        return $keys;
    }

    /**
     * Passenger Records create response envelope (REST returns {@code CreatePassengerNameRecordRS} / camelCase sibling).
     *
     * @param  array<string, mixed>  $json
     */
    protected function jsonBodyLooksLikePassengerRecordsCreateRs(array $json): bool
    {
        foreach (['CreatePassengerNameRecordRS', 'createPassengerNameRecordRS'] as $k) {
            if (isset($json[$k]) && is_array($json[$k])) {
                return true;
            }
        }

        return false;
    }

    /**
     * B62/C1: Passenger Records HTTP 200 + {@code ApplicationResults} + {@code CreatePassengerNameRecordRS} PNR extraction
     * (no Trip Orders {@code errors[]} application-error path unless this body is absent). No PNR + Incomplete/NotProcessed,
     * {@code ERR.SP.PROVIDER_ERROR}, CHECK FLIGHT NUMBER, or air-booking-step failure → {@code success=false},
     * {@code sabre_booking_application_error}; non-empty PNR → pending ticketing unchanged.
     *
     * @param  array<string, mixed>  $arr
     * @param  array<string, mixed>  $baseLog
     * @return array<string, mixed>
     */
    protected function normalizePassengerRecordsCpnrHttp200Response(array $arr, int $http, int $durationMs, array $baseLog): array
    {
        $probe = $this->digestBookingResponseJsonForProbe($arr);
        $digFlat = $this->bookingDiagnosticsMergedFromProbeDigest($probe);

        $pnr = $this->extractSabrePnrLocator($arr);
        $pnrTrim = trim($pnr);
        $orderId = $this->extractSabreSupplierOrderId($arr);
        $providerStatus = $this->extractProviderStatus($arr);
        $appStatus = isset($probe['application_results_status']) && is_string($probe['application_results_status'])
            ? trim($probe['application_results_status'])
            : '';

        $diagMerge = array_merge($baseLog, [
            'duration_ms' => $durationMs,
            'http_status' => $http,
            'live_call_attempted' => true,
            'response_safe_keys' => $this->safeTopLevelKeys($arr),
            'pnr_present_in_response_body' => $pnrTrim !== '',
        ], $digFlat);

        if ($pnrTrim !== '') {
            if (($baseLog['allow_nn_cert_operational'] ?? false) === true
                && $this->probeIndicatesUnconfirmedSegmentNnAfterPnrCreate($probe)
                && ! $this->probeIndicatesConfirmedSegmentStatus($probe)) {
                Log::notice('sabre.booking.passenger_records_pnr_unconfirmed_segment_nn', [
                    'http_status' => $http,
                    'duration_ms' => $durationMs,
                    'supplier_connection_id' => $baseLog['supplier_connection_id'] ?? null,
                    'booking_id' => $baseLog['booking_id'] ?? null,
                    'reason_code' => 'sabre_passenger_records_pnr_unconfirmed_segment_nn',
                ]);

                return [
                    'success' => false,
                    'status' => 'needs_review',
                    'provider' => SupplierProvider::Sabre->value,
                    'pnr' => strtoupper(substr($pnrTrim, 0, 32)),
                    'record_locator' => strtoupper(substr($pnrTrim, 0, 32)),
                    'provider_booking_id' => $orderId !== '' ? $orderId : null,
                    'provider_status' => $providerStatus,
                    'safe_message' => 'Sabre returned a PNR but segment status remains NN (unconfirmed). Staff review required before treating as confirmed.',
                    'http_status' => $http,
                    'live_call_attempted' => true,
                    'error_code' => 'sabre_booking_application_error',
                    'reason_code' => 'sabre_passenger_records_pnr_unconfirmed_segment_nn',
                    'response_safe_keys' => $this->safeTopLevelKeys($arr),
                    'booking_diagnostics' => array_merge($diagMerge, [
                        'reason_code' => 'sabre_passenger_records_pnr_unconfirmed_segment_nn',
                        'error_code' => 'sabre_booking_application_error',
                        'airline_segment_status' => 'NN',
                    ]),
                ];
            }

            Log::info('sabre.booking.passenger_records_pnr_created', [
                'http_status' => $http,
                'duration_ms' => $durationMs,
                'supplier_connection_id' => $baseLog['supplier_connection_id'] ?? null,
                'booking_id' => $baseLog['booking_id'] ?? null,
                'application_results_complete' => strcasecmp($appStatus, 'Complete') === 0,
            ]);

            $providerBookingId = $orderId !== '' ? $orderId : null;

            return [
                'success' => true,
                'status' => 'pending_payment_or_ticketing',
                'provider' => SupplierProvider::Sabre->value,
                'pnr' => strtoupper(substr($pnrTrim, 0, 32)),
                'record_locator' => strtoupper(substr($pnrTrim, 0, 32)),
                'provider_booking_id' => $providerBookingId,
                'provider_status' => $providerStatus,
                'safe_message' => 'Sabre PNR created via Passenger Records; ticketing or payment may still be required.',
                'http_status' => $http,
                'live_call_attempted' => true,
                'error_code' => null,
                'reason_code' => 'sabre_booking_success',
                'booking_diagnostics' => array_merge($diagMerge, [
                    'reason_code' => 'sabre_booking_success',
                    'error_code' => null,
                ]),
            ];
        }

        $incompleteNoPnr = $this->probeIndicatesPassengerRecordsIncompleteOrNotProcessed($appStatus, $probe);
        $completeNoPnr = strcasecmp($appStatus, 'Complete') === 0;
        $applicationBookingFailure = $this->probeIndicatesPassengerRecordsApplicationBookingFailure($probe);
        $hostNnHalt = $this->probeIndicatesHostSegmentNnHalt($probe);
        $hostSellReject = $this->probeIndicatesHostSegmentSellReject($probe);

        if ($pnrTrim === '' && $hostNnHalt) {
            $flights = is_array($probe['affected_flight_numbers'] ?? null)
                ? implode(', ', array_map('strval', array_slice($probe['affected_flight_numbers'], 0, 8)))
                : '';
            $segmentStatus = 'NN';

            Log::notice('sabre.booking.passenger_records_halt_on_status_nn', [
                'http_status' => $http,
                'duration_ms' => $durationMs,
                'supplier_connection_id' => $baseLog['supplier_connection_id'] ?? null,
                'booking_id' => $baseLog['booking_id'] ?? null,
                'reason_code' => 'sabre_passenger_records_halt_on_status_nn',
                'segment_status' => $segmentStatus,
                'affected_flight_count' => is_array($probe['affected_flight_numbers'] ?? null)
                    ? count($probe['affected_flight_numbers'])
                    : 0,
            ]);

            $safeMessage = $flights !== ''
                ? 'Sabre halted booking: airline returned pending segment status NN ('.$flights.'). No PNR created — try CERT allow-NN diagnostic or another itinerary.'
                : 'Sabre halted booking: airline returned pending segment status NN. No PNR created — try CERT allow-NN diagnostic or another itinerary.';

            return [
                'success' => false,
                'status' => 'needs_review',
                'provider' => SupplierProvider::Sabre->value,
                'pnr' => null,
                'record_locator' => null,
                'provider_booking_id' => null,
                'provider_status' => $providerStatus,
                'safe_message' => $safeMessage,
                'http_status' => $http,
                'live_call_attempted' => true,
                'error_code' => 'sabre_booking_application_error',
                'reason_code' => 'sabre_passenger_records_halt_on_status_nn',
                'response_safe_keys' => $this->safeTopLevelKeys($arr),
                'booking_diagnostics' => array_merge($diagMerge, [
                    'reason_code' => 'sabre_passenger_records_halt_on_status_nn',
                    'error_code' => 'sabre_booking_application_error',
                ]),
            ];
        }

        if ($pnrTrim === '' && $hostSellReject) {
            $flights = is_array($probe['affected_flight_numbers'] ?? null)
                ? implode(', ', array_map('strval', array_slice($probe['affected_flight_numbers'], 0, 8)))
                : '';
            $segmentStatus = strtoupper(trim((string) ($probe['airline_segment_status'] ?? 'UC')));

            Log::notice('sabre.booking.passenger_records_halt_on_status_uc', [
                'http_status' => $http,
                'duration_ms' => $durationMs,
                'supplier_connection_id' => $baseLog['supplier_connection_id'] ?? null,
                'booking_id' => $baseLog['booking_id'] ?? null,
                'reason_code' => 'sabre_passenger_records_halt_on_status_uc',
                'segment_status' => $segmentStatus !== '' ? $segmentStatus : null,
                'affected_flight_count' => is_array($probe['affected_flight_numbers'] ?? null)
                    ? count($probe['affected_flight_numbers'])
                    : 0,
            ]);

            $safeMessage = $flights !== ''
                ? 'Sabre halted booking: airline returned segment status '.$segmentStatus.' ('.$flights.'). No PNR created — choose another itinerary or fresh search.'
                : 'Sabre halted booking: airline returned segment status '.$segmentStatus.'. No PNR created — choose another itinerary or fresh search.';

            return [
                'success' => false,
                'status' => 'needs_review',
                'provider' => SupplierProvider::Sabre->value,
                'pnr' => null,
                'record_locator' => null,
                'provider_booking_id' => null,
                'provider_status' => $providerStatus,
                'safe_message' => $safeMessage,
                'http_status' => $http,
                'live_call_attempted' => true,
                'error_code' => 'sabre_booking_application_error',
                'reason_code' => 'sabre_passenger_records_halt_on_status_uc',
                'response_safe_keys' => $this->safeTopLevelKeys($arr),
                'booking_diagnostics' => array_merge($diagMerge, [
                    'reason_code' => 'sabre_passenger_records_halt_on_status_uc',
                    'error_code' => 'sabre_booking_application_error',
                ]),
            ];
        }

        if ($incompleteNoPnr || $applicationBookingFailure) {
            $reasonCode = $incompleteNoPnr
                ? 'sabre_passenger_records_incomplete_no_pnr'
                : 'sabre_passenger_records_application_booking_failure';
            $safeMessage = $this->safeMessageForPassengerRecordsApplicationBookingFailure($probe, $incompleteNoPnr);
            $applicationDigest = $this->passengerRecordsApplicationDigestFromResponse($arr);

            Log::notice('sabre.booking.passenger_records_application_failure_no_pnr', [
                'http_status' => $http,
                'duration_ms' => $durationMs,
                'supplier_connection_id' => $baseLog['supplier_connection_id'] ?? null,
                'booking_id' => $baseLog['booking_id'] ?? null,
                'reason_code' => $reasonCode,
                'application_results_status' => $appStatus !== '' ? substr($appStatus, 0, 32) : null,
                'application_results_incomplete' => (bool) ($probe['application_results_incomplete'] ?? false),
            ]);

            return [
                'success' => false,
                'status' => 'needs_review',
                'provider' => SupplierProvider::Sabre->value,
                'pnr' => null,
                'record_locator' => null,
                'provider_booking_id' => null,
                'provider_status' => $providerStatus,
                'safe_message' => $safeMessage,
                'http_status' => $http,
                'live_call_attempted' => true,
                'error_code' => 'sabre_booking_application_error',
                'reason_code' => $reasonCode,
                'response_safe_keys' => $this->safeTopLevelKeys($arr),
                'passenger_records_application_digest' => $applicationDigest,
                'booking_diagnostics' => array_merge($diagMerge, [
                    'reason_code' => $reasonCode,
                    'error_code' => 'sabre_booking_application_error',
                    'passenger_records_application_digest' => $applicationDigest,
                ]),
            ];
        }

        if ($completeNoPnr || $orderId !== '') {
            Log::notice('sabre.booking.passenger_records_complete_without_pnr', [
                'http_status' => $http,
                'duration_ms' => $durationMs,
                'connection_id' => $baseLog['supplier_connection_id'] ?? null,
                'booking_id' => $baseLog['booking_id'] ?? null,
                'reason_code' => 'sabre_booking_success_missing_locator',
            ]);

            return [
                'success' => true,
                'status' => 'needs_review',
                'provider' => SupplierProvider::Sabre->value,
                'pnr' => null,
                'record_locator' => null,
                'provider_booking_id' => $orderId !== '' ? self::truncateSafeString($orderId, 120) : null,
                'provider_status' => $providerStatus,
                'safe_message' => 'Sabre Passenger Records completed without exposing a locator. Staff review may be required.',
                'http_status' => $http,
                'live_call_attempted' => true,
                'error_code' => null,
                'reason_code' => 'sabre_booking_success_missing_locator',
                'response_safe_keys' => $this->safeTopLevelKeys($arr),
                'booking_diagnostics' => array_merge($diagMerge, [
                    'reason_code' => 'sabre_booking_success_missing_locator',
                    'error_code' => null,
                ]),
            ];
        }

        Log::notice('sabre.booking.passenger_records_uncertain_no_locator', [
            'http_status' => $http,
            'duration_ms' => $durationMs,
            'supplier_connection_id' => $baseLog['supplier_connection_id'] ?? null,
            'booking_id' => $baseLog['booking_id'] ?? null,
            'reason_code' => 'sabre_passenger_records_uncertain_response',
            'application_results_status_truncated' => $appStatus !== '' ? substr($appStatus, 0, 32) : null,
        ]);

        return [
            'success' => false,
            'status' => 'needs_review',
            'provider' => SupplierProvider::Sabre->value,
            'pnr' => null,
            'record_locator' => null,
            'provider_booking_id' => null,
            'provider_status' => $providerStatus,
            'safe_message' => 'Sabre Passenger Records response did not expose a locator. Staff review required.',
            'http_status' => $http,
            'live_call_attempted' => true,
            'error_code' => 'sabre_booking_application_error',
            'reason_code' => 'sabre_passenger_records_uncertain_response',
            'response_safe_keys' => $this->safeTopLevelKeys($arr),
            'booking_diagnostics' => array_merge($diagMerge, [
                'reason_code' => 'sabre_passenger_records_uncertain_response',
                'error_code' => 'sabre_booking_application_error',
            ]),
        ];
    }

    /**
     * @param  array<string, mixed>  $probe  {@see digestBookingResponseJsonForProbe()}
     * @return array<string, mixed>
     */
    protected function bookingDiagnosticsMergedFromProbeDigest(array $probe): array
    {
        $flat = $this->flattenDigestForDiagnostics($probe);

        if (isset($probe['application_results_status']) && is_string($probe['application_results_status'])) {
            $flat['application_results_status'] = self::truncateSafeString(trim((string) $probe['application_results_status']), 64);
        }
        $flat['application_results_incomplete'] = (bool) ($probe['application_results_incomplete'] ?? false);
        $flat['passenger_records_error_digest_present'] = (bool) ($probe['passenger_records_error_digest_present'] ?? false);

        foreach (['host_warning_modules', 'host_warning_sabre_codes', 'host_warning_messages_truncated'] as $hk) {
            if (! isset($probe[$hk]) || ! is_array($probe[$hk])) {
                continue;
            }
            $flat[$hk] = array_slice(array_map('strval', $probe[$hk]), 0, $hk === 'host_warning_messages_truncated' ? 16 : 24);
        }

        return $flat;
    }

    /**
     * Airline / Sabre record locator only (not generic order IDs).
     *
     * @param  array<string, mixed>  $json
     */
    protected function extractSabrePnrLocator(array $json): string
    {
        foreach (['CreatePassengerNameRecordRS', 'createPassengerNameRecordRS'] as $rk) {
            $rs = $json[$rk] ?? null;
            if (! is_array($rs)) {
                continue;
            }
            foreach ([
                'ItineraryRef.ID',
                'ItineraryRef.Id',
                'ItineraryRef.id',
                'itineraryRef.ID',
                'itineraryRef.Id',
                'itineraryRef.id',
                'TravelItineraryRead.TravelItinerary.ItineraryRef.ID',
                'TravelItineraryRead.TravelItinerary.ItineraryRef.Id',
                'TravelItineraryRead.TravelItinerary.ItineraryRef.id',
            ] as $p) {
                $v = data_get($rs, $p);
                if (is_string($v) && trim($v) !== '') {
                    return strtoupper(substr(trim($v), 0, 32));
                }
            }
            $ref = $rs['ItineraryRef'] ?? $rs['itineraryRef'] ?? null;
            if (is_array($ref)) {
                foreach (['ID', 'Id', 'id'] as $ik) {
                    $v = $ref[$ik] ?? null;
                    if (is_string($v) && trim($v) !== '') {
                        return strtoupper(substr(trim($v), 0, 32));
                    }
                }
            }
        }

        $paths = [
            'recordLocator',
            'RecordLocator',
            'record_locator',
            'PNR',
            'pnr',
            'airlinePnr',
            'passengerNameRecord.locator',
            'CreatePassengerNameRecordRS.ItineraryRef.ID',
            'CreatePassengerNameRecordRS.TravelItineraryRead.TravelItinerary.ItineraryRef.ID',
            'Creation.recordLocator',
            'PassengerReservation.recordLocator',
            'passengerReservation.recordLocator',
            'confirmation.recordLocator',
            'passengerNameRecord.RecordLocator',
            'PassengerNameRecord.RecordLocator',
            'data.recordLocator',
            'data.pnr',
            'data.PNR',
            'data.reservation.locator',
            'data.reservation.recordLocator',
        ];
        foreach ($paths as $p) {
            $v = data_get($json, $p);
            if (is_string($v) && trim($v) !== '') {
                return strtoupper(substr(trim($v), 0, 32));
            }
        }

        return '';
    }

    /**
     * Trip Orders / REST order or booking identifiers (excludes correlation-only IDs).
     *
     * @param  array<string, mixed>  $json
     */
    protected function extractSabreSupplierOrderId(array $json): string
    {
        foreach ([
            'bookingId',
            'booking.id',
            'orderId',
            'order.id',
            'reservationId',
            'reservation.id',
            'itinerary.id',
            'supplierReference',
            'confirmationId',
            'bookingReference',
            'data.bookingId',
            'data.orderId',
            'data.reservationId',
            'data.supplierReference',
            'data.confirmationId',
            'data.bookingReference',
            'data.booking.id',
            'data.order.id',
            'data.itinerary.id',
            'passengerNameRecord.id',
        ] as $k) {
            $v = data_get($json, $k);
            if (is_string($v) && trim($v) !== '') {
                return self::truncateSafeString(trim($v), 120);
            }
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $json
     * @return array<string, mixed>
     */
    protected function parseTripOrdersCreateBookingSafeDigest(array $json): array
    {
        $codes = [];
        $messages = [];
        $fieldHints = [];
        $missingAgg = [];
        $pathHints = [];
        $errors = $json['errors'] ?? null;
        if (is_array($errors)) {
            foreach ($errors as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $code = isset($row['code']) ? self::truncateSafeString((string) $row['code']) : '';
                $parts = [];
                foreach (['message', 'title', 'detail', 'type', 'status', 'description', 'developerMessage'] as $ek) {
                    if (isset($row[$ek]) && is_string($row[$ek]) && trim($row[$ek]) !== '') {
                        $parts[] = self::truncateSafeString(trim($row[$ek]));
                    }
                }
                foreach (['details', 'detail'] as $dk) {
                    if (! array_key_exists($dk, $row)) {
                        continue;
                    }
                    $flat = $this->flattenMixedForSafeDigest($row[$dk]);
                    if ($flat !== '') {
                        $parts[] = $flat;
                    }
                }
                foreach (['field', 'path', 'parameter', 'source'] as $fk) {
                    if (! isset($row[$fk])) {
                        continue;
                    }
                    $fv = $this->flattenMixedForSafeDigest($row[$fk]);
                    if ($fv !== '') {
                        $fieldHints[] = $fv;
                    }
                }
                $this->collectTripOrdersErrorPathHintsFromRow($row, $pathHints);
                if (isset($row['validationErrors']) && is_array($row['validationErrors'])) {
                    foreach (array_slice($row['validationErrors'], 0, 16) as $ve) {
                        if (is_array($ve)) {
                            $this->collectTripOrdersErrorPathHintsFromRow($ve, $pathHints);
                        }
                    }
                }
                foreach (['validationMessages', 'validationErrors'] as $vk) {
                    if (! isset($row[$vk])) {
                        continue;
                    }
                    $ex = $this->flattenMixedForSafeDigest($row[$vk]);
                    if ($ex !== '') {
                        $parts[] = $ex;
                    }
                }
                if (isset($row['missingFields'])) {
                    $this->collectMissingFieldTokens($row['missingFields'], $missingAgg, $fieldHints);
                }
                $msg = $parts !== [] ? implode(' — ', array_slice($parts, 0, 6)) : '';
                if ($code !== '') {
                    $codes[] = $code;
                }
                if ($msg !== '') {
                    $messages[] = $msg;
                }
            }
        }

        $single = $json['error'] ?? null;
        if (is_array($single)) {
            if (isset($single['code']) && is_string($single['code']) && trim($single['code']) !== '') {
                $codes[] = self::truncateSafeString(trim($single['code']));
            }
            $eparts = [];
            foreach (['message', 'title', 'detail', 'description', 'developerMessage'] as $ek) {
                if (isset($single[$ek]) && is_string($single[$ek]) && trim($single[$ek]) !== '') {
                    $eparts[] = self::truncateSafeString(trim($single[$ek]));
                }
            }
            foreach (['details', 'validationMessages', 'validationErrors'] as $ek) {
                if (! array_key_exists($ek, $single)) {
                    continue;
                }
                $ex = $this->flattenMixedForSafeDigest($single[$ek]);
                if ($ex !== '') {
                    $eparts[] = $ex;
                }
            }
            foreach (['field', 'path', 'parameter', 'source'] as $fk) {
                if (! isset($single[$fk])) {
                    continue;
                }
                $fv = $this->flattenMixedForSafeDigest($single[$fk]);
                if ($fv !== '') {
                    $fieldHints[] = $fv;
                }
            }
            $this->collectTripOrdersErrorPathHintsFromRow($single, $pathHints);
            if (isset($single['validationErrors']) && is_array($single['validationErrors'])) {
                foreach (array_slice($single['validationErrors'], 0, 16) as $ve) {
                    if (is_array($ve)) {
                        $this->collectTripOrdersErrorPathHintsFromRow($ve, $pathHints);
                    }
                }
            }
            if (isset($single['missingFields'])) {
                $this->collectMissingFieldTokens($single['missingFields'], $missingAgg, $fieldHints);
            }
            if ($eparts !== []) {
                $messages[] = implode(' — ', $eparts);
            }
        }

        if (isset($json['validationErrors']) && is_array($json['validationErrors'])) {
            foreach (array_slice($json['validationErrors'], 0, 16) as $ve) {
                if (is_array($ve)) {
                    $this->collectTripOrdersErrorPathHintsFromRow($ve, $pathHints);
                }
            }
        }

        $codes = array_values(array_unique(array_slice($codes, 0, 24)));
        $messages = array_values(array_unique(array_slice($messages, 0, 24)));
        $fieldHints = array_values(array_unique(array_slice($fieldHints, 0, 32)));
        $missingAgg = array_values(array_unique(array_slice($missingAgg, 0, 32)));
        $pathHints = array_values(array_unique(array_slice($pathHints, 0, 48)));

        $topMessage = isset($json['message']) && is_string($json['message']) && trim($json['message']) !== ''
            ? self::truncateSafeString(trim($json['message']))
            : null;
        $topStatus = isset($json['status']) && is_string($json['status']) && trim($json['status']) !== ''
            ? self::truncateSafeString(trim($json['status']))
            : null;

        $requestId = $this->extractSabreRequestId($json);
        $requestCorrelationId = $this->extractSabreRequestCorrelationIdOnly($json);
        $traceId = isset($json['traceId']) && is_string($json['traceId']) && trim($json['traceId']) !== ''
            ? self::truncateSafeString(trim($json['traceId']))
            : null;
        $timestamp = isset($json['timestamp']) && is_string($json['timestamp']) && trim($json['timestamp']) !== ''
            ? self::truncateSafeString(trim($json['timestamp']))
            : null;
        $responseTopLevelKeys = $this->safeTopLevelKeys($json);
        $topType = isset($json['type']) && is_string($json['type']) && trim($json['type']) !== ''
            ? self::truncateSafeString(trim($json['type']), 120)
            : null;
        $topErrorCode = null;
        foreach (['errorCode', 'error_code', 'ErrorCode'] as $ek) {
            if (! array_key_exists($ek, $json)) {
                continue;
            }
            $ev = $json[$ek];
            if ($ev === null || $ev === false || $ev === '') {
                continue;
            }
            if (is_string($ev) && trim($ev) !== '') {
                $topErrorCode = self::truncateSafeString(trim($ev), 120);
                break;
            }
            if (is_int($ev) || is_float($ev)) {
                $topErrorCode = self::truncateSafeString((string) $ev, 120);
                break;
            }
        }
        $additionalMsgs = [];
        $addRaw = $json['additionalMessages'] ?? null;
        if (is_array($addRaw)) {
            foreach (array_slice($addRaw, 0, 8) as $am) {
                if (is_string($am) && trim($am) !== '') {
                    $additionalMsgs[] = self::truncateSafeString(trim($am), 200);
                } elseif (is_array($am)) {
                    $flat = $this->flattenMixedForSafeDigest($am);
                    if ($flat !== '') {
                        $additionalMsgs[] = self::truncateSafeString($flat, 200);
                    }
                }
            }
        }

        $errList = is_array($errors) ? $errors : [];
        $singleCount = is_array($single) && $single !== [] ? 1 : 0;
        $responseErrorCount = $errList !== [] ? count($errList) : $singleCount;

        $digest = [
            'response_error_count' => $responseErrorCount,
            'response_error_codes' => $codes,
            'response_error_messages' => $messages,
            'response_error_fields' => $fieldHints,
            'response_error_paths' => $pathHints,
            'response_missing_fields' => $missingAgg,
            'response_top_level_message' => $topMessage,
            'response_top_level_status' => $topStatus,
            'response_top_level_keys' => $responseTopLevelKeys,
            'response_top_level_error_code' => $topErrorCode,
            'response_top_level_type' => $topType,
            'response_additional_messages' => $additionalMsgs,
            'request_id' => $requestId,
            'request_correlation_id' => $requestCorrelationId,
            'trace_id' => $traceId,
            'timestamp' => $timestamp,
        ];

        return SabreCancelProbeDiagnostics::enrichDigestFromJson($json, $digest);
    }

    /**
     * Collect JSON pointer / field tokens from a single Sabre error row (capped; no raw bodies).
     *
     * @param  array<string, mixed>  $row
     * @param  list<string>  $pathHints
     */
    protected function collectTripOrdersErrorPathHintsFromRow(array $row, array &$pathHints): void
    {
        foreach (['invalidField', 'invalid_field', 'property', 'field', 'path', 'parameter'] as $k) {
            if (! isset($row[$k])) {
                continue;
            }
            $v = $row[$k];
            if (is_string($v) && trim($v) !== '') {
                $pathHints[] = self::truncateSafeString(trim($v), 200);
            }
        }
        if (isset($row['source']) && is_array($row['source']) && isset($row['source']['pointer']) && is_string($row['source']['pointer']) && trim($row['source']['pointer']) !== '') {
            $pathHints[] = self::truncateSafeString(trim($row['source']['pointer']), 200);
        }
    }

    /**
     * @param  array<string, mixed>  $json
     */
    protected function extractSabreRequestId(array $json): ?string
    {
        foreach (['request.id', 'requestId', 'transactionId'] as $p) {
            $v = data_get($json, $p);
            if (is_string($v) && trim($v) !== '') {
                return self::truncateSafeString(trim($v));
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $json
     */
    protected function extractSabreRequestCorrelationIdOnly(array $json): ?string
    {
        $v = data_get($json, 'request.correlationId');
        if (is_string($v) && trim($v) !== '') {
            return self::truncateSafeString(trim($v));
        }

        return null;
    }

    /**
     * Flatten nested structures to a single capped diagnostic string (no raw JSON).
     */
    protected function flattenMixedForSafeDigest(mixed $value, int $depth = 0): string
    {
        if ($depth > 4) {
            return '';
        }
        if ($value === null) {
            return '';
        }
        if (is_string($value)) {
            return self::truncateSafeString(trim($value));
        }
        if (is_int($value) || is_float($value)) {
            return self::truncateSafeString((string) $value);
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (! is_array($value)) {
            return '';
        }
        $parts = [];
        if ($this->isAssocArray($value)) {
            foreach (['message', 'title', 'detail', 'description', 'code', 'field', 'path', 'parameter', 'text', 'value'] as $k) {
                if (! isset($value[$k])) {
                    continue;
                }
                $inner = $this->flattenMixedForSafeDigest($value[$k], $depth + 1);
                if ($inner !== '') {
                    $parts[] = $inner;
                }
            }
        } else {
            foreach (array_slice($value, 0, 12) as $item) {
                $inner = $this->flattenMixedForSafeDigest($item, $depth + 1);
                if ($inner !== '') {
                    $parts[] = $inner;
                }
            }
        }

        return self::truncateSafeString(implode(' | ', array_slice($parts, 0, 10)));
    }

    /**
     * @param  array<int|string, mixed>  $missing
     * @param  list<string>  $missingAgg
     * @param  list<string>  $fieldHints
     */
    protected function collectMissingFieldTokens(mixed $missing, array &$missingAgg, array &$fieldHints): void
    {
        if (is_string($missing) && trim($missing) !== '') {
            $t = self::truncateSafeString(trim($missing));
            $missingAgg[] = $t;
            $fieldHints[] = $t;

            return;
        }
        if (! is_array($missing)) {
            return;
        }
        foreach (array_slice($missing, 0, 24) as $m) {
            if (is_string($m) && trim($m) !== '') {
                $t = self::truncateSafeString(trim($m));
                $missingAgg[] = $t;
                $fieldHints[] = $t;
            } elseif (is_array($m)) {
                foreach (['field', 'path', 'name', 'parameter', 'key'] as $k) {
                    if (isset($m[$k]) && is_string($m[$k]) && trim($m[$k]) !== '') {
                        $t = self::truncateSafeString(trim($m[$k]));
                        $missingAgg[] = $t;
                        $fieldHints[] = $t;
                    }
                }
            }
        }
    }

    /**
     * @param  array<mixed>  $arr
     */
    protected function isAssocArray(array $arr): bool
    {
        return $arr !== [] && array_keys($arr) !== range(0, count($arr) - 1);
    }

    /**
     * @param  array<string, mixed>  $digest
     * @return array<string, mixed>
     */
    protected function flattenDigestForDiagnostics(array $digest): array
    {
        $out = [];
        foreach ([
            'response_error_count',
            'response_error_codes',
            'response_error_messages',
            'response_error_fields',
            'response_error_paths',
            'response_missing_fields',
            'response_top_level_message',
            'response_top_level_status',
            'request_id',
            'request_correlation_id',
            'trace_id',
            'timestamp',
            'airline_segment_status',
            'affected_flight_numbers',
            'halt_on_status_received',
            'probable_issue',
            'retry_blocker_reasons',
        ] as $k) {
            if (array_key_exists($k, $digest)) {
                $out[$k] = $digest[$k];
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $json
     */
    protected function tripOrdersResponseHasApplicationErrors(array $json): bool
    {
        $errors = $json['errors'] ?? null;
        if (is_array($errors) && $errors !== []) {
            return true;
        }
        $single = $json['error'] ?? null;
        if (is_array($single) && $single !== []) {
            foreach (['code', 'message', 'title', 'detail', 'description', 'developerMessage'] as $k) {
                if (isset($single[$k]) && is_string($single[$k]) && trim($single[$k]) !== '') {
                    return true;
                }
            }
            if (isset($single['missingFields']) && is_array($single['missingFields']) && $single['missingFields'] !== []) {
                return true;
            }
        }

        return false;
    }

    protected static function truncateSafeString(string $value, int $max = self::SAFE_EXCERPT_MAX): string
    {
        if ($max < 1) {
            return '';
        }
        if (strlen($value) <= $max) {
            return $value;
        }

        return substr($value, 0, $max);
    }

    /**
     * @param  array<string, mixed>  $json
     */
    protected function extractProviderStatus(?array $json): ?string
    {
        if ($json === null || $json === []) {
            return null;
        }
        foreach (['status', 'bookingStatus', 'reservationStatus'] as $k) {
            if (isset($json[$k]) && is_string($json[$k]) && trim($json[$k]) !== '') {
                return substr(trim($json[$k]), 0, 64);
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $json
     */
    protected function safeProviderMessage(array $json, int $http): string
    {
        if (isset($json['message']) && is_string($json['message']) && trim($json['message']) !== '') {
            return 'Sabre booking error (HTTP '.$http.'): '.substr(trim($json['message']), 0, 200);
        }
        $errors = $json['errors'] ?? null;
        if (is_array($errors) && isset($errors[0]) && is_array($errors[0])) {
            $parts = [];
            foreach (['title', 'detail', 'code', 'status'] as $k) {
                if (isset($errors[0][$k])) {
                    $parts[] = substr((string) $errors[0][$k], 0, 120);
                }
            }
            if ($parts !== []) {
                return 'Sabre booking error (HTTP '.$http.'): '.implode(' — ', $parts);
            }
        }

        return 'Sabre booking error (HTTP '.$http.').';
    }

    /**
     * @param  array<string, mixed>  $json
     * @return list<string>
     */
    protected function safeValidationExcerpts(array $json): array
    {
        $out = [];
        $errors = $json['errors'] ?? null;
        if (is_array($errors)) {
            foreach ($errors as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $chunk = '';
                foreach (['title', 'detail', 'message', 'code', 'source.pointer'] as $k) {
                    $v = data_get($row, $k);
                    if (is_string($v) && trim($v) !== '') {
                        $chunk .= ($chunk !== '' ? ' ' : '').substr(trim($v), 0, 100);
                    }
                }
                if ($chunk !== '') {
                    $out[] = $chunk;
                }
                if (count($out) >= 4) {
                    break;
                }
            }
        }
        $msg = $json['message'] ?? null;
        if (is_string($msg) && trim($msg) !== '' && count($out) < 4) {
            $out[] = substr(trim($msg), 0, 160);
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $diagnostics  Safe scalar metadata only
     * @return array<string, mixed>
     */
    protected function normalizedFailure(
        ?int $httpStatus,
        string $safeMessage,
        bool $liveCallAttempted,
        string $errorCode,
        string $reasonCode,
        array $diagnostics = [],
    ): array {
        return [
            'success' => false,
            'status' => 'failed',
            'provider' => SupplierProvider::Sabre->value,
            'pnr' => null,
            'record_locator' => null,
            'provider_booking_id' => null,
            'provider_status' => null,
            'safe_message' => $safeMessage,
            'http_status' => $httpStatus,
            'live_call_attempted' => $liveCallAttempted,
            'error_code' => $errorCode,
            'reason_code' => $reasonCode,
            'booking_diagnostics' => array_merge($diagnostics, [
                'reason_code' => $reasonCode,
                'error_code' => $errorCode,
            ]),
        ];
    }

    protected static function truncateExceptionMessage(Throwable $e): string
    {
        return substr(trim($e->getMessage()), 0, 240);
    }

    /**
     * B38/B44: Safe error digest for booking endpoint matrix probes (no raw body storage).
     * B44/B45: Merges Passenger Records {@code ApplicationResults} / {@code CreatePassengerNameRecordRS} / REST top-level error fields with Trip Orders {@code errors[]} parsing.
     *
     * @param  array<string, mixed>  $json
     * @return array<string, mixed>
     */
    public function digestBookingResponseJsonForProbe(array $json): array
    {
        $trip = $this->parseTripOrdersCreateBookingSafeDigest($json);
        $pnr = $this->parsePassengerRecordsStyleSafeDigest($json);

        return $this->enrichProbeDigestWithHostSegmentSellReject(
            $this->enrichPassengerRecordsProbeDigestHostWarnings(
                $this->mergeTripOrdersAndPassengerRecordProbeDigests($trip, $pnr)
            )
        );
    }

    /**
     * Sprint 11J: HTTP 200 Passenger Records host sell reject (UC + HaltOnStatus) — safe segment/flight tokens only.
     *
     * @param  array<string, mixed>  $digest
     * @return array<string, mixed>
     */
    protected function enrichProbeDigestWithHostSegmentSellReject(array $digest): array
    {
        $messages = (array) ($digest['response_error_messages'] ?? []);
        $codes = array_map('strtoupper', array_map('strval', (array) ($digest['response_error_codes'] ?? [])));
        $messagesUpper = strtoupper(implode(' ', array_map('strval', $messages)));

        $haltOnStatus = in_array('WARN.SP.HALT_ON_STATUS_RECEIVED', $codes, true)
            || str_contains($messagesUpper, 'HALT_ON_STATUS_RECEIVED')
            || str_contains($messagesUpper, 'HALT_ON_STATUS RECEIVED')
            || str_contains($messagesUpper, 'SPECIFIED HALTONSTATUS RECEIVED');

        $segmentStatus = null;
        $flights = [];
        foreach ($messages as $m) {
            if (! is_string($m) || trim($m) === '') {
                continue;
            }
            if (preg_match_all('/\b(?:Flight|Segment)\s+([A-Z]{2}\d{1,4})\s+returned\s+status\s+code\s+([A-Z]{2})\b/i', $m, $mm, PREG_SET_ORDER)) {
                foreach ($mm as $hit) {
                    $flight = strtoupper(trim((string) ($hit[1] ?? '')));
                    $status = strtoupper(trim((string) ($hit[2] ?? '')));
                    if ($flight !== '') {
                        $flights[$flight] = true;
                    }
                    if ($segmentStatus === null && $status !== '') {
                        $segmentStatus = $status;
                    }
                }
            }
        }

        if ($segmentStatus === null && str_contains($messagesUpper, 'STATUS CODE UC')) {
            $segmentStatus = 'UC';
        }
        if ($segmentStatus === null && str_contains($messagesUpper, 'STATUS CODE NN')) {
            $segmentStatus = 'NN';
        }

        $affectedFlights = array_values(array_slice(array_keys($flights), 0, 8));
        $hardRejectStatuses = ['UC', 'NO', 'UN'];
        $isHardReject = $segmentStatus !== null && in_array($segmentStatus, $hardRejectStatuses, true);
        $isNnHalt = $haltOnStatus && $segmentStatus === 'NN';

        if ($isNnHalt) {
            $digest['airline_segment_status'] = 'NN';
            $digest['affected_flight_numbers'] = $affectedFlights;
            $digest['halt_on_status_received'] = true;
            $digest['probable_issue'] = 'airline_segment_status_nn_halt';
            $digest['retry_blocker_reasons'] = [
                'airline_segment_status_nn_halt',
                'halt_on_status_received',
                'cert_allow_nn_diagnostic_or_alternate_itinerary',
            ];

            return $digest;
        }

        $ucReject = $isHardReject || ($haltOnStatus && ! $isNnHalt);
        if (! $ucReject) {
            return $digest;
        }

        if ($segmentStatus === null && $haltOnStatus) {
            $segmentStatus = 'UC';
        }

        $digest['airline_segment_status'] = $segmentStatus;
        $digest['affected_flight_numbers'] = $affectedFlights;
        $digest['halt_on_status_received'] = $haltOnStatus;
        $digest['probable_issue'] = 'airline_segment_status_uc';
        $digest['retry_blocker_reasons'] = [
            'airline_segment_status_uc',
            'halt_on_status_received',
            'choose_alternate_itinerary',
        ];

        return $digest;
    }

    /**
     * @param  array<string, mixed>  $probe
     */
    /**
     * Passenger Records HTTP 200 without PNR: Incomplete / NotProcessed {@code ApplicationResults} status.
     *
     * @param  array<string, mixed>  $probe
     */
    protected function probeIndicatesPassengerRecordsIncompleteOrNotProcessed(string $appStatus, array $probe): bool
    {
        if (in_array(strtolower(trim($appStatus)), ['incomplete', 'notprocessed'], true)) {
            return true;
        }

        return ($probe['application_results_incomplete'] ?? false) === true;
    }

    /**
     * Passenger Records HTTP 200 without PNR: application-level booking failure (provider error, host warning, air-book step).
     *
     * @param  array<string, mixed>  $probe
     */
    protected function probeIndicatesPassengerRecordsApplicationBookingFailure(array $probe): bool
    {
        $codes = array_map('strtoupper', array_map('strval', array_merge(
            (array) ($probe['response_error_codes'] ?? []),
            (array) ($probe['host_warning_sabre_codes'] ?? []),
        )));
        if (in_array('ERR.SP.PROVIDER_ERROR', $codes, true)) {
            return true;
        }

        $messagesUpper = strtoupper(implode(' ', array_map('strval', array_merge(
            (array) ($probe['response_error_messages'] ?? []),
            (array) ($probe['host_warning_messages_truncated'] ?? []),
        ))));
        if (str_contains($messagesUpper, 'CHECK FLIGHT NUMBER')
            || str_contains($messagesUpper, 'UNABLE TO PERFORM AIR BOOKING STEP')) {
            return true;
        }

        if (in_array('WARN.SWS.HOST.ERROR_IN_RESPONSE', $codes, true)
            && (str_contains($messagesUpper, 'CHECK FLIGHT NUMBER')
                || str_contains($messagesUpper, 'ENHANCEDAIRBOOKRQ'))) {
            return true;
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $probe
     */
    protected function safeMessageForPassengerRecordsApplicationBookingFailure(array $probe, bool $incompleteOrNotProcessed): string
    {
        $messagesUpper = strtoupper(implode(' ', array_map('strval', array_merge(
            (array) ($probe['response_error_messages'] ?? []),
            (array) ($probe['host_warning_messages_truncated'] ?? []),
        ))));
        if (str_contains($messagesUpper, 'CHECK FLIGHT NUMBER')) {
            return 'Sabre Passenger Records returned CHECK FLIGHT NUMBER without a PNR locator. Staff review required.';
        }
        if ($incompleteOrNotProcessed) {
            return 'Sabre Passenger Records returned Incomplete or NotProcessed without a PNR locator. Staff review required.';
        }

        return 'Sabre Passenger Records returned application-level booking failure without a PNR locator. Staff review required.';
    }

    /**
     * F9G: Safe ApplicationResults digest for Incomplete/NotProcessed application failures (no raw body).
     *
     * @param  array<string, mixed>  $arr
     * @return array<string, mixed>
     */
    protected function passengerRecordsApplicationDigestFromResponse(array $arr): array
    {
        return app(SabrePassengerRecordsApplicationResultDigest::class)->digest($arr);
    }

    protected function probeIndicatesHostSegmentNnHalt(array $probe): bool
    {
        return ($probe['probable_issue'] ?? '') === 'airline_segment_status_nn_halt';
    }

    /**
     * @param  array<string, mixed>  $probe
     */
    protected function probeIndicatesUnconfirmedSegmentNnAfterPnrCreate(array $probe): bool
    {
        $status = strtoupper(trim((string) ($probe['airline_segment_status'] ?? '')));
        if ($status === 'NN') {
            return true;
        }

        $messagesUpper = strtoupper(implode(' ', array_map('strval', (array) ($probe['response_error_messages'] ?? []))));

        return str_contains($messagesUpper, 'STATUS CODE NN');
    }

    /**
     * @param  array<string, mixed>  $probe
     */
    protected function probeIndicatesConfirmedSegmentStatus(array $probe): bool
    {
        $status = strtoupper(trim((string) ($probe['airline_segment_status'] ?? '')));
        if (in_array($status, ['HK', 'SS'], true)) {
            return true;
        }

        $messagesUpper = strtoupper(implode(' ', array_map('strval', (array) ($probe['response_error_messages'] ?? []))));

        return str_contains($messagesUpper, 'STATUS CODE HK')
            || str_contains($messagesUpper, 'STATUS CODE SS');
    }

    protected function probeIndicatesHostSegmentSellReject(array $probe): bool
    {
        if ($this->probeIndicatesHostSegmentNnHalt($probe)) {
            return false;
        }
        if (($probe['probable_issue'] ?? '') === 'airline_segment_status_uc') {
            return true;
        }
        if (($probe['airline_segment_status'] ?? '') === 'UC') {
            return true;
        }

        return ($probe['halt_on_status_received'] ?? false) === true;
    }

    /**
     * B57: Passenger Records HTTP 200 + {@code ApplicationResults=Incomplete}: extract host LLSRQ/RQ module tokens and
     * redact long digit runs from warning lines (no raw bodies).
     *
     * @param  array<string, mixed>  $digest
     * @return array<string, mixed>
     */
    protected function enrichPassengerRecordsProbeDigestHostWarnings(array $digest): array
    {
        $appStatus = isset($digest['application_results_status']) && is_string($digest['application_results_status'])
            ? trim($digest['application_results_status'])
            : '';
        $digest['application_results_incomplete'] = in_array(strtolower($appStatus), ['incomplete', 'notprocessed'], true);

        $messages = (array) ($digest['response_error_messages'] ?? []);
        $moduleHits = [];
        $truncMsgs = [];
        $sabreCodes = [];

        foreach ($messages as $m) {
            if (! is_string($m) || trim($m) === '') {
                continue;
            }
            $line = $this->redactHostWarningLineForDigest($m);
            if ($line !== '') {
                $truncMsgs[] = self::truncateSafeString($line, 220);
            }
            if (preg_match_all('/(?P<mod>[A-Za-z][A-Za-z0-9]*LLSRQ|EnhancedAirBookRQ)\s*:/', $m, $mm)) {
                foreach ($mm['mod'] as $mod) {
                    $tok = self::truncateSafeString(trim((string) $mod), 80);
                    if ($tok !== '') {
                        $moduleHits[$tok] = true;
                    }
                }
            }
            if (preg_match_all('/\bERR\.[A-Z0-9_.]+\b/', $m, $ec)) {
                foreach ($ec[0] as $c) {
                    $sabreCodes[self::truncateSafeString($c, 80)] = true;
                }
            }
        }

        $digest['host_warning_modules'] = array_slice(array_keys($moduleHits), 0, 16);
        sort($digest['host_warning_modules']);
        $digest['host_warning_messages_truncated'] = array_values(array_unique(array_slice($truncMsgs, 0, 16)));
        $digest['host_warning_sabre_codes'] = array_values(array_unique(array_slice(array_keys($sabreCodes), 0, 16)));
        sort($digest['host_warning_sabre_codes']);

        return $digest;
    }

    /**
     * Strip long digit runs (phones/ids) and bracketed emails from a single diagnostic line.
     */
    protected function redactHostWarningLineForDigest(string $line): string
    {
        $s = preg_replace('/\d{7,}/', '', $line) ?? $line;
        $s = preg_replace('/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}\b/', '[email]', $s) ?? $s;

        return trim($s);
    }

    /**
     * B44/B45: Extract capped diagnostic tokens from Sabre Passenger Records / CPNR-style error envelopes (incl. REST top-level {@code errorCode}/{@code message}/{@code status}/{@code type}).
     *
     * @param  array<string, mixed>  $json
     * @return array{
     *   pnr_codes: list<string>,
     *   pnr_messages: list<string>,
     *   pnr_fields: list<string>,
     *   pnr_paths: list<string>,
     *   application_results_status: ?string,
     *   passenger_records_error_digest_present: bool,
     *   pnr_top_level_error_code: ?string,
     *   pnr_top_level_message: ?string,
     *   pnr_top_level_status: ?string,
     *   pnr_top_level_type: ?string,
     *   pnr_timestamp_present: bool
     * }
     */
    protected function parsePassengerRecordsStyleSafeDigest(array $json): array
    {
        $codes = [];
        $messages = [];
        $fields = [];
        $paths = [];
        $applicationStatus = null;
        $foundApplicationResults = false;

        $prTopCode = null;
        $prTopMessage = null;
        $prTopStatus = null;
        $prTopType = null;
        foreach (['errorCode', 'ErrorCode', 'error_code'] as $eck) {
            if (! array_key_exists($eck, $json)) {
                continue;
            }
            $ev = $json[$eck];
            if ($ev === null || $ev === false || $ev === '') {
                continue;
            }
            if (is_string($ev) && trim($ev) !== '') {
                $prTopCode = self::truncateSafeString(trim($ev), 120);
                $codes[] = $prTopCode;
                break;
            }
            if (is_int($ev) || is_float($ev)) {
                $prTopCode = self::truncateSafeString((string) $ev, 120);
                $codes[] = $prTopCode;
                break;
            }
        }
        foreach (['message', 'Message'] as $mk) {
            if (! isset($json[$mk]) || ! is_string($json[$mk]) || trim($json[$mk]) === '') {
                continue;
            }
            $prTopMessage = self::truncateSafeString(trim($json[$mk]));
            $messages[] = $prTopMessage;
            break;
        }
        foreach (['status', 'Status'] as $sk) {
            if (isset($json[$sk]) && is_string($json[$sk]) && trim($json[$sk]) !== '') {
                $prTopStatus = self::truncateSafeString(trim($json[$sk]), 64);
                break;
            }
        }
        foreach (['type', 'Type'] as $tk) {
            if (isset($json[$tk]) && is_string($json[$tk]) && trim($json[$tk]) !== '') {
                $prTopType = self::truncateSafeString(trim($json[$tk]), 120);
                break;
            }
        }
        $prTsPresent = array_key_exists('timeStamp', $json) || array_key_exists('timestamp', $json);
        $hasPrTopEnvelope = $prTopCode !== null || $prTopMessage !== null || $prTopStatus !== null || $prTopType !== null;

        $candidates = [
            $json,
            is_array($json['CreatePassengerNameRecordRS'] ?? null) ? $json['CreatePassengerNameRecordRS'] : null,
            is_array($json['createPassengerNameRecordRS'] ?? null) ? $json['createPassengerNameRecordRS'] : null,
        ];
        foreach ($candidates as $node) {
            if (! is_array($node)) {
                continue;
            }
            foreach (['ApplicationResults', 'applicationResults'] as $arKey) {
                $ar = $node[$arKey] ?? null;
                if (! is_array($ar)) {
                    continue;
                }
                $foundApplicationResults = true;
                if ($applicationStatus === null) {
                    $st = $ar['status'] ?? $ar['Status'] ?? null;
                    if (is_string($st) && trim($st) !== '') {
                        $applicationStatus = self::truncateSafeString(trim($st), 64);
                    }
                }
                $this->ingestApplicationResultsBlock($ar, $codes, $messages, $fields, $paths);
            }
        }

        $capE = $json['Error'] ?? null;
        if (is_array($capE)) {
            $ec = $capE['ErrorCode'] ?? $capE['errorCode'] ?? null;
            if (is_string($ec) && trim($ec) !== '') {
                $codes[] = self::truncateSafeString(trim($ec));
            }
            $em = $capE['Message'] ?? $capE['message'] ?? null;
            if (is_string($em) && trim($em) !== '') {
                $messages[] = self::truncateSafeString(trim($em));
            }
            $this->collectTripOrdersErrorPathHintsFromRow($capE, $paths);
        }

        $details = $json['details'] ?? null;
        if (is_string($details) && trim($details) !== '') {
            $messages[] = self::truncateSafeString(trim($details));
        } elseif (is_array($details)) {
            $flat = $this->flattenMixedForSafeDigest($details);
            if ($flat !== '') {
                $messages[] = self::truncateSafeString($flat);
            }
        }

        $val = $json['validationErrors'] ?? null;
        if (is_array($val)) {
            foreach (array_slice($val, 0, 16) as $ve) {
                if (is_array($ve)) {
                    $this->collectTripOrdersErrorPathHintsFromRow($ve, $paths);
                    $this->collectPassengerRecordsFieldPathHints($ve, $fields, $paths);
                    foreach (['message', 'Message', 'description', 'detail'] as $vk) {
                        if (isset($ve[$vk]) && is_string($ve[$vk]) && trim($ve[$vk]) !== '') {
                            $messages[] = self::truncateSafeString(trim($ve[$vk]));
                        }
                    }
                }
            }
        }

        $codes = array_values(array_unique(array_slice($codes, 0, 32)));
        $messages = array_values(array_unique(array_slice($messages, 0, 32)));
        $fields = array_values(array_unique(array_slice($fields, 0, 32)));
        $paths = array_values(array_unique(array_slice($paths, 0, 48)));

        $digestPresent = $foundApplicationResults
            || $applicationStatus !== null
            || $codes !== []
            || $messages !== []
            || $hasPrTopEnvelope
            || isset($json['CreatePassengerNameRecordRS'])
            || isset($json['createPassengerNameRecordRS']);

        return [
            'pnr_codes' => $codes,
            'pnr_messages' => $messages,
            'pnr_fields' => $fields,
            'pnr_paths' => $paths,
            'application_results_status' => $applicationStatus,
            'passenger_records_error_digest_present' => $digestPresent,
            'pnr_top_level_error_code' => $prTopCode,
            'pnr_top_level_message' => $prTopMessage,
            'pnr_top_level_status' => $prTopStatus,
            'pnr_top_level_type' => $prTopType,
            'pnr_timestamp_present' => $prTsPresent,
        ];
    }

    /**
     * @param  array<string, mixed>  $ar  ApplicationResults node
     * @param  list<string>  $codes
     * @param  list<string>  $messages
     * @param  list<string>  $fields
     * @param  list<string>  $paths
     */
    protected function ingestApplicationResultsBlock(array $ar, array &$codes, array &$messages, array &$fields, array &$paths): void
    {
        foreach (['Error', 'Errors', 'error', 'errors'] as $ek) {
            $errRaw = $ar[$ek] ?? null;
            $errList = match (true) {
                $errRaw === null => [],
                is_array($errRaw) && array_is_list($errRaw) => $errRaw,
                is_array($errRaw) => [$errRaw],
                default => [],
            };
            foreach (array_slice($errList, 0, 24) as $errRow) {
                if (! is_array($errRow)) {
                    continue;
                }
                $this->collectTripOrdersErrorPathHintsFromRow($errRow, $paths);
                $this->collectPassengerRecordsFieldPathHints($errRow, $fields, $paths);
                $ssRaw = $errRow['SystemSpecificResults'] ?? $errRow['systemSpecificResults'] ?? null;
                $ssList = match (true) {
                    $ssRaw === null => [],
                    is_array($ssRaw) && array_is_list($ssRaw) => $ssRaw,
                    is_array($ssRaw) => [$ssRaw],
                    default => [],
                };
                foreach (array_slice($ssList, 0, 16) as $ssr) {
                    if (! is_array($ssr)) {
                        continue;
                    }
                    $this->collectTripOrdersErrorPathHintsFromRow($ssr, $paths);
                    $this->collectPassengerRecordsFieldPathHints($ssr, $fields, $paths);
                    foreach (['ShortText', 'shortText', 'Element', 'element'] as $sk) {
                        if (isset($ssr[$sk]) && is_string($ssr[$sk]) && trim($ssr[$sk]) !== '') {
                            $fields[] = self::truncateSafeString(trim($ssr[$sk]), 200);
                        }
                    }
                    $msgRaw = $ssr['Message'] ?? $ssr['message'] ?? null;
                    $msgList = match (true) {
                        $msgRaw === null => [],
                        is_array($msgRaw) && array_is_list($msgRaw) => $msgRaw,
                        is_array($msgRaw) => [$msgRaw],
                        default => [],
                    };
                    foreach (array_slice($msgList, 0, 24) as $mrow) {
                        $this->appendSabreApplicationMessageRow($mrow, $codes, $messages);
                    }
                }
            }
        }

        foreach (['Warning', 'Warnings', 'warning', 'warnings'] as $wk) {
            $wRaw = $ar[$wk] ?? null;
            $wList = match (true) {
                $wRaw === null => [],
                is_array($wRaw) && array_is_list($wRaw) => $wRaw,
                is_array($wRaw) => [$wRaw],
                default => [],
            };
            foreach (array_slice($wList, 0, 16) as $wrow) {
                if (! is_array($wrow)) {
                    continue;
                }
                $this->collectTripOrdersErrorPathHintsFromRow($wrow, $paths);
                $ssRaw = $wrow['SystemSpecificResults'] ?? $wrow['systemSpecificResults'] ?? null;
                $ssList = match (true) {
                    $ssRaw === null => [],
                    is_array($ssRaw) && array_is_list($ssRaw) => $ssRaw,
                    is_array($ssRaw) => [$ssRaw],
                    default => [],
                };
                foreach (array_slice($ssList, 0, 12) as $ssr) {
                    if (! is_array($ssr)) {
                        continue;
                    }
                    $msgRaw = $ssr['Message'] ?? $ssr['message'] ?? null;
                    $msgList = match (true) {
                        $msgRaw === null => [],
                        is_array($msgRaw) && array_is_list($msgRaw) => $msgRaw,
                        is_array($msgRaw) => [$msgRaw],
                        default => [],
                    };
                    foreach (array_slice($msgList, 0, 12) as $mrow) {
                        $this->appendSabreApplicationMessageRow($mrow, $codes, $messages, '[Warning] ');
                    }
                }
            }
        }
    }

    /**
     * @param  list<string>  $codes
     * @param  list<string>  $messages
     */
    protected function appendSabreApplicationMessageRow(mixed $msg, array &$codes, array &$messages, string $prefix = ''): void
    {
        if (! is_array($msg)) {
            return;
        }
        $code = trim((string) ($msg['code'] ?? $msg['Code'] ?? ''));
        $textParts = [];
        foreach (['content', 'Content', 'value', 'Value', '_', 'text', 'Text', 'message', 'Message'] as $tk) {
            if (! isset($msg[$tk])) {
                continue;
            }
            $v = $msg[$tk];
            if (is_string($v) && trim($v) !== '') {
                $textParts[] = self::truncateSafeString(trim($v));
            } elseif (is_array($v)) {
                $flat = $this->flattenMixedForSafeDigest($v);
                if ($flat !== '') {
                    $textParts[] = self::truncateSafeString($flat);
                }
            }
        }
        if ($code !== '') {
            $codes[] = self::truncateSafeString($code);
        }
        if ($textParts !== []) {
            $body = $textParts[0];
            $line = $code !== '' ? $code.' — '.$body : $body;
            $messages[] = self::truncateSafeString($prefix.$line);
        } elseif ($code !== '') {
            $messages[] = self::truncateSafeString($prefix.$code);
        }
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  list<string>  $fields
     * @param  list<string>  $paths
     */
    protected function collectPassengerRecordsFieldPathHints(array $row, array &$fields, array &$paths): void
    {
        foreach (['propertyPath', 'jsonPath', 'field', 'path', 'parameter', 'invalidField', 'invalid_field'] as $k) {
            if (! isset($row[$k])) {
                continue;
            }
            $v = $row[$k];
            if (is_string($v) && trim($v) !== '') {
                $tok = self::truncateSafeString(trim($v), 200);
                if (in_array($k, ['field', 'path', 'parameter', 'invalidField', 'invalid_field'], true)) {
                    $fields[] = $tok;
                }
                $paths[] = $tok;
            }
        }
    }

    /**
     * @param  array<string, mixed>  $trip  Output of {@see parseTripOrdersCreateBookingSafeDigest}
     * @param  array<string, mixed>  $pnr  Output of {@see parsePassengerRecordsStyleSafeDigest}
     * @return array<string, mixed>
     */
    protected function mergeTripOrdersAndPassengerRecordProbeDigests(array $trip, array $pnr): array
    {
        $codes = array_values(array_unique(array_merge(
            (array) ($trip['response_error_codes'] ?? []),
            (array) ($pnr['pnr_codes'] ?? []),
        )));
        $messages = array_values(array_unique(array_merge(
            (array) ($trip['response_error_messages'] ?? []),
            (array) ($pnr['pnr_messages'] ?? []),
        )));
        $fieldHints = array_values(array_unique(array_merge(
            (array) ($trip['response_error_fields'] ?? []),
            (array) ($pnr['pnr_fields'] ?? []),
        )));
        $pathHints = array_values(array_unique(array_merge(
            (array) ($trip['response_error_paths'] ?? []),
            (array) ($pnr['pnr_paths'] ?? []),
        )));

        $codes = array_slice($codes, 0, 32);
        $messages = array_slice($messages, 0, 32);
        $fieldHints = array_slice($fieldHints, 0, 32);
        $pathHints = array_slice($pathHints, 0, 56);

        $out = $trip;
        $out['response_error_codes'] = $codes;
        $out['response_error_messages'] = $messages;
        $out['response_error_fields'] = $fieldHints;
        $out['response_error_paths'] = $pathHints;
        $out['application_results_status'] = $pnr['application_results_status'] ?? null;
        $out['passenger_records_error_digest_present'] = (bool) ($pnr['passenger_records_error_digest_present'] ?? false);

        $pick = static function (?string $primary, ?string $fallback): ?string {
            $p = $primary !== null && trim($primary) !== '' ? trim($primary) : '';

            return $p !== '' ? $primary : (($fallback !== null && trim($fallback) !== '') ? $fallback : null);
        };
        $out['response_top_level_error_code'] = $pick(
            isset($trip['response_top_level_error_code']) && is_string($trip['response_top_level_error_code']) ? $trip['response_top_level_error_code'] : null,
            isset($pnr['pnr_top_level_error_code']) && is_string($pnr['pnr_top_level_error_code']) ? $pnr['pnr_top_level_error_code'] : null,
        );
        $out['response_top_level_message'] = $pick(
            isset($trip['response_top_level_message']) && is_string($trip['response_top_level_message']) ? $trip['response_top_level_message'] : null,
            isset($pnr['pnr_top_level_message']) && is_string($pnr['pnr_top_level_message']) ? $pnr['pnr_top_level_message'] : null,
        );
        $out['response_top_level_status'] = $pick(
            isset($trip['response_top_level_status']) && is_string($trip['response_top_level_status']) ? $trip['response_top_level_status'] : null,
            isset($pnr['pnr_top_level_status']) && is_string($pnr['pnr_top_level_status']) ? $pnr['pnr_top_level_status'] : null,
        );
        $out['response_top_level_type'] = $pick(
            isset($trip['response_top_level_type']) && is_string($trip['response_top_level_type']) ? $trip['response_top_level_type'] : null,
            isset($pnr['pnr_top_level_type']) && is_string($pnr['pnr_top_level_type']) ? $pnr['pnr_top_level_type'] : null,
        );
        $tripTsVal = $trip['timestamp'] ?? null;
        $tripHasDigestTimestamp = is_string($tripTsVal) && trim($tripTsVal) !== '';
        $out['response_timestamp_present'] = $tripHasDigestTimestamp || (bool) ($pnr['pnr_timestamp_present'] ?? false);

        $tripCount = (int) ($trip['response_error_count'] ?? 0);
        $mergedCount = max($tripCount, count($codes), count($messages));
        $out['response_error_count'] = $mergedCount > 0 ? $mergedCount : $tripCount;

        return $out;
    }

    public function extractPnrLocatorFromBookingJson(array $json): string
    {
        return $this->extractSabrePnrLocator($json);
    }

    public function extractSupplierOrderReferenceFromBookingJson(array $json): string
    {
        return $this->extractSabreSupplierOrderId($json);
    }
}
