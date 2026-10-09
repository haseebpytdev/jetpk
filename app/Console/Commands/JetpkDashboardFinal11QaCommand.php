<?php

namespace App\Console\Commands;

use App\Enums\AccountType;
use App\Enums\AgencyRole;
use App\Enums\UserAccountStatus;
use App\Models\Agency;
use App\Models\AgencyUser;
use App\Models\Agent;
use App\Models\User;
use App\Services\Agents\AgentWalletService;
use App\Support\Agencies\AgencyRolePermissionMatrix;
use App\Support\Qa\JetpkDashboardFinal11QaScope;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * JP-DASH-FINAL-11 production certification QA identities only.
 * Mutations require --execute. Never prints passwords.
 */
class JetpkDashboardFinal11QaCommand extends Command
{
    public const QA_RUN_ID = JetpkDashboardFinal11QaScope::QA_RUN_ID;

    /**
     * @var array<string, array{
     *   marker: string,
     *   name: string,
     *   email: string,
     *   account_type: AccountType,
     *   membership_role: string,
     *   agency_slug: string,
     *   agency_role: ?AgencyRole,
     *   agent_staff_template: ?AgencyRole,
     *   env_password: string,
     *   provision_agent: bool,
     *   agent_code: ?string
     * }>
     */
    public const ROLE_DEFINITIONS = [
        'customer_b' => [
            'marker' => 'jp-final-11-qa-customer-b',
            'name' => 'JP-FINAL-11 QA Customer B',
            'email' => 'jp-final-11-qa-customer-b@jetpakistan.pk',
            'account_type' => AccountType::Customer,
            'membership_role' => 'customer',
            'agency_slug' => JetpkDashboardFinal11QaScope::QA_AGENCY_A_SLUG,
            'agency_role' => null,
            'agent_staff_template' => null,
            'env_password' => 'JP_FINAL_11_QA_CUSTOMER_B_PASSWORD',
            'provision_agent' => false,
            'agent_code' => null,
        ],
        'agent_b' => [
            'marker' => 'jp-final-11-qa-agent-b',
            'name' => 'JP-FINAL-11 QA Agent B',
            'email' => 'jp-final-11-qa-agent-b@jetpakistan.pk',
            'account_type' => AccountType::Agent,
            'membership_role' => 'agent',
            'agency_slug' => JetpkDashboardFinal11QaScope::QA_AGENCY_B_SLUG,
            'agency_role' => AgencyRole::Owner,
            'agent_staff_template' => null,
            'env_password' => 'JP_FINAL_11_QA_AGENT_B_PASSWORD',
            'provision_agent' => true,
            'agent_code' => 'QA-JP-FINAL-11-B',
        ],
        'agent_staff_manager' => [
            'marker' => 'jp-final-11-qa-staff-manager',
            'name' => 'JP-FINAL-11 QA Staff Manager',
            'email' => 'jp-final-11-qa-staff-manager@jetpakistan.pk',
            'account_type' => AccountType::AgentStaff,
            'membership_role' => 'agent_staff',
            'agency_slug' => JetpkDashboardFinal11QaScope::QA_AGENCY_A_SLUG,
            'agency_role' => AgencyRole::Manager,
            'agent_staff_template' => AgencyRole::Manager,
            'env_password' => 'JP_FINAL_11_QA_STAFF_MANAGER_PASSWORD',
            'provision_agent' => false,
            'agent_code' => null,
        ],
        'agent_staff_accountant' => [
            'marker' => 'jp-final-11-qa-staff-accountant',
            'name' => 'JP-FINAL-11 QA Staff Accountant',
            'email' => 'jp-final-11-qa-staff-accountant@jetpakistan.pk',
            'account_type' => AccountType::AgentStaff,
            'membership_role' => 'agent_staff',
            'agency_slug' => JetpkDashboardFinal11QaScope::QA_AGENCY_A_SLUG,
            'agency_role' => AgencyRole::Accountant,
            'agent_staff_template' => AgencyRole::Accountant,
            'env_password' => 'JP_FINAL_11_QA_STAFF_ACCOUNTANT_PASSWORD',
            'provision_agent' => false,
            'agent_code' => null,
        ],
        'agent_staff_sales' => [
            'marker' => 'jp-final-11-qa-staff-sales',
            'name' => 'JP-FINAL-11 QA Staff Sales',
            'email' => 'jp-final-11-qa-staff-sales@jetpakistan.pk',
            'account_type' => AccountType::AgentStaff,
            'membership_role' => 'agent_staff',
            'agency_slug' => JetpkDashboardFinal11QaScope::QA_AGENCY_A_SLUG,
            'agency_role' => AgencyRole::SalesAgent,
            'agent_staff_template' => AgencyRole::SalesAgent,
            'env_password' => 'JP_FINAL_11_QA_STAFF_SALES_PASSWORD',
            'provision_agent' => false,
            'agent_code' => null,
        ],
        'agent_staff_support' => [
            'marker' => 'jp-final-11-qa-staff-support',
            'name' => 'JP-FINAL-11 QA Staff Support',
            'email' => 'jp-final-11-qa-staff-support@jetpakistan.pk',
            'account_type' => AccountType::AgentStaff,
            'membership_role' => 'agent_staff',
            'agency_slug' => JetpkDashboardFinal11QaScope::QA_AGENCY_A_SLUG,
            'agency_role' => AgencyRole::SupportStaff,
            'agent_staff_template' => AgencyRole::SupportStaff,
            'env_password' => 'JP_FINAL_11_QA_STAFF_SUPPORT_PASSWORD',
            'provision_agent' => false,
            'agent_code' => null,
        ],
        'agent_staff_ticketing' => [
            'marker' => 'jp-final-11-qa-staff-ticketing',
            'name' => 'JP-FINAL-11 QA Staff Ticketing',
            'email' => 'jp-final-11-qa-staff-ticketing@jetpakistan.pk',
            'account_type' => AccountType::AgentStaff,
            'membership_role' => 'agent_staff',
            'agency_slug' => JetpkDashboardFinal11QaScope::QA_AGENCY_A_SLUG,
            'agency_role' => AgencyRole::TicketingStaff,
            'agent_staff_template' => AgencyRole::TicketingStaff,
            'env_password' => 'JP_FINAL_11_QA_STAFF_TICKETING_PASSWORD',
            'provision_agent' => false,
            'agent_code' => null,
        ],
        'agent_staff_viewer' => [
            'marker' => 'jp-final-11-qa-staff-viewer',
            'name' => 'JP-FINAL-11 QA Staff Viewer',
            'email' => 'jp-final-11-qa-staff-viewer@jetpakistan.pk',
            'account_type' => AccountType::AgentStaff,
            'membership_role' => 'agent_staff',
            'agency_slug' => JetpkDashboardFinal11QaScope::QA_AGENCY_A_SLUG,
            'agency_role' => AgencyRole::Viewer,
            'agent_staff_template' => AgencyRole::Viewer,
            'env_password' => 'JP_FINAL_11_QA_STAFF_VIEWER_PASSWORD',
            'provision_agent' => false,
            'agent_code' => null,
        ],
        'legacy_agency_admin' => [
            'marker' => 'jp-final-11-qa-legacy-agency-admin',
            'name' => 'JP-FINAL-11 QA Legacy Agency Admin',
            'email' => 'jp-final-11-qa-legacy-agency-admin@jetpakistan.pk',
            'account_type' => AccountType::AgencyAdmin,
            'membership_role' => 'agency_admin',
            'agency_slug' => JetpkDashboardFinal11QaScope::LEGACY_AGENCY_SLUG,
            'agency_role' => null,
            'agent_staff_template' => null,
            'env_password' => 'JP_FINAL_11_QA_LEGACY_AGENCY_ADMIN_PASSWORD',
            'provision_agent' => false,
            'agent_code' => null,
        ],
    ];

