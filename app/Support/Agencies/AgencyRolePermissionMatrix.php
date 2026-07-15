<?php

namespace App\Support\Agencies;

use App\Enums\AgencyRole;
use App\Support\Agents\AgentPermission;

/**
 * Suggested default agent portal permission templates per agency business role.
 * Display and future onboarding only — does not enforce or overwrite users.meta.agent_permissions.
 */
final class AgencyRolePermissionMatrix
{
    /**
     * @return array<string, string>
     */
    public static function roleLabels(): array
    {
        return AgencyRole::options();
    }

    /**
     * @return list<string>
     */
    public static function suggestedPermissionSummary(AgencyRole $role): string
    {
        $labels = AgentPermission::labels();

        return collect(self::suggestedPermissions($role))
            ->map(static fn (string $permission): string => $labels[$permission] ?? $permission)
            ->implode(', ');
    }

    /**
     * @return list<string>
     */
    /**
     * Permissions safe to apply to agent_staff from a role template (explicit apply only).
     *
     * @return list<string>
     */
    public static function templatePermissionsForAgentStaff(AgencyRole $role): array
    {
        if ($role === AgencyRole::Owner) {
            return [];
        }

        $suggested = self::suggestedPermissions($role);
        $selectable = array_flip(AgentPermission::staffSelectable());
        $normalized = [];

        foreach ($suggested as $permission) {
            if (! isset($selectable[$permission])) {
                continue;
            }

            if (
                in_array($role, [AgencyRole::Manager, AgencyRole::Accountant], true)
                && $permission === AgentPermission::LedgerManage
            ) {
                continue;
            }

            $normalized[] = $permission;
        }

        return array_values(array_unique($normalized));
    }

    /**
     * @return list<string>
     */
    public static function suggestedPermissions(AgencyRole $role): array
    {
        return match ($role) {
            AgencyRole::Owner => AgentPermission::all(),
            AgencyRole::Manager => [
                AgentPermission::BookingsView,
                AgentPermission::BookingsCreate,
                AgentPermission::WalletView,
                AgentPermission::LedgerView,
                AgentPermission::LedgerManage,
                AgentPermission::ReportsView,
                AgentPermission::PaymentsUpload,
                AgentPermission::TravelersManage,
                AgentPermission::SupportManage,
                AgentPermission::StaffManage,
                AgentPermission::AgencyView,
            ],
            AgencyRole::Accountant => [
                AgentPermission::BookingsView,
                AgentPermission::WalletView,
                AgentPermission::LedgerView,
                AgentPermission::LedgerManage,
                AgentPermission::ReportsView,
                AgentPermission::PaymentsUpload,
                AgentPermission::AgencyView,
            ],
            AgencyRole::SalesAgent => [
                AgentPermission::BookingsView,
                AgentPermission::BookingsCreate,
                AgentPermission::TravelersManage,
                AgentPermission::AgencyView,
            ],
            AgencyRole::SupportStaff => [
                AgentPermission::BookingsView,
                AgentPermission::SupportManage,
                AgentPermission::AgencyView,
            ],
            AgencyRole::TicketingStaff => [
                AgentPermission::BookingsView,
                AgentPermission::AgencyView,
            ],
            AgencyRole::Viewer => [
                AgentPermission::BookingsView,
                AgentPermission::AgencyView,
            ],
        };
    }
}
