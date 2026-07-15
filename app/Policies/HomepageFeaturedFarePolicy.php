<?php

namespace App\Policies;

use App\Models\HomepageFeaturedFare;
use App\Models\User;

class HomepageFeaturedFarePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isPlatformAdmin();
    }

    public function view(User $user, HomepageFeaturedFare $fare): bool
    {
        return $user->isPlatformAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isPlatformAdmin();
    }

    public function update(User $user, HomepageFeaturedFare $fare): bool
    {
        return $user->isPlatformAdmin();
    }

    public function delete(User $user, HomepageFeaturedFare $fare): bool
    {
        return $this->update($user, $fare);
    }

    public function refresh(User $user, HomepageFeaturedFare $fare): bool
    {
        return $this->update($user, $fare);
    }
}
