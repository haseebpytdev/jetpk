<?php

namespace Tests\Unit\Services\Suppliers\AirBlue;

use App\Enums\SupplierEnvironment;
use App\Enums\SupplierProvider;
use App\Models\SupplierConnection;
use App\Services\Suppliers\AirBlue\AirBlueClient;
use App\Services\Suppliers\AirBlue\AirBlueConfigResolver;
use App\Services\Suppliers\AirBlue\Exceptions\AirBlueValidationException;
use ReflectionClass;
use Tests\TestCase;

class AirBlueZapwaysArchitectureTest extends TestCase
{
    public function test_call_ndc_always_fails_closed(): void
    {
        $connection = $this->makeConnection();

        $this->expectException(AirBlueValidationException::class);

        app(AirBlueClient::class)->callNdc($connection, 'air_shopping', '<xml/>');
    }

    public function test_client_has_no_active_ndc_parser_dependency(): void
    {
        $reflection = new ReflectionClass(AirBlueClient::class);
        $constructor = $reflection->getConstructor();
        $this->assertNotNull($constructor);

        $params = array_map(fn ($p) => $p->getName(), $constructor->getParameters());
        $this->assertContains('otaXmlParser', $params);
        $this->assertNotContains('ndcXmlParser', $params);
    }

    public function test_qa_endpoint_resolves_for_sandbox(): void
    {
        $connection = $this->makeConnection([
            'base_url' => null,
            'environment' => SupplierEnvironment::Sandbox,
        ]);

        $config = app(AirBlueConfigResolver::class)->resolveOta($connection);

        $this->assertTrue($config['is_test']);
        $this->assertStringContainsString('ota.qa.zapways.com', $config['endpoint_url']);
    }

    public function test_live_endpoint_resolves_for_live_environment(): void
    {
        $connection = $this->makeConnection([
            'base_url' => null,
            'environment' => SupplierEnvironment::Live,
        ]);

        $config = app(AirBlueConfigResolver::class)->resolveOta($connection);

        $this->assertFalse($config['is_test']);
        $this->assertStringContainsString('ota3.zapways.com', $config['endpoint_url']);
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
