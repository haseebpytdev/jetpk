<?php

namespace App\Console\Commands;

use App\Data\FlightSearchRequestData;
use App\Enums\SupplierProvider;
use App\Models\SupplierConnection;
use App\Services\Suppliers\Iati\IatiAuthService;
use App\Services\Suppliers\Iati\IatiConfigResolver;
use App\Services\Suppliers\Iati\IatiPayloadBuilder;
use App\Support\Security\SensitiveDataRedactor;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

/**
 * Non-mutating IATI search payload variant probe — POST /search only via JWT exchange.
 * Does not log credentials or tokens.
 */
class IatiSearchPayloadProbeCommand extends Command
{
    protected $signature = 'iati:search-payload-probe
        {--connection= : Supplier connection ID}
        {--from=LHE : Origin airport code}
        {--to=DXB : Destination airport code}
        {--date= : Departure YYYY-MM-DD}
        {--adults=1 : Adult passenger count}
        {--return= : Return YYYY-MM-DD (enables return_trip variant)}
        {--limit-variants= : Max variants to run; when set, disables early stop on success}';

    protected $description = 'Probe IATI /search payload variants to diagnose FE001 vs inventory issues';

    public function handle(
        IatiConfigResolver $configResolver,
        IatiAuthService $authService,
        IatiPayloadBuilder $payloadBuilder,
    ): int {
        $connection = $this->resolveConnection();
        if ($connection === null) {
            $this->error('No IATI SupplierConnection found.');

            return self::FAILURE;
        }

        $config = $configResolver->resolve($connection);
        $from = strtoupper((string) $this->option('from'));
        $to = strtoupper((string) $this->option('to'));
        $date = (string) ($this->option('date') ?: now()->addMonth()->format('Y-m-d'));
        $adults = max(1, (int) $this->option('adults'));
        $returnDate = $this->option('return');
        $returnDate = is_string($returnDate) && trim($returnDate) !== '' ? trim($returnDate) : null;
        $routeSlug = strtolower($from.'-'.$to);
        $limitVariants = $this->option('limit-variants');
        $stopEarly = $limitVariants === null;
        $maxVariants = is_numeric($limitVariants) ? max(1, (int) $limitVariants) : null;

        $this->line('connection_id='.$connection->id);
        $this->line('environment='.$config['environment']);
        $this->line('flight_base='.$config['flight_base']);
        $this->line('route='.$from.'→'.$to);
        $this->line('departure_date='.$date);
        if ($returnDate !== null) {
            $this->line('return_date='.$returnDate);
        }
        $this->line('auth_mode='.($authService->usesJwtExchange($connection) ? 'jwt_exchange' : 'auth_code'));
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

        $variants = $this->buildVariants($from, $to, $date, $adults, $returnDate, $payloadBuilder);
        if ($maxVariants !== null) {
            $variants = array_slice($variants, 0, $maxVariants, true);
        }

        $timeout = (int) config('suppliers.iati.timeout_seconds', 60);
        $connectTimeout = (int) config('suppliers.iati.connect_timeout_seconds', 10);
        $searchUrl = rtrim($config['flight_base'], '/').'/search';
        $organizationId = trim($config['organization_id']);
        $foundFlights = false;
        $testedCount = 0;

        foreach ($variants as $variantName => $payload) {
            $testedCount++;
            $result = $this->probeSearch(
                $searchUrl,
                $token,
                $organizationId,
                $payload,
                $timeout,
                $connectTimeout,
            );

            $rawPath = $this->saveProbeResponse(
                $variantName,
                $routeSlug,
                $date,
                $result['body'],
                $result['raw_body'],
            );

            $this->printVariantResult($variantName, $result, $rawPath);

            if ($result['departure_flights_count'] > 0) {
                $foundFlights = true;
                if ($stopEarly) {
                    $this->newLine();
                    $this->info('Early stop: variant "'.$variantName.'" returned departure_flights.');

                    break;
                }
            }
        }

        $this->newLine();
        $this->line('variants_tested='.$testedCount);
        $this->line('found_departure_flights='.($foundFlights ? 'yes' : 'no'));

        return self::SUCCESS;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    protected function buildVariants(
        string $from,
        string $to,
        string $date,
        int $adults,
        ?string $returnDate,
        IatiPayloadBuilder $payloadBuilder,
    ): array {
        $paxList = $payloadBuilder->paxList($adults, 0, 0);

        $oneWayCurrent = $payloadBuilder->buildSearchPayload(FlightSearchRequestData::fromArray([
            'origin' => $from,
            'destination' => $to,
            'depart_date' => $date,
            'adults' => $adults,
            'children' => 0,
            'infants' => 0,
            'trip_type' => 'one_way',
        ]));

        $formattedReturn = null;
        if ($returnDate !== null) {
            $formattedReturn = (string) $payloadBuilder->buildSearchPayload(FlightSearchRequestData::fromArray([
                'origin' => $from,
                'destination' => $to,
                'depart_date' => $date,
                'adults' => $adults,
                'children' => 0,
                'infants' => 0,
                'trip_type' => 'return',
                'return_date' => $returnDate,
            ]))['return_date'];
        }

        $variants = [
            'current_airport_false' => $oneWayCurrent,
            'city_true' => $this->withDestinationCities($oneWayCurrent, true),
            'omit_accept_pending' => $this->withoutKey($oneWayCurrent, 'accept_pending'),
            'accept_pending_false' => array_merge($oneWayCurrent, ['accept_pending' => false]),
            'omit_cabin_type' => $this->withoutKey($oneWayCurrent, 'cabin_type'),
            'cabin_lowercase' => array_merge($oneWayCurrent, ['cabin_type' => 'economy']),
            'no_city_keys' => [
                'from_destination' => ['code' => $from],
                'to_destination' => ['code' => $to],
                'departure_date' => $oneWayCurrent['departure_date'],
                'pax_list' => $paxList,
                'accept_pending' => true,
                'cabin_type' => 'ECONOMY',
            ],
        ];

        if ($returnDate !== null) {
            $variants['return_trip'] = $payloadBuilder->buildSearchPayload(FlightSearchRequestData::fromArray([
                'origin' => $from,
                'destination' => $to,
                'depart_date' => $date,
                'adults' => $adults,
                'children' => 0,
                'infants' => 0,
                'trip_type' => 'return',
                'return_date' => $returnDate,
            ]));
        }

        $variants['binham_exact'] = $this->binhamExactPayload(
            $from,
            $to,
            (string) $oneWayCurrent['departure_date'],
            $paxList,
            $formattedReturn,
        );

        return $variants;
    }

    /**
     * Match Binham IATI_SEARCH_PAYLOAD_FROM_POST field order and values.
     *
     * @param  list<array{type: string, count: int}>  $paxList
     * @return array<string, mixed>
     */
    protected function binhamExactPayload(
        string $from,
        string $to,
        string $date,
        array $paxList,
        ?string $returnDate,
    ): array {
        $payload = [
            'from_destination' => ['code' => $from, 'city' => false],
            'to_destination' => ['code' => $to, 'city' => false],
            'departure_date' => $date,
            'pax_list' => $paxList,
            'accept_pending' => true,
            'cabin_type' => 'ECONOMY',
        ];

        if ($returnDate !== null) {
            $payload['return_date'] = $returnDate;
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function withDestinationCities(array $payload, bool $city): array
    {
        $payload['from_destination'] = array_merge(
            is_array($payload['from_destination'] ?? null) ? $payload['from_destination'] : [],
            ['city' => $city],
        );
        $payload['to_destination'] = array_merge(
            is_array($payload['to_destination'] ?? null) ? $payload['to_destination'] : [],
            ['city' => $city],
        );

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function withoutKey(array $payload, string $key): array
    {
        unset($payload[$key]);

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{
     *     http_status: int|null,
     *     body: array<string, mixed>,
     *     raw_body: string,
     *     provider_error_code: ?string,
     *     provider_error_description: ?string,
     *     result_keys: list<string|int>,
     *     departure_flights_count: int,
     *     has_departure_flights: bool
     * }
     */
    protected function probeSearch(
        string $url,
        string $bearerToken,
        string $organizationId,
        array $payload,
        int $timeout,
        int $connectTimeout,
    ): array {
        try {
            $response = Http::timeout($timeout)
                ->connectTimeout($connectTimeout)
                ->withHeaders($this->flightHeaders($bearerToken, $organizationId))
                ->post($url, $payload);
        } catch (ConnectionException) {
            return [
                'http_status' => null,
                'body' => [],
                'raw_body' => '',
                'provider_error_code' => null,
                'provider_error_description' => 'transport_failed',
                'result_keys' => [],
                'departure_flights_count' => 0,
                'has_departure_flights' => false,
            ];
        }

        $rawBody = (string) $response->body();
        $json = $response->json();
        $body = is_array($json) ? $json : [];
        $unwrapped = $this->unwrapResult($body);
        $resultKeys = is_array($unwrapped) ? array_keys($unwrapped) : [];
        $departureFlights = is_array($unwrapped['departure_flights'] ?? null)
            ? $unwrapped['departure_flights']
            : [];
        $departureCount = count($departureFlights);
        $providerError = $this->extractProviderError($body);

        return [
            'http_status' => $response->status(),
            'body' => $body,
            'raw_body' => $rawBody,
            'provider_error_code' => $providerError['code'],
            'provider_error_description' => $providerError['description'],
            'result_keys' => $resultKeys,
            'departure_flights_count' => $departureCount,
            'has_departure_flights' => $departureCount > 0,
        ];
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array{code: ?string, description: ?string}
     */
    protected function extractProviderError(array $body): array
    {
        $code = null;
        $description = null;

        foreach (['code', 'error_code', 'status_code'] as $key) {
            $value = trim((string) ($body[$key] ?? ''));
            if ($value !== '' && ! is_array($body[$key] ?? null)) {
                $code = $value;
                break;
            }
        }

        $error = $body['error'] ?? null;
        if (is_array($error)) {
            if ($code === null) {
                $code = trim((string) ($error['error_code'] ?? $error['code'] ?? '')) ?: null;
            }
            $description = trim((string) ($error['description'] ?? $error['message'] ?? '')) ?: null;
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
     * @return array<string, mixed>
     */
    protected function unwrapResult(array $body): array
    {
        if (isset($body['result']) && is_array($body['result'])) {
            return $body['result'];
        }

        return $body;
    }

    /**
     * @return array<string, string>
     */
    protected function flightHeaders(string $bearerToken, string $organizationId): array
    {
        $headers = [
            'Authorization' => 'Bearer '.$bearerToken,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];

        if ($organizationId !== '') {
            $headers['Organization-Id'] = $organizationId;
        }

        return $headers;
    }

    /**
     * @param  array<string, mixed>  $body
     */
    protected function saveProbeResponse(
        string $variant,
        string $routeSlug,
        string $date,
        array $body,
        string $rawBody,
    ): string {
        $dir = storage_path('app/iati');
        File::ensureDirectoryExists($dir);

        $timestamp = now()->format('Ymd-His');
        $filename = sprintf('probe-%s-%s-%s-%s.json', $variant, $routeSlug, $date, $timestamp);
        $path = $dir.DIRECTORY_SEPARATOR.$filename;

        $record = [
            'variant' => $variant,
            'route' => $routeSlug,
            'date' => $date,
            'body_size' => strlen($rawBody),
            'response' => SensitiveDataRedactor::redact($body),
        ];

        file_put_contents($path, json_encode($record, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $path;
    }

    /**
     * @param  array{
     *     http_status: int|null,
     *     provider_error_code: ?string,
     *     provider_error_description: ?string,
     *     result_keys: list<string|int>,
     *     raw_body: string,
     *     departure_flights_count: int,
     *     has_departure_flights: bool
     * }  $result
     */
    protected function printVariantResult(string $variant, array $result, string $rawPath): void
    {
        $this->line('---');
        $this->line('variant='.$variant);
        $this->line('http_status='.($result['http_status'] ?? 'n/a'));
        $this->line('provider_error_code='.($result['provider_error_code'] ?? 'null'));
        $this->line('provider_error_description='.($result['provider_error_description'] ?? 'null'));
        $this->line('result_keys='.json_encode($result['result_keys'], JSON_UNESCAPED_SLASHES));
        $this->line('body_size='.strlen($result['raw_body']));
        $this->line('has_departure_flights='.($result['has_departure_flights'] ? 'true' : 'false'));
        $this->line('departure_flights_count='.$result['departure_flights_count']);
        $this->line('raw_path='.$rawPath);
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
