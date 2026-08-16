<?php

namespace Tests\Feature\Jetpk;

use App\Enums\OtaNotificationEvent;
use App\Http\Controllers\Admin\SupplierConnectionController;
use App\Models\ClientProfile;
use App\Models\User;
use App\Services\Client\CurrentClientContext;
use App\Support\Client\ClientPageMediaSchema;
use App\Support\Emails\EmailTemplateRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use ReflectionClass;
use Tests\Support\AdminLegacyViewTestHelpers;
use Tests\Support\PlatformAdminTestHelpers;
use Tests\TestCase;

/**
 * JetPK 9H-D closure requirement matrix — maps phase behaviors to PHPUnit coverage.
 */
class Jetpk9hDClosureTest extends TestCase
{
    use AdminLegacyViewTestHelpers;
    use PlatformAdminTestHelpers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedJetpkProfile();
    }

    public function test_settings_hub_lists_required_cards_including_background_removal(): void
    {
        $admin = $this->platformAdmin();

        $this->actingAs($admin)
            ->get(route('admin.settings.index'))
            ->assertRedirect('/admin/dashboard/settings');

        $html = $this->adminSettingsIndexHtml($admin);
        foreach ([
            'Company branding',
            'Page settings',
            'Media library',
            'Background removal',
            'Email templates',
            'Notification routing',
            'Supplier API connections',
            'Supplier diagnostics',
        ] as $label) {
            $this->assertStringContainsString($label, $html);
        }
        $this->assertTrue(Route::has('admin.settings.background-removal.edit'));
    }

    public function test_background_removal_route_accessible_to_platform_admin(): void
    {
        $admin = $this->platformAdmin();
        $this->actingAs($admin)
            ->get(route('admin.settings.background-removal.edit'))
            ->assertRedirect('/admin/dashboard/settings/general');

        $html = $this->adminBackgroundRemovalEditHtml($admin);
        $this->assertNotSame('', trim($html));
    }

    public function test_unauthorized_user_denied_settings_hub(): void
    {
        $user = User::factory()->create(['account_type' => 'customer']);
        $this->actingAs($user)->get(route('admin.settings.index'))->assertForbidden();
    }

    public function test_homepage_media_schema_covers_required_asset_keys(): void
    {
        $keys = ClientPageMediaSchema::assetKeysFor('home');
        foreach (['group_card_1', 'destination_1', 'support_cta_background', 'support_cta_background_mobile'] as $required) {
            $this->assertContains($required, $keys);
        }
    }

    public function test_page_settings_home_editor_includes_feature_board_trust_routes_panels(): void
    {
        $admin = $this->platformAdmin();
        $this->actingAs($admin)
            ->get(route('admin.page-settings.edit', ['pageKey' => 'home']))
            ->assertRedirect('/admin/dashboard/cms/pages');

        $html = $this->adminPageSettingsEditHtml($admin, 'home');
        $this->assertStringContainsString('data-jp-section-panel="feature-board"', $html);
        $this->assertStringContainsString('data-jp-section-panel="trust"', $html);
        $this->assertStringContainsString('data-jp-section-panel="routes"', $html);
        $this->assertStringContainsString('Choose from Media Library', $html);
    }

    public function test_legacy_homepage_settings_redirects_to_page_settings(): void
    {
        $admin = $this->platformAdmin();
        $this->actingAs($admin)
            ->get(route('admin.settings.homepage.edit'))
            ->assertRedirect('/admin/dashboard/cms/pages');
    }

    public function test_supplier_create_renders_provider_picker_first(): void
    {
        $admin = $this->platformAdmin();
        $this->actingAs($admin)
            ->get(route('admin.api-settings.create'))
            ->assertRedirect('/admin/dashboard/api-connections');

        $html = $this->adminApiSettingsCreateHtml($admin);
        $this->assertStringContainsString('Sabre', $html);
        $this->assertStringContainsString('AirSial', $html);
        $this->assertStringContainsString('Al-Haider', $html);
        $this->assertStringContainsString('Duffel', $html);
    }

    public function test_supplier_provider_catalog_includes_required_cards(): void
    {
        $controller = new ReflectionClass(SupplierConnectionController::class);
        $method = $controller->getMethod('providerCards');
        $method->setAccessible(true);
        $instance = app(SupplierConnectionController::class);
        $cards = $method->invoke($instance, []);
        $keys = array_column($cards, 'key');
        foreach (['sabre', 'pia_ndc', 'airblue', 'iati', 'duffel', 'airsial', 'al_haider'] as $required) {
            $this->assertContains($required, $keys);
        }
    }

    public function test_notification_routing_renders_grouped_searchable_ui(): void
    {
        $admin = $this->platformAdmin();
        $this->actingAs($admin)
            ->get(route('admin.settings.communications.notification-events.index'))
            ->assertRedirect('/admin/dashboard/settings/notifications');

        $html = $this->adminNotificationEventsHtml($admin);
        $this->assertStringContainsString('data-jp-notification-search', $html);
        $this->assertStringContainsString('data-jp-unsaved-badge', $html);
        $this->assertStringContainsString('Save all routing', $html);
        $this->assertGreaterThan(0, count(OtaNotificationEvent::cases()));
    }

    public function test_universal_jetpk_email_shell_exists_without_parwaaz(): void
    {
        $shell = 'emails.themes.jetpakistan.layouts.base';
        $this->assertTrue(View::exists($shell));
        $path = resource_path('views/emails/themes/jetpakistan/layouts/base.blade.php');
        $this->assertFileExists($path);
        $this->assertStringNotContainsString('Parwaaz', (string) file_get_contents($path));
    }

    public function test_email_registry_renders_redesigned_grouped_ui(): void
    {
        Mail::fake();
        $admin = $this->platformAdmin();
        $this->actingAs($admin)
            ->get(route('admin.settings.communications.templates.index'))
            ->assertRedirect('/admin/dashboard/settings/notifications');

        $html = $this->adminEmailTemplatesIndexHtml($admin);
        $this->assertStringContainsString('data-testid="email-event-content-list"', $html);
        $this->assertStringContainsString('Customize content', $html);
        $this->assertStringNotContainsString('Create editable template', $html);
        Mail::assertNothingSent();
    }

    public function test_email_template_registry_has_entries_for_events(): void
    {
        $agency = $this->platformAdmin()->currentAgency;
        $rows = EmailTemplateRegistry::listForAgency($agency, []);
        $this->assertGreaterThan(count(OtaNotificationEvent::cases()), count($rows));
    }

    public function test_deep_admin_routes_render_without_server_error(): void
    {
        $admin = $this->platformAdmin();
        $routes = [
            'admin.settings.payments.index' => '/admin/dashboard/settings',
            'admin.promo-codes.index' => '/admin/dashboard/cms',
            'admin.markups' => '/admin/dashboard/markups',
            'admin.cms-pages.index' => '/admin/dashboard/cms/pages',
            'admin.users.index' => '/admin/dashboard/users',
        ];
        foreach ($routes as $routeName => $expectedPath) {
            if (! Route::has($routeName)) {
                continue;
            }
            $this->actingAs($admin)
                ->get(route($routeName))
                ->assertRedirect($expectedPath);
        }
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
