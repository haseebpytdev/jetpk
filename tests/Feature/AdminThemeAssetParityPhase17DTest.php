<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\PlatformAdminTestHelpers;
use Tests\TestCase;

class AdminThemeAssetParityPhase17DTest extends TestCase
{
    use PlatformAdminTestHelpers;
    use RefreshDatabase;

    public function test_admin_dashboard_includes_ops_console_stylesheets(): void
    {
        $admin = $this->platformAdmin();

        $html = $this->actingAs($admin)->get('/admin')->assertOk()->getContent();

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

        $html = $this->actingAs($admin)->get(route('admin.bookings'))->assertOk()->getContent();

        $this->assertStringContainsString('ota-admin-console.css', $html);
        $this->assertStringContainsString('tabler.min.css', $html);
    }
}
