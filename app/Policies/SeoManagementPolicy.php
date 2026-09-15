<?php

namespace App\Policies;

use App\Models\User;
use App\Support\Staff\StaffPermission;

/**
 * Authorization for SEO Management admin module.
 */
class SeoManagementPolicy
{
    public function manage(User $user): bool
    {
        if ($user->isPlatformAdmin()) {
            return true;
        }

        if ($user->isAgencyAdmin()) {
            return false;
        }

        if ($user->isStaff()) {
            return $user->hasExplicitStaffPermission(StaffPermission::PageSettingsManage);
        }

        return false;
    }
}
