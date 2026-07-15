<?php

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Models\Agency;
use App\Models\Agent;
use App\Models\Booking;
use App\Models\User;
use App\Support\Agents\AgentPermission;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SeedAccessDemoUsersCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_creates_and_updates_target_users(): void
    {
        $this->seedFoundationForAccessDemo();

        $exit = Artisan::call('ota:seed-access-demo-users', [
            '--force' => true,
            '--password' => 'DemoPass123!',
        ]);

        $this->assertSame(0, $exit);

        $this->assertDatabaseHas('users', [
            'email' => 'admin@ota.demo',
            'account_type' => AccountType::PlatformAdmin->value,
            'name' => 'Platform Admin',
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('users', [
            'email' => 'agent@demo.ota',
            'account_type' => AccountType::Agent->value,
        ]);
        $this->assertDatabaseHas('users', [
            'email' => 'agent.staff@demo.ota',
            'account_type' => AccountType::AgentStaff->value,
        ]);
        $this->assertDatabaseHas('users', [
            'email' => 'staff@demo.ota',
            'account_type' => AccountType::Staff->value,
        ]);
        $this->assertDatabaseHas('users', [
            'email' => 'customer@demo.ota',
            'account_type' => AccountType::Customer->value,
        ]);

        $this->assertDemoUsernamesAssigned();

        $agentUser = User::query()->where('email', 'agent@demo.ota')->firstOrFail();
        $this->assertDatabaseHas('agents', [
            'user_id' => $agentUser->id,
            'agency_id' => $agentUser->current_agency_id,
        ]);
    }

    public function test_command_promotes_admin_only_with_force(): void
    {
        $this->seedFoundationForAccessDemo();

        $exit = Artisan::call('ota:seed-access-demo-users');
        $this->assertSame(1, $exit);
        $this->assertSame(AccountType::AgencyAdmin, User::query()->where('email', 'admin@ota.demo')->firstOrFail()->account_type);

        $exit = Artisan::call('ota:seed-access-demo-users', [
            '--force' => true,
            '--password' => 'DemoPass123!',
        ]);
        $this->assertSame(0, $exit);
        $this->assertSame(AccountType::PlatformAdmin, User::query()->where('email', 'admin@ota.demo')->firstOrFail()->fresh()->account_type);
    }

    public function test_command_does_not_delete_unrelated_users(): void
    {
        $this->seedFoundationForAccessDemo();
        $unrelated = User::factory()->create([
            'email' => 'unrelated@example.test',
            'account_type' => AccountType::Customer,
        ]);

        Artisan::call('ota:seed-access-demo-users', [
            '--force' => true,
            '--password' => 'DemoPass123!',
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $unrelated->id,
            'email' => 'unrelated@example.test',
            'account_type' => AccountType::Customer->value,
        ]);
    }

    public function test_command_does_not_delete_bookings(): void
    {
        $this->seedFoundationForAccessDemo();
        $agency = Agency::query()->firstOrFail();
        $booking = Booking::factory()->for($agency)->create();

        Artisan::call('ota:seed-access-demo-users', [
            '--force' => true,
            '--password' => 'DemoPass123!',
        ]);

        $this->assertSame(1, Booking::query()->count());
        $this->assertDatabaseHas('bookings', ['id' => $booking->id]);
    }

    public function test_agent_staff_gets_staff_selectable_permission_keys(): void
    {
        $this->seedFoundationForAccessDemo();

        Artisan::call('ota:seed-access-demo-users', [
            '--force' => true,
            '--password' => 'DemoPass123!',
        ]);

        $staffUser = User::query()->where('email', 'agent.staff@demo.ota')->firstOrFail();
        $ownerAgent = Agent::query()->whereHas('user', static fn ($query) => $query->where('email', 'agent@demo.ota'))->firstOrFail();

        $this->assertSame($ownerAgent->id, (int) ($staffUser->meta['owner_agent_id'] ?? 0));
        $this->assertEqualsCanonicalizing(
            AgentPermission::staffSelectable(),
            $staffUser->meta['agent_permissions'] ?? [],
        );
    }

    public function test_dry_run_does_not_persist_changes(): void
    {
        $this->seedFoundationForAccessDemo();
        $beforeCount = User::query()->count();
        $adminBefore = User::query()->where('email', 'admin@ota.demo')->firstOrFail();

        $exit = Artisan::call('ota:seed-access-demo-users', [
            '--dry-run' => true,
            '--force' => true,
        ]);

        $output = Artisan::output();

        $this->assertSame(0, $exit);
        $this->assertSame($beforeCount, User::query()->count());
        $this->assertSame(AccountType::AgencyAdmin, User::query()->where('email', 'admin@ota.demo')->firstOrFail()->account_type);
        $this->assertSame($adminBefore->username, User::query()->where('email', 'admin@ota.demo')->firstOrFail()->username);
        $this->assertDatabaseMissing('users', ['email' => 'agent@demo.ota']);
        $this->assertStringContainsString('dry-run', strtolower($output));
        $this->assertStringContainsString('username:', strtolower($output));
    }

    public function test_command_assigns_stable_usernames_when_column_exists(): void
    {
        $this->seedFoundationForAccessDemo();

        Artisan::call('ota:seed-access-demo-users', [
            '--force' => true,
            '--password' => 'DemoPass123!',
        ]);

        $this->assertDemoUsernamesAssigned();
    }

    public function test_command_fails_when_username_belongs_to_unrelated_user(): void
    {
        $this->seedFoundationForAccessDemo();

        User::factory()->create([
            'email' => 'unrelated@example.test',
            'username' => 'agencyowner',
            'account_type' => AccountType::Customer,
        ]);

        $beforeCount = User::query()->count();

        $exit = Artisan::call('ota:seed-access-demo-users', [
            '--force' => true,
            '--password' => 'DemoPass123!',
        ]);

        $this->assertSame(1, $exit);
        $this->assertSame($beforeCount, User::query()->count());
        $this->assertDatabaseMissing('users', ['email' => 'agent@demo.ota']);
        $this->assertStringContainsString('refusing to overwrite', strtolower(Artisan::output()));
    }

    public function test_command_reports_email_only_login_when_username_column_missing(): void
    {
        $this->seed(OtaFoundationSeeder::class);

        Schema::table('users', static function ($table): void {
            $table->dropUnique('users_username_unique');
            $table->dropColumn('username');
        });

        $exit = Artisan::call('ota:seed-access-demo-users', [
            '--force' => true,
            '--password' => 'DemoPass123!',
        ]);

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('email-only', strtolower(Artisan::output()));
        $this->assertDatabaseHas('users', [
            'email' => 'admin@ota.demo',
            'account_type' => AccountType::PlatformAdmin->value,
        ]);
    }

    public function test_command_is_idempotent(): void
    {
        $this->seedFoundationForAccessDemo();

        Artisan::call('ota:seed-access-demo-users', [
            '--force' => true,
            '--password' => 'DemoPass123!',
        ]);

        $snapshot = User::query()
            ->whereIn('email', [
                'admin@ota.demo',
                'agent@demo.ota',
                'agent.staff@demo.ota',
                'staff@demo.ota',
                'customer@demo.ota',
            ])
            ->orderBy('email')
            ->get(['email', 'account_type', 'name', 'status', 'username'])
            ->map(static fn (User $user): array => [
                'email' => $user->email,
                'account_type' => $user->account_type?->value,
                'name' => $user->name,
                'status' => $user->status?->value,
                'username' => $user->username,
            ])
            ->all();

        Artisan::call('ota:seed-access-demo-users', [
            '--force' => true,
            '--password' => 'DemoPass123!',
        ]);

        $after = User::query()
            ->whereIn('email', [
                'admin@ota.demo',
                'agent@demo.ota',
                'agent.staff@demo.ota',
                'staff@demo.ota',
                'customer@demo.ota',
            ])
            ->orderBy('email')
            ->get(['email', 'account_type', 'name', 'status', 'username'])
            ->map(static fn (User $user): array => [
                'email' => $user->email,
                'account_type' => $user->account_type?->value,
                'name' => $user->name,
                'status' => $user->status?->value,
                'username' => $user->username,
            ])
            ->all();

        $this->assertSame($snapshot, $after);
    }

    public function test_include_owner_email_seeds_configured_platform_owner(): void
    {
        $this->seedFoundationForAccessDemo();
        Config::set('ota.access_demo.owner_email', 'myworkhaseeb@gmail.com');

        User::query()->where('email', 'admin@ota.demo')->update(['username' => 'platformdemo']);

        Artisan::call('ota:seed-access-demo-users', [
            '--force' => true,
            '--password' => 'DemoPass123!',
            '--include-owner-email' => true,
        ]);

        $this->assertDatabaseHas('users', [
            'email' => 'myworkhaseeb@gmail.com',
            'account_type' => AccountType::PlatformAdmin->value,
            'username' => 'admin',
        ]);
    }

    protected function seedFoundationForAccessDemo(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $this->releaseLegacyDemoUsernameConflicts();
    }

    protected function releaseLegacyDemoUsernameConflicts(): void
    {
        $legacyUsernames = [
            'agent@ota.demo' => 'legacyagent',
            'staff@ota.demo' => 'legacystaff',
            'customer@ota.demo' => 'legacycustomer',
        ];

        foreach ($legacyUsernames as $email => $username) {
            User::query()->where('email', $email)->update(['username' => $username]);
        }
    }

    /**
     * @return array<string, string>
     */
    protected function expectedDemoUsernames(): array
    {
        return [
            'admin@ota.demo' => 'platformdemo',
            'agent@demo.ota' => 'agencyowner',
            'agent.staff@demo.ota' => 'agentstaff',
            'staff@demo.ota' => 'staffdemo',
            'customer@demo.ota' => 'customerdemo',
        ];
    }

    protected function assertDemoUsernamesAssigned(): void
    {
        foreach ($this->expectedDemoUsernames() as $email => $username) {
            $this->assertDatabaseHas('users', [
                'email' => $email,
                'username' => $username,
            ]);
        }
    }
}
