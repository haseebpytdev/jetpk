<?php

namespace Tests\Feature\Dashboard;

use App\Enums\AccountType;
use App\Models\Agency;
use App\Models\AgentApplication;
use App\Models\User;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\Support\PlatformAdminTestHelpers;
use Tests\TestCase;

/**
 * JP-DASH-03 checkpoint 11 — Agent admin action route/RBAC matrix (fixtures only).
 */
class JpDash03AgentActionMatrixTest extends TestCase
{
    use PlatformAdminTestHelpers;
    use RefreshDatabase;

    public function test_agent_action_routes_exist_with_expected_methods(): void
    {
        $expected = [
            'admin.agencies.prefix.update' => ['PATCH'],
            'admin.agent-applications.approve' => ['PATCH'],
            'admin.agent-applications.reject' => ['PATCH'],
            'admin.agent-applications.needs-more-info' => ['PATCH'],
            'admin.agencies.users.agent-permissions.update' => ['PATCH', 'PUT'],
            'admin.agencies.users.agent-permissions.apply-template' => ['POST'],
        ];

        foreach ($expected as $routeName => $allowedMethods) {
            $this->assertTrue(Route::has($routeName), "Missing route: {$routeName}");
            $route = Route::getRoutes()->getByName($routeName);
            $this->assertNotNull($route);
            $methods = array_diff($route->methods(), ['HEAD']);
            $this->assertNotEmpty(array_intersect($allowedMethods, $methods), "Unexpected methods for {$routeName}");
        }
    }

    public function test_staff_cannot_approve_agent_application(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $staff = User::query()->where('email', 'staff@ota.demo')->firstOrFail();
        $application = AgentApplication::query()->create([
            'first_name' => 'Staff',
            'last_name' => 'Gate',
            'email' => 'staff-gate@example.test',
            'mobile' => '+923001112233',
            'company_name' => 'Gate Travels',
            'business_type' => 'travel_agency',
            'city' => 'Lahore',
            'country' => 'Pakistan',
            'office_address' => 'Test',
            'expected_booking_volume' => '10',
            'status' => 'pending',
        ]);

        $this->actingAs($staff)
            ->patch(route('admin.agent-applications.approve', $application))
            ->assertForbidden();
    }

    public function test_platform_admin_agent_applications_index_redirects_to_next(): void
    {
        $admin = $this->platformAdmin();
        AgentApplication::query()->create([
            'first_name' => 'Matrix',
            'last_name' => 'Applicant',
            'email' => 'matrix-applicant@example.test',
            'mobile' => '+923001112233',
            'company_name' => 'Matrix Travels',
            'business_type' => 'travel_agency',
            'city' => 'Lahore',
            'country' => 'Pakistan',
            'office_address' => 'Test',
            'expected_booking_volume' => '10',
            'status' => 'pending',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.agent-applications.index'))
            ->assertRedirect('/admin/dashboard/agents/applications');
    }

    public function test_customer_cannot_access_agent_admin_routes(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $customer = User::query()->where('account_type', AccountType::Customer)->first();
        $this->assertNotNull($customer);

        $this->actingAs($customer)->get(route('admin.agent-applications.index'))->assertForbidden();
    }
}
