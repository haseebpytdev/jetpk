<?php

namespace App\Policies;

use App\Models\Booking;
use App\Models\User;
use App\Support\Agents\AgentPermission;
use App\Support\Staff\StaffPermission;

class BookingPolicy
{
    public function viewAny(User $user): bool
    {
        if ($user->isAgentAdmin()) {
            return $user->agent() !== null;
        }

        if ($user->isAgentStaff()) {
            return $user->hasAgentPermission(AgentPermission::BookingsView) && $user->agent() !== null;
        }

        if ($user->isStaff()) {
            return $user->hasStaffPermission(StaffPermission::BookingsView);
        }

        return $user->isPlatformAdmin() || $user->isCustomer();
    }

    public function view(User $user, Booking $booking): bool
    {
        if ($user->isPlatformAdmin()) {
            return true;
        }

        if ($user->isStaff()) {
            return $user->current_agency_id === $booking->agency_id
                && $user->hasStaffPermission(StaffPermission::BookingsView);
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

    public function create(User $user): bool
    {
        if ($user->isAgentAdmin()) {
            return $user->agent() !== null;
        }

        if ($user->isAgentStaff()) {
            return $user->hasAgentPermission(AgentPermission::BookingsCreate) && $user->agent() !== null;
        }

        return $user->isPlatformAdmin() || $user->isStaff();
    }

    public function update(User $user, Booking $booking): bool
    {
        if ($user->isPlatformAdmin()) {
            return true;
        }

        if ($user->isStaff()) {
            return $user->current_agency_id === $booking->agency_id;
        }

        return false;
    }

    public function changeStatus(User $user, Booking $booking): bool
    {
        if ($user->isPlatformAdmin()) {
            return true;
        }

        if ($user->isStaff()) {
            return $user->current_agency_id === $booking->agency_id
                && $user->hasStaffPermission(StaffPermission::BookingsUpdateStatus);
        }

        return false;
    }

    public function addNote(User $user, Booking $booking): bool
    {
        if ($user->isAgent() || $user->isCustomer()) {
            return false;
        }

        if ($user->isPlatformAdmin()) {
            return true;
        }

        return $user->isStaff()
            && $user->current_agency_id === $booking->agency_id
            && $user->hasStaffPermission(StaffPermission::BookingsNotes);
    }

    public function assignStaff(User $user, Booking $booking): bool
    {
        if ($user->isAgent() || $user->isCustomer() || $user->isStaff()) {
            return false;
        }

        return $user->isPlatformAdmin();
    }

    public function createSupplierBooking(User $user, Booking $booking): bool
    {
        if ($user->isPlatformAdmin()) {
            return true;
        }

        if ($user->isStaff()) {
            return $user->current_agency_id === $booking->agency_id
                && $user->hasStaffPermission(StaffPermission::BookingsUpdateStatus);
        }

        return false;
    }

    public function releasePiaNdcOptionPnr(User $user, Booking $booking): bool
    {
        return $this->createSupplierBooking($user, $booking);
    }

    public function createPiaNdcOptionPnr(User $user, Booking $booking): bool
    {
        return $this->createSupplierBooking($user, $booking);
    }

    public function previewPiaNdcTicket(User $user, Booking $booking): bool
    {
        return $this->issueTicket($user, $booking);
    }

    public function voidPiaNdcTicket(User $user, Booking $booking): bool
    {
        return $this->issueTicket($user, $booking);
    }

    public function resendPiaNdcEticket(User $user, Booking $booking): bool
    {
        return $this->issueTicket($user, $booking);
    }

    public function recordPayment(User $user, Booking $booking): bool
    {
        if ($user->isPlatformAdmin()) {
            return true;
        }

        return $user->isStaff()
            && $user->current_agency_id === $booking->agency_id
            && $user->hasStaffPermission(StaffPermission::PaymentsRecord);
    }

    public function verifyPayment(User $user, Booking $booking): bool
    {
        if ($user->isPlatformAdmin()) {
            return true;
        }

        if ($user->isStaff()) {
            return $user->current_agency_id === $booking->agency_id
                && $user->hasStaffPermission(StaffPermission::PaymentsVerify);
        }

        return false;
    }

    public function rejectPayment(User $user, Booking $booking): bool
    {
        if ($user->isPlatformAdmin()) {
            return true;
        }

        if ($user->isStaff()) {
            return $user->current_agency_id === $booking->agency_id
                && $user->hasStaffPermission(StaffPermission::PaymentsReject);
        }

        return false;
    }

    public function submitPaymentProof(User $user, Booking $booking): bool
    {
        if ($user->isPlatformAdmin() || $user->isStaff()) {
            return $user->current_agency_id === $booking->agency_id;
        }

        if ($user->isAgentPortalUser()) {
            if ($user->isAgentStaff() && ! $user->hasAgentPermission(AgentPermission::PaymentsUpload)) {
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

    public function issueTicket(User $user, Booking $booking): bool
    {
        if ($user->isPlatformAdmin()) {
            return true;
        }

        if ($user->isStaff()) {
            return $user->current_agency_id === $booking->agency_id
                && $user->hasStaffPermission(StaffPermission::TicketingIssue);
        }

        return false;
    }

    public function viewPlatformReports(User $user): bool
    {
        if ($user->isPlatformAdmin()) {
            return true;
        }

        return $user->isStaff() && $user->hasStaffPermission(StaffPermission::ReportsView);
    }

    public function exportPlatformReports(User $user): bool
    {
        if ($user->isPlatformAdmin()) {
            return true;
        }

        return $user->isStaff() && $user->hasStaffPermission(StaffPermission::ReportsExport);
    }

    public function viewAgencyReports(User $user): bool
    {
        if (! $user->isAgentPortalUser()) {
            return false;
        }

        if ($user->isAgentAdmin()) {
            return $user->agent() !== null;
        }

        return $user->hasAgentPermission(AgentPermission::ReportsView) && $user->agent() !== null;
    }
}
