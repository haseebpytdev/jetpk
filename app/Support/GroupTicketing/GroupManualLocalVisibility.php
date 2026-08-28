<?php

namespace App\Support\GroupTicketing;

use App\Models\GroupInventory;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Audience gate for supplier=manual_local QA inventory (never public by default).
 */
class GroupManualLocalVisibility
{
    public static function viewerEmails(): array
    {
        $emails = config('ota.group_ticketing.manual_local_qa_viewer_emails', []);

        return is_array($emails) ? $emails : [];
    }

    public static function userCanViewManualLocal(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        if (Gate::forUser($user)->allows('platform.admin')) {
            return true;
        }

        $email = strtolower(trim((string) $user->email));
        if ($email === '') {
            return false;
        }

        return in_array($email, self::viewerEmails(), true);
    }

    public static function inventoryVisibleTo(?User $user, GroupInventory $inventory): bool
    {
        if (! $inventory->isManualLocal()) {
            return true;
        }

        return self::userCanViewManualLocal($user);
    }
}
