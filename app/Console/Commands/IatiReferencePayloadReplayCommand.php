<?php

namespace App\Console\Commands;

use App\Data\FlightSearchRequestData;
use App\Enums\SupplierProvider;
use App\Models\SupplierConnection;
use App\Services\Suppliers\Iati\Exceptions\IatiException;
use App\Services\Suppliers\Iati\IatiAuthService;
use App\Services\Suppliers\Iati\IatiClient;
use App\Services\Suppliers\Iati\IatiConfigResolver;
use App\Services\Suppliers\Iati\IatiPayloadBuilder;
use App\Support\Security\SensitiveDataRedactor;
use App\Support\Suppliers\Iati\IatiReferencePayloadCatalog;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Replay Binham/iati.pk successful /search payloads against OTA JWT auth.
 * Does not log credentials or tokens.
 */
class IatiReferencePayloadReplayCommand extends Command
{
    protected $signature = 'iati:reference-payload-replay
        {--connection= : Supplier connection ID}
        {--reference=latest : Fixture id or "latest" from debug parse}
        {--route= : Filter route ORIGIN-DEST (e.g. LHE-DXB)}
        {--date= : Override departure_date YYYY-MM-DD}
        {--variant= : Run single header variant; default runs all reference-derived variants}';

    protected $description = 'Replay reference iati.pk search payloads via OTA JWT to diagnose FE001 parity';

