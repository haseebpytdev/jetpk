<?php

namespace Tests\Feature\Admin;

use App\Enums\SupplierProvider;
use App\Models\Agency;
use App\Models\SupplierConnection;
use App\Services\Suppliers\EjmiralAerost\EjmiralAerostConfigResolver;
use App\Services\Suppliers\EjmiralAerost\EjmiralAerostReadinessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\PlatformAdminTestHelpers;
use Tests\TestCase;

class EjmiralAerostCredentialPortalMinimizationTest extends TestCase
{
    use PlatformAdminTestHelpers;
    use RefreshDatabase;

    public function test_normal_form_shows_only_runtime_credentials(): void
    {
        $fields = (array) config('supplier_credentials.providers.ejmiral_aerost.fields', []);
        $keys = array_keys($fields);

        $this->assertSame(
            ['username', 'password'],
            $keys,
            'AEROST_NORMAL_FORM_SHOWS_ONLY_RUNTIME_CREDENTIALS'
        );
        $this->assertCount(2, $keys, 'AEROST_VISIBLE_CREDENTIAL_FIELD_COUNT');
        $this->assertArrayNotHasKey('api_base_url', $fields, 'AEROST_API_HOST_GUESSED=NO / not operator field');
        $this->assertArrayNotHasKey('psa_username', $fields, 'AEROST_PSA_CREDENTIAL_FIELDS_PRESENT=NO');
        $this->assertArrayNotHasKey('psa_password', $fields, 'AEROST_PSA_CREDENTIAL_FIELDS_PRESENT=NO');
        $this->assertArrayNotHasKey('bearer_token', $fields, 'AEROST_BEARER_TOKEN_MANUAL_FIELD_PRESENT=NO');
        $this->assertArrayNotHasKey('token', $fields, 'AEROST_BEARER_TOKEN_MANUAL_FIELD_PRESENT=NO');
        $this->assertArrayNotHasKey('existing_token', $fields, 'AEROST_BEARER_TOKEN_MANUAL_FIELD_PRESENT=NO');
        $this->assertArrayNotHasKey('token_ttl_seconds', $fields);
        $this->assertArrayNotHasKey('connect_timeout_seconds', $fields);
    }

    public function test_api_host_status_unresolved_until_system_config(): void
    {
        config(['suppliers.ejmiral_aerost.default_api_base_url' => '']);
        $resolver = app(EjmiralAerostConfigResolver::class);
        $this->assertSame('UNRESOLVED', $resolver->apiBaseUrlStatus());

        $connection = SupplierConnection::factory()->create([
            'provider' => SupplierProvider::EjmiralAerost,
            'credentials' => [
                'username' => 'sub_agent',
                'password' => 'secret_fixture',
                // Operator must not supply API host; even if present it is ignored.
                'api_base_url' => 'https://sa.uat.ejmiral.com:7777',
            ],
        ]);

        $readiness = app(EjmiralAerostReadinessService::class)->dimensions($connection);
        $this->assertTrue($readiness['AEROST_CREDENTIAL_READINESS']['ready']);
        $this->assertFalse($readiness['AEROST_API_READINESS']['ready']);
        $this->assertSame('FAIL_CLOSED', $readiness['AEROST_API_READINESS']['code']);
        $this->assertSame('UNRESOLVED', $readiness['AEROST_API_BASE_URL_STATUS']['code']);

        config(['suppliers.ejmiral_aerost.default_api_base_url' => 'https://aerost.example.test']);
        $this->assertSame('CONFIGURED', $resolver->apiBaseUrlStatus());
        $resolved = $resolver->resolve($connection->fresh());
        $this->assertSame('https://aerost.example.test', $resolved['api_base_url']);
        $this->assertStringNotContainsString('sa.uat.ejmiral.com', $resolved['api_base_url']);
    }

    public function test_create_validation_requires_only_sub_agent_credentials(): void
    {
        $admin = $this->platformAdmin();
        $agency = Agency::factory()->create();
        $admin->forceFill(['current_agency_id' => $agency->id])->save();

        $response = $this->actingAs($admin)->post(route('admin.api-settings.store'), [
            'provider' => 'ejmiral_aerost',
            'name' => 'AEROST UAT Sub Agent',
            'environment' => 'sandbox',
            'status' => 'inactive',
            'is_active' => '0',
            'agency_id' => $agency->id,
            'credentials' => [
                'username' => 'sub_agent_uat',
                'password' => 'sub_agent_secret',
            ],
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
        $connection = SupplierConnection::query()
            ->where('provider', SupplierProvider::EjmiralAerost)
            ->where('name', 'AEROST UAT Sub Agent')
            ->first();

        $this->assertNotNull($connection);
        $this->assertSame('sub_agent_uat', $connection->credentials['username'] ?? null);
        $this->assertSame('sub_agent_secret', $connection->credentials['password'] ?? null);
        $this->assertArrayNotHasKey('api_base_url', $connection->credentials ?? []);
    }

    public function test_credentials_encrypted_at_rest(): void
    {
        $connection = SupplierConnection::factory()->create([
            'provider' => SupplierProvider::EjmiralAerost,
            'credentials' => [
                'username' => 'sub_agent_uat',
                'password' => 'sub_agent_secret_value',
            ],
        ]);

        $raw = $connection->getAttributes()['credentials'] ?? '';
        $this->assertIsString($raw);
        $this->assertStringNotContainsString('sub_agent_secret_value', $raw);
    }

    public function test_password_blank_on_edit_preserves_existing(): void
    {
        $admin = $this->platformAdmin();
        $agency = Agency::factory()->create();
        $admin->forceFill(['current_agency_id' => $agency->id])->save();
        $connection = SupplierConnection::factory()->create([
            'agency_id' => $agency->id,
            'provider' => SupplierProvider::EjmiralAerost,
            'name' => 'AEROST keep password',
            'credentials' => [
                'username' => 'sub_agent_uat',
                'password' => 'original_secret',
            ],
        ]);

        $response = $this->actingAs($admin)->patch(route('admin.api-settings.update', $connection), [
            'provider' => 'ejmiral_aerost',
            'name' => 'AEROST keep password',
            'environment' => 'sandbox',
            'status' => 'inactive',
            'is_active' => false,
            'agency_id' => $agency->id,
            'credentials' => [
                'username' => 'sub_agent_uat',
                'password' => '',
            ],
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
        $this->assertSame('original_secret', $connection->fresh()->credentials['password'] ?? null);
    }
}
