<?php

namespace App\Console\Commands;

use App\Data\FlightSearchRequestData;
use App\Enums\SupplierProvider;
use App\Models\SupplierConnection;
use App\Services\Suppliers\Iati\IatiAuthService;
use App\Services\Suppliers\Iati\IatiConfigResolver;
use App\Services\Suppliers\Iati\IatiPayloadBuilder;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Non-mutating IATI auth/search probe — compares credential patterns against reference iati.pk.
 * Does not log auth_code, secret, or tokens.
 */
class IatiReferenceAuthProbeCommand extends Command
{
    protected $signature = 'iati:reference-auth-probe
        {--connection= : Supplier connection ID}
        {--from=LHE : Search origin}
        {--to=DXB : Search destination}
        {--date= : Departure YYYY-MM-DD}
        {--skip-search : Skip POST /search probes}';

    protected $description = 'Probe IATI auth patterns (ping, token exchange, search) without logging credentials';

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
        $hasSecret = trim($config['secret']) !== '';

        $this->line('connection_id='.$connection->id);
        $this->line('environment='.$config['environment']);
        $this->line('is_test='.($config['is_test'] ? 'true' : 'false'));
        $this->line('flight_base='.$config['flight_base']);
        $this->line('auth_base='.$config['auth_base']);
        $this->line('organization_id='.$config['organization_id']);
        $this->line('secret_configured='.($hasSecret ? 'yes' : 'no'));
        $this->newLine();

        $timeout = (int) config('suppliers.iati.timeout_seconds', 60);
        $connectTimeout = (int) config('suppliers.iati.connect_timeout_seconds', 10);
        $flightBase = rtrim($config['flight_base'], '/');
        $authBase = rtrim($config['auth_base'], '/');
        $authCode = $config['auth_code'];
        $secret = $config['secret'];
        $organizationId = $config['organization_id'];

        $this->info('=== Token exchange patterns (GET /rest/auth/token) ===');

        $tokenPatterns = [
            'reference_auth_code_colon_secret' => [
                'enabled' => $hasSecret,
                'basic' => $authCode.':'.$secret,
                'note' => 'Reference iati.pk pattern (c1:c2)',
            ],
            'alt_organization_id_colon_secret' => [
                'enabled' => $hasSecret,
                'basic' => $organizationId.':'.$secret,
                'note' => 'Hypothesis: agency id as username',
            ],
            'alt_auth_code_colon_organization_id' => [
                'enabled' => $organizationId !== '',
                'basic' => $authCode.':'.$organizationId,
                'note' => 'Hypothesis: org id as password',
            ],
            'alt_auth_code_only' => [
                'enabled' => true,
                'basic' => $authCode.':',
                'note' => 'Hypothesis: auth code only',
            ],
        ];

        $referenceJwt = null;

        foreach ($tokenPatterns as $name => $pattern) {
            if (! $pattern['enabled']) {
                $this->line("token_{$name}=skipped reason=missing_credentials");

                continue;
            }

            $result = $this->probeTokenExchange($authBase.'/token', $pattern['basic'], $timeout, $connectTimeout);
            $this->printProbeResult('token_'.$name, $result, $pattern['note']);

            if ($name === 'reference_auth_code_colon_secret' && $result['token_present']) {
                $referenceJwt = $result['token'];
            }
        }

        $this->newLine();
        $this->info('=== Ping patterns (GET /test/ping) ===');

        $pingCases = [
            'ping_bearer_auth_code_with_org_header' => [
                'token' => $authCode,
                'organization_id' => $organizationId,
            ],
            'ping_bearer_auth_code_no_org_header' => [
                'token' => $authCode,
                'organization_id' => null,
            ],
        ];

        if ($referenceJwt !== null) {
            $pingCases['ping_bearer_jwt_with_org_header'] = [
                'token' => $referenceJwt,
                'organization_id' => $organizationId,
            ];
            $pingCases['ping_bearer_jwt_no_org_header'] = [
                'token' => $referenceJwt,
                'organization_id' => null,
            ];
        }

        foreach ($pingCases as $name => $case) {
            $result = $this->probeGet(
                $flightBase.'/test/ping',
                (string) $case['token'],
                $case['organization_id'],
                $timeout,
                $connectTimeout,
            );
            $this->printProbeResult($name, $result);
        }

        if ($this->option('skip-search')) {
            $this->warn('search probes skipped (--skip-search)');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->info('=== Search patterns (POST /search) ===');

        $date = (string) ($this->option('date') ?: now()->addMonth()->format('Y-m-d'));
        $searchPayload = $payloadBuilder->buildSearchPayload(FlightSearchRequestData::fromArray([
            'origin' => strtoupper((string) $this->option('from')),
            'destination' => strtoupper((string) $this->option('to')),
            'depart_date' => $date,
            'adults' => 1,
            'children' => 0,
            'infants' => 0,
            'trip_type' => 'one_way',
        ]));

        $searchCases = [
            'search_bearer_auth_code_with_org_header' => [
                'token' => $authCode,
                'organization_id' => $organizationId,
            ],
            'search_bearer_auth_code_no_org_header' => [
                'token' => $authCode,
                'organization_id' => null,
            ],
        ];

        if ($referenceJwt !== null) {
            $searchCases['search_bearer_jwt_with_org_header'] = [
                'token' => $referenceJwt,
                'organization_id' => $organizationId,
            ];
            $searchCases['search_bearer_jwt_no_org_header'] = [
                'token' => $referenceJwt,
                'organization_id' => null,
            ];
        }

        foreach ($searchCases as $name => $case) {
            $result = $this->probePost(
                $flightBase.'/search',
                (string) $case['token'],
                $case['organization_id'],
                $searchPayload,
                $timeout,
                $connectTimeout,
            );
            $this->printProbeResult($name, $result);
        }

        if ($hasSecret) {
            try {
                $exchanged = $authService->getBearerToken($connection);
                $this->line('ota_exchange_via_service='.($exchanged !== $authCode ? 'jwt_differs_from_auth_code' : 'same_as_auth_code'));
            } catch (\Throwable $exception) {
                $this->line('ota_exchange_via_service=failed message='.$exception->getMessage());
            }
        }

        return self::SUCCESS;
    }