    public function handle(
        IatiConfigResolver $configResolver,
        IatiAuthService $authService,
        IatiPayloadBuilder $payloadBuilder,
        IatiClient $iatiClient,
    ): int {
        $connection = $this->resolveConnection();
        if ($connection === null) {
            $this->error('No IATI SupplierConnection found.');

            return self::FAILURE;
        }

        $config = $configResolver->resolve($connection);
        $sample = IatiReferencePayloadCatalog::resolveSample(
            (string) $this->option('reference'),
            $this->option('route') ? (string) $this->option('route') : null,
        );

        if ($sample === null) {
            $this->error('No reference sample matched reference/route filters.');

            return self::FAILURE;
        }

        $sampleDepartureDate = (string) ($sample['payload']['departure_date'] ?? '');
        $dateOverride = trim((string) ($this->option('date') ?? ''));
        if ($dateOverride !== '') {
            $sample = IatiReferencePayloadCatalog::applyDepartureDateOverride($sample, $dateOverride);
        }

        $payload = $sample['payload'];

        $otaPayload = $payloadBuilder->buildSearchPayload(FlightSearchRequestData::fromArray([
            'origin' => $sample['origin'],
            'destination' => $sample['destination'],
            'depart_date' => $payload['departure_date'],
            'adults' => 1,
            'children' => 0,
            'infants' => 0,
            'trip_type' => 'one_way',
        ]));

        $this->line('connection_id='.$connection->id);
        $this->line('environment='.$config['environment']);
        $this->line('flight_base='.$config['flight_base']);
        $this->line('reference_sample_id='.($sample['id'] ?? 'unknown'));
        $this->line('reference_route='.($sample['route'] ?? ''));
        $this->line('reference_sample_departure_date='.$sampleDepartureDate);
        $this->line('replay_departure_date='.($payload['departure_date'] ?? ''));
        $this->line('departure_date_overridden='.($dateOverride !== '' && $dateOverride !== $sampleDepartureDate ? 'yes' : 'no'));
        $this->line('reference_historical_flights='.($sample['reference_departure_flights'] ?? 0));
        $this->line('reference_historical_http='.($sample['reference_http_code'] ?? 0));
        $this->line('reference_request_ip='.($sample['reference_request_ip'] ?? 'unknown'));
        $this->line('reference_source='.($sample['source_file'] ?? 'embedded_fixture'));
        $this->line('auth_mode='.($authService->usesJwtExchange($connection) ? 'jwt_exchange' : 'auth_code_only'));
        $this->line('organization_id_configured='.(trim($config['organization_id']) !== '' ? 'yes' : 'no'));
        $this->newLine();

        $referenceHash = IatiReferencePayloadCatalog::payloadHash($payload);
        $otaHash = IatiReferencePayloadCatalog::payloadHash($otaPayload);
        $this->line('reference_payload_hash='.$referenceHash);
        $this->line('ota_builder_payload_hash='.$otaHash);
        $this->line('payload_parity='.($referenceHash === $otaHash ? 'identical' : 'DIFFERS'));
        if ($referenceHash !== $otaHash) {
            $this->line('reference_payload_json='.json_encode($payload, JSON_UNESCAPED_SLASHES));
            $this->line('ota_builder_payload_json='.json_encode($otaPayload, JSON_UNESCAPED_SLASHES));
        }
        $this->newLine();

        try {
            $token = $authService->getBearerToken($connection);
        } catch (\Throwable $exception) {
            $this->error('token_exchange_failed='.$exception->getMessage());

            return self::FAILURE;
        }

        if ($token === '') {
            $this->error('token_exchange_failed=empty_token');

            return self::FAILURE;
        }

        $this->line('token_exchange_status=ok');
        $this->newLine();

        $searchUrl = rtrim($config['flight_base'], '/').'/search';
        $timeout = (int) config('suppliers.iati.timeout_seconds', 60);
        $connectTimeout = (int) config('suppliers.iati.connect_timeout_seconds', 10);
        $organizationId = trim($config['organization_id']);

        $variants = IatiReferencePayloadCatalog::headerVariants();
        $singleVariant = $this->option('variant');
        if (is_string($singleVariant) && trim($singleVariant) !== '') {
            $key = trim($singleVariant);
            if (! isset($variants[$key])) {
                $this->error('Unknown variant "'.$key.'". Options: '.implode(', ', array_keys($variants)));

                return self::FAILURE;
            }
            $variants = [$key => $variants[$key]];
        }

        $anyFlights = false;
        $variantResults = [];

        foreach ($variants as $variantName => $variant) {
            $transport = (string) ($variant['transport'] ?? 'raw');

            $result = $transport === 'iati_client'
                ? $this->replayViaIatiClient(
                    $iatiClient,
                    $connection,
                    $payload,
                    $variantName,
                    (string) $sample['route'],
                    (string) $payload['departure_date'],
                )
                : $this->replayViaRawHttp(
                    $searchUrl,
                    $token,
                    $organizationId,
                    $payload,
                    $variantName,
                    (string) $sample['route'],
                    (string) $payload['departure_date'],
                    (bool) ($variant['organization_id'] ?? false),
                    (bool) ($variant['correlation_id'] ?? false),
                    $timeout,
                    $connectTimeout,
                );

            $variantResults[$variantName] = $result;
            $this->printReplayResult($variantName, $variant['note'], $payload, $result);

            if ($result['departure_flights_count'] > 0) {
                $anyFlights = true;
            }
        }

        $this->newLine();
        $this->line('replay_found_departure_flights='.($anyFlights ? 'yes' : 'no'));
        $this->printVariantComparisonDiagnosis($variantResults);

        if (! $this->hasExplicitDiagnosis($variantResults)) {
            $this->line('diagnosis='.($anyFlights
                ? 'reference_payload_succeeds_with_listed_variant'
                : 'reference_payload_still_FE001_likely_account_ip_inventory_not_payload'));
        }

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function replayViaRawHttp(
        string $url,
        string $bearerToken,
        string $organizationId,
        array $payload,
        string $variantName,
        string $routeSlug,
        string $date,
        bool $withOrganizationId,
        bool $withCorrelationId,
        int $timeout,
        int $connectTimeout,
    ): array {
        $headers = [
            'Authorization' => 'Bearer '.$bearerToken,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];

        if ($withOrganizationId && $organizationId !== '') {
            $headers['Organization-Id'] = $organizationId;
        }

        if ($withCorrelationId) {
            $headers['X-Correlation-ID'] = 'iati-replay-'.Str::uuid()->toString();
        }

        try {
            $response = Http::timeout($timeout)
                ->connectTimeout($connectTimeout)
                ->withHeaders($headers)
                ->post($url, $payload);
        } catch (ConnectionException) {
            return $this->buildReplayResult(
                variantName: $variantName,
                routeSlug: $routeSlug,
                date: $date,
                httpStatus: null,
                body: [],
                rawBody: '',
                responseHeaders: [],
                providerErrorCode: null,
                providerErrorDescription: 'transport_failed',
            );
        }

        return $this->buildReplayResultFromHttpResponse(
            $response,
            $variantName,
            $routeSlug,
            $date,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function replayViaIatiClient(
        IatiClient $client,
        SupplierConnection $connection,
        array $payload,
        string $variantName,
        string $routeSlug,
        string $date,
    ): array {
        try {
            $body = $client->post($connection, '/search', $payload, [
                'request_context' => 'iati:reference-payload-replay',
            ]);
        } catch (IatiException $exception) {
            $contextBody = is_array($exception->context['response'] ?? null)
                ? $exception->context['response']
                : [];
            $rawBody = $contextBody !== [] ? json_encode($contextBody, JSON_UNESCAPED_SLASHES) : '';

            return $this->buildReplayResult(
                variantName: $variantName,
                routeSlug: $routeSlug,
                date: $date,
                httpStatus: $exception->httpStatus,
                body: $contextBody,
                rawBody: $rawBody,
                responseHeaders: [],
                providerErrorCode: $this->extractProviderError($contextBody)['code'] ?? $exception->normalizedCode,
                providerErrorDescription: $this->extractProviderError($contextBody)['description'] ?? $exception->safeMessage,
            );
        } catch (\Throwable $exception) {
            return $this->buildReplayResult(
                variantName: $variantName,
                routeSlug: $routeSlug,
                date: $date,
                httpStatus: null,
                body: [],
                rawBody: '',
                responseHeaders: [],
                providerErrorCode: null,
                providerErrorDescription: 'client_exception: '.$exception->getMessage(),
            );
        }

        $diagnostic = is_array($body['_ota_diagnostic'] ?? null) ? $body['_ota_diagnostic'] : [];
        $httpStatus = isset($diagnostic['http_status']) ? (int) $diagnostic['http_status'] : null;
        $rawBodySize = (int) ($diagnostic['raw_body_size'] ?? 0);
        $providerError = $this->extractProviderError($body);
        $unwrapped = $this->unwrapResult($body);
        $departureFlights = is_array($unwrapped['departure_flights'] ?? null)
            ? $unwrapped['departure_flights']
            : [];
        $rawBody = json_encode(SensitiveDataRedactor::redact($body), JSON_UNESCAPED_SLASHES);
        $resultKeys = is_array($unwrapped) ? array_keys($unwrapped) : [];
        $rawPath = $this->saveRawResponse(
            $variantName,
            $routeSlug,
            $date,
            $rawBody,
            $body,
            [],
            'application/json',
            $this->shouldIncludeRawBodyPreview($body, $resultKeys, $rawBody) ? $this->sanitizeRawBodyPreview($rawBody) : null,
        );

        return [
            'response_status' => $httpStatus,
            'http_status' => $httpStatus,
            'body' => $body,
            'raw_body' => $rawBody,
            'raw_body_size' => $rawBodySize > 0 ? $rawBodySize : strlen($rawBody),
            'content_type' => 'application/json',
            'response_headers' => [],
            'raw_body_preview' => $this->shouldIncludeRawBodyPreview($body, $resultKeys, $rawBody)
                ? $this->sanitizeRawBodyPreview($rawBody)
                : null,
            'provider_error_code' => $providerError['code'],
            'provider_error_description' => $providerError['description'],
            'result_keys' => $resultKeys,
            'departure_flights_count' => count($departureFlights),
            'raw_path' => $rawPath,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildReplayResultFromHttpResponse(
        Response $response,
        string $variantName,
        string $routeSlug,
        string $date,
    ): array {
        $rawBody = (string) $response->body();
        $json = $response->json();
        $body = is_array($json) ? $json : [];
        $providerError = $this->extractProviderError($body);

        return $this->buildReplayResult(
            variantName: $variantName,
            routeSlug: $routeSlug,
            date: $date,
            httpStatus: $response->status(),
            body: $body,
            rawBody: $rawBody,
            responseHeaders: $this->sanitizeResponseHeaders($response->headers()),
            providerErrorCode: $providerError['code'],
            providerErrorDescription: $providerError['description'],
        );
    }

    /**
     * @param  array<string, mixed>  $body
     * @param  array<string, string>  $responseHeaders
     * @return array<string, mixed>
     */
    protected function buildReplayResult(
        string $variantName,
        string $routeSlug,
        string $date,
        ?int $httpStatus,
        array $body,
        string $rawBody,
        array $responseHeaders,
        ?string $providerErrorCode,
        ?string $providerErrorDescription,
    ): array {
        $unwrapped = $this->unwrapResult($body);
        $resultKeys = is_array($unwrapped) ? array_keys($unwrapped) : [];
        $departureFlights = is_array($unwrapped['departure_flights'] ?? null)
            ? $unwrapped['departure_flights']
            : [];
        $contentType = $this->extractContentType($responseHeaders);
        $preview = $this->shouldIncludeRawBodyPreview($body, $resultKeys, $rawBody)
            ? $this->sanitizeRawBodyPreview($rawBody)
            : null;
        $rawPath = $this->saveRawResponse(
            $variantName,
            $routeSlug,
            $date,
            $rawBody,
            $body,
            $responseHeaders,
            $contentType,
            $preview,
        );

        return [
            'response_status' => $httpStatus,
            'http_status' => $httpStatus,
            'body' => $body,
            'raw_body' => $rawBody,
            'raw_body_size' => strlen($rawBody),
            'content_type' => $contentType,
            'response_headers' => $responseHeaders,
            'raw_body_preview' => $preview,
            'provider_error_code' => $providerErrorCode,
            'provider_error_description' => $providerErrorDescription,
            'result_keys' => $resultKeys,
            'departure_flights_count' => count($departureFlights),
            'raw_path' => $rawPath,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $result
     */
    protected function printReplayResult(string $variant, string $note, array $payload, array $result): void
    {
        $this->line('---');
        $this->line('variant='.$variant);
        $this->line('variant_note='.$note);
        $this->line('payload_hash='.IatiReferencePayloadCatalog::payloadHash($payload));
        $this->line('payload_json='.json_encode($payload, JSON_UNESCAPED_SLASHES));
        $this->line('response_status='.($result['response_status'] ?? 'n/a'));
        $this->line('http_status='.($result['http_status'] ?? 'n/a'));
        $this->line('content_type='.($result['content_type'] ?? ''));
        $this->line('raw_body_size='.($result['raw_body_size'] ?? 0));
        $this->line('response_headers='.json_encode($result['response_headers'] ?? [], JSON_UNESCAPED_SLASHES));
        $this->line('provider_error_code='.($result['provider_error_code'] ?? 'null'));
        $this->line('provider_error_description='.($result['provider_error_description'] ?? 'null'));
        $this->line('departure_flights_count='.$result['departure_flights_count']);
        $this->line('response_keys='.json_encode($result['result_keys'], JSON_UNESCAPED_SLASHES));

        if (! empty($result['raw_body_preview'])) {
            $this->line('raw_body_preview='.$result['raw_body_preview']);
        }

        $this->line('raw_path='.$result['raw_path']);
    }

    /**
     * @param  array<string, array<string, mixed>>  $variantResults
     */
    protected function printVariantComparisonDiagnosis(array $variantResults): void
    {
        $referenceExact = $variantResults['reference_exact'] ?? null;
        $iatiClientDirect = $variantResults['iati_client_direct'] ?? null;

        if ($referenceExact === null || $iatiClientDirect === null) {
            return;
        }

        $referenceStatus = (int) ($referenceExact['response_status'] ?? 0);
        $clientStatus = (int) ($iatiClientDirect['response_status'] ?? 0);
        $clientProviderCode = strtoupper((string) ($iatiClientDirect['provider_error_code'] ?? ''));

        if ($referenceStatus === 403 && $clientStatus === 202 && $clientProviderCode === 'FE001') {
            $this->line('diagnosis=raw_replay_header_or_http_client_difference');
        }
    }

    /**
     * @param  array<string, array<string, mixed>>  $variantResults
     */
    protected function hasExplicitDiagnosis(array $variantResults): bool
    {
        $referenceExact = $variantResults['reference_exact'] ?? null;
        $iatiClientDirect = $variantResults['iati_client_direct'] ?? null;

        if ($referenceExact === null || $iatiClientDirect === null) {
            return false;
        }

        $referenceStatus = (int) ($referenceExact['response_status'] ?? 0);
        $clientStatus = (int) ($iatiClientDirect['response_status'] ?? 0);
        $clientProviderCode = strtoupper((string) ($iatiClientDirect['provider_error_code'] ?? ''));

        return $referenceStatus === 403 && $clientStatus === 202 && $clientProviderCode === 'FE001';
    }

    /**
     * @param  array<string, mixed>  $body
     * @param  list<string|int>  $resultKeys
     */
    protected function shouldIncludeRawBodyPreview(array $body, array $resultKeys, string $rawBody = ''): bool
    {
        if ($resultKeys === []) {
            return true;
        }

        if ($body === [] && trim($rawBody) !== '') {
            return true;
        }

        $trimmed = ltrim($rawBody);

        return $trimmed !== '' && $trimmed[0] !== '{' && $trimmed[0] !== '[';
    }

    /**
     * @param  array<string, array<int, string>|string>  $headers
     * @return array<string, string>
     */
    protected function sanitizeResponseHeaders(array $headers): array
    {
        $normalized = [];

        foreach ($headers as $key => $values) {
            $headerName = (string) $key;
            $headerValue = is_array($values) ? implode(', ', $values) : (string) $values;

            if (preg_match('/^(authorization|set-cookie|cookie)$/i', $headerName)) {
                $normalized[$headerName] = '[redacted]';

                continue;
            }

            $normalized[$headerName] = preg_replace('/Bearer\s+\S+/i', 'Bearer [redacted]', $headerValue) ?? $headerValue;
        }

        return $normalized;
    }

    /**
     * @param  array<string, string>  $headers
     */
    protected function extractContentType(array $headers): string
    {
        foreach ($headers as $name => $value) {
            if (strcasecmp((string) $name, 'Content-Type') === 0) {
                return trim(explode(';', $value)[0]);
            }
        }

        return '';
    }

    protected function sanitizeRawBodyPreview(string $rawBody, int $limit = 500): string
    {
        if ($rawBody === '') {
            return '';
        }

        $preview = substr($rawBody, 0, $limit);
        $preview = preg_replace('/Bearer\s+\S+/i', 'Bearer [redacted]', $preview) ?? $preview;
        $preview = preg_replace(
            '/("(?:token|access_token|authorization|secret)")\s*:\s*"[^"]*"/i',
            '$1:"[redacted]"',
            $preview,
        ) ?? $preview;

        return $preview;
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array{code: ?string, description: ?string}
     */
    protected function extractProviderError(array $body): array
    {
        $code = null;
        $description = null;

        $error = $body['error'] ?? null;
        if (is_array($error)) {
            $code = trim((string) ($error['error_code'] ?? $error['code'] ?? '')) ?: null;
            $description = trim((string) ($error['description'] ?? $error['message'] ?? '')) ?: null;
        }

        foreach (['code', 'error_code'] as $key) {
            if ($code === null) {
                $value = trim((string) ($body[$key] ?? ''));
                $code = $value !== '' ? $value : null;
            }
        }

        $diagnostic = is_array($body['_ota_diagnostic'] ?? null) ? $body['_ota_diagnostic'] : [];
        if ($code === null) {
            $value = trim((string) ($diagnostic['provider_code'] ?? ''));
            $code = $value !== '' ? $value : null;
        }

        if ($description === null) {
            $description = trim((string) ($body['message'] ?? $body['description'] ?? '')) ?: null;
        }

        if ($description !== null && preg_match('/token|password|secret|authorization/i', $description)) {
            $description = null;
        }

        return ['code' => $code, 'description' => $description];
    }

    /**
     * @param  array<string, mixed>  $body
     * @param  array<string, string>  $responseHeaders
     */
    protected function saveRawResponse(
        string $variant,
        string $routeSlug,
        string $date,
        string $rawBody,
        array $body,
        array $responseHeaders = [],
        string $contentType = '',
        ?string $rawBodyPreview = null,
    ): string {
        $dir = storage_path('app/iati/reference-replay');
        File::ensureDirectoryExists($dir);

        $timestamp = now()->format('Ymd-His');
        $filename = sprintf('replay-%s-%s-%s-%s.json', $variant, strtolower($routeSlug), $date, $timestamp);
        $path = $dir.DIRECTORY_SEPARATOR.$filename;

        $record = [
            'variant' => $variant,
            'route' => $routeSlug,
            'date' => $date,
            'content_type' => $contentType,
            'raw_body_size' => strlen($rawBody),
            'response_headers' => $responseHeaders,
            'response' => SensitiveDataRedactor::redact($body),
            'raw_body_preview' => $rawBodyPreview,
            'raw_body' => $rawBody,
        ];

        file_put_contents($path, json_encode($record, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $path;
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    protected function unwrapResult(array $body): array
    {
        if (isset($body['result']) && is_array($body['result'])) {
            return $body['result'];
        }

        return $body;
    }

    protected function resolveConnection(): ?SupplierConnection
    {
        $id = $this->option('connection');
        if ($id) {
            return SupplierConnection::query()
                ->where('id', (int) $id)
                ->where('provider', SupplierProvider::Iati)
                ->first();
        }

        return SupplierConnection::query()
            ->where('provider', SupplierProvider::Iati)
            ->orderByDesc('is_active')
            ->orderBy('id')
            ->first();
    }
}
