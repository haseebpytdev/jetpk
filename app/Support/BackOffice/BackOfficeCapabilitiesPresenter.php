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

        if ($has('dashboard.view')) {
            $items[] = ['label' => 'Dashboard', 'href' => '/', 'key' => 'dashboard'];
        }
        if ($has('bookings.view')) {
            $items[] = ['label' => 'Bookings', 'href' => '/bookings', 'key' => 'bookings'];
        }
        if ($has('payments.view')) {
            $items[] = ['label' => 'Payments', 'href' => '/payments', 'key' => 'payments'];
        }
        if ($has('pnrs.view')) {
            $items[] = ['label' => 'PNRs', 'href' => '/pnrs', 'key' => 'pnrs'];
        }
        if ($has('tickets.view')) {
            $items[] = ['label' => 'Tickets', 'href' => '/tickets', 'key' => 'tickets'];
        }
        if ($isAdmin && ($modules['agent_deposits'] ?? false)) {
            $items[] = ['label' => 'Deposits', 'href' => '/deposits', 'key' => 'deposits'];
        }
        if ($has('customers.view')) {
            $items[] = ['label' => 'Customers', 'href' => '/customers', 'key' => 'customers'];
        }
        if ($has('agents.view')) {
            $items[] = ['label' => 'Agents', 'href' => '/agents', 'key' => 'agents'];
        }
        if ($has('suppliers.view')) {
            $items[] = ['label' => 'Suppliers', 'href' => '/suppliers', 'key' => 'suppliers'];
        }
        if ($has('users.view')) {
            $items[] = ['label' => 'Users', 'href' => '/users', 'key' => 'users'];
        }
        if ($has('reports.view')) {
            $items[] = ['label' => 'Reports', 'href' => '/reports', 'key' => 'reports'];
        }
        if ($has('audit.view')) {
            $items[] = ['label' => 'Audit', 'href' => '/audit', 'key' => 'audit'];
        }
        if ($has('settings.view')) {
            $items[] = ['label' => 'Settings', 'href' => '/settings', 'key' => 'settings'];
        }
        if (($isAdmin || $user->hasStaffPermission(StaffPermission::SupportView)) && ($modules['agent_support'] ?? false)) {
            $items[] = ['label' => 'Support', 'href' => '/support', 'key' => 'support'];
        }

        return $items;
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

        return [
            'can_approve' => $canApprove && $request->status->value === 'requested',
            'can_reject' => $canReject && $reviewable,
            'can_process' => false,
            'external_execution_required' => $canProcess,
            'denial_reason' => $canProcess ? 'external_execution_required' : null,
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

        return [
            'can_approve' => $canApprove && in_array($refund->status->value, ['pending'], true),
            'can_reject' => $canReject && $reviewable,
            'can_mark_paid' => false,
            'external_execution_required' => $canMarkPaid,
            'denial_reason' => $canMarkPaid ? 'external_execution_required' : null,
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
