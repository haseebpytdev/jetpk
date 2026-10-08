<?php

namespace App\Console\Commands;

use App\Enums\AccountType;
use App\Enums\AgencyRole;
use App\Enums\BookingCancellationStatus;
use App\Enums\BookingCancellationType;
use App\Enums\BookingPaymentMethod;
use App\Enums\BookingPaymentStatus;
use App\Enums\BookingRefundStatus;
use App\Enums\BookingStatus;
use App\Enums\MarkupRuleStatus;
use App\Enums\MarkupRuleType;
use App\Enums\MarkupValueType;
use App\Enums\SupportTicketCategory;
use App\Enums\SupportTicketStatus;
use App\Enums\UserAccountStatus;
use App\Models\Agency;
use App\Models\AgencyUser;
use App\Models\Agent;
use App\Models\Booking;
use App\Models\BookingCancellationRequest;
use App\Models\BookingPayment;
use App\Models\BookingRefund;
use App\Models\MarkupRule;
use App\Models\StaffProfile;
use App\Models\SupportTicket;
use App\Models\User;
use App\Services\Agents\AgentWalletService;
use App\Support\Access\RolePermissionMatrix;
use App\Support\Agents\AgentPermission;
use App\Support\Staff\StaffPermission;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * JP-DASH-PROD-01 production certification QA context.
 * Idempotent reconcile for JetPakistan Production QA agency, JP-DASH-03 identities,
 * synthetic fixtures, and password sync via stdin. Never prints passwords.
 */
class JetpkDashboardProdCertQaCommand extends Command
{
    public const QA_AGENCY_SLUG = 'jetpk-production-qa';

    public const QA_RUN_ID = 'JPQA-20261008';

    private const LEGACY_AGENCY_SLUG = 'jp-dash-03-qa-agency';

    /**
     * @var array<string, array{
     *   marker: string,
     *   name: string,
     *   email: string,
     *   account_type: AccountType,
     *   membership_role: string,
     *   agency_role: ?AgencyRole,
     *   env_password: string
     * }>
     */
    private const ROLE_DEFINITIONS = [
        'admin' => [
            'marker' => 'jp-dash-03-qa-admin',
            'name' => 'JP-DASH-03 QA Admin',
            'email' => 'jp-dash-03-qa-admin@jetpakistan.pk',
            'account_type' => AccountType::PlatformAdmin,
            'membership_role' => 'platform_admin',
            'agency_role' => null,
            'env_password' => 'JP_DASH_03_QA_ADMIN_PASSWORD',
        ],
        'staff' => [
            'marker' => 'jp-dash-03-qa-staff',
            'name' => 'JP-DASH-03 QA Staff',
            'email' => 'jp-dash-03-qa-staff@jetpakistan.pk',
            'account_type' => AccountType::Staff,
            'membership_role' => 'staff',
            'agency_role' => null,
            'env_password' => 'JP_DASH_03_QA_STAFF_PASSWORD',
        ],
        'agent' => [
            'marker' => 'jp-dash-03-qa-agent',
            'name' => 'JP-DASH-03 QA Agent',
            'email' => 'jp-dash-03-qa-agent@jetpakistan.pk',
            'account_type' => AccountType::Agent,
            'membership_role' => 'agent',
            'agency_role' => AgencyRole::Owner,
            'env_password' => 'JP_DASH_03_QA_AGENT_PASSWORD',
        ],
        'agent_staff' => [
            'marker' => 'jp-dash-03-qa-agent-staff',
            'name' => 'JP-DASH-03 QA Agent Staff',
            'email' => 'jp-dash-03-qa-agent-staff@jetpakistan.pk',
            'account_type' => AccountType::AgentStaff,
            'membership_role' => 'agent_staff',
            'agency_role' => AgencyRole::SupportStaff,
            'env_password' => 'JP_DASH_03_QA_AGENT_STAFF_PASSWORD',
        ],
        'customer' => [
            'marker' => 'jp-dash-03-qa-customer',
            'name' => 'JP-DASH-03 QA Customer',
            'email' => 'jp-dash-03-qa-customer@jetpakistan.pk',
            'account_type' => AccountType::Customer,
            'membership_role' => 'customer',
            'agency_role' => null,
            'env_password' => 'JP_DASH_03_QA_CUSTOMER_PASSWORD',
        ],
    ];