    protected $signature = 'jetpk:dashboard-final-11-qa
        {action=status : status|reconcile|sync-password}
        {--role= : Role key (required for sync-password)}
        {--execute : Required acknowledgement for reconcile or sync-password mutations}';

    protected $description = 'JP-DASH-FINAL-11 dedicated production QA identities (sanitized output only)';

    public function handle(AgentWalletService $walletService): int
    {
        return match ((string) $this->argument('action')) {
            'status' => $this->reportStatus(),
            'reconcile' => $this->reconcile($walletService),
            'sync-password' => $this->syncPasswordFromStdin(),
            default => $this->invalidAction((string) $this->argument('action')),
        };
    }

    private function invalidAction(string $action): int
    {
        $this->line('FINAL11_QA_COMMAND=INVALID_ACTION');
        $this->error("Unknown action: {$action}");

        return self::FAILURE;
    }

    private function reportStatus(): int
    {
        $this->line('FINAL11_QA_RUN_ID='.self::QA_RUN_ID);
        $this->line('FINAL11_QA_AGENCY_A_SLUG='.JetpkDashboardFinal11QaScope::QA_AGENCY_A_SLUG);
        $this->line('FINAL11_QA_AGENCY_B_SLUG='.JetpkDashboardFinal11QaScope::QA_AGENCY_B_SLUG);
        $this->line('FINAL11_LEGACY_AGENCY_SLUG='.JetpkDashboardFinal11QaScope::LEGACY_AGENCY_SLUG);

        foreach (array_keys(self::ROLE_DEFINITIONS) as $roleKey) {
            $definition = self::ROLE_DEFINITIONS[$roleKey];
            $user = User::query()->where('username', $definition['marker'])->first();
            if ($user === null) {
                $this->line('FINAL11_'.$roleKey.'_STATUS=missing');

                continue;
            }
            $this->line('FINAL11_'.$roleKey.'_USER_ID='.$user->id);
            $this->line('FINAL11_'.$roleKey.'_STATUS='.$user->status->value);
            $this->line('FINAL11_'.$roleKey.'_ACCOUNT_TYPE='.$user->account_type->value);
            $this->line('FINAL11_'.$roleKey.'_AGENCY_ID='.(string) ($user->current_agency_id ?? ''));
            $permissions = is_array($user->meta['agent_permissions'] ?? null)
                ? count($user->meta['agent_permissions'])
                : 0;
            $this->line('FINAL11_'.$roleKey.'_AGENT_PERMISSION_COUNT='.$permissions);
        }

        return self::SUCCESS;
    }

