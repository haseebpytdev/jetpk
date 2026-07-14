<?php

namespace App\Console\Commands;

use App\Data\FlightSearchRequestData;
use App\Enums\SupplierProvider;
use App\Models\SupplierConnection;
use App\Services\Suppliers\PiaNdc\PiaNdcConfigResolver;
use App\Services\Suppliers\PiaNdc\PiaNdcCorrelationContext;
use App\Services\Suppliers\PiaNdc\PiaNdcResponseNormalizer;
use App\Services\Suppliers\PiaNdc\PiaNdcXmlBuilder;
use App\Services\Suppliers\PiaNdc\PiaNdcXmlParser;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Throwable;

class PiaNdcAuthHeaderProbeCommand extends Command
{
    protected $signature = 'pia-ndc:auth-header-probe
        {--connection= : Supplier connection ID}
        {--from=ISB : Origin}
        {--to=KHI : Destination}
        {--date=2026-07-03 : Departure YYYY-MM-DD}
        {--currency=PKR : Currency code}
        {--legacy-body-auth-experiment : Diagnostic-only legacy Crane OTA clientInformation body auth (not for production)}';

    protected $description = 'Probe PIA NDC HTTP auth header name pairs using stored SupplierConnection credentials';

    /** @var list<array{0: string, 1: string}> */
    private const HEADER_PAIRS = [
        ['userName', 'password'],
        ['username', 'password'],
        ['Username', 'Password'],
        ['UserName', 'Password'],
        ['USERNAME', 'PASSWORD'],
        ['USER_NAME', 'PASSWORD'],
        ['login', 'password'],
    ];

    public function handle(
        PiaNdcConfigResolver $configResolver,
        PiaNdcXmlBuilder $xmlBuilder,
        PiaNdcXmlParser $xmlParser,
        PiaNdcResponseNormalizer $responseNormalizer,
        PiaNdcCorrelationContext $correlationContext,
    ): int {
        $connection = $this->resolveConnection();
        if ($connection === null) {
            $this->error('No PIA NDC SupplierConnection found.');

            return self::FAILURE;
        }

        $config = $configResolver->resolve($connection);
        $request = FlightSearchRequestData::fromArray([
            'origin' => strtoupper((string) $this->option('from')),
            'destination' => strtoupper((string) $this->option('to')),
            'depart_date' => (string) $this->option('date'),
            'adults' => 1,
            'children' => 0,
            'infants' => 0,
            'currency' => strtoupper((string) $this->option('currency')),
            'trip_type' => 'one_way',
        ]);
        $requestXml = $xmlBuilder->buildAirShoppingRequest($request, $config);
        $defaultSoapAction = (string) config('suppliers.pia_ndc.operations.air_shopping.soap_action', 'doAirShopping');

        $this->line('connection_id='.$connection->id);
        $this->line('endpoint='.$config['endpoint_url']);
        $this->line('configured_username_header='.$config['username_header']);
        $this->line('configured_password_header='.$config['password_header']);
        $this->line('note=Binham legacy module uses clientInformation.userName/password with CraneOTAService, but this NDC probe only tests HTTP header names for CraneNDCService.');
        $this->newLine();

        foreach (self::HEADER_PAIRS as [$usernameHeader, $passwordHeader]) {
            $correlationId = $correlationContext->newCorrelationId();
            $result = $this->evaluateResponse(
                $this->postSoapRequest(
                    $config['endpoint_url'],
                    $requestXml,
                    $correlationId,
                    [
                        'Content-Type' => 'text/xml; charset=utf-8',
                        'SOAPAction' => $defaultSoapAction,
                        $usernameHeader => $config['username'],
                        $passwordHeader => $config['password'],
                    ],
                ),
                $correlationId,
                $xmlParser,
                $responseNormalizer,
                $connection,
            );

            $this->printAttemptResult('custom_header', $usernameHeader.' / '.$passwordHeader, $correlationId, $result);

            if ($result['success']) {
                if ($usernameHeader === 'userName' && $passwordHeader === 'password') {
                    $this->info('recommended_username_header=userName');
                    $this->info('recommended_password_header=password');
                } else {
                    $this->info('recommended_username_header='.$usernameHeader);
                    $this->info('recommended_password_header='.$passwordHeader);
                }

                return self::SUCCESS;
            }
        }

        foreach ($this->additionalAuthModes($config, $defaultSoapAction) as $mode) {
            $correlationId = $correlationContext->newCorrelationId();
            $result = $this->evaluateResponse(
                $this->postSoapRequest($config['endpoint_url'], $requestXml, $correlationId, $mode['headers']),
                $correlationId,
                $xmlParser,
                $responseNormalizer,
                $connection,
            );

            $this->printAttemptResult($mode['auth_mode'], $mode['header_pair'], $correlationId, $result);

            if ($result['success']) {
                $this->printSuccessRecommendations($mode);

                return self::SUCCESS;
            }
        }

        if ($this->option('legacy-body-auth-experiment')) {
            $this->warn('legacy-body-auth-experiment=enabled (diagnostic-only; legacy Crane OTA clientInformation in SOAP body — not NDC production flow)');
            $correlationId = $correlationContext->newCorrelationId();
            $legacyXml = $this->injectLegacyClientInformation(
                $requestXml,
                $config['agency_id'],
                $config['username'],
                $config['password'],
            );
            $result = $this->evaluateResponse(
                $this->postSoapRequest($config['endpoint_url'], $legacyXml, $correlationId, [
                    'Content-Type' => 'text/xml; charset=utf-8',
                    'SOAPAction' => $defaultSoapAction,
                ]),
                $correlationId,
                $xmlParser,
                $responseNormalizer,
                $connection,
            );

            $this->printAttemptResult(
                'legacy-body-auth-experiment',
                'legacy-body:clientInformation.userName / clientInformation.password',
                $correlationId,
                $result,
            );

            if ($result['success']) {
                $this->warn('legacy-body-auth-experiment succeeded — do not enable in production without explicit ops approval.');

                return self::SUCCESS;
            }
        }

        $this->warn('No auth mode succeeded. Stored credentials were not modified.');

        return self::FAILURE;
    }

