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
            'credentials' => [
                'username' => 'ONE_API_TEST_USERNAME',
                'password' => 'ONE_API_TEST_PASSWORD',
                'agent_code' => 'AGENT',
                'rest_auth_url' => 'https://example.test/auth',
                'rest_search_url' => 'https://example.test/search',
            ],
        ]);

        $payload = OneApiSupplierConnectionNormalizer::normalizePayload([
            'provider' => 'one_api',
            'credentials' => [
                'username' => 'ONE_API_TEST_USERNAME',
                'password' => '',
                'agent_code' => 'AGENT',
                'rest_auth_url' => 'https://example.test/auth',
                'rest_search_url' => 'https://example.test/search',
                'soap_url' => '',
            ],
        ], $existing);

        $this->assertSame('ONE_API_TEST_PASSWORD', $payload['credentials']['password']);
    }

    public function test_credentials_are_encrypted_at_rest(): void
    {
        $connection = SupplierConnection::factory()->create([
            'provider' => SupplierProvider::OneApi,
            'credentials' => [
                'username' => 'ONE_API_TEST_USERNAME',
                'password' => 'ONE_API_TEST_PASSWORD',
                'agent_code' => 'AGENT',
                'rest_auth_url' => 'https://example.test/auth',
                'rest_search_url' => 'https://example.test/search',
            ],
        ]);

        $raw = $connection->getAttributes()['credentials'] ?? '';
        $this->assertIsString($raw);
        $this->assertStringNotContainsString('ONE_API_TEST_PASSWORD', $raw);
    }
}
