<?php

namespace Tests\Feature\Client;

use App\Models\ClientProfile;
use App\Models\ClientProfileModule;
use App\Services\Client\CurrentClientContext;
use App\Support\Client\ClientProfileConfigReader;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;
use Tests\Support\Auth\ConfiguresAuthTestEnvironment;
use Tests\TestCase;

class ClientPrefixedRouteParityTest extends TestCase
{
    use ConfiguresAuthTestEnvironment;
    use RefreshDatabase;

    private const PARITY_SLUG = 'preview-agency';

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('ota-developer.enabled', true);
        $this->withoutLoginOtpGate();
        Config::set('ota_client.slug', 'jetpk');
    }

    public function test_root_routes_remain_unprefixed(): void
    {
        $this->makeProfile(['slug' => 'haseeb-master', 'is_master_profile' => true]);

        $this->get('/')->assertOk();
        $this->get('/login')->assertOk();
        $this->get('/admin')->assertRedirect(route('login', absolute: false));

        $this->assertTrue(Route::has('home'));
        $this->assertTrue(Route::has('login'));
        $this->assertTrue(Route::has('admin.dashboard'));
    }

    public function test_default_slug_prefixed_login_redirects_to_canonical_login(): void
    {
        $this->makeProfile(['slug' => 'jetpk', 'name' => 'Jet Pakistan']);

        $this->get('/jetpk/login')
            ->assertStatus(302)
            ->assertRedirect('/login');
    }

    public function test_non_default_slug_login_renders_auth_controller_in_client_context(): void
    {
        $this->makeParityProfile();

        $this->get('/'.self::PARITY_SLUG.'/login')
            ->assertOk();
    }

    public function test_non_default_slug_admin_uses_auth_middleware(): void
    {
        $this->makeParityProfile();

        $this->get('/'.self::PARITY_SLUG.'/admin/dashboard')
            ->assertRedirect('/'.self::PARITY_SLUG.'/login');
    }

    public function test_non_default_slug_groups_search_renders_group_search_page(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $this->makeParityProfile();

        $this->get('/'.self::PARITY_SLUG.'/groups/search')
            ->assertOk();
    }

    public function test_non_default_slug_lookup_booking_redirects_to_manage_booking_surface(): void
    {
        $this->makeParityProfile();

        $this->get('/'.self::PARITY_SLUG.'/lookup-booking')
            ->assertRedirect('/lookup-booking');
    }

    public function test_dev_cp_routes_are_not_prefixed(): void
    {
        $this->assertTrue(Route::has('dev.cp.login'));
        $this->assertFalse(Route::has('client.parity.dev.cp.login'));

        $this->get(route('dev.cp.login'))->assertOk();
    }

    public function test_supplier_booking_post_is_not_prefixed(): void
    {
        $postRoutes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route) => str_starts_with((string) $route->getName(), 'client.parity.'))
            ->filter(fn ($route) => in_array('POST', $route->methods(), true))
            ->filter(fn ($route) => str_contains(strtolower($route->uri()), 'supplier'));

        $this->assertCount(0, $postRoutes);
    }

    public function test_page_settings_mutating_parity_routes_are_registered_for_client_prefix(): void
    {
        $this->makeParityProfile();

        $expected = [
            'client.parity.admin.page-settings.update',
            'client.parity.admin.page-settings.publish',
            'client.parity.admin.page-settings.preview.begin',
            'client.parity.admin.page-settings.assets.store',
            'client.parity.admin.page-settings.assets.destroy',
            'client.parity.admin.page-settings.palette.generate',
            'client.parity.admin.page-settings.palette.apply',
        ];

        foreach ($expected as $routeName) {
            $this->assertTrue(Route::has($routeName), "Missing parity route: {$routeName}");
        }

        $this->assertSame(
            '/'.self::PARITY_SLUG.'/admin/page-settings/home/publish',
            client_route('admin.page-settings.publish', ['pageKey' => 'home'], self::PARITY_SLUG),
        );
        $this->assertSame(
            '/'.self::PARITY_SLUG.'/admin/page-settings/home/preview',
            client_route('admin.page-settings.preview.begin', ['pageKey' => 'home'], self::PARITY_SLUG),
        );
        $this->assertSame(
            '/'.self::PARITY_SLUG.'/admin/page-settings/home',
            client_route('admin.page-settings.update', ['pageKey' => 'home'], self::PARITY_SLUG),
        );
    }

    public function test_high_risk_mutating_routes_are_not_registered_as_parity_except_allowed_flows(): void
    {
        $mutatingParity = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route) => str_starts_with((string) $route->getName(), 'client.parity.'))
            ->filter(fn ($route) => count(array_intersect($route->methods(), ['POST', 'PUT', 'PATCH', 'DELETE'])) > 0)
            ->reject(fn ($route) => (bool) ($route->getAction()['client_parity_mutating_page_settings'] ?? false))
            ->reject(fn ($route) => (bool) ($route->getAction()['client_parity_mutating_flow'] ?? false));

        $this->assertCount(0, $mutatingParity);
    }

    public function test_reserved_slugs_do_not_bind_as_client_slug(): void
    {
        $this->get('/admin/home')->assertNotFound();
        $this->get('/login/home')->assertNotFound();
    }

    public function test_client_route_helper_generates_prefixed_url_in_preview_context(): void
    {
        $profile = $this->makeParityProfile();
        app(CurrentClientContext::class)->set($profile);

        $this->assertSame(
            route('client.parity.login', ['clientSlug' => self::PARITY_SLUG], false),
            client_route('login'),
        );
    }

    public function test_client_url_helper_prefixes_path_in_preview_context(): void
    {
        $profile = $this->makeParityProfile();
        app(CurrentClientContext::class)->set($profile);

        $this->assertSame('/'.self::PARITY_SLUG.'/groups/search', client_url('/groups/search'));
    }

    public function test_is_client_preview_helper(): void
    {
        $profile = $this->makeParityProfile();

        $this->assertFalse(is_client_preview());

        app(CurrentClientContext::class)->set($profile);

        $this->assertTrue(is_client_preview());
    }

    public function test_parity_status_command_reports_registered_routes(): void
    {
        $this->makeProfile(['slug' => 'haseeb-master', 'is_master_profile' => true]);

        $this->artisan('ota:client-route-parity-status', [
            '--client' => 'haseeb-master',
            '--target' => 'jetpk',
        ])->assertSuccessful();
    }

    public function test_client_route_with_explicit_slug_outside_preview(): void
    {
        $this->makeParityProfile();

        $this->assertFalse(is_client_preview());

        $this->assertSame(
            route('client.parity.login', ['clientSlug' => self::PARITY_SLUG], false),
            client_route('login', [], self::PARITY_SLUG),
        );
    }

    public function test_root_route_generation_without_preview_context(): void
    {
        $this->makeProfile(['slug' => 'jetpk', 'name' => 'Jet Pakistan']);

        app(CurrentClientContext::class)->clear();

        $this->assertSame('/login', route('login', [], false));
        $this->assertSame('/login', client_route('login'));
    }

    public function test_non_default_slug_login_page_contains_prefixed_nav_links(): void
    {
        $this->makeParityProfile();

        $this->get('/'.self::PARITY_SLUG.'/login')
            ->assertOk()
            ->assertSee('/'.self::PARITY_SLUG.'/login', false)
            ->assertSee('/'.self::PARITY_SLUG.'/home', false);
    }

    public function test_non_default_slug_home_public_layout_nav_stays_prefixed(): void
    {
        $this->makeParityProfile();

        $this->get('/'.self::PARITY_SLUG.'/home')
            ->assertOk()
            ->assertSee('/'.self::PARITY_SLUG.'/home', false)
            ->assertSee('/'.self::PARITY_SLUG.'/login', false);
    }

    public function test_client_context_flow_audit_command_passes_for_non_default_slug(): void
    {
        $this->makeParityProfile();

        $this->artisan('ota:client-context-flow-audit', [
            '--client' => self::PARITY_SLUG,
        ])->assertSuccessful();
    }

    public function test_route_safety_audit_passes_with_parity_enabled(): void
    {
        $this->makeProfile(['slug' => 'haseeb-master', 'is_master_profile' => true]);

        $this->artisan('ota:route-safety-audit', [
            '--client' => 'haseeb-master',
        ])->assertSuccessful();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeParityProfile(array $overrides = []): ClientProfile
    {
        return $this->makeProfile(array_merge([
            'slug' => self::PARITY_SLUG,
            'name' => 'Preview Agency',
            'active_frontend_theme' => 'jetpakistan',
            'active_admin_theme' => 'jetpakistan',
            'asset_profile' => 'jetpk-assets',
        ], $overrides));
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
