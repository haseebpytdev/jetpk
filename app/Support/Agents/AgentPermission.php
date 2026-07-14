<?php

namespace App\Support\Agents;

/**
 * Agent portal permission keys for agent staff users.
 * Agent admins implicitly have all permissions.
 */
final class AgentPermission
{
    public const BookingsView = 'agent.bookings.view';

    public const BookingsCreate = 'agent.bookings.create';

    public const WalletView = 'agent.wallet.view';

    public const LedgerView = 'agent.ledger.view';

    public const LedgerManage = 'agent.ledger.manage';

    public const ReportsView = 'agent.reports.view';

    public const PaymentsUpload = 'agent.payments.upload';

    public const TravelersManage = 'agent.travelers.manage';

    public const SupportManage = 'agent.support.manage';

    public const StaffManage = 'agent.staff.manage';

    public const ProfileManage = 'agent.profile.manage';

    public const AgencyView = 'agent.agency.view';

    public const AgencyEdit = 'agent.agency.edit';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::BookingsView,
            self::BookingsCreate,
            self::WalletView,
            self::LedgerView,
            self::LedgerManage,
            self::ReportsView,
            self::PaymentsUpload,
            self::TravelersManage,
            self::SupportManage,
            self::StaffManage,
            self::ProfileManage,
            self::AgencyView,
            self::AgencyEdit,
        ];
    }

    /**
     * Permissions assignable to agent staff (excludes reserved/unused keys).
     *
     * @return list<string>
     */
    public static function staffSelectable(): array
    {
        return array_values(array_filter(
            self::all(),
            static fn (string $permission): bool => ! in_array($permission, [self::ProfileManage, self::AgencyEdit], true),
        ));
    }

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return [
            self::BookingsView => 'View bookings',
            self::BookingsCreate => 'Create bookings',
            self::WalletView => 'View wallet',
            self::LedgerView => 'View ledger',
            self::LedgerManage => 'Manage ledger actions',
            self::ReportsView => 'View agency reports',
            self::PaymentsUpload => 'Upload payments',
            self::TravelersManage => 'Manage travelers',
            self::SupportManage => 'Manage support tickets',
            self::StaffManage => 'Manage staff users',
            self::AgencyView => 'View agency details',
            self::AgencyEdit => 'Edit agency details',
        ];
    }
}
