<?php

namespace App\Support\Auth;

use App\Models\User;
use App\Services\Client\ClientRedirectResolver;

class LoginDestination
{
    public static function path(?User $user): string
    {
        return app(ClientRedirectResolver::class)->dashboardPathForUser($user);
    }
}
