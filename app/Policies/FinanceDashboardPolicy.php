<?php

namespace App\Policies;

use App\Models\User;

/**
 * Platform-admin read-only finance operations dashboard (Finance-Reports-12).
 */
class FinanceDashboardPolicy
{
    public function view(User $user): bool
    {
        return $user->isPlatformAdmin();
    }
}
