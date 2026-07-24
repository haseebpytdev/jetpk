<?php

namespace App\Services\Suppliers\OneApi\Authentication;

use App\Models\SupplierConnection;
use App\Services\Suppliers\OneApi\Exceptions\OneApiAuthException;
use App\Services\Suppliers\OneApi\Exceptions\OneApiTransportException;
use App\Services\Suppliers\OneApi\Support\OneApiConfigResolver;
use App\Support\Security\SensitiveDataRedactor;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * One API REST authentication (tokenPair). Tokens cached per connection+environment only.
 */
class OneApiAuthService
{
    public function __construct(
        private readonly OneApiConfigResolver $configResolver,
    ) {}

    public function getAccessToken(SupplierConnection $connection, bool $forceRefresh = false): string
    {
        $config = $this->configResolver->resolve($connection);
        $cacheKey = $this->cacheKey($connection, $config);
        $lockKey = $cacheKey.':lock';

        if (! $forceRefresh) {
            $cached = Cache::get($cacheKey);
            if (is_array($cached) && ! empty($cached['access_token']) && (int) ($cached['expires_at'] ?? 0) > time() + (int) config('suppliers.one_api.token_expiry_margin_seconds', 120)) {
                return (string) $cached['access_token'];
            }
        }

        return Cache::lock($lockKey, 15)->block(10, function () use ($connection, $config, $cacheKey, $forceRefresh): string {
            if (! $forceRefresh) {
                $cached = Cache::get($cacheKey);
                if (is_array($cached) && ! empty($cached['access_token']) && (int) ($cached['expires_at'] ?? 0) > time() + (int) config('suppliers.one_api.token_expiry_margin_seconds', 120)) {
                    return (string) $cached['access_token'];
                }
            }

            return $this->authenticate($connection, $config, $cacheKey);
        });
    }

    public function clearTokenCache(SupplierConnection $connection): void
    {
        $config = $this->configResolver->resolve($connection);
        Cache::forget($this->cacheKey($connection, $config));
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function authenticate(SupplierConnection $connection, array $config, string $cacheKey): string
    {
        $startedAt = microtime(true);
        $url = (string) $config['rest_auth_url'];
        $payload = [
            'login' => (string) $config['username'],
            'password' => (string) $config['password'],
        ];

        try {
            $pending = Http::timeout((int) $config['request_timeout_seconds'])
                ->connectTimeout((int) $config['connect_timeout_seconds'])
                ->acceptJson()
                ->asJson();

            if (! ($config['verify_tls'] ?? true)) {
                $pending = $pending->withoutVerifying();
            }

            $response = $pending->post($url, $payload);
        } catch (ConnectionException $exception) {
            $this->logAuth($connection, 'failed', (int) round((microtime(true) - $startedAt) * 1000), 'supplier_timeout');

            throw new OneApiTransportException(
                'supplier_timeout',
                503,
                'One API authentication is temporarily unavailable.',
                previous: $exception,
            );
        }

        $durationMs = (int) round((microtime(true) - $startedAt) * 1000);
        $status = $response->status();

        if (in_array($status, [401, 403], true)) {
            $this->logAuth($connection, 'rejected', $durationMs, 'authentication_error', $status);

            throw new OneApiAuthException(
                'authentication_error',
                $status,
                'One API credentials were rejected.',
            );
        }

        if ($status < 200 || $status >= 300) {
            $this->logAuth($connection, 'failed', $durationMs, 'supplier_transport_error', $status);

            throw new OneApiTransportException(
                'supplier_transport_error',
                $status,
                'One API authentication failed.',
            );
        }

        $data = $response->json();
        if (! is_array($data)) {
            throw new OneApiAuthException('malformed_response', 502, 'Invalid authentication response.');
        }

        $tokenPair = $data['tokenPair'] ?? null;
        if (! is_array($tokenPair)) {
            throw new OneApiAuthException('missing_token_pair', 502, 'Authentication response missing tokenPair.');
        }

        $accessToken = trim((string) ($tokenPair['accessToken'] ?? ''));
        if ($accessToken === '') {
            throw new OneApiAuthException('missing_access_token', 502, 'Authentication response missing access token.');
        }

        $expiresAt = $this->resolveExpiryFromJwt($accessToken);
        $ttl = max(60, $expiresAt - time() - (int) config('suppliers.one_api.token_expiry_margin_seconds', 120));
        if ($expiresAt <= 0) {
            $ttl = (int) config('suppliers.one_api.token_cache_fallback_ttl_seconds', 3000);
            $expiresAt = time() + $ttl;
        }

        Cache::put($cacheKey, [
            'access_token' => $accessToken,
            'expires_at' => $expiresAt,
        ], $ttl);

        $this->logAuth($connection, 'success', $durationMs);

        return $accessToken;
    }

    private function resolveExpiryFromJwt(string $jwt): int
    {
        $parts = explode('.', $jwt);
        if (count($parts) < 2) {
            return 0;
        }

        $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')) ?: '', true);
        if (! is_array($payload)) {
            return 0;
        }

        $exp = (int) ($payload['exp'] ?? 0);

        return $exp > 0 ? $exp : 0;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function cacheKey(SupplierConnection $connection, array $config): string
    {
        $env = (string) ($config['environment'] ?? 'sandbox');

        return 'one_api_auth:'.$connection->id.':'.strtolower($env);
    }

    private function logAuth(SupplierConnection $connection, string $status, int $durationMs, ?string $code = null, ?int $httpStatus = null): void
    {
        Log::channel('one-api')->info('one_api.auth.'.$status, SensitiveDataRedactor::redact([
            'supplier' => 'one_api',
            'supplier_connection_id' => $connection->id,
            'duration_ms' => $durationMs,
            'error_code' => $code,
            'http_status' => $httpStatus,
        ]));
    }
}
