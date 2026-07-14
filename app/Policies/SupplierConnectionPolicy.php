<?php

namespace App\Policies;

use App\Models\SupplierConnection;
use App\Models\User;

class SupplierConnectionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isPlatformAdmin();
    }

    public function view(User $user, SupplierConnection $supplierConnection): bool
    {
        return $user->isPlatformAdmin();
    }

    public function update(User $user, SupplierConnection $supplierConnection): bool
    {
        return $user->isPlatformAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isPlatformAdmin();
    }

    public function delete(User $user, SupplierConnection $supplierConnection): bool
    {
        return $this->update($user, $supplierConnection);
    }
}
