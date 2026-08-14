<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\Support\PlatformAdminTestHelpers;
use Tests\TestCase;

class AdminSettingsHubTest extends TestCase
{
    use PlatformAdminTestHelpers;
    use RefreshDatabase;

    public function test_settings_hub_redirects_platform_admin_to_dashboard_settings(): void
    {
        $admin = $this->platformAdmin();

        $this->actingAs($admin)
            ->get(route('admin.settings.index'))
            ->assertRedirect('/admin/dashboard/settings');
    }

    public function test_legacy_agency_admin_cannot_access_settings_hub(): void
    {
        $legacy = $this->legacyAgencyAdminFromSeed();

        $this->actingAs($legacy)
            ->get(route('admin.settings.index'))
            ->assertForbidden();
    }

    public function test_settings_named_routes_remain_registered(): void
    {
        $expectedRoutes = [
            'admin.settings.branding.edit',
            'admin.settings.branding.footer.edit',
            'admin.settings.homepage.edit',
            'admin.settings.media.index',
            'admin.api-settings',
            'admin.settings.communications.index',
            'admin.settings.communications.templates.index',
            'admin.settings.communications.notification-events.index',
            'admin.settings.payments.index',
            'admin.markups',
            'admin.promo-codes.index',
            'admin.support.tickets.index',
        ];

        foreach ($expectedRoutes as $routeName) {
            $this->assertTrue(Route::has($routeName), "Missing route: {$routeName}");
        }
    }

    public function test_payment_settings_page_redirects_without_exposing_named_secret_routes_to_guests(): void
    {
        $admin = $this->platformAdmin();

        $this->actingAs($admin)
            ->get(route('admin.settings.payments.index'))
            ->assertRedirect('/admin/dashboard/settings');
    }

    public function test_staff_cannot_access_settings_hub(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $staff = User::query()->where('email', 'staff@ota.demo')->firstOrFail();

        $this->actingAs($staff)->get(route('admin.settings.index'))->assertForbidden();
        $this->actingAs($staff)->get(route('admin.settings.payments.index'))->assertForbidden();
    }

    public function test_customer_cannot_access_settings_hub(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $customer = User::query()->where('email', 'customer@ota.demo')->firstOrFail();

        $this->actingAs($customer)->get(route('admin.settings.index'))->assertForbidden();
    }
}
