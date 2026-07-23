<?php

namespace App\Services\Suppliers\OneApi\Transport;

use App\Models\SupplierConnection;
use App\Services\Suppliers\OneApi\Authentication\OneApiAuthService;
use App\Services\Suppliers\OneApi\Exceptions\OneApiAuthException;
use App\Services\Suppliers\OneApi\Exceptions\OneApiTransportException;
use App\Services\Suppliers\OneApi\Support\OneApiConfigResolver;
use App\Services\Suppliers\OneApi\Support\OneApiCorrelationContext;
use App\Support\Security\SensitiveDataRedactor;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OneApiRestClient
{
    public function __construct(
        private readonly OneApiConfigResolver $configResolver,
        private readonly OneApiAuthService $authService,
        private readonly OneApiCorrelationContext $correlationContext,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function postSearch(SupplierConnection $connection, array $payload, ?string $correlationId = null): array
    {
        $config = $this->configResolver->resolve($connection);
        $correlationId = $correlationId ?: $this->correlationContext->newCorrelationId();
        $url = (string) $config['rest_search_url'];
        $startedAt = microtime(true);

        return $this->postJson($connection, $config, $url, $payload, $correlationId, (int) $config['search_timeout_seconds'], $startedAt, 'search');
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function postJson(
        SupplierConnection $connection,
        array $config,
        string $url,
        array $payload,
        string $correlationId,
        int $timeoutSeconds,
        float $startedAt,
        string $operation,
        bool $retryAuth = true,
    ): array {
        try {
            $token = $this->authService->getAccessToken($connection);
            $pending = Http::timeout($timeoutSeconds)
                ->connectTimeout((int) $config['connect_timeout_seconds'])
                ->acceptJson()
                ->asJson()
                ->withHeaders([
                    'Authorization' => 'Bearer '.$token,
                    'X-Correlation-ID' => $correlationId,
                ]);

            if (! ($config['verify_tls'] ?? true)) {
                $pending = $pending->withoutVerifying();
            }

            $response = $pending->post($url, $payload);
        } catch (ConnectionException $exception) {
            throw new OneApiTransportException('supplier_timeout', 503, 'One API request timed out.', previous: $exception);
        }

        $status = $response->status();
        if (in_array($status, [401, 403], true) && $retryAuth) {
            $this->authService->clearTokenCache($connection);
            $this->authService->getAccessToken($connection, true);

            return $this->postJson($connection, $config, $url, $payload, $correlationId, $timeoutSeconds, $startedAt, $operation, false);
        }

        if ($status < 200 || $status >= 300) {
            throw new OneApiTransportException('supplier_transport_error', $status, 'One API request failed.');
        }

        $data = $response->json();
        if (! is_array($data)) {
            throw new OneApiTransportException('malformed_response', 502, 'Invalid JSON response from One API.');
        }

        $durationMs = (int) round((microtime(true) - $startedAt) * 1000);
        Log::channel('one-api')->info('one_api.rest.'.$operation, SensitiveDataRedactor::redact([
            'supplier_connection_id' => $connection->id,
            'operation' => $operation,
            'duration_ms' => $durationMs,
            'http_status' => $status,
            'correlation_id' => $correlationId,
        ]));

        $data['_ota_diagnostic'] = [
            'correlation_id' => $correlationId,
            'duration_ms' => $durationMs,
            'http_status' => $status,
        ];

        return $data;
    }
}
