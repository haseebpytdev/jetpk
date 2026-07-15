<?php

namespace App\Policies;

use App\Enums\AccountType;
use App\Models\User;
use App\Support\Agents\AgentPermission;

class AgentStaffPolicy
{
    public function viewAny(User $actor): bool
    {
        return $this->canManageStaff($actor);
    }

    public function view(User $actor, User $staff): bool
    {
        return $this->ownsStaff($actor, $staff);
    }

    public function create(User $actor): bool
    {
        return $this->canManageStaff($actor);
    }

    public function update(User $actor, User $staff): bool
    {
        if ($actor->isAgentStaff() && (int) $actor->id === (int) $staff->id) {
            return false;
        }

        if (! $this->ownsStaff($actor, $staff)) {
            return false;
        }

        if ($actor->isAgentAdmin()) {
            return true;
        }

        return $actor->isAgentStaff() && $actor->hasAgentPermission(AgentPermission::StaffManage);
    }

    public function delete(User $actor, User $staff): bool
    {
        return $actor->isAgentAdmin() && $this->ownsStaff($actor, $staff);
    }

    protected function canManageStaff(User $actor): bool
    {
        if ($actor->isAgentAdmin()) {
            return $actor->agent() !== null;
        }

        return $actor->isAgentStaff() && $actor->hasAgentPermission(AgentPermission::StaffManage);
    }

    protected function ownsStaff(User $actor, User $staff): bool
    {
        if ($staff->account_type !== AccountType::AgentStaff) {
            return false;
        }

        $ownerAgent = $actor->isAgentAdmin() ? $actor->agent() : $actor->employerAgent();
        if ($ownerAgent === null) {
            return false;
        }

        return (int) ($staff->meta['owner_agent_id'] ?? 0) === $ownerAgent->id
            && $staff->current_agency_id === $ownerAgent->agency_id;
    }
}
