<?php

namespace App\Support\CustomerPortal;

use App\Enums\BookingStatus;
use App\Models\Booking;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Customer bookings list JSON for Next.js dashboard.
 */
class CustomerPortalBookingsPresenter
{
    /**
     * @return array<string, mixed>
     */
    public function presentIndex(LengthAwarePaginator $bookings, string $filter): array
    {
        return [
            'ok' => true,
            'filter' => $filter,
            'allowed_filters' => ['all', 'pending_payment', 'pnr_created', 'needs_action', 'cancelled'],
            'bookings' => collect($bookings->items())
                ->map(fn (Booking $booking) => $this->presentListItem($booking))
                ->values()
                ->all(),
            'pagination' => $this->presentPagination($bookings),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function presentListItem(Booking $booking): array
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $tripType = (string) (data_get($meta, 'search_criteria.trip_type') ?? 'one_way');
        $passengerCount = $booking->relationLoaded('passengers')
            ? $booking->passengers->count()
            : (int) ($booking->passengers()->count());

        $hasPnr = filled($booking->pnr);

        return [
            'booking_reference' => $booking->display_reference,
            'booking_date' => $booking->created_at?->toIso8601String(),
            'trip_type' => $tripType,
            'route' => (string) ($booking->route ?? ''),
            'departure_date' => $booking->travel_date?->toDateString(),
            'airline' => (string) ($booking->airline ?? ''),
            'passenger_count' => $passengerCount,
            'total' => CustomerPortalStatusPresenter::customerPayable($booking),
            'currency' => (string) ($booking->currency ?? 'PKR'),
            'booking_status' => CustomerPortalStatusPresenter::bookingStatus($booking),
            'payment_status' => CustomerPortalStatusPresenter::paymentStatus($booking),
            'ticketing_status' => CustomerPortalStatusPresenter::ticketingStatus($booking),
            'pnr' => $hasPnr ? strtoupper((string) $booking->pnr) : null,
            'booking_type' => 'standard',
            'detail_url' => '/customer/bookings/'.$booking->booking_reference,
            'next_action' => $this->presentNextAction($booking),
        ];
    }

    /**
     * @return array{code: string, label: string, url: string|null}|null
     */
    private function presentNextAction(Booking $booking): ?array
    {
        $paymentStatus = (string) ($booking->payment_status ?? 'unpaid');
        if (in_array($paymentStatus, ['unpaid', 'partial'], true) || $booking->status === BookingStatus::PaymentPending) {
            return [
                'code' => 'complete_payment',
                'label' => 'Complete payment',
                'url' => '/customer/bookings/'.$booking->booking_reference,
            ];
        }

        return [
            'code' => 'view_details',
            'label' => 'View details',
            'url' => '/customer/bookings/'.$booking->booking_reference,
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
