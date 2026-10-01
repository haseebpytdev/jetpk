<?php

namespace Tests\Unit\Services\Suppliers\AirBlue;

use App\Services\Suppliers\AirBlue\AirBlueClient;
use ReflectionClass;
use Tests\TestCase;

class AirBlueClientSoapActionTest extends TestCase
{
    public function test_read_soap_action_uses_test_host_for_test_config(): void
    {
        $client = app(AirBlueClient::class);
        $method = (new ReflectionClass($client))->getMethod('resolveOtaSoapAction');
        $method->setAccessible(true);

        $action = $method->invoke($client, 'read', ['is_test' => true]);

        $this->assertSame('https://otatest4.zapways.com/Read', $action);
    }

    public function test_read_soap_action_uses_live_host_for_production_config(): void
    {
        $client = app(AirBlueClient::class);
        $method = (new ReflectionClass($client))->getMethod('resolveOtaSoapAction');
        $method->setAccessible(true);

        $action = $method->invoke($client, 'read', ['is_test' => false]);

        $this->assertSame('https://ota4.zapways.com/Read', $action);
    }
}
