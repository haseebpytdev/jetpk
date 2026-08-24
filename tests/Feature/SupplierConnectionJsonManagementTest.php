<?php

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Enums\SupplierConnectionStatus;
use App\Enums\SupplierEnvironment;
use App\Enums\SupplierProvider;
use App\Models\User;
use App\Support\Suppliers\SabreCapabilityTruth;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierConnectionJsonManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_admin_can_list_create_toggle_test_and_delete_json(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $admin = $this->platformAdmin();

        $list = $this->actingAs($admin)->getJson('/admin/api-settings?format=json');
        $list->assertOk();

        $create = $this->actingAs($admin)->postJson('/admin/api-settings?format=json', [
            'provider' => SupplierProvider::Duffel->value,
            'name' => 'QA Duffel sandbox',
            'environment' => SupplierEnvironment::Sandbox->value,
            'status' => SupplierConnectionStatus::Inactive->value,
            'credentials' => ['access_token' => 'qa-fixture-token'],
            'settings_json' => '{}',
        ]);
        $create->assertOk()->assertJsonPath('ok', true);
        $id = (string) $create->json('connection.id');
        $this->assertNotSame('', $id);
        $create->assertJsonMissing(['qa-fixture-token']);

        $this->actingAs($admin)
            ->patchJson('/admin/api-settings/'.$id.'/toggle-status?format=json')
            ->assertOk()
            ->assertJsonPath('ok', true);

        $test = $this->actingAs($admin)->patchJson('/admin/api-settings/'.$id.'/test?format=json');
        $test->assertOk()->assertJsonPath('ok', true);
        $encoded = $test->getContent();
        $this->assertStringNotContainsString('qa-fixture-token', $encoded);

        $this->actingAs($admin)
            ->deleteJson('/admin/api-settings/'.$id.'?format=json')
            ->assertOk()
            ->assertJsonPath('ok', true);
    }

    public function test_staff_cannot_mutate_api_connections(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $staff = User::query()->where('email', 'staff@ota.demo')->firstOrFail();

        $this->actingAs($staff)
            ->postJson('/admin/api-settings?format=json', [
                'provider' => SupplierProvider::Duffel->value,
                'name' => 'Forbidden',
                'environment' => SupplierEnvironment::Sandbox->value,
                'status' => SupplierConnectionStatus::Inactive->value,
                'credentials' => ['access_token' => 'x'],
            ])
            ->assertForbidden();
    }

    public function test_sabre_ndc_supported_comes_from_adapters_not_provider_label_alone(): void
    {
        $this->assertTrue(SabreCapabilityTruth::gdsSupported());
        $this->assertTrue(SabreCapabilityTruth::ndcSupported());
    }

    public function test_provider_catalog_returns_full_safe_field_metadata_and_audit_history(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $admin = $this->platformAdmin();

        $list = $this->actingAs($admin)->getJson('/admin/api-settings?format=json')->assertOk();
        $airblue = collect($list->json('providers') ?? $list->json('data.providers'))
            ->firstWhere('key', SupplierProvider::Airblue->value);
        $this->assertIsArray($airblue);
        $channel = collect($airblue['credentialFields'])->firstWhere('key', 'api_channel');
        $this->assertSame('select', $channel['type']);
        $this->assertNotEmpty($channel['options']);
        $this->assertTrue($channel['required']);
        $username = collect($airblue['credentialFields'])->firstWhere('key', 'username');
        $this->assertSame('crane_ndc', $username['channel']);
        $this->assertArrayNotHasKey('secret', $channel);

        $create = $this->actingAs($admin)->postJson('/admin/api-settings?format=json', [
            'provider' => SupplierProvider::Duffel->value,
            'name' => 'QA metadata duffel',
            'environment' => SupplierEnvironment::Sandbox->value,
            'status' => SupplierConnectionStatus::Inactive->value,
            'credentials' => ['access_token' => 'qa-fixture-token'],
        ])->assertOk();
        $id = (string) $create->json('connection.id');
        $listed = $this->actingAs($admin)->getJson('/admin/api-settings?format=json')->assertOk();
        $row = collect($listed->json('connections') ?? $listed->json('data.connections'))->firstWhere('id', $id);
        $this->assertNotEmpty($row['audit']['history'] ?? []);
        $this->assertSame('supplier.connection_created', $row['audit']['history'][0]['action'] ?? $row['audit']['history'][count($row['audit']['history']) - 1]['action']);
        $encoded = $listed->getContent();
        $this->assertStringNotContainsString('qa-fixture-token', $encoded);
        $apiVersion = collect($row['credentialFields'])->firstWhere('key', 'api_version');
        $this->assertSame('advanced', $apiVersion['group'] ?? null);
        $this->assertSame('v2', $apiVersion['default'] ?? null);
    }

    public function test_inactive_supplier_connection_can_persist_without_credentials(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $admin = $this->platformAdmin();

        $create = $this->actingAs($admin)->postJson('/admin/api-settings?format=json', [
            'provider' => SupplierProvider::Sabre->value,
            'name' => 'Wizard shell Sabre A',
            'environment' => SupplierEnvironment::Sandbox->value,
            'status' => SupplierConnectionStatus::Inactive->value,
            'credentials' => [],
            'sabre_gds_enabled' => true,
            'sabre_ndc_enabled' => false,
        ]);

        $create->assertOk()->assertJsonPath('ok', true);
        $create->assertJsonPath('connection.provider', 'sabre');
        $create->assertJsonPath('connection.enabled', false);
        $create->assertJsonPath('connection.sabreGdsEnabled', true);
        $create->assertJsonPath('connection.sabreNdcEnabled', false);
        $this->assertFalse((bool) $create->json('connection.credentialsConfigured'));
    }

    public function test_two_sabre_connections_are_independent_with_channel_toggles(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $admin = $this->platformAdmin();

        $a = $this->actingAs($admin)->postJson('/admin/api-settings?format=json', [
            'provider' => SupplierProvider::Sabre->value,
            'name' => 'Sabre CERT GDS',
            'environment' => SupplierEnvironment::Sandbox->value,
            'status' => SupplierConnectionStatus::Inactive->value,
            'credentials' => [],
            'sabre_gds_enabled' => true,
            'sabre_ndc_enabled' => false,
        ])->assertOk();

        $b = $this->actingAs($admin)->postJson('/admin/api-settings?format=json', [
            'provider' => SupplierProvider::Sabre->value,
            'name' => 'Sabre CERT NDC',
            'environment' => SupplierEnvironment::Sandbox->value,
            'status' => SupplierConnectionStatus::Inactive->value,
            'credentials' => [],
            'sabre_gds_enabled' => false,
            'sabre_ndc_enabled' => true,
        ])->assertOk();

        $idA = (string) $a->json('connection.id');
        $idB = (string) $b->json('connection.id');
        $this->assertNotSame($idA, $idB);

        $this->actingAs($admin)->patchJson('/admin/api-settings/'.$idA.'?format=json', [
            'provider' => SupplierProvider::Sabre->value,
            'name' => 'Sabre CERT GDS',
            'environment' => SupplierEnvironment::Sandbox->value,
            'status' => SupplierConnectionStatus::Inactive->value,
            'sabre_gds_enabled' => true,
            'sabre_ndc_enabled' => true,
        ])->assertOk()->assertJsonPath('connection.sabreNdcEnabled', true);

        $listed = $this->actingAs($admin)->getJson('/admin/api-settings?format=json')->assertOk();
        $rows = collect($listed->json('connections') ?? $listed->json('data.connections'))
            ->where('provider', 'sabre')
            ->values();
        $this->assertGreaterThanOrEqual(2, $rows->count());

        $rowA = $rows->firstWhere('id', $idA);
        $rowB = $rows->firstWhere('id', $idB);
        $this->assertTrue((bool) ($rowA['sabreGdsEnabled'] ?? false));
        $this->assertTrue((bool) ($rowA['sabreNdcEnabled'] ?? false));
        $this->assertFalse((bool) ($rowB['sabreGdsEnabled'] ?? true));
        $this->assertTrue((bool) ($rowB['sabreNdcEnabled'] ?? false));

        $hub = $this->actingAs($admin)->getJson('/admin/integrations?format=json&category=flights')->assertOk();
        $sabre = collect($hub->json('hub.integrations') ?? [])
            ->firstWhere('code', 'sabre');
        $this->assertNotNull($sabre);
        $this->assertGreaterThanOrEqual(2, (int) ($sabre['summary']['connection_count'] ?? 0));
        $this->assertTrue((bool) ($sabre['summary']['supports_multiple_connections'] ?? false));
    }

    protected function platformAdmin(): User
    {
        $this->seed(OtaFoundationSeeder::class);
        $admin = User::query()->where('email', 'admin@ota.demo')->firstOrFail();
        $admin->forceFill(['account_type' => AccountType::PlatformAdmin])->save();

        return $admin->fresh();
    }
}
