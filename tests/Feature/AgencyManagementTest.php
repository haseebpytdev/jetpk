<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\AgencyManagementController;
use App\Http\Controllers\Admin\UserManagementController;
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
use App\Support\Branding\CompanyEmailProfileResolver;
use App\Support\Identity\ActorIdentifier;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
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
            ->forceFill(['display_name' => 'YD Travels', 'support_email' => 'yd@travels.test'])
            ->save();

        $profile = CompanyEmailProfileResolver::resolve($agency);
        $this->assertSame('YD Travels', $profile->name);
        $this->assertSame('yd@travels.test', $profile->support_email);

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk();
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
            ->assertRedirect('/admin/dashboard/users?selected='.$user->id);

        $html = $this->userShowHtml($admin, $user);
        $this->assertStringContainsString('data-testid="user-access-username"', $html);
        $this->assertStringContainsString('agentstaff01', $html);
    }

    public function test_platform_admin_can_view_agencies_index(): void
    {
        [$admin] = $this->platformAdmin();

        $this->actingAs($admin)
            ->get(route('admin.agencies.index'))
            ->assertRedirect('/admin/dashboard/agents');

        $html = $this->agenciesIndexHtml($admin);
        $this->assertStringContainsString('Agencies', $html);
        $this->assertStringContainsString('Agency list', $html);
        $this->assertStringContainsString('data-testid="admin-agencies-index"', $html);
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
            ->assertRedirect('/admin/dashboard/agents');

        $html = $this->agencyShowHtml($admin, $agency);
        $this->assertStringContainsString('Bare Agency', $html);
        $this->assertStringContainsString('No agency owner user is linked', $html);
        $this->assertStringContainsString('PKR 0.00', $html);
        $this->assertStringNotContainsString('Wallet is not available', $html);
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
            ->assertRedirect('/admin/dashboard/agents');

        $this->assertStringContainsString('PKR 100.00', $this->agenciesIndexHtml($admin));

        $showHtml = $this->agencyShowHtml($admin, $agency);
        $this->assertStringContainsString('data-testid="admin-agency-wallet-balance"', $showHtml);
        $this->assertStringContainsString('PKR 100.00', $showHtml);

        $walletHtml = $this->agencyShowHtml($admin, $agency, ['tab' => 'wallet']);
        $this->assertStringContainsString('data-testid="admin-agency-wallet-available"', $walletHtml);
        $this->assertStringContainsString('PKR 100.00', $walletHtml);
        $this->assertStringContainsString('Individual wallets', $walletHtml);

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
            ->assertRedirect('/admin/dashboard/agents');

        $html = $this->agencyShowHtml($admin, $agency);
        $this->assertStringContainsString($agency->name, $html);
        $this->assertStringContainsString('data-testid="admin-agency-tabs"', $html);
    }

    public function test_agency_detail_shows_owner_user(): void
    {
        [$admin, $agency, $owner] = $this->platformAdminWithOwner();

        $this->actingAs($admin)
            ->get(route('admin.agencies.show', ['agency' => $agency, 'tab' => 'owner']))
            ->assertRedirect('/admin/dashboard/agents?tab=owner');

        $html = $this->agencyShowHtml($admin, $agency, ['tab' => 'owner']);
        $this->assertStringContainsString($owner->name, $html);
        $this->assertStringContainsString($owner->email, $html);
        $this->assertStringContainsString('Agency Owner', $html);
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
            ->assertRedirect('/admin/dashboard/agents?tab=staff');

        $html = $this->agencyShowHtml($admin, $agency, ['tab' => 'staff']);
        $this->assertStringContainsString($staffUser->name, $html);
        $this->assertStringContainsString($staffUser->email, $html);
    }

    public function test_users_access_labels_agent_as_agency_owner(): void
    {
        [$admin] = $this->platformAdmin();
        $this->seed(OtaFoundationSeeder::class);
        $agent = User::query()->where('email', 'agent@ota.demo')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('admin.users.index', ['account_type' => 'agent']))
            ->assertRedirect('/admin/dashboard/users?account_type=agent');

        $html = $this->usersIndexHtml($admin, ['account_type' => 'agent']);
        $this->assertStringContainsString('Agency Owner', $html);
        $this->assertStringContainsString($agent->email, $html);
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
            ->assertRedirect('/admin/dashboard/users?account_type=agent_staff');

        $html = $this->usersIndexHtml($admin, ['account_type' => 'agent_staff']);
        $this->assertStringContainsString('Agency Staff', $html);
        $this->assertStringContainsString('stafflabel@agency.test', $html);
    }

    public function test_users_access_shows_agency_badge_for_agency_users(): void
    {
        [$admin, $agency] = $this->platformAdminWithAgency();
        $this->seed(OtaFoundationSeeder::class);

        $this->actingAs($admin)
            ->get(route('admin.users.index', ['account_type' => 'agent']))
            ->assertRedirect('/admin/dashboard/users?account_type=agent');

        $html = $this->usersIndexHtml($admin, ['account_type' => 'agent']);
        $this->assertStringContainsString($agency->name, $html);
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
            ->assertRedirect('/admin/dashboard/agents?tab=bookings');

        $html = $this->agencyShowHtml($admin, $agency, ['tab' => 'bookings']);
        $this->assertStringContainsString('legacy_unknown_status', $html);
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
            ->assertRedirect('/admin/dashboard/agents?tab=activity');

        $html = $this->agencyShowHtml($admin, $agency, ['tab' => 'activity']);
        $this->assertStringContainsString('data-testid="admin-agency-tab-activity"', $html);
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
            ->assertRedirect('/admin/dashboard/agents?tab=activity');

        $html = $this->agencyShowHtml($admin, $agency, ['tab' => 'activity']);
        $this->assertStringContainsString('No audit activity recorded for this agency', $html);
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
            ->assertRedirect('/admin/dashboard/agents?tab=activity');

        $html = $this->agencyShowHtml($admin, $agency, ['tab' => 'activity']);
        $this->assertStringContainsString('agency.settings.updated', $html);
        $this->assertStringContainsString('System', $html);
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
            ->assertRedirect('/admin/dashboard/agents?tab=activity');

        $html = $this->agencyShowHtml($admin, $agency, ['tab' => 'activity']);
        $this->assertStringContainsString('agency.owner.removed', $html);
        $this->assertStringContainsString('System', $html);
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
            ->assertRedirect('/admin/dashboard/agents?tab=activity');

        $html = $this->agencyShowHtml($admin, $agency, ['tab' => 'activity']);
        $this->assertStringContainsString('agency.profile.updated', $html);
        $this->assertStringContainsString(ActorIdentifier::forUser($owner), $html);
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
            ->assertRedirect('/admin/dashboard/agents?tab=activity');

        $html = $this->agencyShowHtml($admin, $agency, ['tab' => 'activity']);
        $this->assertStringContainsString('Easy Ticket', $html);
        $this->assertStringContainsString('ET', $html);
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

    /**
     * @param  array<string, mixed>  $query
     */
    private function agenciesIndexHtml(User $admin, array $query = []): string
    {
        $this->actingAs($admin);
        $uri = '/admin/agencies';
        if ($query !== []) {
            $uri .= '?'.http_build_query($query);
        }
        $request = Request::create($uri, 'GET');
        $request->setUserResolver(fn () => $admin);

        return app(AgencyManagementController::class)->index($request)->render();
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function agencyShowHtml(User $admin, Agency $agency, array $query = []): string
    {
        $this->actingAs($admin);
        $uri = '/admin/agencies/'.$agency->id;
        if ($query !== []) {
            $uri .= '?'.http_build_query($query);
        }
        $request = Request::create($uri, 'GET');
        $request->setUserResolver(fn () => $admin);

        return app(AgencyManagementController::class)->show($request, $agency)->render();
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function usersIndexHtml(User $admin, array $query = []): string
    {
        $this->actingAs($admin);
        $uri = '/admin/users';
        if ($query !== []) {
            $uri .= '?'.http_build_query($query);
        }
        $request = Request::create($uri, 'GET');
        $request->setUserResolver(fn () => $admin);

        return app(UserManagementController::class)->index($request)->render();
    }

    private function userShowHtml(User $admin, User $user): string
    {
        $this->actingAs($admin);
        $request = Request::create('/admin/users/'.$user->id, 'GET');
        $request->setUserResolver(fn () => $admin);

        return app(UserManagementController::class)->show($user)->render();
    }
}
