<?php

namespace App\Policies;

use App\Models\CommunicationLog;
use App\Models\User;

class CommunicationLogPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isPlatformAdmin();
    }

    public function view(User $user, CommunicationLog $log): bool
    {
        return $user->isPlatformAdmin();
    }

    public function resend(User $user, CommunicationLog $log): bool
    {
        if (! in_array($log->status, ['failed', 'skipped'], true)) {
            return false;
        }

        return $user->isPlatformAdmin();
    }
}
