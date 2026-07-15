<?php

namespace App\Services\Suppliers\Iati;

use App\Models\SupplierConnection;
use App\Services\Suppliers\Iati\Exceptions\IatiAuthException;
use App\Services\Suppliers\Iati\Exceptions\IatiProviderException;
use App\Services\Suppliers\Iati\Exceptions\IatiUnavailableException;
use App\Services\Suppliers\Iati\Exceptions\IatiValidationException;
use App\Support\Security\SensitiveDataRedactor;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Central HTTP client for all IATI flight v2 API calls.
 * Auth: JWT via {@see IatiAuthService} when secret is configured; raw auth_code for /test/ping.
 * Organization-Id header is sent only when configured.
 */
class IatiClient
{
    public function __construct(
        private readonly IatiConfigResolver $configResolver,
        private readonly IatiAuthService $authService,
        private readonly IatiCorrelationContext $correlationContext,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $diagnosticContext
     * @return array<string, mixed>
     */
    public function post(
        SupplierConnection $connection,
        string $path,
        array $payload = [],
        array $diagnosticContext = [],
    ): array {
        return $this->send($connection, 'POST', $path, $payload, $diagnosticContext);
    }

    /**
     * @param  array<string, mixed>  $diagnosticContext
     * @return array<string, mixed>
     */
    public function get(
        SupplierConnection $connection,
        string $path,
        array $diagnosticContext = [],
    ): array {
        return $this->send($connection, 'GET', $path, [], $diagnosticContext);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $diagnosticContext
     * @return array<string, mixed>
     */
    public function send(
        SupplierConnection $connection,
        string $method,
        string $path,
        array $payload = [],
        array $diagnosticContext = [],
    ): array {
        $config = $this->configResolver->resolve($connection);
        $correlationId = $diagnosticContext['correlation_id'] ?? $this->correlationContext->newCorrelationId();
        $endpointName = $this->endpointName($path);
        $normalizedPath = '/'.ltrim($path, '/');
        $url = rtrim($config['flight_base'], '/').$normalizedPath;
        $startedAt = microtime(true);
        $retryCount = 0;

        Log::channel('iati')->info('iati.http.request', SensitiveDataRedactor::redact([
            'supplier_connection_id' => $connection->id,
            'endpoint' => $url,
            'method' => strtoupper($method),
            'url_path' => $normalizedPath,
            'request_context' => $diagnosticContext['request_context'] ?? null,
        ]));

        $isPing = $normalizedPath === '/test/ping';
        $token = $isPing
            ? $this->authService->getPingBearerToken($connection)
            : $this->authService->getBearerToken($connection);
        $isGet = strtoupper($method) === 'GET';
        $request = $this->http($token, $correlationId, $config['organization_id'], ! $isGet);

        try {
            $response = $isGet
                ? $request->get($url)
                : $request->post($url, $payload);
        } catch (ConnectionException $exception) {
            Log::channel('iati')->warning('iati.http.exception', [
                'supplier_connection_id' => $connection->id,
                'endpoint' => $url,
                'method' => strtoupper($method),
                'exception_class' => $exception::class,
                'exception_message' => $exception->getMessage(),
            ]);

            $this->logCall($connection, $config, $endpointName, $method, $normalizedPath, $correlationId, $startedAt, null, $payload, null, 'failed', 'supplier_transport_failed', $retryCount, $diagnosticContext);

            throw new IatiUnavailableException(
                'supplier_transport_failed',
                503,
                'IATI is temporarily unavailable. Please try again.',
                ['correlation_id' => $correlationId],
                $exception,
            );
        }

        if (! $isPing && in_array($response->status(), [401, 403], true) && trim($config['secret']) !== '') {
            $retryCount = 1;
            $this->authService->clearTokenCache($connection);
            $token = $this->authService->getBearerToken($connection, true);
            $request = $this->http($token, $correlationId, $config['organization_id'], ! $isGet);

            try {
                $response = $isGet
                    ? $request->get($url)
                    : $request->post($url, $payload);
            } catch (ConnectionException $exception) {
                Log::channel('iati')->warning('iati.http.exception', [
                    'supplier_connection_id' => $connection->id,
                    'endpoint' => $url,
                    'method' => strtoupper($method),
                    'exception_class' => $exception::class,
                    'exception_message' => $exception->getMessage(),
                    'retry_count' => $retryCount,
                ]);

                $this->logCall($connection, $config, $endpointName, $method, $normalizedPath, $correlationId, $startedAt, $response->status(), $payload, null, 'failed', 'supplier_transport_failed', $retryCount, $diagnosticContext);

                throw new IatiUnavailableException(
                    'supplier_transport_failed',
                    503,
                    'IATI is temporarily unavailable. Please try again.',
                    ['correlation_id' => $correlationId],
                    $exception,
                );
            }
        }

        $status = $response->status();
        $rawBody = (string) $response->body();
        Log::channel('iati')->info('iati.http.response', [
            'supplier_connection_id' => $connection->id,
            'endpoint' => $url,
            'method' => strtoupper($method),
            'http_status' => $status,
            'body_size' => strlen($rawBody),
        ]);

        $json = $response->json();
        $body = is_array($json) ? $json : [];
        $providerCode = $this->providerStatusCode($body);
        $durationMs = (int) round((microtime(true) - $startedAt) * 1000);

        if (in_array($status, [401, 403], true)) {
            $this->logCall($connection, $config, $endpointName, $method, $normalizedPath, $correlationId, $startedAt, $status, $payload, $body, 'failed', 'supplier_auth_failed', $retryCount, $diagnosticContext);

            throw new IatiAuthException('supplier_auth_failed', $status, 'IATI authentication failed.', ['correlation_id' => $correlationId]);
        }

        if ($status === 409 || $this->isUnavailableError($body, $providerCode)) {
            $this->logCall($connection, $config, $endpointName, $method, $normalizedPath, $correlationId, $startedAt, $status, $payload, $body, 'failed', 'offer_unavailable', $retryCount, $diagnosticContext);

            throw new IatiUnavailableException(
                'offer_unavailable',
                $status,
                $this->customerSafeMessage($body, 'This fare is no longer available.'),
                ['correlation_id' => $correlationId, 'provider_code' => $providerCode],
            );
        }

        if ($status === 422 || $this->isValidationError($body, $providerCode)) {
            $this->logCall($connection, $config, $endpointName, $method, $normalizedPath, $correlationId, $startedAt, $status, $payload, $body, 'failed', 'supplier_request_invalid', $retryCount, $diagnosticContext);

            throw new IatiValidationException(
                $this->mapUnavailableCode($body, $providerCode) ?? 'supplier_request_invalid',
                $status,
                $this->customerSafeMessage($body, 'IATI request validation failed.'),
                ['correlation_id' => $correlationId, 'provider_code' => $providerCode],
            );
        }

        if ($status >= 500) {
            $this->logCall($connection, $config, $endpointName, $method, $normalizedPath, $correlationId, $startedAt, $status, $payload, $body, 'failed', 'supplier_transport_failed', $retryCount, $diagnosticContext);

            throw new IatiUnavailableException(
                'supplier_transport_failed',
                $status,
                'IATI is temporarily unavailable. Please try again.',
                ['correlation_id' => $correlationId],
            );
        }

        if ($status < 200 || $status >= 300) {
            $this->logCall($connection, $config, $endpointName, $method, $normalizedPath, $correlationId, $startedAt, $status, $payload, $body, 'failed', 'supplier_provider_error', $retryCount, $diagnosticContext);

            throw new IatiProviderException(
                'supplier_provider_error',
                $status,
                $this->customerSafeMessage($body, 'IATI request failed.'),
                ['correlation_id' => $correlationId, 'provider_code' => $providerCode],
            );
        }

        $this->logCall($connection, $config, $endpointName, $method, $normalizedPath, $correlationId, $startedAt, $status, $payload, $body, 'success', null, $retryCount, $diagnosticContext);

        $unwrapped = $this->unwrapResult($body);

        $body['_ota_diagnostic'] = [
            'correlation_id' => $correlationId,
            'duration_ms' => $durationMs,
            'http_status' => $status,
            'raw_body_size' => strlen($rawBody),
            'provider_result_keys' => is_array($unwrapped) ? array_keys($unwrapped) : [],
            'provider_code' => $providerCode,
            'endpoint' => $endpointName,
        ];

        return $body;
    }

    /**
     * @return array<string, mixed>
     */
    public function unwrapResult(array $response): array
    {
        if (isset($response['result']) && is_array($response['result'])) {
            return $response['result'];
        }

        return $response;
    }

    private function http(string $token, string $correlationId, string $organizationId, bool $withJsonContentType = false): PendingRequest
    {
        $headers = [
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
            'X-Correlation-ID' => $correlationId,
        ];

        if (trim($organizationId) !== '') {
            $headers['Organization-Id'] = trim($organizationId);
        }

        if ($withJsonContentType) {
            $headers['Content-Type'] = 'application/json';
        }

        return Http::timeout((int) config('suppliers.iati.timeout_seconds', 60))
            ->connectTimeout((int) config('suppliers.iati.connect_timeout_seconds', 10))
            ->withHeaders($headers);
    }

    private function endpointName(string $path): string
    {
        $normalized = '/'.trim($path, '/');

        return match (true) {
            $normalized === '/search' => 'search',
            $normalized === '/fare' => 'fare',
            $normalized === '/book' => 'book',
            $normalized === '/option' => 'option',
            str_starts_with($normalized, '/option/') && str_ends_with($normalized, '/book') => 'option_book',
            str_starts_with($normalized, '/option/') && str_ends_with($normalized, '/cancel') => 'option_cancel',
            str_starts_with($normalized, '/book/') && str_ends_with($normalized, '/cancel') => 'book_cancel',
            str_starts_with($normalized, '/order/') => 'order_retrieve',
            $normalized === '/order' => 'order_list',
            $normalized === '/test/ping' => 'ping',
            $normalized === '/airport' => 'airport',
            $normalized === '/balance' => 'balance',
            default => trim(str_replace('/', '_', $normalized), '_') ?: 'unknown',
        };
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function providerStatusCode(array $body): ?string
    {
        foreach (['code', 'error_code', 'status_code'] as $key) {
            $raw = $body[$key] ?? null;
            if (is_array($raw)) {
                continue;
            }
            $value = trim((string) ($raw ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        $error = $body['error'] ?? null;
        if (is_array($error)) {
            $value = trim((string) ($error['code'] ?? $error['error_code'] ?? ''));

            return $value !== '' ? $value : null;
        }

        $value = trim((string) ($error ?? ''));
        if ($value !== '') {
            return $value;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function isValidationError(array $body, ?string $providerCode): bool
    {
        if ($providerCode !== null && str_starts_with(strtoupper($providerCode), 'V')) {
            return true;
        }

        return (bool) ($body['validation_error'] ?? false);
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function isUnavailableError(array $body, ?string $providerCode): bool
    {
        if (in_array(strtoupper((string) $providerCode), ['VA009', 'VA001', 'VA002'], true)) {
            return true;
        }

        $message = strtolower((string) ($body['message'] ?? $body['description'] ?? ''));

        return str_contains($message, 'unavailable')
            || str_contains($message, 'expired')
            || str_contains($message, 'sold out');
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function mapUnavailableCode(array $body, ?string $providerCode): ?string
    {
        if ($this->isUnavailableError($body, $providerCode)) {
            return 'offer_unavailable';
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function customerSafeMessage(array $body, string $fallback): string
    {
        $message = trim((string) ($body['message'] ?? $body['description'] ?? ''));

        if ($message === '' || strlen($message) > 200) {
            return $fallback;
        }

        if (preg_match('/token|password|secret|authorization/i', $message)) {
            return $fallback;
        }

        return $message;
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>|null  $payload
     * @param  array<string, mixed>|null  $response
     * @param  array<string, mixed>  $diagnosticContext
     */
    private function logCall(
        SupplierConnection $connection,
        array $config,
        string $endpointName,
        string $method,
        string $path,
        string $correlationId,
        float $startedAt,
        ?int $httpStatus,
        ?array $payload,
        ?array $response,
        string $status,
        ?string $errorCode,
        int $retryCount,
        array $diagnosticContext,
    ): void {
        Log::channel('iati')->info('iati.provider_call', SensitiveDataRedactor::redact([
            'correlation_id' => $correlationId,
            'supplier_connection_id' => $connection->id,
            'provider' => 'iati',
            'environment' => $config['environment'],
            'endpoint' => $endpointName,
            'http_method' => strtoupper($method),
            'url_path' => $path,
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'http_status' => $httpStatus,
            'provider_status' => is_array($response) ? $this->providerStatusCode($response) : null,
            'request_payload' => $payload,
            'response_payload' => $response,
            'booking_id' => $diagnosticContext['booking_id'] ?? null,
            'user_id' => $diagnosticContext['user_id'] ?? null,
            'status' => $status,
            'error_code' => $errorCode,
            'retry_count' => $retryCount,
        ]));
    }
}