    protected $signature = 'jetpk:dashboard-prod-cert-qa
        {action=status : status|reconcile|activate-all|sync-password|create-fixtures}
        {--role= : admin|staff|agent|agent_staff|customer (required for sync-password)}
        {--preset=staff_operator+staff_support : Staff permission preset for reconcile}';

    protected $description = 'JP-DASH-PROD-01 production certification QA context (sanitized output only)';

    public function handle(AgentWalletService $walletService): int
    {
        $action = (string) $this->argument('action');

        return match ($action) {
            'status' => $this->reportStatus(),
            'reconcile' => $this->reconcile($walletService),
            'activate-all' => $this->activateAll($walletService),
            'sync-password' => $this->syncPasswordFromStdin(),
            'create-fixtures' => $this->createFixtures($walletService),
            default => $this->invalidAction($action),
        };
    }

    private function invalidAction(string $action): int
    {
        $this->line('QA_PROD_CERT_COMMAND=INVALID_ACTION');
        $this->error("Unknown action: {$action}");

        return self::FAILURE;
    }

    private function qaAgency(): Agency
    {
        $agency = Agency::query()->firstOrCreate(
            ['slug' => self::QA_AGENCY_SLUG],
            [
                'name' => 'JetPakistan Production QA',
                'timezone' => 'Asia/Karachi',
                'settings' => [
                    'qa_only' => true,
                    'purpose' => 'production_operational_certification',
                    'jp_dash_03_qa' => true,
                    'qa_run_id' => self::QA_RUN_ID,
                ],
            ],
        );

        $settings = is_array($agency->settings) ? $agency->settings : [];
        $agency->forceFill([
            'name' => 'JetPakistan Production QA',
            'timezone' => 'Asia/Karachi',
            'settings' => array_merge($settings, [
                'qa_only' => true,
                'purpose' => 'production_operational_certification',
                'jp_dash_03_qa' => true,
                'qa_run_id' => self::QA_RUN_ID,
            ]),
        ])->save();

        return $agency;
    }

    private function reconcile(AgentWalletService $walletService): int
    {
        $agency = $this->qaAgency();
        $this->line('QA_AGENCY_ID='.$agency->id);
        $this->line('QA_AGENCY_SLUG='.self::QA_AGENCY_SLUG);

        foreach (array_keys(self::ROLE_DEFINITIONS) as $role) {
            $this->reconcileRole($role, $agency, $walletService);
        }

        $this->line('QA_RECONCILE=PASS');

        return self::SUCCESS;
    }

