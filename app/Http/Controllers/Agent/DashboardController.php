<?php

namespace App\Http\Controllers\Agent;

use App\Enums\BookingStatus;
use App\Http\Controllers\Controller;
use App\Models\AgentCommissionEntry;
use App\Models\Booking;
use App\Models\SavedTraveler;
use App\Services\Agents\AgentCommissionService;
use App\Services\Agents\AgentWalletService;
use App\Support\Agents\AgentPermission;
use App\Support\Ui\MobileViewPreference;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        protected AgentCommissionService $commissionService,
        protected AgentWalletService $walletService,
        protected MobileViewPreference $mobileViewPreference,
    ) {}

    public function index(Request $request): View
    {
        $user = auth()->user();
        abort_if($user === null || ! $user->isAgentPortalUser(), 403);

        $agent = $user->agent();
        abort_if($agent === null, 403);

        $canViewBookings = $user->hasAgentPermission(AgentPermission::BookingsView);
        $canCreateBookings = $user->hasAgentPermission(AgentPermission::BookingsCreate);
        $canViewWallet = $user->hasAgentPermission(AgentPermission::WalletView);
        $canViewLedger = $user->hasAgentPermission(AgentPermission::LedgerView);
        $canViewReports = $user->hasAgentPermission(AgentPermission::ReportsView);
        $canUploadPayments = $user->hasAgentPermission(AgentPermission::PaymentsUpload);
        $canViewCommissions = $user->isAgentAdmin();
        $canManageTravelers = $user->hasAgentPermission(AgentPermission::TravelersManage);
        $canManageSupport = $user->hasAgentPermission(AgentPermission::SupportManage);
        $canViewAgency = $user->hasAgentPermission(AgentPermission::AgencyView);
        $canManageStaff = $user->hasAgentPermission(AgentPermission::StaffManage);

        $bookingQuery = $canViewBookings
            ? Booking::query()->where('agent_id', $agent->id)
            : Booking::query()->whereRaw('1 = 0');

        $pendingPaymentQuery = (clone $bookingQuery)->where(function ($q): void {
            $q->whereIn('payment_status', ['unpaid', 'partial'])
                ->orWhere('status', BookingStatus::PaymentPending);
        });

        $commissionEntries = $canViewCommissions
            ? AgentCommissionEntry::query()->where('agent_id', $agent->id)
            : AgentCommissionEntry::query()->whereRaw('1 = 0');

        $commissionPending = $canViewCommissions
            ? (float) (clone $commissionEntries)->where('status', 'pending')->sum('commission_amount')
            : 0.0;
        $commissionEarned = $canViewCommissions
            ? $this->commissionService->calculateBalance($agent)
            : 0.0;
        $commissionPaid = $canViewCommissions
            ? (float) (clone $commissionEntries)->where('status', 'paid')->sum('commission_amount')
            : 0.0;

        $walletSummary = $canViewWallet
            ? $this->walletService->summary($agent)
            : null;

        $travelersCount = $canManageTravelers
            ? SavedTraveler::query()->where('user_id', $user->id)->count()
            : null;

        $viewData = [
            'role' => 'Agent',
            'agencyName' => $user->currentAgency?->name ?? 'Agent portal',
            'portalPermissions' => [
                'bookings_view' => $canViewBookings,
                'bookings_create' => $canCreateBookings,
                'wallet_view' => $canViewWallet,
                'ledger_view' => $canViewLedger,
                'reports_view' => $canViewReports,
                'payments_upload' => $canUploadPayments,
                'commissions_view' => $canViewCommissions,
                'travelers_manage' => $canManageTravelers,
                'support_manage' => $canManageSupport,
                'agency_view' => $canViewAgency,
                'staff_manage' => $canManageStaff,
            ],
            'bookingKpis' => [
                'total' => (clone $bookingQuery)->count(),
                'pending_payment' => $pendingPaymentQuery->count(),
                'pnr_confirmed' => (clone $bookingQuery)->where(function ($q): void {
                    $q->whereNotNull('pnr')->where('pnr', '!=', '')
                        ->orWhereIn('supplier_booking_status', ['created', 'booked', 'pending_ticketing', 'ticketed']);
                })->count(),
                'commission_earned' => $commissionEarned,
                'commission_pending' => $commissionPending,
            ],
            'financeSummary' => [
                'balance' => $commissionEarned,
                'pending' => $commissionPending,
                'paid' => $commissionPaid,
            ],
            'walletSummary' => $walletSummary,
            'recentBookings' => (clone $bookingQuery)
                ->with(['passengers', 'contact', 'fareBreakdown', 'commissionEntries'])
                ->orderByDesc('created_at')
                ->limit(5)
                ->get(),
            'hasPendingPaymentBooking' => $pendingPaymentQuery->exists(),
            'firstPendingPaymentBooking' => $pendingPaymentQuery->orderByDesc('created_at')->first(),
            'travelersCount' => $travelersCount,
        ];

        if ($this->mobileViewPreference->shouldUseMobileShell($request)) {
            return view('mobile.dashboard.agent', $viewData);
        }

        return view(client_view('index', 'agent'), $viewData);
    }
}
