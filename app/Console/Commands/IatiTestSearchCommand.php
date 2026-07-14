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
use App\Services\Suppliers\Iati\IatiResponseNormalizer;
use App\Support\Security\SensitiveDataRedactor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class IatiTestSearchCommand extends Command
{
    protected $signature = 'iati:test-search
        {--connection= : Supplier connection ID}
        {--from=LHE : Origin}
        {--to=DXB : Destination}
        {--date= : Departure YYYY-MM-DD}
        {--return= : Return YYYY-MM-DD}
        {--adults=1}
        {--children=0}
        {--infants=0}';

    protected $description = 'Run IATI test search and save sanitized fixture';

    public function handle(
        IatiClient $client,
        IatiConfigResolver $configResolver,
        IatiPayloadBuilder $payloadBuilder,
        IatiResponseNormalizer $normalizer,
        IatiAuthService $authService,
    ): int {
        $connection = $this->resolveConnection();
        if ($connection === null) {
            $this->error('No IATI SupplierConnection found.');

            return self::FAILURE;
        }

        $usesJwt = $authService->usesJwtExchange($connection);
        $this->line('auth_mode='.($usesJwt ? 'jwt_exchange' : 'auth_code'));

        $tokenExchangeStatus = 'skipped';
        if ($usesJwt) {
            try {
                $authService->getBearerToken($connection);
                $tokenExchangeStatus = 'ok';
            } catch (\Throwable $exception) {
                $tokenExchangeStatus = 'fail';
                $this->line('token_exchange_error='.$exception->getMessage());
            }
        }
        $this->line('token_exchange_status='.$tokenExchangeStatus);

        $date = (string) ($this->option('date') ?: now()->addMonth()->format('Y-m-d'));
        $return = $this->option('return');
        $criteria = [
            'origin' => strtoupper((string) $this->option('from')),
            'destination' => strtoupper((string) $this->option('to')),
            'depart_date' => $date,
            'adults' => (int) $this->option('adults'),
            'children' => (int) $this->option('children'),
            'infants' => (int) $this->option('infants'),
            'trip_type' => $return ? 'return' : 'one_way',
        ];
        if ($return) {
            $criteria['return_date'] = (string) $return;
        }

        $request = FlightSearchRequestData::fromArray($criteria);
        $payload = null;

        try {
            $config = $configResolver->resolve($connection);
            $endpoint = rtrim($config['flight_base'], '/').'/search';
            $payload = $payloadBuilder->buildSearchPayload($request);
            $this->line('audit_endpoint='.$endpoint);
            $this->line('audit_method=POST');
            $this->line('audit_payload='.json_encode($payload, JSON_UNESCAPED_SLASHES));
        } catch (\Throwable $exception) {
            $this->line('audit_precheck_failed='.$exception::class.': '.$exception->getMessage());
        }

        if (! is_array($payload)) {
            try {
                $payload = $payloadBuilder->buildSearchPayload($request);
            } catch (\Throwable $exception) {
                $this->error('payload_build_failed='.$exception->getMessage());

                return self::FAILURE;
            }
        }

        $warnings = [];
        $offers = [];
        $httpStatus = 'n/a';
        $rawBodySize = 0;
        $providerResultKeys = [];
        $correlationId = '';
        $rawFixturePath = '';
        $errorCode = null;
        $response = [];

        try {
            $response = $client->post($connection, '/search', $payload, [
                'request_context' => 'iati:test-search',
            ]);
            $diagnostic = is_array($response['_ota_diagnostic'] ?? null) ? $response['_ota_diagnostic'] : [];
            $correlationId = (string) ($diagnostic['correlation_id'] ?? '');
            $httpStatus = (string) ($diagnostic['http_status'] ?? 'n/a');
            $rawBodySize = (int) ($diagnostic['raw_body_size'] ?? 0);
            $providerResultKeys = is_array($diagnostic['provider_result_keys'] ?? null)
                ? $diagnostic['provider_result_keys']
                : array_keys($client->unwrapResult($response));

            $rawFixturePath = $this->saveSanitizedRawResponse(
                $response,
                strtolower($criteria['origin']),
                strtolower($criteria['destination']),
                $date,
            );

            $offers = $normalizer->normalizeSearchResponse(
                $response,
                $connection,
                $correlationId,
                $request->adults,
                $request->children,
                $request->infants,
            );

            if ($offers === []) {
                $warnings[] = $this->noFaresWarning($response);
            }
        } catch (IatiException $exception) {
            $httpStatus = (string) $exception->httpStatus;
            $errorCode = $exception->normalizedCode;
            $warnings[] = 'Provider search is temporarily unavailable.';
            $this->line('error_code='.$errorCode);
            $this->line('exception_message='.$exception->getMessage());
        } catch (\Throwable $exception) {
            $warnings[] = 'Provider search is temporarily unavailable.';
            $this->line('exception_class='.$exception::class);
            $this->line('exception_message='.$exception->getMessage());
        }

        $this->line('search_http_status='.$httpStatus);
        $this->line('http_status='.$httpStatus);
        $this->line('raw_body_size='.$rawBodySize);
        $this->line('provider_result_keys='.json_encode($providerResultKeys, JSON_UNESCAPED_SLASHES));
        if ($rawFixturePath !== '') {
            $this->line('sanitized_raw_fixture_path='.$rawFixturePath);
            $this->info('raw_fixture_saved='.$rawFixturePath);
        }

        $offersCount = count($offers);
        $this->line('offers_count='.$offersCount);
        $this->line('normalized_offers_count='.$offersCount);

        if (in_array((int) $httpStatus, [200, 201], true)) {
            $this->line('normalizer_input_shape='.$this->describeNormalizerInputShape($response));
        }

        foreach ($warnings as $warning) {
            $this->line('warnings: '.$warning);
        }

        if ($this->output->isVerbose() && $correlationId !== '') {
            $this->line('correlation_id='.$correlationId);
        }

        if ($offers !== []) {
            $first = $offers[0]->toArray();
            $this->line('first_offer_id='.($first['offer_id'] ?? ''));
            $this->line('first_route='.($first['origin'] ?? '').'-'.($first['destination'] ?? ''));
            $this->line('first_total='.($first['fare_breakdown']['supplier_total'] ?? ''));
        }

        $dir = base_path('tests/Fixtures/iati');
        File::ensureDirectoryExists($dir);
        $fixture = [
            'criteria' => $criteria,
            'offers_count' => $offersCount,
            'first_offer' => isset($offers[0]) ? SensitiveDataRedactor::redact($offers[0]->toArray()) : null,
            'warnings' => $warnings,
        ];
        $path = $dir.'/search_'.strtolower($criteria['origin']).'_'.strtolower($criteria['destination']).'.json';
        file_put_contents($path, json_encode($fixture, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $this->info('fixture_saved='.$path);

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $response
     */
    protected function saveSanitizedRawResponse(
        array $response,
        string $origin,
        string $destination,
        string $date,
    ): string {
        $dir = storage_path('app/iati');
        File::ensureDirectoryExists($dir);

        $timestamp = now()->format('Ymd-His');
        $filename = sprintf('search-raw-%s-%s-%s-%s.json', $origin, $destination, $date, $timestamp);
        $path = $dir.DIRECTORY_SEPARATOR.$filename;

        $sanitized = SensitiveDataRedactor::redact($response);
        unset($sanitized['_ota_diagnostic']);

        file_put_contents($path, json_encode($sanitized, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $path;
    }

    /**
     * @param  array<string, mixed>  $response
     */
    protected function describeNormalizerInputShape(array $response): string
    {
        if ($response === []) {
            return 'empty_response';
        }

        $topKeys = array_keys($response);
        $unwrapped = isset($response['result']) && is_array($response['result'])
            ? $response['result']
            : $response;
        $resultKeys = is_array($unwrapped) ? array_keys($unwrapped) : [];
        $departureCount = is_array($unwrapped['departure_flights'] ?? null)
            ? count($unwrapped['departure_flights'])
            : 0;
        $returnCount = is_array($unwrapped['return_flights'] ?? null)
            ? count($unwrapped['return_flights'])
            : 0;

        return sprintf(
            'top=%s;result=%s;departure_flights=%d;return_flights=%d',
            implode(',', $topKeys),
            implode(',', $resultKeys),
            $departureCount,
            $returnCount,
        );
    }

    /**
     * @param  array<string, mixed>  $response
     */
    protected function noFaresWarning(array $response): string
    {
        $providerCode = $this->extractProviderErrorCode($response);

        if (strtoupper((string) $providerCode) === 'FE001') {
            return 'IATI returned FE001: No result found for related request.';
        }

        return 'IATI returned no fares for this route/date.';
    }

    /**
     * @param  array<string, mixed>  $response
     */
    protected function extractProviderErrorCode(array $response): ?string
    {
        foreach (['code', 'error_code', 'status_code'] as $key) {
            $value = trim((string) ($response[$key] ?? ''));
            if ($value !== '' && ! is_array($response[$key] ?? null)) {
                return $value;
            }
        }

        $error = $response['error'] ?? null;
        if (is_array($error)) {
            $value = trim((string) ($error['error_code'] ?? $error['code'] ?? ''));

            return $value !== '' ? $value : null;
        }

        $diagnostic = is_array($response['_ota_diagnostic'] ?? null) ? $response['_ota_diagnostic'] : [];
        $value = trim((string) ($diagnostic['provider_code'] ?? ''));

        return $value !== '' ? $value : null;
    }

    protected function resolveConnection(): ?SupplierConnection
    {
        $id = $this->option('connection');
        if ($id) {
            return SupplierConnection::query()->where('id', (int) $id)->where('provider', SupplierProvider::Iati)->first();
        }

        return SupplierConnection::query()->where('provider', SupplierProvider::Iati)->orderByDesc('is_active')->first();
    }
}
