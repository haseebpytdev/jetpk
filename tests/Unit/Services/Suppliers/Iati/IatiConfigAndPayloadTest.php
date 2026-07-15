<?php

namespace Tests\Unit\Services\Suppliers\Iati;

use App\Data\FlightSearchRequestData;
use App\Enums\SupplierEnvironment;
use App\Enums\SupplierProvider;
use App\Models\SupplierConnection;
use App\Services\Suppliers\Iati\IatiConfigResolver;
use App\Services\Suppliers\Iati\IatiPayloadBuilder;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class IatiConfigAndPayloadTest extends TestCase
{
    #[Test]
    public function test_resolves_config_without_secret(): void
    {
        $connection = new SupplierConnection([
            'provider' => SupplierProvider::Iati,
            'environment' => SupplierEnvironment::Sandbox,
            'credentials' => ['auth_code' => 'code', 'organization_id' => '187570'],
        ]);

        $config = app(IatiConfigResolver::class)->resolve($connection);

        $this->assertSame('code', $config['auth_code']);
        $this->assertSame('', $config['secret']);
        $this->assertSame('187570', $config['organization_id']);
    }

    #[Test]
    public function test_resolves_test_urls_for_sandbox_environment(): void
    {
        $connection = new SupplierConnection([
            'provider' => SupplierProvider::Iati,
            'environment' => SupplierEnvironment::Sandbox,
            'credentials' => ['auth_code' => 'code', 'organization_id' => '187570'],
        ]);

        $config = app(IatiConfigResolver::class)->resolve($connection);

        $this->assertTrue($config['is_test']);
        $this->assertStringContainsString('testapi.iati.com', $config['flight_base']);
        $this->assertStringContainsString('/rest/auth', $config['auth_base']);
    }

    #[Test]
    public function test_resolves_prod_urls_for_live_environment(): void
    {
        $connection = new SupplierConnection([
            'provider' => SupplierProvider::Iati,
            'environment' => SupplierEnvironment::Live,
            'credentials' => ['auth_code' => 'code', 'organization_id' => '187570'],
        ]);

        $config = app(IatiConfigResolver::class)->resolve($connection);

        $this->assertFalse($config['is_test']);
        $this->assertStringContainsString('api.iati.com', $config['flight_base']);
    }

    #[Test]
    public function test_resolves_auth_base_when_flight_base_url_stored_on_connection(): void
    {
        $connection = new SupplierConnection([
            'provider' => SupplierProvider::Iati,
            'environment' => SupplierEnvironment::Sandbox,
            'base_url' => 'https://testapi.iati.com/rest/flight/v2',
            'credentials' => ['auth_code' => 'code', 'organization_id' => '187570'],
        ]);

        $config = app(IatiConfigResolver::class)->resolve($connection);

        $this->assertSame('https://testapi.iati.com/rest/auth', $config['auth_base']);
        $this->assertSame('https://testapi.iati.com/rest/flight/v2', $config['flight_base']);
    }

    #[Test]
    public function test_builds_one_way_search_payload(): void
    {
        $request = new FlightSearchRequestData(
            origin: 'LHE',
            destination: 'DXB',
            departure_date: '2026-07-01',
            adults: 2,
            children: 1,
            infants: 0,
        );

        $payload = app(IatiPayloadBuilder::class)->buildSearchPayload($request);

        $this->assertSame('LHE', $payload['from_destination']['code']);
        $this->assertSame('DXB', $payload['to_destination']['code']);
        $this->assertSame('2026-07-01', $payload['departure_date']);
        $this->assertSame('ECONOMY', $payload['cabin_type']);
        $this->assertSame([
            ['type' => 'ADULT', 'count' => 2],
            ['type' => 'CHILD', 'count' => 1],
        ], $payload['pax_list']);
    }

    #[Test]
    public function test_search_payload_omits_zero_count_passenger_types(): void
    {
        $request = new FlightSearchRequestData(
            origin: 'LHE',
            destination: 'DXB',
            departure_date: '2026-07-01',
            adults: 1,
            children: 0,
            infants: 0,
        );

        $payload = app(IatiPayloadBuilder::class)->buildSearchPayload($request);

        $this->assertSame([['type' => 'ADULT', 'count' => 1]], $payload['pax_list']);
    }

    #[Test]
    public function test_builds_return_search_payload(): void
    {
        $request = new FlightSearchRequestData(
            origin: 'LHE',
            destination: 'DXB',
            departure_date: '2026-07-01',
            return_date: '2026-07-08',
            trip_type: 'return',
        );

        $payload = app(IatiPayloadBuilder::class)->buildSearchPayload($request);

        $this->assertSame('2026-07-08', $payload['return_date']);
    }

    #[Test]
    public function test_builds_fare_payload_with_fare_keys(): void
    {
        $payload = app(IatiPayloadBuilder::class)->buildFarePayload([
            'departure_fare_key' => 'dep-key',
            'return_fare_key' => 'ret-key',
            'pax_counts' => ['adults' => 1, 'children' => 0, 'infants' => 0],
        ]);

        $this->assertSame('dep-key', $payload['departure_fare_key']);
        $this->assertSame('ret-key', $payload['return_fare_key']);
    }
}
