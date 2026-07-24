<?php

namespace Tests\Unit\Services\Suppliers\OneApi;

use App\Data\FlightSearchRequestData;
use App\Enums\SupplierConnectionStatus;
use App\Enums\SupplierProvider;
use App\Models\Agency;
use App\Models\PlatformModuleSetting;
use App\Models\SupplierConnection;
use App\Services\FlightSearch\FlightSearchService;
use App\Services\Platform\PlatformModuleSettingsService;
use App\Services\Suppliers\Adapters\DuffelFlightSupplierAdapter;
use App\Services\Suppliers\Adapters\OneApiFlightSupplierAdapter;
use App\Services\Suppliers\Adapters\SabreFlightSupplierAdapter;
use App\Services\Suppliers\OneApi\Search\OneApiFlightSearchService;
use App\Services\Suppliers\OneApi\Transport\OneApiRestClient;
use App\Support\OneApi\OneApiFixtureTransportScope;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OneApiDormantLiveSearchGateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake();
    }

    protected function tearDown(): void
    {
        OneApiFixtureTransportScope::disable();
        OneApiFixtureTransportScope::allowUnitTestFixtures();
        parent::tearDown();
    }

    public function test_environment_gate_blocks_search_before_http_transport(): void
    {
        config(['suppliers.one_api.live_search_enabled' => false]);
        OneApiFixtureTransportScope::disable();
        OneApiFixtureTransportScope::disallowUnitTestFixtures();

        $this->mock(OneApiRestClient::class, function ($mock): void {
            $mock->shouldReceive('postSearch')->never();
        });

        $result = app(OneApiFlightSearchService::class)->search(
            $this->searchRequest(),
            $this->connection(),
        );

        $this->assertSame([], $result->offers);
        $this->assertSame('live_search_disabled', $result->meta['error_code'] ?? null);
        Http::assertNothingSent();
    }

    public function test_connection_live_search_flag_blocks_search_before_http_transport(): void
    {
        config(['suppliers.one_api.live_search_enabled' => true]);
        OneApiFixtureTransportScope::disable();
        OneApiFixtureTransportScope::disallowUnitTestFixtures();

        $this->mock(OneApiRestClient::class, function ($mock): void {
            $mock->shouldReceive('postSearch')->never();
        });

        $connection = $this->connection(['live_search_enabled' => 'false']);
        $result = app(OneApiFlightSearchService::class)->search(
            $this->searchRequest(),
            $connection,
        );

        $this->assertSame([], $result->offers);
        $this->assertSame('live_search_disabled', $result->meta['error_code'] ?? null);
        Http::assertNothingSent();
    }

    public function test_inactive_connection_blocks_adapter_before_search_service(): void
    {
        $this->mock(OneApiFlightSearchService::class, function ($mock): void {
            $mock->shouldReceive('search')->never();
        });

        $connection = SupplierConnection::factory()->create([
            'provider' => SupplierProvider::OneApi,
            'is_active' => false,
            'status' => SupplierConnectionStatus::Inactive,
            'credentials' => $this->credentials(),
        ]);

        $result = app(OneApiFlightSupplierAdapter::class)->search(
            $this->searchRequest(),
            $connection,
        );

        $this->assertSame([], $result->offers);
        $this->assertStringContainsString('inactive', strtolower($result->warnings[0] ?? ''));
        Http::assertNothingSent();
    }

    public function test_platform_module_gate_skips_one_api_supplier_search_path(): void
    {
        $this->seed(OtaFoundationSeeder::class);

        PlatformModuleSetting::query()->create([
            'module_key' => 'one_api_supplier',
            'enabled' => false,
        ]);
        app(PlatformModuleSettingsService::class)->forgetCache();

        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        SupplierConnection::query()->where('agency_id', $agency->id)->update([
            'is_active' => false,
            'status' => SupplierConnectionStatus::Inactive,
        ]);

        SupplierConnection::factory()->create([
            'agency_id' => $agency->id,
            'provider' => SupplierProvider::OneApi,
            'status' => SupplierConnectionStatus::Active,
            'is_active' => true,
            'credentials' => $this->credentials(),
        ]);

        $this->mock(OneApiFlightSupplierAdapter::class, function ($mock): void {
            $mock->shouldReceive('search')->never();
        });
        $this->mock(DuffelFlightSupplierAdapter::class, function ($mock): void {
            $mock->shouldReceive('search')->never();
        });
        $this->mock(SabreFlightSupplierAdapter::class, function ($mock): void {
            $mock->shouldReceive('search')->never();
        });

        $result = app(FlightSearchService::class)->searchWithMeta([
            'origin' => 'SHJ',
            'destination' => 'KHI',
            'depart_date' => '2026-09-01',
            'adults' => 1,
        ], $agency, 'public_guest');

        $this->assertSame([], $result['offers']);
        Http::assertNothingSent();
    }

    public function test_fixture_backed_search_executes_without_live_search_enabled(): void
    {
        Cache::flush();
        OneApiFixtureTransportScope::enable('dormant_live_gate');
        config(['suppliers.one_api.live_search_enabled' => false]);

        $jwt = $this->jwtWithExp(time() + 3600);
        Http::fake([
            'https://example.test/auth' => Http::response([
                'tokenPair' => ['accessToken' => $jwt],
            ]),
            'https://example.test/search' => Http::response(
                json_decode((string) file_get_contents(base_path('tests/Fixtures/Suppliers/OneApi/search_oneway.json')), true),
            ),
        ]);

        $result = app(OneApiFlightSearchService::class)->search(
            $this->searchRequest(),
            $this->connection(),
        );

        $this->assertNotSame('live_search_disabled', $result->meta['error_code'] ?? null);
        $this->assertGreaterThanOrEqual(1, count(Http::recorded()));
    }

    private function jwtWithExp(int $exp): string
    {
        $header = rtrim(strtr(base64_encode('{"alg":"none"}'), '+/', '-_'), '=');
        $payload = rtrim(strtr(base64_encode(json_encode(['exp' => $exp])), '+/', '-_'), '=');

        return $header.'.'.$payload.'.sig';
    }

    private function searchRequest(): FlightSearchRequestData
    {
        return new FlightSearchRequestData(
            origin: 'SHJ',
            destination: 'KHI',
            departure_date: '2026-08-15',
            return_date: null,
            adults: 1,
            children: 0,
            infants: 0,
            cabin: 'economy',
            trip_type: 'one_way',
        );
    }

    /**
     * @param  array<string, string>  $credentialOverrides
     */
    private function connection(array $credentialOverrides = []): SupplierConnection
    {
        return SupplierConnection::factory()->create([
            'provider' => SupplierProvider::OneApi,
            'is_active' => true,
            'status' => SupplierConnectionStatus::Active,
            'credentials' => array_merge($this->credentials(), $credentialOverrides),
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function credentials(): array
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
}
