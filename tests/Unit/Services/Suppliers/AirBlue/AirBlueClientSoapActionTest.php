<?php

namespace Tests\Unit\Services\Suppliers\AirBlue;

use App\Enums\SupplierEnvironment;
use App\Enums\SupplierProvider;
use App\Models\SupplierConnection;
use App\Services\Suppliers\AirBlue\AirBlueClient;
use Illuminate\Support\Facades\Http;
use ReflectionClass;

class AirBlueClientSoapActionTest extends AirBlueOfflineTestCase
{
    public function test_read_soap_action_uses_test_host_for_test_config(): void
    {
        $client = app(AirBlueClient::class);
        $method = (new ReflectionClass($client))->getMethod('resolveOtaSoapAction');
        $method->setAccessible(true);

        $action = $method->invoke($client, 'read', ['is_test' => true, 'protocol_version' => '2.0']);

        $this->assertSame('https://ota.qa.zapways.com/Read', $action);
        // SOAPAction host ≠ HTTP endpoint (nested "2.0" key cannot use dotted config()).
        $v2 = (array) (config('suppliers.airblue.protocol_versions')['2.0'] ?? []);
        $this->assertSame('https://otatest4.zapways.com/v2.0/OTAAPI.asmx', (string) ($v2['default_qa_base_url'] ?? ''));
        $this->assertSame('https://otatest4.zapways.com/v2.0/OTAAPI.asmx', (string) config('suppliers.airblue.default_ota_qa_base_url'));
    }

    public function test_read_soap_action_uses_live_host_for_production_config(): void
    {
        $client = app(AirBlueClient::class);
        $method = (new ReflectionClass($client))->getMethod('resolveOtaSoapAction');
        $method->setAccessible(true);

        $action = $method->invoke($client, 'read', ['is_test' => false, 'protocol_version' => '2.0']);

        $this->assertSame('https://ota.zapways.com/Read', $action);
        $v2 = (array) (config('suppliers.airblue.protocol_versions')['2.0'] ?? []);
        $this->assertSame('https://ota4.zapways.com/v2.0/OTAAPI.asmx', (string) ($v2['default_base_url'] ?? ''));
        $this->assertSame('https://ota4.zapways.com/v2.0/OTAAPI.asmx', (string) config('suppliers.airblue.default_ota_base_url'));
    }

    public function test_sandbox_call_does_not_depend_on_global_external_call_kill_switch(): void
    {
        config(['suppliers.airblue.external_calls_enabled' => false]);

        $connection = new SupplierConnection([
            'provider' => SupplierProvider::Airblue,
            'environment' => SupplierEnvironment::Sandbox,
            'base_url' => 'https://otatest4.zapways.com/v2.0/OTAAPI.asmx',
            'is_active' => true,
            'credentials' => [
                'api_channel' => 'zapways_ota',
                'protocol_version' => '2.0',
                'client_id' => 'client',
                'client_key' => 'key',
                'agent_type' => '5',
                'agent_id' => 'agent',
                'agent_password' => 'secret',
                'service_target' => 'Test',
            ],
        ]);
        $connection->id = 22;

        $fixture = file_get_contents(base_path('tests/Fixtures/airblue/ota_air_low_fare_search_success.xml'));
        $this->assertIsString($fixture);

        Http::fake([
            'otatest4.zapways.com/*' => Http::response($fixture, 200, ['Content-Type' => 'text/xml']),
        ]);

        $result = app(AirBlueClient::class)->call(
            $connection,
            'air_low_fare_search',
            '<ota:test xmlns:ota="http://www.opentravel.org/OTA/2003/05"/>',
        );

        $this->assertIsArray($result);
        Http::assertSentCount(1);
    }
}
