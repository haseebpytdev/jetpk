<?php

namespace App\Http\Resources\Dashboard;

use App\Enums\AccountType;
use App\Models\User;
use App\Support\Dashboard\DashboardRoleCatalog;

final class DashboardUserResource
{
    /**
     * @return array<string, mixed>
     */
    public static function fromModel(User $user): array
    {
        $role = self::primaryRole($user);
        $meta = is_array($user->meta) ? $user->meta : [];

        return [
            'id' => self::publicId($user),
            'fullName' => (string) $user->name,
            'displayName' => self::displayName($user),
            'email' => DashboardSessionResource::maskEmail($user->email) ?? '—',
            'phone' => self::maskPhone((string) ($meta['phone'] ?? '')),
            'department' => (string) ($meta['department'] ?? self::defaultDepartment($user)),
            'jobTitle' => (string) ($meta['job_title'] ?? self::defaultJobTitle($user)),
            'userType' => self::userType($user),
            'userTypeLabel' => self::userTypeLabel($user),
            'assignedRoleNames' => [$role['name']],
            'assignedRoleIds' => [$role['id']],
            'status' => self::status($user),
            'verificationState' => $user->email_verified_at ? 'verified' : 'unverified',
            'mfaState' => 'unknown',
            'mfaRequired' => $user->isPlatformAdmin(),
            'securityState' => $user->status->value === 'active' ? 'normal' : 'warning',
            'lastSignInAt' => null,
            'activeSessionCount' => 0,
            'validationState' => 'valid',
            'accountType' => $user->account_type->value,
            'effectiveAccessSummary' => [
                'roleLabel' => $role['name'],
                'scope' => $role['scope'],
                'highRiskAccess' => false,
                'previewOnly' => true,
            ],
            'highRiskAccessSummary' => [
                'hasHighRiskAccess' => $user->isPlatformAdmin(),
                'labels' => $user->isPlatformAdmin() ? ['Platform administration'] : [],
            ],
            'lastActivitySummary' => [
                'label' => 'Account updated',
                'occurredAt' => $user->updated_at?->toIso8601String(),
            ],
            'createdAt' => $user->created_at?->toIso8601String(),
            'updatedAt' => $user->updated_at?->toIso8601String(),
            'reviewFlags' => ['needsReview' => false],
        ];
    }

    public static function publicId(User $user): string
    {
        return 'JP-USR-'.str_pad((string) $user->id, 4, '0', STR_PAD_LEFT);
    }

    /**
     * @return array{id: string, name: string, scope: string}
     */
    private static function primaryRole(User $user): array
    {
        return match (true) {
            $user->isPlatformAdmin() => ['id' => 'JP-ROL-0001', 'name' => 'Super Administrator', 'scope' => 'allRecords'],
            $user->isStaff() => ['id' => 'JP-ROL-0002', 'name' => 'Operations Manager', 'scope' => 'allRecords'],
            $user->isAgentPortalUser() => ['id' => 'JP-ROL-AGENT', 'name' => 'Agent Portal', 'scope' => 'ownRecords'],
            default => ['id' => 'JP-ROL-0002', 'name' => 'Dashboard User', 'scope' => 'ownRecords'],
        };
    }

    private static function displayName(User $user): string
    {
        $parts = preg_split('/\s+/', trim((string) $user->name)) ?: [];
        if (count($parts) >= 2) {
            return $parts[0].' '.mb_substr($parts[1], 0, 1).'.';
        }

        return (string) $user->name;
    }

    private static function maskPhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if (strlen($digits) < 4) {
            return '—';
        }

        return '***'.substr($digits, -4);
    }

    private static function userType(User $user): string
    {
        return match ($user->account_type) {
            AccountType::PlatformAdmin => 'superAdministrator',
            AccountType::Staff => 'operationsManager',
            AccountType::Agent, AccountType::AgentStaff => 'bookingAgent',
            default => 'administrator',
        };
    }

    private static function userTypeLabel(User $user): string
    {
        return match ($user->account_type) {
            AccountType::PlatformAdmin => 'Super Administrator',
            AccountType::Staff => 'Staff',
            AccountType::Agent => 'Agent',
            AccountType::AgentStaff => 'Agent Staff',
            default => 'User',
        };
    }

    private static function status(User $user): string
    {
        $value = is_object($user->status) ? $user->status->value : (string) $user->status;

        return match ($value) {
            'active' => 'active',
            'inactive', 'disabled' => 'disabled',
            'suspended' => 'suspended',
            default => 'active',
        };
    }

    private static function defaultDepartment(User $user): string
    {
        return match ($user->account_type) {
            AccountType::PlatformAdmin => 'Executive',
            AccountType::Staff => 'Operations',
            AccountType::Agent, AccountType::AgentStaff => 'Commercial',
            default => 'Operations',
        };
    }

    private static function defaultJobTitle(User $user): string
    {
        return match ($user->account_type) {
            AccountType::PlatformAdmin => 'Platform Administrator',
            AccountType::Staff => 'Staff Member',
            AccountType::Agent => 'Agent Owner',
            AccountType::AgentStaff => 'Agent Staff',
            default => 'User',
        };
    }
}
