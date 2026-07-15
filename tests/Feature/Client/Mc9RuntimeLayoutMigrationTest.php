<?php

namespace Tests\Feature\Client;

use App\Models\ClientProfile;
use App\Models\ClientProfileModule;
use App\Models\DeveloperUser;
use App\Services\Client\RuntimeViewResolver;
use App\Support\Audits\RuntimeLayoutMigrationAuditService;
use App\Support\Client\ClientProfileConfigReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\View;
use Tests\TestCase;

class Mc9RuntimeLayoutMigrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('ota-developer.enabled', true);
    }

    public function test_migrated_login_resolves_theme_auth_layout(): void
    {
        $profile = $this->makeProfile([
            'slug' => 'haseeb-master',
            'name' => 'Haseeb Master',
            'active_frontend_theme' => 'v1-classic',
            'is_master_profile' => true,
        ]);

        $resolution = app(RuntimeViewResolver::class)
            ->resolveLayoutSample('auth', 'frontend', $profile);

        $this->assertSame('themes.frontend.v1-classic.layouts.auth', $resolution['resolved_layout_name']);
        $this->assertTrue(View::exists('themes.frontend.v1-classic.layouts.auth'));
    }

    public function test_migrated_admin_index_resolves_theme_dashboard_layout(): void
    {
        $profile = $this->makeProfile([
            'slug' => 'haseeb-master',
            'name' => 'Haseeb Master',
            'active_admin_theme' => 'default-admin',
            'is_master_profile' => true,
        ]);

        $resolution = app(RuntimeViewResolver::class)
            ->resolveLayoutSample('dashboard', 'admin', $profile);

        $this->assertSame('themes.admin.default-admin.layouts.dashboard', $resolution['resolved_layout_name']);
    }

    public function test_migrated_staff_index_resolves_theme_dashboard_layout(): void
    {
        $profile = $this->makeProfile([
            'slug' => 'haseeb-master',
            'name' => 'Haseeb Master',
            'active_staff_theme' => 'default-staff',
            'is_master_profile' => true,
        ]);

        $resolution = app(RuntimeViewResolver::class)
            ->resolveLayoutSample('dashboard', 'staff', $profile);

        $this->assertSame('themes.staff.default-staff.layouts.dashboard', $resolution['resolved_layout_name']);
    }

    public function test_migrated_agent_index_resolves_theme_agent_portal_layout(): void
    {
        $profile = $this->makeProfile([
            'slug' => 'haseeb-master',
            'name' => 'Haseeb Master',
            'is_master_profile' => true,
        ]);

        $resolution = app(RuntimeViewResolver::class)
            ->resolveLayoutSample('agent-portal', 'agent', $profile);

        $this->assertSame('themes.agent.default-agent.layouts.agent-portal', $resolution['resolved_layout_name']);
    }

    public function test_migrated_customer_account_resolves_theme_customer_account_layout(): void
    {
        $profile = $this->makeProfile([
            'slug' => 'haseeb-master',
            'name' => 'Haseeb Master',
            'is_master_profile' => true,
        ]);

        $resolution = app(RuntimeViewResolver::class)
            ->resolveLayoutSample('customer-account', 'customer', $profile);

        $this->assertSame('themes.customer.default-customer.layouts.customer-account', $resolution['resolved_layout_name']);
    }

    public function test_migrated_customer_dashboard_resolves_theme_dashboard_layout(): void
    {
        $profile = $this->makeProfile([
            'slug' => 'haseeb-master',
            'name' => 'Haseeb Master',
            'is_master_profile' => true,
        ]);

        $resolution = app(RuntimeViewResolver::class)
            ->resolveLayoutSample('dashboard', 'customer', $profile);

        $this->assertSame('themes.customer.default-customer.layouts.dashboard', $resolution['resolved_layout_name']);
    }

    public function test_root_and_prefixed_routes_return_200_or_canonical_redirect(): void
    {
        $this->makeProfile([
            'slug' => 'haseeb-master',
            'name' => 'Haseeb Master',
            'active_frontend_theme' => 'v1-classic',
            'is_master_profile' => true,
        ]);

        $this->get('/')->assertOk();
        $this->get('/haseeb-master/home')->assertRedirect('/');
        $this->get('/login')->assertOk();
        $this->get('/haseeb-master/login')->assertRedirect('/login');
    }

    public function test_admin_guest_redirects_unchanged(): void
    {
        $this->makeProfile([
            'slug' => 'haseeb-master',
            'name' => 'Haseeb Master',
            'is_master_profile' => true,
        ]);

        $this->get('/admin')->assertRedirect(route('login', absolute: false));
        $this->get('/haseeb-master/admin')->assertRedirect('/admin');
    }

    public function test_staff_guest_redirects_unchanged(): void
    {
        $this->makeProfile([
            'slug' => 'haseeb-master',
            'name' => 'Haseeb Master',
            'is_master_profile' => true,
        ]);

        $this->get('/staff')->assertRedirect(route('login', absolute: false));
        $this->get('/haseeb-master/staff')->assertRedirect('/staff');
    }

    public function test_agent_guest_redirects_unchanged(): void
    {
        $this->makeProfile([
            'slug' => 'haseeb-master',
            'name' => 'Haseeb Master',
            'is_master_profile' => true,
        ]);

        $this->get('/agent')->assertRedirect(route('login', absolute: false));
        $this->get('/haseeb-master/agent')->assertRedirect('/agent');
    }

    public function test_customer_guest_redirects_unchanged(): void
    {
        $this->makeProfile([
            'slug' => 'haseeb-master',
            'name' => 'Haseeb Master',
            'is_master_profile' => true,
        ]);

        $this->get('/customer')->assertRedirect(route('login', absolute: false));
        $this->get('/haseeb-master/customer')->assertRedirect('/customer');
    }

    public function test_runtime_layout_migration_audit_passes(): void
    {
        $this->makeProfile([
            'slug' => 'haseeb-master',
            'name' => 'Haseeb Master',
            'active_frontend_theme' => 'v1-classic',
            'active_admin_theme' => 'default-admin',
            'active_staff_theme' => 'default-staff',
            'is_master_profile' => true,
        ]);

        $this->artisan('ota:runtime-layout-migration-audit', ['--client' => 'haseeb-master'])
            ->expectsOutputToContain('Classification: READ-ONLY runtime layout migration audit (MC-9A–9E).')
            ->expectsOutputToContain('Runtime layout migration audit passed for haseeb-master.')
            ->assertSuccessful();
    }

    public function test_route_safety_and_ui_runtime_audits_still_pass(): void
    {
        $this->makeProfile([
            'slug' => 'haseeb-master',
            'name' => 'Haseeb Master',
            'active_frontend_theme' => 'v1-classic',
            'active_admin_theme' => 'default-admin',
            'active_staff_theme' => 'default-staff',
            'is_master_profile' => true,
        ]);

        $this->artisan('ota:route-safety-audit', ['--client' => 'haseeb-master'])
            ->expectsOutputToContain('Route safety audit passed.')
            ->assertSuccessful();

        $this->artisan('ota:ui-runtime-audit', ['--client' => 'haseeb-master'])
            ->expectsOutputToContain('UI runtime audit completed for haseeb-master.')
            ->assertSuccessful();
    }

    public function test_dev_cp_still_accessible(): void
    {
        $developer = DeveloperUser::query()->create([
            'name' => 'Dev',
            'email' => 'dev-mc9-'.uniqid('', true).'@example.com',
            'password' => 'secret-password',
            'is_active' => true,
        ]);

        $this->withSession(['dev_cp_user_id' => $developer->id])
            ->get(route('dev.cp.clients.index'))
            ->assertOk();
    }

    public function test_migrated_portals_have_no_remaining_legacy_extends(): void
    {
        $this->makeProfile([
            'slug' => 'haseeb-master',
            'name' => 'Haseeb Master',
            'is_master_profile' => true,
        ]);

        $audit = app(RuntimeLayoutMigrationAuditService::class)->run('haseeb-master');

        $this->assertSame(0, $audit['counts']['remaining_staff_dashboard']);
        $this->assertSame(0, $audit['counts']['remaining_agent_portal']);
        $this->assertSame(0, $audit['counts']['remaining_customer_account']);
        $this->assertSame(0, $audit['counts']['remaining_customer_dashboard']);
        $this->assertGreaterThan(0, $audit['counts']['staff_migrated']);
        $this->assertGreaterThan(0, $audit['counts']['agent_migrated']);
        $this->assertGreaterThan(0, $audit['counts']['customer_migrated']);
        $this->assertFalse($audit['safety']['profile_edit_dashboard_migrated']);
        $this->assertFalse($audit['safety']['profile_edit_agent_migrated']);
        $this->assertFalse($audit['safety']['profile_edit_frontend_migrated']);
        $this->assertSame(0, $audit['counts']['deferred_client_layout_violations']);
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
            'environment' => 'production',
            'active_frontend_theme' => 'v1-classic',
            'active_admin_theme' => 'default-admin',
            'active_staff_theme' => 'default-staff',
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
