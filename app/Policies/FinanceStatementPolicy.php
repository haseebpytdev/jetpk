<?php

namespace App\Policies;

use App\Models\Agency;
use App\Models\User;
use App\Support\Agents\AgentPermission;
use App\Support\Staff\StaffPermission;

/**
 * Read-only agent finance statements (wallet source-of-truth + ledger comparison).
 */
class FinanceStatementPolicy
{
    public function viewIndex(User $user): bool
    {
        if ($user->isPlatformAdmin()) {
            return true;
        }

        return $user->isStaff() && $user->hasStaffPermission(StaffPermission::ReportsView);
    }

    public function view(User $user, Agency $agency): bool
    {
        if ($user->isPlatformAdmin()) {
            return true;
        }

        if ($user->isStaff()) {
            return $user->hasStaffPermission(StaffPermission::ReportsView);
        }

        if ($user->isAgent() || $user->isAgentStaff()) {
            if (! $user->hasAgentPermission(AgentPermission::ReportsView)
                && ! $user->hasAgentPermission(AgentPermission::LedgerView)) {
                return false;
            }

            $agent = $user->agent();

            return $agent !== null && (int) $agent->agency_id === (int) $agency->id;
        }

        return false;
    }

    public function export(User $user, Agency $agency): bool
    {
        if ($user->isPlatformAdmin()) {
            return $this->view($user, $agency);
        }

        if ($user->isStaff()) {
            return $user->hasStaffPermission(StaffPermission::ReportsExport)
                && $this->view($user, $agency);
        }

        if ($user->isAgent() || $user->isAgentStaff()) {
            if (! $user->hasAgentPermission(AgentPermission::ReportsView)
                && ! $user->hasAgentPermission(AgentPermission::LedgerView)) {
                return false;
            }

            return $this->view($user, $agency);
        }

        return false;
    }
}
