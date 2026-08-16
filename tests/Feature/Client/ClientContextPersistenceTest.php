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

    private const PARITY_SLUG = 'preview-agency';

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('ota-developer.enabled', true);
        Config::set('client_route_parity.enabled', true);
        Config::set('ota_client.slug', 'jetpk');
    }

    public function test_preview_get_sets_session_slug(): void
    {
        $this->makeParityProfile();

        $this->get('/'.self::PARITY_SLUG.'/login')
            ->assertOk()
            ->assertSessionHas(PersistClientPreviewContext::SESSION_KEY, self::PARITY_SLUG);
    }

    public function test_default_slug_login_alias_redirects_to_canonical_login(): void
    {
        $this->makeProfile([
            'slug' => 'jetpk',
            'name' => 'Jet Pakistan',
            'is_master_profile' => true,
        ]);

        $this->get('/jetpk/login')
            ->assertRedirect('/login');
    }

    public function test_client_redirect_resolver_uses_canonical_admin_for_default_deployment(): void
    {
        $profile = $this->makeProfile(['slug' => 'jetpk', 'name' => 'Jet Pakistan', 'is_master_profile' => true]);
        app(CurrentClientContext::class)->set($profile);

        $admin = User::factory()->create([
            'account_type' => AccountType::PlatformAdmin,
        ]);

        $path = app(ClientRedirectResolver::class)->dashboardPathForUser($admin);

        $this->assertSame('/admin/dashboard', $path);
    }

    public function test_dev_cp_route_is_not_client_prefixed(): void
    {
        $this->assertSame('/dev/cp/login', client_route('dev.cp.login'));
    }

    public function test_current_client_slug_and_profile_helpers(): void
    {
        $profile = $this->makeParityProfile();

        $this->get('/'.self::PARITY_SLUG.'/login')
            ->assertOk()
            ->assertSessionHas(PersistClientPreviewContext::SESSION_KEY, self::PARITY_SLUG);

        app(CurrentClientContext::class)->set($profile);

        $this->assertTrue(is_client_preview());
        $this->assertSame(self::PARITY_SLUG, app(CurrentClientContext::class)->slug());
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

    private function makeParityProfile(): ClientProfile
    {
        return $this->makeProfile([
            'slug' => self::PARITY_SLUG,
            'name' => 'Preview Agency',
            'is_master_profile' => false,
            'active_frontend_theme' => 'jetpakistan',
            'active_admin_theme' => 'jetpakistan',
            'active_staff_theme' => 'jetpakistan',
            'asset_profile' => 'jetpk-assets',
        ]);
    }
}
