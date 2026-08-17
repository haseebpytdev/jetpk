<?php

namespace Tests\Feature;

use App\Models\ClientProfile;
use App\Services\Client\CurrentClientContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\Support\AdminLegacyViewTestHelpers;
use Tests\Support\PlatformAdminTestHelpers;
use Tests\TestCase;

class JetPakistanAdminDesignSystemParityPhase17DTest extends TestCase
{
    use AdminLegacyViewTestHelpers;
    use PlatformAdminTestHelpers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedJetpkSingleClientContext();
    }

    public function test_admin_root_redirects_to_next_dashboard(): void
    {
        $admin = $this->platformAdmin();

        $this->actingAs($admin)->get('/admin')->assertRedirect('/admin/dashboard');
    }

    public function test_admin_dashboard_blade_overview_superseded_by_next_cutover(): void
    {
        $this->markTestSkipped(
            'Next dashboard at /admin/dashboard is the canonical admin overview; Blade shell assertions at /admin root are obsolete after cutover.',
        );
    }

    public function test_jetpakistan_admin_layout_includes_override_stylesheet_when_theme_shell_is_active(): void
    {
        $this->markTestSkipped(
            'JetPakistan admin overview is served by Next dashboard; Blade layout stylesheet checks apply to module pages only.',
        );
    }

    public function test_admin_bookings_and_customers_share_ops_console_stylesheets(): void
    {
        $admin = $this->platformAdmin();

        $this->assertLegacyAdminBookingsIndexRedirect($admin);
        $this->assertLegacyAdminCustomersIndexRedirect($admin);
        $bookings = $this->adminBookingsIndexHtml($admin);
        $customers = $this->adminCustomersIndexHtml($admin);

        foreach ([$bookings, $customers] as $html) {
            $this->assertStringContainsString('ota-admin-console.css', $html);
            $this->assertStringContainsString('ota-design-system.css', $html);
            $this->assertStringContainsString('ota-admin-console', $html);
        }
    }

    protected function seedJetpkSingleClientContext(): void
    {
        Config::set([
            'ota_client.slug' => 'jetpk',
            'ota_client.single_client_mode' => true,
            'ota_client.single_client_root' => true,
        ]);

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
    }
}
