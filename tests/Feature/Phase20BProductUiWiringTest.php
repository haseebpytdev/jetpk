<?php

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Models\Agency;
use App\Models\Agent;
use App\Models\Booking;
use App\Models\StaffProfile;
use App\Models\User;
use App\Support\BackOffice\BackOfficeCapabilitiesPresenter;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\AdminLegacyViewTestHelpers;
use Tests\Support\PlatformAdminTestHelpers;
use Tests\TestCase;

class Phase20BProductUiWiringTest extends TestCase
{
    use AdminLegacyViewTestHelpers;
    use PlatformAdminTestHelpers;
    use RefreshDatabase;

    public function test_admin_agents_uses_db_rows_not_demo_config(): void
    {
        $agency = Agency::factory()->create();
        $admin = $this->platformAdmin();
        $agentUser = User::factory()->create([
            'current_agency_id' => $agency->id,
            'account_type' => AccountType::Agent,
        ]);
        Agent::factory()->create([
            'agency_id' => $agency->id,
            'user_id' => $agentUser->id,
            'code' => 'AGT-REAL-2001',
        ]);

        $this->assertLegacyAgentsRedirect($admin);

        $html = $this->agentsWorkspaceHtml($admin);
        $this->assertStringContainsString('AGT-REAL-2001', $html);
        $this->assertStringNotContainsString('AGT-1001', $html);
    }

    public function test_admin_agents_platform_admin_sees_cross_agency_rows(): void
    {
        $agency = Agency::factory()->create();
        $admin = $this->platformAdmin();
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

        $this->assertLegacyAgentsRedirect($admin);

        $html = $this->agentsWorkspaceHtml($admin);
        $this->assertStringContainsString('AGT-LOCAL', $html);
        $this->assertStringContainsString('AGT-OTHER', $html);
    }

    public function test_admin_agents_empty_state_when_no_agents(): void
    {
        $admin = $this->platformAdmin();
        Agent::query()->delete();

        $this->assertLegacyAgentsRedirect($admin);

        $html = $this->agentsWorkspaceHtml($admin);
        $this->assertStringContainsString('No agents yet', $html);
        $this->assertStringContainsString(
            'Agents and partner agencies will appear here after approval or manual creation.',
            $html
        );
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
        $agency = Agency::factory()->create();
        $admin = $this->platformAdmin();
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
            ->assertRedirect('/admin/dashboard/staff');

        $html = $this->adminStaffIndexHtml($admin);
        $this->assertStringContainsString('Real Staff Member', $html);
        $this->assertStringNotContainsString('STF-001', $html);
    }

    public function test_admin_staff_platform_admin_sees_cross_agency_rows(): void
    {
        $agency = Agency::factory()->create();
        $admin = $this->platformAdmin();
        $otherAgency = Agency::factory()->create();

        $staffA = User::factory()->create(['current_agency_id' => $agency->id, 'account_type' => AccountType::Staff]);
        $staffB = User::factory()->create(['current_agency_id' => $otherAgency->id, 'account_type' => AccountType::Staff]);
        StaffProfile::factory()->create(['agency_id' => $agency->id, 'user_id' => $staffA->id, 'job_title' => 'Ops A']);
        StaffProfile::factory()->create(['agency_id' => $otherAgency->id, 'user_id' => $staffB->id, 'job_title' => 'Ops B']);

        $this->actingAs($admin)->get(route('admin.staff'))
            ->assertRedirect('/admin/dashboard/staff');

        $html = $this->adminStaffIndexHtml($admin);
        $this->assertStringContainsString('Ops A', $html);
        $this->assertStringContainsString('Ops B', $html);
    }

    public function test_admin_staff_empty_state_when_no_staff(): void
    {
        $admin = $this->platformAdmin();
        StaffProfile::query()->delete();

        $this->actingAs($admin)->get(route('admin.staff'))
            ->assertRedirect('/admin/dashboard/staff');

        $html = $this->adminStaffIndexHtml($admin);
        $this->assertStringContainsString(
            'No staff users have been created yet. Create staff from Users &amp; Access.',
            $html
        );
    }

    public function test_roles_permissions_renders_real_access_matrix(): void
    {
        $admin = $this->platformAdmin();
        $this->actingAs($admin)->get(route('admin.roles-permissions'))
            ->assertRedirect('/admin/dashboard/users/roles');

        $html = $this->adminRolesPermissionsHtml($admin);
        $this->assertStringContainsString('Platform admin', $html);
        $this->assertStringContainsString('Agency admin', $html);
        $this->assertStringContainsString('Staff', $html);
        $this->assertStringContainsString('Agent', $html);
        $this->assertStringContainsString('Customer', $html);
        $this->assertStringContainsString('This matrix reflects current middleware and policy behavior.', $html);
    }

    public function test_sidebar_contains_production_navigation_groups(): void
    {
        $admin = $this->platformAdmin();
        $this->actingAs($admin)->get(route('admin.dashboard'))
            ->assertOk();

        $labels = collect(app(BackOfficeCapabilitiesPresenter::class)->present($admin)['navigation_groups'] ?? [])
            ->pluck('label')
            ->all();

        $this->assertContains('Operations', $labels);
        $this->assertContains('Finance', $labels);
        $this->assertContains('Customers', $labels);
        $this->assertContains('Suppliers', $labels);
        $this->assertContains('Website', $labels);
        $this->assertContains('Communications', $labels);
        $this->assertContains('System', $labels);
        $this->assertNotContains('PLANNED', $labels);
        $this->assertFalse(
            collect($labels)->contains(fn (string $label): bool => str_contains(strtolower($label), 'placeholder'))
        );
    }

    public function test_key_pages_avoid_demo_fake_placeholder_sample_data_wording(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $admin = $this->platformAdmin();
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
        $admin = $this->platformAdmin();
        $this->actingAs($admin)->get(route('admin.branding'))
            ->assertRedirect('/admin/dashboard/settings/general');
    }

    public function test_go_live_checklist_remains_admin_only_and_production_safe(): void
    {
        $admin = $this->platformAdmin();
        $staff = User::query()->where('email', 'staff@ota.demo')->firstOrFail();

        $this->actingAs($admin)->get(route('admin.go-live-checklist'))
            ->assertRedirect('/admin/dashboard/system/go-live');

        $html = $this->adminGoLiveChecklistHtml($admin);
        $this->assertStringNotContainsString('demo only', strtolower($html));
        $this->assertStringNotContainsString('sample data', strtolower($html));
        $this->assertStringNotContainsString('placeholder', strtolower($html));

        $this->actingAs($staff)->get(route('admin.go-live-checklist'))->assertForbidden();
    }

    protected function assertLegacyAgentsRedirect(User $admin, string $uri = '/admin/agents'): void
    {
        $response = $this->actingAs($admin)->get($uri);
        $response->assertRedirect();
        $target = (string) $response->headers->get('Location');
        $this->assertStringContainsString('/admin/dashboard/agents', $target);
    }

    /**
     * @param  array<string, mixed>  $query
     */
    protected function agentsWorkspaceHtml(User $admin, array $query = []): string
    {
        $response = $this->actingAs($admin)
            ->getJson(route('admin.agents.data', $query))
            ->assertOk();

        return (string) ($response->json('rows_html') ?? '')
            .(string) ($response->json('preview_html') ?? '');
    }
}
