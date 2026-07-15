<?php

namespace App\Policies;

use App\Models\User;

class UserManagementPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->isPlatformAdmin();
    }

    public function view(User $actor, User $target): bool
    {
        return $actor->isPlatformAdmin();
    }

    public function create(User $actor): bool
    {
        return $actor->isPlatformAdmin();
    }

    public function update(User $actor, User $target): bool
    {
        return $this->view($actor, $target);
    }

    public function suspend(User $actor, User $target): bool
    {
        return $this->update($actor, $target);
    }

    public function activate(User $actor, User $target): bool
    {
        return $this->update($actor, $target);
    }
}
