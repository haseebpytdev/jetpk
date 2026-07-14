<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AdminSettingsHubTest extends TestCase
{
    use RefreshDatabase;

    public function test_settings_hub_loads_for_agency_admin(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $admin = User::query()->where('email', 'admin@ota.demo')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('admin.settings.index'))
            ->assertOk()
            ->assertSee('Settings')
            ->assertSee('Branding &amp; company profile', false)
            ->assertSee('Promo codes')
            ->assertSee('Payment methods');
    }

    public function test_settings_hub_links_only_resolve_to_valid_routes(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $admin = User::query()->where('email', 'admin@ota.demo')->firstOrFail();

        $response = $this->actingAs($admin)->get(route('admin.settings.index'));
        $response->assertOk();

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
            $response->assertSee(route($routeName), false);
        }
    }

    public function test_payment_settings_page_loads_without_secrets(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $admin = User::query()->where('email', 'admin@ota.demo')->firstOrFail();

        $response = $this->actingAs($admin)->get(route('admin.settings.payments.index'));
        $response->assertOk()
            ->assertSee('Online payment gateways not enabled')
            ->assertSee('Bank transfer')
            ->assertSee('payment proof', false);

        $content = $response->getContent();
        $this->assertStringNotContainsString('smtp_password', strtolower($content));
        $this->assertStringNotContainsString('api_key', strtolower($content));
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