    /**
     * @param  array<string, mixed>  $config
     * @return list<array{auth_mode: string, header_pair: ?string, headers: array<string, string>, recommended_soap_action: ?string, uses_basic_auth: bool}>
     */
    private function additionalAuthModes(array $config, string $defaultSoapAction): array
    {
        $basicAuthorization = 'Basic '.base64_encode($config['username'].':'.$config['password']);
        $xmlContentType = 'text/xml;charset=UTF-8';
        $usernameHeader = (string) $config['username_header'];
        $passwordHeader = (string) $config['password_header'];

        return [
            [
                'auth_mode' => 'basic_auth',
                'header_pair' => 'Authorization: Basic',
                'headers' => [
                    'Authorization' => $basicAuthorization,
                    'Content-Type' => $xmlContentType,
                    'SOAPAction' => $defaultSoapAction,
                ],
                'recommended_soap_action' => null,
                'uses_basic_auth' => true,
            ],
            [
                'auth_mode' => 'basic_auth_plus_soapaction_empty',
                'header_pair' => 'Authorization: Basic',
                'headers' => [
                    'Authorization' => $basicAuthorization,
                    'Content-Type' => $xmlContentType,
                    'SOAPAction' => '',
                ],
                'recommended_soap_action' => '',
                'uses_basic_auth' => true,
            ],
            [
                'auth_mode' => 'basic_auth_plus_soapaction_method',
                'header_pair' => 'Authorization: Basic',
                'headers' => [
                    'Authorization' => $basicAuthorization,
                    'Content-Type' => $xmlContentType,
                    'SOAPAction' => 'doAirShopping',
                ],
                'recommended_soap_action' => 'doAirShopping',
                'uses_basic_auth' => true,
            ],
            [
                'auth_mode' => 'custom_username_password_plus_soapaction_empty',
                'header_pair' => $usernameHeader.' / '.$passwordHeader,
                'headers' => [
                    'Content-Type' => $xmlContentType,
                    'SOAPAction' => '',
                    $usernameHeader => (string) $config['username'],
                    $passwordHeader => (string) $config['password'],
                ],
                'recommended_soap_action' => '',
                'uses_basic_auth' => false,
            ],
            [
                'auth_mode' => 'custom_username_password_plus_soapaction_method',
                'header_pair' => $usernameHeader.' / '.$passwordHeader,
                'headers' => [
                    'Content-Type' => $xmlContentType,
                    'SOAPAction' => 'doAirShopping',
                    $usernameHeader => (string) $config['username'],
                    $passwordHeader => (string) $config['password'],
                ],
                'recommended_soap_action' => 'doAirShopping',
                'uses_basic_auth' => false,
            ],
        ];
    }

    /**
     * @param  array{
     *     auth_mode: string,
     *     header_pair: ?string,
     *     headers: array<string, string>,
     *     recommended_soap_action: ?string,
     *     uses_basic_auth: bool
     * }  $mode
     */
    private function printSuccessRecommendations(array $mode): void
    {
        if ($mode['uses_basic_auth']) {
            $this->info('recommended_auth_mode=basic_auth');
        }

        if ($mode['recommended_soap_action'] !== null) {
            $this->info('recommended_soap_action='.$mode['recommended_soap_action']);
        }
    }

    /**
     * @param  array{
     *     http_status: int|string,
     *     provider_error_code: string,
     *     provider_error_message: string,
     *     success: bool
     * }  $result
     */
    private function printAttemptResult(string $authMode, ?string $headerPair, string $correlationId, array $result): void
    {
        $this->line('auth_mode='.$authMode);
        if ($headerPair !== null && $headerPair !== '') {
            $this->line('header_pair='.$headerPair);
        }
        $this->line('http_status='.$result['http_status']);
        $this->line('provider_error_code='.$result['provider_error_code']);
        $this->line('provider_error_message='.$result['provider_error_message']);
        $this->line('correlation_id='.$correlationId);
        $this->line('success='.($result['success'] ? 'true' : 'false'));
        $this->newLine();
    }

