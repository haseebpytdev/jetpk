<?php

namespace App\Services\Suppliers\AirBlue;

use App\Models\SupplierConnection;
use App\Services\Suppliers\AirBlue\Exceptions\AirBlueAuthException;
use App\Services\Suppliers\AirBlue\Exceptions\AirBlueProviderException;
use App\Services\Suppliers\AirBlue\Exceptions\AirBlueUnavailableException;
use App\Services\Suppliers\AirBlue\Exceptions\AirBlueValidationException;
use App\Services\Suppliers\AirBlue\Exceptions\AirBlueXmlException;
use App\Support\Security\SensitiveDataRedactor;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Central SOAP/XML HTTP client for AirBlue Zapways OTA (v2.0 and v3.0).
 */
class AirBlueClient
{
    public function __construct(
        private readonly AirBlueConfigResolver $configResolver,
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
        $this->configResolver->resolve($connection);

        return $this->callOta($connection, $operation, $requestXml, $diagnosticContext);
    }

    /**
     * @deprecated AirBlue Crane NDC is retired.
     *
     * @param  array<string, mixed>  $diagnosticContext
     * @return array<string, mixed>
     */
    public function callNdc(
        SupplierConnection $connection,
        string $operation,
        string $requestXml,
        array $diagnosticContext = [],
    ): array {
        throw new AirBlueValidationException(
            'deprecated_channel',
            422,
            'AirBlue Crane NDC is no longer supported. Use PIA NDC (pia_ndc) for Hitit Crane NDC 20.1.',
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
        if (! (bool) config('suppliers.airblue.external_calls_enabled', false)) {
            throw new AirBlueValidationException(
                'external_calls_disabled',
                503,
                'AirBlue external network calls are disabled.',
                [
                    'operation' => $operation,
                    'supplier_connection_id' => $connection->id,
                ],
            );
        }

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
            $faultSummary = $this->summarizeSoapFault($body);

            throw new AirBlueProviderException(
                'supplier_http_error',
                $status,
                'Provider temporarily unavailable.',
                [
                    'correlation_id' => $correlationId,
                    'http_status' => $status,
                    'fault_class' => $faultSummary['fault_class'],
                    'fault_snippet' => $faultSummary['fault_snippet'],
                ],
            );
        }

        try {
            $parsed = $this->otaXmlParser->parse($body);
        } catch (AirBlueXmlException $exception) {
            $this->logCall($connection, $config, $operation, $correlationId, $startedAt, $status, $requestXml, $body, 'failed', $exception->normalizedCode, $diagnosticContext);

            throw $exception;
        }

        if ($parsed['soap_fault'] !== null) {
            $this->logCall($connection, $config, $operation, $correlationId, $startedAt, $status, $requestXml, $body, 'failed', 'soap_fault', $diagnosticContext);
            $faultSummary = $this->summarizeSoapFault($body);

            throw new AirBlueProviderException(
                'soap_fault',
                502,
                'Provider temporarily unavailable.',
                [
                    'correlation_id' => $correlationId,
                    'fault_code' => $parsed['soap_fault']['code'],
                    'fault_class' => $faultSummary['fault_class'],
                    'fault_snippet' => $faultSummary['fault_snippet'],
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

        $keyPath = trim((string) ($config['tls_key_path'] ?? ''));
        $sslKey = ($keyPath !== '' && is_file($keyPath)) ? $keyPath : $certPath;

        return $client->withOptions([
            'verify' => true,
            'cert' => $certPath,
            'ssl_key' => $sslKey,
        ]);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function resolveOtaSoapAction(string $operation, array $config): string
    {
        $protocol = \App\Enums\AirBlueZapwaysProtocolVersion::fromCredentials([
            'protocol_version' => (string) ($config['protocol_version'] ?? '2.0'),
        ]);
        $ops = $this->configResolver->protocolOperationsConfig($protocol);
        $operationConfig = is_array($ops[$operation] ?? null) ? $ops[$operation] : [];

        if ($operation === 'read') {
            $soapAction = trim((bool) ($config['is_test'] ?? false)
                ? (string) ($operationConfig['soap_action_test'] ?? '')
                : (string) ($operationConfig['soap_action_live'] ?? ''));
            if ($soapAction === '') {
                throw new AirBlueValidationException(
                    'missing_soap_action',
                    422,
                    sprintf('SOAPAction for AirBlue operation "%s" on protocol %s is not configured.', $operation, $protocol->value),
                );
            }

            return $soapAction;
        }

        $soapAction = trim((string) ($operationConfig['soap_action'] ?? ''));
        if ($soapAction === '') {
            throw new AirBlueValidationException(
                'missing_soap_action',
                422,
                sprintf('SOAPAction for AirBlue operation "%s" on protocol %s is not configured.', $operation, $protocol->value),
            );
        }

        return $soapAction;
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

        $logPath = storage_path('logs/airblue.log');
        $logDir = dirname($logPath);
        if (! is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }
        if (! is_file($logPath)) {
            @touch($logPath);
        }

        $faultSummary = $responseXml !== null ? $this->summarizeSoapFault($responseXml) : [
            'fault_class' => null,
            'fault_snippet' => null,
        ];

        Log::channel('air-blue')->info('airblue.provider_call', SensitiveDataRedactor::redact([
            'correlation_id' => $correlationId,
            'supplier_connection_id' => $connection->id,
            'provider' => 'airblue',
            'api_channel' => $config['api_channel'] ?? null,
            'protocol_version' => $config['protocol_version'] ?? null,
            'environment' => $config['environment'] ?? null,
            'operation' => $operation,
            'endpoint' => $config['endpoint_url'] ?? null,
            'duration_ms' => $durationMs,
            'http_status' => $httpStatus,
            'status' => $status,
            'error_code' => $errorCode,
            'fault_class' => $faultSummary['fault_class'],
            'fault_snippet' => $faultSummary['fault_snippet'],
            'booking_id' => $diagnosticContext['booking_id'] ?? null,
            'user_id' => $diagnosticContext['user_id'] ?? null,
            'request_xml' => $this->sanitizeXml($requestXml),
            'response_xml' => $responseXml !== null ? $this->sanitizeXml($responseXml) : null,
        ]));
    }

    /**
     * Extract a redacted, classification-only SOAP fault summary for diagnostics.
     *
     * @return array{fault_class: ?string, fault_snippet: ?string}
     */
    private function summarizeSoapFault(string $body): array
    {
        $fault = '';
        if (preg_match('/<(?:[\w]+:)?faultstring[^>]*>([^<]+)<\/(?:[\w]+:)?faultstring>/i', $body, $m)) {
            $fault = html_entity_decode(trim($m[1]), ENT_QUOTES | ENT_XML1);
        }

        if ($fault === '') {
            return ['fault_class' => null, 'fault_snippet' => null];
        }

        $fault = preg_replace('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', '[REDACTED_EMAIL]', $fault) ?? $fault;
        $fault = preg_replace('/[A-Za-z0-9+\/=]{40,}/', '[REDACTED]', $fault) ?? $fault;

        $class = match (true) {
            str_contains($fault, 'DocumentTypeGender') => 'soap_client_schema_gender',
            str_contains($fault, 'Object reference not set') => 'provider_nre',
            str_contains($fault, 'Instance validation error') => 'soap_client_schema_validation',
            default => 'soap_fault',
        };

        return [
            'fault_class' => $class,
            'fault_snippet' => substr($fault, 0, 220),
        ];
    }

    private function sanitizeXml(string $xml): string
    {
        $redacted = preg_replace('/(<(?:[\w]+:)?(?:EmailAddressText|GivenName|Surname|Birthdate|BirthDate|PhoneNumber|DocID|MessagePassword|Email)>)[^<]+(<\/)/i', '$1[REDACTED]$2', $xml);
        $redacted = is_string($redacted) ? preg_replace('/(MessagePassword=")[^"]+(")/i', '$1[REDACTED]$2', $redacted) : $xml;
        $redacted = is_string($redacted) ? preg_replace('/(ERSP_UserID=")[^"]+(")/i', '$1[REDACTED]$2', $redacted) : $xml;
        $redacted = is_string($redacted) ? preg_replace('/(BirthDate=")[^"]+(")/i', '$1[REDACTED]$2', $redacted) : $xml;
        $redacted = is_string($redacted) ? preg_replace('/(DocID=")[^"]+(")/i', '$1[REDACTED]$2', $redacted) : $xml;
        $redacted = is_string($redacted) ? preg_replace('/(\bID=")(?![0-9]{1,3}")[^"]+(")/i', '$1[REDACTED]$2', $redacted) : $xml;
        $redacted = is_string($redacted) ? preg_replace('/(<ota:Email>)[^<]+(<\/ota:Email>)/i', '$1[REDACTED]$2', $redacted) : $xml;
        $redacted = is_string($redacted) ? preg_replace('/(PhoneNumber=")[^"]+(")/i', '$1[REDACTED]$2', $redacted) : $xml;

        return is_string($redacted) ? $redacted : $xml;
    }
}
