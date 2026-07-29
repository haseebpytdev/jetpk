<?php

namespace App\Support\AgentPortal;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Agent bookings list JSON for Next.js dashboard.
 */
class AgentPortalBookingsPresenter
{
    /**
     * @return array<string, mixed>
     */
    public function presentIndex(LengthAwarePaginator $bookings, string $filter, User $user): array
    {
        return [
            'ok' => true,
            'filter' => $filter,
            'allowed_filters' => ['all', 'pending_payment', 'pnr_created', 'needs_action', 'cancelled'],
            'bookings' => collect($bookings->items())
                ->map(fn (Booking $booking) => $this->presentListItem($booking, $user))
                ->values()
                ->all(),
            'pagination' => $this->presentPagination($bookings),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function presentListItem(Booking $booking, User $user): array
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $tripType = (string) (data_get($meta, 'search_criteria.trip_type') ?? 'one_way');
        $passengerCount = $booking->relationLoaded('passengers')
            ? $booking->passengers->count()
            : (int) ($booking->passengers()->count());

        $hasPnr = filled($booking->pnr);
        $bookingType = data_get($meta, 'group_booking_id') !== null ? 'group_ticketing' : 'standard';

        $creatorName = data_get($meta, 'creator_context.display_name')
            ?? data_get($meta, 'creator_context.agent_staff_creator_name');

        $item = [
            'booking_reference' => $booking->display_reference,
            'booking_date' => $booking->created_at?->toIso8601String(),
            'trip_type' => $tripType,
            'route' => (string) ($booking->route ?? ''),
            'departure_date' => $booking->travel_date?->toDateString(),
            'airline' => (string) ($booking->airline ?? ''),
            'passenger_count' => $passengerCount,
            'total' => AgentPortalStatusPresenter::bookingTotal($booking),
            'currency' => (string) ($booking->currency ?? 'PKR'),
            'booking_status' => AgentPortalStatusPresenter::bookingStatus($booking),
            'payment_status' => AgentPortalStatusPresenter::paymentStatus($booking),
            'ticketing_status' => AgentPortalStatusPresenter::ticketingStatus($booking),
            'pnr' => $hasPnr ? strtoupper((string) $booking->pnr) : null,
            'booking_type' => $bookingType,
            'creator_name' => $creatorName,
            'detail_url' => '/agent/bookings/'.$booking->booking_reference,
            'next_action' => $this->presentNextAction($booking),
        ];

        if ($user->isAgentAdmin() && $booking->relationLoaded('commissionEntries')) {
            $commission = $booking->commissionEntries->sortByDesc('created_at')->first();
            if ($commission !== null) {
                $item['commission'] = [
                    'amount' => (float) $commission->commission_amount,
                    'currency' => (string) ($booking->currency ?? 'PKR'),
                    'status' => (string) ($commission->status?->value ?? $commission->status),
                ];
            }
        }

        return $item;
    }

    /**
     * @return array{code: string, label: string, url: string|null}|null
     */
    private function presentNextAction(Booking $booking): ?array
    {
        $paymentStatus = (string) ($booking->payment_status ?? 'unpaid');
        if (in_array($paymentStatus, ['unpaid', 'partial'], true) || $booking->status === BookingStatus::PaymentPending) {
            return [
                'code' => 'review_payment',
                'label' => 'Review payment',
                'url' => '/agent/bookings/'.$booking->booking_reference,
            ];
        }

        return [
            'code' => 'view_details',
            'label' => 'View details',
            'url' => '/agent/bookings/'.$booking->booking_reference,
        ];
    }

    /**
     * @return array<string, int|null>
     */
    private function presentPagination(LengthAwarePaginator $paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'from' => $paginator->firstItem(),
            'to' => $paginator->lastItem(),
        ];
    }
}
