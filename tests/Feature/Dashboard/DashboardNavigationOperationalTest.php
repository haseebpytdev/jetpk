<?php

namespace Tests\Feature\Dashboard;

use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\PlatformAdminTestHelpers;
use Tests\TestCase;

class DashboardNavigationOperationalTest extends TestCase
{
    use PlatformAdminTestHelpers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
    }

    public function test_admin_navigation_includes_operational_modules_with_targets(): void
    {
        $admin = $this->platformAdmin();

        $response = $this->actingAs($admin)
            ->getJson(route('api.dashboard.session', ['portal' => 'admin']))
            ->assertOk();

        $navigation = $response->json('data.navigation');
        $this->assertIsArray($navigation);

        $keys = collect($navigation)->pluck('key')->all();
        $this->assertContains('dashboard', $keys);
        $this->assertContains('bookings', $keys);
        $this->assertContains('cms', $keys);
        $this->assertContains('page-settings', $keys);
        $this->assertContains('support', $keys);

        $pageSettings = collect($navigation)->firstWhere('key', 'page-settings');
        $this->assertSame('laravel', $pageSettings['target'] ?? null);
        $this->assertStringContainsString('/admin/page-settings', $pageSettings['href'] ?? '');

        $support = collect($navigation)->firstWhere('key', 'support');
        $this->assertSame('laravel', $support['target'] ?? null);
        $this->assertStringContainsString('/admin/support/tickets', $support['href'] ?? '');
    }

    public function test_staff_navigation_omits_admin_only_laravel_modules(): void
    {
        $staff = \App\Models\User::query()->where('email', 'staff@ota.demo')->firstOrFail();

        $response = $this->actingAs($staff)
            ->getJson(route('api.dashboard.session', ['portal' => 'staff']))
            ->assertOk();

        $keys = collect($response->json('data.navigation'))->pluck('key')->all();
        $this->assertNotContains('staff', $keys);
        $this->assertNotContains('markups', $keys);
        $this->assertNotContains('page-settings', $keys);
        $this->assertNotContains('go-live', $keys);
    }
}
