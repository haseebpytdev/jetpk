<?php

namespace Tests\Unit\Support\Suppliers;

use App\Enums\SupplierProvider;
use App\Support\Suppliers\AirBlueSupplierConnectionNormalizer;
use Tests\TestCase;

class AirBlueSupplierConnectionNormalizerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'suppliers.airblue.default_tls_cert_path' => '/secure/zapways/jetpakistan-zapways.crt.pem',
            'suppliers.airblue.default_tls_key_path' => '/secure/zapways/jetpakistan-zapways.key.pem',
        ]);
    }

    public function test_sandbox_defaults_service_target_to_test(): void
    {
        $payload = AirBlueSupplierConnectionNormalizer::normalizePayload([
            'provider' => SupplierProvider::Airblue->value,
            'environment' => 'sandbox',
            'credentials' => [
                'client_id' => 'id',
                'client_key' => 'key',
                'agent_id' => 'agent',
                'agent_password' => 'secret',
            ],
        ]);

        $this->assertSame('zapways_ota', $payload['credentials']['api_channel']);
        $this->assertSame('29', $payload['credentials']['agent_type']);
        $this->assertSame('2.0', $payload['credentials']['protocol_version']);
        $this->assertSame('Test', $payload['credentials']['service_target']);
        $this->assertSame('1.04', $payload['credentials']['service_version']);
        $this->assertSame('pending', $payload['credentials']['certification_status']);
        $this->assertStringContainsString('otatest4.zapways.com', (string) $payload['base_url']);
        $this->assertSame('/secure/zapways/jetpakistan-zapways.crt.pem', $payload['credentials']['tls_cert_path']);
        $this->assertSame('/secure/zapways/jetpakistan-zapways.key.pem', $payload['credentials']['tls_key_path']);
    }

    public function test_live_defaults_service_target_to_production(): void
    {
        $payload = AirBlueSupplierConnectionNormalizer::normalizePayload([
            'provider' => SupplierProvider::Airblue->value,
            'environment' => 'live',
            'credentials' => [
                'client_id' => 'id',
                'client_key' => 'key',
                'agent_id' => 'agent',
                'agent_password' => 'secret',
            ],
        ]);

        $this->assertSame('Production', $payload['credentials']['service_target']);
        $this->assertStringContainsString('ota4.zapways.com', (string) $payload['base_url']);
    }

    public function test_forces_zapways_even_when_legacy_crane_channel_submitted(): void
    {
        $payload = AirBlueSupplierConnectionNormalizer::normalizePayload([
            'provider' => SupplierProvider::Airblue->value,
            'environment' => 'sandbox',
            'base_url' => 'https://evil.example.test/override',
            'credentials' => [
                'api_channel' => 'crane_ndc',
                'client_id' => 'id',
                'client_key' => 'key',
                'agent_id' => 'agent',
                'agent_password' => 'secret',
            ],
        ]);

        $this->assertSame('zapways_ota', $payload['credentials']['api_channel']);
        $this->assertStringContainsString('otatest4.zapways.com', (string) $payload['base_url']);
        $this->assertStringNotContainsString('evil.example.test', (string) $payload['base_url']);
    }
}
