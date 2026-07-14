<?php

namespace App\Support\Access;

use App\Enums\AccountType;
use App\Support\Agents\AgentPermission;
use App\Support\Staff\StaffPermission;

/**
 * Read-only capability matrix by account type, plus editable permission labels for agent staff and staff.
 * Agent staff keys: AgentPermission / hasAgentPermission. Staff keys: StaffPermission / hasStaffPermission.
 */
final class RolePermissionMatrix
{
    public const Allowed = 'Allowed';

    public const Limited = 'Limited';

    public const Denied = 'Denied';

    /**
     * @return list<array<string, string>>
     */
    public static function areas(): array
    {
        return [
            ['area' => 'Admin dashboard', 'platform_admin' => self::Allowed, 'agency_admin' => self::Denied, 'staff' => self::Denied, 'agent' => self::Denied, 'agent_staff' => self::Denied, 'customer' => self::Denied],
            ['area' => 'Staff portal', 'platform_admin' => self::Denied, 'agency_admin' => self::Denied, 'staff' => self::Allowed, 'agent' => self::Denied, 'agent_staff' => self::Denied, 'customer' => self::Denied],
            ['area' => 'Agent portal', 'platform_admin' => self::Denied, 'agency_admin' => self::Denied, 'staff' => self::Denied, 'agent' => self::Allowed, 'agent_staff' => self::Limited, 'customer' => self::Denied],
            ['area' => 'Customer portal', 'platform_admin' => self::Denied, 'agency_admin' => self::Denied, 'staff' => self::Denied, 'agent' => self::Denied, 'agent_staff' => self::Denied, 'customer' => self::Allowed],
            ['area' => 'Bookings', 'platform_admin' => self::Allowed, 'agency_admin' => self::Denied, 'staff' => self::Allowed, 'agent' => self::Limited, 'agent_staff' => self::Limited, 'customer' => self::Limited],
            ['area' => 'Payments', 'platform_admin' => self::Allowed, 'agency_admin' => self::Denied, 'staff' => self::Limited, 'agent' => self::Limited, 'agent_staff' => self::Limited, 'customer' => self::Limited],
            ['area' => 'Ticketing', 'platform_admin' => self::Allowed, 'agency_admin' => self::Denied, 'staff' => self::Limited, 'agent' => self::Denied, 'agent_staff' => self::Denied, 'customer' => self::Denied],
            ['area' => 'Documents', 'platform_admin' => self::Allowed, 'agency_admin' => self::Denied, 'staff' => self::Allowed, 'agent' => self::Limited, 'agent_staff' => self::Limited, 'customer' => self::Limited],
            ['area' => 'Users & access', 'platform_admin' => self::Allowed, 'agency_admin' => self::Denied, 'staff' => self::Denied, 'agent' => self::Denied, 'agent_staff' => self::Denied, 'customer' => self::Denied],
            ['area' => 'Branding & settings', 'platform_admin' => self::Allowed, 'agency_admin' => self::Denied, 'staff' => self::Limited, 'agent' => self::Denied, 'agent_staff' => self::Denied, 'customer' => self::Denied],
            ['area' => 'Supplier credentials', 'platform_admin' => self::Allowed, 'agency_admin' => self::Denied, 'staff' => self::Denied, 'agent' => self::Denied, 'agent_staff' => self::Denied, 'customer' => self::Denied],
            ['area' => 'Commissions', 'platform_admin' => self::Allowed, 'agency_admin' => self::Denied, 'staff' => self::Limited, 'agent' => self::Limited, 'agent_staff' => self::Denied, 'customer' => self::Denied],
            ['area' => 'Reports', 'platform_admin' => self::Allowed, 'agency_admin' => self::Denied, 'staff' => self::Limited, 'agent' => self::Allowed, 'agent_staff' => self::Limited, 'customer' => self::Denied],
            ['area' => 'Master ledger', 'platform_admin' => self::Allowed, 'agency_admin' => self::Denied, 'staff' => self::Limited, 'agent' => self::Denied, 'agent_staff' => self::Denied, 'customer' => self::Denied],
            ['area' => 'Agency ledger', 'platform_admin' => self::Denied, 'agency_admin' => self::Denied, 'staff' => self::Denied, 'agent' => self::Allowed, 'agent_staff' => self::Limited, 'customer' => self::Denied],
        ];
    }

    /**
     * @return list<array{area: string, access: string}>
     */
    public static function effectiveFor(AccountType $accountType): array
    {
        $key = $accountType->value;

        return array_map(
            static fn (array $row): array => [
                'area' => $row['area'],
                'access' => $row[$key] ?? self::Denied,
            ],
            self::areas(),
        );
    }

    public static function showsMatrix(?AccountType $accountType): bool
    {
        return $accountType !== null && $accountType !== AccountType::Customer;
    }

    public static function isEditable(AccountType $accountType): bool
    {
        return in_array($accountType, [AccountType::AgentStaff, AccountType::Staff], true);
    }

