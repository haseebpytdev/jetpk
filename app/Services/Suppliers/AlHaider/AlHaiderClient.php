<?php

namespace App\Services\Suppliers\AlHaider;

use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Support\Suppliers\AlHaiderSupplierConnectionNormalizer;

/**
 * Read-only HTTP client for Al-Haider group flight inventory API.
 *
 * Bearer tokens are persisted in encrypted server-side storage with optional
 * performance cache — never in browser, URLs, logs, or .env (except optional static override).
 */
class AlHaiderClient
{
    public const TOKEN_CACHE_KEY = 'alhaider:auth_token';

    private const TOKEN_LIMIT_BLOCK_KEY = 'alhaider:auth_token:limit_blocked';

    private const LOGIN_LOCK_KEY = 'alhaider:auth_token:login';

    public function __construct(
        private readonly AlHaiderTokenStore $tokenStore,
        private readonly AlHaiderTokenExpiryResolver $tokenExpiryResolver,
        private readonly AlHaiderConnectionAuthResolver $connectionAuthResolver,
    ) {}

    private ?string $resolvedAuthMode = null;

    private ?string $connectionBaseUrl = null;

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function listGroups(array $filters = []): array
    {
        $query = $this->buildGroupsQuery($filters);

        return $this->sendAuthenticated('GET', $this->path('groups_path'), [], $query, [
            'request_context' => 'list_groups',
            'filter_summary' => $this->filterSummary($filters),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function listAirlines(): array
    {
        return $this->sendAuthenticated('GET', $this->path('airlines_path'), [], [], [
            'request_context' => 'list_airlines',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function getGroupDetail(string $groupId): array
    {
        $path = str_replace('{id}', rawurlencode($groupId), $this->path('group_detail_path'));

        return $this->sendAuthenticated('GET', $path, [], [], [
            'request_context' => 'group_detail',
            'supplier_package_id' => $groupId,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function getAvailableSeats(string $groupId): array
    {
        $path = str_replace('{id}', rawurlencode($groupId), $this->path('seats_path'));

        return $this->sendAuthenticated('GET', $path, [], [], [
            'request_context' => 'available_seats',
            'supplier_package_id' => $groupId,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function reserveGroup(string $groupId, array $payload): array
    {
        if (! (bool) config('suppliers.al_haider.booking_enabled')) {
            throw new AlHaiderProviderException(
                'booking_disabled',
                503,
                'Al-Haider group booking is not enabled.'
            );
        }

        $path = str_replace('{id}', rawurlencode($groupId), $this->path('reserve_path'));

        return $this->sendAuthenticated('POST', $path, array_merge($payload, [
            'group_id' => $groupId,
        ]), [], [
            'request_context' => 'reserve_group',
            'supplier_package_id' => $groupId,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function cancelReservation(string $reservationId, array $payload = []): array
    {
        if (! (bool) config('suppliers.al_haider.booking_enabled')) {
            return ['skipped' => true, 'reason' => 'booking_disabled'];
        }

        $path = str_replace('{id}', rawurlencode($reservationId), $this->path('cancel_path'));

        return $this->sendAuthenticated('POST', $path, array_merge($payload, [
            'reservation_id' => $reservationId,
        ]), [], [
            'request_context' => 'cancel_reservation',
            'supplier_reservation_id' => $reservationId,
        ]);
    }

    public function clearTokenCache(): void
    {
        Cache::forget(self::TOKEN_CACHE_KEY);
    }

    public function isTokenLimitBlocked(): bool
    {
        return Cache::has(self::TOKEN_LIMIT_BLOCK_KEY);
    }

    public function isTokenGenerationEnabled(): bool
    {
        return (bool) config('suppliers.al_haider.token_generation_enabled', false);
    }

    public function hasPersistedToken(): bool
    {
        return $this->tokenStore->hasValidToken();
    }

    public function isConfigured(): bool
    {
        if (! (bool) config('suppliers.al_haider.enabled')) {
            return false;
        }

        $staticToken = trim((string) config('suppliers.al_haider.token'));
        if ($staticToken !== '') {
            return true;
        }

        $username = trim((string) config('suppliers.al_haider.username'));
        $password = trim((string) config('suppliers.al_haider.password'));

        return $username !== '' && $password !== '';
    }

    /**
     * Safe auth probe for diagnostics — never returns token value or calls login when generation is disabled.
     *
     * @return array{http_status: int, reason_code: string, token_obtained: bool}
     */
    public function probeAuthentication(): array
    {
        if ($this->isTokenLimitBlocked()) {
            return [
                'http_status' => 429,
                'reason_code' => 'supplier_auth_token_limit',
                'token_obtained' => false,
            ];
        }

        $connectionAuth = $this->connectionAuthResolver->resolveConnectionAuth();
        if ($connectionAuth !== null) {
            if ($connectionAuth['mode'] === AlHaiderSupplierConnectionNormalizer::AUTH_MODE_MANUAL) {
                if ($this->connectionAuthResolver->manualTokenExpired($connectionAuth['token_expires_at'])) {
                    return [
                        'http_status' => 401,
                        'reason_code' => 'supplier_auth_token_expired',
                        'token_obtained' => false,
                    ];
                }

                $manualToken = trim($connectionAuth['existing_token']);

                return [
                    'http_status' => $manualToken !== '' ? 200 : 503,
                    'reason_code' => $manualToken !== '' ? 'ok' : 'supplier_auth_token_missing',
                    'token_obtained' => $manualToken !== '',
                ];
            }
        }

        $staticToken = trim((string) config('suppliers.al_haider.token'));
        if ($staticToken !== '') {
            return [
                'http_status' => 200,
                'reason_code' => 'ok',
                'token_obtained' => true,
            ];
        }

        $cached = Cache::get(self::TOKEN_CACHE_KEY);
        if (is_string($cached) && $cached !== '') {
            return [
                'http_status' => 200,
                'reason_code' => 'ok',
                'token_obtained' => true,
            ];
        }

        $durable = $this->tokenStore->load();
        if ($durable !== null && $durable->isValid(time(), $this->tokenStore->expiryMarginSeconds())) {
            return [
                'http_status' => 200,
                'reason_code' => 'ok',
                'token_obtained' => true,
            ];
        }

        if (! $this->isTokenGenerationEnabled()) {
            return [
                'http_status' => 503,
                'reason_code' => 'supplier_auth_token_missing',
                'token_obtained' => false,
            ];
        }

        try {
            $token = $this->resolveToken();
            $obtained = $token !== '';

            return [
                'http_status' => $obtained ? 200 : 401,
                'reason_code' => $obtained ? 'ok' : 'supplier_auth_failed',
                'token_obtained' => $obtained,
            ];
        } catch (AlHaiderProviderException $exception) {
            return [
                'http_status' => $exception->httpStatus,
                'reason_code' => $exception->errorCode,
                'token_obtained' => false,
            ];
        }
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, string>
     */
    private function buildGroupsQuery(array $filters): array
    {
        $query = [];
        foreach (['type', 'airline_id', 'sector', 'dept_date'] as $key) {
            $value = trim((string) ($filters[$key] ?? ''));
            if ($value !== '') {
                $query[$key] = $value;
            }
        }

        if (! isset($query['dept_date']) && trim((string) ($filters['start_date'] ?? '')) !== '') {
            $query['dept_date'] = trim((string) $filters['start_date']);
        }

        return $query;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $query
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function sendAuthenticated(
        string $method,
        string $path,
        array $payload = [],
        array $query = [],
        array $context = [],
    ): array {
        $token = $this->resolveToken();

        try {
            return $this->send($method, $path, $token, $payload, $query, $context);
        } catch (AlHaiderProviderException $exception) {
            if ($exception->errorCode === 'supplier_auth_token_limit') {
                throw $exception;
            }

            if ($exception->httpStatus !== 401) {
                throw $exception;
            }

            if ($this->resolvedAuthMode === AlHaiderSupplierConnectionNormalizer::AUTH_MODE_MANUAL) {
                throw new AlHaiderProviderException(
                    'supplier_auth_token_rejected',
                    401,
                    'Token expired / rejected'
                );
            }

            $this->clearTokenCache();
            $this->tokenStore->markInvalidated($this->tokenStore->load());

            if (! $this->isTokenGenerationEnabled()) {
                throw $exception;
            }

            $token = $this->resolveToken(forceRefresh: true);

            return $this->send($method, $path, $token, $payload, $query, $context);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $query
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function send(
        string $method,
        string $path,
        string $token,
        array $payload = [],
        array $query = [],
        array $context = [],
    ): array {
        $url = $this->url($path);

        try {
            $request = $this->http($token);
            $response = $method === 'GET'
                ? $request->get($url, $query)
                : $request->post($url, $payload);
        } catch (ConnectionException $exception) {
            $this->logFailure($path, 0, $context, 'connection_exception');

            throw new AlHaiderProviderException(
                'supplier_transport_failed',
                503,
                'Al-Haider is temporarily unavailable. Please try again.',
                $exception
            );
        }

        $status = $response->status();
        if ($status === 401) {
            $this->logFailure($path, $status, $context, 'auth_failed');

            throw new AlHaiderProviderException(
                'supplier_auth_failed',
                401,
                'Al-Haider authentication failed.'
            );
        }

        if ($status < 200 || $status >= 300) {
            $this->logFailure($path, $status, $context, 'http_error');

            throw new AlHaiderProviderException(
                'supplier_http_error',
                $status,
                'Al-Haider returned an unexpected response.'
            );
        }

        $decoded = $response->json();
        if (! is_array($decoded)) {
            $this->logFailure($path, $status, $context, 'invalid_json');

            throw new AlHaiderProviderException(
                'supplier_invalid_response',
                502,
                'Al-Haider returned an invalid response.'
            );
        }

        return $decoded;
    }

    private function resolveToken(bool $forceRefresh = false): string
    {
        $this->resolvedAuthMode = null;
        $this->connectionBaseUrl = null;

        $connectionAuth = $this->connectionAuthResolver->resolveConnectionAuth();
        if ($connectionAuth !== null) {
            $this->resolvedAuthMode = $connectionAuth['mode'];
            if ($connectionAuth['base_url'] !== '') {
                $this->connectionBaseUrl = $connectionAuth['base_url'];
            }

            if ($connectionAuth['mode'] === AlHaiderSupplierConnectionNormalizer::AUTH_MODE_MANUAL) {
                if ($this->connectionAuthResolver->manualTokenExpired($connectionAuth['token_expires_at'])) {
                    throw new AlHaiderProviderException(
                        'supplier_auth_token_expired',
                        401,
                        'Token expired / rejected'
                    );
                }

                $manualToken = trim($connectionAuth['existing_token']);
                if ($manualToken === '') {
                    throw new AlHaiderProviderException(
                        'supplier_auth_token_missing',
                        503,
                        'Authentication required'
                    );
                }

                return $manualToken;
            }
        }

        $staticToken = trim((string) config('suppliers.al_haider.token'));
        if ($staticToken !== '') {
            return $staticToken;
        }

        if ($this->isTokenLimitBlocked()) {
            Log::warning('alhaider.auth.token_limit', [
                'supplier' => 'alhaider',
                'reason' => 'limit_block_active',
            ]);

            throw new AlHaiderProviderException(
                'supplier_auth_token_limit',
                503,
                'Al-Haider authentication is temporarily unavailable.'
            );
        }

        if (! $forceRefresh) {
            $cached = Cache::get(self::TOKEN_CACHE_KEY);
            if (is_string($cached) && $cached !== '') {
                Log::info('alhaider.auth.token_cache_hit', [
                    'supplier' => 'alhaider',
                    'cache_key' => self::TOKEN_CACHE_KEY,
                ]);

                return $cached;
            }

            $durable = $this->tokenStore->load();
            if ($durable !== null && $durable->isValid(time(), $this->tokenStore->expiryMarginSeconds())) {
                $this->populatePerformanceCache($durable);
                Log::info('alhaider.auth.durable_hit', [
                    'supplier' => 'alhaider',
                    'expires_at' => $durable->expiresAt,
                ]);

                return $durable->token;
            }
        }

        if (! $this->isTokenGenerationEnabled()) {
            Log::warning('alhaider.auth.token_missing', [
                'supplier' => 'alhaider',
                'reason' => 'generation_disabled',
            ]);

            throw new AlHaiderProviderException(
                'supplier_auth_token_missing',
                503,
                'Authentication required'
            );
        }

        Log::info('alhaider.auth.token_cache_miss', [
            'supplier' => 'alhaider',
            'cache_key' => self::TOKEN_CACHE_KEY,
            'force_refresh' => $forceRefresh,
        ]);

        $loginCredentials = $this->resolveAutoLoginCredentials($connectionAuth);

        return $this->loginWithLock($loginCredentials);
    }

    /**
     * @param  array{mode: string, existing_token: string, token_expires_at: ?string, username: string, password: string, base_url: string}|null  $connectionAuth
     * @return array{username: string, password: string}|null
     */
    private function resolveAutoLoginCredentials(?array $connectionAuth): ?array
    {
        if (
            is_array($connectionAuth)
            && ($connectionAuth['mode'] ?? '') === AlHaiderSupplierConnectionNormalizer::AUTH_MODE_AUTO
            && trim((string) ($connectionAuth['username'] ?? '')) !== ''
            && trim((string) ($connectionAuth['password'] ?? '')) !== ''
        ) {
            return [
                'username' => trim((string) $connectionAuth['username']),
                'password' => trim((string) $connectionAuth['password']),
            ];
        }

        return null;
    }

    /**
     * @param  array{username: string, password: string}|null  $credentialOverride
     */
    private function loginWithLock(?array $credentialOverride = null): string
    {
        $lockSeconds = max(5, (int) config('suppliers.al_haider.login_lock_seconds', 15));
        $waitSeconds = max(1, (int) config('suppliers.al_haider.login_lock_wait_seconds', 10));
        $lock = Cache::lock(self::LOGIN_LOCK_KEY, $lockSeconds);

        try {
            Log::info('alhaider.auth.lock_wait', [
                'supplier' => 'alhaider',
                'wait_seconds' => $waitSeconds,
            ]);
            $lock->block($waitSeconds);
        } catch (LockTimeoutException) {
            Log::warning('alhaider.auth.lock_timeout', [
                'supplier' => 'alhaider',
                'wait_seconds' => $waitSeconds,
            ]);

            $cached = Cache::get(self::TOKEN_CACHE_KEY);
            if (is_string($cached) && $cached !== '') {
                return $cached;
            }

            $durable = $this->tokenStore->load();
            if ($durable !== null && $durable->isValid(time(), $this->tokenStore->expiryMarginSeconds())) {
                $this->populatePerformanceCache($durable);

                return $durable->token;
            }

            throw new AlHaiderProviderException(
                'supplier_auth_busy',
                503,
                'Al-Haider authentication is busy. Please try again.'
            );
        }

        try {
            $cached = Cache::get(self::TOKEN_CACHE_KEY);
            if (is_string($cached) && $cached !== '') {
                Log::info('alhaider.auth.token_cache_hit', [
                    'supplier' => 'alhaider',
                    'cache_key' => self::TOKEN_CACHE_KEY,
                    'after_lock' => true,
                ]);

                return $cached;
            }

            $durable = $this->tokenStore->load();
            if ($durable !== null && $durable->isValid(time(), $this->tokenStore->expiryMarginSeconds())) {
                $this->populatePerformanceCache($durable);

                return $durable->token;
            }

            return $this->performLogin($credentialOverride);
        } finally {
            $lock->release();
        }
    }

    /**
     * @param  array{username: string, password: string}|null  $credentialOverride
     */
    private function performLogin(?array $credentialOverride = null): string
    {
        $username = trim((string) ($credentialOverride['username'] ?? config('suppliers.al_haider.username')));
        $password = trim((string) ($credentialOverride['password'] ?? config('suppliers.al_haider.password')));
        if ($username === '' || $password === '') {
            throw new AlHaiderProviderException(
                'supplier_auth_missing',
                401,
                'Al-Haider credentials are not configured.'
            );
        }

        Log::info('alhaider.auth.login_attempted', [
            'supplier' => 'alhaider',
            'endpoint' => $this->path('login_path'),
        ]);

        $loginPayload = [
            'email' => $username,
            'password' => $password,
        ];

        try {
            $response = $this->http(null)
                ->asForm()
                ->post($this->url($this->path('login_path')), $loginPayload);
        } catch (ConnectionException $exception) {
            throw new AlHaiderProviderException(
                'supplier_transport_failed',
                503,
                'Al-Haider is temporarily unavailable. Please try again.',
                $exception
            );
        }

        $token = $this->extractToken($response);

        if ($token === '' && $this->responseIndicatesTokenLimit($response)) {
            $this->handleTokenLimitResponse($response);

            throw new AlHaiderProviderException(
                'supplier_auth_token_limit',
                $response->status() ?: 429,
                'Al-Haider authentication is temporarily unavailable.'
            );
        }

        if ($token === '') {
            Log::warning('alhaider.login_failed', [
                'supplier' => 'alhaider',
                'endpoint' => $this->path('login_path'),
                'http_status' => $response->status(),
            ]);

            throw new AlHaiderProviderException(
                'supplier_auth_failed',
                $response->status() ?: 401,
                'Al-Haider login failed.'
            );
        }

        $issuedAt = time();
        $expiresAt = $this->tokenExpiryResolver->resolveExpiresAt($response, $issuedAt);
        $record = new AlHaiderTokenRecord(
            token: $token,
            issuedAt: $issuedAt,
            expiresAt: $expiresAt,
            source: 'login',
        );

        $this->tokenStore->save($record);
        $this->populatePerformanceCache($record);

        Log::info('alhaider.auth.login_succeeded', [
            'supplier' => 'alhaider',
            'endpoint' => $this->path('login_path'),
            'http_status' => $response->status(),
            'expires_at' => $expiresAt,
        ]);

        return $token;
    }

    private function populatePerformanceCache(AlHaiderTokenRecord $record): void
    {
        Cache::put(self::TOKEN_CACHE_KEY, $record->token, $this->performanceCacheTtlSeconds($record));
    }

    private function performanceCacheTtlSeconds(AlHaiderTokenRecord $record): int
    {
        $maxSeconds = max(
            60,
            (int) config(
                'suppliers.al_haider.token_performance_cache_max_seconds',
                (int) config('suppliers.al_haider.token_cache_ttl_seconds', 3600),
            ),
        );
        $remaining = $record->expiresAt - time() - $this->tokenStore->expiryMarginSeconds();

        return max(60, min($maxSeconds, $remaining));
    }

    private function extractToken(Response $response): string
    {
        $decoded = $response->json();
        if (! is_array($decoded)) {
            return '';
        }

        return trim((string) ($decoded['token'] ?? ''));
    }

    private function responseIndicatesTokenLimit(Response $response): bool
    {
        $decoded = $response->json();
        $messages = [];

        if (is_array($decoded)) {
            foreach (['message', 'error', 'errors'] as $key) {
                $value = $decoded[$key] ?? null;
                if (is_string($value)) {
                    $messages[] = strtolower($value);
                }
                if (is_array($value)) {
                    foreach ($value as $item) {
                        if (is_string($item)) {
                            $messages[] = strtolower($item);
                        }
                    }
                }
            }
        }

        $body = strtolower($response->body());
        if ($body !== '') {
            $messages[] = $body;
        }

        foreach ($messages as $message) {
            if (
                str_contains($message, 'maximum')
                && (str_contains($message, 'token') || str_contains($message, 'active'))
            ) {
                return true;
            }
            if (str_contains($message, 'active tokens')) {
                return true;
            }
        }

        return false;
    }

    private function handleTokenLimitResponse(Response $response): void
    {
        $blockSeconds = max(60, (int) config('suppliers.al_haider.token_limit_block_seconds', 300));
        Cache::put(self::TOKEN_LIMIT_BLOCK_KEY, true, $blockSeconds);

        Log::warning('alhaider.auth.token_limit', [
            'supplier' => 'alhaider',
            'endpoint' => $this->path('login_path'),
            'http_status' => $response->status(),
            'block_seconds' => $blockSeconds,
        ]);
    }

    private function http(?string $token): PendingRequest
    {
        $headers = ['Accept' => 'application/json'];
        if ($token !== null && $token !== '') {
            $headers['Authorization'] = 'Bearer '.$token;
        }

        return Http::withHeaders($headers)
            ->timeout((int) config('suppliers.al_haider.timeout_seconds', 20))
            ->connectTimeout((int) config('suppliers.al_haider.connect_timeout_seconds', 10));
    }

    private function url(string $path): string
    {
        $base = $this->connectionBaseUrl !== null && $this->connectionBaseUrl !== ''
            ? rtrim($this->connectionBaseUrl, '/')
            : rtrim((string) config('suppliers.al_haider.default_base_url'), '/');
        $path = '/'.ltrim($path, '/');

        return $base.$path;
    }

    private function path(string $key): string
    {
        return (string) config('suppliers.al_haider.'.$key);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function filterSummary(array $filters): string
    {
        $parts = [];
        foreach (['sector', 'dept_date', 'airline_id', 'type'] as $key) {
            $value = trim((string) ($filters[$key] ?? ''));
            if ($value !== '') {
                $parts[] = $key.'='.$value;
            }
        }

        return implode(',', $parts);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function logFailure(string $path, int $status, array $context, string $reason): void
    {
        Log::warning('alhaider.request_failed', array_merge([
            'supplier' => 'alhaider',
            'endpoint' => $path,
            'http_status' => $status,
            'reason' => $reason,
        ], array_intersect_key($context, array_flip([
            'request_context',
            'filter_summary',
            'supplier_package_id',
        ]))));
    }
}
