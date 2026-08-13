<?php

namespace App\Support\Rbac;

use App\Support\Dashboard\DashboardPermissionCatalog;
use App\Support\Staff\StaffPermission;

/**
 * Authoritative permission keys that may be persisted on role_permissions.
 */
final class RbacPermissionRegistry
{
    /**
     * Extra keys from the Next permission catalogue that are not yet in the PHP view catalogue.
     *
     * @return list<string>
     */
    public static function dashboardWriteKeys(): array
    {
        return [
            'bookings.create', 'bookings.update', 'bookings.cancel.request', 'bookings.cancel.approve',
            'payments.record', 'payments.reconcile', 'payments.refund.request', 'payments.refund.approve',
            'customers.update',
            'suppliers.manage',
            'agents.manage',
            'pnrs.review', 'pnrs.cancel.request', 'pnrs.cancel.approve',
            'tickets.review', 'tickets.issue.request', 'tickets.issue.approve',
            'reports.export',
            'cms.preview', 'cms.edit', 'cms.review', 'cms.publish.request', 'cms.publish.approve',
            'users.invite', 'users.update', 'users.suspend',
            'roles.create', 'roles.update',
            'permissions.view',
            'support.view',
        ];
    }

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        $dashboard = array_map(
            static fn (array $row): string => (string) $row['key'],
            DashboardPermissionCatalog::all(),
        );

        return array_values(array_unique(array_merge(
            $dashboard,
            self::dashboardWriteKeys(),
            StaffPermission::all(),
        )));
    }

    public static function isValid(string $key): bool
    {
        return in_array($key, self::all(), true);
    }

    /**
     * @param  list<string>  $keys
     * @return list<string>
     */
    public static function unknown(array $keys): array
    {
        return array_values(array_filter(
            $keys,
            static fn (string $key): bool => ! self::isValid($key),
        ));
    }

    public static function isStaffKey(string $key): bool
    {
        return str_starts_with($key, 'staff.');
    }

    public static function isPlatformAdminGrantKey(string $key): bool
    {
        return in_array($key, [
            'users.assignRoles',
            'roles.assignPermissions',
            'roles.create',
            'roles.update',
            'settings.update',
        ], true);
    }
}
