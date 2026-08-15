<?php

namespace Tests\Feature\Client;

use App\Models\ClientProfile;
use App\Models\ClientProfileBranding;
use App\Models\ClientProfileModule;
use App\Services\Client\ClientProfileResolver;
use App\Services\Client\CurrentClientContext;
use App\Support\Client\ClientProfileConfigReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ClientPreviewRoutingTest extends TestCase
{
    use RefreshDatabase;

    private const PARITY_SLUG = 'preview-agency';

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('ota_client.slug', 'jetpk');
        Config::set('client.canonical_client.slug', 'jetpk');
    }

    public function test_default_slug_prefixed_home_redirects_to_canonical_home(): void
    {
        $this->makeProfile([
            'name' => 'Jet Pakistan',
            'slug' => 'jetpk',
            'active_frontend_theme' => 'jetpakistan',
            'asset_profile' => 'jetpk-assets',
        ]);

        $this->get(route('client.parity.home.alias', ['clientSlug' => 'jetpk']))
            ->assertStatus(302)
            ->assertRedirect('/');

        $context = app(CurrentClientContext::class);
        $this->assertFalse($context->isPreview());
    }

    public function test_non_default_slug_home_loads_in_preview_context(): void
    {
        $this->makeProfile([
            'name' => 'Preview Agency',
            'slug' => self::PARITY_SLUG,
            'active_frontend_theme' => 'v1-classic',
            'asset_profile' => 'preview-assets',
        ]);

        $this->get(route('client.parity.home.alias', ['clientSlug' => self::PARITY_SLUG]))
            ->assertOk();

        $context = app(CurrentClientContext::class);
        $this->assertTrue($context->isPreview());
        $this->assertSame(self::PARITY_SLUG, $context->slug());
        $this->assertSame('v1-classic', $context->theme());
        $this->assertSame('preview-assets', $context->assetProfile());
    }

    public function test_haseeb_master_prefixed_home_loads_as_non_default_parity_client(): void
    {
        $this->makeProfile([
            'name' => 'Haseeb Master',
            'slug' => 'haseeb-master',
            'is_master_profile' => true,
            'active_frontend_theme' => 'v1-classic',
        ]);

        $this->get('/haseeb-master/home')->assertOk();

        $context = app(CurrentClientContext::class);
        $this->assertTrue($context->isPreview());
        $this->assertSame('haseeb-master', $context->slug());
    }

    public function test_haseeb_master_prefixed_admin_dashboard_redirects_to_prefixed_login(): void
    {
        $this->makeProfile([
            'name' => 'Haseeb Master',
            'slug' => 'haseeb-master',
            'is_master_profile' => true,
        ]);

        $this->get('/haseeb-master/admin/dashboard')
            ->assertStatus(302)
            ->assertRedirect('/haseeb-master/login');
    }

    public function test_default_slug_root_redirects_to_canonical_home(): void
    {
        $this->makeProfile([
            'slug' => 'jetpk',
            'name' => 'Jet Pakistan',
        ]);

        $this->get('/jetpk/home')
            ->assertRedirect('/');
    }

    public function test_preview_route_returns_404_for_missing_client(): void
    {
        $this->get('/missing-client/home')
            ->assertNotFound();
    }

    public function test_preview_route_returns_404_for_inactive_client(): void
    {
        $this->makeProfile([
            'slug' => 'inactive-client',
            'is_active' => false,
        ]);

        $this->get('/inactive-client/home')
            ->assertNotFound();
    }

    public function test_reserved_slug_is_not_treated_as_client_preview(): void
    {
        $this->get('/admin/home')
            ->assertNotFound();
    }

    public function test_current_client_context_contains_expected_values(): void
    {
        $profile = $this->makeProfile([
            'slug' => 'ctx-client',
            'active_frontend_theme' => 'v1-classic',
            'asset_profile' => 'ctx-assets',
        ]);

        ClientProfileModule::query()
            ->where('client_profile_id', $profile->id)
            ->where('module_key', 'admin_panel')
            ->update(['enabled' => true]);

        $route = Route::getRoutes()->getByName('client.parity.admin.dashboard');
        $this->assertNotNull($route);
        $this->assertContains('preview.client', $route->gatherMiddleware());

        $this->get(route('client.parity.admin.dashboard', ['clientSlug' => 'ctx-client']))
            ->assertRedirect(route('client.parity.login', ['clientSlug' => 'ctx-client'], false));
    }

    public function test_existing_homepage_route_still_works_and_resolves_default_context(): void
    {
        $this->makeProfile([
            'slug' => 'jetpk',
            'name' => 'Jet Pakistan',
            'active_frontend_theme' => 'jetpakistan',
        ]);

        $this->get(route('home'))
            ->assertOk();

        $context = app(CurrentClientContext::class);
        $this->assertFalse($context->isPreview());
        $this->assertSame('jetpk', $context->slug());
    }

    public function test_parity_request_sets_resolved_branding_and_theme_metadata(): void
    {
        $profile = $this->makeProfile([
            'slug' => self::PARITY_SLUG,
            'active_frontend_theme' => 'v1-classic',
            'active_admin_theme' => 'default-admin',
            'active_staff_theme' => 'default-staff',
            'asset_profile' => 'preview-assets',
        ]);

        ClientProfileBranding::query()->create([
            'client_profile_id' => $profile->id,
            'company_name' => 'Preview Agency',
            'logo_path' => 'logo/preview.svg',
            'favicon_path' => 'favicon/preview.ico',
            'primary_color' => '#112233',
            'secondary_color' => '#445566',
            'accent_color' => '#778899',
            'phone' => '+92 300 1112233',
            'email' => 'hello@preview.test',
            'address' => 'Karachi, PK',
            'footer_text' => 'Preview Agency',
        ]);

        $this->get(route('client.parity.home.alias', ['clientSlug' => self::PARITY_SLUG]))
            ->assertOk();

        $resolved = app(ClientProfileResolver::class)->resolveBySlug(self::PARITY_SLUG);
        $this->assertNotNull($resolved);
        $this->assertSame('Preview Agency', $resolved->branding?->company_name);
        $this->assertSame('#112233', $resolved->branding?->primary_color);
        $this->assertSame('hello@preview.test', $resolved->branding?->email);
        $this->assertSame('v1-classic', $resolved->active_frontend_theme);
        $this->assertSame('preview-assets', $resolved->asset_profile);
    }

    public function test_parity_request_resolves_asset_profile(): void
    {
        $this->makeProfile([
            'slug' => self::PARITY_SLUG,
            'active_frontend_theme' => 'v1-classic',
            'asset_profile' => 'preview-assets',
        ]);

        ClientProfileBranding::query()->create([
            'client_profile_id' => ClientProfile::query()->where('slug', self::PARITY_SLUG)->value('id'),
            'company_name' => 'Preview Agency',
            'logo_path' => 'logo/preview.svg',
            'favicon_path' => 'favicon/preview.ico',
        ]);

        $this->get('/'.self::PARITY_SLUG.'/home')->assertOk();

        $resolved = app(ClientProfileResolver::class)->resolveBySlug(self::PARITY_SLUG);
        $this->assertSame('preview-assets', $resolved?->asset_profile);
        $this->assertSame('v1-classic', $resolved?->active_frontend_theme);
    }

    public function test_existing_portal_routes_remain_registered(): void
    {
        $this->assertTrue(Route::has('home'));
        $this->assertTrue(Route::has('login'));
        $this->assertTrue(Route::has('admin.dashboard'));
        $this->assertSame('/admin/dashboard', route('admin.dashboard', [], false));
        $this->assertTrue(Route::has('staff.dashboard'));
        $this->assertSame('/staff/dashboard', route('staff.dashboard', [], false));
        $this->assertTrue(Route::has('agent.dashboard'));
        $this->assertSame('/agent', route('agent.dashboard', [], false));
        $this->assertTrue(Route::has('client.preview.root'));
        $this->assertTrue(Route::has('client.parity.login'));
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
