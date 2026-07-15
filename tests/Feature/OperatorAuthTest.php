<?php

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Models\User;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperatorAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_admin_and_is_redirected_to_login(): void
    {
        $this->get('/admin')->assertRedirect(route('login'));
    }

    public function test_agency_admin_can_login_and_access_admin(): void
    {
        $this->seed(OtaFoundationSeeder::class);

        $this->withoutMiddleware([ValidateCsrfToken::class]);

        $response = $this->post('/login', [
            'email' => 'admin@ota.demo',
            'password' => 'password',
        ]);

        $response->assertRedirect(route('admin.dashboard', absolute: false));
        $this->get('/admin')->assertOk();
    }

    public function test_staff_login_redirects_to_staff_dashboard(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $this->withoutMiddleware([ValidateCsrfToken::class]);

        $this->post('/login', [
            'email' => 'staff@ota.demo',
            'password' => 'password',
        ])->assertRedirect(route('staff.dashboard', absolute: false));
    }

    public function test_agent_login_redirects_to_agent_dashboard(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $this->withoutMiddleware([ValidateCsrfToken::class]);

        $this->post('/login', [
            'email' => 'agent@ota.demo',
            'password' => 'password',
        ])->assertRedirect(route('agent.dashboard', absolute: false));
    }

    public function test_customer_login_redirects_to_customer_dashboard(): void
    {
        User::factory()->create([
            'email' => 'customer@example.test',
            'password' => 'password',
            'account_type' => AccountType::Customer,
        ]);

        $this->withoutMiddleware([ValidateCsrfToken::class]);

        $this->post('/login', [
            'email' => 'customer@example.test',
            'password' => 'password',
        ])->assertRedirect(route('customer.dashboard', absolute: false));
    }

    public function test_staff_cannot_access_admin(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $staff = User::query()->where('email', 'staff@ota.demo')->firstOrFail();

        $this->actingAs($staff);
        $this->get('/admin')->assertForbidden();
    }

    public function test_staff_can_access_staff_dashboard(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $staff = User::query()->where('email', 'staff@ota.demo')->firstOrFail();

        $this->actingAs($staff);
        $this->get('/staff')->assertOk();
    }

    public function test_agent_can_access_agent_dashboard(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $agent = User::query()->where('email', 'agent@ota.demo')->firstOrFail();

        $this->actingAs($agent);
        $this->get('/agent')->assertOk();
    }

    public function test_agent_cannot_access_admin(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $agent = User::query()->where('email', 'agent@ota.demo')->firstOrFail();

        $this->actingAs($agent);
        $this->get('/admin')->assertForbidden();
    }

    public function test_user_without_agency_context_gets_403(): void
    {
        $user = User::factory()->staff()->create([
            'current_agency_id' => null,
        ]);

        $response = $this->actingAs($user)->get('/staff');
        $response->assertForbidden();
        $response->assertSee('No agency context assigned.', false);
    }

    public function test_ensure_agency_context_sets_current_agency_id_when_pivot_exists(): void
    {
        $this->seed(OtaFoundationSeeder::class);

        $staff = User::query()->where('email', 'staff@ota.demo')->firstOrFail();
        $agencyId = $staff->current_agency_id;

        $staff->forceFill(['current_agency_id' => null])->save();
        $staff->refresh();
        $this->assertNull($staff->current_agency_id);

        $this->actingAs($staff);
        $this->get('/staff')->assertOk();

        $staff->refresh();
        $this->assertSame($agencyId, $staff->current_agency_id);
    }

    public function test_public_homepage_accessible_without_login(): void
    {
        $this->get('/')->assertOk();
    }

    public function test_public_flight_results_accessible_without_login(): void
    {
        $depart = now()->addWeek()->format('Y-m-d');
        $this->get('/flights/results?from=LHE&to=DXB&depart='.$depart.'&trip_type=one_way&cabin=economy&adults=1&children=0&infants=0')->assertOk();
    }

    public function test_platform_admin_can_access_admin_without_agency_membership(): void
    {
        $user = User::factory()->create([
            'account_type' => AccountType::PlatformAdmin,
            'current_agency_id' => null,
        ]);

        $this->actingAs($user);
        $this->get('/admin')->assertOk();
    }
}
