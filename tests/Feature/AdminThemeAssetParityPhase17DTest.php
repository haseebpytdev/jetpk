<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\AdminLegacyViewTestHelpers;
use Tests\Support\PlatformAdminTestHelpers;
use Tests\TestCase;

class AdminThemeAssetParityPhase17DTest extends TestCase
{
    use AdminLegacyViewTestHelpers;
    use PlatformAdminTestHelpers;
    use RefreshDatabase;

    public function test_admin_dashboard_includes_ops_console_stylesheets(): void
    {
        $admin = $this->platformAdmin();

        $this->actingAs($admin)->get('/admin')->assertRedirect('/admin/dashboard');

        $html = $this->adminDashboardHtml($admin);

        $this->assertStringContainsString('ota-admin-console.css', $html);
        $this->assertStringContainsString('ota-design-system.css', $html);
        $this->assertStringContainsString('tabler.min.css', $html);
        $this->assertStringContainsString('tabler-icons', $html);
        $this->assertStringContainsString('ota-admin-console', $html);
        $this->assertStringContainsString('data-testid="ota-dash-overview"', $html);
    }

    public function test_admin_bookings_page_includes_ops_console_stylesheets(): void
    {
        $admin = $this->platformAdmin();

        $html = $this->adminBookingsIndexHtml($admin);

        $this->assertStringContainsString('ota-admin-console.css', $html);
        $this->assertStringContainsString('tabler.min.css', $html);
    }
}
