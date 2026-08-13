<?php

namespace App\Support\Rbac;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

/**
 * Dual-read: assigned role_permissions first, then AccountType + staff_permissions meta.
 */
final class RbacPermissionResolver
{
    /**
     * Staff.* keys from assigned roles, or null to fall back to meta.
     *
     * @return list<string>|null
     */
    public static function staffKeysFromAssignedRoles(User $user): ?array
    {
        if (! Schema::hasTable('role_user')) {
            return null;
        }

        $keys = [];
        $foundStaffRow = false;
        foreach (self::assignedRoles($user) as $role) {
            foreach ($role->grantedPermissionKeys() as $key) {
                if (! RbacPermissionRegistry::isStaffKey($key)) {
                    continue;
                }
                $foundStaffRow = true;
                $keys[] = $key;
            }
        }

        if (! $foundStaffRow) {
            return null;
        }

        return array_values(array_unique($keys));
    }

    /**
     * @return list<string>
     */
    public static function dashboardKeysFromAssignedRoles(User $user): array
    {
        if (! Schema::hasTable('role_user')) {
            return [];
        }

        $keys = [];
        foreach (self::assignedRoles($user) as $role) {
            foreach ($role->grantedPermissionKeys() as $key) {
                if (RbacPermissionRegistry::isStaffKey($key)) {
                    continue;
                }
                $keys[] = $key;
            }
        }

        return array_values(array_unique($keys));
    }

    /**
     * @return list<Role>
     */
    public static function assignedRoles(User $user): array
    {
        if (! Schema::hasTable('role_user')) {
            return [];
        }

        return $user->rbacRoles()->with('permissionRows')->get()->all();
    }
}