    public static function scopeNote(AccountType $accountType): ?string
    {
        return match ($accountType) {
            AccountType::PlatformAdmin => 'Cross-agency platform scope. Full access across all capability areas.',
            AccountType::AgencyAdmin => 'Legacy account type — disabled. Users are routed to the legacy notice page and cannot access admin or platform controls.',
            AccountType::Staff => 'Granular staff portal permissions. Toggle individual capabilities below. Users without saved permissions keep legacy full staff access for most modules; client page settings requires explicit staff.page_settings.manage.',
            AccountType::Agent => 'Full agent portal access. Agent-side permissions are implicit for the portal owner.',
            AccountType::AgentStaff => 'Granular agent portal permissions. Toggle individual capabilities below.',
            default => null,
        };
    }

    /**
     * @return array<string, string>
     */
    public static function agentStaffPermissionLabels(): array
    {
        return array_intersect_key(
            AgentPermission::labels(),
            array_flip(AgentPermission::staffSelectable()),
        );
    }

    /**
     * Display-only grouping for read-only effective access matrices.
     *
     * @return array<string, list<string>>
     */
    public static function effectiveAccessModuleGroups(): array
    {
        return [
            'Dashboard' => ['Admin dashboard', 'Staff portal', 'Agent portal', 'Customer portal'],
            'Bookings' => ['Bookings', 'Documents'],
            'Payments' => ['Payments'],
            'Ticketing' => ['Ticketing'],
            'Staff & access' => ['Users & access'],
            'Reports' => ['Reports'],
            'Finance' => ['Commissions', 'Master ledger', 'Agency ledger'],
            'Settings' => ['Branding & settings'],
            'Suppliers & API' => ['Supplier credentials'],
        ];
    }

    /**
     * Display-only grouping for editable agent staff permission toggles.
     *
     * @return array<string, list<string>>
     */
    public static function agentStaffModuleGroups(): array
    {
        return [
            'Bookings' => [AgentPermission::BookingsView, AgentPermission::BookingsCreate],
            'Finance' => [
                AgentPermission::WalletView,
                AgentPermission::LedgerView,
                AgentPermission::LedgerManage,
                AgentPermission::PaymentsUpload,
            ],
            'Reports' => [AgentPermission::ReportsView],
            'Customers' => [AgentPermission::TravelersManage],
            'Support' => [AgentPermission::SupportManage],
            'Agents' => [AgentPermission::StaffManage],
            'Settings' => [AgentPermission::AgencyView],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function staffPermissionLabels(): array
    {
        return StaffPermission::labels();
    }

    /**
     * @return array<string, list<string>>
     */
    public static function staffModuleGroups(): array
    {
        return [
            'Bookings' => [
                StaffPermission::BookingsView,
                StaffPermission::BookingsUpdateStatus,
                StaffPermission::BookingsNotes,
            ],
            'Payments' => [
                StaffPermission::PaymentsRecord,
                StaffPermission::PaymentsVerify,
                StaffPermission::PaymentsReject,
            ],
            'Cancellations' => [
                StaffPermission::CancellationsCreate,
                StaffPermission::CancellationsApprove,
                StaffPermission::CancellationsProcess,
            ],
            'Refunds' => [
                StaffPermission::RefundsCreate,
                StaffPermission::RefundsApprove,
                StaffPermission::RefundsMarkPaid,
                StaffPermission::RefundsReject,
            ],
            'Documents' => [
                StaffPermission::DocumentsGenerate,
                StaffPermission::DocumentsDownload,
            ],
            'Ticketing' => [StaffPermission::TicketingIssue],
            'Support' => [
                StaffPermission::SupportView,
                StaffPermission::SupportReply,
                StaffPermission::SupportStatus,
            ],
            'Ledger & reports' => [
                StaffPermission::LedgerView,
                StaffPermission::LedgerManage,
                StaffPermission::LedgerAdjust,
                StaffPermission::ReportsView,
                StaffPermission::ReportsExport,
            ],
            'Settings' => [
                StaffPermission::PageSettingsManage,
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function staffPresetLabels(): array
    {
        return StaffPermission::presetLabels();
    }

    /**
     * @return array<string, list<string>>
     */
    public static function staffPresetPermissions(): array
    {
        $presets = [];
        foreach (StaffPermission::presetKeys() as $preset) {
            $presets[$preset] = StaffPermission::presetPermissions($preset);
        }

        return $presets;
    }

    /**
     * @param  list<mixed>|null  $permissions
     * @return list<string>
     */
    public static function normalizeStaffPermissions(?array $permissions): array
    {
        if ($permissions === null) {
            return [];
        }

        $allowed = StaffPermission::staffSelectable();
        $normalized = [];
        foreach ($permissions as $permission) {
            if (is_string($permission) && in_array($permission, $allowed, true)) {
                $normalized[] = $permission;
            }
        }

        return array_values(array_unique($normalized));
    }

    /**
     * @param  list<mixed>|null  $permissions
     * @return list<string>
     */
    public static function normalizeAgentPermissions(?array $permissions): array
    {
        if ($permissions === null) {
            return [];
        }

        $allowed = AgentPermission::staffSelectable();
        $normalized = [];
        foreach ($permissions as $permission) {
            if (is_string($permission) && in_array($permission, $allowed, true)) {
                $normalized[] = $permission;
            }
        }

        return array_values(array_unique($normalized));
    }
}
