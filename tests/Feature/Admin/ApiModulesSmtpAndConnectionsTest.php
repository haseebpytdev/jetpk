<?php

namespace Tests\Feature\Admin;

use App\Enums\AccountType;
use App\Enums\SupplierConnectionStatus;
use App\Enums\SupplierEnvironment;
use App\Enums\SupplierProvider;
use App\Models\SupplierConnection;
use App\Models\User;
use App\Services\Integrations\SmtpEnvironmentImportService;
use App\Services\Integrations\SmtpMailConfigResolver;
use App\Support\Suppliers\ProviderEndpointDefaults;
use App\Support\Suppliers\SabreSupplierChannelConfig;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class ApiModulesSmtpAndConnectionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_api_settings_index_returns_configured_connections_with_metrics(): void
    {
        $admin = $this->seededAdmin();

        SupplierConnection::query()->create([
            'agency_id' => $admin->current_agency_id,
            'provider' => SupplierProvider::Sabre,
            'name' => 'Sabre LIVE',
            'environment' => SupplierEnvironment::Live,
            'status' => SupplierConnectionStatus::Active,
            'is_active' => true,
            'credentials' => ['sign_in' => 'a', 'password' => 'b', 'pcc' => 'XYZ'],
            'settings' => SabreSupplierChannelConfig::mergeIntoSettings([], true, false),
        ]);

        SupplierConnection::query()->create([
            'agency_id' => $admin->current_agency_id,
            'provider' => SupplierProvider::Sabre,
            'name' => 'Sabre CERT',
            'environment' => SupplierEnvironment::Sandbox,
            'status' => SupplierConnectionStatus::Inactive,
            'is_active' => false,
            'credentials' => ['sign_in' => 'c', 'password' => 'd', 'pcc' => 'ABC'],
            'settings' => SabreSupplierChannelConfig::mergeIntoSettings([], true, false),
        ]);

        $response = $this->actingAs($admin)->getJson('/admin/api-settings?format=json')->assertOk();
        $connections = $response->json('connections');
        $this->assertCount(2, $connections);
        $names = collect($connections)->pluck('name')->all();
        $this->assertContains('Sabre LIVE', $names);
        $this->assertContains('Sabre CERT', $names);

        $metrics = $response->json('metrics');
        $this->assertSame(2, $metrics['configured']);
        $this->assertSame(1, $metrics['active']);
    }

    public function test_duplicate_sabre_connections_toggle_independently_without_deleting_credentials(): void
    {
        $admin = $this->seededAdmin();

        $a = SupplierConnection::query()->create([
            'agency_id' => $admin->current_agency_id,
            'provider' => SupplierProvider::Sabre,
            'name' => 'Sabre A',
            'environment' => SupplierEnvironment::Live,
            'status' => SupplierConnectionStatus::Active,
            'is_active' => true,
            'credentials' => ['sign_in' => 'a', 'password' => 'b', 'pcc' => 'AAA'],
        ]);
        $b = SupplierConnection::query()->create([
            'agency_id' => $admin->current_agency_id,
            'provider' => SupplierProvider::Sabre,
            'name' => 'Sabre B',
            'environment' => SupplierEnvironment::Sandbox,
            'status' => SupplierConnectionStatus::Active,
            'is_active' => true,
            'credentials' => ['sign_in' => 'c', 'password' => 'd', 'pcc' => 'BBB'],
        ]);

        $this->actingAs($admin)
            ->patchJson('/admin/api-settings/'.$a->id.'/toggle-status?format=json')
            ->assertOk();

        $a->refresh();
        $b->refresh();
        $this->assertFalse($a->is_active);
        $this->assertTrue($b->is_active);
        $this->assertSame('a', $a->credentials['sign_in']);
    }

    public function test_sabre_gds_ndc_persist_independently_and_endpoint_defaults_by_environment(): void
    {
        $admin = $this->seededAdmin();

        $connection = SupplierConnection::query()->create([
            'agency_id' => $admin->current_agency_id,
            'provider' => SupplierProvider::Sabre,
            'name' => 'Sabre Channels',
            'environment' => SupplierEnvironment::Sandbox,
            'status' => SupplierConnectionStatus::Active,
            'is_active' => true,
            'credentials' => ['sign_in' => 'a', 'password' => 'b', 'pcc' => 'CCC'],
            'settings' => SabreSupplierChannelConfig::mergeIntoSettings([], true, false),
            'base_url' => ProviderEndpointDefaults::for('sabre', 'sandbox')['base_url'],
        ]);

        $this->actingAs($admin)
            ->patchJson('/admin/api-settings/'.$connection->id.'?format=json', [
                'provider' => 'sabre',
                'name' => 'Sabre Channels',
                'environment' => 'live',
                'status' => 'active',
                'sabre_gds_enabled' => false,
                'sabre_ndc_enabled' => true,
                'base_url_mode' => 'provider_default',
                'settings_json' => '{}',
            ])
            ->assertOk();

        $connection->refresh();
        $this->assertFalse(SabreSupplierChannelConfig::gdsEnabled($connection));
        $this->assertTrue(SabreSupplierChannelConfig::ndcEnabled($connection));
        $this->assertSame(ProviderEndpointDefaults::for('sabre', 'live')['base_url'], $connection->base_url);
    }

    public function test_alhaider_live_endpoint_default_and_manual_token_strips_user_pass(): void
    {
        $defaults = ProviderEndpointDefaults::for('al_haider', 'live');
        $this->assertSame('https://alhaidertravel.pk', $defaults['base_url']);

        $admin = $this->seededAdmin();
        $this->actingAs($admin)->postJson('/admin/api-settings?format=json', [
            'provider' => 'al_haider',
            'name' => 'Al-Haider LIVE',
            'environment' => 'live',
            'status' => 'inactive',
            'credentials' => [
                'auth_mode' => 'manual_token',
                'existing_token' => 'owner-supplied-token-value',
                'token_expires_at' => '2027-01-01',
                'username' => 'should-be-stripped',
                'password' => 'should-be-stripped',
            ],
            'settings_json' => '{}',
        ])->assertOk();

        $connection = SupplierConnection::query()->where('provider', SupplierProvider::AlHaider)->first();
        $this->assertNotNull($connection);
        $this->assertSame('manual_token', $connection->credentials['auth_mode']);
        $this->assertArrayNotHasKey('username', $connection->credentials);
        $this->assertArrayNotHasKey('password', $connection->credentials);
        $this->assertSame('https://alhaidertravel.pk', $connection->base_url);
        $this->assertFalse((bool) config('suppliers.al_haider.token_generation_enabled'));
    }

    public function test_smtp_import_is_idempotent_and_env_fallback_when_disabled(): void
    {
        $admin = $this->seededAdmin();
        $agency = $admin->currentAgency;

        Config::set('mail.default', 'smtp');
        Config::set('mail.mailers.smtp.host', 'smtp.mailprovider.test');
        Config::set('mail.mailers.smtp.port', 587);
        Config::set('mail.mailers.smtp.username', 'mailer');
        Config::set('mail.mailers.smtp.password', 'secret-pass');
        Config::set('mail.from.address', 'noreply@jetpakistan.pk');
        Config::set('mail.from.name', 'JetPakistan');

        $service = app(SmtpEnvironmentImportService::class);
        $first = $service->importIfNeeded($agency);
        $second = $service->importIfNeeded($agency);

        $this->assertNotNull($first);
        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, SupplierConnection::query()->where('provider', SupplierProvider::Smtp)->count());

        $resolver = app(SmtpMailConfigResolver::class);
        $this->assertSame('db_managed', $resolver->applyRuntimeConfig());

        $first->update(['is_active' => false, 'status' => SupplierConnectionStatus::Inactive]);
        $this->assertSame('env_fallback', $resolver->currentSource());
    }

    public function test_provider_catalog_includes_smtp_and_endpoint_defaults(): void
    {
        $admin = $this->seededAdmin();
        $response = $this->actingAs($admin)->getJson('/admin/api-settings?format=json')->assertOk();
        $providers = collect($response->json('providers'));
        $smtp = $providers->firstWhere('key', SupplierProvider::Smtp->value);
        $this->assertNotNull($smtp);
        $this->assertTrue($smtp['installed']);

        $sabre = $providers->firstWhere('key', SupplierProvider::Sabre->value);
        $this->assertSame(ProviderEndpointDefaults::for('sabre', 'live')['base_url'], $sabre['defaultBaseUrlLive']);
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
