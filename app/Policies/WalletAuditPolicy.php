<?php

namespace App\Policies;

use App\Models\User;

/**
 * Platform-admin read-only duplicate wallet audit (Finance-Reports-14).
 */
class WalletAuditPolicy
{
    public function view(User $user): bool
    {
        return $user->isPlatformAdmin();
    }

    public function archive(User $user): bool
    {
        return $user->isPlatformAdmin();
    }
}
