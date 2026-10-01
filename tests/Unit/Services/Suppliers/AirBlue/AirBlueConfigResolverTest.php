<?php

namespace Tests\Unit\Services\Suppliers\AirBlue;

use App\Enums\SupplierEnvironment;
use App\Enums\SupplierProvider;
use App\Models\SupplierConnection;
use App\Services\Suppliers\AirBlue\AirBlueConfigResolver;
use Tests\TestCase;

class AirBlueConfigResolverTest extends TestCase
{
    public function test_sandbox_resolves_test_endpoint_and_target(): void
    {
        $connection = $this->makeConnection([
            'environment' => SupplierEnvironment::Sandbox,
            'base_url' => null,
        ]);

        $config = app(AirBlueConfigResolver::class)->resolveOta($connection);

        $this->assertTrue($config['is_test']);
        $this->assertStringContainsString('otatest4.zapways.com', $config['endpoint_url']);
        $this->assertSame('Test', $config['service_target']);
        $this->assertSame('1.04', $config['service_version']);
    }

    public function test_demo_resolves_test_endpoint_and_target(): void
    {
        $connection = $this->makeConnection([
            'environment' => SupplierEnvironment::Demo,
            'base_url' => null,
        ]);

        $config = app(AirBlueConfigResolver::class)->resolveOta($connection);

        $this->assertTrue($config['is_test']);
        $this->assertStringContainsString('otatest4.zapways.com', $config['endpoint_url']);
        $this->assertSame('Test', $config['service_target']);
    }

    public function test_live_resolves_production_endpoint_and_target(): void
    {
        $connection = $this->makeConnection([
            'environment' => SupplierEnvironment::Live,
            'base_url' => null,
        ]);

        $config = app(AirBlueConfigResolver::class)->resolveOta($connection);

        $this->assertFalse($config['is_test']);
        $this->assertStringContainsString('ota4.zapways.com', $config['endpoint_url']);
        $this->assertSame('Production', $config['service_target']);
    }

    public function test_explicit_service_target_override_is_honored(): void
    {
        $connection = $this->makeConnection([
            'environment' => SupplierEnvironment::Sandbox,
            'credentials' => [
                'api_channel' => 'zapways_ota',
                'client_id' => 'client',
                'client_key' => 'key',
                'agent_type' => '5',
                'agent_id' => 'agent',
                'agent_password' => 'secret',
                'service_target' => 'Production',
            ],
        ]);

        $config = app(AirBlueConfigResolver::class)->resolveOta($connection);

        $this->assertSame('Production', $config['service_target']);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function makeConnection(array $attributes = []): SupplierConnection
    {
        $connection = new SupplierConnection([
            'provider' => SupplierProvider::Airblue,
            'environment' => SupplierEnvironment::Sandbox,
            'credentials' => [
                'api_channel' => 'zapways_ota',
                'client_id' => 'client',
                'client_key' => 'key',
                'agent_type' => '5',
                'agent_id' => 'agent',
                'agent_password' => 'secret',
            ],
            ...$attributes,
        ]);
        $connection->id = 42;

        return $connection;
    }
}
