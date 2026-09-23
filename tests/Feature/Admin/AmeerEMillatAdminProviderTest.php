<?php

namespace Tests\Feature\Admin;

use App\Enums\SupplierConnectionStatus;
use App\Enums\SupplierEnvironment;
use App\Enums\SupplierProvider;
use App\Models\SupplierConnection;
use App\Models\User;
use App\Support\Suppliers\AmeerEMillatSupplierConnectionNormalizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\Support\PlatformAdminTestHelpers;
use Tests\TestCase;

class AmeerEMillatAdminProviderTest extends TestCase
{
    use PlatformAdminTestHelpers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('suppliers.ameer_e_millat.enabled', true);
        Config::set('suppliers.ameer_e_millat.default_base_url', 'https://ameer.test');
        Config::set('suppliers.ameer_e_millat.profile_path', '/api/user');
    }

    public function test_enum_and_provider_picker_include_ameer_e_millat(): void
    {
        $admin = $this->seededAdmin();

        $this->assertSame('ameer_e_millat', SupplierProvider::AmeerEMillat->value);

        $this->actingAs($admin)
            ->get('/admin/api-settings/create?provider=ameer_e_millat')
            ->assertOk()
            ->assertSee('AMEER E MILLAT', false)
            ->assertSee('Bearer token', false);
    }

    public function test_store_manual_token_connection_normalizes_payload(): void
    {
        $admin = $this->seededAdmin();

        $this->actingAs($admin)->post('/admin/api-settings', [
            'provider' => SupplierProvider::AmeerEMillat->value,
            'name' => 'Ameer Sandbox',
            'environment' => SupplierEnvironment::Sandbox->value,
            'status' => SupplierConnectionStatus::Inactive->value,
            'credentials' => [
                'auth_mode' => AmeerEMillatSupplierConnectionNormalizer::AUTH_MODE_MANUAL,
                'existing_token' => 'manual-token-value-1234567890',
                'token_expires_at' => '2030-01-01T00:00:00+00:00',
            ],
            'settings_json' => '{}',
        ])->assertRedirect('/admin/api-settings');

        $connection = SupplierConnection::query()
            ->where('agency_id', $admin->current_agency_id)
            ->where('provider', SupplierProvider::AmeerEMillat)
            ->firstOrFail();

        $this->assertSame(AmeerEMillatSupplierConnectionNormalizer::AUTH_MODE_MANUAL, $connection->credentials['auth_mode']);
        $this->assertSame('manual-token-value-1234567890', $connection->credentials['existing_token']);
        $this->assertSame('https://ameer.test', $connection->base_url);
    }

    public function test_store_auto_mode_requires_email_and_password(): void
    {
        $admin = $this->seededAdmin();

        $this->actingAs($admin)->post('/admin/api-settings', [
            'provider' => SupplierProvider::AmeerEMillat->value,
            'name' => 'Ameer Auto Missing',
            'environment' => SupplierEnvironment::Sandbox->value,
            'status' => SupplierConnectionStatus::Inactive->value,
            'credentials' => [
                'auth_mode' => AmeerEMillatSupplierConnectionNormalizer::AUTH_MODE_AUTO,
            ],
            'settings_json' => '{}',
        ])->assertSessionHasErrors(['credentials.email', 'credentials.password']);
    }

    public function test_edit_masks_saved_token_and_test_connection_probes_profile(): void
    {
        $admin = $this->seededAdmin();

        $connection = SupplierConnection::query()->create([
            'agency_id' => $admin->current_agency_id,
            'provider' => SupplierProvider::AmeerEMillat,
            'name' => 'Ameer Live',
            'environment' => SupplierEnvironment::Sandbox,
            'status' => SupplierConnectionStatus::Inactive,
            'is_active' => false,
            'base_url' => 'https://ameer.test',
            'credentials' => [
                'auth_mode' => AmeerEMillatSupplierConnectionNormalizer::AUTH_MODE_MANUAL,
                'existing_token' => 'super-secret-token-value',
            ],
        ]);

        $this->actingAs($admin)
            ->get('/admin/api-settings/'.$connection->id.'/edit')
            ->assertOk()
            ->assertSee('Authentication mode', false)
            ->assertDontSee('super-secret-token-value', false);

        Http::fake([
            'ameer.test/api/user' => Http::response(['id' => 1, 'email' => 'ops@example.test'], 200),
        ]);

        $this->actingAs($admin)
            ->patch('/admin/api-settings/'.$connection->id.'/test')
            ->assertRedirect();

        $connection->refresh();
        $this->assertSame('connection_ok', $connection->last_test_status);
        $this->assertNull($connection->last_error);
        $this->assertNotNull($connection->last_tested_at);
    }

    protected function seededAdmin(): User
    {
        return $this->platformAdmin();
    }
}
