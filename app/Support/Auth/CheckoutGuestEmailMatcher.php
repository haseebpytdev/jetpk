<?php

namespace App\Support\Auth;

use App\Enums\AccountType;
use App\Models\User;

/**
 * Checkout-only email recognition: Customer accounts only.
 * Privileged and unknown emails both return false (no enumeration of staff/admin/agent).
 */
class CheckoutGuestEmailMatcher
{
    public function customerMatch(string $email): bool
    {
        $normalized = strtolower(trim($email));
        if ($normalized === '' || ! filter_var($normalized, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $user = User::query()->where('email', $normalized)->first();

        return $user instanceof User && $user->account_type === AccountType::Customer;
    }
}
