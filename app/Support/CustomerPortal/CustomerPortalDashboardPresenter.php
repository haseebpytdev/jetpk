<?php

namespace App\Support\CustomerPortal;

use App\Enums\BookingStatus;
use App\Enums\SupportTicketStatus;
use App\Models\Booking;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Laravel-authoritative customer dashboard overview metrics and quick actions.
 */
class CustomerPortalDashboardPresenter
{
    /**
     * @return array<string, mixed>
     */
    public function present(User $user): array
    {
        $customerId = (int) $user->id;
        $bookings = Booking::query()->where('customer_id', $customerId);

        $pendingPaymentQuery = (clone $bookings)->where(function ($q): void {
            $q->whereIn('payment_status', ['unpaid', 'partial'])
                ->orWhere('status', BookingStatus::PaymentPending);
        });

        $today = now()->toDateString();
        $upcomingQuery = (clone $bookings)
            ->whereNotNull('travel_date')
            ->whereDate('travel_date', '>=', $today)
            ->where('status', '!=', BookingStatus::Cancelled)
            ->orderBy('travel_date');

        $ticketingPendingQuery = (clone $bookings)->where(function ($q): void {
            $q->whereIn('ticketing_status', ['pending', 'not_started', 'processing'])
                ->where('payment_status', 'paid');
        });

        $recentBookings = (clone $bookings)
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        $firstPending = (clone $pendingPaymentQuery)->orderByDesc('created_at')->first();
        $upcomingBooking = (clone $upcomingQuery)->first();

        $openSupportCount = SupportTicket::query()
            ->where('created_by_user_id', $customerId)
            ->whereNotIn('status', [SupportTicketStatus::Resolved, SupportTicketStatus::Closed])
            ->count();

        $metrics = [
            'upcoming_trips' => (clone $upcomingQuery)->count(),
            'pending_payment' => $pendingPaymentQuery->count(),
            'ticketing_pending' => $ticketingPendingQuery->count(),
            'confirmed_bookings' => (clone $bookings)->where('status', BookingStatus::Confirmed)->count(),
            'total_bookings' => (clone $bookings)->count(),
            'open_support_cases' => $openSupportCount,
            'unread_notifications' => 0,
        ];

        return [
            'ok' => true,
            'metrics' => $metrics,
            'notifications_available' => false,
            'recent_bookings' => $this->presentBookingSummaries($recentBookings),
            'upcoming_booking' => $upcomingBooking ? $this->presentBookingSummary($upcomingBooking) : null,
            'first_pending_payment_booking' => $firstPending ? $this->presentBookingSummary($firstPending) : null,
            'quick_actions' => $this->presentQuickActions($metrics, $firstPending, $upcomingBooking),
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
            // Match bookings list + Next CustomerBookingListItem (`booking_status`).
            'booking_status' => CustomerPortalStatusPresenter::bookingStatus($booking),
            'payment_status' => CustomerPortalStatusPresenter::paymentStatus($booking),
            'ticketing_status' => CustomerPortalStatusPresenter::ticketingStatus($booking),
            'total' => CustomerPortalStatusPresenter::customerPayable($booking),
            'currency' => (string) ($booking->currency ?? 'PKR'),
            'detail_url' => CustomerPortalBookingUrl::detailPath($booking),
        ];
    }

    /**
     * @param  array<string, int>  $metrics
     * @return list<array<string, mixed>>
     */
    private function presentQuickActions(array $metrics, ?Booking $firstPending, ?Booking $upcoming): array
    {
        $actions = [
            [
                'code' => 'search_flights',
                'label' => 'Search flights',
                'available' => true,
                'url' => '/flights/search',
            ],
            [
                'code' => 'view_bookings',
                'label' => 'View bookings',
                'available' => true,
                'url' => '/customer/bookings',
            ],
        ];

        if ($firstPending !== null) {
            $actions[] = [
                'code' => 'complete_pending_payment',
                'label' => 'Complete pending payment',
                'available' => true,
                'url' => CustomerPortalBookingUrl::detailPath($firstPending),
            ];
        }

        if ($upcoming !== null) {
            $actions[] = [
                'code' => 'view_upcoming_trip',
                'label' => 'View upcoming trip',
                'available' => true,
                'url' => CustomerPortalBookingUrl::detailPath($upcoming),
            ];
        }

        if ($metrics['open_support_cases'] > 0) {
            $actions[] = [
                'code' => 'view_support',
                'label' => 'View support cases',
                'available' => true,
                'url' => '/customer/support',
            ];
        }

        $actions[] = [
            'code' => 'update_profile',
            'label' => 'Update profile',
            'available' => true,
            'url' => '/customer/profile',
        ];

        return $actions;
    }
}
