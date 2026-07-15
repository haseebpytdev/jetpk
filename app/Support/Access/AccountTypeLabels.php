<?php

namespace App\Support\Access;

use App\Enums\AccountType;
use App\Models\User;
use App\Support\Agencies\AgencyScopeResolver;

/**
 * Human-readable account type labels for Platform Admin UI.
 */
final class AccountTypeLabels
{
    /**
     * @return array<string, string>
     */
    public static function all(): array
    {
        $labels = [];
        foreach (AccountType::cases() as $type) {
            $labels[$type->value] = self::label($type);
        }

        return $labels;
    }

    public static function label(AccountType|string|null $type): string
    {
        $value = $type instanceof AccountType ? $type->value : (string) ($type ?? '');

        return match ($value) {
            AccountType::PlatformAdmin->value => 'Platform Admin',
            AccountType::Staff->value => 'Platform Staff',
            AccountType::Agent->value => 'Agency Owner',
            AccountType::AgentStaff->value => 'Agency Staff',
            AccountType::Customer->value => 'Customer',
            AccountType::AgencyAdmin->value => 'Legacy Agency Admin',
            default => $value !== '' ? str_replace('_', ' ', ucfirst($value)) : 'Unknown',
        };
    }

    public static function agencyBadge(User $user): string
    {
        return AgencyScopeResolver::badgeLabel($user);
    }

    public static function accessModeLabel(User $user): string
    {
        return match ($user->account_type) {
            AccountType::PlatformAdmin => 'Platform full access',
            AccountType::Staff => $user->usesLegacyStaffPermissions()
                ? 'Default staff access active'
                : 'Permission-based',
            AccountType::Agent => 'Agency owner (full access)',
            AccountType::AgentStaff => self::agentStaffAccessModeLabel($user),
            AccountType::AgencyAdmin => 'Legacy (disabled)',
            AccountType::Customer => 'Customer portal',
            default => '—',
        };
    }

    protected static function agentStaffAccessModeLabel(User $user): string
    {
        $permissions = $user->meta['agent_permissions'] ?? null;
        if (! is_array($permissions) || $permissions === []) {
            return 'No permissions assigned';
        }

        return count($permissions).' permission'.(count($permissions) === 1 ? '' : 's');
    }
}
