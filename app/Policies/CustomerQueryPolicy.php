<?php

namespace App\Policies;

use App\Models\CustomerQuery;
use App\Models\User;

class CustomerQueryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('platform.admin');
    }

    public function view(User $user, CustomerQuery $customerQuery): bool
    {
        return $user->can('platform.admin');
    }

    public function update(User $user, CustomerQuery $customerQuery): bool
    {
        return $user->can('platform.admin');
    }
}