    private function reconcile(AgentWalletService $walletService): int
    {
        if (! $this->option('execute')) {
            $this->line('FINAL11_RECONCILE=EXECUTE_REQUIRED');

            return self::FAILURE;
        }

        $agencyA = $this->resolveAgencyA();
        if ($agencyA === null) {
            return self::FAILURE;
        }

        $agencyB = $this->ensureOwnedAgencyB();
        if ($agencyB === null) {
            return self::FAILURE;
        }

        $legacyAgency = Agency::query()->where('slug', JetpkDashboardFinal11QaScope::LEGACY_AGENCY_SLUG)->first();
        if ($legacyAgency === null || ! JetpkDashboardFinal11QaScope::agencyIsSafeToMutate($legacyAgency, JetpkDashboardFinal11QaScope::LEGACY_AGENCY_SLUG)) {
            $this->line('FINAL11_LEGACY_AGENCY=ambiguous_or_missing');

            return self::FAILURE;
        }

        $this->line('FINAL11_QA_AGENCY_A_ID='.$agencyA->id);
        $this->line('FINAL11_QA_AGENCY_B_ID='.$agencyB->id);
        $this->line('FINAL11_LEGACY_AGENCY_ID='.$legacyAgency->id);

        foreach (array_keys(self::ROLE_DEFINITIONS) as $roleKey) {
            if (! $this->reconcileRole($roleKey, $walletService)) {
                $this->line('FINAL11_QA_RECONCILE=FAIL');

                return self::FAILURE;
            }
        }

        $this->line('FINAL11_QA_RECONCILE=PASS');

        return self::SUCCESS;
    }

    private function resolveAgencyA(): ?Agency
    {
        $agency = Agency::query()->where('slug', JetpkDashboardFinal11QaScope::QA_AGENCY_A_SLUG)->first();
        if ($agency === null) {
            $this->line('FINAL11_QA_AGENCY_A=missing');

            return null;
        }

        if (! JetpkDashboardFinal11QaScope::agencyIsSafeToMutate($agency, JetpkDashboardFinal11QaScope::QA_AGENCY_A_SLUG)) {
            $this->line('FINAL11_QA_AGENCY_A=ambiguous_ownership');

            return null;
        }

        $settings = is_array($agency->settings) ? $agency->settings : [];
        $agency->forceFill([
            'settings' => array_merge($settings, [
                'jp_final_11_qa' => true,
                'qa_run_id' => self::QA_RUN_ID,
            ]),
        ])->save();

        return $agency;
    }

