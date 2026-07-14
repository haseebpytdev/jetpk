<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Booking;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;

trait ResolvesSupportTicketBookings
{
    protected function resolveOptionalBooking(User $user, ?int $bookingId): ?Booking
    {
        if ($bookingId === null) {
            return null;
        }

        $booking = Booking::query()->findOrFail($bookingId);
        Gate::authorize('view', $booking);

        return $booking;
    }

    /**
     * @return Collection<int, Booking>
     */
    protected function bookableOptionsForUser(User $user)
    {
        $query = Booking::query()->orderByDesc('created_at')->limit(50);

        if ($user->isCustomer()) {
            $query->where('customer_id', $user->id);
        } elseif ($user->isAgentPortalUser()) {
            $agent = $user->agent();
            if ($agent === null) {
                return collect();
            }
            $query->where('agent_id', $agent->id)
                ->where('agency_id', $user->current_agency_id);
        }

        return $query->get(['id', 'booking_reference', 'route', 'travel_date', 'status']);
    }
}
