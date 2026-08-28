<?php

namespace App\Listeners\Auth;

use App\Models\User;
use App\Support\Auth\BestEffortEmailVerification;
use Illuminate\Auth\Events\Registered;

/**
 * Replaces Laravel's SendEmailVerificationNotification with commit-safe, non-throwing delivery.
 */
final class SendEmailVerificationNotificationBestEffort
{
    public function handle(Registered $event): void
    {
        $user = $event->user;
        if (! $user instanceof User) {
            return;
        }

        BestEffortEmailVerification::sendAfterCommit($user);
    }
}
