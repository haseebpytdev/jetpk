<?php

namespace App\Policies;

use App\Models\AgentDepositRequest;
use App\Models\User;
use App\Support\Agents\AgentPermission;

class AgentDepositRequestPolicy
{
    public function viewAny(User $user): bool
    {
        if ($user->isAgentPortalUser()) {
            return $user->hasAgentPermission(AgentPermission::WalletView) && $user->agent() !== null;
        }

        return $user->isPlatformAdmin();
    }

    public function view(User $user, AgentDepositRequest $deposit): bool
    {
        if ($user->isPlatformAdmin()) {
            return true;
        }

        if ($user->isAgentPortalUser()) {
            if (! $user->hasAgentPermission(AgentPermission::WalletView)) {
                return false;
            }

            $agent = $user->agent();

            return $agent !== null && $agent->id === $deposit->agent_id;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->isAgentPortalUser()
            && $user->hasAgentPermission(AgentPermission::PaymentsUpload)
            && $user->agent() !== null;
    }

    public function approve(User $user, AgentDepositRequest $deposit): bool
    {
        return $user->isPlatformAdmin();
    }

    public function reject(User $user, AgentDepositRequest $deposit): bool
    {
        return $this->approve($user, $deposit);
    }

    public function downloadProof(User $user, AgentDepositRequest $deposit): bool
    {
        return $this->approve($user, $deposit);
    }
}
