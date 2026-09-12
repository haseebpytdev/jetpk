<?php

namespace Tests\Unit\Support\Suppliers;

use App\Http\Controllers\Admin\SupplierConnectionController;
use App\Services\Suppliers\AirBlue\AirBlueConfigResolver;
use App\Services\Suppliers\AirBlue\Exceptions\AirBlueValidationException;
use App\Services\Suppliers\PiaNdc\PiaNdcConfigResolver;
use App\Enums\SupplierEnvironment;
use App\Enums\SupplierProvider;
use App\Models\SupplierConnection;
use ReflectionClass;
use Tests\TestCase;

class SupplierProviderCardCanonicalLabelsTest extends TestCase
{

    public function test_provider_cards_use_canonical_pia_and_airblue_labels(): void
    {
        $controller = new ReflectionClass(SupplierConnectionController::class);
        $method = $controller->getMethod('providerCards');
        $method->setAccessible(true);
        $cards = $method->invoke(app(SupplierConnectionController::class), []);
        $byKey = collect($cards)->keyBy('key');

        $pia = $byKey->get('pia_ndc');
        $this->assertIsArray($pia);
        $this->assertStringContainsString('PIA', (string) $pia['label']);
        $this->assertStringContainsString('Hitit', (string) $pia['label']);
        $this->assertSame('Hitit Crane NDC 20.1', $pia['channel']);
        $this->assertStringContainsString('Hitit Crane', (string) $pia['description']);

        $airblue = $byKey->get('airblue');
        $this->assertIsArray($airblue);
        $this->assertStringContainsString('AirBlue', (string) $airblue['label']);
        $this->assertStringContainsString('Zapways', (string) $airblue['label']);
        $this->assertSame('Zapways API / NDC', $airblue['channel']);
        $this->assertStringContainsString('Zapways', (string) $airblue['description']);
        $this->assertStringNotContainsString('Crane', (string) $airblue['label']);
        $this->assertStringNotContainsString('Crane', (string) $airblue['channel']);
        $this->assertStringNotContainsString('Crane', (string) $airblue['description']);
        $this->assertStringNotContainsString('Hitit', (string) $airblue['label']);
        $this->assertStringNotContainsString('Hitit', (string) $airblue['channel']);
        $this->assertStringNotContainsString('Hitit', (string) $airblue['description']);
    }

    public function test_pia_config_resolver_uses_pia_ndc_crane_endpoint_default(): void
    {
        $connection = $this->makeConnection(SupplierProvider::PiaNdc, [
            'base_url' => null,
            'credentials' => [
                'username' => 'pia-user',
                'password' => 'pia-pass',
                'agency_id' => 'AGY1',
                'agency_name' => 'JetPakistan',
                'owner_code' => 'PK',
            ],
        ]);

        $config = app(PiaNdcConfigResolver::class)->resolve($connection);

        $this->assertSame(
            rtrim((string) config('suppliers.pia_ndc.default_ndc_base_url'), '/'),
            $config['endpoint_url'],
        );
        $this->assertStringContainsString('crane.aero', $config['endpoint_url']);
    }

    public function test_airblue_config_resolver_rejects_legacy_crane_channel_without_db_mutation(): void
    {
        $connection = $this->makeConnection(SupplierProvider::Airblue, [
            'base_url' => 'https://app.crane.aero/cranendc/v20.1/CraneNDCService',
            'credentials' => [
                'api_channel' => 'crane_ndc',
                'username' => 'legacy-user',
                'password' => 'legacy-pass',
            ],
        ]);

        $this->expectException(AirBlueValidationException::class);
        $this->expectExceptionMessage('PIA NDC');

        app(AirBlueConfigResolver::class)->resolve($connection);
        $this->assertSame('crane_ndc', $connection->credentials['api_channel']);
    }

    public function test_airblue_config_resolver_resolves_zapways_configuration(): void
    {
        $connection = $this->makeConnection(SupplierProvider::Airblue, [
            'base_url' => 'https://ota.qa.zapways.com/v2.0/OTAAPI.asmx',
            'credentials' => [
                'api_channel' => 'zapways_ota',
                'client_id' => 'client',
                'client_key' => 'key',
                'agent_type' => '5',
                'agent_id' => 'agent',
                'agent_password' => 'secret',
            ],
        ]);

        $config = app(AirBlueConfigResolver::class)->resolve($connection);

        $this->assertSame('zapways_ota', $config['api_channel']);
        $this->assertStringContainsString('zapways.com', $config['endpoint_url']);
        $this->assertStringNotContainsString('crane.aero', $config['endpoint_url']);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function makeConnection(SupplierProvider $provider, array $attributes = []): SupplierConnection
    {
        $connection = new SupplierConnection([
            'provider' => $provider,
            'environment' => SupplierEnvironment::Sandbox,
            'name' => 'Test connection',
            ...$attributes,
        ]);
        $connection->id = 99;

        return $connection;
    }
}
