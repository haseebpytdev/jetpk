<?php

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Enums\UserAccountStatus;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\User;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class CleanupAccessDemoUsersCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_renames_legacy_usernames_safely(): void
    {
        $this->seedLiveAccessConflictState();

        $exit = Artisan::call('ota:cleanup-access-demo-users', [
            '--force' => true,
        ]);

        $this->assertSame(0, $exit);
        $this->assertDatabaseHas('users', [
            'email' => 'agent@ota.demo',
            'username' => 'legacyagent',
        ]);
        $this->assertDatabaseHas('users', [
            'email' => 'staff@ota.demo',
            'username' => 'legacystaff',
        ]);
        $this->assertDatabaseHas('users', [
            'email' => 'customer@ota.demo',
            'username' => 'legacycustomer',
        ]);
    }

    public function test_command_promotes_owner_and_preserves_admin_username(): void
    {
        $this->seedLiveAccessConflictState();

        Artisan::call('ota:cleanup-access-demo-users', [
            '--force' => true,
        ]);

        $this->assertDatabaseHas('users', [
            'email' => 'myworkhaseeb@gmail.com',
            'username' => 'admin',
            'account_type' => AccountType::PlatformAdmin->value,
        ]);
    }

    public function test_command_does_not_delete_users(): void
    {
        $this->seedLiveAccessConflictState();
        $beforeCount = User::query()->count();
        $unrelated = User::factory()->create([
            'email' => 'unrelated@example.test',
            'account_type' => AccountType::Customer,
        ]);

        Artisan::call('ota:cleanup-access-demo-users', [
            '--force' => true,
        ]);

        $this->assertSame($beforeCount + 1, User::query()->count());
        $this->assertDatabaseHas('users', [
            'id' => $unrelated->id,
            'email' => 'unrelated@example.test',
        ]);
    }

    public function test_command_does_not_delete_bookings(): void
    {
        $this->seedLiveAccessConflictState();
        $agency = Agency::query()->firstOrFail();
        $booking = Booking::factory()->for($agency)->create();

        Artisan::call('ota:cleanup-access-demo-users', [
            '--force' => true,
        ]);

        $this->assertSame(1, Booking::query()->count());
        $this->assertDatabaseHas('bookings', ['id' => $booking->id]);
    }

    public function test_dry_run_persists_nothing(): void
    {
        $this->seedLiveAccessConflictState();
        $beforeCount = User::query()->count();
        $ownerBefore = User::query()->where('email', 'myworkhaseeb@gmail.com')->firstOrFail();
        $agentBefore = User::query()->where('email', 'agent@ota.demo')->firstOrFail();

        $exit = Artisan::call('ota:cleanup-access-demo-users', [
            '--dry-run' => true,
        ]);

        $output = Artisan::output();

        $this->assertSame(0, $exit);
        $this->assertSame($beforeCount, User::query()->count());
        $this->assertSame($ownerBefore->account_type, User::query()->where('email', 'myworkhaseeb@gmail.com')->firstOrFail()->account_type);
        $this->assertSame($agentBefore->username, User::query()->where('email', 'agent@ota.demo')->firstOrFail()->username);
        $this->assertStringContainsString('dry-run', strtolower($output));
        $this->assertStringContainsString('username:', strtolower($output));
        $this->assertStringContainsString('account_type', strtolower($output));
    }

    public function test_command_is_idempotent(): void
    {
        $this->seedLiveAccessConflictState();

        Artisan::call('ota:cleanup-access-demo-users', [
            '--force' => true,
        ]);

        $snapshot = User::query()
            ->whereIn('email', [
                'myworkhaseeb@gmail.com',
                'agent@ota.demo',
                'staff@ota.demo',
                'customer@ota.demo',
            ])
            ->orderBy('email')
            ->get(['email', 'account_type', 'username', 'status'])
            ->map(static fn (User $user): array => [
                'email' => $user->email,
                'account_type' => $user->account_type?->value,
                'username' => $user->username,
                'status' => $user->status?->value,
            ])
            ->all();

        $exit = Artisan::call('ota:cleanup-access-demo-users', [
            '--force' => true,
        ]);

        $after = User::query()
            ->whereIn('email', [
                'myworkhaseeb@gmail.com',
                'agent@ota.demo',
                'staff@ota.demo',
                'customer@ota.demo',
            ])
            ->orderBy('email')
            ->get(['email', 'account_type', 'username', 'status'])
            ->map(static fn (User $user): array => [
                'email' => $user->email,
                'account_type' => $user->account_type?->value,
                'username' => $user->username,
                'status' => $user->status?->value,
            ])
            ->all();

        $this->assertSame(0, $exit);
        $this->assertSame($snapshot, $after);
    }

    public function test_deactivate_legacy_sets_old_demo_users_inactive(): void
    {
        $this->seedLiveAccessConflictState();

        Artisan::call('ota:cleanup-access-demo-users', [
            '--force' => true,
            '--deactivate-legacy' => true,
        ]);

        foreach (['agent@ota.demo', 'staff@ota.demo', 'customer@ota.demo'] as $email) {
            $this->assertDatabaseHas('users', [
                'email' => $email,
                'status' => UserAccountStatus::Inactive->value,
            ]);
        }
    }

    public function test_command_fails_when_target_username_belongs_to_unrelated_user(): void
    {
        $this->seedLiveAccessConflictState();

        User::factory()->create([
            'email' => 'unrelated@example.test',
            'username' => 'legacyagent',
            'account_type' => AccountType::Customer,
        ]);

        $beforeCount = User::query()->count();

        $exit = Artisan::call('ota:cleanup-access-demo-users', [
            '--force' => true,
        ]);

        $this->assertSame(1, $exit);
        $this->assertSame($beforeCount, User::query()->count());
        $this->assertDatabaseHas('users', [
            'email' => 'agent@ota.demo',
            'username' => 'agent',
        ]);
        $this->assertStringContainsString('refusing to overwrite', strtolower(Artisan::output()));
    }

    public function test_cleanup_then_seed_applies_new_demo_usernames(): void
    {
        $this->seedLiveAccessConflictState();

        Artisan::call('ota:cleanup-access-demo-users', [
            '--force' => true,
            '--deactivate-legacy' => true,
        ]);

        Artisan::call('ota:seed-access-demo-users', [
            '--force' => true,
            '--password' => 'DemoPass123!',
            '--include-owner-email' => true,
        ]);

        $this->assertDatabaseHas('users', [
            'email' => 'myworkhaseeb@gmail.com',
            'username' => 'admin',
            'account_type' => AccountType::PlatformAdmin->value,
        ]);
        $this->assertDatabaseHas('users', [
            'email' => 'admin@ota.demo',
            'username' => 'platformdemo',
            'account_type' => AccountType::PlatformAdmin->value,
        ]);
        $this->assertDatabaseHas('users', [
            'email' => 'agent@demo.ota',
            'username' => 'agencyowner',
        ]);
        $this->assertDatabaseHas('users', [
            'email' => 'agent.staff@demo.ota',
            'username' => 'agentstaff',
        ]);
        $this->assertDatabaseHas('users', [
            'email' => 'staff@demo.ota',
            'username' => 'staffdemo',
        ]);
        $this->assertDatabaseHas('users', [
            'email' => 'customer@demo.ota',
            'username' => 'customerdemo',
        ]);
    }

    protected function seedLiveAccessConflictState(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        Config::set('ota.access_demo.owner_email', 'myworkhaseeb@gmail.com');

        User::query()->where('email', 'admin@ota.demo')->update(['username' => 'pendingplatformdemo']);

        $agency = Agency::query()->firstOrFail();

        User::query()->updateOrCreate(
            ['email' => 'myworkhaseeb@gmail.com'],
            [
                'name' => 'Platform Owner',
                'username' => 'admin',
                'password' => bcrypt('password'),
                'email_verified_at' => now(),
                'current_agency_id' => $agency->id,
                'account_type' => AccountType::AgencyAdmin,
                'status' => UserAccountStatus::Active,
            ],
        );
    }
}
