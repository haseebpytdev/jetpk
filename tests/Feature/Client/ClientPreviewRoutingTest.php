<?php

namespace Tests\Feature\Client;

use App\Models\ClientProfile;
use App\Models\ClientProfileBranding;
use App\Models\ClientProfileModule;
use App\Services\Client\ClientProfileResolver;
use App\Services\Client\CurrentClientContext;
use App\Support\Client\ClientProfileConfigReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ClientPreviewRoutingTest extends TestCase
{
    use RefreshDatabase;

    public function test_preview_home_loads_for_active_client(): void
    {
        $this->makeProfile([
            'name' => 'Jet Pakistan',
            'slug' => 'jetpk',
            'active_frontend_theme' => 'v2-modern',
            'asset_profile' => 'jetpk-assets',
        ]);

        $this->get(route('client.parity.home.alias', ['clientSlug' => 'jetpk']))
            ->assertOk();

        $context = app(CurrentClientContext::class);
        $this->assertTrue($context->isPreview());
        $this->assertSame('jetpk', $context->slug());
        $this->assertSame('v2-modern', $context->theme());
        $this->assertSame('jetpk-assets', $context->assetProfile());
    }

    public function test_haseeb_master_root_redirects_to_canonical_home(): void
    {
        $this->makeProfile([
            'name' => 'Haseeb Master',
            'slug' => 'haseeb-master',
            'is_master_profile' => true,
        ]);

        $this->get('/haseeb-master')
            ->assertStatus(302)
            ->assertRedirect('/');
    }

    public function test_haseeb_master_prefixed_home_redirects_to_canonical_home(): void
    {
        $this->makeProfile([
            'name' => 'Haseeb Master',
            'slug' => 'haseeb-master',
            'is_master_profile' => true,
        ]);

        $this->get('/haseeb-master/home')
            ->assertStatus(302)
            ->assertRedirect('/');
    }

    public function test_haseeb_master_prefixed_admin_redirects_to_canonical_admin(): void
    {
        $this->makeProfile([
            'name' => 'Haseeb Master',
            'slug' => 'haseeb-master',
            'is_master_profile' => true,
        ]);

        $this->get('/haseeb-master/admin')
            ->assertStatus(302)
            ->assertRedirect('/admin');
    }

    public function test_client_slug_root_redirects_to_home(): void
    {
        $this->makeProfile([
            'slug' => 'jetpk',
            'name' => 'Jet Pakistan',
        ]);

        $this->get('/jetpk')
            ->assertRedirect(route('client.parity.home.alias', ['clientSlug' => 'jetpk'], false));
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
            'slug' => 'haseeb-master',
            'name' => 'Haseeb Master',
            'is_master_profile' => true,
        ]);

        $this->get(route('home'))
            ->assertOk();

        $context = app(CurrentClientContext::class);
        $this->assertFalse($context->isPreview());
        $this->assertSame('haseeb-master', $context->slug());
    }

    public function test_parity_request_sets_resolved_branding_and_theme_metadata(): void
    {
        $profile = $this->makeProfile([
            'slug' => 'jetpk',
            'active_frontend_theme' => 'v2-modern',
            'active_admin_theme' => 'v1-classic',
            'active_staff_theme' => 'v1-classic',
            'asset_profile' => 'jetpk-assets',
        ]);

        ClientProfileBranding::query()->create([
            'client_profile_id' => $profile->id,
            'company_name' => 'Jet Pakistan',
            'logo_path' => 'logo/jetpk.svg',
            'favicon_path' => 'favicon/jetpk.ico',
            'primary_color' => '#112233',
            'secondary_color' => '#445566',
            'accent_color' => '#778899',
            'phone' => '+92 300 1112233',
            'email' => 'hello@jetpakistan.com',
            'address' => 'Karachi, PK',
            'footer_text' => 'Fly with Jet Pakistan',
        ]);

        $this->get(route('client.parity.home.alias', ['clientSlug' => 'jetpk']))
            ->assertOk();

        $resolved = app(ClientProfileResolver::class)->resolveBySlug('jetpk');
        $this->assertNotNull($resolved);
        $this->assertSame('Jet Pakistan', $resolved->branding?->company_name);
        $this->assertSame('#112233', $resolved->branding?->primary_color);
        $this->assertSame('hello@jetpakistan.com', $resolved->branding?->email);
        $this->assertSame('v2-modern', $resolved->active_frontend_theme);
        $this->assertSame('jetpk-assets', $resolved->asset_profile);
    }

    public function test_parity_request_resolves_asset_profile(): void
    {
        $this->makeProfile([
            'slug' => 'jetpk',
            'active_frontend_theme' => 'v2-modern',
            'asset_profile' => 'jetpk-assets',
        ]);

        ClientProfileBranding::query()->create([
            'client_profile_id' => ClientProfile::query()->where('slug', 'jetpk')->value('id'),
            'company_name' => 'Jet Pakistan',
            'logo_path' => 'logo/jetpk.svg',
            'favicon_path' => 'favicon/jetpk.ico',
        ]);

        $this->get('/jetpk/home')->assertOk();

        $resolved = app(ClientProfileResolver::class)->resolveBySlug('jetpk');
        $this->assertSame('jetpk-assets', $resolved?->asset_profile);
        $this->assertSame('v2-modern', $resolved?->active_frontend_theme);
    }

    public function test_existing_portal_routes_remain_registered(): void
    {
        $this->assertTrue(Route::has('home'));
        $this->assertTrue(Route::has('login'));
        $this->assertTrue(Route::has('admin.dashboard'));
        $this->assertSame('/admin', route('admin.dashboard', [], false));
        $this->assertTrue(Route::has('staff.dashboard'));
        $this->assertSame('/staff', route('staff.dashboard', [], false));
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
