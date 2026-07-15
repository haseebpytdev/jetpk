<?php

namespace App\Policies;

use App\Models\Agency;
use App\Models\User;

class AgencyBrandingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isPlatformAdmin();
    }

    public function view(User $user, Agency $agency): bool
    {
        return $user->isPlatformAdmin();
    }

    public function update(User $user, Agency $agency): bool
    {
        return $user->isPlatformAdmin();
    }
}
