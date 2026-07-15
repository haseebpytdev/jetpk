<?php

namespace App\Policies;

use App\Models\AgencyMessageTemplate;
use App\Models\User;

class AgencyMessageTemplatePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isPlatformAdmin();
    }

    public function view(User $user, AgencyMessageTemplate $template): bool
    {
        return $user->isPlatformAdmin();
    }

    public function update(User $user, AgencyMessageTemplate $template): bool
    {
        return $user->isPlatformAdmin();
    }
}
