<?php

namespace App\Services\Suppliers\Iati;

use App\Models\SupplierConnection;
use App\Services\Suppliers\Iati\Exceptions\IatiAuthException;
use App\Support\Security\SensitiveDataRedactor;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * IATI Flight API v2 auth: when a secret is stored, flight calls use JWT from
 * GET {auth_base}/token (Basic auth_code:secret). Ping may use raw auth_code Bearer.
 */
class IatiAuthService
{
    public function __construct(
        private readonly IatiConfigResolver $configResolver,
    ) {}

    /**
     * Bearer token for flight v2 requests (search, book, fare, etc.).
     * Exchanges auth_code:secret for JWT when secret is configured; otherwise auth_code.
     */
    public function getBearerToken(SupplierConnection $connection, bool $forceRefresh = false): string
    {
        $config = $this->configResolver->resolve($connection);

        if (trim($config['secret']) !== '') {
            return $this->exchangeToken($connection, $config, $forceRefresh);
        }

        return $config['auth_code'];
    }

    /**
     * Bearer token for GET /test/ping — always raw auth_code (lightweight health check).
     */
    public function getPingBearerToken(SupplierConnection $connection): string
    {
        $config = $this->configResolver->resolve($connection);

        return $config['auth_code'];
    }

    public function usesJwtExchange(SupplierConnection $connection): bool
    {
        $config = $this->configResolver->resolve($connection);

        return trim($config['secret']) !== '';
    }

    public function getToken(SupplierConnection $connection, bool $forceRefresh = false): string
    {
        return $this->getBearerToken($connection, $forceRefresh);
    }

    public function clearTokenCache(SupplierConnection $connection): void
    {
        Cache::forget($this->cacheKey($connection));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function extractToken(array $data, string $rawBody = ''): string
    {
        foreach (['access_token', 'token', 'bearer_token', 'jwt'] as $key) {
            $value = trim((string) ($data[$key] ?? ''));
            if ($value !== '') {
                return $this->stripBearerPrefix($value);
            }
        }

        foreach (['data.access_token', 'data.token', 'result.access_token', 'result.token'] as $path) {
            $value = trim((string) data_get($data, $path, ''));
            if ($value !== '') {
                return $this->stripBearerPrefix($value);
            }
        }

        $trimmed = trim($rawBody);
        if ($trimmed !== '' && ! str_starts_with($trimmed, '{')) {
            return $this->stripBearerPrefix($trimmed);
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function exchangeToken(SupplierConnection $connection, array $config, bool $forceRefresh): string
    {
        $cacheKey = $this->cacheKey($connection);

        if (! $forceRefresh) {
            $cached = Cache::get($cacheKey);
            if (is_array($cached) && ! empty($cached['token']) && (int) ($cached['expires_at'] ?? 0) > time() + 60) {
                return (string) $cached['token'];
            }
        }

        $startedAt = microtime(true);
        $url = rtrim($config['auth_base'], '/').'/token';

        try {
            $response = Http::timeout((int) config('suppliers.iati.timeout_seconds', 30))
                ->connectTimeout((int) config('suppliers.iati.connect_timeout_seconds', 10))
                ->withHeaders([
                    'Authorization' => 'Basic '.base64_encode($config['auth_code'].':'.$config['secret']),
                    'Accept' => 'application/json',
                ])
                ->get($url);
        } catch (ConnectionException $exception) {
            $this->logAuthLifecycle($connection, $config, 'failed', null, (int) round((microtime(true) - $startedAt) * 1000), 'supplier_transport_failed');

            throw new IatiAuthException(
                'supplier_transport_failed',
                503,
                'IATI authentication is temporarily unavailable.',
                previous: $exception,
            );
        }

        $durationMs = (int) round((microtime(true) - $startedAt) * 1000);
        $status = $response->status();

        if ($status < 200 || $status >= 300) {
            $this->logAuthLifecycle($connection, $config, 'failed', null, $durationMs, 'supplier_auth_failed', $status);

            throw new IatiAuthException(
                'supplier_auth_failed',
                $status,
                'IATI authentication failed. Check credentials and environment.',
            );
        }

        $data = $response->json();
        $token = $this->extractToken(is_array($data) ? $data : [], $response->body());

        if ($token === '') {
            $this->logAuthLifecycle($connection, $config, 'failed', null, $durationMs, 'supplier_malformed_response');

            throw new IatiAuthException(
                'supplier_malformed_response',
                502,
                'IATI authentication returned an invalid token response.',
            );
        }

        $ttlSeconds = (int) config('suppliers.iati.token_cache_ttl_seconds', 86000);
        Cache::put($cacheKey, [
            'token' => $token,
            'expires_at' => time() + $ttlSeconds,
        ], $ttlSeconds);

        $this->logAuthLifecycle($connection, $config, 'success', $token, $durationMs);

        return $token;
    }

    private function stripBearerPrefix(string $token): string
    {
        return preg_replace('/^Bearer\s+/i', '', trim($token)) ?? trim($token);
    }

    private function cacheKey(SupplierConnection $connection): string
    {
        $config = $this->configResolver->resolve($connection);

        return 'iati_auth_token:'.$connection->id.':'.($config['is_test'] ? 'test' : 'prod');
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function logAuthLifecycle(
        SupplierConnection $connection,
        array $config,
        string $status,
        ?string $token,
        int $durationMs,
        ?string $errorCode = null,
        ?int $httpStatus = null,
    ): void {
        Log::channel('iati')->info('iati.auth.'.$status, SensitiveDataRedactor::redact([
            'provider' => 'iati',
            'supplier_connection_id' => $connection->id,
            'environment' => $config['environment'],
            'endpoint' => '/rest/auth/token',
            'duration_ms' => $durationMs,
            'http_status' => $httpStatus,
            'error_code' => $errorCode,
            'token_present' => $token !== null && $token !== '',
        ]));
    }
}
