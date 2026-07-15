<?php

namespace App\Support\Agencies;

use App\Enums\AccountType;
use App\Enums\AgencyRole;
use App\Models\AgencyUser;
use App\Models\User;
use App\Support\Agents\AgentPermission;

/**
 * Resolves the effective agency business role for display/reporting.
 * Does not mutate data or enforce access — permissions remain in users.meta.agent_permissions.
 */
final class AgencyRoleResolver
{
    public static function resolve(User $user, ?int $agencyId = null): AgencyRole
    {
        $agencyId ??= $user->current_agency_id;

        if ($agencyId !== null) {
            $membership = AgencyUser::query()
                ->where('user_id', $user->id)
                ->where('agency_id', $agencyId)
                ->first();

            if ($membership !== null) {
                $stored = AgencyRole::fromNullable($membership->agency_role);
                if ($stored !== null) {
                    return $stored;
                }
            }
        }

        if ($user->account_type === AccountType::Agent) {
            return AgencyRole::Owner;
        }

        if ($user->account_type === AccountType::AgentStaff) {
            $permissions = is_array($user->meta['agent_permissions'] ?? null)
                ? $user->meta['agent_permissions']
                : [];

            return self::inferFromAgentStaffPermissions($permissions);
        }

        return AgencyRole::Viewer;
    }

    public static function isStoredRole(User $user, ?int $agencyId = null): bool
    {
        $agencyId ??= $user->current_agency_id;
        if ($agencyId === null) {
            return false;
        }

        $membership = AgencyUser::query()
            ->where('user_id', $user->id)
            ->where('agency_id', $agencyId)
            ->first();

        return $membership !== null && AgencyRole::fromNullable($membership->agency_role) !== null;
    }

    public static function labelFor(User $user, ?int $agencyId = null): string
    {
        return self::resolve($user, $agencyId)->label();
    }

    /**
     * @param  list<string>  $permissions
     */
    public static function inferFromAgentStaffPermissions(array $permissions): AgencyRole
    {
        $permissionSet = array_fill_keys($permissions, true);

        if (isset($permissionSet[AgentPermission::StaffManage])) {
            return AgencyRole::Manager;
        }

        if (
            isset($permissionSet[AgentPermission::LedgerManage])
            || (
                isset($permissionSet[AgentPermission::LedgerView])
                && isset($permissionSet[AgentPermission::WalletView])
                && isset($permissionSet[AgentPermission::PaymentsUpload])
            )
        ) {
            return AgencyRole::Accountant;
        }

        if (
            isset($permissionSet[AgentPermission::BookingsCreate])
            && isset($permissionSet[AgentPermission::TravelersManage])
        ) {
            return AgencyRole::SalesAgent;
        }

        if (isset($permissionSet[AgentPermission::SupportManage])) {
            return AgencyRole::SupportStaff;
        }

        return AgencyRole::Viewer;
    }
}
