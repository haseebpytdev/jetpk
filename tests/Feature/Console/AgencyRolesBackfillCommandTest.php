<?php

namespace Tests\Feature\Console;

use App\Enums\AccountType;
use App\Enums\AgencyRole;
use App\Enums\UserAccountStatus;
use App\Models\Agency;
use App\Models\AgencyUser;
use App\Models\Agent;
use App\Models\User;
use App\Support\Agents\AgentPermission;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AgencyRolesBackfillCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_migration_adds_agency_role_column(): void
    {
        $this->assertTrue(Schema::hasColumn('agency_users', 'agency_role'));
        $this->assertTrue(Schema::hasColumn('agency_users', 'role'));
    }

    public function test_dry_run_does_not_write_agency_role(): void
    {
        [$ownerUser, $agent, $staffUser, $membership] = $this->seedAgencyMemberships();

        Artisan::call('agency-roles:backfill', ['--dry-run' => true]);

        $this->assertStringContainsString('Dry run', Artisan::output());
        $this->assertNull($membership->fresh()->agency_role);
        $this->assertNull(
            AgencyUser::query()
                ->where('user_id', $ownerUser->id)
                ->value('agency_role'),
        );
    }

    public function test_backfill_writes_owner_for_agent_and_viewer_for_bare_staff(): void
    {
        [$ownerUser, $agent, $staffUser, $staffMembership] = $this->seedAgencyMemberships();

        Artisan::call('agency-roles:backfill');

        $ownerMembership = AgencyUser::query()
            ->where('user_id', $ownerUser->id)
            ->firstOrFail();
        $staffMembership->refresh();

        $this->assertSame(AgencyRole::Owner, $ownerMembership->agency_role);
        $this->assertSame(AgencyRole::Viewer, $staffMembership->agency_role);
        $this->assertSame(AccountType::Agent->value, $ownerMembership->role);
        $this->assertSame(AccountType::AgentStaff->value, $staffMembership->role);
    }

    public function test_backfill_skips_rows_with_existing_agency_role(): void
    {
        [$ownerUser, $agent, $staffUser, $staffMembership] = $this->seedAgencyMemberships();

        $staffMembership->forceFill(['agency_role' => AgencyRole::Manager->value])->save();

        Artisan::call('agency-roles:backfill');

        $this->assertSame(AgencyRole::Manager, $staffMembership->fresh()->agency_role);
    }

    public function test_backfill_respects_user_filter(): void
    {
        [$ownerUser, $agent, $staffUser, $staffMembership] = $this->seedAgencyMemberships();

        Artisan::call('agency-roles:backfill', ['--user' => $staffUser->id]);

        $this->assertNull(
            AgencyUser::query()
                ->where('user_id', $ownerUser->id)
                ->value('agency_role'),
        );
        $this->assertSame(AgencyRole::Viewer, $staffMembership->fresh()->agency_role);
    }

    public function test_backfill_infers_manager_from_staff_permissions(): void
    {
        $agency = Agency::factory()->create();
        $user = User::factory()->create([
            'account_type' => AccountType::AgentStaff,
            'current_agency_id' => $agency->id,
            'status' => UserAccountStatus::Active,
            'meta' => ['agent_permissions' => [AgentPermission::StaffManage]],
        ]);

        AgencyUser::query()->create([
            'agency_id' => $agency->id,
            'user_id' => $user->id,
            'role' => AccountType::AgentStaff->value,
        ]);

        Artisan::call('agency-roles:backfill', ['--user' => $user->id]);

        $this->assertSame(
            AgencyRole::Manager,
            AgencyUser::query()->where('user_id', $user->id)->firstOrFail()->agency_role,
        );
    }

    public function test_backfill_skips_platform_admin_agency_users_row(): void
    {
        $this->assertBackfillSkipsIneligibleAccountType(AccountType::PlatformAdmin, 'platform_admin');
    }

    public function test_backfill_skips_staff_agency_users_row(): void
    {
        $this->assertBackfillSkipsIneligibleAccountType(AccountType::Staff, 'staff');
    }

    public function test_backfill_skips_customer_agency_users_row(): void
    {
        $this->assertBackfillSkipsIneligibleAccountType(AccountType::Customer, 'customer');
    }

    protected function assertBackfillSkipsIneligibleAccountType(AccountType $accountType, string $legacyRole): void
    {
        $agency = Agency::factory()->create();
        $user = User::factory()->create([
            'account_type' => $accountType,
            'current_agency_id' => $agency->id,
            'status' => UserAccountStatus::Active,
        ]);

        AgencyUser::query()->create([
            'agency_id' => $agency->id,
            'user_id' => $user->id,
            'role' => $legacyRole,
        ]);

        Artisan::call('agency-roles:backfill', ['--user' => $user->id]);

        $this->assertNull(AgencyUser::query()->where('user_id', $user->id)->firstOrFail()->agency_role);
        $this->assertStringContainsString('Skipped ineligible (non-agency portal)', Artisan::output());
    }

    /**
     * @return array{0: User, 1: Agent, 2: User, 3: AgencyUser}
     */
    protected function seedAgencyMemberships(): array
    {
        $this->seed(OtaFoundationSeeder::class);

        $ownerUser = User::query()->where('email', 'agent@ota.demo')->firstOrFail();
        $agent = Agent::query()->where('user_id', $ownerUser->id)->firstOrFail();

        AgencyUser::query()->updateOrCreate(
            ['agency_id' => $agent->agency_id, 'user_id' => $ownerUser->id],
            ['role' => AccountType::Agent->value],
        );

        $staffUser = User::query()->create([
            'name' => 'Bare Staff',
            'username' => 'bare-staff',
            'email' => 'bare-staff@agency.test',
            'password' => bcrypt('password'),
            'account_type' => AccountType::AgentStaff,
            'status' => UserAccountStatus::Active,
            'current_agency_id' => $agent->agency_id,
            'meta' => [
                'owner_agent_id' => $agent->id,
                'agent_permissions' => [],
            ],
        ]);

        $staffMembership = AgencyUser::query()->create([
            'agency_id' => $agent->agency_id,
            'user_id' => $staffUser->id,
            'role' => AccountType::AgentStaff->value,
        ]);

        return [$ownerUser, $agent, $staffUser, $staffMembership];
    }
}
