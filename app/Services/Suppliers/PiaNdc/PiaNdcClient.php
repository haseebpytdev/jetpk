<?php

namespace App\Services\Suppliers\PiaNdc;

use App\Models\SupplierConnection;
use App\Services\Suppliers\PiaNdc\Exceptions\PiaNdcAuthException;
use App\Services\Suppliers\PiaNdc\Exceptions\PiaNdcProviderException;
use App\Services\Suppliers\PiaNdc\Exceptions\PiaNdcUnavailableException;
use App\Services\Suppliers\PiaNdc\Exceptions\PiaNdcXmlException;
use App\Support\Security\SensitiveDataRedactor;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Central SOAP/XML HTTP client for PIA Hitit Crane NDC 20.1.
 */
class PiaNdcClient
{
    public function __construct(
        private readonly PiaNdcConfigResolver $configResolver,
        private readonly PiaNdcXmlParser $xmlParser,
        private readonly PiaNdcCorrelationContext $correlationContext,
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
        $config = $this->configResolver->resolve($connection);
        $correlationId = (string) ($diagnosticContext['correlation_id'] ?? $this->correlationContext->newCorrelationId());
        $soapActionConfigured = (string) ($diagnosticContext['soap_action_override']
            ?? config('suppliers.pia_ndc.operations.'.$operation.'.soap_action', $operation));
        $soapActionSent = $this->normalizeCraneNdcSoapAction($soapActionConfigured);
        $diagnosticContext = array_merge($diagnosticContext, [
            'soap_action_configured' => $soapActionConfigured,
            'soap_action_sent' => $soapActionSent,
        ]);
        $startedAt = microtime(true);

        try {
            $response = Http::timeout((int) config('suppliers.pia_ndc.timeout_seconds', 60))
                ->connectTimeout((int) config('suppliers.pia_ndc.connect_timeout_seconds', 10))
                ->withHeaders([
                    'Content-Type' => 'text/xml; charset=utf-8',
                    'SOAPAction' => $soapActionSent,
                    $config['username_header'] => $config['username'],
                    $config['password_header'] => $config['password'],
                    'X-Correlation-ID' => $correlationId,
                ])
                ->withBody($requestXml, 'text/xml; charset=utf-8')
                ->post($config['endpoint_url']);
        } catch (ConnectionException $exception) {
            $this->logCall($connection, $config, $operation, $correlationId, $startedAt, null, $requestXml, null, 'failed', 'supplier_transport_failed', $diagnosticContext);

            throw new PiaNdcUnavailableException(
                'supplier_transport_failed',
                503,
                'Provider temporarily unavailable.',
                ['correlation_id' => $correlationId],
                $exception,
            );
        }

        $status = $response->status();
        $body = (string) $response->body();
        $durationMs = (int) round((microtime(true) - $startedAt) * 1000);

        if (in_array($status, [401, 403], true)) {
            $this->logCall($connection, $config, $operation, $correlationId, $startedAt, $status, $requestXml, $body, 'failed', 'supplier_auth_failed', $diagnosticContext);

            throw new PiaNdcAuthException(
                'supplier_auth_failed',
                $status,
                'Provider credentials were rejected.',
                $this->exceptionContext($correlationId, $status, $operation, $config['endpoint_url'], $body),
            );
        }

        if ($status < 200 || $status >= 300) {
            $this->logCall($connection, $config, $operation, $correlationId, $startedAt, $status, $requestXml, $body, 'failed', 'supplier_http_error', $diagnosticContext);

            throw new PiaNdcProviderException(
                'supplier_http_error',
                $status,
                'Provider temporarily unavailable.',
                $this->exceptionContext($correlationId, $status, $operation, $config['endpoint_url'], $body),
            );
        }

        try {
            $parsed = $this->xmlParser->parse($body);
        } catch (PiaNdcXmlException $exception) {
            $this->logCall($connection, $config, $operation, $correlationId, $startedAt, $status, $requestXml, $body, 'failed', $exception->normalizedCode, $diagnosticContext);

            throw new PiaNdcXmlException(
                $exception->normalizedCode,
                $exception->httpStatus,
                $exception->safeMessage,
                array_merge(
                    $exception->context,
                    $this->exceptionContext($correlationId, $status, $operation, $config['endpoint_url'], $body),
                ),
                $exception,
            );
        }

        if ($parsed['soap_fault'] !== null) {
            $this->logCall(
                $connection,
                $config,
                $operation,
                $correlationId,
                $startedAt,
                $status,
                $requestXml,
                $body,
                'failed',
                'soap_fault',
                $diagnosticContext,
                null,
                $parsed['soap_fault'],
            );

            throw new PiaNdcProviderException(
                'soap_fault',
                502,
                'Provider temporarily unavailable.',
                array_merge(
                    $this->exceptionContext($correlationId, $status, $operation, $config['endpoint_url'], $body),
                    [
                        'fault_code' => $parsed['soap_fault']['code'],
                        'fault_message' => $parsed['soap_fault']['message'],
                    ],
                ),
            );
        }

        if ($parsed['errors'] !== []) {
            $this->logCall(
                $connection,
                $config,
                $operation,
                $correlationId,
                $startedAt,
                $status,
                $requestXml,
                $body,
                'failed',
                'provider_error',
                $diagnosticContext,
                $parsed['errors'],
            );

            throw new PiaNdcProviderException(
                'provider_error',
                422,
                'Fare or booking is unavailable.',
                array_merge(
                    $this->exceptionContext($correlationId, $status, $operation, $config['endpoint_url'], $body),
                    ['provider_errors' => $parsed['errors']],
                ),
            );
        }

        $this->logCall($connection, $config, $operation, $correlationId, $startedAt, $status, $requestXml, $body, 'success', null, $diagnosticContext);

        $parsed['_ota_diagnostic'] = [
            'correlation_id' => $correlationId,
            'duration_ms' => $durationMs,
            'operation' => $operation,
            'http_status' => $status,
            'soap_action_configured' => $soapActionConfigured,
            'soap_action_sent' => $soapActionSent,
        ];

        return $parsed;
    }

