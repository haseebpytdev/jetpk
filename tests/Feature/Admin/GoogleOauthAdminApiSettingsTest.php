<?php

namespace Tests\Feature\Admin;

use App\Enums\AccountType;
use App\Enums\SupplierConnectionStatus;
use App\Enums\SupplierEnvironment;
use App\Enums\SupplierProvider;
use App\Http\Controllers\Auth\SocialAuthController;
use App\Models\SupplierConnection;
use App\Models\User;
use App\Services\Integrations\GoogleOauthConfigResolver;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class GoogleOauthAdminApiSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_resolver_db_overlays_env_when_complete(): void
    {
        $admin = $this->seededAdmin();

        Config::set('services.google.client_id', 'env-client-id');
        Config::set('services.google.client_secret', 'env-client-secret');
        Config::set('services.google.redirect', 'https://env.example.test/auth/google/callback');
        Config::set('app.url', 'https://jetpakistan.test');

        SupplierConnection::query()->create([
            'agency_id' => $admin->current_agency_id,
            'provider' => SupplierProvider::GoogleOauth,
            'name' => 'Google OAuth LIVE',
            'environment' => SupplierEnvironment::Live,
            'status' => SupplierConnectionStatus::Active,
            'is_active' => true,
            'credentials' => [
                'client_id' => 'db-client-id.apps.googleusercontent.com',
                'client_secret' => 'db-client-secret',
                'redirect_uri' => 'https://jetpakistan.test/auth/google/callback',
            ],
        ]);

        $resolver = app(GoogleOauthConfigResolver::class);
        $this->assertSame('db_managed', $resolver->applyRuntimeConfig());
        $this->assertSame('db-client-id.apps.googleusercontent.com', config('services.google.client_id'));
        $this->assertSame('db-client-secret', config('services.google.client_secret'));
        $this->assertSame('https://jetpakistan.test/auth/google/callback', config('services.google.redirect'));
        $this->assertTrue(SocialAuthController::providerIsConfigured('google'));
    }

    public function test_incomplete_db_falls_back_to_env(): void
    {
        $admin = $this->seededAdmin();

        Config::set('services.google.client_id', 'env-client-id');
        Config::set('services.google.client_secret', 'env-client-secret');
        Config::set('services.google.redirect', 'https://env.example.test/auth/google/callback');
        Config::set('app.url', 'https://jetpakistan.test');

        SupplierConnection::query()->create([
            'agency_id' => $admin->current_agency_id,
            'provider' => SupplierProvider::GoogleOauth,
            'name' => 'Google OAuth Incomplete',
            'environment' => SupplierEnvironment::Live,
            'status' => SupplierConnectionStatus::Active,
            'is_active' => true,
            'credentials' => [
                'client_id' => 'db-only-id',
                'client_secret' => '',
            ],
        ]);

        $resolver = app(GoogleOauthConfigResolver::class);
        $this->assertSame('env_fallback', $resolver->applyRuntimeConfig());
        $this->assertSame('env-client-id', config('services.google.client_id'));
        $this->assertSame('env-client-secret', config('services.google.client_secret'));
        $this->assertTrue(SocialAuthController::providerIsConfigured('google'));
    }

    public function test_blank_secret_on_edit_preserves_existing(): void
    {
        $admin = $this->seededAdmin();
        $connection = SupplierConnection::query()->create([
            'agency_id' => $admin->current_agency_id,
            'provider' => SupplierProvider::GoogleOauth,
            'name' => 'Google Keep Secret',
            'environment' => SupplierEnvironment::Live,
            'status' => SupplierConnectionStatus::Active,
            'is_active' => true,
            'credentials' => [
                'client_id' => 'keep-id.apps.googleusercontent.com',
                'client_secret' => 'keep-me-secret',
            ],
        ]);

        $this->actingAs($admin)->patchJson('/admin/api-settings/'.$connection->id.'?format=json', [
            'provider' => 'google_oauth',
            'name' => 'Google Keep Secret',
            'environment' => 'live',
            'status' => 'active',
            'credentials' => [
                'client_id' => 'keep-id.apps.googleusercontent.com',
                'client_secret' => '',
            ],
            'settings_json' => '{}',
        ])->assertOk();

        $connection->refresh();
        $this->assertSame('keep-me-secret', $connection->credentials['client_secret']);
    }

    public function test_present_connection_json_omits_secret_plaintext(): void
    {
        $admin = $this->seededAdmin();
        SupplierConnection::query()->create([
            'agency_id' => $admin->current_agency_id,
            'provider' => SupplierProvider::GoogleOauth,
            'name' => 'Google Masked',
            'environment' => SupplierEnvironment::Live,
            'status' => SupplierConnectionStatus::Active,
            'is_active' => true,
            'credentials' => [
                'client_id' => 'mask-id.apps.googleusercontent.com',
                'client_secret' => 'super-secret-google-value',
            ],
        ]);

        $response = $this->actingAs($admin)->getJson('/admin/api-settings?format=json')->assertOk();
        $payload = $response->getContent();
        $this->assertStringNotContainsString('super-secret-google-value', $payload);

        $row = collect($response->json('connections'))->firstWhere('provider', 'google_oauth');
        $this->assertNotNull($row);
        $this->assertTrue($row['google_oauth']['client_secret_present']);
        $this->assertArrayNotHasKey('client_secret', $row['google_oauth']);
        $this->assertStringNotContainsString('super-secret-google-value', json_encode($row));
    }

    public function test_unauthorized_roles_denied_api_settings(): void
    {
        $this->seed(OtaFoundationSeeder::class);

        $staff = User::query()->where('email', 'staff@ota.demo')->first()
            ?? User::factory()->create(['account_type' => AccountType::Staff, 'email' => 'staff-google-oauth@ota.demo']);
        $agent = User::query()->where('email', 'agent@ota.demo')->first()
            ?? User::factory()->create(['account_type' => AccountType::Agent, 'email' => 'agent-google-oauth@ota.demo']);
        $customer = User::factory()->create(['account_type' => AccountType::Customer]);

        $this->actingAs($staff)->getJson('/admin/api-settings?format=json')->assertForbidden();
        $this->actingAs($agent)->getJson('/admin/api-settings?format=json')->assertForbidden();
        $this->actingAs($customer)->getJson('/admin/api-settings?format=json')->assertForbidden();
    }

    public function test_provider_is_configured_false_when_disabled_or_incomplete_without_env(): void
    {
        $admin = $this->seededAdmin();

        Config::set('services.google.client_id', null);
        Config::set('services.google.client_secret', null);
        Config::set('services.google.redirect', null);
        Config::set('app.url', 'https://jetpakistan.test');

        $connection = SupplierConnection::query()->create([
            'agency_id' => $admin->current_agency_id,
            'provider' => SupplierProvider::GoogleOauth,
            'name' => 'Google Disabled',
            'environment' => SupplierEnvironment::Live,
            'status' => SupplierConnectionStatus::Inactive,
            'is_active' => false,
            'credentials' => [
                'client_id' => 'db-client-id.apps.googleusercontent.com',
                'client_secret' => 'db-client-secret',
            ],
        ]);

        $resolver = app(GoogleOauthConfigResolver::class);
        $this->assertSame('env_fallback', $resolver->applyRuntimeConfig());
        $this->assertFalse(SocialAuthController::providerIsConfigured('google'));

        $connection->update([
            'is_active' => true,
            'status' => SupplierConnectionStatus::Active,
            'credentials' => ['client_id' => 'only-id'],
        ]);

        $this->assertSame('env_fallback', $resolver->applyRuntimeConfig());
        $this->assertFalse(SocialAuthController::providerIsConfigured('google'));
    }

    public function test_provider_catalog_includes_google_oauth(): void
    {
        $admin = $this->seededAdmin();
        $response = $this->actingAs($admin)->getJson('/admin/api-settings?format=json')->assertOk();
        $providers = collect($response->json('providers'));
        $google = $providers->firstWhere('key', SupplierProvider::GoogleOauth->value);
        $this->assertNotNull($google);
        $this->assertTrue($google['installed']);
        $this->assertSame('auth', $google['module']);

        $cards = collect($response->json('providerCards'));
        $card = $cards->firstWhere('key', 'google_oauth');
        $this->assertNotNull($card);
        $this->assertSame('Google Sign-In / Google OAuth', $card['label']);
        $this->assertSame('Authentication', $card['channel']);
    }

    public function test_test_configuration_checks_completeness_without_token_exchange(): void
    {
        $admin = $this->seededAdmin();
        Config::set('app.url', 'https://jetpakistan.test');

        $connection = SupplierConnection::query()->create([
            'agency_id' => $admin->current_agency_id,
            'provider' => SupplierProvider::GoogleOauth,
            'name' => 'Google Test Config',
            'environment' => SupplierEnvironment::Live,
            'status' => SupplierConnectionStatus::Active,
            'is_active' => true,
            'credentials' => [
                'client_id' => 'test-id.apps.googleusercontent.com',
                'client_secret' => 'test-secret',
            ],
        ]);

        $response = $this->actingAs($admin)
            ->patchJson('/admin/api-settings/'.$connection->id.'/test?format=json')
            ->assertOk();

        $test = $response->json('test');
        $this->assertSame('ready_for_review', $test['last_test_status']);
        $this->assertFalse($test['token_exchange'] ?? true);

        $connection->refresh();
        $this->assertSame('ready_for_review', $connection->last_test_status);
        $this->assertNull($connection->last_error);
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
