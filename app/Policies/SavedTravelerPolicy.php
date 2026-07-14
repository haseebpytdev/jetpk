<?php

namespace App\Policies;

use App\Models\SavedTraveler;
use App\Models\User;
use App\Support\Agents\AgentPermission;

class SavedTravelerPolicy
{
    public function viewAny(User $user): bool
    {
        if ($user->isCustomer()) {
            return true;
        }

        return $user->isAgentPortalUser()
            && $user->hasAgentPermission(AgentPermission::TravelersManage)
            && $user->agent() !== null;
    }

    public function view(User $user, SavedTraveler $traveler): bool
    {
        return $this->ownsTraveler($user, $traveler);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, SavedTraveler $traveler): bool
    {
        return $this->ownsTraveler($user, $traveler);
    }

    public function delete(User $user, SavedTraveler $traveler): bool
    {
        return $this->ownsTraveler($user, $traveler);
    }

    protected function ownsTraveler(User $user, SavedTraveler $traveler): bool
    {
        if ($user->isCustomer()) {
            return (int) $traveler->user_id === (int) $user->id;
        }

        if ($user->isAgentPortalUser()) {
            if (! $user->hasAgentPermission(AgentPermission::TravelersManage)) {
                return false;
            }

            return $user->current_agency_id !== null
                && (int) $traveler->agency_id === (int) $user->current_agency_id
                && in_array((int) $traveler->user_id, $user->ownerAgentPortalUserIds(), true);
        }

        return false;
    }
}
