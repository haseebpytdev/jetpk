<?php

namespace App\Policies;

use App\Models\LedgerTransaction;
use App\Models\User;
use App\Support\Agents\AgentPermission;
use App\Support\Staff\StaffPermission;

/**
 * Authorization for double-entry accounting ledger (parallel layer, read-only UI).
 */
class AccountingLedgerPolicy
{
    public function viewAny(User $user): bool
    {
        if ($user->isPlatformAdmin()) {
            return true;
        }

        if ($user->isStaff() && $user->hasStaffPermission(StaffPermission::LedgerView)) {
            return true;
        }

        if ($user->isAgent() || $user->isAgentStaff()) {
            return $user->hasAgentPermission(AgentPermission::LedgerView);
        }

        return false;
    }

    public function view(User $user, LedgerTransaction $transaction): bool
    {
        if (! $this->viewAny($user)) {
            return false;
        }

        if ($user->isPlatformAdmin()) {
            return true;
        }

        if ($user->isStaff()) {
            return true;
        }

        $agent = $user->agent();
        if ($agent === null) {
            return false;
        }

        return (int) $transaction->agency_id === (int) $agent->agency_id;
    }

    public function viewReconciliation(User $user): bool
    {
        if ($user->isPlatformAdmin()) {
            return true;
        }

        return $user->isStaff() && $user->hasStaffPermission(StaffPermission::LedgerView);
    }
}
