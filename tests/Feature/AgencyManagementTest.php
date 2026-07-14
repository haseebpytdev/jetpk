<?php

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Enums\AgentWalletStatus;
use App\Enums\UserAccountStatus;
use App\Models\Agency;
use App\Models\Agent;
use App\Models\AgentWallet;
use App\Models\User;
use App\Services\Agencies\AgencyBrandingService;
use App\Services\Agents\AgentWalletService;
use App\Services\Finance\Dashboard\AdminFinanceDashboardService;
use App\Services\Finance\Ledger\LedgerBalanceService;
use App\Support\Agents\AgentPermission;
use App\Support\Identity\ActorIdentifier;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AgencyManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_layout_uses_configured_platform_branding(): void
    {
        [$admin] = $this->platformAdmin();
        $agency = Agency::query()->where('slug', config('ota.default_agency_slug'))->first()
            ?? Agency::query()->firstOrFail();
        $this->app->make(AgencyBrandingService::class)->getSettingsForAgency($agency)
            ->forceFill(['display_name' => 'YD Travels'])
            ->save();

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('YD Travels', false)
            ->assertDontSee('Asif Travels', false);
    }

    public function test_user_show_page_displays_username(): void
    {
        [$admin] = $this->platformAdmin();
        $user = User::factory()->create([
            'username' => 'agentstaff01',
            'account_type' => AccountType::AgentStaff,
            'current_agency_id' => Agency::query()->firstOrFail()->id,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.users.show', $user))
            ->assertOk()
            ->assertSee('data-testid="user-access-username"', false)
            ->assertSee('agentstaff01', false);
    }

    public function test_platform_admin_can_view_agencies_index(): void
    {
        [$admin] = $this->platformAdmin();

        $this->actingAs($admin)
            ->get(route('admin.agencies.index'))
            ->assertOk()
            ->assertSee('Agencies', false)
            ->assertSee('Agency list', false)
            ->assertSee('data-testid="admin-agencies-index"', false);
    }

    public function test_agency_detail_loads_with_missing_optional_data(): void
    {
        [$admin] = $this->platformAdmin();
        $agency = Agency::factory()->create([
            'name' => 'Bare Agency',
            'slug' => 'bare-agency-'.str()->random(4),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.agencies.show', $agency))
            ->assertOk()
            ->assertSee('Bare Agency', false)
            ->assertSee('No agency owner user is linked', false)
            ->assertSee('PKR 0.00', false)
            ->assertDontSee('Wallet is not available', false);
    }

    public function test_agency_wallet_display_sums_all_wallets_for_multi_wallet_agency(): void
    {
        [$admin] = $this->platformAdmin();
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $primaryAgent = Agent::query()->where('agency_id', $agency->id)->firstOrFail();

        $walletBalances = [0.0, 0.0, 100.0];
        $agents = [$primaryAgent];

        for ($i = 1; $i < count($walletBalances); $i++) {
            $user = User::factory()->create([
                'current_agency_id' => $agency->id,
                'account_type' => AccountType::Agent,
            ]);
            $agents[] = Agent::query()->create([
                'agency_id' => $agency->id,
                'user_id' => $user->id,
                'is_active' => true,
            ]);
        }

        foreach ($walletBalances as $index => $balance) {
            AgentWallet::query()->updateOrCreate(
                ['agent_id' => $agents[$index]->id],
                [
                    'agency_id' => $agency->id,
                    'user_id' => $agents[$index]->user_id,
                    'balance' => $balance,
                    'currency' => 'PKR',
                    'status' => AgentWalletStatus::Active,
                ],
            );
        }

        $summary = app(AgentWalletService::class)->agencyWalletSummary($agency->id);
        $this->assertSame(100.0, $summary['balance']);
        $this->assertSame(3, $summary['wallet_count']);

        $beforeCounts = [
            'wallets' => AgentWallet::query()->where('agency_id', $agency->id)->count(),
        ];

        $this->actingAs($admin)
            ->get(route('admin.agencies.index'))
            ->assertOk()
            ->assertSee('PKR 100.00', false);

        $this->actingAs($admin)
            ->get(route('admin.agencies.show', $agency))
            ->assertOk()
            ->assertSee('data-testid="admin-agency-wallet-balance"', false)
            ->assertSee('PKR 100.00', false);

        $this->actingAs($admin)
            ->get(route('admin.agencies.show', ['agency' => $agency, 'tab' => 'wallet']))
            ->assertOk()
            ->assertSee('data-testid="admin-agency-wallet-available"', false)
            ->assertSee('PKR 100.00', false)
            ->assertSee('Individual wallets', false);

        $compare = app(LedgerBalanceService::class)->compareWalletToLedger($agency->id);
        $this->assertSame(100.0, $compare['wallet_balance']);

        $dashboard = app(AdminFinanceDashboardService::class)->build();
        $exposure = collect($dashboard['agency_exposure'] ?? [])->firstWhere('agency_id', $agency->id);
        if ($exposure !== null) {
            $this->assertSame(100.0, (float) $exposure['wallet_balance']);
        }

        $this->assertSame($beforeCounts['wallets'], AgentWallet::query()->where('agency_id', $agency->id)->count());
    }

    public function test_platform_admin_can_view_agency_detail(): void
    {
        [$admin, $agency] = $this->platformAdminWithAgency();

        $this->actingAs($admin)
            ->get(route('admin.agencies.show', $agency))
            ->assertOk()
            ->assertSee($agency->name, false)
            ->assertSee('data-testid="admin-agency-tabs"', false);
    }

    public function test_agency_detail_shows_owner_user(): void
    {
        [$admin, $agency, $owner] = $this->platformAdminWithOwner();

        $this->actingAs($admin)
            ->get(route('admin.agencies.show', ['agency' => $agency, 'tab' => 'owner']))
            ->assertOk()
            ->assertSee($owner->name, false)
            ->assertSee($owner->email, false)
            ->assertSee('Agency Owner', false);
    }

    public function test_agency_detail_shows_staff_users_linked_to_agency(): void
    {
        [$admin, $agency, $owner, $agent] = $this->platformAdminWithOwner();
        $staffUser = User::factory()->create([
            'name' => 'Agency Staff Member',
            'email' => 'staffmember@agency.test',
            'account_type' => AccountType::AgentStaff,
            'current_agency_id' => $agency->id,
            'status' => UserAccountStatus::Active,
            'meta' => [
                'owner_agent_id' => $agent->id,
                'agent_permissions' => [AgentPermission::BookingsView],
            ],
        ]);

        $this->actingAs($admin)
            ->get(route('admin.agencies.show', ['agency' => $agency, 'tab' => 'staff']))
            ->assertOk()
            ->assertSee($staffUser->name, false)
            ->assertSee($staffUser->email, false);
    }

    public function test_users_access_labels_agent_as_agency_owner(): void
    {
        [$admin] = $this->platformAdmin();
        $this->seed(OtaFoundationSeeder::class);
        $agent = User::query()->where('email', 'agent@ota.demo')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('admin.users.index', ['account_type' => 'agent']))
            ->assertOk()
            ->assertSee('Agency Owner', false)
            ->assertSee($agent->email, false);
    }

    public function test_users_access_labels_agent_staff_as_agency_staff(): void
    {
        [$admin, $agency, $owner, $agent] = $this->platformAdminWithOwner();
        User::factory()->create([
            'name' => 'Staff Label Test',
            'email' => 'stafflabel@agency.test',
            'account_type' => AccountType::AgentStaff,
            'current_agency_id' => $agency->id,
            'status' => UserAccountStatus::Active,
            'meta' => ['owner_agent_id' => $agent->id, 'agent_permissions' => []],
        ]);

        $this->actingAs($admin)
            ->get(route('admin.users.index', ['account_type' => 'agent_staff']))
            ->assertOk()
            ->assertSee('Agency Staff', false)
            ->assertSee('stafflabel@agency.test', false);
    }

    public function test_users_access_shows_agency_badge_for_agency_users(): void
    {
        [$admin, $agency] = $this->platformAdminWithAgency();
        $this->seed(OtaFoundationSeeder::class);

        $this->actingAs($admin)
            ->get(route('admin.users.index', ['account_type' => 'agent']))
            ->assertOk()
            ->assertSee($agency->name, false);
    }

    public function test_staff_cannot_access_platform_admin_agency_management(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $staff = User::query()->where('email', 'staff@ota.demo')->firstOrFail();
        $agency = Agency::query()->firstOrFail();

        $this->actingAs($staff)->get(route('admin.agencies.index'))->assertForbidden();
        $this->actingAs($staff)->get(route('admin.agencies.show', $agency))->assertForbidden();
    }

    public function test_agent_cannot_access_platform_admin_agency_management(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $agentUser = User::query()->where('email', 'agent@ota.demo')->firstOrFail();
        $agency = Agency::query()->firstOrFail();

        $this->actingAs($agentUser)->get(route('admin.agencies.index'))->assertForbidden();
        $this->actingAs($agentUser)->get(route('admin.agencies.show', $agency))->assertForbidden();
    }

    public function test_agent_staff_cannot_access_platform_admin_agency_management(): void
    {
        [$admin, $agency, $owner, $agent] = $this->platformAdminWithOwner();
        $staffUser = User::factory()->create([
            'account_type' => AccountType::AgentStaff,
            'current_agency_id' => $agency->id,
            'status' => UserAccountStatus::Active,
            'meta' => ['owner_agent_id' => $agent->id, 'agent_permissions' => []],
        ]);

        $this->actingAs($staffUser)->get(route('admin.agencies.index'))->assertForbidden();
        $this->actingAs($staffUser)->get(route('admin.agencies.show', $agency))->assertForbidden();
    }

    public function test_customer_cannot_access_platform_admin_agency_management(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $customer = User::query()->where('email', 'customer@ota.demo')->firstOrFail();
        $agency = Agency::query()->firstOrFail();

        $this->actingAs($customer)->get(route('admin.agencies.index'))->assertForbidden();
        $this->actingAs($customer)->get(route('admin.agencies.show', $agency))->assertForbidden();
    }

    public function test_agency_detail_loads_with_legacy_invalid_booking_status(): void
    {
        [$admin] = $this->platformAdmin();
        $agency = Agency::factory()->create([
            'name' => 'Legacy Agency',
            'slug' => 'legacy-agency-'.str()->random(4),
        ]);

        DB::table('bookings')->insert([
            'agency_id' => $agency->id,
            'customer_id' => null,
            'status' => 'legacy_unknown_status',
            'payment_status' => 'unpaid',
            'route' => 'LHE-DXB',
            'booking_reference' => 'LEG-001',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.agencies.show', ['agency' => $agency, 'tab' => 'bookings']))
            ->assertOk()
            ->assertSee('legacy_unknown_status', false);
    }

    public function test_agency_detail_activity_loads_without_meta_column(): void
    {
        [$admin, $agency] = $this->platformAdminWithAgency();

        $this->assertFalse(
            Schema::hasColumn('audit_logs', 'meta'),
            'Test assumes production-like audit_logs schema without meta column.'
        );

        $this->actingAs($admin)
            ->get(route('admin.agencies.show', ['agency' => $agency, 'tab' => 'activity']))
            ->assertOk()
            ->assertSee('data-testid="admin-agency-tab-activity"', false);
    }

    public function test_agency_detail_activity_loads_with_empty_audit_logs(): void
    {
        [$admin] = $this->platformAdmin();
        $agency = Agency::factory()->create([
            'name' => 'No Activity Agency',
            'slug' => 'no-activity-'.str()->random(4),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.agencies.show', ['agency' => $agency, 'tab' => 'activity']))
            ->assertOk()
            ->assertSee('No audit activity recorded for this agency', false);
    }

    public function test_agency_detail_activity_loads_with_null_user_id(): void
    {
        [$admin, $agency] = $this->platformAdminWithAgency();

        DB::table('audit_logs')->insert([
            'agency_id' => $agency->id,
            'user_id' => null,
            'action' => 'agency.settings.updated',
            'properties' => json_encode(['field' => 'prefix']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.agencies.show', ['agency' => $agency, 'tab' => 'activity']))
            ->assertOk()
            ->assertSee('agency.settings.updated', false)
            ->assertSee('System', false);
    }

    public function test_agency_detail_activity_loads_with_missing_audit_user(): void
    {
        [$admin, $agency] = $this->platformAdminWithAgency();

        DB::table('audit_logs')->insert([
            'agency_id' => $agency->id,
            'user_id' => null,
            'action' => 'agency.owner.removed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $users = collect();
        $unresolvedUser = filled(999999) ? $users->get(999999) : null;
        $this->assertSame('System', ActorIdentifier::forUser($unresolvedUser));

        $this->actingAs($admin)
            ->get(route('admin.agencies.show', ['agency' => $agency, 'tab' => 'activity']))
            ->assertOk()
            ->assertSee('agency.owner.removed', false)
            ->assertSee('System', false);
    }

    public function test_agency_detail_activity_displays_actor_code_for_existing_user(): void
    {
        [$admin, $agency, $owner] = $this->platformAdminWithOwner();

        DB::table('audit_logs')->insert([
            'agency_id' => $agency->id,
            'user_id' => $owner->id,
            'action' => 'agency.profile.updated',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.agencies.show', ['agency' => $agency, 'tab' => 'activity']))
            ->assertOk()
            ->assertSee('agency.profile.updated', false)
            ->assertSee(ActorIdentifier::forUser($owner), false);
    }

    public function test_easy_ticket_agency_detail_loads_on_activity_tab(): void
    {
        [$admin] = $this->platformAdmin();
        $agency = Agency::factory()->create([
            'name' => 'Easy Ticket',
            'slug' => 'easy-ticket-'.str()->random(4),
            'settings' => ['code_prefix' => 'ET'],
        ]);

        $this->actingAs($admin)
            ->get(route('admin.agencies.show', ['agency' => $agency, 'tab' => 'activity']))
            ->assertOk()
            ->assertSee('Easy Ticket', false)
            ->assertSee('ET', false);
    }

    /**
     * @return array{0: User}
     */
    protected function platformAdmin(): array
    {
        $this->seed(OtaFoundationSeeder::class);
        $admin = User::query()->where('email', 'admin@ota.demo')->firstOrFail();
        if ($admin->account_type !== AccountType::PlatformAdmin) {
            $admin->forceFill(['account_type' => AccountType::PlatformAdmin])->save();
            $admin = $admin->fresh();
        }

        return [$admin];
    }

    /**
     * @return array{0: User, 1: Agency}
     */
    protected function platformAdminWithAgency(): array
    {
        [$admin] = $this->platformAdmin();
        $agency = Agency::query()->firstOrFail();

        return [$admin, $agency];
    }

    /**
     * @return array{0: User, 1: Agency, 2: User, 3: Agent}
     */
    protected function platformAdminWithOwner(): array
    {
        [$admin, $agency] = $this->platformAdminWithAgency();
        $owner = User::query()->where('email', 'agent@ota.demo')->firstOrFail();
        $agent = Agent::query()->where('user_id', $owner->id)->firstOrFail();

        return [$admin, $agency, $owner, $agent];
    }
}
