<?php

namespace App\Support\Suppliers;

use App\Enums\AccountType;
use App\Models\User;

/**
 * UI visibility for supplier/source labels on flight result cards (operational users only).
 */
final class SupplierSourceVisibility
{
    public static function canCurrentUser(): bool
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return false;
        }

        if ($user->isCustomer()) {
            return false;
        }

        return match ($user->account_type) {
            AccountType::PlatformAdmin,
            AccountType::Staff,
            AccountType::Agent,
            AccountType::AgentStaff,
            AccountType::AgencyAdmin => true,
            default => false,
        };
    }
}
