<?php

namespace App\Console\Commands;

use App\Enums\AccountType;
use App\Enums\UserAccountStatus;
use App\Models\Agency;
use App\Models\Agent;
use App\Models\DeveloperUser;
use App\Models\StaffProfile;
use App\Models\User;
use App\Services\Client\JetPakistanClientProfileProvisioner;
use App\Support\Agents\AgentPermission;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

/**
 * Upsert JetPK local demo users with known password and roles (local/testing only).
 */
class JetpkSeedDemoUsersCommand extends Command
{
    protected $signature = 'jetpk:seed-demo-users
                            {--force : Run even when APP_ENV=production (not recommended)}
                            {--skip-devcp : Do not upsert Dev CP demo developer user}';

    protected $description = 'Upsert JetPK demo users (admin/staff/agent/customer) for local OTP testing';

    /** @var list<string> */
    private const DEMO_EMAILS = [
        'admin@ota.demo',
        'staff@ota.demo',
        'agent@ota.demo',
        'agent.staff@demo.ota',
        'customer@ota.demo',
    ];

    public function handle(JetPakistanClientProfileProvisioner $jetPkProfile): int
    {
        if (app()->environment('production') && ! $this->option('force')) {
            $this->error('Refusing to seed demo users in production. Pass --force only if you explicitly intend this.');

            return self::FAILURE;
        }

        if (app()->environment('production')) {
            $this->warn('APP_ENV=production — demo user seed is running because --force was passed.');
        }

        $this->line('JetPK demo user seed');
        $this->newLine();

        try {
            $jetPkProfile->provision();
        } catch (\Throwable $e) {
            $this->warn('JetPK client profile provision skipped: '.$e->getMessage());
        }

        $foundation = new OtaFoundationSeeder;
        $foundation->seedSupplierConnectionPlaceholders = app()->environment(['local', 'testing']);
        $foundation->run();

        $agency = Agency::query()->where('slug', 'asif-travels')->first();
        if ($agency === null) {
            $this->error('Foundation agency missing after seed.');

            return self::FAILURE;
        }

        $definitions = [
            'admin@ota.demo' => [
                'name' => 'JetPK Admin',
                'username' => 'admin',
                'account_type' => AccountType::PlatformAdmin,
                'agency_role' => 'agency_admin',
            ],
            'staff@ota.demo' => [
                'name' => 'JetPK Staff',
                'username' => 'staff',
                'account_type' => AccountType::Staff,
                'agency_role' => 'staff',
            ],
            'agent@ota.demo' => [
                'name' => 'JetPK Agent',
                'username' => 'agent',
                'account_type' => AccountType::Agent,
                'agency_role' => 'agent',
            ],
            'customer@ota.demo' => [
                'name' => 'JetPK Customer',
                'username' => 'customer',
                'account_type' => AccountType::Customer,
                'agency_role' => 'customer',
            ],
            'agent.staff@demo.ota' => [
                'name' => 'JetPK Agent Staff',
                'username' => 'agentstaff',
                'account_type' => AccountType::AgentStaff,
                'agency_role' => 'agent_staff',
            ],
        ];

        $ownerAgent = null;

        foreach ($definitions as $email => $definition) {
            $meta = [];
            if ($definition['account_type'] === AccountType::AgentStaff) {
                $ownerAgent ??= Agent::query()
                    ->where('agency_id', $agency->id)
                    ->whereHas('user', fn ($q) => $q->where('email', 'agent@ota.demo'))
                    ->first();
                if ($ownerAgent === null) {
                    $this->warn('Skipping agent.staff@demo.ota — owner agent record missing');

                    continue;
                }
                $meta = [
                    'owner_agent_id' => $ownerAgent->id,
                    'agent_permissions' => AgentPermission::staffSelectable(),
                ];
            }

            $user = User::query()->updateOrCreate(
                ['email' => $email],
                [
                    'name' => $definition['name'],
                    'username' => $definition['username'],
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                    'current_agency_id' => $agency->id,
                    'account_type' => $definition['account_type'],
                    'status' => UserAccountStatus::Active,
                    'must_change_password' => false,
                    'meta' => $meta !== [] ? $meta : null,
                ],
            );

            $user->agencies()->syncWithoutDetaching([
                $agency->id => ['role' => $definition['agency_role']],
            ]);

            if ($definition['account_type'] === AccountType::Staff) {
                StaffProfile::query()->updateOrCreate(
                    ['agency_id' => $agency->id, 'user_id' => $user->id],
                    [
                        'job_title' => 'Operations Lead',
                        'department' => 'Operations',
                        'is_active' => true,
                    ],
                );
            }

            if ($definition['account_type'] === AccountType::Agent) {
                Agent::query()->updateOrCreate(
                    ['agency_id' => $agency->id, 'user_id' => $user->id],
                    [
                        'code' => 'AGT-JETPK-001',
                        'commission_percent' => 7.5,
                        'is_active' => true,
                        'meta' => ['tier' => 'gold'],
                    ],
                );
            }

            $this->info(sprintf(
                'Upserted %s (%s)',
                $email,
                $definition['account_type']->value,
            ));
        }

        if (! $this->option('skip-devcp')) {
            $this->seedDevCpDemoUser();
        }

        $this->newLine();
        $this->comment('Demo password for OTA users: password (not printed as hash).');
        $this->comment('Use fixed OTP from OTP_DEMO_FIXED_CODE when OTP_DEMO_FIXED_ENABLED=true in local.');

        return self::SUCCESS;
    }

    private function seedDevCpDemoUser(): void
    {
        $email = 'devcp@ota.demo';

        DeveloperUser::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => 'JetPK Dev CP Demo',
                'password' => Hash::make('password'),
                'is_active' => true,
                'must_change_password' => false,
            ],
        );

        $this->info('Upserted Dev CP developer user: '.$email.' (password-only login at /dev/cp/login)');
    }
}
