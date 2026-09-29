<?php

namespace Tests\Feature\Dashboard;

use App\Enums\AccountType;
use App\Models\User;
use App\Support\BackOffice\BackOfficeCapabilitiesPresenter;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardManagementNavBridgeTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_admin_navigation_includes_laravel_management_bridges(): void
    {
        [$admin] = $this->platformAdmin();

        $payload = app(BackOfficeCapabilitiesPresenter::class)->present($admin, 'admin');
        $navigation = $payload['navigation'] ?? [];
        $byKey = [];
        foreach ($navigation as $item) {
            $byKey[$item['key']] = $item;
        }

        $expected = [
            'api-connections' => '/api-connections',
            'company-profile' => '/admin/settings/branding',
            'homepage-cms' => '/admin/settings/homepage',
            'cms-pages' => '/admin/cms-pages',
            'seo' => '/admin/seo',
            'customer-queries' => '/admin/customer-queries',
            'login-otp' => '/admin/settings/login-otp',
            'ai-assistant' => '/admin/settings/ai-assistant',
            'settings-hub' => '/admin/settings',
            'go-live' => '/admin/go-live-checklist',
            'staff' => '/admin/staff',
        ];

        foreach ($expected as $key => $href) {
            $this->assertArrayHasKey($key, $byKey, "Missing nav key {$key}");
            if ($key === 'api-connections') {
                $this->assertSame('dashboard', $byKey[$key]['target'] ?? null, "Nav {$key} must target dashboard Next");
            } else {
                $this->assertSame('laravel', $byKey[$key]['target'] ?? null, "Nav {$key} must target laravel (temporary Batch A until Next port)");
            }
            $this->assertSame($href, $byKey[$key]['href'] ?? null, "Nav {$key} href mismatch");
        }

        // Operational Next items remain dashboard-target.
        $this->assertSame('dashboard', $byKey['dashboard']['target'] ?? null);
        $this->assertSame('/', $byKey['dashboard']['href'] ?? null);
        $this->assertSame('dashboard', $byKey['bookings']['target'] ?? null);
        $this->assertSame('/bookings', $byKey['bookings']['href'] ?? null);

        // Admin must not be sent to the read-only Next settings stub as primary Settings.
        $this->assertArrayNotHasKey('settings', $byKey);
    }

    public function test_staff_navigation_keeps_next_settings_and_omits_admin_laravel_hubs(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $staff = User::query()->where('email', 'staff@ota.demo')->firstOrFail();

        $payload = app(BackOfficeCapabilitiesPresenter::class)->present($staff, 'staff');
        $navigation = $payload['navigation'] ?? [];
        $byKey = [];
        foreach ($navigation as $item) {
            $byKey[$item['key']] = $item;
        }

        $this->assertArrayNotHasKey('api-settings', $byKey);
        $this->assertArrayNotHasKey('settings-hub', $byKey);
        $this->assertArrayNotHasKey('company-profile', $byKey);
        $this->assertArrayNotHasKey('seo', $byKey);

        if (isset($byKey['settings'])) {
            $this->assertSame('dashboard', $byKey['settings']['target'] ?? null);
            $this->assertSame('/settings', $byKey['settings']['href'] ?? null);
        }
    }

    public function test_laravel_route_paths_reject_unknown_names_via_null(): void
    {
        $this->assertNull(
            \App\Support\BackOffice\BackOfficeLaravelRoutePaths::pathFor('admin.does-not-exist')
        );
        $this->assertSame(
            '/admin/api-settings',
            \App\Support\BackOffice\BackOfficeLaravelRoutePaths::pathFor('admin.api-settings')
        );
    }

    /**
     * @return array{0: User}
     */
    protected function platformAdmin(): array
    {
        $admin = User::query()->where('email', 'admin@ota.demo')->first();
        if ($admin === null) {
            $this->seed(OtaFoundationSeeder::class);
            $admin = User::query()->where('email', 'admin@ota.demo')->firstOrFail();
        }

        if ($admin->account_type !== AccountType::PlatformAdmin) {
            $admin->forceFill(['account_type' => AccountType::PlatformAdmin])->save();
            $admin = $admin->fresh();
        }

        return [$admin];
    }
}
