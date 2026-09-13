<?php

namespace Tests\Unit\Support\Suppliers;

use App\Enums\SupplierProvider;
use App\Support\Suppliers\AirBlueSupplierConnectionNormalizer;
use Tests\TestCase;

class AirBlueSupplierConnectionNormalizerTest extends TestCase
{
    public function test_sandbox_defaults_service_target_to_test(): void
    {
        $payload = AirBlueSupplierConnectionNormalizer::normalizePayload([
            'provider' => SupplierProvider::Airblue->value,
            'environment' => 'sandbox',
            'credentials' => [
                'client_id' => 'id',
                'client_key' => 'key',
                'agent_type' => '5',
                'agent_id' => 'agent',
                'agent_password' => 'secret',
            ],
        ]);

        $this->assertSame('Test', $payload['credentials']['service_target']);
        $this->assertSame('1.04', $payload['credentials']['service_version']);
        $this->assertStringContainsString('otatest4.zapways.com', (string) $payload['base_url']);
    }

    public function test_live_defaults_service_target_to_production(): void
    {
        $payload = AirBlueSupplierConnectionNormalizer::normalizePayload([
            'provider' => SupplierProvider::Airblue->value,
            'environment' => 'live',
            'credentials' => [
                'client_id' => 'id',
                'client_key' => 'key',
                'agent_type' => '5',
                'agent_id' => 'agent',
                'agent_password' => 'secret',
            ],
        ]);

        $this->assertSame('Production', $payload['credentials']['service_target']);
        $this->assertStringContainsString('ota4.zapways.com', (string) $payload['base_url']);
    }
}