    private function normalizeCraneNdcSoapAction(string $action): string
    {
        $action = trim($action);
        $action = trim($action, " \t\n\r\0\x0B\"'");

        if (! str_starts_with($action, 'cranendc/')) {
            $action = 'cranendc/'.$action;
        }

        return '"'.$action.'"';
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $diagnosticContext
     */
    /**
     * @param  array<string, mixed>  $diagnosticContext
     * @param  ?list<array{code: string, message: string, type: ?string}>  $providerErrors
     * @param  ?array{code: string, message: string}  $soapFault
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
        ?array $providerErrors = null,
        ?array $soapFault = null,
    ): void {
        $durationMs = (int) round((microtime(true) - $startedAt) * 1000);

        $payload = SensitiveDataRedactor::redact([
            'correlation_id' => $correlationId,
            'supplier_connection_id' => $connection->id,
            'provider' => 'pia_ndc',
            'environment' => $config['environment'],
            'operation' => $operation,
            'endpoint' => $config['endpoint_url'],
            'duration_ms' => $durationMs,
            'http_status' => $httpStatus,
            'status' => $status,
            'error_code' => $errorCode,
            'provider_errors' => $providerErrors,
            'soap_fault' => $soapFault,
            'booking_id' => $diagnosticContext['booking_id'] ?? null,
            'user_id' => $diagnosticContext['user_id'] ?? null,
            'soap_action_configured' => $diagnosticContext['soap_action_configured'] ?? null,
            'soap_action_sent' => $diagnosticContext['soap_action_sent'] ?? null,
            'request_xml' => $this->sanitizeXml($requestXml),
            'response_xml' => $responseXml !== null ? $this->sanitizeXml($responseXml) : null,
        ]);

        if ($status === 'failed') {
            Log::channel('pia-ndc')->warning('pia_ndc.provider_call', $payload);
        } else {
            Log::channel('pia-ndc')->info('pia_ndc.provider_call', $payload);
        }
    }

    /**
     * @return array{correlation_id: string, http_status: int, operation: string, endpoint: string, response_xml?: string}
     */
    private function exceptionContext(
        string $correlationId,
        int $httpStatus,
        string $operation,
        string $endpoint,
        ?string $responseXml = null,
    ): array {
        $context = [
            'correlation_id' => $correlationId,
            'http_status' => $httpStatus,
            'operation' => $operation,
            'endpoint' => $endpoint,
        ];

        if ($responseXml !== null && trim($responseXml) !== '') {
            $context['response_xml'] = $this->sanitizeXmlForDiagnostics($responseXml);
        }

        return $context;
    }

    public function sanitizeXmlForDiagnostics(string $xml): string
    {
        $redacted = preg_replace(
            '/(<(?:EmailAddressText|GivenName|Surname|Birthdate|PhoneNumber|Username|Password)>)[^<]+(<\/)/i',
            '$1[REDACTED]$2',
            $xml,
        );

        return is_string($redacted) ? $redacted : $xml;
    }

    private function sanitizeXml(string $xml): string
    {
        return $this->sanitizeXmlForDiagnostics($xml);
    }
}
