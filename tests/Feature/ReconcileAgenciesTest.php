<?php

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Models\Agency;
use App\Models\Agent;
use App\Models\AgentApplication;
use App\Models\User;
use App\Services\Agencies\AgencyReconciliationService;
use App\Support\Access\AccountTypeLabels;
use App\Support\Agencies\AgencyScopeResolver;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ReconcileAgenciesTest extends TestCase
{
    use RefreshDatabase;

    public function test_dry_run_detects_approved_application_without_agent_linkage(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        AgentApplication::query()->create([
            'first_name' => 'Legacy',
            'last_name' => 'Owner',
            'email' => 'legacy-owner@example.test',
            'mobile' => '+923001112233',
            'company_name' => 'Legacy Partner Travels',
            'business_type' => 'travel_agency',
            'city' => 'Lahore',
            'country' => 'Pakistan',
            'office_address' => 'Test address',
            'status' => 'approved',
        ]);

        $issues = app(AgencyReconciliationService::class)->diagnose();
        $this->assertNotEmpty($issues);
        $this->assertSame('legacy-owner@example.test', $issues[0]['email']);

        Artisan::call('ota:reconcile-agencies', ['--dry-run' => true]);
        $this->assertStringContainsString('would repair', Artisan::output());
    }

    public function test_reconciliation_creates_active_agency_and_agent_linkage(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $application = AgentApplication::query()->create([
            'first_name' => 'Legacy',
            'last_name' => 'Owner',
            'email' => 'legacy-owner@example.test',
            'mobile' => '+923001112233',
            'company_name' => 'Legacy Partner Travels',
            'business_type' => 'travel_agency',
            'city' => 'Lahore',
            'country' => 'Pakistan',
            'office_address' => 'Test address',
            'status' => 'approved',
        ]);

        $result = app(AgencyReconciliationService::class)->reconcile(false);
        $this->assertSame(1, $result['repaired']);

        $user = User::query()->where('email', 'legacy-owner@example.test')->firstOrFail();
        $agent = Agent::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertTrue($agent->is_active);
        $this->assertSame('Legacy Partner Travels', Agency::query()->find($agent->agency_id)?->name);

        $resultAgain = app(AgencyReconciliationService::class)->reconcile(false);
        $this->assertSame(0, $resultAgain['repaired']);
    }

    public function test_agency_badge_uses_agent_agency_not_only_current_agency_id(): void
    {
        $platform = Agency::factory()->create(['name' => 'Asif Travels', 'slug' => 'asif-travels']);
        $partner = Agency::factory()->create(['name' => 'Partner Travels', 'slug' => 'partner-travels']);
        $owner = User::factory()->create([
            'account_type' => AccountType::Agent,
            'current_agency_id' => $platform->id,
            'email' => 'owner@partner.test',
            'meta' => ['company_name' => 'Partner Travels'],
        ]);
        Agent::factory()->for($platform)->create(['user_id' => $owner->id, 'is_active' => true]);
        Agent::factory()->for($partner)->create(['user_id' => $owner->id, 'is_active' => true]);

        $this->assertSame('Partner Travels', AccountTypeLabels::agencyBadge($owner->fresh(['agentProfiles.agency'])));
        $this->assertSame('Partner Travels', AgencyScopeResolver::badgeLabel($owner->fresh(['agentProfiles.agency'])));
    }

    public function test_reconciliation_deactivates_duplicate_default_agency_agent_row(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $platform = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $partner = Agency::query()->create([
            'name' => 'Easy Ticket',
            'slug' => 'easy-ticket',
            'timezone' => 'Asia/Karachi',
        ]);

        $owner = User::factory()->create([
            'account_type' => AccountType::Agent,
            'current_agency_id' => $partner->id,
            'email' => 'asifkhalil@easyticket.pk',
            'meta' => ['company_name' => 'Easy Ticket'],
        ]);

        $oldDuplicate = Agent::factory()->for($platform)->create([
            'user_id' => $owner->id,
            'is_active' => true,
        ]);
        $canonical = Agent::factory()->for($partner)->create([
            'user_id' => $owner->id,
            'is_active' => true,
        ]);

        $application = AgentApplication::query()->create([
            'first_name' => 'Asif',
            'last_name' => 'Khalil',
            'email' => 'asifkhalil@easyticket.pk',
            'mobile' => '+923001112233',
            'company_name' => 'Easy Ticket',
            'business_type' => 'travel_agency',
            'city' => 'Lahore',
            'country' => 'Pakistan',
            'office_address' => 'Test address',
            'status' => 'approved',
        ]);

        $service = app(AgencyReconciliationService::class);
        $before = $service->diagnoseApplication($application);
        $this->assertContains('duplicate_active_agent_rows', $before['issues']);
        $this->assertSame($canonical->id, $before['canonical_agent_id']);

        $result = $service->reconcile(false);
        $this->assertSame(1, $result['repaired']);
        $this->assertSame(1, $result['rows'][0]['duplicate_rows_deactivated'] ?? 0);
        $this->assertContains($oldDuplicate->id, $result['rows'][0]['deactivated_agent_ids'] ?? []);

        $owner->refresh();
        $oldDuplicate->refresh();
        $canonical->refresh();

        $this->assertSame($partner->id, $owner->current_agency_id);
        $this->assertFalse($oldDuplicate->is_active);
        $this->assertTrue($canonical->is_active);
        $this->assertSame([], $service->diagnose());

        $resultAgain = $service->reconcile(false);
        $this->assertSame(0, $resultAgain['repaired']);
        $this->assertSame(1, Agent::query()->where('user_id', $owner->id)->where('agency_id', $partner->id)->count());
    }

    public function test_admin_agencies_shows_partner_active_after_duplicate_deactivation(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        [$admin] = $this->platformAdmin();
        $platform = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $partner = Agency::query()->create([
            'name' => 'Easy Ticket',
            'slug' => 'easy-ticket',
            'timezone' => 'Asia/Karachi',
        ]);

        $owner = User::factory()->create([
            'account_type' => AccountType::Agent,
            'current_agency_id' => $partner->id,
            'email' => 'partner-owner@easyticket.pk',
        ]);
        Agent::factory()->for($platform)->create(['user_id' => $owner->id, 'is_active' => true]);
        Agent::factory()->for($partner)->create(['user_id' => $owner->id, 'is_active' => true]);

        AgentApplication::query()->create([
            'first_name' => 'Partner',
            'last_name' => 'Owner',
            'email' => 'partner-owner@easyticket.pk',
            'mobile' => '+923001112233',
            'company_name' => 'Easy Ticket',
            'business_type' => 'travel_agency',
            'city' => 'Lahore',
            'country' => 'Pakistan',
            'office_address' => 'Test address',
            'status' => 'approved',
        ]);

        app(AgencyReconciliationService::class)->reconcile(false);

        $this->actingAs($admin)
            ->get(route('admin.agencies.index', ['status' => 'active']))
            ->assertOk()
            ->assertSee('Easy Ticket', false);

        Agent::query()->where('user_id', $owner->id)->where('agency_id', $platform->id)->first()?->refresh();
        $this->assertFalse(
            Agent::query()->where('user_id', $owner->id)->where('agency_id', $platform->id)->value('is_active')
        );
    }

    public function test_users_access_badge_resolves_partner_agency_with_duplicate_rows(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $platform = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $partner = Agency::query()->create([
            'name' => 'Easy Ticket',
            'slug' => 'easy-ticket',
            'timezone' => 'Asia/Karachi',
        ]);

        $owner = User::factory()->create([
            'account_type' => AccountType::Agent,
            'current_agency_id' => $partner->id,
            'email' => 'badge-owner@easyticket.pk',
            'meta' => ['company_name' => 'Easy Ticket'],
        ]);
        Agent::factory()->for($platform)->create(['user_id' => $owner->id, 'is_active' => false]);
        Agent::factory()->for($partner)->create(['user_id' => $owner->id, 'is_active' => true]);

        [$admin] = $this->platformAdmin();

        $this->actingAs($admin)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('Easy Ticket', false);
    }

    public function test_platform_admin_reports_handles_null_current_agency_id(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $admin = User::query()->where('email', 'admin@ota.demo')->firstOrFail();
        $admin->forceFill(['account_type' => AccountType::PlatformAdmin, 'current_agency_id' => null])->save();

        $this->actingAs($admin->fresh())
            ->get(route('admin.reports'))
            ->assertOk();
    }

    public function test_agencies_active_count_reflects_active_agent_record(): void
    {
        [$admin] = $this->platformAdmin();
        $agency = Agency::factory()->create(['name' => 'Active Partner', 'slug' => 'active-partner']);
        $owner = User::factory()->create([
            'account_type' => AccountType::Agent,
            'current_agency_id' => $agency->id,
        ]);
        Agent::factory()->for($agency)->create(['user_id' => $owner->id, 'is_active' => true]);

        $this->actingAs($admin)
            ->get(route('admin.agencies.index', ['status' => 'active']))
            ->assertOk()
            ->assertSee('Active Partner', false);
    }

    public function test_staff_page_status_badge_is_readable(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        [$admin] = $this->platformAdmin();

        $this->actingAs($admin)
            ->get(route('admin.staff'))
            ->assertOk()
            ->assertSee('data-testid="admin-staff-status-active"', false)
            ->assertSee('ota-bstat', false);
    }

    /**
     * @return array{0: User}
     */
    protected function platformAdmin(): array
    {
        $this->seed(OtaFoundationSeeder::class);
        $admin = User::query()->where('email', 'admin@ota.demo')->firstOrFail();
        $admin->forceFill(['account_type' => AccountType::PlatformAdmin])->save();

        return [$admin->fresh()];
    }
}
