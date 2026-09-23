<?php

namespace App\Services\Suppliers\AmeerEMillat;

use App\Models\SupplierConnection;
use App\Support\Suppliers\AmeerEMillatSupplierConnectionNormalizer;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * HTTP client for Ameer-e-Millat group flight inventory API.
 *
 * Auth authority: active SupplierConnection credentials (managed/manual token) first,
 * then optional env static token, then cached dynamic login. Secrets stay in DB/env —
 * never in source. Dynamic login tokens may be cached under TOKEN_CACHE_KEY.
 */
class AmeerEMillatClient
{
    public const TOKEN_CACHE_KEY = 'ameer_e_millat:auth_token';

    private const LOGIN_LOCK_KEY = 'ameer_e_millat:auth_token:login';

    public function __construct(
        private readonly AmeerEMillatConnectionAuthResolver $connectionAuthResolver,
    ) {}

    private ?string $resolvedAuthMode = null;

    private ?string $connectionBaseUrl = null;

    private ?int $resolvedConnectionId = null;

    /**
     * @return array<string, mixed>
     */
    public function listSectors(): array
    {
        return $this->sendAuthenticated('GET', $this->path('sectors_path'), [], [], [
            'request_context' => 'list_sectors',
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
        $primaryPath = str_replace('{id}', rawurlencode($groupId), $this->path('seats_path'));

        try {
            return $this->sendAuthenticated('GET', $primaryPath, [], [], [
                'request_context' => 'available_seats',
                'supplier_package_id' => $groupId,
            ]);
        } catch (AmeerEMillatProviderException $exception) {
            if (! in_array($exception->httpStatus, [404, 405], true)) {
                throw $exception;
            }

            $legacyPath = str_replace('{id}', rawurlencode($groupId), $this->path('legacy_seats_path'));

            return $this->sendAuthenticated('GET', $legacyPath, [], [], [
                'request_context' => 'available_seats_legacy',
                'supplier_package_id' => $groupId,
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createBooking(array $payload): array
    {
        if (! (bool) config('suppliers.ameer_e_millat.booking_enabled')) {
            throw new AmeerEMillatProviderException(
                'booking_disabled',
                503,
                'Ameer-e-Millat group booking is not enabled.'
            );
        }

        return $this->sendAuthenticated('POST', $this->path('create_booking_path'), $payload, [], [
            'request_context' => 'create_booking',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function showBooking(string $bookingId): array
    {
        $path = str_replace('{id}', rawurlencode($bookingId), $this->path('show_booking_path'));

        return $this->sendAuthenticated('GET', $path, [], [], [
            'request_context' => 'show_booking',
            'supplier_booking_id' => $bookingId,
        ]);
    }

    /**
     * Safe auth probe via profile endpoint — never returns token value.
     *
     * @return array{http_status: int, reason_code: string, profile_ok: bool}
     */
    public function probeUserProfile(): array
    {
        try {
            $this->sendAuthenticated('GET', $this->path('profile_path'), [], [], [
                'request_context' => 'probe_profile',
            ]);

            return [
                'http_status' => 200,
                'reason_code' => 'ok',
                'profile_ok' => true,
            ];
        } catch (AmeerEMillatProviderException $exception) {
            return [
                'http_status' => $exception->httpStatus,
                'reason_code' => $exception->errorCode,
                'profile_ok' => false,
            ];
        }
    }

    /**
     * Probe using a specific supplier connection row (admin test connection).
     *
     * @return array{http_status: int, reason_code: string, profile_ok: bool}
     */
    public function probeUserProfileForConnection(SupplierConnection $connection): array
    {
        return $this->connectionAuthResolver->usingConnection(
            $connection,
            fn (): array => $this->probeUserProfile(),
        );
    }

    public function clearTokenCache(): void
    {
        Cache::forget(self::TOKEN_CACHE_KEY);
    }

    public function isConfigured(): bool
    {
        if (! (bool) config('suppliers.ameer_e_millat.enabled')) {
            return false;
        }

        $connectionAuth = $this->connectionAuthResolver->resolveConnectionAuth();
        if ($connectionAuth !== null && $this->connectionAuthResolver->connectionAuthIsConfigured($connectionAuth)) {
            return true;
        }

        $staticToken = trim((string) config('suppliers.ameer_e_millat.token'));
        if ($staticToken !== '') {
            return true;
        }

        $email = trim((string) config('suppliers.ameer_e_millat.email'));
        $password = trim((string) config('suppliers.ameer_e_millat.password'));

        return $email !== '' && $password !== '';
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
        } catch (AmeerEMillatProviderException $exception) {
            if ($exception->httpStatus !== 401) {
                throw $exception;
            }

            if (in_array($this->resolvedAuthMode, [
                AmeerEMillatSupplierConnectionNormalizer::AUTH_MODE_MANUAL,
                AmeerEMillatSupplierConnectionNormalizer::AUTH_MODE_MANAGED,
            ], true)) {
                Log::warning('ameer_e_millat.auth.fail_closed_401', [
                    'supplier' => 'ameer_e_millat',
                    'auth_mode' => $this->resolvedAuthMode,
                    'connection_id' => $this->resolvedConnectionId,
                    'request_context' => $context['request_context'] ?? null,
                ]);

                throw new AmeerEMillatProviderException(
                    'supplier_auth_token_rejected',
                    401,
                    'Token expired / rejected'
                );
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

            throw new AmeerEMillatProviderException(
                'supplier_transport_failed',
                503,
                'Ameer-e-Millat is temporarily unavailable. Please try again.',
                $exception
            );
        }

        $status = $response->status();
        if ($status === 401) {
            $this->logFailure($path, $status, $context, 'auth_failed');

            throw new AmeerEMillatProviderException(
                'supplier_auth_failed',
                401,
                'Ameer-e-Millat authentication failed.'
            );
        }

        if ($status < 200 || $status >= 300) {
            $this->logFailure($path, $status, $context, 'http_error');

            throw new AmeerEMillatProviderException(
                'supplier_http_error',
                $status,
                'Ameer-e-Millat returned an unexpected response.'
            );
        }

        $decoded = $response->json();
        if (! is_array($decoded)) {
            $this->logFailure($path, $status, $context, 'invalid_json');

            throw new AmeerEMillatProviderException(
                'supplier_invalid_response',
                502,
                'Ameer-e-Millat returned an invalid response.'
            );
        }

        return $this->parseResponse($decoded, $path, $status, $context);
    }

    /**
     * @param  array<string, mixed>  $decoded
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function parseResponse(array $decoded, string $path, int $status, array $context): array
    {
        $errorFlag = $decoded['error'] ?? null;
        $successFlag = $decoded['success'] ?? null;
        $vendorFailed = $errorFlag === true
            || $errorFlag === 1
            || $errorFlag === 'true'
            || $successFlag === false
            || $successFlag === 0
            || $successFlag === 'false';

        if ($vendorFailed) {
            $message = trim((string) ($decoded['message'] ?? $decoded['error_message'] ?? ''));
            if ($message === '') {
                $message = 'Ameer-e-Millat returned an error response.';
            }

            $this->logFailure($path, $status, $context, 'vendor_error');

            throw new AmeerEMillatProviderException(
                'supplier_vendor_error',
                $status >= 200 && $status < 300 ? 422 : $status,
                $message
            );
        }

        if (isset($decoded['data']) && is_array($decoded['data'])) {
            return $decoded['data'];
        }

        return $decoded;
    }

    private function resolveToken(bool $forceRefresh = false): string
    {
        $this->resolvedAuthMode = null;
        $this->connectionBaseUrl = null;
        $this->resolvedConnectionId = null;

        $connectionAuth = $this->connectionAuthResolver->resolveConnectionAuth();
        if ($connectionAuth !== null) {
            $this->resolvedAuthMode = $connectionAuth['mode'];
            $this->resolvedConnectionId = (int) ($connectionAuth['connection_id'] ?? 0) ?: null;
            if ($connectionAuth['base_url'] !== '') {
                $this->connectionBaseUrl = $connectionAuth['base_url'];
            }

            if ($connectionAuth['mode'] === AmeerEMillatSupplierConnectionNormalizer::AUTH_MODE_MANUAL) {
                if ($this->connectionAuthResolver->manualTokenExpired($connectionAuth['token_expires_at'])) {
                    throw new AmeerEMillatProviderException(
                        'supplier_auth_token_expired',
                        401,
                        'Token expired / rejected'
                    );
                }

                $manualToken = trim($connectionAuth['existing_token']);
                if ($manualToken === '') {
                    throw new AmeerEMillatProviderException(
                        'supplier_auth_token_missing',
                        503,
                        'Authentication required'
                    );
                }

                return $manualToken;
            }

            if ($connectionAuth['mode'] === AmeerEMillatSupplierConnectionNormalizer::AUTH_MODE_MANAGED) {
                $managedToken = trim($connectionAuth['existing_token']);
                $expired = $this->connectionAuthResolver->manualTokenExpired($connectionAuth['token_expires_at']);

                if ($managedToken !== '' && ! $expired) {
                    return $managedToken;
                }

                if ($expired || $managedToken === '') {
                    throw new AmeerEMillatProviderException(
                        $expired ? 'supplier_auth_token_expired' : 'supplier_auth_token_missing',
                        $expired ? 401 : 503,
                        $expired ? 'Token expired / rejected' : 'Authentication required'
                    );
                }
            }

            if ($connectionAuth['mode'] === AmeerEMillatSupplierConnectionNormalizer::AUTH_MODE_AUTO) {
                if ($connectionAuth['username'] === '' || $connectionAuth['password'] === '') {
                    throw new AmeerEMillatProviderException(
                        'supplier_auth_missing',
                        401,
                        'Ameer-e-Millat credentials are not configured.'
                    );
                }
            }
        }

        $staticToken = trim((string) config('suppliers.ameer_e_millat.token'));
        if ($staticToken !== '') {
            return $staticToken;
        }

        if (! $forceRefresh) {
            $cached = Cache::get(self::TOKEN_CACHE_KEY);
            if (is_string($cached) && $cached !== '') {
                Log::info('ameer_e_millat.auth.token_cache_hit', [
                    'supplier' => 'ameer_e_millat',
                    'cache_key' => self::TOKEN_CACHE_KEY,
                ]);

                return $cached;
            }
        }

        Log::info('ameer_e_millat.auth.token_cache_miss', [
            'supplier' => 'ameer_e_millat',
            'cache_key' => self::TOKEN_CACHE_KEY,
            'force_refresh' => $forceRefresh,
        ]);

        return $this->loginWithLock($connectionAuth);
    }

    /**
     * @param  array{
     *     mode: string,
     *     existing_token: string,
     *     token_expires_at: ?string,
     *     username: string,
     *     password: string,
     *     auto_renew: bool,
     *     base_url: string,
     *     connection_id: int
     * }|null  $connectionAuth
     */
    private function loginWithLock(?array $connectionAuth = null): string
    {
        $lockSeconds = max(5, (int) config('suppliers.ameer_e_millat.login_lock_seconds', 15));
        $waitSeconds = max(1, (int) config('suppliers.ameer_e_millat.login_lock_wait_seconds', 10));
        $lock = Cache::lock(self::LOGIN_LOCK_KEY, $lockSeconds);

        try {
            Log::info('ameer_e_millat.auth.lock_wait', [
                'supplier' => 'ameer_e_millat',
                'wait_seconds' => $waitSeconds,
            ]);
            $lock->block($waitSeconds);
        } catch (LockTimeoutException) {
            Log::warning('ameer_e_millat.auth.lock_timeout', [
                'supplier' => 'ameer_e_millat',
                'wait_seconds' => $waitSeconds,
            ]);

            $cached = Cache::get(self::TOKEN_CACHE_KEY);
            if (is_string($cached) && $cached !== '') {
                return $cached;
            }

            throw new AmeerEMillatProviderException(
                'supplier_auth_busy',
                503,
                'Ameer-e-Millat authentication is busy. Please try again.'
            );
        }

        try {
            $cached = Cache::get(self::TOKEN_CACHE_KEY);
            if (is_string($cached) && $cached !== '') {
                Log::info('ameer_e_millat.auth.token_cache_hit', [
                    'supplier' => 'ameer_e_millat',
                    'cache_key' => self::TOKEN_CACHE_KEY,
                    'after_lock' => true,
                ]);

                return $cached;
            }

            return $this->performLogin($connectionAuth);
        } finally {
            $lock->release();
        }
    }

    /**
     * @param  array{
     *     mode: string,
     *     existing_token: string,
     *     token_expires_at: ?string,
     *     username: string,
     *     password: string,
     *     auto_renew: bool,
     *     base_url: string,
     *     connection_id: int
     * }|null  $connectionAuth
     */
    private function performLogin(?array $connectionAuth = null): string
    {
        $email = trim((string) config('suppliers.ameer_e_millat.email'));
        $password = trim((string) config('suppliers.ameer_e_millat.password'));

        if (
            is_array($connectionAuth)
            && ($connectionAuth['mode'] ?? '') === AmeerEMillatSupplierConnectionNormalizer::AUTH_MODE_AUTO
            && trim((string) ($connectionAuth['username'] ?? '')) !== ''
            && trim((string) ($connectionAuth['password'] ?? '')) !== ''
        ) {
            $email = trim((string) $connectionAuth['username']);
            $password = trim((string) $connectionAuth['password']);
        }

        if ($email === '' || $password === '') {
            throw new AmeerEMillatProviderException(
                'supplier_auth_missing',
                401,
                'Ameer-e-Millat credentials are not configured.'
            );
        }

        Log::info('ameer_e_millat.auth.login_attempted', [
            'supplier' => 'ameer_e_millat',
            'endpoint' => $this->path('login_path'),
        ]);

        $loginPayload = [
            'email' => $email,
            'password' => $password,
        ];

        try {
            $response = $this->http(null)
                ->asForm()
                ->post($this->url($this->path('login_path')), $loginPayload);
        } catch (ConnectionException $exception) {
            throw new AmeerEMillatProviderException(
                'supplier_transport_failed',
                503,
                'Ameer-e-Millat is temporarily unavailable. Please try again.',
                $exception
            );
        }

        $token = $this->extractToken($response);

        if ($token === '' && $response->status() !== 200) {
            try {
                $response = $this->http(null)
                    ->post($this->url($this->path('login_path')), $loginPayload);
                $token = $this->extractToken($response);
            } catch (ConnectionException $exception) {
                throw new AmeerEMillatProviderException(
                    'supplier_transport_failed',
                    503,
                    'Ameer-e-Millat is temporarily unavailable. Please try again.',
                    $exception
                );
            }
        }

        if ($token === '') {
            Log::warning('ameer_e_millat.login_failed', [
                'supplier' => 'ameer_e_millat',
                'endpoint' => $this->path('login_path'),
                'http_status' => $response->status(),
            ]);

            throw new AmeerEMillatProviderException(
                'supplier_auth_failed',
                $response->status() ?: 401,
                'Ameer-e-Millat login failed.'
            );
        }

        $ttl = $this->resolveTokenCacheTtl($token);

        Cache::put(self::TOKEN_CACHE_KEY, $token, $ttl);

        Log::info('ameer_e_millat.auth.login_succeeded', [
            'supplier' => 'ameer_e_millat',
            'endpoint' => $this->path('login_path'),
            'http_status' => $response->status(),
            'cache_ttl_seconds' => $ttl,
        ]);

        return $token;
    }

    private function resolveTokenCacheTtl(string $token): int
    {
        $defaultTtl = max(60, (int) config('suppliers.ameer_e_millat.token_cache_ttl_seconds', 82800));
        $derivedExpiry = AmeerEMillatSupplierConnectionNormalizer::deriveJwtExpiryIso($token);

        if ($derivedExpiry === null) {
            return $defaultTtl;
        }

        $expiryTimestamp = strtotime($derivedExpiry);
        if ($expiryTimestamp === false) {
            return $defaultTtl;
        }

        $remaining = $expiryTimestamp - time();
        if ($remaining <= 60) {
            return 60;
        }

        return min($defaultTtl, $remaining);
    }

    private function extractToken(Response $response): string
    {
        $decoded = $response->json();
        if (! is_array($decoded)) {
            return '';
        }

        return trim((string) ($decoded['token'] ?? ''));
    }

    private function http(?string $token): PendingRequest
    {
        $headers = ['Accept' => 'application/json'];
        if ($token !== null && $token !== '') {
            $headers['Authorization'] = 'Bearer '.$token;
        }

        return Http::withHeaders($headers)
            ->timeout((int) config('suppliers.ameer_e_millat.timeout_seconds', 20))
            ->connectTimeout((int) config('suppliers.ameer_e_millat.connect_timeout_seconds', 10));
    }

    private function url(string $path): string
    {
        $base = $this->connectionBaseUrl !== null && $this->connectionBaseUrl !== ''
            ? rtrim($this->connectionBaseUrl, '/')
            : rtrim((string) config('suppliers.ameer_e_millat.default_base_url'), '/');
        $path = '/'.ltrim($path, '/');

        return $base.$path;
    }

    private function path(string $key): string
    {
        return (string) config('suppliers.ameer_e_millat.'.$key);
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
        Log::warning('ameer_e_millat.request_failed', array_merge([
            'supplier' => 'ameer_e_millat',
            'endpoint' => $path,
            'http_status' => $status,
            'reason' => $reason,
        ], array_intersect_key($context, array_flip([
            'request_context',
            'filter_summary',
            'supplier_package_id',
            'supplier_booking_id',
        ]))));
    }
}