    /**
     * @param  array<string, string>  $headers
     * @return array{http_status: int|string, body: string}
     */
    private function postSoapRequest(
        string $endpoint,
        string $requestXml,
        string $correlationId,
        array $headers,
    ): array {
        $failure = [
            'http_status' => 'n/a',
            'body' => '',
        ];

        try {
            $response = Http::timeout((int) config('suppliers.pia_ndc.timeout_seconds', 60))
                ->connectTimeout((int) config('suppliers.pia_ndc.connect_timeout_seconds', 10))
                ->withHeaders(array_merge($headers, [
                    'X-Correlation-ID' => $correlationId,
                ]))
                ->withBody($requestXml, 'text/xml; charset=utf-8')
                ->post($endpoint);
        } catch (ConnectionException) {
            return $failure;
        } catch (Throwable) {
            return $failure;
        }

        return [
            'http_status' => $response->status(),
            'body' => (string) $response->body(),
        ];
    }

    /**
     * @param  array{http_status: int|string, body: string}  $response
     * @return array{
     *     http_status: int|string,
     *     provider_error_code: string,
     *     provider_error_message: string,
     *     success: bool
     * }
     */
    private function evaluateResponse(
        array $response,
        string $correlationId,
        PiaNdcXmlParser $xmlParser,
        PiaNdcResponseNormalizer $responseNormalizer,
        SupplierConnection $connection,
    ): array {
        $httpStatus = $response['http_status'];
        $body = $response['body'];

        if ($httpStatus === 'n/a') {
            return [
                'http_status' => $httpStatus,
                'provider_error_code' => 'transport_error',
                'provider_error_message' => 'Request failed before a response was received.',
                'success' => false,
            ];
        }

        if (in_array($httpStatus, [401, 403], true)) {
            return [
                'http_status' => $httpStatus,
                'provider_error_code' => 'supplier_auth_failed',
                'provider_error_message' => 'HTTP authentication rejected.',
                'success' => false,
            ];
        }

        if (! is_int($httpStatus) || $httpStatus < 200 || $httpStatus >= 300) {
            return [
                'http_status' => $httpStatus,
                'provider_error_code' => 'supplier_http_error',
                'provider_error_message' => 'Non-success HTTP status.',
                'success' => false,
            ];
        }

        try {
            $parsed = $xmlParser->parse($body);
        } catch (Throwable) {
            return [
                'http_status' => $httpStatus,
                'provider_error_code' => 'malformed_xml',
                'provider_error_message' => 'Response could not be parsed as XML.',
                'success' => false,
            ];
        }

        if ($parsed['soap_fault'] !== null) {
            return [
                'http_status' => $httpStatus,
                'provider_error_code' => (string) ($parsed['soap_fault']['code'] ?? 'soap_fault'),
                'provider_error_message' => (string) ($parsed['soap_fault']['message'] ?? 'SOAP fault.'),
                'success' => false,
            ];
        }

        $errors = is_array($parsed['errors'] ?? null) ? $parsed['errors'] : [];
        if ($errors !== []) {
            $first = $errors[0];

            return [
                'http_status' => $httpStatus,
                'provider_error_code' => (string) ($first['code'] ?? 'provider_error'),
                'provider_error_message' => (string) ($first['message'] ?? 'Provider returned an error.'),
                'success' => false,
            ];
        }

        $responseNormalizer->normalizeSearchResponse($parsed, $connection, $correlationId);

        return [
            'http_status' => $httpStatus,
            'provider_error_code' => '',
            'provider_error_message' => '',
            'success' => true,
        ];
    }

    private function injectLegacyClientInformation(
        string $requestXml,
        string $agencyId,
        string $username,
        string $password,
    ): string {
        $dom = new DOMDocument;
        if (@$dom->loadXML($requestXml) !== true) {
            return $requestXml;
        }

        $xpath = new DOMXPath($dom);
        $nodes = $xpath->query('//*[local-name()="IATA_AirShoppingRQ"]');
        if ($nodes === false || $nodes->length === 0) {
            return $requestXml;
        }

        $root = $nodes->item(0);
        if (! $root instanceof DOMElement) {
            return $requestXml;
        }

        $clientInfo = $dom->createElement('clientInformation');
        $clientInfo->appendChild($dom->createElement('userName', $username));
        $clientInfo->appendChild($dom->createElement('password', $password));
        $clientInfo->appendChild($dom->createElement('clientIP', '127.0.0.1'));
        $clientInfo->appendChild($dom->createElement('member', $agencyId));

        if ($root->firstChild !== null) {
            $root->insertBefore($clientInfo, $root->firstChild);
        } else {
            $root->appendChild($clientInfo);
        }

        return $dom->saveXML() ?: $requestXml;
    }

    protected function resolveConnection(): ?SupplierConnection
    {
        $id = $this->option('connection');
        if ($id) {
            return SupplierConnection::query()
                ->where('id', (int) $id)
                ->where('provider', SupplierProvider::PiaNdc)
                ->first();
        }

        return SupplierConnection::query()
            ->where('provider', SupplierProvider::PiaNdc)
            ->orderByDesc('is_active')
            ->orderBy('id')
            ->first();
    }
}
