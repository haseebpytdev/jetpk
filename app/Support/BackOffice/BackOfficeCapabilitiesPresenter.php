<?php

namespace App\Support\BackOffice;

use App\Models\AgentDepositRequest;
use App\Models\Booking;
use App\Models\BookingCancellationRequest;
use App\Models\BookingPayment;
use App\Models\BookingRefund;
use App\Models\SupportTicket;
use App\Models\User;
use App\Support\Dashboard\DashboardPermissionResolver;
use App\Support\Platform\PlatformModuleEnforcer;
use App\Support\Staff\StaffPermission;
use Illuminate\Support\Facades\Gate;

/**
 * Admin / Platform Staff dashboard navigation and capability flags for Next.js shell.
 */
class BackOfficeCapabilitiesPresenter
{
    public function __construct(
        protected PlatformModuleEnforcer $platformModules,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function present(User $user, string $portalType = 'admin'): array
    {
        $access = BackOfficePortalAccess::evaluate($user);
        $isAdmin = $user->isPlatformAdmin();
        $effectivePermissions = DashboardPermissionResolver::effectivePermissionKeys($user);

        $modules = [
            'agent_deposits' => $this->platformModules->routeEnabled('agent_deposits'),
            'payment_proofs' => $this->platformModules->routeEnabled('payment_proofs'),
            'agent_support' => $this->platformModules->routeEnabled('agent_support'),
            'agent_reports' => $this->platformModules->routeEnabled('agent_reports'),
        ];

        $capabilities = $this->presentCapabilityFlags($user, $isAdmin, $access, $modules);

        return [
            'ok' => ($access['ok'] ?? false) === true,
            'session_usable' => ($access['ok'] ?? false) === true,
            'denial_reason' => $access['denial_reason'] ?? null,
            'authenticated' => true,
            'user_id' => (string) $user->id,
            'display_name' => $user->name,
            'portal_type' => $portalType,
            'platform_role' => $isAdmin ? 'platform_admin' : 'staff',
            'effective_permissions' => $effectivePermissions,
            'account_status' => $user->status?->value ?? 'active',
            'requires_password_change' => (bool) data_get($user->meta, 'requires_password_change', false),
            'requires_email_verification' => $user->email_verified_at === null,
            'landing_route' => $isAdmin ? '/admin/dashboard' : '/staff/dashboard',
            'identity' => [
                'display_name' => $user->name,
                'email' => DashboardPermissionResolver::roleLabels($user)[0] ?? 'User',
                'role' => $isAdmin ? 'platform_admin' : 'staff',
                'role_label' => $isAdmin ? 'Platform Admin' : 'Platform Staff',
                'status' => $user->status?->value ?? 'active',
            ],
            'modules' => $modules,
            'capabilities' => $capabilities,
            'navigation' => $this->presentNavigation($user, $effectivePermissions, $modules, $isAdmin),
        ];
    }

    /**
     * @param  array{ok: bool, denial_reason?: string|null}  $access
     * @param  array<string, bool>  $modules
     * @return array<string, mixed>
     */
    private function presentCapabilityFlags(User $user, bool $isAdmin, array $access, array $modules): array
    {
        $usable = ($access['ok'] ?? false) === true;

        return [
            'can_view_booking' => $usable && Gate::forUser($user)->allows('viewAny', Booking::class),
            'can_manage_booking' => $usable && ($isAdmin || $user->hasStaffPermission(StaffPermission::BookingsUpdateStatus)),
            'can_review_payment' => $usable && ($isAdmin || $user->hasStaffPermission(StaffPermission::PaymentsVerify)),
            'can_review_deposit' => $usable && $isAdmin && ($modules['agent_deposits'] ?? false),
            'can_review_cancellation' => $usable && ($isAdmin || $user->hasStaffPermission(StaffPermission::CancellationsApprove)),
            'can_review_refund' => $usable && ($isAdmin || $user->hasStaffPermission(StaffPermission::RefundsApprove)),
            'can_view_ticketing' => $usable && Gate::forUser($user)->allows('viewAny', Booking::class),
            'can_issue_ticket' => $usable && ($isAdmin || $user->hasStaffPermission(StaffPermission::TicketingIssue)),
            'can_manage_agency' => $usable && $isAdmin,
            'can_manage_platform_staff' => $usable && $isAdmin,
            'can_view_supplier_health' => $usable && Gate::forUser($user)->allows('viewAny', \App\Models\SupplierConnection::class),
            'can_manage_support' => $usable && ($isAdmin || $user->hasStaffPermission(StaffPermission::SupportReply)),
            'can_view_reports' => $usable && DashboardPermissionResolver::canViewReports($user),
            'can_export_reports' => $usable && ($isAdmin || $user->hasStaffPermission(StaffPermission::ReportsExport)),
            'can_view_audit_logs' => $usable && DashboardPermissionResolver::canViewAudit($user),
            'can_retry_job' => false,
            'reason_codes' => $usable ? [] : [
                'session' => $access['denial_reason'] ?? 'permission_required',
            ],
        ];
    }

    /**
     * @param  list<string>  $effectivePermissions
     * @param  array<string, bool>  $modules
     * @return list<array<string, mixed>>
     */
    private function presentNavigation(User $user, array $effectivePermissions, array $modules, bool $isAdmin): array
    {
        $items = [];
        $has = static fn (string $key): bool => in_array($key, $effectivePermissions, true);
        $bookingsRoute = $isAdmin ? 'admin.bookings' : 'staff.bookings.index';
        $supportRoute = $isAdmin ? 'admin.support.tickets.index' : 'staff.support.tickets.index';

        if ($has('dashboard.view')) {
            $items[] = $this->dashboardNav('Dashboard', 'dashboard', '/');
        }
        if ($has('bookings.view')) {
            $items[] = $this->dashboardNav('Bookings', 'bookings', '/bookings');
            if ($isAdmin || $user->hasStaffPermission(StaffPermission::CancellationsApprove)) {
                $items[] = $this->laravelNav('Cancellations', 'cancellations', $bookingsRoute, ['queue' => 'cancellations']);
            }
            if ($isAdmin || $user->hasStaffPermission(StaffPermission::BookingsUpdateStatus)) {
                $items[] = $this->laravelNav('Execution queue', 'execution', $bookingsRoute, ['queue' => 'needs_action']);
            }
        }
        if ($has('payments.view')) {
            $items[] = $this->dashboardNav('Payments', 'payments', '/payments');
        }
        if ($has('pnrs.view')) {
            $items[] = $this->dashboardNav('PNRs', 'pnrs', '/pnrs');
        }
        if ($has('tickets.view')) {
            $items[] = $this->dashboardNav('Tickets', 'tickets', '/tickets');
        }
        if ($isAdmin && ($modules['agent_deposits'] ?? false)) {
            $items[] = $this->dashboardNav('Deposits', 'deposits', '/deposits');
        }
        if ($has('customers.view')) {
            $items[] = $this->dashboardNav('Customers', 'customers', '/customers');
        }
        if ($has('agents.view')) {
            $items[] = $this->dashboardNav('Agents', 'agents', '/agents');
        }
        if ($has('suppliers.view')) {
            $items[] = $this->dashboardNav('Suppliers', 'suppliers', '/suppliers');
            if ($isAdmin) {
                $items[] = $this->laravelNav('API settings', 'api-settings', 'admin.api-settings');
            }
        }
        if ($has('users.view')) {
            $items[] = $this->dashboardNav('Users', 'users', '/users');
        }
        if ($isAdmin) {
            $items[] = $this->laravelNav('Staff', 'staff', 'admin.staff');
        }
        if ($has('cms.view')) {
            $items[] = $this->dashboardNav('CMS', 'cms', '/cms');
        }
        if ($has('reports.view')) {
            $items[] = $this->dashboardNav('Reports', 'reports', '/reports');
        }
        if ($has('audit.view')) {
            $items[] = $this->dashboardNav('Audit', 'audit', '/audit');
        }
        if ($has('settings.view')) {
            $items[] = $this->dashboardNav('Settings', 'settings', '/settings');
            if ($isAdmin && ($modules['branding_settings'] ?? false)) {
                $items[] = $this->laravelNav('Branding', 'branding', 'admin.settings.branding.edit');
            }
            if ($isAdmin) {
                $items[] = $this->laravelNav('Page settings', 'page-settings', 'admin.page-settings.index');
            }
            if ($isAdmin && $this->platformModules->routeEnabled('markups')) {
                $items[] = $this->laravelNav('Markups', 'markups', 'admin.markups');
            }
            if ($isAdmin && ($modules['notifications'] ?? false)) {
                $items[] = $this->laravelNav('Communications', 'communications', 'admin.settings.communications.index');
            }
            if ($isAdmin) {
                $items[] = $this->laravelNav('Go-live checklist', 'go-live', 'admin.go-live-checklist');
            }
        }
        if ($isAdmin) {
            $items[] = $this->laravelNav('Flight search', 'flights-search', 'flights.search');
        }
        if (($isAdmin || $user->hasStaffPermission(StaffPermission::SupportView)) && ($modules['agent_support'] ?? false)) {
            $items[] = $this->laravelNav('Support', 'support', $supportRoute);
        }

        return array_values(array_filter($items));
    }

    /**
     * @return array{label: string, href: string, key: string, target: string}
     */
    private function dashboardNav(string $label, string $key, string $href): array
    {
        return [
            'label' => $label,
            'href' => $href,
            'key' => $key,
            'target' => 'dashboard',
        ];
    }

    /**
     * @param  array<string, string|null>  $params
     * @return array{label: string, href: string, key: string, target: string}|null
     */
    private function laravelNav(string $label, string $key, string $routeName, array $params = []): ?array
    {
        $filtered = array_filter($params, static fn ($value) => $value !== null && $value !== '');
        $href = BackOfficeLaravelRoutePaths::publicPathFromRoute($routeName, $filtered);
        if ($href === null) {
            return null;
        }

        return [
            'label' => $label,
            'href' => $href,
            'key' => $key,
            'target' => 'laravel',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function presentBookingPaymentCapabilities(User $user, BookingPayment $payment): array
    {
        $booking = $payment->booking;
        $canVerify = Gate::forUser($user)->allows('verifyPayment', $booking);
        $canReject = Gate::forUser($user)->allows('rejectPayment', $booking);
        $alreadyProcessed = ! in_array($payment->status->value, ['pending', 'submitted'], true);

        return [
            'can_verify' => $canVerify && ! $alreadyProcessed,
            'can_reject' => $canReject && ! $alreadyProcessed,
            'already_processed' => $alreadyProcessed,
            'denial_reason' => $alreadyProcessed ? 'already_processed' : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function presentDepositCapabilities(User $user, AgentDepositRequest $deposit): array
    {
        $canApprove = Gate::forUser($user)->allows('approve', $deposit);
        $canReject = Gate::forUser($user)->allows('reject', $deposit);
        $pending = $deposit->status->value === 'submitted';

        return [
            'can_approve' => $canApprove && $pending,
            'can_reject' => $canReject && $pending,
            'already_processed' => ! $pending,
            'denial_reason' => ! $pending ? 'already_processed' : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function presentCancellationCapabilities(User $user, BookingCancellationRequest $request): array
    {
        $canApprove = Gate::forUser($user)->allows('approve', $request);
        $canReject = Gate::forUser($user)->allows('reject', $request);
        $canProcess = Gate::forUser($user)->allows('process', $request);
        $reviewable = in_array($request->status->value, ['requested', 'approved'], true);
        $pendingReconciliation = ($request->meta['sabre_cancel_manual_review'] ?? false) === true
            || filled($request->meta['manual_warning'] ?? null);
        $alreadyProcessed = in_array($request->status->value, ['processed', 'rejected'], true);
        $processEligible = $canProcess
            && $request->status->value === 'approved'
            && ! $pendingReconciliation;

        return [
            'can_approve' => $canApprove && $request->status->value === 'requested',
            'can_reject' => $canReject && $reviewable,
            'can_process' => $processEligible,
            'already_processed' => $alreadyProcessed,
            'pending_reconciliation' => $pendingReconciliation,
            'denial_reason' => $alreadyProcessed
                ? 'already_processed'
                : ($pendingReconciliation ? 'pending_reconciliation' : null),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function presentRefundCapabilities(User $user, BookingRefund $refund): array
    {
        $canApprove = Gate::forUser($user)->allows('approve', $refund);
        $canReject = Gate::forUser($user)->allows('reject', $refund);
        $canMarkPaid = Gate::forUser($user)->allows('markPaid', $refund);
        $reviewable = in_array($refund->status->value, ['pending', 'approved'], true);
        $alreadyProcessed = in_array($refund->status->value, ['paid', 'rejected'], true);
        $markPaidEligible = $canMarkPaid && $refund->status->value === 'approved';

        return [
            'can_approve' => $canApprove && in_array($refund->status->value, ['pending'], true),
            'can_reject' => $canReject && $reviewable,
            'can_mark_paid' => $markPaidEligible,
            'already_processed' => $alreadyProcessed,
            'denial_reason' => $alreadyProcessed ? 'already_processed' : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function presentBookingTicketingCapabilities(User $user, Booking $booking): array
    {
        $canIssue = Gate::forUser($user)->allows('issueTicket', $booking);
        $alreadyTicketed = $booking->status === \App\Enums\BookingStatus::Ticketed
            || ($booking->ticketing_status ?? '') === 'ticketed';

        return [
            'can_issue_ticket' => $canIssue && ! $alreadyTicketed,
            'already_ticketed' => $alreadyTicketed,
            'denial_reason' => $alreadyTicketed ? 'already_ticketed' : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function presentSupportCapabilities(User $user, SupportTicket $ticket): array
    {
        $canReply = Gate::forUser($user)->allows('reply', $ticket);
        $canUpdateStatus = Gate::forUser($user)->allows('update', $ticket);

        return [
            'can_reply' => $canReply,
            'can_update_status' => $canUpdateStatus,
            'can_assign' => $user->isPlatformAdmin(),
        ];
    }
}