    private function reconcileRole(string $role, Agency $agency, AgentWalletService $walletService): void
    {
        $definition = self::ROLE_DEFINITIONS[$role];
        $user = User::query()
            ->where('username', $definition['marker'])
            ->first();

        if ($user === null && in_array($role, ['admin', 'agent', 'customer'], true)) {
            $this->call('jetpk:dash-03-qa-identities', ['role' => $role, 'action' => 'create']);
            $user = User::query()->where('username', $definition['marker'])->first();
        }

        if ($user === null && $role === 'staff') {
            $this->call('jetpk:dash-03-qa-staff', ['action' => 'create', '--preset' => 'staff_operator']);
            $user = User::query()->where('username', $definition['marker'])->first();
        }

        if ($user === null) {
            $user = $this->createMissingUser($role, $agency, $walletService);
        }

        if ($user === null) {
            $this->line('QA_'.$role.'_RECONCILE=missing');

            return;
        }

        $meta = is_array($user->meta) ? $user->meta : [];
        $meta['jp_dash_03_qa'] = true;
        $meta['qa_run_id'] = self::QA_RUN_ID;
        $meta['qa_only'] = true;

        if ($role === 'staff') {
            $permissions = array_values(array_unique(array_merge(
                StaffPermission::presetPermissions(StaffPermission::PresetOperator),
                StaffPermission::presetPermissions(StaffPermission::PresetSupport),
            )));
            $meta['staff_permissions'] = RolePermissionMatrix::normalizeStaffPermissions($permissions);
            $meta['permission_group'] = (string) $this->option('preset');
        }

        if ($role === 'agent_staff') {
            $ownerAgent = Agent::query()
                ->where('agency_id', $agency->id)
                ->whereHas('user', fn ($q) => $q->where('username', self::ROLE_DEFINITIONS['agent']['marker']))
                ->first();
            if ($ownerAgent !== null) {
                $meta['owner_agent_id'] = $ownerAgent->id;
                $meta['agent_permissions'] = AgentPermission::staffSelectable();
            }
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

        if ($role === 'staff') {
            StaffProfile::query()->updateOrCreate(
                ['user_id' => $user->id, 'agency_id' => $agency->id],
                [
                    'job_title' => 'Operations Staff',
                    'department' => 'Operations',
                    'is_active' => true,
                ],
            );
        }

        if ($role === 'agent') {
            $agent = Agent::query()->updateOrCreate(
                ['agency_id' => $agency->id, 'user_id' => $user->id],
                [
                    'code' => 'QA-JP-DASH-03',
                    'commission_percent' => 0,
                    'is_active' => true,
                    'meta' => [
                        'jp_dash_03_qa' => true,
                        'qa_run_id' => self::QA_RUN_ID,
                        'agency_name' => $agency->name,
                    ],
                ],
            );
            $wallet = $walletService->walletFor($agent);
            $wallet->forceFill(['balance' => 0, 'credit_limit' => 0])->save();
        }

        $this->line('QA_'.$role.'_USER_ID='.$user->id);
        $this->line('QA_'.$role.'_RECONCILE=PASS');
    }

    private function createMissingUser(string $role, Agency $agency, AgentWalletService $walletService): ?User
    {
        if (! isset(self::ROLE_DEFINITIONS[$role])) {
            return null;
        }

        $definition = self::ROLE_DEFINITIONS[$role];
        $password = $this->resolvePassword($role);
        if ($password === null) {
            $this->line('QA_'.$role.'_PASSWORD_REQUIRED=YES');

            return null;
        }

        return DB::transaction(function () use ($role, $definition, $password, $agency, $walletService): User {
            $meta = ['jp_dash_03_qa' => true, 'qa_run_id' => self::QA_RUN_ID, 'qa_only' => true];

            if ($role === 'staff') {
                $permissions = array_values(array_unique(array_merge(
                    StaffPermission::presetPermissions(StaffPermission::PresetOperator),
                    StaffPermission::presetPermissions(StaffPermission::PresetSupport),
                )));
                $meta['staff_permissions'] = RolePermissionMatrix::normalizeStaffPermissions($permissions);
                $meta['permission_group'] = (string) $this->option('preset');
            }

            if ($role === 'agent_staff') {
                $ownerAgent = Agent::query()
                    ->where('agency_id', $agency->id)
                    ->whereHas('user', fn ($q) => $q->where('username', self::ROLE_DEFINITIONS['agent']['marker']))
                    ->first();
                if ($ownerAgent === null) {
                    throw new \RuntimeException('QA agent owner missing for agent_staff provisioning');
                }
                $meta['owner_agent_id'] = $ownerAgent->id;
                $meta['agent_permissions'] = AgentPermission::staffSelectable();
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

            if ($role === 'staff') {
                StaffProfile::query()->create([
                    'user_id' => $user->id,
                    'agency_id' => $agency->id,
                    'job_title' => 'Operations Staff',
                    'department' => 'Operations',
                    'is_active' => true,
                ]);
            }

            if ($role === 'agent') {
                $agent = Agent::query()->create([
                    'agency_id' => $agency->id,
                    'user_id' => $user->id,
                    'code' => 'QA-JP-DASH-03',
                    'commission_percent' => 0,
                    'is_active' => true,
                    'meta' => ['jp_dash_03_qa' => true, 'qa_run_id' => self::QA_RUN_ID],
                ]);
                $wallet = $walletService->walletFor($agent);
                $wallet->forceFill(['balance' => 0, 'credit_limit' => 0])->save();
            }

            return $user;
        });
    }

    private function activateAll(AgentWalletService $walletService): int
    {
        $agency = $this->qaAgency();

        foreach (array_keys(self::ROLE_DEFINITIONS) as $role) {
            $definition = self::ROLE_DEFINITIONS[$role];
            $user = User::query()->where('username', $definition['marker'])->first();
            if ($user === null) {
                $this->line('QA_'.$role.'_ACTIVATE=missing');
                continue;
            }

            $user->forceFill([
                'status' => UserAccountStatus::Active,
                'email_verified_at' => $user->email_verified_at ?? now(),
                'must_change_password' => false,
                'current_agency_id' => $agency->id,
            ])->save();

            if ($role === 'agent') {
                Agent::query()->where('user_id', $user->id)->update(['is_active' => true]);
            }
            if ($role === 'staff') {
                StaffProfile::query()->where('user_id', $user->id)->update(['is_active' => true]);
            }

            $this->line('QA_'.$role.'_ACTIVATE=PASS');
        }

        $this->line('QA_ACTIVATE_ALL=PASS');

        return self::SUCCESS;
    }

    private function syncPasswordFromStdin(): int
    {
        $role = strtolower((string) $this->option('role'));
        if ($role === '' || ! isset(self::ROLE_DEFINITIONS[$role])) {
            $this->line('PASSWORD_SYNC=INVALID_ROLE');

            return self::FAILURE;
        }

        $password = trim((string) stream_get_contents(STDIN));
        if ($password === '') {
            $password = $this->resolvePassword($role) ?? '';
        }
        if ($password === '') {
            $this->line('PASSWORD_SYNC=MISSING_PASSWORD');

            return self::FAILURE;
        }

        $definition = self::ROLE_DEFINITIONS[$role];
        $user = User::query()->where('username', $definition['marker'])->first();
        if ($user === null) {
            $this->line('PASSWORD_SYNC=USER_MISSING');

            return self::FAILURE;
        }

        $user->forceFill([
            'password' => Hash::make($password),
            'must_change_password' => false,
        ])->save();
        DB::table('sessions')->where('user_id', $user->id)->delete();

        $this->line('QA_'.$role.'_USER_ID='.$user->id);
        $this->line('QA_AGENCY_ID='.(string) ($user->current_agency_id ?? ''));
        $this->line('PASSWORD_SYNC=PASS');

        return self::SUCCESS;
    }

    private function createFixtures(AgentWalletService $walletService): int
    {
        $agency = $this->qaAgency();
        $customer = User::query()->where('username', self::ROLE_DEFINITIONS['customer']['marker'])->first();
        $agentUser = User::query()->where('username', self::ROLE_DEFINITIONS['agent']['marker'])->first();
        $staff = User::query()->where('username', self::ROLE_DEFINITIONS['staff']['marker'])->first();
        $agent = $agentUser ? Agent::query()->where('user_id', $agentUser->id)->first() : null;

        if ($customer === null || $agent === null) {
            $this->line('QA_FIXTURES=MISSING_IDENTITIES');

            return self::FAILURE;
        }

        $bookingRef = self::QA_RUN_ID.'-BOOKING';

        $booking = Booking::query()->firstOrCreate(
            ['booking_reference' => $bookingRef, 'agency_id' => $agency->id],
            [
                'customer_id' => $customer->id,
                'agent_id' => $agent->id,
                'supplier' => 'QA_MANUAL_NO_EXTERNAL',
                'route' => 'KHI-DXB',
                'airline' => 'QA',
                'travel_date' => now()->addDays(30)->toDateString(),
                'status' => BookingStatus::PaymentPending,
                'payment_status' => 'submitted',
                'currency' => 'PKR',
                'balance_due' => 1000,
                'amount_paid' => 0,
                'source_channel' => 'qa_certification',
                'pnr' => null,
                'supplier_reference' => 'QA-NO-SUPPLIER-LOCATOR',
                'supplier_api_booking_id' => null,
                'notes' => 'JPQA synthetic booking — no supplier attachment',
                'meta' => [
                    'qa_run_id' => self::QA_RUN_ID,
                    'qa_only' => true,
                    'no_supplier_call' => true,
                ],
            ],
        );
        $this->line('QA_BOOKING_ID='.$booking->id);

        $payment = BookingPayment::query()->firstOrCreate(
            [
                'booking_id' => $booking->id,
                'payment_reference' => self::QA_RUN_ID.'-PAYMENT',
            ],
            [
                'agency_id' => $agency->id,
                'payer_user_id' => $customer->id,
                'method' => BookingPaymentMethod::BankTransfer,
                'status' => BookingPaymentStatus::Submitted,
                'amount' => 1000,
                'currency' => 'PKR',
                'notes' => 'JPQA synthetic payment — no gateway',
                'submitted_at' => now(),
                'meta' => ['qa_run_id' => self::QA_RUN_ID, 'qa_only' => true],
            ],
        );
        $this->line('QA_PAYMENT_ID='.$payment->id);

        $cancellation = BookingCancellationRequest::query()->firstOrCreate(
            ['booking_id' => $booking->id, 'agency_id' => $agency->id],
            [
                'requested_by' => $customer->id,
                'request_source' => 'qa_certification',
                'cancellation_type' => BookingCancellationType::BookingCancel,
                'reason' => 'JPQA synthetic cancellation request',
                'status' => BookingCancellationStatus::Requested,
                'meta' => ['qa_run_id' => self::QA_RUN_ID, 'qa_only' => true],
            ],
        );
        $this->line('QA_CANCELLATION_ID='.$cancellation->id);

        $refund = BookingRefund::query()->firstOrCreate(
            ['booking_id' => $booking->id, 'agency_id' => $agency->id],
            [
                'amount' => 500,
                'currency' => 'PKR',
                'method' => 'bank_transfer',
                'reference' => self::QA_RUN_ID.'-REFUND',
                'status' => BookingRefundStatus::Pending,
                'notes' => 'JPQA synthetic refund — no gateway settlement',
                'meta' => ['qa_run_id' => self::QA_RUN_ID, 'qa_only' => true],
            ],
        );
        $this->line('QA_REFUND_ID='.$refund->id);

        $ticket = SupportTicket::query()->firstOrCreate(
            ['ticket_reference' => self::QA_RUN_ID.'-TICKET'],
            [
                'agency_id' => $agency->id,
                'booking_id' => $booking->id,
                'created_by_user_id' => $customer->id,
                'assigned_to_user_id' => $staff?->id,
                'requester_name' => $customer->name,
                'requester_email' => $customer->email,
                'subject' => 'JPQA support ticket',
                'category' => SupportTicketCategory::Other,
                'priority' => 'normal',
                'status' => SupportTicketStatus::Open,
                'source' => 'qa_certification',
            ],
        );
        $this->line('QA_SUPPORT_TICKET_ID='.$ticket->id);

        $markup = MarkupRule::query()->firstOrCreate(
            ['agency_id' => $agency->id, 'name' => self::QA_RUN_ID.'-MARKUP'],
            [
                'rule_type' => MarkupRuleType::Global,
                'value' => 100,
                'value_type' => MarkupValueType::Fixed,
                'applies_to' => ['all'],
                'priority' => 999,
                'status' => MarkupRuleStatus::Active,
                'is_active' => true,
                'meta' => ['qa_run_id' => self::QA_RUN_ID, 'qa_only' => true],
            ],
        );
        $this->line('QA_MARKUP_ID='.$markup->id);

        $this->line('QA_FIXTURES=PASS');

        return self::SUCCESS;
    }

    private function resolvePassword(string $role): ?string
    {
        $envKey = self::ROLE_DEFINITIONS[$role]['env_password'];
        $password = (string) env($envKey, '');

        return $password !== '' ? $password : null;
    }

    private function reportStatus(): int
    {
        $agency = Agency::query()->where('slug', self::QA_AGENCY_SLUG)->first();
        $this->line('QA_AGENCY_SLUG='.self::QA_AGENCY_SLUG);
        $this->line('QA_AGENCY_ID='.(string) ($agency?->id ?? 'missing'));
        $this->line('QA_RUN_ID='.self::QA_RUN_ID);

        foreach (array_keys(self::ROLE_DEFINITIONS) as $role) {
            $definition = self::ROLE_DEFINITIONS[$role];
            $user = User::query()->where('username', $definition['marker'])->first();
            if ($user === null) {
                $this->line('QA_'.$role.'_STATUS=missing');
                continue;
            }
            $this->line('QA_'.$role.'_USER_ID='.$user->id);
            $this->line('QA_'.$role.'_STATUS='.$user->status->value);
            $this->line('QA_'.$role.'_AGENCY_ID='.(string) ($user->current_agency_id ?? ''));
            $this->line('QA_'.$role.'_EMAIL_VERIFIED='.($user->email_verified_at ? 'yes' : 'no'));
            $this->line('QA_'.$role.'_MUST_CHANGE_PASSWORD='.($user->must_change_password ? 'yes' : 'no'));
        }

        $booking = Booking::query()
            ->where('booking_reference', self::QA_RUN_ID.'-BOOKING')
            ->first();
        $this->line('QA_BOOKING_EXISTS='.($booking ? 'yes' : 'no'));

        return self::SUCCESS;
    }
}