    private function ensureOwnedAgencyB(): ?Agency
    {
        $slug = JetpkDashboardFinal11QaScope::QA_AGENCY_B_SLUG;
        $existing = Agency::query()->where('slug', $slug)->first();
        if ($existing !== null) {
            if (! JetpkDashboardFinal11QaScope::agencyIsSafeToMutate($existing, $slug)) {
                $this->line('FINAL11_QA_AGENCY_B=ambiguous_ownership');

                return null;
            }

            $settings = is_array($existing->settings) ? $existing->settings : [];
            $existing->forceFill([
                'name' => 'JetPakistan Production QA B',
                'settings' => array_merge($settings, [
                    'qa_only' => true,
                    'jp_final_11_qa' => true,
                    'purpose' => 'jp_dash_final_11_certification',
                    'qa_run_id' => self::QA_RUN_ID,
                ]),
            ])->save();

            return $existing;
        }

        return Agency::query()->create([
            'slug' => $slug,
            'name' => 'JetPakistan Production QA B',
            'timezone' => 'Asia/Karachi',
            'settings' => [
                'qa_only' => true,
                'jp_final_11_qa' => true,
                'purpose' => 'jp_dash_final_11_certification',
                'qa_run_id' => self::QA_RUN_ID,
            ],
        ]);
    }

