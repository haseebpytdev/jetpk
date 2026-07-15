<?php

namespace Tests\Feature;

use App\Enums\SupplierConnectionStatus;
use App\Enums\SupplierEnvironment;
use App\Enums\SupplierProvider;
use App\Models\Agency;
use App\Models\SupplierConnection;
use App\Models\User;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SupplierConnectionCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_agency_admin_can_view_api_settings(): void
    {
        $admin = $this->seededAdmin();
        $this->actingAs($admin)->get('/admin/api-settings')->assertOk();
    }

    public function test_agency_admin_can_create_supplier_connection(): void
    {
        $admin = $this->seededAdmin();

        $this->actingAs($admin)->post('/admin/api-settings', [
            'provider' => SupplierProvider::Amadeus->value,
            'name' => 'Amadeus Sandbox',
            'environment' => SupplierEnvironment::Sandbox->value,
            'status' => SupplierConnectionStatus::Inactive->value,
            'base_url' => 'https://example.test/amadeus',
            'credentials' => ['client_id' => 'amadeus_ci', 'client_secret' => 'amadeus_cs'],
            'settings_json' => '{"mode":"sandbox"}',
        ])->assertRedirect('/admin/api-settings');

        $this->assertDatabaseHas('supplier_connections', [
            'name' => 'Amadeus Sandbox',
            'provider' => SupplierProvider::Amadeus->value,
        ]);
    }

    public function test_agency_admin_can_create_second_connection_same_provider_with_distinct_name(): void
    {
        $admin = $this->seededAdmin();
        $existing = SupplierConnection::query()
            ->where('agency_id', $admin->current_agency_id)
            ->where('provider', SupplierProvider::Sabre)
            ->firstOrFail();

        $this->actingAs($admin)->post('/admin/api-settings', [
            'provider' => SupplierProvider::Sabre->value,
            'name' => 'Sabre secondary sandbox',
            'environment' => SupplierEnvironment::Sandbox->value,
            'status' => SupplierConnectionStatus::Inactive->value,
            'credentials' => [
                'client_id' => 'sabre-secondary-id',
                'client_secret' => 'sabre-secondary-secret',
            ],
            'settings_json' => '{}',
        ])->assertRedirect('/admin/api-settings');

        $this->assertSame(2, SupplierConnection::query()
            ->where('agency_id', $admin->current_agency_id)
            ->where('provider', SupplierProvider::Sabre)
            ->count());
        $this->assertDatabaseHas('supplier_connections', [
            'agency_id' => $admin->current_agency_id,
            'name' => 'Sabre secondary sandbox',
            'provider' => SupplierProvider::Sabre->value,
        ]);
        $this->assertDatabaseHas('supplier_connections', [
            'id' => $existing->id,
            'name' => $existing->name,
            'provider' => SupplierProvider::Sabre->value,
        ]);
    }

    public function test_store_rejects_duplicate_connection_name_same_provider_same_agency(): void
    {
        $admin = $this->seededAdmin();
        $existing = SupplierConnection::query()
            ->where('agency_id', $admin->current_agency_id)
            ->where('provider', SupplierProvider::Sabre)
            ->firstOrFail();

        $this->actingAs($admin)->post('/admin/api-settings', [
            'provider' => SupplierProvider::Sabre->value,
            'name' => $existing->name,
            'environment' => SupplierEnvironment::Sandbox->value,
            'status' => SupplierConnectionStatus::Inactive->value,
            'credentials' => [
                'client_id' => 'other-id',
                'client_secret' => 'other-secret',
            ],
            'settings_json' => '{}',
        ])->assertSessionHasErrors('name');
    }

    public function test_agency_admin_can_edit_own_supplier_connection(): void
    {
        $admin = $this->seededAdmin();
        $connection = SupplierConnection::query()->where('agency_id', $admin->current_agency_id)->firstOrFail();

        $this->actingAs($admin)->patch('/admin/api-settings/'.$connection->id, [
            'provider' => $connection->provider->value,
            'name' => 'Updated Provider Name',
            'environment' => SupplierEnvironment::Sandbox->value,
            'status' => SupplierConnectionStatus::Inactive->value,
            'base_url' => 'https://sandbox.example.test',
            'settings_json' => '{"region":"pk"}',
            'credentials' => [],
        ])->assertRedirect('/admin/api-settings');

        $connection->refresh();
        $this->assertSame('Updated Provider Name', $connection->name);
    }

    public function test_agency_admin_can_delete_own_supplier_connection(): void
    {
        $admin = $this->seededAdmin();
        $connection = SupplierConnection::factory()->create([
            'agency_id' => $admin->current_agency_id,
            'provider' => SupplierProvider::Travelport,
            'name' => 'Delete Me',
            'credentials' => ['client_id' => 'tp_ci', 'client_secret' => 'tp_cs'],
        ]);

        $this->actingAs($admin)
            ->delete('/admin/api-settings/'.$connection->id)
            ->assertRedirect('/admin/api-settings');

        $this->assertDatabaseMissing('supplier_connections', [
            'id' => $connection->id,
        ]);
    }

    public function test_agency_admin_cannot_edit_another_agency_supplier_connection(): void
    {
        $admin = $this->seededAdmin();
        $otherAgency = Agency::factory()->create();
        $foreign = SupplierConnection::factory()->create(['agency_id' => $otherAgency->id]);

        $this->actingAs($admin)->patch('/admin/api-settings/'.$foreign->id, [
            'provider' => SupplierProvider::PiaNdc->value,
            'name' => 'Forbidden',
            'environment' => SupplierEnvironment::Sandbox->value,
            'status' => SupplierConnectionStatus::Inactive->value,
            'credentials' => [],
            'settings_json' => '{}',
        ])->assertForbidden();
    }

    public function test_agency_admin_cannot_delete_another_agency_supplier_connection(): void
    {
        $admin = $this->seededAdmin();
        $otherAgency = Agency::factory()->create();
        $foreign = SupplierConnection::factory()->create(['agency_id' => $otherAgency->id]);

        $this->actingAs($admin)
            ->delete('/admin/api-settings/'.$foreign->id)
            ->assertForbidden();
    }

    public function test_staff_cannot_access_admin_api_settings(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $staff = User::query()->where('email', 'staff@ota.demo')->firstOrFail();
        $this->actingAs($staff)->get('/admin/api-settings')->assertForbidden();
    }

    public function test_credentials_are_encrypted_and_not_exposed_in_edit_page(): void
    {
        $admin = $this->seededAdmin();
        $connection = SupplierConnection::query()
            ->where('agency_id', $admin->current_agency_id)
            ->where('provider', SupplierProvider::Duffel)
            ->firstOrFail();
        $connection->forceFill([
            'name' => 'Duffel Secure',
            'environment' => SupplierEnvironment::Sandbox,
            'status' => SupplierConnectionStatus::Inactive,
            'credentials' => ['access_token' => 'secret-plaintext'],
        ])->save();

        $raw = (string) DB::table('supplier_connections')->whereKey($connection->id)->value('credentials');
        $this->assertStringNotContainsString('secret-plaintext', $raw);

        $this->actingAs($admin)
            ->get('/admin/api-settings/'.$connection->id.'/edit')
            ->assertOk()
            ->assertDontSee('secret-plaintext', false)
            ->assertSee('••••', false);
    }

    public function test_empty_credential_update_preserves_old_credentials(): void
    {
        $admin = $this->seededAdmin();
        $connection = SupplierConnection::factory()->create([
            'agency_id' => $admin->current_agency_id,
            'provider' => SupplierProvider::Amadeus,
            'name' => 'Amadeus Preserve',
            'credentials' => ['client_id' => 'old-id', 'client_secret' => 'old-secret'],
        ]);

        $this->actingAs($admin)->patch('/admin/api-settings/'.$connection->id, [
            'provider' => $connection->provider->value,
            'name' => $connection->name,
            'environment' => SupplierEnvironment::Sandbox->value,
            'status' => SupplierConnectionStatus::Inactive->value,
            'base_url' => '',
            'credentials' => [
                'client_id' => '',
                'client_secret' => '',
            ],
            'settings_json' => '{}',
        ])->assertRedirect('/admin/api-settings');

        $connection->refresh();
        $this->assertSame('old-id', $connection->credentials['client_id']);
        $this->assertSame('old-secret', $connection->credentials['client_secret']);
    }

    public function test_duffel_form_shows_access_token_and_api_version(): void
    {
        $admin = $this->seededAdmin();
        $duffel = SupplierConnection::query()
            ->where('agency_id', $admin->current_agency_id)
            ->where('provider', SupplierProvider::Duffel)
            ->firstOrFail();
        $duffel->forceFill(['name' => 'Duffel Form'])->save();

        $this->actingAs($admin)
            ->get('/admin/api-settings/'.$duffel->id.'/edit')
            ->assertOk()
            ->assertSee('Access Token', false)
            ->assertSee('API Version', false);
    }

    public function test_duffel_form_hides_generic_non_duffel_credentials(): void
    {
        $admin = $this->seededAdmin();
        $duffel = SupplierConnection::query()
            ->where('agency_id', $admin->current_agency_id)
            ->where('provider', SupplierProvider::Duffel)
            ->firstOrFail();
        $duffel->forceFill(['name' => 'Duffel Form Hidden Fields'])->save();

        $this->actingAs($admin)
            ->get('/admin/api-settings/'.$duffel->id.'/edit')
            ->assertOk()
            ->assertDontSee('Client ID</label>', false)
            ->assertDontSee('Client Secret</label>', false)
            ->assertDontSee('Username</label>', false)
            ->assertDontSee('Password</label>', false);
    }

    public function test_duffel_store_requires_access_token(): void
    {
        $admin = $this->seededAdmin();

        $this->actingAs($admin)->post('/admin/api-settings', [
            'provider' => SupplierProvider::Duffel->value,
            'name' => 'Duffel Missing Token',
            'environment' => SupplierEnvironment::Sandbox->value,
            'status' => SupplierConnectionStatus::Inactive->value,
            'credentials' => ['api_version' => 'v2'],
            'settings_json' => '{}',
        ])->assertSessionHasErrors('credentials.access_token');
    }

    public function test_duffel_store_does_not_require_client_id_and_client_secret(): void
    {
        $admin = $this->seededAdmin();

        SupplierConnection::query()
            ->where('agency_id', $admin->current_agency_id)
            ->where('provider', SupplierProvider::Duffel)
            ->delete();

        $this->actingAs($admin)->post('/admin/api-settings', [
            'provider' => SupplierProvider::Duffel->value,
            'name' => 'Duffel Token Only',
            'environment' => SupplierEnvironment::Sandbox->value,
            'status' => SupplierConnectionStatus::Inactive->value,
            'credentials' => ['access_token' => 'duffel_test_token_only'],
            'settings_json' => '{}',
        ])->assertRedirect('/admin/api-settings');

        $this->assertDatabaseHas('supplier_connections', [
            'name' => 'Duffel Token Only',
            'provider' => SupplierProvider::Duffel->value,
        ]);
    }

    public function test_duffel_update_preserves_existing_token_when_blank(): void
    {
        $admin = $this->seededAdmin();
        $connection = SupplierConnection::query()
            ->where('agency_id', $admin->current_agency_id)
            ->where('provider', SupplierProvider::Duffel)
            ->firstOrFail();
        $connection->forceFill([
            'name' => 'Duffel Preserve Token',
            'credentials' => ['access_token' => 'duffel_test_old_token', 'api_version' => 'v1'],
        ])->save();

        $this->actingAs($admin)->patch('/admin/api-settings/'.$connection->id, [
            'provider' => SupplierProvider::Duffel->value,
            'name' => $connection->name,
            'environment' => SupplierEnvironment::Sandbox->value,
            'status' => SupplierConnectionStatus::Inactive->value,
            'base_url' => '',
            'credentials' => [
                'access_token' => '',
                'api_version' => 'v2',
            ],
            'settings_json' => '{}',
        ])->assertRedirect('/admin/api-settings');

        $connection->refresh();
        $this->assertSame('duffel_test_old_token', $connection->credentials['access_token']);
        $this->assertSame('v2', $connection->credentials['api_version']);
    }

    public function test_duffel_update_replaces_token_when_new_token_submitted(): void
    {
        $admin = $this->seededAdmin();
        $connection = SupplierConnection::query()
            ->where('agency_id', $admin->current_agency_id)
            ->where('provider', SupplierProvider::Duffel)
            ->firstOrFail();
        $connection->forceFill([
            'name' => 'Duffel Replace Token',
            'credentials' => ['access_token' => 'duffel_test_old_token'],
        ])->save();

        $this->actingAs($admin)->patch('/admin/api-settings/'.$connection->id, [
            'provider' => SupplierProvider::Duffel->value,
            'name' => $connection->name,
            'environment' => SupplierEnvironment::Sandbox->value,
            'status' => SupplierConnectionStatus::Inactive->value,
            'base_url' => '',
            'credentials' => [
                'access_token' => 'duffel_test_new_token',
                'api_version' => 'v2',
            ],
            'settings_json' => '{}',
        ])->assertRedirect('/admin/api-settings');

        $connection->refresh();
        $this->assertSame('duffel_test_new_token', $connection->credentials['access_token']);
    }

    public function test_duffel_readiness_passes_with_access_token_only(): void
    {
        $admin = $this->seededAdmin();
        $duffel = SupplierConnection::query()
            ->where('agency_id', $admin->current_agency_id)
            ->where('provider', SupplierProvider::Duffel)
            ->firstOrFail();
        $duffel->forceFill([
            'environment' => SupplierEnvironment::Sandbox,
            'status' => SupplierConnectionStatus::Active,
            'is_active' => true,
            'credentials' => ['access_token' => 'duffel_test_readiness_token'],
        ])->save();

        $this->actingAs($admin)->patch('/admin/api-settings/'.$duffel->id.'/test')->assertRedirect();

        $duffel->refresh();
        $this->assertSame('ready_for_review', $duffel->last_test_status);
        $this->assertNull($duffel->last_error);
    }

    public function test_saved_duffel_token_is_never_rendered_in_edit_page(): void
    {
        $admin = $this->seededAdmin();
        $connection = SupplierConnection::query()
            ->where('agency_id', $admin->current_agency_id)
            ->where('provider', SupplierProvider::Duffel)
            ->firstOrFail();
        $connection->forceFill([
            'name' => 'Duffel Edit Masking',
            'credentials' => ['access_token' => 'duffel_test_super_secret_token'],
        ])->save();

        $this->actingAs($admin)
            ->get('/admin/api-settings/'.$connection->id.'/edit')
            ->assertOk()
            ->assertDontSee('duffel_test_super_secret_token', false)
            ->assertSee('Stored token:', false)
            ->assertSee('duffel_test_', false);
    }

    public function test_sabre_non_duffel_provider_fields_still_work(): void
    {
        $admin = $this->seededAdmin();
        $sabre = SupplierConnection::query()
            ->where('agency_id', $admin->current_agency_id)
            ->where('provider', SupplierProvider::Sabre)
            ->firstOrFail();

        $this->actingAs($admin)->patch('/admin/api-settings/'.$sabre->id, [
            'provider' => SupplierProvider::Sabre->value,
            'name' => 'Sabre Creds',
            'environment' => SupplierEnvironment::Sandbox->value,
            'status' => SupplierConnectionStatus::Inactive->value,
            'credentials' => [
                'sign_in' => 'sabre-client',
                'password' => 'sabre-secret',
            ],
            'settings_json' => '{}',
        ])->assertRedirect('/admin/api-settings');

        $sabre->refresh();
        $this->assertSame('sabre-client', $sabre->credentials['sign_in']);
        $this->assertSame('sabre-client', $sabre->credentials['client_id']);
        $this->assertSame('sabre-secret', $sabre->credentials['password']);
        $this->assertSame('sabre-secret', $sabre->credentials['client_secret']);
        $this->assertSame('https://api.cert.platform.sabre.com', $sabre->base_url);
    }

    public function test_sabre_update_preserves_credentials_when_blank(): void
    {
        $admin = $this->seededAdmin();
        $sabre = SupplierConnection::query()
            ->where('agency_id', $admin->current_agency_id)
            ->where('provider', SupplierProvider::Sabre)
            ->firstOrFail();
        $sabre->forceFill([
            'credentials' => [
                'sign_in' => 'keep-sign-in',
                'password' => 'keep-secret',
                'client_id' => 'keep-sign-in',
                'client_secret' => 'keep-secret',
            ],
        ])->save();

        $this->actingAs($admin)->patch('/admin/api-settings/'.$sabre->id, [
            'provider' => SupplierProvider::Sabre->value,
            'name' => $sabre->name,
            'environment' => SupplierEnvironment::Live->value,
            'status' => SupplierConnectionStatus::Inactive->value,
            'credentials' => [
                'sign_in' => '',
                'password' => '',
            ],
            'settings_json' => '{}',
        ])->assertRedirect('/admin/api-settings');

        $sabre->refresh();
        $this->assertSame('keep-sign-in', $sabre->credentials['sign_in']);
        $this->assertSame('keep-secret', $sabre->credentials['password']);
        $this->assertSame('https://api.platform.sabre.com', $sabre->base_url);
    }

    public function test_sabre_edit_form_shows_canonical_fields_and_masked_summary(): void
    {
        $admin = $this->seededAdmin();
        $sabre = SupplierConnection::query()
            ->where('agency_id', $admin->current_agency_id)
            ->where('provider', SupplierProvider::Sabre)
            ->firstOrFail();
        $sabre->forceFill([
            'credentials' => [
                'sign_in' => 'epr4016',
                'password' => 'secretes99',
                'client_id' => 'epr4016',
                'client_secret' => 'secretes99',
                'pcc' => '4HLD',
            ],
        ])->save();

        $this->actingAs($admin)
            ->get('/admin/api-settings/'.$sabre->id.'/edit')
            ->assertOk()
            ->assertSee('Sabre Sign in / Client ID (EPR)', false)
            ->assertSee('Sabre Secret / Password', false)
            ->assertSee('PCC (pseudo city code)', false)
            ->assertSee('Sign in / Client ID', false)
            ->assertDontSee('epr4016', false)
            ->assertDontSee('secretes99', false)
            ->assertDontSee('Client Secret</label>', false);
    }

    public function test_api_settings_index_shows_enable_toggle(): void
    {
        $admin = $this->seededAdmin();

        $this->actingAs($admin)
            ->get('/admin/api-settings')
            ->assertOk()
            ->assertSee('Enabled', false)
            ->assertSee('toggle-status', false);
    }

    public function test_sabre_readiness_with_credentials_sets_ready_for_review_without_external_calls(): void
    {
        $admin = $this->seededAdmin();
        $sabre = SupplierConnection::query()
            ->where('agency_id', $admin->current_agency_id)
            ->where('provider', SupplierProvider::Sabre)
            ->firstOrFail();
        $sabre->forceFill([
            'credentials' => ['client_id' => 'id-123', 'client_secret' => 'secret-123'],
        ])->save();

        $this->actingAs($admin)->patch('/admin/api-settings/'.$sabre->id.'/test')->assertRedirect();

        $sabre->refresh();
        $this->assertSame('ready_for_review', $sabre->last_test_status);
        $this->assertSame(SupplierConnectionStatus::Inactive, $sabre->status);
        $this->assertNull($sabre->last_error);
    }

    public function test_inactive_active_toggle_works(): void
    {
        $admin = $this->seededAdmin();
        $connection = SupplierConnection::factory()->create([
            'agency_id' => $admin->current_agency_id,
            'provider' => SupplierProvider::Travelport,
            'name' => 'Toggle Supplier',
            'status' => SupplierConnectionStatus::Inactive,
            'is_active' => false,
        ]);

        $this->actingAs($admin)->patch('/admin/api-settings/'.$connection->id.'/toggle-status')->assertRedirect();
        $connection->refresh();
        $this->assertSame(SupplierConnectionStatus::Active, $connection->status);

        $this->actingAs($admin)->patch('/admin/api-settings/'.$connection->id.'/toggle-status')->assertRedirect();
        $connection->refresh();
        $this->assertSame(SupplierConnectionStatus::Inactive, $connection->status);
    }

    public function test_supplier_rows_are_agency_scoped(): void
    {
        $admin = $this->seededAdmin();
        $otherAgency = Agency::factory()->create();
        SupplierConnection::factory()->create([
            'agency_id' => $otherAgency->id,
            'provider' => SupplierProvider::Travelport,
            'name' => 'Foreign Supplier',
        ]);

        $this->actingAs($admin)
            ->get('/admin/api-settings')
            ->assertOk()
            ->assertDontSee('Foreign Supplier', false);
    }

    protected function seededAdmin(): User
    {
        $this->seed(OtaFoundationSeeder::class);

        return User::query()->where('email', 'admin@ota.demo')->firstOrFail();
    }
}
