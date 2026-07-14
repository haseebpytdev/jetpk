<?php

namespace App\Console\Commands;

use App\Enums\AccountType;
use App\Enums\UserAccountStatus;
use App\Models\Agency;
use App\Models\AgencyUser;
use App\Models\Agent;
use App\Models\StaffProfile;
use App\Models\User;
use App\Support\Agents\AgentPermission;
use App\Support\References\CompactReferenceGenerator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Safely create or update demo portal users by email without deleting unrelated data.
 */
class SeedAccessDemoUsers extends Command
{
    protected $signature = 'ota:seed-access-demo-users
                            {--agency-id=1 : Agency id for agency-scoped demo users}
                            {--password= : Optional password applied to all target users}
                            {--dry-run : Preview changes without saving}
                            {--force : Required when promoting admin@ota.demo from agency_admin}
                            {--include-owner-email : Also seed the configured platform owner email as platform_admin}';

    protected $description = 'Safely create or update demo access users without deleting existing data';

    /**
     * @var list<array{email: string, name: string, account_type: AccountType, username: string|null, meta: array<string, mixed>, staff_profile: array<string, mixed>|null, requires_force_from: AccountType|null}>
     */
    protected array $definitions = [];

    /**
     * @var list<array{email: string, before: string|null, after: string, username_before: string|null, username_after: string|null, action: string}>
     */
    protected array $changeLog = [];

    protected bool $usernameColumnExists = false;

    /** @var list<array{email: string, password: string}> */
    protected array $credentials = [];

    protected ?string $resolvedPassword = null;

    protected bool $passwordProvided = false;

    public function handle(): int
    {
        $agencyId = (int) $this->option('agency-id');
        $agency = Agency::query()->find($agencyId);
        if ($agency === null) {
            $this->error('Agency not found for --agency-id='.$agencyId);

            return self::FAILURE;
        }

        $this->passwordProvided = filled($this->option('password'));
        $this->resolvedPassword = $this->passwordProvided
            ? (string) $this->option('password')
            : Str::password(16);

        $this->definitions = $this->buildDefinitions($agency);

        if (! $this->option('force') && $this->requiresForceForAdminPromotion()) {
            $this->error('Refusing to promote admin@ota.demo from agency_admin to platform_admin without --force.');

            return self::FAILURE;
        }

        $this->usernameColumnExists = Schema::hasColumn('users', 'username');
        if (! $this->usernameColumnExists) {
            $this->comment('Users table has no username column; login is email-only.');
        } elseif (! $this->validateUsernames()) {
            return self::FAILURE;
        }

        $this->info('OTA access demo user seeding'.($this->option('dry-run') ? ' (dry-run)' : '').'…');
        $this->line('Agency: '.$agency->name.' (#'.$agency->id.')');

        if ($this->option('dry-run')) {
            $this->previewChanges($agency);

            return self::SUCCESS;
        }

        DB::transaction(function () use ($agency): void {
            foreach ($this->definitions as $definition) {
                $this->upsertDemoUser($agency, $definition);
            }
        });

        $this->printChangeLog();
        $this->printCredentials();

        return self::SUCCESS;
    }

    /**
     * @return list<array{email: string, name: string, account_type: AccountType, username: string|null, meta: array<string, mixed>, staff_profile: array<string, mixed>|null, requires_force_from: AccountType|null}>
     */
    protected function buildDefinitions(Agency $agency): array
    {
        $definitions = [
            [
                'email' => 'admin@ota.demo',
                'name' => 'Platform Admin',
                'account_type' => AccountType::PlatformAdmin,
                'username' => 'platformdemo',
                'meta' => [],
                'staff_profile' => null,
                'requires_force_from' => AccountType::AgencyAdmin,
            ],
            [
                'email' => 'agent@demo.ota',
                'name' => 'Demo Agency Owner',
                'account_type' => AccountType::Agent,
                'username' => 'agencyowner',
                'meta' => ['agency_name' => 'Demo Agency'],
                'staff_profile' => null,
                'requires_force_from' => null,
            ],
            [
                'email' => 'staff@demo.ota',
                'name' => 'Demo Platform Staff',
                'account_type' => AccountType::Staff,
                'username' => 'staffdemo',
                'meta' => ['department' => 'Operations', 'role_title' => 'Demo Staff'],
                'staff_profile' => [
                    'job_title' => 'Demo Staff',
                    'department' => 'Operations',
                ],
                'requires_force_from' => null,
            ],
            [
                'email' => 'customer@demo.ota',
                'name' => 'Demo Customer',
                'account_type' => AccountType::Customer,
                'username' => 'customerdemo',
                'meta' => [],
                'staff_profile' => null,
                'requires_force_from' => null,
            ],
        ];

        if ($this->shouldIncludeOwnerEmail()) {
            $ownerEmail = trim((string) config('ota.access_demo.owner_email', ''));
            if ($ownerEmail !== '') {
                $definitions[] = [
                    'email' => $ownerEmail,
                    'name' => 'Platform Owner',
                    'account_type' => AccountType::PlatformAdmin,
                    'username' => strtolower($ownerEmail) === 'myworkhaseeb@gmail.com' ? 'admin' : null,
                    'meta' => [],
                    'staff_profile' => null,
                    'requires_force_from' => null,
                ];
            }
        }

        $definitions[] = [
            'email' => 'agent.staff@demo.ota',
            'name' => 'Demo Agency Staff',
            'account_type' => AccountType::AgentStaff,
            'username' => 'agentstaff',
            'meta' => [
                'agent_permissions' => AgentPermission::staffSelectable(),
            ],
            'staff_profile' => null,
            'requires_force_from' => null,
        ];

        return $definitions;
    }

