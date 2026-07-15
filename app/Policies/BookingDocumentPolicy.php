<?php

namespace App\Policies;

use App\Models\Booking;
use App\Models\BookingDocument;
use App\Models\User;
use App\Support\Staff\StaffPermission;

class BookingDocumentPolicy
{
    public function create(User $user, Booking $booking): bool
    {
        if ($user->isPlatformAdmin()) {
            return true;
        }

        if ($user->isStaff()) {
            return $user->current_agency_id === $booking->agency_id
                && $user->hasStaffPermission(StaffPermission::DocumentsGenerate);
        }

        return false;
    }

    public function view(User $user, BookingDocument $document): bool
    {
        if ($user->isPlatformAdmin()) {
            return true;
        }

        if ($user->isStaff()) {
            return $user->current_agency_id === $document->agency_id
                && $user->hasStaffPermission(StaffPermission::DocumentsDownload);
        }

        if ($user->isCustomer()) {
            return $document->booking?->customer_id === $user->id;
        }

        return false;
    }
}
