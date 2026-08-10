<?php

namespace Tests\Feature\Dashboard;

use App\Models\User;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardStaffRbacTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
    }

    public function test_staff_can_access_dashboard_session_and_overview(): void
    {
        $staff = User::query()->where('email', 'staff@ota.demo')->firstOrFail();

        $this->actingAs($staff)
            ->getJson(route('api.dashboard.session', ['portal' => 'staff']))
            ->assertOk()
            ->assertJsonPath('data.platformRole', 'staff');

        $this->actingAs($staff)
            ->getJson(route('api.dashboard.overview'))
            ->assertOk();
    }

    public function test_staff_cannot_access_admin_portal_session(): void
    {
        $staff = User::query()->where('email', 'staff@ota.demo')->firstOrFail();

        $this->actingAs($staff)
            ->getJson(route('api.dashboard.session', ['portal' => 'admin']))
            ->assertForbidden();
    }

    public function test_staff_search_is_scoped_and_customer_denied(): void
    {
        $staff = User::query()->where('email', 'staff@ota.demo')->firstOrFail();
        $customer = User::factory()->create([
            'account_type' => \App\Enums\AccountType::Customer,
        ]);

        $this->actingAs($staff)
            ->getJson(route('api.dashboard.search', ['q' => 'test']))
            ->assertOk();

        $this->actingAs($customer)
            ->getJson(route('api.dashboard.search', ['q' => 'test']))
            ->assertForbidden();
    }
}
