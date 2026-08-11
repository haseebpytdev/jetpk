<?php

namespace Tests\Feature\BackOffice;

use App\Models\User;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\PlatformAdminTestHelpers;
use Tests\TestCase;

class BackOfficeLegacyCustomerRedirectTest extends TestCase
{
    use PlatformAdminTestHelpers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
    }

    public function test_legacy_admin_customers_index_redirects_to_next_dashboard(): void
    {
        $admin = $this->platformAdmin();

        $this->actingAs($admin)
            ->get('/admin/customers?search=ayesha')
            ->assertRedirect('/admin/dashboard/customers?q=ayesha');
    }

    public function test_legacy_admin_customer_show_redirects_to_next_drawer_query(): void
    {
        $customer = User::query()->where('email', 'customer@ota.demo')->firstOrFail();
        $admin = $this->platformAdmin();

        $this->actingAs($admin)
            ->get(route('admin.customers.show', $customer))
            ->assertRedirect('/admin/dashboard/customers?id=CU-'.$customer->id);
    }

    public function test_legacy_admin_customer_show_rejects_non_customer_users(): void
    {
        $staff = User::query()->where('email', 'staff@ota.demo')->firstOrFail();
        $admin = $this->platformAdmin();

        $this->actingAs($admin)
            ->get('/admin/customers/'.$staff->id)
            ->assertNotFound();
    }

    public function test_guest_customer_show_route_remains_on_laravel(): void
    {
        $admin = $this->platformAdmin();

        $this->actingAs($admin)
            ->get('/admin/customers/guests/show?email=guest@example.com')
            ->assertOk();
    }
}