    protected function shouldIncludeOwnerEmail(): bool
    {
        if ($this->option('include-owner-email')) {
            return true;
        }

        return (bool) config('ota.access_demo.include_owner_email', false);
    }

    protected function requiresForceForAdminPromotion(): bool
    {
        $existing = User::query()->where('email', 'admin@ota.demo')->first();
        if ($existing === null) {
            return false;
        }

        return $existing->account_type === AccountType::AgencyAdmin;
    }

    protected function previewChanges(Agency $agency): void
    {
        foreach ($this->definitions as $definition) {
            $existing = User::query()->where('email', $definition['email'])->first();
            $before = $existing?->account_type?->value;
            $after = $definition['account_type']->value;
            $action = $existing === null ? 'create' : 'update';
            $usernameBefore = $this->usernameColumnExists ? $existing?->username : null;
            $usernameAfter = $this->usernameColumnExists ? $definition['username'] : null;

            $this->changeLog[] = [
                'email' => $definition['email'],
                'before' => $before,
                'after' => $after,
                'username_before' => $usernameBefore,
                'username_after' => $usernameAfter,
                'action' => $action,
            ];

            $line = sprintf(
                '[dry-run] %s %s: %s → %s',
                $action,
                $definition['email'],
                $before ?? '(new)',
                $after,
            );

            if ($this->usernameColumnExists) {
                $line .= sprintf(
                    ' (username: %s → %s)',
                    $usernameBefore ?? '(new)',
                    $usernameAfter ?? '(unchanged)',
                );
            }

            $this->line($line);
        }

        $this->newLine();
        $this->comment('Dry-run complete. No changes were saved.');
    }

    /**
     * @param  array{email: string, name: string, account_type: AccountType, username: string|null, meta: array<string, mixed>, staff_profile: array<string, mixed>|null, requires_force_from: AccountType|null}  $definition
     */
    protected function upsertDemoUser(Agency $agency, array $definition): void
    {
        $existing = User::query()->where('email', $definition['email'])->first();
        $before = $existing?->account_type?->value;
        $meta = $this->mergeAccessMeta($existing, $definition);

        if ($definition['account_type'] === AccountType::AgentStaff) {
            $ownerAgent = $this->resolveOwnerAgent($agency);
            if ($ownerAgent === null) {
                $this->warn('Skipping agent.staff@demo.ota — owner agent record not found for agency #'.$agency->id);

                return;
            }

            $meta['owner_agent_id'] = $ownerAgent->id;
            $meta['agent_permissions'] = AgentPermission::staffSelectable();
        }

        $attributes = [
            'name' => $definition['name'],
            'account_type' => $definition['account_type'],
            'status' => UserAccountStatus::Active,
            'email_verified_at' => now(),
            'current_agency_id' => $agency->id,
            'meta' => $meta,
        ];

        if ($this->usernameColumnExists && $definition['username'] !== null) {
            $attributes['username'] = $definition['username'];
        }

        if ($existing === null || $this->passwordProvided) {
            $attributes['password'] = Hash::make($this->resolvedPassword);
        }

        $user = User::query()->updateOrCreate(
            ['email' => $definition['email']],
            $attributes,
        );

        AgencyUser::query()->updateOrCreate(
            ['agency_id' => $agency->id, 'user_id' => $user->id],
            ['role' => $definition['account_type']->value],
        );

        if ($definition['account_type'] === AccountType::Staff && $definition['staff_profile'] !== null) {
            StaffProfile::query()->updateOrCreate(
                ['agency_id' => $agency->id, 'user_id' => $user->id],
                [
                    'job_title' => $definition['staff_profile']['job_title'],
                    'department' => $definition['staff_profile']['department'],
                    'is_active' => true,
                ],
            );
        }

        if ($definition['account_type'] === AccountType::Agent) {
            Agent::query()->updateOrCreate(
                ['agency_id' => $agency->id, 'user_id' => $user->id],
                [
                    'code' => $this->resolveAgentCode($user),
                    'commission_percent' => 5.0,
                    'is_active' => true,
                    'meta' => array_merge(
                        is_array($user->meta) ? $user->meta : [],
                        ['agency_name' => $definition['meta']['agency_name'] ?? 'Demo Agency'],
                    ),
                ],
            );
        }

        $this->changeLog[] = [
            'email' => $definition['email'],
            'before' => $before,
            'after' => $definition['account_type']->value,
            'username_before' => $this->usernameColumnExists ? $existing?->username : null,
            'username_after' => $this->usernameColumnExists ? $definition['username'] : null,
            'action' => $before === null ? 'create' : 'update',
        ];

        if ($existing === null || $this->passwordProvided) {
            $this->credentials[] = [
                'email' => $definition['email'],
                'password' => $this->resolvedPassword,
            ];
        }
    }

