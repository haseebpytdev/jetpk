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
            'navigation' => $this->presentNavigationFlat($user, $effectivePermissions, $modules, $isAdmin),
            'navigation_groups' => $this->presentNavigationGroups($user, $effectivePermissions, $modules, $isAdmin),
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
    private function presentNavigationFlat(User $user, array $effectivePermissions, array $modules, bool $isAdmin): array
    {
        $groups = $this->presentNavigationGroups($user, $effectivePermissions, $modules, $isAdmin);
        $flat = [];
        foreach ($groups as $group) {
            foreach ($group['items'] as $item) {
                $flat[] = $item;
            }
        }

        return $flat;
    }

    /**
     * @param  list<string>  $effectivePermissions
     * @param  array<string, bool>  $modules
     * @return list<array{label: string, items: list<array<string, mixed>>}>
     */
    private function presentNavigationGroups(User $user, array $effectivePermissions, array $modules, bool $isAdmin): array
    {
        $has = static fn (string $key): bool => in_array($key, $effectivePermissions, true);

        $groups = [];

        $overview = [];
        if ($has('dashboard.view')) {
            $overview[] = $this->dashboardNav('Dashboard', 'dashboard', '/');
        }
        if ($overview !== []) {
            $groups[] = ['label' => 'Overview', 'items' => $overview];
        }

        $bookingOps = [];
        if ($has('bookings.view')) {
            $bookingOps[] = $this->dashboardNav('Bookings', 'bookings', '/bookings');
            if ($isAdmin || $user->hasStaffPermission(StaffPermission::BookingsUpdateStatus)) {
                $bookingOps[] = $this->dashboardNav('Execution', 'execution', '/operations/execution');
            }
            if ($isAdmin || $user->hasStaffPermission(StaffPermission::CancellationsApprove)) {
                $bookingOps[] = $this->dashboardNav('Cancellations', 'cancellations', '/operations/review');
            }
        }
        if ($has('pnrs.view')) {
            $bookingOps[] = $this->dashboardNav('PNRs', 'pnrs', '/pnrs');
        }
        if ($has('tickets.view')) {
            $bookingOps[] = $this->dashboardNav('Tickets', 'tickets', '/tickets');
        }
        if ($bookingOps !== []) {
            $groups[] = ['label' => 'Booking operations', 'items' => array_values(array_filter($bookingOps))];
        }

        $finance = [];
        if ($has('payments.view')) {
            $finance[] = $this->dashboardNav('Payments', 'payments', '/payments');
        }
        if ($isAdmin && ($modules['agent_deposits'] ?? false)) {
            $finance[] = $this->dashboardNav('Deposits', 'deposits', '/deposits');
        }
        if ($isAdmin && $this->platformModules->routeEnabled('markup_settings')) {
            $finance[] = $this->dashboardNav('Markups', 'markups', '/markups');
        }
        if ($isAdmin) {
            $finance[] = $this->dashboardNav('Commissions', 'commissions', '/commissions');
        }
        if ($finance !== []) {
            $groups[] = ['label' => 'Finance', 'items' => array_values(array_filter($finance))];
        }

        $customers = [];
        if ($has('customers.view')) {
            $customers[] = $this->dashboardNav('Customers', 'customers', '/customers');
        }
        if ($has('agents.view')) {
            $customers[] = $this->dashboardNav('Agents', 'agents', '/agents');
        }
        if ($isAdmin) {
            $customers[] = $this->dashboardNav('Agent applications', 'agent-applications', '/agents/applications');
        }
        if ($customers !== []) {
            $groups[] = ['label' => 'Customers & distribution', 'items' => array_values(array_filter($customers))];
        }

        $suppliers = [];
        if ($has('suppliers.view')) {
            $suppliers[] = $this->dashboardNav('Suppliers', 'suppliers', '/suppliers');
        }
        if ($suppliers !== []) {
            $groups[] = ['label' => 'Suppliers', 'items' => array_values(array_filter($suppliers))];
        }

        $content = [];
        if ($has('cms.view')) {
            $content[] = $this->dashboardNav('CMS', 'cms', '/cms');
        }
        if ($isAdmin && ($modules['branding_settings'] ?? false)) {
            $content[] = $this->dashboardNav('Branding', 'branding', '/settings/general');
        }
        if ($isAdmin) {
            $content[] = $this->dashboardNav('Pages', 'cms-pages', '/cms/pages');
            $content[] = $this->dashboardNav('Homepage', 'homepage', '/cms/sections');
            $content[] = $this->dashboardNav('Media library', 'media', '/cms/assets');
        }
        if ($content !== []) {
            $groups[] = ['label' => 'Content & website', 'items' => array_values(array_filter($content))];
        }

        $communications = [];
        if (($isAdmin || $user->hasStaffPermission(StaffPermission::SupportView)) && ($modules['agent_support'] ?? false)) {
            $communications[] = $this->dashboardNav('Support', 'support', '/support');
        }
        if ($isAdmin && ($modules['notifications'] ?? false)) {
            $communications[] = $this->dashboardNav('Communications', 'communications', '/settings/notifications');
        }
        if ($communications !== []) {
            $groups[] = ['label' => 'Communications', 'items' => array_values(array_filter($communications))];
        }

        $administration = [];
        if ($has('users.view')) {
            $administration[] = $this->dashboardNav('Users', 'users', '/users');
        }
        if ($isAdmin) {
            $administration[] = $this->dashboardNav('Staff', 'staff', '/staff');
            $administration[] = $this->dashboardNav('Roles & permissions', 'roles-permissions', '/users/roles');
        }
        if ($administration !== []) {
            $groups[] = ['label' => 'Administration', 'items' => array_values(array_filter($administration))];
        }

        $reporting = [];
        if ($has('reports.view')) {
            $reporting[] = $this->dashboardNav('Reports', 'reports', '/reports');
        }
        if ($has('audit.view')) {
            $reporting[] = $this->dashboardNav('Audit', 'audit', '/audit');
        }
        if ($reporting !== []) {
            $groups[] = ['label' => 'Reporting', 'items' => array_values(array_filter($reporting))];
        }

        $system = [];
        if ($isAdmin && $has('suppliers.view')) {
            $system[] = $this->dashboardNav('API Connections', 'api-settings', '/api-connections');
        }
        if ($has('settings.view')) {
            $system[] = $this->dashboardNav('Settings', 'settings', '/settings');
        }
        if ($isAdmin) {
            $system[] = $this->dashboardNav('Go-live checklist', 'go-live', '/system/go-live');
            $system[] = $this->dashboardNav('System health', 'system-health', '/system/health');
        }
        if ($system !== []) {
            $groups[] = ['label' => 'System', 'items' => array_values(array_filter($system))];
        }

        return array_values(array_filter($groups, static fn (array $group): bool => ($group['items'] ?? []) !== []));
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
