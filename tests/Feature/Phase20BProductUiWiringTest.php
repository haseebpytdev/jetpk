<?php

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Models\Agency;
use App\Models\Agent;
use App\Models\Booking;
use App\Models\StaffProfile;
use App\Models\User;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase20BProductUiWiringTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_agents_uses_db_rows_not_demo_config(): void
    {
        [$agency, $admin] = $this->agencyAdmin();
        $agentUser = User::factory()->create([
            'current_agency_id' => $agency->id,
            'account_type' => AccountType::Agent,
        ]);
        Agent::factory()->create([
            'agency_id' => $agency->id,
            'user_id' => $agentUser->id,
            'code' => 'AGT-REAL-2001',
        ]);

        $this->actingAs($admin)->get(route('admin.agents'))
            ->assertOk()
            ->assertSee('AGT-REAL-2001')
            ->assertDontSee('AGT-1001');
    }

    public function test_admin_agents_scopes_by_agency(): void
    {
        [$agency, $admin] = $this->agencyAdmin();
        $otherAgency = Agency::factory()->create();

        Agent::factory()->create([
            'agency_id' => $agency->id,
            'user_id' => User::factory()->create(['current_agency_id' => $agency->id, 'account_type' => AccountType::Agent])->id,
            'code' => 'AGT-LOCAL',
        ]);
        Agent::factory()->create([
            'agency_id' => $otherAgency->id,
            'user_id' => User::factory()->create(['current_agency_id' => $otherAgency->id, 'account_type' => AccountType::Agent])->id,
            'code' => 'AGT-OTHER',
        ]);

        $this->actingAs($admin)->get(route('admin.agents'))
            ->assertOk()
            ->assertSee('AGT-LOCAL')
            ->assertDontSee('AGT-OTHER');
    }

    public function test_admin_agents_empty_state_when_no_agents(): void
    {
        [, $admin] = $this->agencyAdmin();
        $this->actingAs($admin)->get(route('admin.agents'))
            ->assertOk()
            ->assertSee('No agents yet')
            ->assertSee('Agents and partner agencies will appear here after approval or manual creation.');
    }

    public function test_foundation_seeder_adds_five_demo_agents_for_agents_dashboard(): void
    {
        $this->seed(OtaFoundationSeeder::class);

        $demoCodes = [
            'AGT-SANA-002',
            'AGT-KCT-003',
            'AGT-IAD-004',
            'AGT-MFT-005',
            'AGT-PUS-006',
        ];

        foreach ($demoCodes as $code) {
            $this->assertDatabaseHas('agents', ['code' => $code]);
        }

        $this->assertSame(5, Agent::query()->whereIn('code', $demoCodes)->count());
        $this->assertDatabaseHas('users', [
            'email' => 'agent.sana@ota.demo',
            'account_type' => AccountType::Agent->value,
        ]);
        $this->assertSame(0, Booking::query()->where('booking_reference', 'like', 'DEMO-%')->count());
    }

    public function test_admin_staff_uses_db_rows_not_demo_config(): void
    {
        [$agency, $admin] = $this->agencyAdmin();
        $staffUser = User::factory()->create([
            'name' => 'Real Staff Member',
            'current_agency_id' => $agency->id,
            'account_type' => AccountType::Staff,
        ]);
        StaffProfile::factory()->create([
            'agency_id' => $agency->id,
            'user_id' => $staffUser->id,
            'job_title' => 'Operations Executive',
        ]);

        $this->actingAs($admin)->get(route('admin.staff'))
            ->assertOk()
            ->assertSee('Real Staff Member')
            ->assertDontSee('STF-001');
    }

    public function test_admin_staff_scopes_by_agency(): void
    {
        [$agency, $admin] = $this->agencyAdmin();
        $otherAgency = Agency::factory()->create();

        $staffA = User::factory()->create(['current_agency_id' => $agency->id, 'account_type' => AccountType::Staff]);
        $staffB = User::factory()->create(['current_agency_id' => $otherAgency->id, 'account_type' => AccountType::Staff]);
        StaffProfile::factory()->create(['agency_id' => $agency->id, 'user_id' => $staffA->id, 'job_title' => 'Ops A']);
        StaffProfile::factory()->create(['agency_id' => $otherAgency->id, 'user_id' => $staffB->id, 'job_title' => 'Ops B']);

        $this->actingAs($admin)->get(route('admin.staff'))
            ->assertOk()
            ->assertSee('Ops A')
            ->assertDontSee('Ops B');
    }

    public function test_admin_staff_empty_state_when_no_staff(): void
    {
        [, $admin] = $this->agencyAdmin();
        $this->actingAs($admin)->get(route('admin.staff'))
            ->assertOk()
            ->assertSee('No staff users have been created yet. Create staff from Users & Access.');
    }

    public function test_roles_permissions_renders_real_access_matrix(): void
    {
        [, $admin] = $this->agencyAdmin();
        $this->actingAs($admin)->get(route('admin.roles-permissions'))
            ->assertOk()
            ->assertSee('Platform Admin')
            ->assertSee('Agency Admin')
            ->assertSee('Staff')
            ->assertSee('Agent')
            ->assertSee('Customer')
            ->assertSee('This matrix reflects current middleware and policy behavior.');
    }

    public function test_sidebar_contains_production_navigation_groups(): void
    {
        [, $admin] = $this->agencyAdmin();
        $this->actingAs($admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Operations')
            ->assertSee('Network')
            ->assertSee('Finance')
            ->assertSee('Suppliers')
            ->assertSee('Website')
            ->assertSee('Communications')
            ->assertSee('System')
            ->assertDontSee('PLANNED')
            ->assertDontSee('placeholder');
    }

    public function test_key_pages_avoid_demo_fake_placeholder_sample_data_wording(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $admin = User::query()->where('email', 'admin@ota.demo')->firstOrFail();
        $customer = User::factory()->create(['account_type' => AccountType::Customer, 'current_agency_id' => $admin->current_agency_id]);

        $publicPaths = [
            '/',
            '/flights/results?from=LHE&to=DXB&depart='.now()->addDays(7)->toDateString().'&trip_type=one_way&cabin=economy&adults=1&children=0&infants=0',
            '/booking/passengers',
            '/booking/review',
            '/booking/confirmation',
            '/lookup-booking',
        ];

        foreach ($publicPaths as $path) {
            $response = $this->get($path);
            if ($response->status() === 200) {
                $content = strtolower($response->getContent());
                foreach (['demo only', 'fake', 'sample data'] as $word) {
                    $this->assertFalse(str_contains($content, $word), "Found '{$word}' in public path {$path}");
                }
            }
        }

        foreach ([
            '/admin',
            '/admin/bookings',
            '/admin/reports',
            '/admin/settings/branding',
            '/admin/settings/homepage',
            '/admin/settings/communications',
            '/agent',
            '/agent/bookings',
            '/customer',
            '/customer/bookings',
            '/staff/bookings',
        ] as $path) {
            $response = str_starts_with($path, '/admin') || str_starts_with($path, '/staff')
                ? $this->actingAs($admin)->get($path)
                : (str_starts_with($path, '/customer') ? $this->actingAs($customer)->get($path) : $this->actingAs(User::query()->where('email', 'agent@ota.demo')->firstOrFail())->get($path));
            if ($response->status() === 200) {
                $content = strtolower($response->getContent());
                foreach (['demo only', 'fake', 'sample data'] as $word) {
                    $this->assertFalse(str_contains($content, $word), "Found '{$word}' in operator path {$path}");
                }
            }
        }
    }

    public function test_legacy_branding_route_redirects_to_real_settings_page(): void
    {
        [, $admin] = $this->agencyAdmin();
        $this->actingAs($admin)->get(route('admin.branding'))
            ->assertRedirect(route('admin.settings.branding.edit'));
    }

    public function test_go_live_checklist_remains_admin_only_and_production_safe(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $admin = User::query()->where('email', 'admin@ota.demo')->firstOrFail();
        $staff = User::query()->where('email', 'staff@ota.demo')->firstOrFail();

        $this->actingAs($admin)->get(route('admin.go-live-checklist'))
            ->assertOk()
            ->assertDontSee('demo');
        $this->actingAs($staff)->get(route('admin.go-live-checklist'))->assertForbidden();
    }

    /**
     * @return array{Agency, User}
     */
    protected function agencyAdmin(): array
    {
        $agency = Agency::factory()->create();
        $admin = User::factory()->agencyAdmin()->create(['current_agency_id' => $agency->id]);
        $agency->users()->attach($admin->id, ['role' => AccountType::AgencyAdmin->value]);

        return [$agency, $admin];
    }
}
