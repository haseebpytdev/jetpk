<?php

namespace Tests\Feature\Admin;

use App\Enums\AccountType;
use App\Enums\SupplierConnectionStatus;
use App\Enums\SupplierEnvironment;
use App\Enums\SupplierProvider;
use App\Models\Agency;
use App\Models\SupplierConnection;
use App\Models\User;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AirBlueZapwaysConnectionStoreTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
        config([
            'suppliers.airblue.default_tls_cert_path' => '/secure/zapways/jetpakistan-zapways.crt.pem',
            'suppliers.airblue.default_tls_key_path' => '/secure/zapways/jetpakistan-zapways.key.pem',
        ]);
    }

    public function test_airblue_store_forces_zapways_and_derives_server_defaults(): void
    {
        $admin = $this->platformAdmin();

        $this->actingAs($admin)->post('/admin/api-settings', [
            'provider' => SupplierProvider::Airblue->value,
            'name' => 'AirBlue Zapways Store Defaults',
            'environment' => SupplierEnvironment::Sandbox->value,
            'status' => SupplierConnectionStatus::Inactive->value,
            'credentials' => [
                'client_id' => 'test-client-id',
                'client_key' => 'test-client-key',
                'agent_id' => 'test-agent-id',
                'agent_password' => 'test-agent-password',
            ],
            'settings_json' => '{}',
        ])->assertRedirect('/admin/dashboard/api-connections');

        $connection = SupplierConnection::query()
            ->where('name', 'AirBlue Zapways Store Defaults')
            ->where('provider', SupplierProvider::Airblue)
            ->firstOrFail();

        $this->assertSame('zapways_ota', $connection->credentials['api_channel']);
        $this->assertSame('29', $connection->credentials['agent_type']);
        $this->assertSame('2.0', $connection->credentials['protocol_version']);
        $this->assertSame('1.04', $connection->credentials['service_version']);
        $this->assertSame('Test', $connection->credentials['service_target']);
        $this->assertSame('pending', $connection->credentials['certification_status']);
        $this->assertSame('PA', $connection->credentials['carrier_code']);
        $this->assertSame('PKR', $connection->credentials['currency']);
        $this->assertStringContainsString('otatest4.zapways.com', (string) $connection->base_url);
        $this->assertSame('/secure/zapways/jetpakistan-zapways.crt.pem', $connection->credentials['tls_cert_path']);
        $this->assertSame('/secure/zapways/jetpakistan-zapways.key.pem', $connection->credentials['tls_key_path']);
    }

    public function test_airblue_live_store_resolves_ota4_and_production_target(): void
    {
        $admin = $this->platformAdmin();

        $this->actingAs($admin)->post('/admin/api-settings', [
            'provider' => SupplierProvider::Airblue->value,
            'name' => 'AirBlue Zapways LIVE v2',
            'environment' => SupplierEnvironment::Live->value,
            'status' => SupplierConnectionStatus::Inactive->value,
            'credentials' => [
                'client_id' => 'live-client-id',
                'client_key' => 'live-client-key',
                'agent_id' => 'live-agent-id',
                'agent_password' => 'live-agent-password',
            ],
            'settings_json' => '{}',
        ])->assertRedirect('/admin/dashboard/api-connections');

        $connection = SupplierConnection::query()
            ->where('name', 'AirBlue Zapways LIVE v2')
            ->firstOrFail();

        $this->assertSame('Production', $connection->credentials['service_target']);
        $this->assertStringContainsString('ota4.zapways.com', (string) $connection->base_url);
    }

    public function test_airblue_store_does_not_require_base_url_or_crane_fields(): void
    {
        $admin = $this->platformAdmin();

        $this->actingAs($admin)->post('/admin/api-settings', [
            'provider' => SupplierProvider::Airblue->value,
            'name' => 'AirBlue Minimal Contract',
            'environment' => SupplierEnvironment::Sandbox->value,
            'status' => SupplierConnectionStatus::Inactive->value,
            'credentials' => [
                'client_id' => 'id',
                'client_key' => 'key',
                'agent_id' => 'agent',
                'agent_password' => 'secret',
            ],
            'settings_json' => '{}',
        ])->assertSessionHasNoErrors();
    }

    public function test_airblue_store_rejects_missing_required_zapways_fields(): void
    {
        $admin = $this->platformAdmin();

        $this->actingAs($admin)->post('/admin/api-settings', [
            'provider' => SupplierProvider::Airblue->value,
            'name' => 'AirBlue Missing Fields',
            'environment' => SupplierEnvironment::Sandbox->value,
            'status' => SupplierConnectionStatus::Inactive->value,
            'credentials' => [
                'client_id' => 'id',
            ],
            'settings_json' => '{}',
        ])->assertSessionHasErrors(['credentials.client_key', 'credentials.agent_id', 'credentials.agent_password']);
    }

    public function test_airblue_json_payload_never_returns_raw_secrets(): void
    {
        $admin = $this->platformAdmin();
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();

        $connection = SupplierConnection::factory()->create([
            'agency_id' => $agency->id,
            'provider' => SupplierProvider::Airblue,
            'name' => 'AirBlue Secret Masking',
            'environment' => SupplierEnvironment::Sandbox,
            'status' => SupplierConnectionStatus::Inactive,
            'credentials' => [
                'api_channel' => 'zapways_ota',
                'client_id' => 'stored-client-id',
                'client_key' => 'stored-client-key',
                'agent_id' => 'stored-agent-id',
                'agent_password' => 'stored-agent-password',
                'agent_type' => '29',
            ],
            'base_url' => 'https://otatest4.zapways.com/v2.0/OTAAPI.asmx',
        ]);

        $row = collect($this->actingAs($admin)->getJson('/admin/api-settings?format=json')->json('connections'))
            ->firstWhere('id', (string) $connection->id);
        $payload = json_encode($row);

        $this->assertStringNotContainsString('stored-client-key', (string) $payload);
        $this->assertStringNotContainsString('stored-agent-password', (string) $payload);
    }

    public function test_airblue_catalog_is_minimal_and_base_url_not_overridable(): void
    {
        $admin = $this->platformAdmin();

        $catalog = collect($this->actingAs($admin)->getJson('/admin/api-settings?format=json')->json('providers'))
            ->firstWhere('key', 'airblue');

        $this->assertNotNull($catalog);
        $this->assertFalse((bool) ($catalog['baseUrlOverridable'] ?? true));
        $keys = collect($catalog['credentialFields'] ?? [])->pluck('key')->all();
        $this->assertSame(['client_id', 'client_key', 'agent_id', 'agent_password'], $keys);
        $this->assertNotContains('username', $keys);
        $this->assertNotContains('api_channel', $keys);
        $this->assertNotContains('agent_type', $keys);
        $clientKeyField = collect($catalog['credentialFields'] ?? [])->firstWhere('key', 'client_key');
        $this->assertSame('password', $clientKeyField['type'] ?? null);
    }

    protected function platformAdmin(): User
    {
        $admin = User::query()->where('email', 'admin@ota.demo')->firstOrFail();
        $admin->forceFill(['account_type' => AccountType::PlatformAdmin])->save();

        return $admin->fresh();
    }
}
