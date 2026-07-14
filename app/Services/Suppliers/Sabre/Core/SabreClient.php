<?php

namespace App\Services\Suppliers\Sabre\Core;

use App\Data\FlightSearchRequestData;
use App\Models\SupplierConnection;
use App\Services\Suppliers\Sabre\SabreFlightSearchRequestBuilder;
use App\Services\Suppliers\Sabre\SabreRevalidationPayloadBuilder;
use App\Services\Suppliers\SupplierDiagnosticLogger;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class SabreClient
{
    public function __construct(
        protected SabreFlightSearchRequestBuilder $requestBuilder,
        protected SupplierDiagnosticLogger $diagnosticLogger,
        protected SabreRevalidationPayloadBuilder $revalidationPayloadBuilder,
    ) {}

    /**
     * Resolved HTTPS host and path for a Sabre REST suffix (e.g. booking or token path).
     *
     * @return array{endpoint_host: string, endpoint_path: string}
     */
    public function resolveEndpointParts(SupplierConnection $connection, string $pathSuffix): array
    {
        $path = $pathSuffix !== '' && $pathSuffix[0] === '/' ? $pathSuffix : '/'.$pathSuffix;

        return $this->endpointParts($connection, $path);
    }

    /**
     * @return array{timeout_seconds: int, connect_timeout_seconds: int}
     */
    public function httpTimeoutSettings(): array
    {
        return [
            'timeout_seconds' => (int) config('suppliers.sabre.timeout_seconds', 30),
            'connect_timeout_seconds' => (int) config('suppliers.sabre.connect_timeout_seconds', 10),
        ];
    }

    /**
     * @return array{endpoint_host: string, endpoint_path: string}
     */
    protected function endpointParts(SupplierConnection $connection, string $pathSuffix): array
    {
        $base = $this->resolveBaseUrl($connection);
        $host = parse_url(str_contains($base, '://') ? $base : 'https://'.$base, PHP_URL_HOST);
        $path = $pathSuffix !== '' && $pathSuffix[0] === '/' ? $pathSuffix : '/'.$pathSuffix;

        return [
            'endpoint_host' => is_string($host) && $host !== '' ? $host : 'unknown',
            'endpoint_path' => $path,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $json
     * @return array<string, string>
     */
    protected function safeOAuthBodySnippet(?array $json): array
    {
        if (! is_array($json)) {
            return [];
        }
        $out = [];
        if (isset($json['error'])) {
            $out['oauth_error'] = substr((string) $json['error'], 0, 80);
        }
        if (isset($json['error_description'])) {
            $out['oauth_error_description'] = substr((string) $json['error_description'], 0, 200);
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>|null  $json
     * @return array<string, string>
     */
    protected function safeSabreErrorsSnippet(?array $json): array
    {
        if (! is_array($json)) {
            return [];
        }
        if (isset($json['message']) && is_string($json['message'])) {
            return ['provider_message' => substr($json['message'], 0, 200)];
        }
        $errors = $json['errors'] ?? null;
        if (is_array($errors) && isset($errors[0]) && is_array($errors[0])) {
            $first = $errors[0];
            $parts = [];
            foreach (['code', 'title', 'type', 'detail', 'status'] as $k) {
                if (isset($first[$k])) {
                    $parts[$k] = substr((string) $first[$k], 0, 120);
                }
            }

            return $parts !== [] ? $parts : [];
        }

        return [];
    }

    public function getAccessToken(SupplierConnection $connection): string
    {
        $cacheKey = 'sabre:token:connection:'.$connection->id;
        $cached = Cache::get($cacheKey);
        if (is_string($cached) && $cached !== '') {
            Log::info('sabre.auth.token_cache_hit', [
                'provider' => 'sabre',
                'connection_id' => $connection->id,
                'cache_key' => $cacheKey,
            ]);

            return $cached;
        }

        Log::info('sabre.auth.token_cache_miss', [
            'provider' => 'sabre',
            'connection_id' => $connection->id,
            'cache_key' => $cacheKey,
        ]);

        $credentials = is_array($connection->credentials) ? $connection->credentials : [];
        $clientId = (string) ($credentials['client_id'] ?? '');
        $clientSecret = (string) ($credentials['client_secret'] ?? '');
        $hasOAuthPair = $clientId !== '' && $clientSecret !== '';
        $presenceMeta = $this->eprCredentialPresenceMeta($connection);
        $eprParts = $this->resolveEprCredentialParts($connection);
        $explicitEpr = $this->hasExplicitEprCredentialTriple($connection);

        if (! $hasOAuthPair && $eprParts === null) {
            throw new RuntimeException('Sabre credentials are missing.');
        }

        $tokenPath = (string) config('suppliers.sabre.token_path', '/v2/auth/token');
        $parts = $this->endpointParts($connection, $tokenPath);
        $started = microtime(true);
        $tokenUrl = $this->resolveBaseUrl($connection).$tokenPath;
        $hostClass = $this->resolveHostClass($parts['endpoint_host']);

        $plannedStrategy = $explicitEpr && $eprParts !== null
            ? 'sabre_epr_encoded'
            : ($hasOAuthPair ? 'basic' : 'sabre_epr_encoded');

        Log::info('sabre.auth.source_resolved', array_merge([
            'provider' => 'sabre',
            'connection_id' => $connection->id,
            'credential_source' => 'db',
            'auth_strategy_planned' => $plannedStrategy,
            'endpoint_host' => $parts['endpoint_host'],
            'endpoint_path' => $parts['endpoint_path'],
            'environment' => $connection->environment->value,
            'explicit_epr_present' => $explicitEpr,
            'oauth_pair_present' => $hasOAuthPair,
            'resolved_host_class' => $hostClass,
        ], $presenceMeta));

        $response = null;
        $authStrategy = $plannedStrategy;

        if ($explicitEpr && $eprParts !== null) {
            $response = $this->attemptEprTokenRequest($connection, $tokenUrl, $eprParts, $parts, $started, $presenceMeta);
            $authStrategy = 'sabre_epr_encoded';
        } elseif ($hasOAuthPair) {
            try {
                $response = $this->sendSabreTokenRequest($tokenUrl, $clientId, $clientSecret, 'basic');
            } catch (ConnectionException $exception) {
                $this->logSabreTokenConnectionFailure($connection, $parts, $started, 'basic', $presenceMeta);

                throw new RuntimeException('Sabre authentication failed.', 0, $exception);
            }

            if (in_array($response->status(), [400, 401], true)) {
                try {
                    $response = $this->sendSabreTokenRequest($tokenUrl, $clientId, $clientSecret, 'form');
                    $authStrategy = 'form';
                } catch (ConnectionException $exception) {
                    $this->logSabreTokenConnectionFailure($connection, $parts, $started, 'form', $presenceMeta);

                    throw new RuntimeException('Sabre authentication failed.', 0, $exception);
                }
            }
        }

        $shouldTryEpr = $eprParts !== null
            && ! ($explicitEpr && $eprParts !== null)
            && (
                ! $hasOAuthPair
                || ($response !== null && $response->status() === 401)
            );

        if ($shouldTryEpr) {
            $response = $this->attemptEprTokenRequest($connection, $tokenUrl, $eprParts, $parts, $started, $presenceMeta);
            $authStrategy = 'sabre_epr_encoded';
        }

        if ($response === null) {
            throw new RuntimeException('Sabre credentials are missing.');
        }

        $durationMs = (int) round((microtime(true) - $started) * 1000);

        if (! $response->successful()) {
            $json = $response->json();
            $meta = array_merge($parts, $presenceMeta, [
                'reason_code' => 'sabre_token_failed',
                'auth_strategy' => $authStrategy,
                'http_status' => $response->status(),
                'environment' => $connection->environment->value,
                'connection_id' => $connection->id,
                'duration_ms' => $durationMs,
                'credential_source' => 'db',
                'resolved_host_class' => $hostClass,
            ], $this->safeOAuthBodySnippet(is_array($json) ? $json : null));

            $this->diagnosticLogger->log(
                connection: $connection,
                action: 'authenticate',
                status: 'failed',
                durationMs: $durationMs,
                safeMessage: 'Sabre token HTTP error.',
                meta: $meta,
            );
            Log::warning('sabre.authenticate.http_failed', array_merge(['provider' => 'sabre'], $meta));
            Log::warning('sabre.auth.http_failed_safe', array_merge(['provider' => 'sabre'], $meta));

            throw new RuntimeException('Sabre authentication failed.');
        }

        $responseJson = $response->json();
        $token = (string) data_get($responseJson, 'access_token', '');
        $expiresIn = (int) data_get($responseJson, 'expires_in', 1800);
        if ($token === '') {
            $meta = array_merge($parts, $presenceMeta, [
                'reason_code' => 'sabre_provider_error',
                'auth_strategy' => $authStrategy,
                'http_status' => $response->status(),
                'environment' => $connection->environment->value,
                'connection_id' => $connection->id,
                'duration_ms' => $durationMs,
            ]);

            $this->diagnosticLogger->log(
                connection: $connection,
                action: 'authenticate',
                status: 'failed',
                durationMs: $durationMs,
                safeMessage: 'Sabre token response missing access_token.',
                meta: $meta,
            );
            Log::warning('sabre.authenticate.malformed', array_merge(['provider' => 'sabre'], $meta));

            throw new RuntimeException('Sabre authentication response is malformed.');
        }

        Cache::put($cacheKey, $token, max(60, $expiresIn - 60));

        $successMeta = array_merge($parts, $presenceMeta, [
            'http_status' => $response->status(),
            'auth_strategy' => $authStrategy,
            'environment' => $connection->environment->value,
            'connection_id' => $connection->id,
            'duration_ms' => $durationMs,
            'pcc_sent_in_shop_request' => $this->includesPccInShopRequest($connection),
        ]);

        $this->diagnosticLogger->log(
            connection: $connection,
            action: 'authenticate',
            status: 'success',
            durationMs: $durationMs,
            safeMessage: 'Sabre token obtained.',
            meta: $successMeta,
        );
        Log::info('sabre.authenticate.success', array_merge(['provider' => 'sabre'], $successMeta));

        return $token;
    }

    /**
     * True when OAuth client id/secret are set or full EPR (PCC + sign-in or client_id + password) material exists.
     */
    public function connectionHasTokenCredentials(SupplierConnection $connection): bool
    {
        $credentials = is_array($connection->credentials) ? $connection->credentials : [];
        $clientId = (string) ($credentials['client_id'] ?? '');
        $clientSecret = (string) ($credentials['client_secret'] ?? '');
        $hasOAuthPair = $clientId !== '' && $clientSecret !== '';

        return $hasOAuthPair || $this->resolveEprCredentialParts($connection) !== null;
    }

    /**
     * POST /v2/auth/token: Basic strategy sends only grant_type in the body; form strategy sends legacy body fields.
     *
     * @param  'basic'|'form'  $strategy
     *
     * @throws ConnectionException
     */
    protected function sendSabreTokenRequest(string $tokenUrl, string $clientId, string $clientSecret, string $strategy): Response
    {
        $pending = Http::withHeaders([
            'Accept' => 'application/json',
        ])
            ->asForm()
            ->timeout((int) config('suppliers.sabre.timeout_seconds', 30))
            ->connectTimeout((int) config('suppliers.sabre.connect_timeout_seconds', 10))
            ->retry(1, 300, fn ($exception): bool => $exception instanceof ConnectionException);

        if ($strategy === 'basic') {
            return $pending
                ->withBasicAuth($clientId, $clientSecret)
                ->post($tokenUrl, ['grant_type' => 'client_credentials']);
        }

        return $pending->post($tokenUrl, [
            'grant_type' => 'client_credentials',
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
        ]);
    }

    /**
     * @throws ConnectionException
     */
    protected function sendSabreTokenRequestWithEncodedBasic(string $tokenUrl, string $basicAuthorizationPayload): Response
    {
        return Http::withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Basic '.$basicAuthorizationPayload,
        ])
            ->asForm()
            ->timeout((int) config('suppliers.sabre.timeout_seconds', 30))
            ->connectTimeout((int) config('suppliers.sabre.connect_timeout_seconds', 10))
            ->retry(1, 300, fn ($exception): bool => $exception instanceof ConnectionException)
            ->post($tokenUrl, ['grant_type' => 'client_credentials']);
    }

    /**
     * @param  array{pcc_present: bool, sign_in_present: bool, password_present: bool}  $presenceMeta
     */
    protected function logSabreTokenConnectionFailure(SupplierConnection $connection, array $parts, float $started, string $authStrategy, array $presenceMeta = []): void
    {
        $durationMs = (int) round((microtime(true) - $started) * 1000);
        $meta = array_merge($parts, $presenceMeta, [
            'reason_code' => 'sabre_timeout',
            'http_status' => null,
            'environment' => $connection->environment->value,
            'connection_id' => $connection->id,
            'duration_ms' => $durationMs,
            'auth_strategy' => $authStrategy,
        ]);
        $this->diagnosticLogger->log(
            connection: $connection,
            action: 'authenticate',
            status: 'failed',
            durationMs: $durationMs,
            safeMessage: 'Sabre token network timeout or connection error.',
            meta: $meta,
        );
        Log::warning('sabre.authenticate.timeout', array_merge(['provider' => 'sabre'], $meta));
    }

    /**
     * Configured Offers Shop path (e.g. /v4/offers/shop).
     */
    protected function shopRequestPath(): string
    {
        return (string) config('suppliers.sabre.shop_path', '/v4/offers/shop');
    }

    /**
     * POST pre-built shop JSON to Sabre (same URL and transport as {@see searchFlights}, no retry).
     * Used by local inspect tooling; returns the HTTP response and does not throw on error status codes.
     *
     * @param  array<string, mixed>  $payload
     */
    public function postShopPayload(SupplierConnection $connection, array $payload): Response
    {
        $shopPath = $this->shopRequestPath();
        $token = $this->getAccessToken($connection);
        $url = $this->resolveBaseUrl($connection).$shopPath;

        return Http::withToken($token)
            ->timeout((int) config('suppliers.sabre.timeout_seconds', 30))
            ->connectTimeout((int) config('suppliers.sabre.connect_timeout_seconds', 10))
            ->post($url, $payload);
    }

    /**
     * Authenticated JSON POST to Sabre revalidation path (default `/v4/shop/flights/revalidate`) used before Trip Orders
     * {@code createBooking} to acquire fare/offer linkage. Same auth/timeouts as shop; logs only safe metadata
     * (endpoint_path, http_status, duration_ms, segment_count, has_booking_class, has_fare_basis, has_offer_reference).
     * Never logs Authorization header, raw payload, or full provider response.
     *
     * @param  array<string, mixed>  $payload  Sanitized revalidation envelope (see {@see SabreRevalidationPayloadBuilder})
     * @param  ?string  $pathOverride  Local/testing only: POST to this path instead of {@code config('suppliers.sabre.revalidate_path')} (must start with {@code /})
     */
    public function postRevalidatePayload(SupplierConnection $connection, array $payload, ?string $pathOverride = null): Response
    {
        $configured = (string) config('suppliers.sabre.revalidate_path', '/v4/shop/flights/revalidate');
        $configured = $configured !== '' && $configured[0] === '/' ? $configured : '/'.$configured;
        $path = $pathOverride !== null && trim($pathOverride) !== ''
            ? trim($pathOverride)
            : $configured;
        $path = $path !== '' && $path[0] === '/' ? $path : '/'.$path;
        $parts = $this->endpointParts($connection, $path);
        $token = $this->getAccessToken($connection);
        $url = $this->resolveBaseUrl($connection).$path;
        $started = microtime(true);

        $wire = $this->revalidationPayloadBuilder->wireableRequestPayload($payload);

        $response = Http::withToken($token)
            ->acceptJson()
            ->asJson()
            ->timeout((int) config('suppliers.sabre.timeout_seconds', 30))
            ->connectTimeout((int) config('suppliers.sabre.connect_timeout_seconds', 10))
            ->retry(0)
            ->post($url, $wire);

        $durationMs = (int) round((microtime(true) - $started) * 1000);
        $segments = data_get($wire, 'OTA_AirLowFareSearchRQ.OriginDestinationInformation');
        $segmentCount = is_array($segments) ? count($segments) : 0;
        if ($segmentCount === 0) {
            $alt = data_get($wire, 'itinerary.segments');
            $segmentCount = is_array($alt) ? count($alt) : 0;
        }
        if ($segmentCount === 0) {
            $alt2 = data_get($wire, 'RevalidateItineraryRQ.FlightSegments');
            $segmentCount = is_array($alt2) ? count($alt2) : 0;
        }
        $hasBookingClass = false;
        $hasFareBasis = false;
        $hasOfferReference = false;
        if (is_array($segments)) {
            foreach ($segments as $seg) {
                if (! is_array($seg)) {
                    continue;
                }
                $flight = is_array($seg['FlightSegment'] ?? null) ? $seg['FlightSegment'] : (is_array($seg['flight_segment'] ?? null) ? $seg['flight_segment'] : $seg);
                if (is_array($flight)) {
                    if (trim((string) ($flight['ResBookDesigCode'] ?? $flight['ClassOfService'] ?? $flight['class_of_service'] ?? '')) !== '') {
                        $hasBookingClass = true;
                    }
                    if (trim((string) ($flight['FareBasisCode'] ?? $flight['fare_basis_code'] ?? '')) !== '') {
                        $hasFareBasis = true;
                    }
                }
            }
        }
        $clientSegs = data_get($wire, 'RevalidateItineraryRQ.FlightSegments');
        if (is_array($clientSegs)) {
            foreach ($clientSegs as $flight) {
                if (! is_array($flight)) {
                    continue;
                }
                if (trim((string) ($flight['ClassOfService'] ?? '')) !== '') {
                    $hasBookingClass = true;
                }
                if (trim((string) ($flight['FareBasisCode'] ?? '')) !== '') {
                    $hasFareBasis = true;
                }
            }
        }
        $shopCtx = is_array($wire['shop_context'] ?? null) ? $wire['shop_context'] : [];
        foreach ($shopCtx as $k => $v) {
            if (! is_string($k) || ! is_scalar($v)) {
                continue;
            }
            $kl = strtolower($k);
            if (str_contains($kl, 'offeritem') || str_contains($kl, 'offer_item')) {
                $hasOfferReference = true;
                break;
            }
        }

        $this->diagnosticLogger->log(
            connection: $connection,
            action: 'revalidate',
            status: $response->successful() ? 'success' : 'failed',
            durationMs: $durationMs,
            safeMessage: $response->successful()
                ? 'Sabre revalidation HTTP completed.'
                : 'Sabre revalidation HTTP error.',
            meta: array_merge($parts, [
                'http_status' => $response->status(),
                'environment' => $connection->environment->value,
                'connection_id' => $connection->id,
                'duration_ms' => $durationMs,
                'segment_count' => $segmentCount,
                'has_booking_class' => $hasBookingClass,
                'has_fare_basis' => $hasFareBasis,
                'has_offer_reference' => $hasOfferReference,
            ]),
        );
        Log::info('sabre.revalidate.http', array_merge(['provider' => 'sabre'], $parts, [
            'http_status' => $response->status(),
            'duration_ms' => $durationMs,
            'segment_count' => $segmentCount,
            'has_booking_class' => $hasBookingClass,
            'has_fare_basis' => $hasFareBasis,
            'has_offer_reference' => $hasOfferReference,
        ]));

        return $response;
    }

    /**
     * Authenticated JSON POST for booking / PNR style endpoints. Does not log request bodies or bearer tokens.
     *
     * @param  array<string, mixed>  $json
     * @param  array<string, string>  $extraHeaders  Optional extra headers (e.g. {@code Conversation-ID} for Passenger Records); values must be non-sensitive.
     */
    public function postAuthenticatedJson(SupplierConnection $connection, string $pathSuffix, array $json, array $extraHeaders = []): Response
    {
        $path = $pathSuffix !== '' && $pathSuffix[0] === '/' ? $pathSuffix : '/'.$pathSuffix;
        $parts = $this->endpointParts($connection, $path);
        $token = $this->getAccessToken($connection);
        $url = $this->resolveBaseUrl($connection).$path;
        $started = microtime(true);

        $pending = Http::withToken($token)
            ->acceptJson()
            ->asJson()
            ->timeout((int) config('suppliers.sabre.timeout_seconds', 30))
            ->connectTimeout((int) config('suppliers.sabre.connect_timeout_seconds', 10))
            ->retry(0);
        foreach ($extraHeaders as $hk => $hv) {
            if (! is_string($hk) || trim($hk) === '' || ! is_string($hv)) {
                continue;
            }
            $pending = $pending->withHeaders([trim($hk) => $hv]);
        }

        $response = $pending->post($url, $json);

        $durationMs = (int) round((microtime(true) - $started) * 1000);
        $this->diagnosticLogger->log(
            connection: $connection,
            action: 'booking_create',
            status: $response->successful() ? 'success' : 'failed',
            durationMs: $durationMs,
            safeMessage: $response->successful()
                ? 'Sabre booking HTTP completed.'
                : 'Sabre booking HTTP error.',
            meta: array_merge($parts, [
                'http_status' => $response->status(),
                'environment' => $connection->environment->value,
                'connection_id' => $connection->id,
            ]),
        );

        return $response;
    }

    /**
     * @return array<string, mixed>
     */
    public function searchFlights(FlightSearchRequestData $request, SupplierConnection $connection): array
    {
        $shopPath = $this->shopRequestPath();
        $parts = $this->endpointParts($connection, $shopPath);

        $token = $this->getAccessToken($connection);
        $payload = $this->requestBuilder->build($request, $connection);

        $started = microtime(true);
        $url = $this->resolveBaseUrl($connection).$shopPath;

        try {
            $response = Http::withToken($token)
                ->timeout((int) config('suppliers.sabre.timeout_seconds', 30))
                ->connectTimeout((int) config('suppliers.sabre.connect_timeout_seconds', 10))
                ->retry(1, 300, fn ($exception): bool => $exception instanceof ConnectionException)
                ->post($url, $payload);

            $durationMs = (int) round((microtime(true) - $started) * 1000);

            if (! $response->successful()) {
                $json = $response->json();
                $status = $response->status();
                $meta = array_merge($this->searchRequestMeta($request, $connection), $parts, [
                    'reason_code' => $this->reasonCodeForFailedSearchHttp(
                        $status,
                        $this->pccPresent($connection),
                        $this->includesPccInShopRequest($connection)
                    ),
                    'http_status' => $status,
                    'duration_ms' => $durationMs,
                    'response_has_grouped_itinerary' => false,
                    'pcc_sent_in_shop_request' => $this->includesPccInShopRequest($connection),
                    'shop_payload_structure' => $this->requestBuilder->payloadStructureSummary($payload),
                ], $this->safeSabreErrorsSnippet(is_array($json) ? $json : null));

                $this->diagnosticLogger->log(
                    connection: $connection,
                    action: 'search',
                    status: 'failed',
                    durationMs: $durationMs,
                    safeMessage: 'Sabre search HTTP error.',
                    meta: $meta,
                );
                Log::warning('sabre.search.http_failed', array_merge(['provider' => 'sabre'], $meta));

                throw new RuntimeException('Sabre search request failed.');
            }

            $json = $response->json();
            if (! is_array($json)) {
                $meta = array_merge($this->searchRequestMeta($request, $connection), $parts, [
                    'reason_code' => 'sabre_request_invalid',
                    'http_status' => $response->status(),
                    'response_has_grouped_itinerary' => false,
                    'normalized_offer_count' => 0,
                    'duration_ms' => $durationMs,
                    'pcc_sent_in_shop_request' => $this->includesPccInShopRequest($connection),
                ]);

                $this->diagnosticLogger->log(
                    connection: $connection,
                    action: 'search',
                    status: 'failed',
                    durationMs: $durationMs,
                    safeMessage: 'Sabre search response was not JSON.',
                    meta: $meta,
                );
                Log::warning('sabre.search.invalid_json', array_merge(['provider' => 'sabre'], $meta));

                throw new RuntimeException('Sabre search response is malformed.');
            }

            $hasGrouped = data_get($json, 'groupedItineraryResponse') !== null;

            $successMeta = array_merge($this->searchRequestMeta($request, $connection), $parts, [
                'http_status' => $response->status(),
                'response_has_grouped_itinerary' => $hasGrouped,
                'duration_ms' => $durationMs,
                'pcc_sent_in_shop_request' => $this->includesPccInShopRequest($connection),
            ]);

            $this->diagnosticLogger->log(
                connection: $connection,
                action: 'search',
                status: 'success',
                durationMs: $durationMs,
                safeMessage: 'Sabre search HTTP completed.',
                meta: $successMeta,
            );
            Log::info('sabre.search.http_success', array_merge(['provider' => 'sabre'], $successMeta));

            return $json;
        } catch (ConnectionException $exception) {
            $durationMs = (int) round((microtime(true) - $started) * 1000);
            $meta = array_merge($this->searchRequestMeta($request, $connection), $parts, [
                'reason_code' => 'sabre_timeout',
                'http_status' => null,
                'duration_ms' => $durationMs,
            ]);

            $this->diagnosticLogger->log(
                connection: $connection,
                action: 'search',
                status: 'failed',
                durationMs: $durationMs,
                safeMessage: 'Sabre search connection timeout or network error.',
                meta: $meta,
            );
            Log::warning('sabre.search.timeout', array_merge(['provider' => 'sabre'], $meta));

            throw new RuntimeException('Sabre search is temporarily unavailable.', 0, $exception);
        } catch (RequestException $exception) {
            $durationMs = (int) round((microtime(true) - $started) * 1000);
            $httpStatus = $exception->response?->status();
            $reasonCode = is_int($httpStatus) && $httpStatus > 0
                ? $this->reasonCodeForFailedSearchHttp(
                    $httpStatus,
                    $this->pccPresent($connection),
                    $this->includesPccInShopRequest($connection)
                )
                : 'sabre_search_failed';
            $meta = array_merge($this->searchRequestMeta($request, $connection), $parts, [
                'reason_code' => $reasonCode,
                'http_status' => $httpStatus,
                'duration_ms' => $durationMs,
                'shop_payload_structure' => $this->requestBuilder->payloadStructureSummary($payload),
            ]);

            $this->diagnosticLogger->log(
                connection: $connection,
                action: 'search',
                status: 'failed',
                durationMs: $durationMs,
                safeMessage: 'Sabre search HTTP client exception.',
                meta: $meta,
            );
            Log::warning('sabre.search.request_exception', array_merge(['provider' => 'sabre'], $meta));

            throw new RuntimeException('Sabre search is temporarily unavailable.', 0, $exception);
        }
    }

    /**
     * Maps HTTP failures to the standard Sabre diagnostic vocabulary (no secrets).
     */
    protected function reasonCodeForFailedSearchHttp(int $httpStatus, bool $pccPresent, bool $pccSentInShopRequest): string
    {
        if (in_array($httpStatus, [408, 504], true)) {
            return 'sabre_timeout';
        }

        if ($httpStatus >= 500) {
            return 'sabre_provider_error';
        }

        if ($httpStatus >= 400 && $httpStatus < 500) {
            if (! $pccPresent || ! $pccSentInShopRequest) {
                return 'pcc_missing_or_not_used';
            }

            return 'sabre_search_failed';
        }

        return 'sabre_search_failed';
    }

    public function includesPccInShopRequest(?SupplierConnection $connection = null): bool
    {
        return $this->requestBuilder->includesPccInShopPayload($connection);
    }

    protected function searchRequestMeta(FlightSearchRequestData $request, SupplierConnection $connection): array
    {
        return [
            'connection_id' => $connection->id,
            'environment' => $connection->environment->value,
            'origin' => $request->origin,
            'destination' => $request->destination,
            'departure_date' => $request->departure_date,
            'trip_type' => $request->trip_type,
            'cabin' => $request->cabin,
            'passenger_counts' => [
                'adults' => $request->adults,
                'children' => $request->children,
                'infants' => $request->infants,
            ],
            'pcc_present' => $this->pccPresent($connection),
            'pcc_sent_in_shop_request' => $this->includesPccInShopRequest($connection),
        ];
    }

    protected function pccPresent(SupplierConnection $connection): bool
    {
        $cred = is_array($connection->credentials) ? $connection->credentials : [];
        $settings = is_array($connection->settings) ? $connection->settings : [];
        foreach (['pcc', 'PCC', 'pseudo_city_code', 'pseudoCityCode'] as $key) {
            if (trim((string) ($cred[$key] ?? '')) !== '') {
                return true;
            }
            if (trim((string) data_get($settings, $key)) !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{pcc_present: bool, sign_in_present: bool, password_present: bool}
     */
    protected function eprCredentialPresenceMeta(SupplierConnection $connection): array
    {
        $cred = is_array($connection->credentials) ? $connection->credentials : [];
        $settings = is_array($connection->settings) ? $connection->settings : [];

        return [
            'pcc_present' => $this->firstCredentialValue($cred, $settings, ['pcc', 'PCC', 'pseudo_city_code', 'pseudoCityCode']) !== '',
            'sign_in_present' => $this->firstCredentialValue($cred, $settings, ['sign_in', 'username', 'client_id']) !== '',
            'password_present' => $this->firstCredentialValue($cred, $settings, ['password', 'client_secret']) !== '',
        ];
    }

    /**
     * @return array{epr: string, pcc: string, password: string}|null
     */
    protected function resolveEprCredentialParts(SupplierConnection $connection): ?array
    {
        $cred = is_array($connection->credentials) ? $connection->credentials : [];
        $settings = is_array($connection->settings) ? $connection->settings : [];

        $pcc = $this->firstCredentialValue($cred, $settings, ['pcc', 'PCC', 'pseudo_city_code', 'pseudoCityCode']);
        $epr = $this->firstCredentialValue($cred, $settings, ['sign_in', 'username', 'client_id']);
        $password = $this->firstCredentialValue($cred, $settings, ['password', 'client_secret']);

        if ($pcc === '' || $epr === '' || $password === '') {
            return null;
        }

        return [
            'pcc' => $pcc,
            'epr' => $epr,
            'password' => $password,
        ];
    }

    /**
     * @param  list<string>  $keys
     */
    protected function firstCredentialValue(array $credentials, array $settings, array $keys): string
    {
        foreach ($keys as $key) {
            $v = trim((string) ($credentials[$key] ?? ''));
            if ($v !== '') {
                return $v;
            }
            $v = trim((string) data_get($settings, $key));
            if ($v !== '') {
                return $v;
            }
        }

        return '';
    }

    protected function resolveBaseUrl(SupplierConnection $connection): string
    {
        $baseUrl = rtrim((string) ($connection->base_url ?: config('suppliers.sabre.default_base_url')), '/');

        return $baseUrl;
    }

    public function hasExplicitEprCredentialTriple(SupplierConnection $connection): bool
    {
        $cred = is_array($connection->credentials) ? $connection->credentials : [];
        $signIn = trim((string) ($cred['sign_in'] ?? ''));
        $password = trim((string) ($cred['password'] ?? ''));
        $pcc = trim((string) ($cred['pcc'] ?? ''));

        return $signIn !== '' && $password !== '' && $pcc !== '';
    }

    /**
     * @param  array{epr: string, pcc: string, password: string}  $eprParts
     * @param  array{endpoint_host: string, endpoint_path: string}  $parts
     * @param  array{pcc_present: bool, sign_in_present: bool, password_present: bool}  $presenceMeta
     */
    protected function attemptEprTokenRequest(
        SupplierConnection $connection,
        string $tokenUrl,
        array $eprParts,
        array $parts,
        float $started,
        array $presenceMeta,
    ): Response {
        $domainCode = (string) config('suppliers.sabre.epr_domain_code', 'AA');
        $encodedPayload = SabreEprEncodedCredentials::basicAuthorizationPayload(
            $eprParts['epr'],
            $eprParts['pcc'],
            $eprParts['password'],
            $domainCode
        );

        try {
            return $this->sendSabreTokenRequestWithEncodedBasic($tokenUrl, $encodedPayload);
        } catch (ConnectionException $exception) {
            $this->logSabreTokenConnectionFailure($connection, $parts, $started, 'sabre_epr_encoded', $presenceMeta);

            throw new RuntimeException('Sabre authentication failed.', 0, $exception);
        }
    }

    protected function resolveHostClass(string $host): string
    {
        if ($host === 'api.platform.sabre.com') {
            return 'live';
        }

        if (in_array($host, [
            'api.cert.platform.sabre.com',
            'api-crt.cert.havail.sabre.com',
            'stl.platform.sabre.com',
        ], true)) {
            return 'cert';
        }

        return 'unknown';
    }
}
