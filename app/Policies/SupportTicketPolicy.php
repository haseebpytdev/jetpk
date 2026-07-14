<?php

namespace App\Policies;

use App\Enums\SupportTicketStatus;
use App\Models\SupportTicket;
use App\Models\User;
use App\Support\Agents\AgentPermission;
use App\Support\Staff\StaffPermission;

class SupportTicketPolicy
{
    public function viewAny(User $user): bool
    {
        if ($user->isAgentPortalUser()) {
            return $user->hasAgentPermission(AgentPermission::SupportManage);
        }

        return $user->isCustomer()
            || ($user->isStaff() && $user->hasStaffPermission(StaffPermission::SupportView))
            || $user->isPlatformAdmin();
    }

    public function view(User $user, SupportTicket $ticket): bool
    {
        if ($user->isPlatformAdmin()) {
            return true;
        }

        if ($user->isStaff()) {
            return $user->current_agency_id === $ticket->agency_id
                && $user->hasStaffPermission(StaffPermission::SupportView);
        }

        if ($user->isAgentPortalUser()) {
            if (! $user->hasAgentPermission(AgentPermission::SupportManage)) {
                return false;
            }

            if ($user->current_agency_id !== $ticket->agency_id) {
                return false;
            }

            $agent = $user->agent();
            if ($agent === null) {
                return false;
            }

            if ($ticket->isForwardedToAgent((int) $agent->id)) {
                return true;
            }

            if ($user->isAgentAdmin()) {
                if (in_array((int) $ticket->created_by_user_id, $user->ownerAgentPortalUserIds(), true)) {
                    return true;
                }

                if ($ticket->booking_id) {
                    $ticket->loadMissing('booking');

                    return (int) ($ticket->booking?->agent_id ?? 0) === (int) $agent->id;
                }

                return false;
            }

            return (int) $ticket->created_by_user_id === (int) $user->id;
        }

        if ($user->isCustomer()) {
            return $ticket->created_by_user_id === $user->id;
        }

        return false;
    }

    public function create(User $user): bool
    {
        if ($user->isCustomer()) {
            return true;
        }

        return $user->isAgentPortalUser()
            && $user->hasAgentPermission(AgentPermission::SupportManage)
            && $user->agent() !== null;
    }

    public function reply(User $user, SupportTicket $ticket): bool
    {
        if (! $this->view($user, $ticket)) {
            return false;
        }

        if ($user->isStaff() || $user->isPlatformAdmin()) {
            if ($user->isStaff() && ! $user->hasStaffPermission(StaffPermission::SupportReply)) {
                return false;
            }

            return ! $ticket->isClosed();
        }

        return $ticket->status->allowsCustomerReply()
            && in_array($ticket->status, [SupportTicketStatus::Open, SupportTicketStatus::Pending], true);
    }

    public function close(User $user, SupportTicket $ticket): bool
    {
        if ($user->isCustomer() && $ticket->created_by_user_id === $user->id) {
            return $ticket->status->allowsCustomerReply();
        }

        return false;
    }

    public function updateStatus(User $user, SupportTicket $ticket): bool
    {
        if ($user->isPlatformAdmin()) {
            return true;
        }

        if ($user->isStaff()) {
            return $user->current_agency_id === $ticket->agency_id
                && $user->hasStaffPermission(StaffPermission::SupportStatus);
        }

        return false;
    }

    public function assign(User $user, SupportTicket $ticket): bool
    {
        return $user->isPlatformAdmin();
    }

    public function forward(User $user, SupportTicket $ticket): bool
    {
        return $user->isPlatformAdmin();
    }
}
