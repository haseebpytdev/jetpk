<?php

namespace App\Policies;

use App\Models\Booking;
use App\Models\BookingRefund;
use App\Models\User;
use App\Support\Staff\StaffPermission;

class BookingRefundPolicy
{
    public function create(User $user, Booking $booking): bool
    {
        if ($user->isPlatformAdmin()) {
            return true;
        }

        return $user->isStaff()
            && $user->current_agency_id === $booking->agency_id
            && $user->hasStaffPermission(StaffPermission::RefundsCreate);
    }

    public function approve(User $user, BookingRefund $refund): bool
    {
        if ($user->isPlatformAdmin()) {
            return true;
        }

        if ($user->isStaff()) {
            return $user->current_agency_id === $refund->agency_id
                && $user->hasStaffPermission(StaffPermission::RefundsApprove);
        }

        return false;
    }

    public function markPaid(User $user, BookingRefund $refund): bool
    {
        if ($user->isPlatformAdmin()) {
            return true;
        }

        if ($user->isStaff()) {
            return $user->current_agency_id === $refund->agency_id
                && $user->hasStaffPermission(StaffPermission::RefundsMarkPaid);
        }

        return false;
    }

    public function reject(User $user, BookingRefund $refund): bool
    {
        if ($user->isPlatformAdmin()) {
            return true;
        }

        if ($user->isStaff()) {
            return $user->current_agency_id === $refund->agency_id
                && $user->hasStaffPermission(StaffPermission::RefundsReject);
        }

        return false;
    }
}
