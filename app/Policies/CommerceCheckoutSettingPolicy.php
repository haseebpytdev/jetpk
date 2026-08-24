<?php

namespace App\Policies;

use App\Models\CommerceCheckoutSetting;
use App\Models\User;

class CommerceCheckoutSettingPolicy
{
    public function view(User $user, CommerceCheckoutSetting $setting): bool
    {
        return $user->isPlatformAdmin();
    }

    public function update(User $user, CommerceCheckoutSetting $setting): bool
    {
        return $user->isPlatformAdmin();
    }
}
