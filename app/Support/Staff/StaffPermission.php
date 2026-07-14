<?php

namespace App\Support\Staff;

/**
 * Staff portal permission keys stored in users.meta.staff_permissions.
 * Platform admins bypass via portal routing; legacy staff without the meta key retain full access.
 */
final class StaffPermission
{
    public const BookingsView = 'staff.bookings.view';

    public const BookingsUpdateStatus = 'staff.bookings.update_status';

    public const BookingsNotes = 'staff.bookings.notes';

    public const PaymentsRecord = 'staff.payments.record';

    public const PaymentsVerify = 'staff.payments.verify';

    public const PaymentsReject = 'staff.payments.reject';

    public const CancellationsCreate = 'staff.cancellations.create';

    public const CancellationsApprove = 'staff.cancellations.approve';

    public const CancellationsProcess = 'staff.cancellations.process';

    public const RefundsCreate = 'staff.refunds.create';

    public const RefundsApprove = 'staff.refunds.approve';

    public const RefundsMarkPaid = 'staff.refunds.mark_paid';

    public const RefundsReject = 'staff.refunds.reject';

    public const DocumentsGenerate = 'staff.documents.generate';

    public const DocumentsDownload = 'staff.documents.download';

    public const TicketingIssue = 'staff.ticketing.issue';

    public const SupportView = 'staff.support.view';

    public const SupportReply = 'staff.support.reply';

    public const SupportStatus = 'staff.support.status';

    public const LedgerView = 'staff.ledger.view';

    public const LedgerManage = 'staff.ledger.manage';

    public const LedgerAdjust = 'staff.ledger.adjust';

    public const ReportsView = 'staff.reports.view';

    public const ReportsExport = 'staff.reports.export';

    public const PageSettingsManage = 'staff.page_settings.manage';

    public const PresetManager = 'staff_manager';

    public const PresetOperator = 'staff_operator';

    public const PresetSupport = 'staff_support';

    /**
     * Permission keys assignable to staff via admin Users & Access.
     *
     * @return list<string>
     */
    public static function staffSelectable(): array
    {
        return self::all();
    }

    /**
     * @return list<string>
     */
    public static function presetKeys(): array
    {
        return [
            self::PresetManager,
            self::PresetOperator,
            self::PresetSupport,
        ];
    }

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::BookingsView,
            self::BookingsUpdateStatus,
            self::BookingsNotes,
            self::PaymentsRecord,
            self::PaymentsVerify,
            self::PaymentsReject,
            self::CancellationsCreate,
            self::CancellationsApprove,
            self::CancellationsProcess,
            self::RefundsCreate,
            self::RefundsApprove,
            self::RefundsMarkPaid,
            self::RefundsReject,
            self::DocumentsGenerate,
            self::DocumentsDownload,
            self::TicketingIssue,
            self::SupportView,
            self::SupportReply,
            self::SupportStatus,
            self::LedgerView,
            self::LedgerManage,
            self::LedgerAdjust,
            self::ReportsView,
            self::ReportsExport,
            self::PageSettingsManage,
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return [
            self::BookingsView => 'View bookings',
            self::BookingsUpdateStatus => 'Update booking status',
            self::BookingsNotes => 'Add booking notes',
            self::PaymentsRecord => 'Record payments',
            self::PaymentsVerify => 'Verify payments',
            self::PaymentsReject => 'Reject payments',
            self::CancellationsCreate => 'Create cancellation requests',
            self::CancellationsApprove => 'Approve cancellations',
            self::CancellationsProcess => 'Process cancellations',
            self::RefundsCreate => 'Create refunds',
            self::RefundsApprove => 'Approve refunds',
            self::RefundsMarkPaid => 'Mark refunds paid',
            self::RefundsReject => 'Reject refunds',
            self::DocumentsGenerate => 'Generate documents',
            self::DocumentsDownload => 'Download documents',
            self::TicketingIssue => 'Issue tickets',
            self::SupportView => 'View support tickets',
            self::SupportReply => 'Reply to support tickets',
            self::SupportStatus => 'Update support ticket status',
            self::LedgerView => 'View master ledger',
            self::LedgerManage => 'Manage ledger statuses',
            self::LedgerAdjust => 'Manual ledger adjustments',
            self::ReportsView => 'View platform reports',
            self::ReportsExport => 'Export platform reports',
            self::PageSettingsManage => 'Manage client page settings',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function presetLabels(): array
    {
        return [
            self::PresetManager => 'Staff manager — full staff portal access',
            self::PresetOperator => 'Staff operator — bookings, payments, documents, create cancellations/refunds',
            self::PresetSupport => 'Staff support — view bookings and manage support tickets',
        ];
    }

    /**
     * @return list<string>
     */
    public static function presetPermissions(string $preset): array
    {
        return match ($preset) {
            self::PresetManager => self::all(),
            self::PresetOperator => [
                self::BookingsView,
                self::BookingsUpdateStatus,
                self::BookingsNotes,
                self::PaymentsRecord,
                self::PaymentsVerify,
                self::PaymentsReject,
                self::DocumentsGenerate,
                self::DocumentsDownload,
                self::CancellationsCreate,
                self::RefundsCreate,
                self::ReportsView,
            ],
            self::PresetSupport => [
                self::BookingsView,
                self::SupportView,
                self::SupportReply,
                self::SupportStatus,
            ],
            default => [],
        };
    }
}
