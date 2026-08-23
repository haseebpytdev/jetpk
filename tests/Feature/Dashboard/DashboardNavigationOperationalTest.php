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
        $this->assertContains('homepage', $keys);
        $this->assertContains('integrations', $keys);
        $this->assertContains('support', $keys);
        $this->assertNotContains('cms', $keys);
        $this->assertNotContains('api-settings', $keys);

        $homepage = collect($navigation)->firstWhere('key', 'homepage');
        $this->assertSame('dashboard', $homepage['target'] ?? null);
        $this->assertStringContainsString('/cms/sections', $homepage['href'] ?? '');

        $support = collect($navigation)->firstWhere('key', 'support');
        $this->assertSame('dashboard', $support['target'] ?? null);
        $this->assertStringContainsString('/support', $support['href'] ?? '');
    }

    public function test_laravel_navigation_hrefs_are_public_relative_paths(): void
    {
        $admin = $this->platformAdmin();

        $response = $this->actingAs($admin)
            ->getJson(route('api.dashboard.session', ['portal' => 'admin']))
            ->assertOk();

        $navigation = $response->json('data.navigation');
        $this->assertIsArray($navigation);

        foreach ($navigation as $item) {
            if (($item['target'] ?? '') !== 'laravel') {
                continue;
            }

            $href = (string) ($item['href'] ?? '');
            $this->assertStringStartsWith('/', $href, "Laravel nav href must be relative: {$href}");
            $this->assertStringNotContainsString('127.0.0.1', $href);
            $this->assertStringNotContainsString('localhost', strtolower($href));
            $this->assertStringNotContainsString(':8088', $href);
        }

        $staff = collect($navigation)->firstWhere('key', 'staff');
        $this->assertNotNull($staff);
        $this->assertSame('/staff', $staff['href']);
        $this->assertSame('dashboard', $staff['target']);

        $this->assertNull(collect($navigation)->firstWhere('key', 'api-settings'));
        $integrations = collect($navigation)->firstWhere('key', 'integrations');
        $this->assertNotNull($integrations);
        $this->assertSame('/integrations', $integrations['href']);
        $this->assertSame('dashboard', $integrations['target']);
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
