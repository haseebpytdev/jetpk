<?php

namespace App\Policies;

use App\Models\Agency;
use App\Models\AgencyMedia;
use App\Models\User;

class AgencyMediaPolicy
{
    public function viewAny(User $user, Agency $agency): bool
    {
        return $user->isPlatformAdmin();
    }

    public function create(User $user, Agency $agency): bool
    {
        return $user->isPlatformAdmin();
    }

    public function delete(User $user, AgencyMedia $media): bool
    {
        return $user->isPlatformAdmin();
    }
}
