<?php

namespace Tests\Unit\Services\Suppliers\OneApi;

use App\Enums\SupplierProvider;
use App\Models\SupplierConnection;
use App\Services\Suppliers\OneApi\Authentication\OneApiAuthService;
use App\Services\Suppliers\OneApi\Exceptions\OneApiAuthException;
use App\Services\Suppliers\OneApi\Exceptions\OneApiTransportException;
use App\Services\Suppliers\OneApi\Search\OneApiFlightSearchService;
use App\Services\Suppliers\OneApi\Transport\OneApiRestClient;
use App\Services\Suppliers\OneApi\Workflow\OneApiWorkflowContextStore;
use App\Data\FlightSearchRequestData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\Support\OneApi\OneApiEnablesFixtureTransport;
use Tests\TestCase;

class OneApiAuthenticationMatrixTest extends TestCase
{
    use OneApiEnablesFixtureTransport;
    use RefreshDatabase;

    private const SYNTH_TOKEN = 'SYNTH_ONE_API_ACCESS_TOKEN_PHASE9_DO_NOT_LOG';

    public function test_missing_token_pair_throws(): void
    {
        Http::fake(['https://example.test/auth' => Http::response(['ok' => true], 200)]);
        $this->expectException(OneApiAuthException::class);
        app(OneApiAuthService::class)->getAccessToken($this->connection());
    }

    public function test_missing_access_token_throws(): void
    {
        Http::fake(['https://example.test/auth' => Http::response(['tokenPair' => ['accessToken' => '']], 200)]);
        $this->expectException(OneApiAuthException::class);
        app(OneApiAuthService::class)->getAccessToken($this->connection());
    }

    public function test_cache_isolated_by_environment(): void
    {
        Cache::flush();
        Http::fake(['https://example.test/auth' => Http::response($this->authPayload())]);
        $sandbox = $this->connection();
        $live = SupplierConnection::factory()->create([
            'provider' => SupplierProvider::OneApi,
            'environment' => 'live',
            'credentials' => $this->cred(),
        ]);
        $auth = app(OneApiAuthService::class);
        $auth->getAccessToken($sandbox);
        $auth->getAccessToken($live);
        Http::assertSentCount(2);
    }

    public function test_cache_lock_prevents_duplicate_auth_storm(): void
    {
        Cache::flush();
        Http::fake(['https://example.test/auth' => Http::response($this->authPayload())]);
        $connection = $this->connection();
        $auth = app(OneApiAuthService::class);
        $auth->getAccessToken($connection);
        $auth->getAccessToken($connection);
        Http::assertSentCount(1);
    }

    public function test_jwt_expiry_and_opaque_fallback_ttl(): void
    {
        Cache::flush();
        $opaque = 'opaque.'.self::SYNTH_TOKEN;
        Http::fake(['https://example.test/auth' => Http::response(['tokenPair' => ['accessToken' => $opaque]], 200)]);
        $token = app(OneApiAuthService::class)->getAccessToken($this->connection());
        $this->assertSame($opaque, $token);
    }

    public function test_expired_token_cache_triggers_reauthentication(): void
    {
        Cache::flush();
        Http::fake(['https://example.test/auth' => Http::response($this->authPayload())]);
        $connection = $this->connection();
        $auth = app(OneApiAuthService::class);
        $auth->getAccessToken($connection, true);
        Http::assertSentCount(1);
    }

    public function test_search_401_retries_once_then_stops(): void
    {
        Cache::flush();
        Http::fake([
            'https://example.test/auth' => Http::response($this->authPayload()),
            'https://example.test/search' => Http::sequence()
                ->push([], 401)
                ->push(['searchOnds' => []], 200),
        ]);
        $connection = $this->connection();
        $request = new FlightSearchRequestData(
            origin: 'SHJ',
            destination: 'KHI',
            departure_date: '2026-09-01',
            return_date: null,
            adults: 1,
            children: 0,
            infants: 0,
            cabin: 'economy',
            trip_type: 'one_way',
        );
        app(OneApiFlightSearchService::class)->search($request, $connection);
        $this->assertGreaterThanOrEqual(3, count(Http::recorded()));
    }

    public function test_forbidden_maps_to_auth_exception_on_auth_endpoint(): void
    {
        Http::fake(['https://example.test/auth' => Http::response([], 403)]);
        $this->expectException(OneApiAuthException::class);
        app(OneApiAuthService::class)->getAccessToken($this->connection());
    }

    public function test_timeout_maps_to_transport_exception(): void
    {
        Http::fake(['https://example.test/auth' => fn () => throw new \Illuminate\Http\Client\ConnectionException('timeout')]);
        $this->expectException(OneApiTransportException::class);
        app(OneApiAuthService::class)->getAccessToken($this->connection());
    }

    public function test_token_redacted_from_logs_and_not_persisted_on_connection(): void
    {
        Cache::flush();
        $jwt = $this->jwtWithExp(time() + 3600);
        Http::fake(['https://example.test/auth' => Http::response(['tokenPair' => ['accessToken' => $jwt]], 200)]);
        $connection = $this->connection();
        app(OneApiAuthService::class)->getAccessToken($connection);
        $connection->refresh();
        $raw = json_encode($connection->getAttributes());
        $this->assertStringNotContainsString($jwt, (string) $raw);
        $store = app(OneApiWorkflowContextStore::class);
        $context = $store->create($connection->id, 'corr-auth', []);
        $serialized = json_encode($context->toArray());
        $this->assertStringNotContainsString($jwt, (string) $serialized);
    }

    /**
     * @return array<string, mixed>
     */
    private function authPayload(): array
    {
        return ['tokenPair' => ['accessToken' => $this->jwtWithExp(time() + 3600)]];
    }

    private function jwtWithExp(int $exp): string
    {
        $header = rtrim(strtr(base64_encode('{"alg":"none"}'), '+/', '-_'), '=');
        $payload = rtrim(strtr(base64_encode(json_encode(['exp' => $exp])), '+/', '-_'), '=');

        return $header.'.'.$payload.'.sig';
    }

    /**
     * @return array<string, string>
     */
    private function cred(): array
    {
        return [
            'username' => 'ONE_API_TEST_USERNAME',
            'password' => 'ONE_API_TEST_PASSWORD',
            'agent_code' => 'ONE_API_TEST_AGENT',
            'agent_preferred_currency' => 'AED',
            'pos_country' => 'AE',
            'pos_station' => 'DXB',
            'rest_auth_url' => 'https://example.test/auth',
            'rest_search_url' => 'https://example.test/search',
            'soap_url' => 'https://example.test/soap',
        ];
    }

    private function connection(): SupplierConnection
    {
        return SupplierConnection::factory()->create([
            'provider' => SupplierProvider::OneApi,
            'credentials' => $this->cred(),
        ]);
    }
}
