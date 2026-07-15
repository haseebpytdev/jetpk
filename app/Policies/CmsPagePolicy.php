<?php

namespace App\Policies;

use App\Models\CmsPage;
use App\Models\User;

class CmsPagePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isPlatformAdmin();
    }

    public function view(User $user, CmsPage $cmsPage): bool
    {
        return $user->isPlatformAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isPlatformAdmin();
    }

    public function update(User $user, CmsPage $cmsPage): bool
    {
        return $user->isPlatformAdmin();
    }

    public function delete(User $user, CmsPage $cmsPage): bool
    {
        return $user->isPlatformAdmin();
    }
}
