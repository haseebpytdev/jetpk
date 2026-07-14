<?php

namespace Tests\Feature\Client;

use App\Enums\AccountType;
use App\Http\Middleware\PersistClientPreviewContext;
use App\Models\ClientProfile;
use App\Models\ClientProfileModule;
use App\Models\User;
use App\Services\Client\ClientRedirectResolver;
use App\Services\Client\CurrentClientContext;
use App\Support\Client\ClientProfileConfigReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class ClientContextPersistenceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('ota-developer.enabled', true);
    }

    public function test_preview_get_sets_session_slug(): void
    {
        $this->makeProfile(['slug' => 'jetpk', 'name' => 'Jet Pakistan']);

        $this->get('/jetpk/login')
            ->assertOk()
            ->assertSessionHas(PersistClientPreviewContext::SESSION_KEY, 'jetpk');
    }

    public function test_client_redirect_resolver_preserves_client_slug_for_admin(): void
    {
        $profile = $this->makeProfile(['slug' => 'jetpk', 'name' => 'Jet Pakistan']);
        app(CurrentClientContext::class)->set($profile);

        $admin = User::factory()->create([
            'account_type' => AccountType::PlatformAdmin,
        ]);

        $path = app(ClientRedirectResolver::class)->dashboardPathForUser($admin);

        $this->assertSame('/jetpk/admin', $path);
    }

    public function test_dev_cp_route_is_not_client_prefixed(): void
    {
        $this->assertSame('/dev/cp/login', client_route('dev.cp.login'));
    }

    public function test_current_client_slug_and_profile_helpers(): void
    {
        $profile = $this->makeProfile(['slug' => 'jetpk', 'name' => 'Jet Pakistan']);

        $this->get('/jetpk/login');

        $this->assertTrue(is_client_preview());
        $this->assertSame('jetpk', current_client_slug());
        $this->assertSame($profile->id, current_client_profile()?->id);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeProfile(array $overrides = []): ClientProfile
    {
        $profile = ClientProfile::query()->create(array_merge([
            'name' => 'Test Client',
            'slug' => 'test-client-'.uniqid(),
            'domain' => null,
            'environment' => 'staging',
            'active_frontend_theme' => 'v1-classic',
            'active_admin_theme' => 'v1-classic',
            'active_staff_theme' => 'v1-classic',
            'asset_profile' => 'test-assets',
            'default_locale' => 'en',
            'timezone' => 'Asia/Karachi',
            'currency' => 'PKR',
            'is_master_profile' => false,
            'is_active' => true,
        ], $overrides));

        foreach (ClientProfileConfigReader::MODULE_KEYS as $moduleKey) {
            ClientProfileModule::query()->create([
                'client_profile_id' => $profile->id,
                'module_key' => $moduleKey,
                'enabled' => false,
            ]);
        }

        return $profile;
    }
}