    /**
     * @param  array{email: string, name: string, account_type: AccountType, username: string|null, meta: array<string, mixed>, staff_profile: array<string, mixed>|null, requires_force_from: AccountType|null}  $definition
     * @return array<string, mixed>
     */
    protected function mergeAccessMeta(?User $existing, array $definition): array
    {
        $meta = is_array($existing?->meta) ? $existing->meta : [];

        if ($definition['account_type'] !== AccountType::AgentStaff) {
            unset($meta['agent_permissions'], $meta['owner_agent_id']);
        }

        if ($definition['account_type'] === AccountType::Staff) {
            $meta['department'] = $definition['meta']['department'] ?? 'Operations';
            $meta['role_title'] = $definition['meta']['role_title'] ?? 'Demo Staff';
        }

        if ($definition['account_type'] === AccountType::Agent) {
            $meta['agency_name'] = $definition['meta']['agency_name'] ?? 'Demo Agency';
        }

        return $meta;
    }

    protected function resolveOwnerAgent(Agency $agency): ?Agent
    {
        $agentUser = User::query()->where('email', 'agent@demo.ota')->first();
        if ($agentUser !== null) {
            $existing = Agent::query()
                ->where('agency_id', $agency->id)
                ->where('user_id', $agentUser->id)
                ->first();
            if ($existing !== null) {
                return $existing;
            }
        }

        return Agent::query()
            ->where('agency_id', $agency->id)
            ->whereHas('user', static fn ($query) => $query->where('email', 'agent@demo.ota'))
            ->first();
    }

    protected function resolveAgentCode(User $user): string
    {
        $existing = Agent::query()
            ->where('user_id', $user->id)
            ->value('code');

        if (is_string($existing) && $existing !== '') {
            return $existing;
        }

        return app(CompactReferenceGenerator::class)->generateUnique('agents', 'code', 7);
    }

    protected function validateUsernames(): bool
    {
        $valid = true;

        foreach ($this->definitions as $definition) {
            if ($definition['username'] === null) {
                continue;
            }

            $targetUser = User::query()->where('email', $definition['email'])->first();
            $owner = User::query()->where('username', $definition['username'])->first();

            if ($owner === null) {
                continue;
            }

            if ($targetUser !== null && $owner->id === $targetUser->id) {
                continue;
            }

            $this->warn(sprintf(
                'Username "%s" for %s is already used by %s (#%d). Refusing to overwrite.',
                $definition['username'],
                $definition['email'],
                $owner->email,
                $owner->id,
            ));
            $valid = false;
        }

        return $valid;
    }

    protected function printChangeLog(): void
    {
        $this->newLine();
        $this->info('User access changes:');

        foreach ($this->changeLog as $entry) {
            $line = sprintf(
                '  %s %s: %s → %s',
                $entry['action'],
                $entry['email'],
                $entry['before'] ?? '(new)',
                $entry['after'],
            );

            if ($this->usernameColumnExists) {
                $line .= sprintf(
                    ' (username: %s → %s)',
                    $entry['username_before'] ?? '(new)',
                    $entry['username_after'] ?? '(unchanged)',
                );
            }

            $this->line($line);
        }
    }

    protected function printCredentials(): void
    {
        if ($this->credentials === []) {
            $this->newLine();
            $this->comment('No passwords were changed. Pass --password= to reset demo user passwords.');

            return;
        }

        $this->newLine();
        $this->info('Demo login credentials:');

        foreach ($this->credentials as $credential) {
            $this->line('  '.$credential['email'].' / '.$credential['password']);
        }
    }
}
