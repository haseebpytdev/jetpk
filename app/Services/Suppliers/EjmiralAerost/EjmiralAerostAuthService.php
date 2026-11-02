<?php

namespace App\Services\Suppliers\EjmiralAerost;

use App\Models\SupplierConnection;
use App\Services\Suppliers\EjmiralAerost\Exceptions\EjmiralAerostAuthException;
use App\Support\Security\SensitiveDataRedactor;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * AEROST Sub Travel Agent login. Bearer token documented as valid ~7 days.
 * Reuse cached token; refresh only on expiry or explicit 401 renewal.
 */
class EjmiralAerostAuthService
{
    public function __construct(
        private readonly EjmiralAerostConfigResolver $configResolver,
    ) {}

    public function getBearerToken(SupplierConnection $connection, bool $forceRefresh = false): string
    {
        $config = $this->configResolver->resolve($connection);
        $cacheKey = $this->cacheKey($connection);

        if (! $forceRefresh) {
            $cached = Cache::get($cacheKey);
            if (is_array($cached)
                && ! empty($cached['token'])
                && (int) ($cached['expires_at'] ?? 0) > time() + (int) $config['token_expiry_margin_seconds']
            ) {
                return (string) $cached['token'];
            }
        }

        return $this->login($connection, $config, $cacheKey);
    }

    public function clearTokenCache(SupplierConnection $connection): void
    {
        Cache::forget($this->cacheKey($connection));
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function login(SupplierConnection $connection, array $config, string $cacheKey): string
    {
        $url = rtrim($config['api_base_url'], '/').$this->configResolver->otaPath('login');
        $startedAt = microtime(true);

        try {
            $pending = Http::timeout((int) $config['request_timeout_seconds'])
                ->connectTimeout((int) $config['connect_timeout_seconds'])
                ->acceptJson()
                ->asJson();

            if (! ($config['verify_tls'] ?? true)) {
                $pending = $pending->withoutVerifying();
            }

            $response = $pending->post($url, [
                'username' => $config['username'],
                'password' => $config['password'],
            ]);
        } catch (ConnectionException $exception) {
            throw new EjmiralAerostAuthException(
                'supplier_timeout',
                503,
                'EJMIRAL AEROST login timed out.',
                previous: $exception,
            );
        }

        $durationMs = (int) round((microtime(true) - $startedAt) * 1000);
        Log::channel('single')->info('ejmiral_aerost.auth.login', SensitiveDataRedactor::redact([
            'supplier_connection_id' => $connection->id,
            'http_status' => $response->status(),
            'duration_ms' => $durationMs,
        ]));

        if ($response->status() === 401 || $response->status() === 403) {
            throw new EjmiralAerostAuthException('auth_rejected', $response->status(), 'EJMIRAL AEROST login rejected.');
        }

        if ($response->status() < 200 || $response->status() >= 300) {
            throw new EjmiralAerostAuthException('auth_failed', $response->status(), 'EJMIRAL AEROST login failed.');
        }

        $payload = $response->json();
        $token = $this->extractToken(is_array($payload) ? $payload : [], (string) $response->body());
        if ($token === '') {
            throw new EjmiralAerostAuthException('auth_token_missing', 502, 'EJMIRAL AEROST login response missing bearer token.');
        }

        $ttl = (int) $config['token_ttl_seconds'];
        Cache::put($cacheKey, [
            'token' => $token,
            'expires_at' => time() + $ttl,
        ], $ttl);

        return $token;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function extractToken(array $data, string $rawBody = ''): string
    {
        foreach (['token', 'access_token', 'bearer_token', 'accessToken'] as $key) {
            $value = trim((string) ($data[$key] ?? ''));
            if ($value !== '') {
                return $this->stripBearerPrefix($value);
            }
        }

        foreach (['data.token', 'data.access_token', 'result.token'] as $path) {
            $value = trim((string) data_get($data, $path, ''));
            if ($value !== '') {
                return $this->stripBearerPrefix($value);
            }
        }

        $trimmed = trim($rawBody);
        if ($trimmed !== '' && ! str_starts_with($trimmed, '{') && ! str_contains($trimmed, ' ')) {
            return $this->stripBearerPrefix($trimmed);
        }

        return '';
    }

    private function stripBearerPrefix(string $token): string
    {
        if (str_starts_with(strtolower($token), 'bearer ')) {
            return trim(substr($token, 7));
        }

        return $token;
    }

    private function cacheKey(SupplierConnection $connection): string
    {
        return 'ejmiral_aerost_token:'.$connection->id;
    }
}
