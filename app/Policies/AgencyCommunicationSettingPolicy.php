<?php

namespace App\Policies;

use App\Models\AgencyCommunicationSetting;
use App\Models\User;

class AgencyCommunicationSettingPolicy
{
    public function view(User $user, AgencyCommunicationSetting $setting): bool
    {
        return $user->isPlatformAdmin();
    }

    public function update(User $user, AgencyCommunicationSetting $setting): bool
    {
        return $user->isPlatformAdmin();
    }
}
