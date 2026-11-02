<?php

namespace App\Services\Suppliers\EjmiralAerost;

use App\Models\SupplierConnection;
use App\Services\Suppliers\EjmiralAerost\Exceptions\EjmiralAerostAuthException;
use App\Services\Suppliers\EjmiralAerost\Exceptions\EjmiralAerostException;
use App\Support\Security\SensitiveDataRedactor;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Central POST client for AEROST OTA v1 endpoints under {{API_URL}}/api/ota/v1/.
 */
class EjmiralAerostClient
{
    public function __construct(
        private readonly EjmiralAerostConfigResolver $configResolver,
        private readonly EjmiralAerostAuthService $authService,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $diagnosticContext
     * @return array{http_status: int, body: array<string, mixed>, raw: string, duration_ms: int}
     */
    public function post(
        SupplierConnection $connection,
        string $pathSuffix,
        array $payload = [],
        array $diagnosticContext = [],
        bool $allowRetryOnUnauthorized = true,
    ): array {
        $config = $this->configResolver->resolve($connection);
        $path = $this->configResolver->otaPath($pathSuffix);
        $url = rtrim($config['api_base_url'], '/').$path;
        $token = $this->authService->getBearerToken($connection);
        $startedAt = microtime(true);

        Log::channel('single')->info('ejmiral_aerost.http.request', SensitiveDataRedactor::redact([
            'supplier_connection_id' => $connection->id,
            'path' => $path,
            'operation' => $diagnosticContext['operation'] ?? $pathSuffix,
        ]));

        try {
            $pending = Http::timeout((int) $config['request_timeout_seconds'])
                ->connectTimeout((int) $config['connect_timeout_seconds'])
                ->withToken($token)
                ->acceptJson()
                ->asJson();

            if (! ($config['verify_tls'] ?? true)) {
                $pending = $pending->withoutVerifying();
            }

            $response = $pending->post($url, $payload);
        } catch (ConnectionException $exception) {
            throw new EjmiralAerostException(
                'supplier_timeout',
                503,
                'EJMIRAL AEROST request timed out.',
                ['path' => $path],
                $exception,
            );
        }

        if ($response->status() === 401 && $allowRetryOnUnauthorized) {
            $this->authService->clearTokenCache($connection);
            $this->authService->getBearerToken($connection, true);

            return $this->post($connection, $pathSuffix, $payload, $diagnosticContext, false);
        }

        if ($response->status() === 401 || $response->status() === 403) {
            throw new EjmiralAerostAuthException(
                'unauthorized',
                $response->status(),
                'EJMIRAL AEROST request unauthorized after refresh.',
                ['path' => $path],
            );
        }

        $durationMs = (int) round((microtime(true) - $startedAt) * 1000);
        $json = $response->json();
        $body = is_array($json) ? $json : [];

        Log::channel('single')->info('ejmiral_aerost.http.response', SensitiveDataRedactor::redact([
            'supplier_connection_id' => $connection->id,
            'path' => $path,
            'http_status' => $response->status(),
            'duration_ms' => $durationMs,
        ]));

        if ($response->status() < 200 || $response->status() >= 300) {
            throw new EjmiralAerostException(
                'supplier_http_error',
                $response->status(),
                'EJMIRAL AEROST HTTP error.',
                ['path' => $path],
            );
        }

        return [
            'http_status' => $response->status(),
            'body' => $body,
            'raw' => (string) $response->body(),
            'duration_ms' => $durationMs,
        ];
    }
}