    /**
     * @return array{http_status: int|null, ok: bool, provider_code: ?string, message: ?string, token_present: bool, token: ?string}
     */
    private function probeTokenExchange(string $url, string $basicCredentials, int $timeout, int $connectTimeout): array
    {
        try {
            $response = Http::timeout($timeout)
                ->connectTimeout($connectTimeout)
                ->withHeaders([
                    'Authorization' => 'Basic '.base64_encode($basicCredentials),
                    'Accept' => 'application/json',
                ])
                ->get($url);
        } catch (ConnectionException $exception) {
            return [
                'http_status' => null,
                'ok' => false,
                'provider_code' => null,
                'message' => 'transport_failed',
                'token_present' => false,
                'token' => null,
            ];
        }

        $body = $response->json();
        $token = '';
        if (is_array($body)) {
            foreach (['access_token', 'token', 'bearer_token', 'jwt'] as $key) {
                $value = trim((string) ($body[$key] ?? ''));
                if ($value !== '') {
                    $token = preg_replace('/^Bearer\s+/i', '', $value) ?? $value;
                    break;
                }
            }
        }

        return [
            'http_status' => $response->status(),
            'ok' => $response->successful() && $token !== '',
            'provider_code' => $this->providerCode(is_array($body) ? $body : []),
            'message' => $this->safeMessage(is_array($body) ? $body : []),
            'token_present' => $token !== '',
            'token' => $token !== '' ? $token : null,
        ];
    }

    /**
     * @return array{http_status: int|null, ok: bool, provider_code: ?string, message: ?string}
     */
    private function probeGet(string $url, string $bearerToken, ?string $organizationId, int $timeout, int $connectTimeout): array
    {
        try {
            $response = Http::timeout($timeout)
                ->connectTimeout($connectTimeout)
                ->withHeaders($this->flightHeaders($bearerToken, $organizationId, false))
                ->get($url);
        } catch (ConnectionException) {
            return ['http_status' => null, 'ok' => false, 'provider_code' => null, 'message' => 'transport_failed'];
        }

        $body = $response->json();

        return [
            'http_status' => $response->status(),
            'ok' => $response->successful(),
            'provider_code' => $this->providerCode(is_array($body) ? $body : []),
            'message' => $this->safeMessage(is_array($body) ? $body : []),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{http_status: int|null, ok: bool, provider_code: ?string, message: ?string}
     */
    private function probePost(string $url, string $bearerToken, ?string $organizationId, array $payload, int $timeout, int $connectTimeout): array
    {
        try {
            $response = Http::timeout($timeout)
                ->connectTimeout($connectTimeout)
                ->withHeaders($this->flightHeaders($bearerToken, $organizationId, true))
                ->post($url, $payload);
        } catch (ConnectionException) {
            return ['http_status' => null, 'ok' => false, 'provider_code' => null, 'message' => 'transport_failed'];
        }

        $body = $response->json();

        return [
            'http_status' => $response->status(),
            'ok' => $response->successful(),
            'provider_code' => $this->providerCode(is_array($body) ? $body : []),
            'message' => $this->safeMessage(is_array($body) ? $body : []),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function flightHeaders(string $bearerToken, ?string $organizationId, bool $withJsonContentType): array
    {
        $headers = [
            'Authorization' => 'Bearer '.$bearerToken,
            'Accept' => 'application/json',
        ];

        if ($withJsonContentType) {
            $headers['Content-Type'] = 'application/json';
        }

        if ($organizationId !== null && trim($organizationId) !== '') {
            $headers['Organization-Id'] = trim($organizationId);
        }

        return $headers;
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function providerCode(array $body): ?string
    {
        $code = trim((string) ($body['error']['error_code'] ?? $body['code'] ?? $body['error_code'] ?? ''));

        return $code !== '' ? $code : null;
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function safeMessage(array $body): ?string
    {
        $message = trim((string) ($body['error']['description'] ?? $body['message'] ?? $body['description'] ?? ''));
        if ($message === '' || preg_match('/token|password|secret|authorization/i', $message)) {
            return null;
        }

        return strlen($message) > 120 ? substr($message, 0, 120).'…' : $message;
    }

    /**
     * @param  array{http_status: int|null, ok: bool, provider_code: ?string, message: ?string, token_present?: bool}  $result
     */
    private function printProbeResult(string $label, array $result, ?string $note = null): void
    {
        $parts = [
            $label.'='.($result['ok'] ? 'ok' : 'fail'),
            'http='.($result['http_status'] ?? 'n/a'),
        ];

        if (! empty($result['provider_code'])) {
            $parts[] = 'code='.$result['provider_code'];
        }

        if (! empty($result['message'])) {
            $parts[] = 'msg='.$result['message'];
        }

        if (isset($result['token_present'])) {
            $parts[] = 'jwt='.($result['token_present'] ? 'yes' : 'no');
        }

        if ($note !== null) {
            $parts[] = 'note='.$note;
        }

        $this->line(implode(' ', $parts));
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
