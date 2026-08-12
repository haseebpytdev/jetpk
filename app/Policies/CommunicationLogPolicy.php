<?php

namespace App\Policies;

use App\Models\CommunicationLog;
use App\Models\User;
use App\Support\Dashboard\DashboardPermissionResolver;

class CommunicationLogPolicy
{
    public function viewAny(User $user): bool
    {
        return DashboardPermissionResolver::canViewSettings($user);
    }

    public function view(User $user, CommunicationLog $log): bool
    {
        if (! DashboardPermissionResolver::canViewSettings($user)) {
            return false;
        }

        if ($user->isPlatformAdmin()) {
            return true;
        }

        return (int) $user->current_agency_id === (int) $log->agency_id;
    }

    public function resend(User $user, CommunicationLog $log): bool
    {
        if (! in_array($log->status, ['failed', 'skipped'], true)) {
            return false;
        }

        return $user->isPlatformAdmin();
    }
}
