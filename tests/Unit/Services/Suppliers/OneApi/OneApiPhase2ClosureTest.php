<?php

namespace Tests\Unit\Services\Suppliers\OneApi;

use App\Enums\SupplierProvider;
use App\Models\SupplierConnection;
use App\Contracts\Suppliers\OneApi\OneApiSoapTransportContract;
use App\Services\Suppliers\OneApi\Authentication\OneApiAuthService;
use App\Services\Suppliers\OneApi\Transport\FixtureOneApiSoapTransport;
use App\Support\OneApi\OneApiFixtureTransportScope;
use App\Support\OneApi\OneApiTestMatrixRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\Support\OneApi\OneApiEnablesFixtureTransport;
use Tests\TestCase;

class OneApiPhase2ClosureTest extends TestCase
{
    use OneApiEnablesFixtureTransport;
    use RefreshDatabase;

    public function test_auth_malformed_response_throws(): void
    {
        Http::fake(['https://example.test/auth' => Http::response('not-json', 200)]);
        $connection = $this->connection();
        $auth = app(OneApiAuthService::class);
        $this->expectException(\App\Services\Suppliers\OneApi\Exceptions\OneApiAuthException::class);
        $auth->getAccessToken($connection);
    }

    public function test_auth_cache_key_scoped_by_connection(): void
    {
        Cache::flush();
        Http::fake(['https://example.test/auth' => Http::response(
            json_decode(file_get_contents(base_path('tests/Fixtures/Suppliers/OneApi/auth_success.json')), true)
        )]);
        $c1 = $this->connection();
        $c2 = SupplierConnection::factory()->create([
            'provider' => SupplierProvider::OneApi,
            'credentials' => array_merge($this->cred(), ['username' => 'OTHER_USER']),
        ]);
        $auth = app(OneApiAuthService::class);
        $auth->getAccessToken($c1);
        $auth->getAccessToken($c2);
        Http::assertSentCount(2);
    }

    public function test_soap_cookie_jar_persists_between_calls(): void
    {
        OneApiFixtureTransportScope::enable('fixture_command');
        $transport = app(OneApiSoapTransportContract::class);
        $this->assertInstanceOf(FixtureOneApiSoapTransport::class, $transport);
        $connection = $this->connection();
        $fixture = base_path('tests/Fixtures/Suppliers/OneApi/price_base.xml');
        $transport->call($connection, 'price', '<x/>', 'session-1', ['fixture_path' => $fixture]);
        $this->assertNotEmpty($transport->cookiesForSession('session-1'));
        $transport->call($connection, 'price', '<x/>', 'session-1', ['fixture_path' => $fixture]);
        $this->assertCount(1, $transport->cookiesForSession('session-1'));
    }

    public function test_matrix_runner_executes_fixture_case(): void
    {
        OneApiFixtureTransportScope::enable('matrix_command');
        Http::fake(['*' => Http::response(
            json_decode((string) file_get_contents(base_path('tests/Fixtures/Suppliers/OneApi/auth_success.json')), true)
        )]);
        $runner = app(OneApiTestMatrixRunner::class);
        $connection = $this->connection();
        $row = $runner->runCase($connection, [
            'flow' => 'ONEWAY',
            'id' => '1',
            'test_case' => 'Booking with 1 Adult',
            'key' => 'oneway_basic_1',
        ], false);
        $this->assertSame('pass', $row['result']);
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
