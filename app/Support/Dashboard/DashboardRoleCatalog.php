<?php

namespace App\Support\Dashboard;

use App\Enums\AccountType;
use App\Models\User;
use App\Support\Staff\StaffPermission;

/**
 * Read-only dashboard role catalogue derived from account types and staff presets.
 */
final class DashboardRoleCatalog
{
    /**
     * @return list<array<string, mixed>>
     */
    public static function all(): array
    {
        return array_map(
            static fn (array $role): array => self::hydrateCounts($role),
            self::definitions(),
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function find(string $id): ?array
    {
        foreach (self::definitions() as $role) {
            if ($role['id'] === $id || $role['key'] === $id) {
                return self::hydrateCounts($role);
            }
        }

        return null;
    }

    public static function rolesWithPermission(string $permissionKey): int
    {
        $count = 0;
        foreach (self::definitions() as $role) {
            if (in_array($permissionKey, $role['permissionKeys'], true)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * @return list<array{roleId: string, roleKey: string, roleName: string, permissionKey: string, assigned: bool, highRisk: bool, protected: bool, scope: string, channel: string}>
     */
    public static function matrix(?string $domain = null): array
    {
        $rows = [];
        $permissions = DashboardPermissionCatalog::all();
        if ($domain !== null && $domain !== '' && $domain !== 'all') {
            $permissions = array_values(array_filter(
                $permissions,
                static fn (array $permission): bool => ($permission['category'] ?? '') === $domain,
            ));
        }

        foreach (self::all() as $role) {
            foreach ($permissions as $permission) {
                $key = (string) $permission['key'];
                $rows[] = [
                    'roleId' => (string) $role['id'],
                    'roleKey' => (string) $role['key'],
                    'roleName' => (string) $role['name'],
                    'permissionKey' => $key,
                    'assigned' => in_array($key, $role['permissionKeys'], true),
                    'highRisk' => DashboardPermissionCatalog::isHighRisk($key),
                    'protected' => (bool) $role['isProtected'],
                    'scope' => (string) $role['scope'],
                    'channel' => (string) ($permission['channel'] ?? 'all'),
                ];
            }
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $role
     * @return array<string, mixed>
     */
    private static function hydrateCounts(array $role): array
    {
        $permissionKeys = $role['permissionKeys'];
        $highRiskCount = count(array_filter(
            $permissionKeys,
            static fn (string $key): bool => DashboardPermissionCatalog::isHighRisk($key),
        ));

        return [
            'id' => $role['id'],
            'key' => $role['key'],
            'name' => $role['name'],
            'description' => $role['description'],
            'category' => $role['category'],
            'status' => 'active',
            'isSystem' => true,
            'isProtected' => $role['isProtected'],
            'assignedUserCount' => self::countUsersForRole($role),
            'permissionCount' => count($permissionKeys),
            'highRiskPermissionCount' => $highRiskCount,
            'permissionGroups' => $role['permissionGroups'],
            'permissionKeys' => $permissionKeys,
            'scope' => $role['scope'],
            'createdAt' => '2025-01-10T08:00:00.000Z',
            'updatedAt' => now()->toIso8601String(),
            'validationState' => 'valid',
            'reviewFlags' => ['needsReview' => false],
        ];
    }

    /**
     * @param  array<string, mixed>  $role
     */
    private static function countUsersForRole(array $role): int
    {
        $accountTypes = $role['accountTypes'] ?? [];
        if ($accountTypes === []) {
            return 0;
        }

        return User::query()
            ->whereIn('account_type', $accountTypes)
            ->count();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function definitions(): array
    {
        $allView = [
            'dashboard.view', 'bookings.view', 'payments.view', 'customers.view',
            'suppliers.view', 'agents.view', 'pnrs.view', 'tickets.view', 'reports.view',
            'cms.view', 'users.view', 'roles.view', 'settings.view', 'integrations.view', 'audit.view',
        ];

        $integrationAdmin = [
            'integrations.view',
            'integrations.manage',
            'integrations.test',
            'integrations.activate',
            'integrations.test-payment',
            'integrations.audit',
        ];

        return [
            [
                'id' => 'JP-ROL-0001',
                'key' => 'super_administrator',
                'name' => 'Super Administrator',
                'description' => 'Full platform administration with protected system access.',
                'category' => 'system',
                'isProtected' => true,
                'scope' => 'allRecords',
                'permissionGroups' => ['dashboard', 'bookings', 'payments', 'customers', 'suppliers', 'agents', 'pnrs', 'tickets', 'reports', 'cms', 'users', 'roles', 'settings', 'integrations', 'audit'],
                'permissionKeys' => array_merge($allView, $integrationAdmin, ['users.assignRoles', 'roles.assignPermissions', 'settings.update', 'audit.export']),
                'accountTypes' => [AccountType::PlatformAdmin],
            ],
            [
                'id' => 'JP-ROL-0002',
                'key' => 'operations_manager',
                'name' => 'Operations Manager',
                'description' => 'Oversees bookings, PNRs, tickets, and operational queues.',
                'category' => 'operations',
                'isProtected' => false,
                'scope' => 'allRecords',
                'permissionGroups' => ['dashboard', 'bookings', 'payments', 'customers', 'agents', 'pnrs', 'tickets', 'reports'],
                'permissionKeys' => ['dashboard.view', 'bookings.view', 'payments.view', 'customers.view', 'agents.view', 'pnrs.view', 'tickets.view', 'reports.view'],
                'accountTypes' => [AccountType::Staff],
            ],
            [
                'id' => 'JP-ROL-0009',
                'key' => 'read_only_auditor',
                'name' => 'Read-only Auditor',
                'description' => 'Audit and compliance read access.',
                'category' => 'compliance',
                'isProtected' => false,
                'scope' => 'allRecords',
                'permissionGroups' => ['dashboard', 'audit', 'reports'],
                'permissionKeys' => ['dashboard.view', 'audit.view', 'reports.view'],
                'accountTypes' => [AccountType::Staff],
            ],
            [
                'id' => 'JP-ROL-0013',
                'key' => 'system_administrator',
                'name' => 'System Administrator',
                'description' => 'Technology administration with settings visibility.',
                'category' => 'technology',
                'isProtected' => false,
                'scope' => 'allRecords',
                'permissionGroups' => ['dashboard', 'users', 'roles', 'settings', 'audit'],
                'permissionKeys' => ['dashboard.view', 'users.view', 'roles.view', 'settings.view', 'audit.view'],
                'accountTypes' => [AccountType::PlatformAdmin, AccountType::Staff],
            ],
            [
                'id' => 'JP-ROL-STAFF-PRESET',
                'key' => 'staff_preset_manager',
                'name' => 'Staff Manager Preset',
                'description' => 'Staff permission preset: '.StaffPermission::PresetManager,
                'category' => 'operations',
                'isProtected' => false,
                'scope' => 'allRecords',
                'permissionGroups' => ['dashboard', 'bookings', 'payments', 'reports'],
                'permissionKeys' => ['dashboard.view', 'bookings.view', 'payments.view', 'reports.view'],
                'accountTypes' => [AccountType::Staff],
            ],
            [
                'id' => 'JP-ROL-AGENT',
                'key' => 'agent_portal',
                'name' => 'Agent Portal',
                'description' => 'Agent portal owner access.',
                'category' => 'commercial',
                'isProtected' => false,
                'scope' => 'ownRecords',
                'permissionGroups' => ['dashboard', 'bookings', 'payments', 'reports'],
                'permissionKeys' => ['dashboard.view', 'bookings.view', 'payments.view', 'reports.view'],
                'accountTypes' => [AccountType::Agent],
            ],
        ];
    }
}
