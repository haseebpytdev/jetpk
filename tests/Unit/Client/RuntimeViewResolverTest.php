<?php

namespace Tests\Unit\Client;

use App\Models\ClientProfile;
use App\Models\ClientProfileModule;
use App\Services\Client\RuntimeViewResolver;
use App\Support\Client\ClientProfileConfigReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class RuntimeViewResolverTest extends TestCase
{
    use RefreshDatabase;

    private ?string $tempThemeViewPath = null;

    private bool $tempThemeViewCreated = false;

    protected function tearDown(): void
    {
        if ($this->tempThemeViewCreated && $this->tempThemeViewPath !== null && File::exists($this->tempThemeViewPath)) {
            File::delete($this->tempThemeViewPath);
        }

        $this->tempThemeViewPath = null;
        $this->tempThemeViewCreated = false;

        parent::tearDown();
    }

    public function test_falls_back_to_legacy_view_when_theme_view_missing(): void
    {
        $profile = $this->makeProfile([
            'slug' => 'haseeb-master',
            'active_frontend_theme' => 'v1-classic',
            'is_master_profile' => true,
        ]);

        $resolver = app(RuntimeViewResolver::class);

        $this->assertSame('frontend.home', $resolver->view('home', 'frontend', $profile));
        $this->assertSame('themes.frontend.v1-classic.home', $resolver->themeViewName('home', 'frontend', $profile));
        $this->assertTrue($resolver->exists('home', 'frontend', $profile));
        $this->assertFalse($resolver->exists('definitely-missing-view', 'frontend', $profile));
    }

    public function test_prefers_theme_specific_view_when_present(): void
    {
        $profile = $this->makeProfile([
            'slug' => 'haseeb-master',
            'active_frontend_theme' => 'v1-classic',
            'is_master_profile' => true,
        ]);

        $this->tempThemeViewPath = resource_path('views/themes/frontend/v1-classic/home.blade.php');
        File::ensureDirectoryExists(dirname($this->tempThemeViewPath));
        File::put($this->tempThemeViewPath, '<div>theme home</div>');
        $this->tempThemeViewCreated = true;

        $resolver = app(RuntimeViewResolver::class);

        $this->assertSame('themes.frontend.v1-classic.home', $resolver->view('home', 'frontend', $profile));
        $this->assertTrue($resolver->exists('home', 'frontend', $profile));

        $sample = $resolver->resolveSample('home', 'frontend', $profile);
        $this->assertFalse($sample['fallback_used']);
        $this->assertSame('themes.frontend.v1-classic.home', $sample['resolved_view_name']);
    }

    public function test_prefers_mc8c_theme_home_path_for_frontend_home_logical_name(): void
    {
        $profile = $this->makeProfile([
            'slug' => 'haseeb-master',
            'active_frontend_theme' => 'v1-classic',
            'is_master_profile' => true,
        ]);

        $resolver = app(RuntimeViewResolver::class);

        $this->assertSame('themes.frontend.v1-classic.frontend.home', $resolver->view('frontend.home', 'frontend', $profile));

        $sample = $resolver->resolveSample('frontend.home', 'frontend', $profile);
        $this->assertFalse($sample['fallback_used']);
        $this->assertSame('themes.frontend.v1-classic.frontend.home', $sample['resolved_view_name']);
    }

    public function test_client_view_helper_returns_fallback_safely(): void
    {
        $this->makeProfile([
            'slug' => 'haseeb-master',
            'active_frontend_theme' => 'v1-classic',
            'is_master_profile' => true,
        ]);

        $this->assertSame('frontend.home', client_view('home', 'frontend'));
        $this->assertTrue(client_view_exists('home', 'frontend'));
        $this->assertFalse(client_view_exists('missing-logical-view', 'frontend'));
    }

    public function test_client_layout_app_alias_resolves_theme_or_legacy_layout(): void
    {
        $this->makeProfile([
            'slug' => 'haseeb-master',
            'active_frontend_theme' => 'v1-classic',
            'active_admin_theme' => 'default-admin',
            'is_master_profile' => true,
        ]);

        $this->assertSame('themes.frontend.v1-classic.layouts.frontend', client_layout('app', 'frontend'));
        $this->assertSame('themes.admin.default-admin.layouts.dashboard', client_layout('app', 'admin'));
        $this->assertSame('themes.agent.default-agent.layouts.agent-portal', client_layout('app', 'agent'));
        $this->assertSame('themes.customer.default-customer.layouts.customer-account', client_layout('app', 'customer'));
    }

    public function test_client_layout_prefers_theme_layout_when_present(): void
    {
        $profile = $this->makeProfile([
            'slug' => 'haseeb-master',
            'active_frontend_theme' => 'v1-classic',
            'is_master_profile' => true,
        ]);

        $resolver = app(RuntimeViewResolver::class);

        $this->assertSame('themes.frontend.v1-classic.layouts.frontend', client_layout('frontend', 'frontend'));
        $this->assertSame('themes.frontend.v1-classic.layouts.auth', client_layout('auth', 'frontend'));
        $this->assertSame('themes.admin.default-admin.layouts.dashboard', client_layout('dashboard', 'admin'));
        $this->assertSame('themes.staff.default-staff.layouts.dashboard', client_layout('dashboard', 'staff'));
        $this->assertSame('themes.agent.default-agent.layouts.agent-portal', client_layout('agent-portal', 'agent'));
        $this->assertSame('themes.customer.default-customer.layouts.customer-account', client_layout('customer-account', 'customer'));

        $sample = $resolver->resolveLayoutSample('frontend', 'frontend', $profile);
        $this->assertFalse($sample['fallback_used']);
        $this->assertTrue($sample['theme_layout_exists']);
        $this->assertTrue($sample['layout_exists']);
    }

    public function test_client_layout_falls_back_to_legacy_layout_when_theme_missing(): void
    {
        $profile = $this->makeProfile([
            'slug' => 'haseeb-master',
            'active_frontend_theme' => 'v1-classic',
            'is_master_profile' => true,
        ]);

        $themeLayout = resource_path('views/themes/frontend/v1-classic/layouts/frontend.blade.php');
        $backup = File::get($themeLayout);
        File::delete($themeLayout);

        try {
            $resolver = app(RuntimeViewResolver::class);
            $this->assertSame('layouts.frontend', client_layout('frontend', 'frontend'));

            $sample = $resolver->resolveLayoutSample('frontend', 'frontend', $profile);
            $this->assertTrue($sample['fallback_used']);
            $this->assertFalse($sample['theme_layout_exists']);
            $this->assertTrue($sample['legacy_layout_exists']);
        } finally {
            File::ensureDirectoryExists(dirname($themeLayout));
            File::put($themeLayout, $backup);
        }
    }

    public function test_client_layout_exists_helper(): void
    {
        $this->makeProfile([
            'slug' => 'haseeb-master',
            'active_frontend_theme' => 'v1-classic',
            'is_master_profile' => true,
        ]);

        $this->assertTrue(client_layout_exists('frontend', 'frontend'));
        $this->assertFalse(client_layout_exists('definitely-missing-layout', 'frontend'));
    }

    public function test_summary_reports_theme_roots_without_requiring_theme_blades(): void
    {
        $profile = $this->makeProfile([
            'slug' => 'haseeb-master',
            'active_frontend_theme' => 'v1-classic',
            'active_admin_theme' => 'default-admin',
            'active_staff_theme' => 'default-staff',
            'is_master_profile' => true,
        ]);

        $summary = app(RuntimeViewResolver::class)->summary(null, $profile);

        $this->assertArrayHasKey('frontend', $summary);
        $this->assertSame('v1-classic', $summary['frontend']['resolved_theme']);
        $this->assertSame('resources/views/themes/frontend/v1-classic', $summary['frontend']['theme_view_root']);
        $this->assertSame('resources/views', $summary['frontend']['fallback_root']);
        $this->assertIsBool($summary['frontend']['theme_root_exists']);
        $this->assertStringContainsString('MC-8D', $summary['frontend']['note']);
    }

    public function test_first_returns_first_existing_view_name(): void
    {
        $profile = $this->makeProfile([
            'slug' => 'haseeb-master',
            'is_master_profile' => true,
        ]);

        $resolver = app(RuntimeViewResolver::class);

        $this->assertSame('frontend.home', $resolver->first(['missing-a', 'home'], 'frontend', $profile));
    }

    public function test_admin_dotted_logical_names_fall_back_to_dashboard_prefix(): void
    {
        $profile = $this->makeProfile([
            'slug' => 'haseeb-master',
            'active_admin_theme' => 'default-admin',
            'is_master_profile' => true,
        ]);

        $resolver = app(RuntimeViewResolver::class);

        $this->assertSame('dashboard.admin.bookings.show', $resolver->legacyViewName('bookings.show', 'admin'));
        $this->assertSame('dashboard.admin.bookings.show', $resolver->view('bookings.show', 'admin', $profile));
        $this->assertSame('auth.login', $resolver->legacyViewName('auth.login', 'frontend'));
    }

    public function test_jetpakistan_profile_resolves_agent_and_customer_portal_themes(): void
    {
        $profile = $this->makeProfile([
            'slug' => 'jetpk',
            'active_frontend_theme' => 'jetpakistan',
            'active_admin_theme' => 'jetpakistan',
            'active_staff_theme' => 'jetpakistan',
        ]);

        $resolver = app(RuntimeViewResolver::class);

        $this->assertSame('themes.agent.jetpakistan.index', $resolver->themeViewName('index', 'agent', $profile));
        $this->assertSame('themes.customer.jetpakistan.dashboard', $resolver->themeViewName('dashboard', 'customer', $profile));
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
