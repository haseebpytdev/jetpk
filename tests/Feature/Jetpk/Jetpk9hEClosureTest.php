<?php

namespace Tests\Feature\Jetpk;

use App\Models\Agency;
use App\Models\ClientProfile;
use App\Models\User;
use App\Services\Client\CurrentClientContext;
use App\Support\Branding\JetpkBrandPaletteCssResolver;
use App\Support\Emails\EmailBaseVariables;
use App\Support\Emails\EmailPlaceholderFallbacks;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\Support\PlatformAdminTestHelpers;
use Tests\TestCase;

/**
 * JetPK 9H-E closure — customization coverage, soft palette, email leakage, blocked routes.
 */
class Jetpk9hEClosureTest extends TestCase
{
    use PlatformAdminTestHelpers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedJetpkProfile();
    }

    public function test_destination_media_upload_control_renders_in_page_settings(): void
    {
        $admin = $this->platformAdmin();
        $this->actingAs($admin)
            ->get(route('admin.page-settings.edit', ['pageKey' => 'home']))
            ->assertOk()
            ->assertSee('destination_1', false)
            ->assertSee('Choose image', false);
    }

    public function test_group_card_media_upload_control_renders(): void
    {
        $admin = $this->platformAdmin();
        $this->actingAs($admin)
            ->get(route('admin.page-settings.edit', ['pageKey' => 'home']))
            ->assertOk()
            ->assertSee('group_card_1', false)
            ->assertSee('data-jp-section-panel="group-cards"', false);
    }

    public function test_why_travellers_and_trust_card_editors_render(): void
    {
        $admin = $this->platformAdmin();
        $this->actingAs($admin)
            ->get(route('admin.page-settings.edit', ['pageKey' => 'home']))
            ->assertOk()
            ->assertSee('data-jp-section-panel="trust"', false)
            ->assertSee('data-jp-section-panel="why-book"', false)
            ->assertSee('name="content[trust][cards][0][title]"', false)
            ->assertSee('name="content[why_book][cards][0][title]"', false);
    }

    public function test_support_cta_media_keys_documented_in_editor(): void
    {
        $admin = $this->platformAdmin();
        $this->actingAs($admin)
            ->get(route('admin.page-settings.edit', ['pageKey' => 'home']))
            ->assertOk()
            ->assertSee('support_cta_background', false)
            ->assertSee('support_cta_background_mobile', false);
    }

    public function test_primary_soft_gradient_variables_exist(): void
    {
        $vars = app(JetpkBrandPaletteCssResolver::class)->variablesFromHex('#16A34A', '#15803D', '#0D9488');
        foreach ([
            '--jp-color-primary-soft',
            '--jp-gradient-primary',
            '--jp-button-shadow',
            '--jp-focus-ring',
        ] as $key) {
            $this->assertArrayHasKey($key, $vars);
            $this->assertNotSame('', $vars[$key]);
        }
        $this->assertStringContainsString('linear-gradient', $vars['--jp-gradient-primary']);
    }

    public function test_dashboard_css_uses_soft_primary_button_classes(): void
    {
        $css = (string) file_get_contents(public_path('themes/admin/jetpakistan/css/dashboard.css'));
        $this->assertStringContainsString('var(--jp-gradient-primary', $css);
        $this->assertStringContainsString('[data-theme="day"] .jp-btn--primary', $css);
        $this->assertStringContainsString('[data-theme="night"] .jp-btn--primary', $css);
    }

    public function test_provider_picker_uses_canonical_button_classes(): void
    {
        $admin = $this->platformAdmin();
        $this->actingAs($admin)
            ->get(route('admin.api-settings.create'))
            ->assertOk()
            ->assertSee('jp-btn--primary', false)
            ->assertSee('jp-provider-card', false);
    }

    public function test_template_reset_route_exists(): void
    {
        $this->assertTrue(Route::has('admin.settings.communications.templates.reset'));
        $routes = collect(Route::getRoutes())->filter(
            fn ($r) => $r->getName() === 'admin.settings.communications.templates.reset',
        );
        $this->assertCount(1, $routes);
        $this->assertContains('DELETE', $routes->first()->methods());
    }

    public function test_jetpk_email_fallback_never_returns_parwaaz(): void
    {
        $agency = $this->platformAdmin()->currentAgency ?? Agency::factory()->create();
        $vars = EmailBaseVariables::forContext($agency);
        $this->assertStringNotContainsString('Parwaaz', (string) ($vars['brand_name'] ?? ''));
        $fallback = EmailPlaceholderFallbacks::fallbackFor('brand_name', ['audience' => 'customer']);
        $this->assertIsString($fallback);
        $this->assertStringNotContainsString('Parwaaz', $fallback);
    }

    public function test_raw_brand_name_placeholder_not_in_resolved_variables(): void
    {
        $agency = $this->platformAdmin()->currentAgency ?? Agency::factory()->create();
        $vars = EmailBaseVariables::forContext($agency);
        foreach (['brand_name', 'agency_name', 'company_name'] as $key) {
            $this->assertNotSame('{{ brand_name }}', (string) ($vars[$key] ?? ''));
            $this->assertStringNotContainsString('{{', (string) ($vars[$key] ?? ''));
        }
    }

    public function test_email_source_files_contain_no_parwaaz_constant(): void
    {
        foreach ([
            app_path('Support/Emails/EmailBaseVariables.php'),
            app_path('Support/Emails/EmailPlaceholderFallbacks.php'),
        ] as $file) {
            $this->assertStringNotContainsString('Parwaaz', (string) file_get_contents($file));
        }
    }

    public function test_homepage_editor_uses_canonical_jp_control_fields(): void
    {
        $admin = $this->platformAdmin();
        $html = $this->actingAs($admin)
            ->get(route('admin.page-settings.edit', ['pageKey' => 'home']))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('jp-control', (string) $html);
        $this->assertStringContainsString('jp-field__label', (string) $html);
        $this->assertStringNotContainsString('class="form-control"', (string) $html);
    }

    public function test_featured_deals_panel_renders_in_page_settings(): void
    {
        $admin = $this->platformAdmin();
        $this->actingAs($admin)
            ->get(route('admin.page-settings.edit', ['pageKey' => 'home']))
            ->assertOk()
            ->assertSee('data-jp-section-panel="featured-deals"', false)
            ->assertSee('featured_deals', false);
    }

    public function test_deep_page_inventory_has_zero_blocked_routes(): void
    {
        $result = app(\App\Support\Audits\JetpkAdminDeepPageInventoryAuditService::class)->run('jetpk');
        $this->assertSame(0, (int) ($result['summary']['blocked'] ?? -1));
    }

    private function seedJetpkProfile(): void
    {
        $profile = ClientProfile::query()->firstOrCreate(
            ['slug' => 'jetpk'],
            [
                'name' => 'Jet Pakistan',
                'environment' => 'staging',
                'active_frontend_theme' => 'jetpakistan',
                'active_admin_theme' => 'jetpakistan',
                'active_staff_theme' => 'jetpakistan',
                'asset_profile' => 'jetpk-assets',
                'default_locale' => 'en',
                'timezone' => 'Asia/Karachi',
                'currency' => 'PKR',
                'is_master_profile' => false,
                'is_active' => true,
            ],
        );
        app(CurrentClientContext::class)->set($profile);
        config(['ota.default_client_slug' => 'jetpk']);
    }
}