    private function reconcileRole(string $roleKey, AgentWalletService $walletService): bool
    {
        $definition = self::ROLE_DEFINITIONS[$roleKey];
        $agency = Agency::query()->where('slug', $definition['agency_slug'])->first();
        if ($agency === null) {
            $this->line('FINAL11_'.$roleKey.'_RECONCILE=agency_missing');

            return false;
        }

        $user = User::query()->where('username', $definition['marker'])->first();
        if ($user !== null && ! $this->canMutateUser($user, $definition)) {
            $this->line('FINAL11_'.$roleKey.'_RECONCILE=ambiguous_ownership');

            return false;
        }

        if ($user === null) {
            $user = $this->createUser($roleKey, $agency, $walletService);
        }

        if ($user === null) {
            $this->line('FINAL11_'.$roleKey.'_RECONCILE=missing');

            return false;
        }

        $meta = is_array($user->meta) ? $user->meta : [];
        $meta['jp_final_11_qa'] = true;
        $meta['qa_run_id'] = self::QA_RUN_ID;
        $meta['qa_only'] = true;

        if ($definition['agent_staff_template'] instanceof AgencyRole) {
            $ownerAgent = Agent::query()
                ->where('agency_id', $agency->id)
                ->whereHas('user', fn ($q) => $q->where('account_type', AccountType::Agent))
                ->orderBy('id')
                ->first();
            if ($ownerAgent === null) {
                $this->line('FINAL11_'.$roleKey.'_RECONCILE=owner_agent_missing');

                return false;
            }
            $meta['owner_agent_id'] = $ownerAgent->id;
            $meta['agent_permissions'] = AgencyRolePermissionMatrix::templatePermissionsForAgentStaff(
                $definition['agent_staff_template'],
            );
        }

        $user->forceFill([
            'name' => $definition['name'],
            'email' => $definition['email'],
            'account_type' => $definition['account_type'],
            'current_agency_id' => $agency->id,
            'status' => UserAccountStatus::Active,
            'email_verified_at' => $user->email_verified_at ?? now(),
            'must_change_password' => false,
            'meta' => $meta,
        ])->save();

        $membership = [
            'agency_id' => $agency->id,
            'user_id' => $user->id,
            'role' => $definition['membership_role'],
        ];
        if ($definition['agency_role'] instanceof AgencyRole) {
            $membership['agency_role'] = $definition['agency_role']->value;
        }

        AgencyUser::query()->updateOrCreate(
            ['agency_id' => $agency->id, 'user_id' => $user->id],
            $membership,
        );

        if ($definition['provision_agent']) {
            $agentCode = $definition['agent_code'] ?? 'QA-JP-FINAL-11';
            $agent = Agent::query()->updateOrCreate(
                ['agency_id' => $agency->id, 'user_id' => $user->id],
                [
                    'code' => $agentCode,
                    'commission_percent' => 0,
                    'is_active' => true,
                    'meta' => [
                        'jp_final_11_qa' => true,
                        'qa_run_id' => self::QA_RUN_ID,
                    ],
                ],
            );
            $wallet = $walletService->walletFor($agent);
            $wallet->forceFill(['balance' => 0, 'credit_limit' => 0])->save();
        }

        $this->line('FINAL11_'.$roleKey.'_USER_ID='.$user->id);
        $this->line('FINAL11_'.$roleKey.'_RECONCILE=PASS');

        return true;
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function canMutateUser(User $user, array $definition): bool
    {
        if ($user->username !== $definition['marker']) {
            return false;
        }

        if ($user->account_type !== $definition['account_type']) {
            return false;
        }

        if (JetpkDashboardFinal11QaScope::isOwnedUser($user)) {
            return true;
        }

        return JetpkDashboardFinal11QaScope::isOwnedUsername((string) $user->username);
    }

    private function createUser(string $roleKey, Agency $agency, AgentWalletService $walletService): ?User
    {
        $definition = self::ROLE_DEFINITIONS[$roleKey];
        $password = $this->resolvePassword($roleKey);
        if ($password === null) {
            $this->line('FINAL11_'.$roleKey.'_PASSWORD_REQUIRED=YES');
            $this->line('FINAL11_MISSING_ENV='.$definition['env_password']);

            return null;
        }

        return DB::transaction(function () use ($roleKey, $definition, $password, $agency, $walletService): User {
            $meta = [
                'jp_final_11_qa' => true,
                'qa_run_id' => self::QA_RUN_ID,
                'qa_only' => true,
            ];

            if ($definition['agent_staff_template'] instanceof AgencyRole) {
                $ownerAgent = Agent::query()
                    ->where('agency_id', $agency->id)
                    ->whereHas('user', fn ($q) => $q->where('account_type', AccountType::Agent))
                    ->orderBy('id')
                    ->first();
                if ($ownerAgent === null) {
                    throw new \RuntimeException('owner_agent_missing');
                }
                $meta['owner_agent_id'] = $ownerAgent->id;
                $meta['agent_permissions'] = AgencyRolePermissionMatrix::templatePermissionsForAgentStaff(
                    $definition['agent_staff_template'],
                );
            }

            $user = User::query()->create([
                'name' => $definition['name'],
                'email' => $definition['email'],
                'username' => $definition['marker'],
                'password' => Hash::make($password),
                'account_type' => $definition['account_type'],
                'current_agency_id' => $agency->id,
                'status' => UserAccountStatus::Active,
                'email_verified_at' => now(),
                'must_change_password' => false,
                'meta' => $meta,
            ]);

            $membership = [
                'agency_id' => $agency->id,
                'user_id' => $user->id,
                'role' => $definition['membership_role'],
            ];
            if ($definition['agency_role'] instanceof AgencyRole) {
                $membership['agency_role'] = $definition['agency_role']->value;
            }
            AgencyUser::query()->create($membership);

            if ($definition['provision_agent']) {
                $agentCode = $definition['agent_code'] ?? 'QA-JP-FINAL-11';
                $agent = Agent::query()->create([
                    'agency_id' => $agency->id,
                    'user_id' => $user->id,
                    'code' => $agentCode,
                    'commission_percent' => 0,
                    'is_active' => true,
                    'meta' => ['jp_final_11_qa' => true, 'qa_run_id' => self::QA_RUN_ID],
                ]);
                $wallet = $walletService->walletFor($agent);
                $wallet->forceFill(['balance' => 0, 'credit_limit' => 0])->save();
            }

            return $user;
        });
    }

    private function syncPasswordFromStdin(): int
    {
        if (! $this->option('execute')) {
            $this->line('FINAL11_PASSWORD_SYNC=EXECUTE_REQUIRED');

            return self::FAILURE;
        }

        $roleKey = strtolower((string) $this->option('role'));
        if ($roleKey === '' || ! isset(self::ROLE_DEFINITIONS[$roleKey])) {
            $this->line('FINAL11_PASSWORD_SYNC=INVALID_ROLE');

            return self::FAILURE;
        }

        $password = trim((string) stream_get_contents(STDIN));
        if ($password === '') {
            $this->line('FINAL11_PASSWORD_SYNC=MISSING_PASSWORD_STDIN');

            return self::FAILURE;
        }

        $definition = self::ROLE_DEFINITIONS[$roleKey];
        $user = User::query()->where('username', $definition['marker'])->first();
        if ($user === null || ! $this->canMutateUser($user, $definition)) {
            $this->line('FINAL11_PASSWORD_SYNC=USER_MISSING_OR_AMBIGUOUS');

            return self::FAILURE;
        }

        $user->forceFill([
            'password' => Hash::make($password),
            'must_change_password' => false,
        ])->save();
        DB::table('sessions')->where('user_id', $user->id)->delete();

        $this->line('FINAL11_'.$roleKey.'_USER_ID='.$user->id);
        $this->line('FINAL11_PASSWORD_SYNC=PASS');

        return self::SUCCESS;
    }

    private function resolvePassword(string $roleKey): ?string
    {
        $envKey = self::ROLE_DEFINITIONS[$roleKey]['env_password'];
        $password = (string) env($envKey, '');

        return $password !== '' ? $password : null;
    }
}
