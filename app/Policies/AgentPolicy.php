<?php

namespace App\Policies;

use App\Models\Agent;
use App\Models\User;
use App\Support\Agents\AgentPermission;

class AgentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isPlatformAdmin();
    }

    public function view(User $user, Agent $agent): bool
    {
        if ($user->isPlatformAdmin()) {
            return true;
        }

        if ($user->isAgent()) {
            return $agent->user_id === $user->id;
        }

        if ($user->isAgentStaff() && $user->hasAgentPermission(AgentPermission::AgencyView)) {
            return $user->employerAgent()?->id === $agent->id;
        }

        return false;
    }

    public function viewWallet(User $user, Agent $agent): bool
    {
        if ($user->isPlatformAdmin()) {
            return true;
        }

        if ($user->isAgentAdmin()) {
            return $agent->user_id === $user->id;
        }

        return $user->isAgentStaff()
            && $user->employerAgent()?->id === $agent->id
            && $user->hasAgentPermission(AgentPermission::WalletView);
    }

    public function viewLedger(User $user, Agent $agent): bool
    {
        if ($user->isPlatformAdmin()) {
            return true;
        }

        if ($user->isAgentAdmin()) {
            return $agent->user_id === $user->id;
        }

        return $user->isAgentStaff()
            && $user->employerAgent()?->id === $agent->id
            && $user->hasAgentPermission(AgentPermission::LedgerView);
    }

    public function updateAgency(User $user, Agent $agent): bool
    {
        return $user->isAgent() && $agent->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->isPlatformAdmin();
    }

    public function update(User $user, Agent $agent): bool
    {
        return $user->isPlatformAdmin();
    }

    public function suspend(User $user, Agent $agent): bool
    {
        return $this->update($user, $agent);
    }
}
