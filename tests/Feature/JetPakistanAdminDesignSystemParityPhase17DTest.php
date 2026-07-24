<?php

namespace Tests\Feature;

use App\Models\ClientProfile;
use App\Services\Client\CurrentClientContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\Support\PlatformAdminTestHelpers;
use Tests\TestCase;

class JetPakistanAdminDesignSystemParityPhase17DTest extends TestCase
{
    use PlatformAdminTestHelpers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedJetpkSingleClientContext();
    }

    public function test_admin_dashboard_includes_canonical_ops_console_stylesheets(): void
    {
        $admin = $this->platformAdmin();
        $html = $this->actingAs($admin)->get('/admin')->assertOk()->getContent();

        $this->assertStringContainsString('ota-design-system.css', $html);
        $this->assertStringContainsString('ota-admin-console.css', $html);
        $this->assertStringContainsString('data-testid="ota-dash-overview"', $html);
    }

    public function test_jetpakistan_admin_layout_includes_override_stylesheet_when_theme_shell_is_active(): void
    {
        $admin = $this->platformAdmin();
        $html = $this->actingAs($admin)->get('/admin')->assertOk()->getContent();

        if (! str_contains($html, 'jp-dash-body')) {
            $this->markTestSkipped('JetPakistan admin shell not active in this test runtime.');
        }

        $this->assertStringContainsString('jp-admin-ops-overrides.css', $html);
        $this->assertStringContainsString('dashboard.css', $html);
    }

    public function test_admin_bookings_and_customers_share_ops_console_stylesheets(): void
    {
        $admin = $this->platformAdmin();

        $bookings = $this->actingAs($admin)->get(route('admin.bookings'))->assertOk()->getContent();
        $customers = $this->actingAs($admin)->get(route('admin.customers.index'))->assertOk()->getContent();

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
