<?php

namespace App\Services\Suppliers\OneApi\Transport;

use App\Contracts\Suppliers\OneApi\OneApiSoapTransportContract;
use App\Models\SupplierConnection;
use App\Services\Suppliers\OneApi\Exceptions\OneApiTransportException;
use App\Services\Suppliers\OneApi\Exceptions\OneApiValidationException;
use App\Services\Suppliers\OneApi\Support\OneApiConfigResolver;
use App\Support\Security\SensitiveDataRedactor;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Live SOAP 1.1 transport (no fixture reads).
 */
class LiveOneApiSoapTransport implements OneApiSoapTransportContract
{
    /** @var array<string, list<string>> */
    private array $cookieJars = [];

    public function __construct(
        private readonly OneApiConfigResolver $configResolver,
        private readonly OneApiXmlParser $xmlParser,
    ) {}

    /**
     * @param  array<string, mixed>  $diagnosticContext
     * @return array<string, mixed>
     */
    public function call(
        SupplierConnection $connection,
        string $operation,
        string $requestXml,
        string $workflowSessionKey,
        array $diagnosticContext = [],
    ): array {
        $config = $this->configResolver->resolve($connection);
        $soapUrl = trim((string) $config['soap_url']);
        if ($soapUrl === '') {
            throw new OneApiValidationException('configuration_error', 422, 'SOAP URL is not configured for this connection.');
        }

        $soapAction = (string) ($diagnosticContext['soap_action_override']
            ?? config('suppliers.one_api.soap_operations.'.$operation.'.soap_action', $operation));

        $cookieHeader = $this->buildCookieHeader($workflowSessionKey);
        $startedAt = microtime(true);

        try {
            $pending = Http::timeout((int) $config['request_timeout_seconds'])
                ->connectTimeout((int) $config['connect_timeout_seconds'])
                ->withHeaders(array_filter([
                    'Content-Type' => 'text/xml; charset=utf-8',
                    'SOAPAction' => $soapAction,
                    'Cookie' => $cookieHeader !== '' ? $cookieHeader : null,
                ]))
                ->withBody($requestXml, 'text/xml; charset=utf-8');

            if (! ($config['verify_tls'] ?? true)) {
                $pending = $pending->withoutVerifying();
            }

            $response = $pending->post($soapUrl);
        } catch (ConnectionException $exception) {
            if (in_array($operation, ['book', 'modify'], true)) {
                throw new OneApiTransportException(
                    'booking_ambiguous',
                    504,
                    'Booking request outcome is ambiguous; reconciliation required.',
                    ['operation' => $operation],
                    $exception,
                );
            }

            throw new OneApiTransportException('supplier_timeout', 503, 'SOAP request timed out.', previous: $exception);
        }

        $this->captureCookies($workflowSessionKey, $response->headers());
        $body = (string) $response->body();
        $durationMs = (int) round((microtime(true) - $startedAt) * 1000);

        Log::channel('one-api')->info('one_api.soap.'.$operation, SensitiveDataRedactor::redact([
            'supplier_connection_id' => $connection->id,
            'operation' => $operation,
            'duration_ms' => $durationMs,
            'http_status' => $response->status(),
            'cookie_jar_size' => count($this->cookieJars[$workflowSessionKey] ?? []),
        ]));

        if ($response->status() < 200 || $response->status() >= 300) {
            throw new OneApiTransportException('supplier_transport_error', $response->status(), 'SOAP HTTP error.');
        }

        $parsed = $this->xmlParser->parse($body);
        $parsed['http_status'] = $response->status();
        $parsed['duration_ms'] = $durationMs;

        return $parsed;
    }

    /**
     * @param  array<string, array<int, string>>  $headers
     */
    private function captureCookies(string $sessionKey, array $headers): void
    {
        $setCookies = $headers['Set-Cookie'] ?? $headers['set-cookie'] ?? [];
        if (! is_array($setCookies)) {
            $setCookies = [$setCookies];
        }
        foreach ($setCookies as $line) {
            $pair = explode(';', (string) $line, 2)[0] ?? '';
            if ($pair !== '') {
                $this->cookieJars[$sessionKey][] = $pair;
            }
        }
        $this->cookieJars[$sessionKey] = array_values(array_unique($this->cookieJars[$sessionKey] ?? []));
    }

    private function buildCookieHeader(string $sessionKey): string
    {
        return implode('; ', $this->cookieJars[$sessionKey] ?? []);
    }

    /**
     * @return list<string>
     */
    public function cookiesForSession(string $sessionKey): array
    {
        return $this->cookieJars[$sessionKey] ?? [];
    }
}
