<?php

namespace Tests\Unit\Services\Suppliers\OneApi;

use App\Contracts\Suppliers\OneApi\OneApiSoapTransportContract;
use App\Enums\SupplierProvider;
use App\Models\SupplierConnection;
use App\Services\Suppliers\OneApi\Transport\LiveOneApiSoapTransport;
use App\Support\OneApi\OneApiFixtureTransportScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Support\OneApi\OneApiEnablesFixtureTransport;
use Tests\TestCase;

class OneApiFixtureTransportSecurityTest extends TestCase
{
    use OneApiEnablesFixtureTransport;
    use RefreshDatabase;

    public function test_fixture_path_rejected_when_scope_disabled_outside_phpunit_gate(): void
    {
        OneApiFixtureTransportScope::disable();
        OneApiFixtureTransportScope::disallowUnitTestFixtures();
        $this->expectException(\App\Services\Suppliers\OneApi\Exceptions\OneApiValidationException::class);
        OneApiFixtureTransportScope::resolveReadableFixturePath(base_path('tests/Fixtures/Suppliers/OneApi/price_base.xml'));
    }

    public function test_traversal_outside_fixture_root_rejected(): void
    {
        $this->expectException(\App\Services\Suppliers\OneApi\Exceptions\OneApiValidationException::class);
        OneApiFixtureTransportScope::resolveReadableFixturePath(base_path('config/app.php'));
    }

    public function test_live_transport_ignores_request_supplied_fixture_path(): void
    {
        OneApiFixtureTransportScope::disable();
        OneApiFixtureTransportScope::disallowUnitTestFixtures();
        Http::fake(['*' => Http::response('<soapenv:Envelope/>', 200)]);
        $connection = SupplierConnection::factory()->create([
            'provider' => SupplierProvider::OneApi,
            'credentials' => [
                'username' => 'ONE_API_TEST_USERNAME',
                'password' => 'ONE_API_TEST_PASSWORD',
                'agent_code' => 'AGENT',
                'rest_auth_url' => 'https://example.test/auth',
                'rest_search_url' => 'https://example.test/search',
                'soap_url' => 'https://example.test/soap',
            ],
        ]);
        $transport = app(OneApiSoapTransportContract::class);
        $this->assertInstanceOf(LiveOneApiSoapTransport::class, $transport);
        $transport->call($connection, 'price', '<x/>', 'sess', [
            'fixture_path' => base_path('tests/Fixtures/Suppliers/OneApi/price_base.xml'),
        ]);
        Http::assertSentCount(1);
    }
}
