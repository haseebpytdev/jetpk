<?php

namespace Tests\Feature\Admin;

use App\Enums\SupplierProvider;
use App\Models\Agency;
use App\Models\SupplierConnection;
use App\Models\User;
use App\Support\Suppliers\OneApiSupplierConnectionNormalizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OneApiSupplierConnectionFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_normalizer_preserves_password_on_blank_update(): void
    {
        $existing = SupplierConnection::factory()->create([
            'provider' => SupplierProvider::OneApi,
            'credentials' => $this->oneApiTestCredentials([
                'rest_password' => 'ONE_API_TEST_PASSWORD',
                'soap_password' => 'ONE_API_TEST_PASSWORD',
            ]),
        ]);

        $payload = OneApiSupplierConnectionNormalizer::normalizePayload([
            'provider' => 'one_api',
            'credentials' => [
                'rest_username' => 'ONE_API_TEST_USERNAME',
                'rest_password' => '',
                'soap_username' => 'ONE_API_TEST_USERNAME',
                'soap_password' => '',
                'agent_code' => 'AGENT',
            ],
        ], $existing);

        $this->assertSame('ONE_API_TEST_PASSWORD', $payload['credentials']['rest_password']);
        $this->assertSame('ONE_API_TEST_PASSWORD', $payload['credentials']['soap_password']);
    }

    public function test_credentials_are_encrypted_at_rest(): void
    {
        $connection = SupplierConnection::factory()->create([
            'provider' => SupplierProvider::OneApi,
            'credentials' => $this->oneApiTestCredentials([
                'rest_password' => 'ONE_API_TEST_PASSWORD',
            ]),
        ]);

        $raw = $connection->getAttributes()['credentials'] ?? '';
        $this->assertIsString($raw);
        $this->assertStringNotContainsString('ONE_API_TEST_PASSWORD', $raw);
    }
}
