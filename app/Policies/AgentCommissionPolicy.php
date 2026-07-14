<?php

namespace App\Policies;

use App\Models\Agent;
use App\Models\AgentCommissionEntry;
use App\Models\AgentCommissionStatement;
use App\Models\User;

class AgentCommissionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isPlatformAdmin() || $user->isAgent();
    }

    public function view(User $user, Agent|AgentCommissionEntry|AgentCommissionStatement $resource): bool
    {
        if ($user->isPlatformAdmin()) {
            return true;
        }

        if ($user->isAgent()) {
            $agent = $user->agent();
            if ($agent === null) {
                return false;
            }

            $agentId = $resource instanceof Agent
                ? $resource->id
                : $resource->agent_id;

            return $agent->id === $agentId;
        }

        return false;
    }

    public function approve(User $user, AgentCommissionEntry $entry): bool
    {
        return $user->isPlatformAdmin();
    }

    public function reject(User $user, AgentCommissionEntry $entry): bool
    {
        return $this->approve($user, $entry);
    }

    public function adjust(User $user, Agent $agent): bool
    {
        return $user->isPlatformAdmin();
    }

    public function payout(User $user, Agent $agent): bool
    {
        return $this->adjust($user, $agent);
    }

    public function statement(User $user, Agent $agent): bool
    {
        return $this->adjust($user, $agent);
    }
}
