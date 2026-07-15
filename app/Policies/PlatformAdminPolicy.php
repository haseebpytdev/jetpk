<?php

namespace App\Policies;

use App\Models\User;

/**
 * Platform-only admin area gates for routes without a dedicated model policy.
 */
class PlatformAdminPolicy
{
    public function accessAdminTools(User $user): bool
    {
        return $user->isPlatformAdmin();
    }
}
