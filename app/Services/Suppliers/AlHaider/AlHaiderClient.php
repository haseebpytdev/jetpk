<?php

namespace App\Services\Suppliers\AlHaider;

use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Read-only HTTP client for Al-Haider group flight inventory API.
 *
 * Dynamic session tokens are cached in Laravel cache with a login lock — not stored in .env.
 */
class AlHaiderClient
{
    public const TOKEN_CACHE_KEY = 'alhaider:auth_token';

    private const TOKEN_LIMIT_BLOCK_KEY = 'alhaider:auth_token:limit_blocked';

    private const LOGIN_LOCK_KEY = 'alhaider:auth_token:login';

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
     * Safe auth probe for diagnostics — never returns token value.
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

            $this->clearTokenCache();
            $token = $this->resolveToken(true);

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
        }

        Log::info('alhaider.auth.token_cache_miss', [
            'supplier' => 'alhaider',
            'cache_key' => self::TOKEN_CACHE_KEY,
            'force_refresh' => $forceRefresh,
        ]);

        return $this->loginWithLock();
    }

    private function loginWithLock(): string
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

            return $this->performLogin();
        } finally {
            $lock->release();
        }
    }

    private function performLogin(): string
    {
        $username = trim((string) config('suppliers.al_haider.username'));
        $password = trim((string) config('suppliers.al_haider.password'));
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

        if ($token === '' && $response->status() !== 200) {
            try {
                $response = $this->http(null)
                    ->post($this->url($this->path('login_path')), $loginPayload);
                $token = $this->extractToken($response);
            } catch (ConnectionException $exception) {
                throw new AlHaiderProviderException(
                    'supplier_transport_failed',
                    503,
                    'Al-Haider is temporarily unavailable. Please try again.',
                    $exception
                );
            }

            if ($token === '' && $this->responseIndicatesTokenLimit($response)) {
                $this->handleTokenLimitResponse($response);

                throw new AlHaiderProviderException(
                    'supplier_auth_token_limit',
                    $response->status() ?: 429,
                    'Al-Haider authentication is temporarily unavailable.'
                );
            }
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

        $ttl = max(60, (int) config('suppliers.al_haider.token_cache_ttl_seconds', 82800));
        Cache::put(self::TOKEN_CACHE_KEY, $token, $ttl);

        Log::info('alhaider.auth.login_succeeded', [
            'supplier' => 'alhaider',
            'endpoint' => $this->path('login_path'),
            'http_status' => $response->status(),
            'cache_ttl_seconds' => $ttl,
        ]);

        return $token;
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
        $base = rtrim((string) config('suppliers.al_haider.default_base_url'), '/');
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
