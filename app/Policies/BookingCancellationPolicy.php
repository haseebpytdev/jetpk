<?php

namespace App\Policies;

use App\Models\Booking;
use App\Models\BookingCancellationRequest;
use App\Models\User;
use App\Support\Agents\AgentPermission;
use App\Support\Staff\StaffPermission;

class BookingCancellationPolicy
{
    public function request(User $user, Booking $booking): bool
    {
        if ($user->isPlatformAdmin()) {
            return true;
        }

        if ($user->isStaff()) {
            return $user->current_agency_id === $booking->agency_id
                && $user->hasStaffPermission(StaffPermission::CancellationsCreate);
        }

        if ($user->isAgentPortalUser()) {
            if ($user->isAgentStaff() && ! $user->hasAgentPermission(AgentPermission::BookingsView)) {
                return false;
            }

            $agent = $user->agent();

            return $agent !== null
                && $booking->agency_id === $user->current_agency_id
                && $booking->agent_id === $agent->id;
        }

        if ($user->isCustomer()) {
            return $booking->customer_id === $user->id;
        }

        return false;
    }

    public function approve(User $user, BookingCancellationRequest $request): bool
    {
        if ($user->isPlatformAdmin()) {
            return true;
        }

        if ($user->isStaff()) {
            return $user->current_agency_id === $request->agency_id
                && $user->hasStaffPermission(StaffPermission::CancellationsApprove);
        }

        return false;
    }

    public function reject(User $user, BookingCancellationRequest $request): bool
    {
        return $this->approve($user, $request);
    }

    public function process(User $user, BookingCancellationRequest $request): bool
    {
        if ($user->isPlatformAdmin()) {
            return true;
        }

        if ($user->isStaff()) {
            return $user->current_agency_id === $request->agency_id
                && $user->hasStaffPermission(StaffPermission::CancellationsProcess);
        }

        return false;
    }
}
