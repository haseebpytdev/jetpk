<?php

namespace Tests\Feature\Admin;

use App\Enums\AccountType;
use App\Enums\SupplierConnectionStatus;
use App\Enums\SupplierEnvironment;
use App\Enums\SupplierProvider;
use App\Models\AuditLog;
use App\Models\SupplierConnection;
use App\Services\Suppliers\SupplierConnectionService;
use App\Support\Suppliers\AlHaiderSupplierConnectionNormalizer;
use App\Models\User;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AlHaiderManualTokenConnectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_al_haider_manual_token_connection(): void
    {
        $admin = $this->seededAdmin();

        $this->actingAs($admin)->postJson('/admin/api-settings?format=json', [
            'provider' => SupplierProvider::AlHaider->value,
            'name' => 'Al-Haider Group',
            'environment' => SupplierEnvironment::Sandbox->value,
            'status' => SupplierConnectionStatus::Active->value,
            'base_url' => 'https://alhaider.test',
            'credentials' => [
                'auth_mode' => AlHaiderSupplierConnectionNormalizer::AUTH_MODE_MANUAL,
                'existing_token' => 'owner-supplied-bearer-token',
                'token_expires_at' => '2099-12-31',
            ],
            'settings_json' => '{}',
        ])->assertOk()->assertJsonPath('ok', true);

        $connection = SupplierConnection::query()
            ->where('agency_id', $admin->current_agency_id)
            ->where('provider', SupplierProvider::AlHaider)
            ->firstOrFail();

        $this->assertSame('owner-supplied-bearer-token', $connection->credentials['existing_token']);
    }

    public function test_blank_existing_token_update_keeps_current_token(): void
    {
        $admin = $this->seededAdmin();
        $connection = SupplierConnection::factory()->create([
            'agency_id' => $admin->current_agency_id,
            'provider' => SupplierProvider::AlHaider,
            'name' => 'Al-Haider Keep Token',
            'credentials' => [
                'auth_mode' => AlHaiderSupplierConnectionNormalizer::AUTH_MODE_MANUAL,
                'existing_token' => 'stored-secret-token-value',
            ],
        ]);

        $this->actingAs($admin)->patchJson('/admin/api-settings/'.$connection->id.'?format=json', [
            'provider' => SupplierProvider::AlHaider->value,
            'name' => 'Al-Haider Keep Token',
            'environment' => SupplierEnvironment::Sandbox->value,
            'status' => SupplierConnectionStatus::Active->value,
            'credentials' => [
                'auth_mode' => AlHaiderSupplierConnectionNormalizer::AUTH_MODE_MANUAL,
            ],
            'settings_json' => '{}',
        ])->assertOk();

        $connection->refresh();
        $this->assertSame('stored-secret-token-value', $connection->credentials['existing_token']);
    }

    public function test_replace_existing_token_updates_stored_secret(): void
    {
        $admin = $this->seededAdmin();
        $connection = SupplierConnection::factory()->create([
            'agency_id' => $admin->current_agency_id,
            'provider' => SupplierProvider::AlHaider,
            'name' => 'Al-Haider Replace Token',
            'credentials' => [
                'auth_mode' => AlHaiderSupplierConnectionNormalizer::AUTH_MODE_MANUAL,
                'existing_token' => 'old-token-value',
            ],
        ]);

        $this->actingAs($admin)->patchJson('/admin/api-settings/'.$connection->id.'?format=json', [
            'provider' => SupplierProvider::AlHaider->value,
            'name' => 'Al-Haider Replace Token',
            'environment' => SupplierEnvironment::Sandbox->value,
            'status' => SupplierConnectionStatus::Active->value,
            'credentials' => [
                'auth_mode' => AlHaiderSupplierConnectionNormalizer::AUTH_MODE_MANUAL,
                'existing_token' => 'replacement-token-value',
            ],
            'settings_json' => '{}',
        ])->assertOk();

        $connection->refresh();
        $this->assertSame('replacement-token-value', $connection->credentials['existing_token']);
    }

    public function test_existing_token_is_encrypted_and_masked_in_api_payload(): void
    {
        $admin = $this->seededAdmin();
        $connection = SupplierConnection::factory()->create([
            'agency_id' => $admin->current_agency_id,
            'provider' => SupplierProvider::AlHaider,
            'name' => 'Al-Haider Masked',
            'credentials' => [
                'auth_mode' => AlHaiderSupplierConnectionNormalizer::AUTH_MODE_MANUAL,
                'existing_token' => 'super-secret-token-1234',
            ],
        ]);

        $raw = (string) DB::table('supplier_connections')->whereKey($connection->id)->value('credentials');
        $this->assertStringNotContainsString('super-secret-token-1234', $raw);

        $response = $this->actingAs($admin)->getJson('/admin/api-settings?format=json')->assertOk();
        $rows = collect($response->json('connections'));
        $presented = $rows->firstWhere('id', (string) $connection->id);
        $this->assertNotNull($presented);
        $this->assertSame('Configured (masked)', $presented['maskedCredentials']['existing_token'] ?? null);
        $this->assertStringNotContainsString('super-secret-token-1234', json_encode($presented, JSON_THROW_ON_ERROR));
    }

    public function test_audit_payload_redacts_existing_token(): void
    {
        $admin = $this->seededAdmin();
        $connection = SupplierConnection::factory()->create([
            'agency_id' => $admin->current_agency_id,
            'provider' => SupplierProvider::AlHaider,
            'name' => 'Al-Haider Audit',
            'credentials' => [
                'auth_mode' => AlHaiderSupplierConnectionNormalizer::AUTH_MODE_MANUAL,
                'existing_token' => 'audit-secret-token',
            ],
        ]);

        app(SupplierConnectionService::class)->updateConnection($connection, [
            'provider' => SupplierProvider::AlHaider->value,
            'name' => 'Al-Haider Audit Updated',
            'environment' => SupplierEnvironment::Sandbox->value,
            'status' => SupplierConnectionStatus::Active->value,
            'credentials' => [
                'auth_mode' => AlHaiderSupplierConnectionNormalizer::AUTH_MODE_MANUAL,
                'existing_token' => 'audit-secret-token',
            ],
        ]);

        $audit = AuditLog::query()
            ->where('auditable_type', SupplierConnection::class)
            ->where('auditable_id', $connection->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($audit);
        $encoded = json_encode($audit->properties, JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('audit-secret-token', $encoded);
        $this->assertStringContainsString('Configured (masked)', $encoded);
    }

    public function test_provider_catalog_includes_al_haider_installed_with_auth_mode_fields(): void
    {
        $admin = $this->seededAdmin();

        $response = $this->actingAs($admin)->getJson('/admin/api-settings?format=json')->assertOk();
        $providers = collect($response->json('providers'));
        $alHaider = $providers->firstWhere('key', SupplierProvider::AlHaider->value);

        $this->assertNotNull($alHaider);
        $this->assertTrue($alHaider['installed']);
        $fieldKeys = collect($alHaider['credentialFields'])->pluck('key')->all();
        $this->assertContains('auth_mode', $fieldKeys);
        $this->assertContains('existing_token', $fieldKeys);
    }

    private function seededAdmin(): User
    {
        $this->seed(OtaFoundationSeeder::class);
        $admin = User::query()->where('email', 'admin@ota.demo')->firstOrFail();
        if ($admin->account_type !== AccountType::PlatformAdmin) {
            $admin->forceFill(['account_type' => AccountType::PlatformAdmin])->save();
            $admin = $admin->fresh();
        }

        return $admin;
    }
}
