<?php

namespace App\Support\AgentPortal;

use App\Enums\BookingStatus;
use App\Enums\SupportTicketStatus;
use App\Models\AgentCommissionEntry;
use App\Models\Booking;
use App\Models\SavedTraveler;
use App\Models\SupportTicket;
use App\Models\User;
use App\Services\Agents\AgentCommissionService;
use App\Services\Agents\AgentWalletService;
use App\Support\Agents\AgentPermission;
use Illuminate\Support\Collection;

/**
 * Laravel-authoritative agent dashboard overview metrics and quick actions.
 */
class AgentPortalDashboardPresenter
{
    public function __construct(
        protected AgentCommissionService $commissionService,
        protected AgentWalletService $walletService,
        protected AgentPortalCapabilitiesPresenter $capabilitiesPresenter,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function present(User $user): array
    {
        $agent = $user->agent();
        abort_if($agent === null, 403);

        $capabilities = $this->capabilitiesPresenter->present($user);
        $permissions = $capabilities['permissions'];

        $canViewBookings = (bool) ($permissions['bookings_view'] ?? false);
        $canViewWallet = (bool) ($permissions['wallet_view'] ?? false);
        $canViewCommissions = (bool) ($permissions['commissions_view'] ?? false);
        $canManageSupport = (bool) ($permissions['support_manage'] ?? false);

        $bookingQuery = $canViewBookings
            ? Booking::query()->where('agent_id', $agent->id)
            : Booking::query()->whereRaw('1 = 0');

        $pendingPaymentQuery = (clone $bookingQuery)->where(function ($q): void {
            $q->whereIn('payment_status', ['unpaid', 'partial'])
                ->orWhere('status', BookingStatus::PaymentPending);
        });

        $ticketingPendingQuery = (clone $bookingQuery)->where(function ($q): void {
            $q->whereIn('ticketing_status', ['pending', 'not_started', 'processing'])
                ->where('payment_status', 'paid');
        });

        $today = now()->toDateString();
        $upcomingQuery = (clone $bookingQuery)
            ->whereNotNull('travel_date')
            ->whereDate('travel_date', '>=', $today)
            ->where('status', '!=', BookingStatus::Cancelled)
            ->orderBy('travel_date');

        $commissionEntries = $canViewCommissions
            ? AgentCommissionEntry::query()->where('agent_id', $agent->id)
            : AgentCommissionEntry::query()->whereRaw('1 = 0');

        $commissionPending = $canViewCommissions
            ? (float) (clone $commissionEntries)->where('status', 'pending')->sum('commission_amount')
            : null;

        $commissionEarned = $canViewCommissions
            ? $this->commissionService->calculateBalance($agent)
            : null;

        $walletSummary = $canViewWallet ? $this->walletService->summary($agent) : null;

        $openSupportCount = $canManageSupport
            ? SupportTicket::query()
                ->forAgentPortalUser($user)
                ->whereNotIn('status', [SupportTicketStatus::Resolved, SupportTicketStatus::Closed])
                ->count()
            : 0;

        $recentBookings = (clone $bookingQuery)
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        $firstPending = (clone $pendingPaymentQuery)->orderByDesc('created_at')->first();
        $upcomingBooking = (clone $upcomingQuery)->first();

        $metrics = [
            'total_bookings' => (clone $bookingQuery)->count(),
            'pending_payment' => $pendingPaymentQuery->count(),
            'ticketing_pending' => $ticketingPendingQuery->count(),
            'confirmed_bookings' => (clone $bookingQuery)->where('status', BookingStatus::Confirmed)->count(),
            'upcoming_trips' => (clone $upcomingQuery)->count(),
            'open_support_cases' => $openSupportCount,
            'unread_notifications' => 0,
        ];

        if ($walletSummary !== null) {
            $metrics['wallet_balance'] = (float) $walletSummary['balance'];
            $metrics['available_balance'] = (float) $walletSummary['available_balance'];
            $metrics['pending_deposits'] = (float) $walletSummary['pending_deposits'];
        }

        if ($commissionEarned !== null) {
            $metrics['commission_earned'] = $commissionEarned;
            $metrics['commission_pending'] = $commissionPending;
        }

        return [
            'ok' => true,
            'capabilities' => $capabilities,
            'metrics' => $metrics,
            'notifications_available' => false,
            'wallet_summary' => $walletSummary !== null ? $this->presentWalletSummary($walletSummary) : null,
            'recent_bookings' => $this->presentBookingSummaries($recentBookings),
            'upcoming_booking' => $upcomingBooking ? $this->presentBookingSummary($upcomingBooking) : null,
            'first_pending_payment_booking' => $firstPending ? $this->presentBookingSummary($firstPending) : null,
            'quick_actions' => $this->presentQuickActions($user, $permissions, $firstPending, $upcomingBooking),
        ];
    }

    /**
     * @param  array<string, mixed>  $summary
     * @return array<string, mixed>
     */
    private function presentWalletSummary(array $summary): array
    {
        return [
            'balance' => (float) $summary['balance'],
            'available_balance' => (float) $summary['available_balance'],
            'pending_deposits' => (float) $summary['pending_deposits'],
            'credit_limit' => $summary['credit_limit'] !== null ? (float) $summary['credit_limit'] : null,
            'credit_enabled' => (bool) $summary['credit_enabled'],
            'currency' => (string) $summary['currency'],
        ];
    }

    /**
     * @param  Collection<int, Booking>  $bookings
     * @return list<array<string, mixed>>
     */
    private function presentBookingSummaries(Collection $bookings): array
    {
        return $bookings
            ->map(fn (Booking $booking) => $this->presentBookingSummary($booking))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function presentBookingSummary(Booking $booking): array
    {
        return [
            'booking_reference' => $booking->display_reference,
            'route' => (string) ($booking->route ?? ''),
            'travel_date' => $booking->travel_date?->toDateString(),
            'airline' => (string) ($booking->airline ?? ''),
            'status' => AgentPortalStatusPresenter::bookingStatus($booking),
            'payment_status' => AgentPortalStatusPresenter::paymentStatus($booking),
            'ticketing_status' => AgentPortalStatusPresenter::ticketingStatus($booking),
            'total' => AgentPortalStatusPresenter::bookingTotal($booking),
            'currency' => (string) ($booking->currency ?? 'PKR'),
            'detail_url' => '/agent/bookings/'.$booking->booking_reference,
        ];
    }

    /**
     * @param  array<string, bool>  $permissions
     * @return list<array<string, mixed>>
     */
    private function presentQuickActions(
        User $user,
        array $permissions,
        ?Booking $firstPending,
        ?Booking $upcoming,
    ): array {
        $actions = [];

        if ($permissions['bookings_create'] ?? false) {
            $actions[] = [
                'code' => 'search_flights',
                'label' => 'Search flights',
                'available' => true,
                'url' => '/flights/search',
            ];
        }

        if ($permissions['bookings_view'] ?? false) {
            $actions[] = [
                'code' => 'view_bookings',
                'label' => 'View bookings',
                'available' => true,
                'url' => '/agent/bookings',
            ];
        }

        if ($firstPending !== null) {
            $actions[] = [
                'code' => 'review_pending_payment',
                'label' => 'Review pending payment',
                'available' => true,
                'url' => '/agent/bookings/'.$firstPending->booking_reference,
            ];
        }

        if ($upcoming !== null) {
            $actions[] = [
                'code' => 'view_upcoming_trip',
                'label' => 'View upcoming trip',
                'available' => true,
                'url' => '/agent/bookings/'.$upcoming->booking_reference,
            ];
        }

        if (($permissions['payments_upload'] ?? false) && ($permissions['wallet_view'] ?? false)) {
            $actions[] = [
                'code' => 'request_deposit',
                'label' => 'Request deposit',
                'available' => true,
                'url' => '/agent/deposits/new',
            ];
        }

        if ($permissions['support_manage'] ?? false) {
            $actions[] = [
                'code' => 'view_support',
                'label' => 'View support',
                'available' => true,
                'url' => '/agent/support',
            ];
        }

        $actions[] = [
            'code' => 'update_profile',
            'label' => 'Update profile',
            'available' => true,
            'url' => '/agent/profile',
        ];

        return $actions;
    }
}
