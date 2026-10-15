<?php

namespace App\Policies;

use App\Models\Booking;
use App\Models\BookingCancellationRequest;
use App\Models\User;
use App\Support\Agents\AgentPermission;
use App\Support\Staff\StaffPermission;

class BookingCancellationPolicy
{
    public function viewAny(User $user): bool
    {
        if ($user->isPlatformAdmin()) {
            return true;
        }

        return $user->isStaff() && (
            $user->hasStaffPermission(StaffPermission::CancellationsApprove)
            || $user->hasStaffPermission(StaffPermission::CancellationsProcess)
            || $user->hasStaffPermission(StaffPermission::CancellationsCreate)
        );
    }

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
            // Agent staff: view alone must never authorize cancellation requests.
            // Require BookingsView + CancellationsRequest plus agency/agent ownership.
            // Agent owners/admins retain ownership-scoped access without the explicit key.
            if ($user->isAgentStaff()) {
                if (! $user->hasAgentPermission(AgentPermission::BookingsView)
                    || ! $user->hasAgentPermission(AgentPermission::CancellationsRequest)) {
                    return false;
                }
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
