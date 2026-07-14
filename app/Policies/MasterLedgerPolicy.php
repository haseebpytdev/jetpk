<?php

namespace App\Policies;

use App\Models\AgentWalletTransaction;
use App\Models\User;
use App\Support\Staff\StaffPermission;

class MasterLedgerPolicy
{
    public function viewAny(User $user): bool
    {
        if ($user->isPlatformAdmin()) {
            return true;
        }

        return $user->isStaff() && $user->hasStaffPermission(StaffPermission::LedgerView);
    }

    public function view(User $user, AgentWalletTransaction $transaction): bool
    {
        if (! $this->viewAny($user)) {
            return false;
        }

        if ($user->isPlatformAdmin()) {
            return true;
        }

        return $user->isStaff();
    }

    public function manage(User $user): bool
    {
        if ($user->isPlatformAdmin()) {
            return true;
        }

        return $user->isStaff() && $user->hasStaffPermission(StaffPermission::LedgerManage);
    }
}
