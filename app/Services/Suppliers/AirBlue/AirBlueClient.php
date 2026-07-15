<?php

namespace App\Services\Suppliers\AirBlue;

use App\Enums\AirBlueApiChannel;
use App\Models\SupplierConnection;
use App\Services\Suppliers\AirBlue\Exceptions\AirBlueAuthException;
use App\Services\Suppliers\AirBlue\Exceptions\AirBlueProviderException;
use App\Services\Suppliers\AirBlue\Exceptions\AirBlueUnavailableException;
use App\Services\Suppliers\AirBlue\Exceptions\AirBlueXmlException;
use App\Support\Security\SensitiveDataRedactor;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Central SOAP/XML HTTP client for AirBlue (Crane NDC 20.1 and Zapways OTA v2.06).
 */
class AirBlueClient
{
    public function __construct(
        private readonly AirBlueConfigResolver $configResolver,
        private readonly AirBlueXmlParser $ndcXmlParser,
        private readonly AirBlueOtaXmlParser $otaXmlParser,
        private readonly AirBlueCorrelationContext $correlationContext,
    ) {}

    /**
     * @param  array<string, mixed>  $diagnosticContext
     * @return array<string, mixed>
     */
    public function call(
        SupplierConnection $connection,
        string $operation,
        string $requestXml,
        array $diagnosticContext = [],
    ): array {
        $channel = $this->configResolver->apiChannel($connection);

        return $channel === AirBlueApiChannel::ZapwaysOta
            ? $this->callOta($connection, $operation, $requestXml, $diagnosticContext)
            : $this->callNdc($connection, $operation, $requestXml, $diagnosticContext);
    }

    /**
     * @param  array<string, mixed>  $diagnosticContext
     * @return array<string, mixed>
     */
    public function callNdc(
        SupplierConnection $connection,
        string $operation,
        string $requestXml,
        array $diagnosticContext = [],
    ): array {
        $config = $this->configResolver->resolveNdc($connection);
        $correlationId = (string) ($diagnosticContext['correlation_id'] ?? $this->correlationContext->newCorrelationId());
        $soapAction = (string) config('suppliers.airblue.ndc_operations.'.$operation.'.soap_action', $operation);
        $startedAt = microtime(true);

        try {
            $response = $this->baseHttpClient()
                ->withHeaders([
                    'Content-Type' => 'text/xml; charset=utf-8',
                    'SOAPAction' => $soapAction,
                    $config['username_header'] => $config['username'],
                    $config['password_header'] => $config['password'],
                    'X-Correlation-ID' => $correlationId,
                ])
                ->withBody($requestXml, 'text/xml; charset=utf-8')
                ->post($config['endpoint_url']);
        } catch (ConnectionException $exception) {
            $this->logCall($connection, $config, $operation, $correlationId, $startedAt, null, $requestXml, null, 'failed', 'supplier_transport_failed', $diagnosticContext);

            throw new AirBlueUnavailableException(
                'supplier_transport_failed',
                503,
                'Provider temporarily unavailable.',
                ['correlation_id' => $correlationId],
                $exception,
            );
        }

        return $this->finalizeResponse(
            $response->status(),
            (string) $response->body(),
            $connection,
            $config,
            $operation,
            $correlationId,
            $startedAt,
            $requestXml,
            $diagnosticContext,
            $this->ndcXmlParser,
        );
    }

