<?php

namespace App\Support\Dashboard;

use App\Models\Agent;
use App\Models\Booking;
use App\Models\CmsPage;
use App\Models\SupplierConnection;
use App\Models\User;
use App\Support\Agents\AgentPermission;
use App\Support\Staff\StaffPermission;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;

/**
 * Maps Laravel portal permissions to dashboard read-only permission keys.
 */
final class DashboardPermissionResolver
{
    /**
     * @return list<string>
     */
    public static function effectivePermissionKeys(User $user): array
    {
        $keys = [];

        if (self::canViewDashboard($user)) {
            $keys[] = 'dashboard.view';
        }
        if (Gate::forUser($user)->allows('viewAny', Booking::class)) {
            $keys[] = 'bookings.view';
        }
        if (self::canViewPayments($user)) {
            $keys[] = 'payments.view';
        }
        if (self::canViewCustomers($user)) {
            $keys[] = 'customers.view';
        }
        if (Gate::forUser($user)->allows('viewAny', SupplierConnection::class)) {
            $keys[] = 'suppliers.view';
        }
        if (Gate::forUser($user)->allows('viewAny', Agent::class)) {
            $keys[] = 'agents.view';
        }
        if (Gate::forUser($user)->allows('viewAny', Booking::class)) {
            $keys[] = 'pnrs.view';
            $keys[] = 'tickets.view';
        }
        if ($user->hasStaffPermission(StaffPermission::ReportsView) || $user->isPlatformAdmin()) {
            $keys[] = 'reports.view';
        }
        if (self::canViewCms($user)) {
            $keys[] = 'cms.view';
        }
        if (self::canViewUsers($user)) {
            $keys[] = 'users.view';
        }
        if (self::canViewRoles($user)) {
            $keys[] = 'roles.view';
            $keys[] = 'permissions.view';
        }
        if (self::canViewSettings($user)) {
            $keys[] = 'settings.view';
        }
        if (self::canViewAudit($user)) {
            $keys[] = 'audit.view';
        }

        return array_values(array_unique($keys));
    }

    /**
     * @return list<string>
     */
    public static function roleLabels(User $user): array
    {
        return match (true) {
            $user->isPlatformAdmin() => ['Platform Admin'],
            $user->isAgencyAdmin() => ['Agency Admin'],
            $user->isStaff() => ['Staff'],
            $user->isAgentAdmin() => ['Agent Admin'],
            $user->isAgentStaff() => ['Agent Staff'],
            $user->isCustomer() => ['Customer'],
            default => ['User'],
        };
    }

    public static function canViewDashboard(User $user): bool
    {
        return $user->isPlatformAdmin()
            || $user->isAgencyAdmin()
            || $user->isStaff()
            || $user->isAgentPortalUser();
    }

    public static function canViewPayments(User $user): bool
    {
        if ($user->isPlatformAdmin() || $user->isAgencyAdmin()) {
            return true;
        }

        if ($user->isStaff()) {
            return $user->hasStaffPermission(StaffPermission::BookingsView)
                || $user->hasStaffPermission(StaffPermission::PaymentsVerify)
                || $user->hasStaffPermission(StaffPermission::PaymentsRecord)
                || $user->hasStaffPermission(StaffPermission::LedgerView);
        }

        if ($user->isAgentPortalUser()) {
            return $user->hasAgentPermission(AgentPermission::BookingsView);
        }

        return false;
    }

    public static function canViewCustomers(User $user): bool
    {
        return Gate::forUser($user)->allows('viewAny', User::class);
    }

    public static function canViewTickets(User $user): bool
    {
        if ($user->isPlatformAdmin()) {
            return true;
        }

        if ($user->isStaff()) {
            return $user->hasStaffPermission(StaffPermission::BookingsView)
                || $user->hasStaffPermission(StaffPermission::DocumentsDownload);
        }

        if ($user->isAgentPortalUser()) {
            return $user->hasAgentPermission(AgentPermission::BookingsView);
        }

        return false;
    }

    public static function canViewReports(User $user): bool
    {
        if ($user->isPlatformAdmin()) {
            return true;
        }

        if ($user->isStaff()) {
            return $user->hasStaffPermission(StaffPermission::ReportsView);
        }

        if ($user->isAgentPortalUser()) {
            return $user->hasAgentPermission(AgentPermission::ReportsView);
        }

        return false;
    }

    public static function canViewCms(User $user): bool
    {
        return Gate::forUser($user)->allows('viewAny', CmsPage::class);
    }

    public static function canViewUsers(User $user): bool
    {
        return $user->isPlatformAdmin();
    }

    public static function canViewRoles(User $user): bool
    {
        return $user->isPlatformAdmin();
    }

    public static function canViewPermissions(User $user): bool
    {
        return self::canViewRoles($user);
    }

    public static function canViewSettings(User $user): bool
    {
        if ($user->isPlatformAdmin()) {
            return true;
        }

        return Gate::forUser($user)->allows('client.page-settings.manage');
    }

    public static function canViewAudit(User $user): bool
    {
        return $user->isPlatformAdmin()
            || ($user->isStaff() && $user->hasStaffPermission(StaffPermission::ReportsView));
    }

    public static function assertPermission(User $user, string $permissionKey): void
    {
        $allowed = match ($permissionKey) {
            'dashboard.view' => self::canViewDashboard($user),
            'bookings.view' => Gate::forUser($user)->allows('viewAny', Booking::class),
            'payments.view' => self::canViewPayments($user),
            'customers.view' => self::canViewCustomers($user),
            'suppliers.view' => Gate::forUser($user)->allows('viewAny', SupplierConnection::class),
            'agents.view' => Gate::forUser($user)->allows('viewAny', Agent::class),
            'pnrs.view' => Gate::forUser($user)->allows('viewAny', Booking::class),
            'tickets.view' => self::canViewTickets($user),
            'reports.view' => self::canViewReports($user),
            'cms.view' => self::canViewCms($user),
            'users.view' => self::canViewUsers($user),
            'roles.view' => self::canViewRoles($user),
            'permissions.view' => self::canViewPermissions($user),
            'settings.view' => self::canViewSettings($user),
            'audit.view' => self::canViewAudit($user),
            default => false,
        };

        if (! $allowed) {
            throw new AuthorizationException('You do not have permission to view this data.');
        }
    }
}
