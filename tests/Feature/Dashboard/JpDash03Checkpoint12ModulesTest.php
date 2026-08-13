<?php

namespace Tests\Feature\Dashboard;

use App\Enums\AccountType;
use App\Enums\SupplierConnectionStatus;
use App\Enums\SupplierEnvironment;
use App\Enums\SupplierProvider;
use App\Models\Agency;
use App\Models\SupplierConnection;
use App\Models\User;
use App\Support\Suppliers\SabreSupplierChannelConfig;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\Support\PlatformAdminTestHelpers;
use Tests\TestCase;

/**
 * JP-DASH-03 checkpoint 12 — Settings, API settings, staff, suppliers module closure (fixtures).
 * Legacy Blade GETs redirect to Next; mutations/API remain Laravel-owned.
 */
class JpDash03Checkpoint12ModulesTest extends TestCase
{
    use PlatformAdminTestHelpers;
    use RefreshDatabase;

    public function test_settings_module_admin_redirects_staff_denied(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $staff = User::query()->where('email', 'staff@ota.demo')->firstOrFail();

        $this->actingAs($this->platformAdmin())
            ->get(route('admin.settings.index'))
            ->assertRedirect('/admin/dashboard/settings');

        $this->actingAs($staff)->get(route('admin.settings.index'))->assertForbidden();
    }

    public function test_api_settings_admin_redirects_staff_denied(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $admin = $this->platformAdmin();
        $staff = User::query()->where('email', 'staff@ota.demo')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('admin.api-settings'))
            ->assertRedirect('/admin/dashboard/settings/integrations');

        $this->actingAs($staff)->get(route('admin.api-settings'))->assertForbidden();
    }

    public function test_api_settings_edit_form_redirects_to_next_integrations(): void
    {
        $admin = $this->platformAdmin();
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $connection = SupplierConnection::factory()->create([
            'agency_id' => $agency->id,
            'provider' => SupplierProvider::Travelport,
            'name' => 'JP Dash 03 Edit Target',
            'credentials' => ['client_id' => 'tp_ci', 'client_secret' => 'tp_cs'],
        ]);

        $this->actingAs($admin)
            ->get(route('admin.api-settings.edit', $connection))
            ->assertRedirect('/admin/dashboard/settings/integrations');
    }

    public function test_staff_management_module_routes_and_rbac(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $admin = $this->platformAdmin();
        $customer = User::query()->where('account_type', AccountType::Customer)->first();
        $this->assertNotNull($customer);

        $this->assertTrue(Route::has('admin.staff'));
        $this->actingAs($admin)
            ->get(route('admin.staff'))
            ->assertRedirect('/admin/dashboard/staff');
        $this->actingAs($customer)->get(route('admin.staff'))->assertForbidden();
    }

    public function test_suppliers_dashboard_api_returns_operational_providers(): void
    {
        $admin = $this->platformAdmin();
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        SupplierConnection::query()->create([
            'agency_id' => $agency->id,
            'provider' => SupplierProvider::Sabre,
            'name' => 'Sabre JP Dash 03',
            'display_name' => 'Sabre JP Dash 03',
            'environment' => SupplierEnvironment::Sandbox,
            'status' => SupplierConnectionStatus::Active,
            'credentials' => ['username' => 'test', 'password' => 'secret', 'pcc' => 'ABC1', 'lniata' => 'LNIATA1'],
            'is_active' => true,
            'settings' => SabreSupplierChannelConfig::mergeIntoSettings([], true, true),
        ]);

        $response = $this->actingAs($admin)
            ->getJson(route('api.dashboard.suppliers.index'))
            ->assertOk();

        $suppliers = collect($response->json('data.suppliers') ?? []);
        $this->assertTrue($suppliers->isNotEmpty());

        $names = $suppliers->pluck('supplierName')->map(fn ($n) => strtolower((string) $n));
        $this->assertTrue($names->contains(fn ($n) => str_contains($n, 'sabre')));
    }
}