    /**
     * @param  array<string, mixed>  $diagnosticContext
     * @return array<string, mixed>
     */
    public function callOta(
        SupplierConnection $connection,
        string $operation,
        string $requestXml,
        array $diagnosticContext = [],
    ): array {
        $config = $this->configResolver->resolveOta($connection);
        $correlationId = (string) ($diagnosticContext['correlation_id'] ?? $this->correlationContext->newCorrelationId());
        $soapAction = $this->resolveOtaSoapAction($operation, $config);
        $startedAt = microtime(true);

        try {
            $client = $this->baseHttpClient()
                ->withHeaders([
                    'Content-Type' => 'text/xml; charset=utf-8',
                    'SOAPAction' => $soapAction,
                    'X-Correlation-ID' => $correlationId,
                ])
                ->withBody($requestXml, 'text/xml; charset=utf-8');

            $client = $this->applyOtaTlsOptions($client, $config);
            $response = $client->post($config['endpoint_url']);
        } catch (ConnectionException $exception) {
            $this->logCall($connection, $config, $operation, $correlationId, $startedAt, null, $requestXml, null, 'failed', 'supplier_transport_failed', $diagnosticContext);

            throw new AirBlueUnavailableException(
                'supplier_transport_failed',
                503,
                'Provider temporarily unavailable.',
                ['correlation_id' => $correlationId],
                $exception,
            );
        }

        return $this->finalizeResponse(
            $response->status(),
            (string) $response->body(),
            $connection,
            $config,
            $operation,
            $correlationId,
            $startedAt,
            $requestXml,
            $diagnosticContext,
            $this->otaXmlParser,
        );
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $diagnosticContext
     * @return array<string, mixed>
     */
    private function finalizeResponse(
        int $status,
        string $body,
        SupplierConnection $connection,
        array $config,
        string $operation,
        string $correlationId,
        float $startedAt,
        string $requestXml,
        array $diagnosticContext,
        AirBlueXmlParser|AirBlueOtaXmlParser $parser,
    ): array {
        $durationMs = (int) round((microtime(true) - $startedAt) * 1000);

        if (in_array($status, [401, 403], true)) {
            $this->logCall($connection, $config, $operation, $correlationId, $startedAt, $status, $requestXml, $body, 'failed', 'supplier_auth_failed', $diagnosticContext);

            throw new AirBlueAuthException(
                'supplier_auth_failed',
                $status,
                'Provider credentials were rejected.',
                ['correlation_id' => $correlationId],
            );
        }

        if ($status < 200 || $status >= 300) {
            $this->logCall($connection, $config, $operation, $correlationId, $startedAt, $status, $requestXml, $body, 'failed', 'supplier_http_error', $diagnosticContext);

            throw new AirBlueProviderException(
                'supplier_http_error',
                $status,
                'Provider temporarily unavailable.',
                ['correlation_id' => $correlationId, 'http_status' => $status],
            );
        }

        try {
            $parsed = $parser->parse($body);
        } catch (AirBlueXmlException $exception) {
            $this->logCall($connection, $config, $operation, $correlationId, $startedAt, $status, $requestXml, $body, 'failed', $exception->normalizedCode, $diagnosticContext);

            throw $exception;
        }

        if ($parsed['soap_fault'] !== null) {
            $this->logCall($connection, $config, $operation, $correlationId, $startedAt, $status, $requestXml, $body, 'failed', 'soap_fault', $diagnosticContext);

            throw new AirBlueProviderException(
                'soap_fault',
                502,
                'Provider temporarily unavailable.',
                [
                    'correlation_id' => $correlationId,
                    'fault_code' => $parsed['soap_fault']['code'],
                ],
            );
        }

        if ($parsed['errors'] !== []) {
            $this->logCall($connection, $config, $operation, $correlationId, $startedAt, $status, $requestXml, $body, 'failed', 'provider_error', $diagnosticContext);

            throw new AirBlueProviderException(
                'provider_error',
                422,
                'Fare or booking is unavailable.',
                [
                    'correlation_id' => $correlationId,
                    'provider_errors' => $parsed['errors'],
                ],
            );
        }

        $this->logCall($connection, $config, $operation, $correlationId, $startedAt, $status, $requestXml, $body, 'success', null, $diagnosticContext);

        $parsed['_ota_diagnostic'] = [
            'correlation_id' => $correlationId,
            'duration_ms' => $durationMs,
            'operation' => $operation,
            'http_status' => $status,
            'api_channel' => $config['api_channel'] ?? null,
        ];

        return $parsed;
    }

    private function baseHttpClient(): PendingRequest
    {
        return Http::timeout((int) config('suppliers.airblue.timeout_seconds', 60))
            ->connectTimeout((int) config('suppliers.airblue.connect_timeout_seconds', 10));
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function applyOtaTlsOptions(PendingRequest $client, array $config): PendingRequest
    {
        $certPath = trim((string) ($config['tls_cert_path'] ?? ''));
        if ($certPath === '' || ! is_file($certPath)) {
            return $client;
        }

        return $client->withOptions([
            'verify' => true,
            'cert' => $certPath,
            'ssl_key' => $certPath,
        ]);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function resolveOtaSoapAction(string $operation, array $config): string
    {
        $ops = (array) config('suppliers.airblue.ota_operations.'.$operation, []);
        if ($operation === 'read') {
            return (bool) ($config['is_test'] ?? false)
                ? (string) ($ops['soap_action_test'] ?? 'https://ota.qa.zapways.com/Read')
                : (string) ($ops['soap_action_live'] ?? 'https://ota.zapways.com/Read');
        }

        return (string) ($ops['soap_action'] ?? $operation);
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $diagnosticContext
     */
    private function logCall(
        SupplierConnection $connection,
        array $config,
        string $operation,
        string $correlationId,
        float $startedAt,
        ?int $httpStatus,
        string $requestXml,
        ?string $responseXml,
        string $status,
        ?string $errorCode,
        array $diagnosticContext,
    ): void {
        $durationMs = (int) round((microtime(true) - $startedAt) * 1000);

        Log::channel('air-blue')->info('airblue.provider_call', SensitiveDataRedactor::redact([
            'correlation_id' => $correlationId,
            'supplier_connection_id' => $connection->id,
            'provider' => 'airblue',
            'api_channel' => $config['api_channel'] ?? null,
            'environment' => $config['environment'] ?? null,
            'operation' => $operation,
            'endpoint' => $config['endpoint_url'] ?? null,
            'duration_ms' => $durationMs,
            'http_status' => $httpStatus,
            'status' => $status,
            'error_code' => $errorCode,
            'booking_id' => $diagnosticContext['booking_id'] ?? null,
            'user_id' => $diagnosticContext['user_id'] ?? null,
            'request_xml' => $this->sanitizeXml($requestXml),
            'response_xml' => $responseXml !== null ? $this->sanitizeXml($responseXml) : null,
        ]));
    }

    private function sanitizeXml(string $xml): string
    {
        $redacted = preg_replace('/(<(?:EmailAddressText|GivenName|Surname|Birthdate|PhoneNumber|DocID|MessagePassword)>)[^<]+(<\/)/i', '$1[REDACTED]$2', $xml);
        $redacted = is_string($redacted) ? preg_replace('/(MessagePassword=")[^"]+(")/i', '$1[REDACTED]$2', $redacted) : $xml;

        return is_string($redacted) ? $redacted : $xml;
    }
}
